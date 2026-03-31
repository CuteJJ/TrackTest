<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

// Check if they were forced out by the helper block check
$is_blocked = isset($_GET['blocked']) && $_GET['blocked'] == '1';

// 1. Clear Remember Me Token from DB if it exists
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// 2. Destroy Remember Me Cookie securely
setcookie('rmb_token', '', time() - 3600, "/");

// 3. Wipe all existing session variables and destroy the session completely
session_unset();
session_destroy();

// 4. Start a brand NEW, clean session specifically to hold the flash message
session_start();

// 5. Show the correct message depending on how they logged out
if ($is_blocked) {
    Helper::setFlash("Your account has been suspended by an administrator.", "error");
} else {
    Helper::setFlash("You have been successfully logged out.", "success");
}

// 6. Redirect back to login
header("Location: login.php");
exit();