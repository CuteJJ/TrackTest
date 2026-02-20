<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['pfp_path'] = !empty($user['pfp_path']) ? $user['pfp_path'] : 'imgs/default_pfp.svg';

        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        Helper::setFlash("Welcome back, " . htmlspecialchars($user['full_name']), "success");
        header("Location: ../index.php");
        exit();
    } else {
        Helper::setFlash("Invalid username or password.", "error");
        header("Location: ../login.php");
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}