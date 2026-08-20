<?php
// test_cases.php
require_once 'configs/helper.php';
Helper::requireManagementRole();

$TITLE = "Test Cases | Track Manager";
require_once 'configs/header.php';
require_once 'configs/nav.php'; // LOADS THE SIDEBAR

require_once 'shared/test_case_manager.php';
?>