<?php
// admin/admin_cases.php

// FIX: PREVENT BROWSER CACHING (Must be called BEFORE any HTML output)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once '../configs/helper.php';
Helper::requireManagementRole();

$TITLE = "Test Cases | Track Manager (Admin)";
$ASSET_PATH = "../";

require_once '../configs/header.php';
require_once 'admin_nav.php';
require_once '../shared/test_case_manager.php';
?>