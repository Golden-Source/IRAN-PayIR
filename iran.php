<?php

define("CLIENTAREA", true);
require("init.php");

$ca = new WHMCS_ClientArea();

$ca->setPageTitle("آی پی غیرمجاز");

$ca->addToBreadCrumb('index.php', $whmcs->get_lang('globalsystemname'));
$ca->addToBreadCrumb('iran.php', 'آی پی غیرمجاز');

$ca->initPage();
$ca->assign('variablename', $value);

if ($ca->isLoggedIn()) {
  $result = mysql_query("SELECT firstname FROM tblclients WHERE id=" . $ca->getUserID());
  $data = mysql_fetch_array($result);
  $clientname = $data[0];
  $ca->assign('clientname', $clientname);
} else {
}

$ca->setTemplate('iran');

$ca->output();
?>
