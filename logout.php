<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

// Clear Remember Me Token from DB if it exists
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Destroy Remember Me Cookie securely
setcookie('rmb_token', '', time() - 3600, "/");

// Wipe all existing session variables and destroy the session completely
session_unset();
session_destroy();

// Start a clean session specifically to hold the flash message
session_start();
Helper::setFlash("You have been successfully logged out.", "success");

// Redirect back to login
header("Location: login.php");
exit();