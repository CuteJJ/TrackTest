<?php
// logout.php
require_once 'configs/helper.php'; // Access to session_start() via helper if needed, but best to be explicit here.

// 1. Initialize the session
// (If your helper.php already starts the session, you can skip this line, 
// but it's safe to call session_start() even if already started in newer PHP versions 
// or check if session_status() === PHP_SESSION_NONE)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Unset all session variables
$_SESSION = array();

// 3. Destroy the session cookie (Best practice for complete logout)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session
session_destroy();

// 5. Redirect to Login
header("Location: login.php");
exit();
?>