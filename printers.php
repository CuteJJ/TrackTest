<?php
// printers.php

// FIX: PREVENT BROWSER CACHING (Must be called BEFORE any HTML output)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'configs/helper.php';
Helper::requireManagementRole();

$TITLE = "Printers | Track Manager";
require_once 'configs/header.php';
require_once 'configs/nav.php'; 

require_once 'shared/printer_manager.php';
?>