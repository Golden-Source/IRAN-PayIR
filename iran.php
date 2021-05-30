<?php

define("CLIENTAREA", true);
require("init.php");

$ca = new WHMCS_ClientArea();

$ca->setPageTitle("آی پی غیرمجاز");

$ca->addToBreadCrumb('index.php', $whmcs->get_lang('globalsystemname'));
$ca->addToBreadCrumb('iran.php', 'آی پی غیرمجاز');

$ca->initPage();
$ca->assign('variablename', $value);

$ca->setTemplate('iran');

$ca->output();
?>
