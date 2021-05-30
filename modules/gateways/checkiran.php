<?php
if(!function_exists('golden_check_iran')){
    function golden_check_iran(){
        $ipAddress = function (){
            $ip_keys = [
                'HTTP_X_SUCURI_CLIENTIP',
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR',
            ];
            foreach ($ip_keys as $key) {
                if (array_key_exists($key, $_SERVER) === TRUE) {
                    foreach (explode(',', $_SERVER[$key]) as $ip) {
                        // trim for safety measures
                        $ip = trim($ip);
                        // attempt to validate IP
                        if (filter_var($ip,
                                FILTER_VALIDATE_IP,
                                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === FALSE) {
                            continue;
                        }
                        return $ip;
                    }
                }
            }
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : FALSE;
            return $ip;
        };
        $info1 = function ($ip){
            $ch = curl_init('http://api.geoiplookup.net/?query=' . $ip);
            curl_setopt_array($ch, [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $response = simplexml_load_string($response);
            $response = (array)$response->results->result;
            $data = [
                'org' => $response['isp'],
                'country' => $response['countryname'],
                'countryCode' => $response['countrycode'],
            ];
            return $data;
        };
        $info2 = function ($ip)
        {
            $ch = curl_init('http://ip-api.com/json/' . $ip);
            curl_setopt_array($ch, [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($response, true);
            $data = [
                'org' => $response['org'],
                'country' => $response['country'],
                'countryCode' => $response['countryCode'],
            ];
            file_put_contents($cacheFile, json_encode($data));
            return $data;
        };
        $ip = $ipAddress();
        $info = $info1($ip);
        if($info['countryCode'] != 'IR'){
            $info = $info2($ip);
        }
        return $info['countryCode'] == 'IR';
    }
}
