<?php
/*
 - Author : GoldenSource.iR 
 - Module Designed For The : Pay.ir
 - Mail : Mail@GoldenSource.ir
*/

use WHMCS\Database\Capsule;
if(isset($_REQUEST['invoiceId']) && is_numeric($_REQUEST['invoiceId'])){
    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';
    require_once __DIR__ . "/checkiran.php";
    if(!golden_check_iran()){
        header("Location: ../../iran.php");
        exit();
    }
    $gatewayParams = getGatewayVariables('payir');
    if(isset($_REQUEST['token'], $_REQUEST['hash'], $_REQUEST['callback']) && $_REQUEST['callback'] == 1){
        $invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->first();
        if(!$invoice){
            die("Invoice not found");
        }
        $response = payir_request('https://pay.ir/pg/verify', [
            'api'          => $gatewayParams['testMode'] == 'on' ? 'test' : $gatewayParams['apiToken'],
            'token'        => $token,
        ]);
        if($response !== false){
                if(isset($response['status'])){
                    if($response['status'] == 1){
                        if ($response['factorNumber'] == $invoice->id) {
                            $amount = $response['amount'] / ($gatewayParams['currencyType'] == 'IRT' ? 10 : 1);
                            $hash = sha1($invoice->id . $response['amount'] . ($gatewayParams['testMode'] == 'on' ? 'test' : $gatewayParams['apiToken']));
                            if ($_REQUEST['hash'] == $hash) {
                                logTransaction($gatewayParams['name'], $_REQUEST, 'Success');
                                addInvoicePayment(
                                $invoice->id,
                                $response['transId'],
                                $amount,
                                0,
                                'payir'
                            );
                            } else {
                                logTransaction($gatewayParams['name'], array(
                                'Code'        => 'Invalid Amount',
                                'Message'     => 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد',
                                'Transaction' => $response['transId'],
                                'Invoice'     => $invoice->id,
                                'Amount'      => $amount,
                            ), 'Failure');
                            }
                        }
                    } else {
                        logTransaction($gatewayParams['name'], array(
                            'Code'        => isset($response['errorCode']) ? $response['errorCode'] : 'Verify',
                            'Message'     => isset($response['errorMessage']) ? $response['errorMessage'] : 'در ارتباط با وب سرویس Pay.ir خطایی رخ داده است',
                            'Transaction' => $response['transId'],
                            'Invoice'     => $invoice->id,
                        ), 'Failure');
                    }
                    header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoice->id);
                }
        } else {
            echo 'اتصال به درگاه امکان پذیر نیست.';
        }
    } else if(isset($_SESSION['uid'])){
        $invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->where('userid', $_SESSION['uid'])->first();
        if(!$invoice){
            die("Invoice not found");
        }
        $client = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();
        $hash = sha1($invoice->id . ($invoice->total * ($gatewayParams['currencyType'] == 'IRT' ? 10 : 1)) . ($gatewayParams['testMode'] == 'on' ? 'test' : $gatewayParams['apiToken']));
        $response = payir_request('https://pay.ir/pg/send', [
            'api'          => $gatewayParams['testMode'] == 'on' ? 'test' : $gatewayParams['apiToken'],
            'amount'       => $invoice->total * ($gatewayParams['currencyType'] == 'IRT' ? 10 : 1),
            'redirect'     => $gatewayParams['systemurl'] . '/modules/gateways/payir.php?invoiceId=' . $invoice->id . '&callback=1&hash=' . $hash,
            'mobile'       => null,
            'factorNumber' => $invoice->id,
            'description'  => sprintf('پرداخت فاکتور #%s', $invoice->id),
        ]);
        if($response !== false){
            if($response['status'] == 1){
                header("Location: https://pay.ir/pg/{$response['token']}");
            } else {
                $text = 'اتصال به درگاه پرداخت ناموفق بود.';
                $text .= '<br />';
                $text .= 'کد خطا: %s';
                $text .= '<br />';
                $text .= 'متن خطا: %s';
                echo sprintf($text, $response['errorCode'], $response['errorMessage']);
            }
        } else {
            echo 'اتصال به درگاه امکان پذیر نیست.';
        }
    }
    return;
}

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

function payir_request($url, $params)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
	]);
	$res = curl_exec($ch);
	curl_close($ch);
	$response = json_decode($res, true);
    if(json_last_error() == JSON_ERROR_NONE){
        return $response;
    }
    return false;
}

function payir_MetaData()
{
    return array(
        'DisplayName' => 'ماژول پرداخت آنلاین Pay.IR برای WHMCS',
        'APIVersion' => '1.0',
    );
}

function payir_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Pay.IR',
        ),
        'currencyType' => array(
            'FriendlyName' => 'نوع ارز',
            'Type' => 'dropdown',
            'Options' => array(
                'IRR' => 'ریال',
                'IRT' => 'تومان',
            ),
        ),
        'apiToken' => array(
            'FriendlyName' => 'کد API',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'کد api دریافتی از سایت Pay.ir',
        ),
        'testMode' => array(
            'FriendlyName' => 'حالت تستی',
            'Type' => 'yesno',
            'Description' => 'برای فعال کردن حالت تستی تیک بزنید',
        ),
    );
}

function payir_link($params)
{
    $htmlOutput = '<form method="GET" action="modules/gateways/payir.php">';
    $htmlOutput .= '<input type="hidden" name="invoiceId" value="' . $params['invoiceid'] .'">';
    $htmlOutput .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}
