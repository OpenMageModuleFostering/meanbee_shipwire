<?php
require_once '../../../../Mage.php';

Varien_Profiler::enable();
Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1);

umask(0);
Mage::app();

$obj = Mage::getModel('shipwire/call');
$obj->home();
?>