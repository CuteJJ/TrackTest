<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        
        // Blocked User Check
        if (isset($user['status']) && $user['status'] === 'blocked') {
            Helper::setFlash("Your account has been suspended. Contact Administration.", "error");
            header("Location: ../login.php");
            exit();
        }

        session_regenerate_id(true);

        // Set session variables for logged-in user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['pfp_path'] = !empty($user['pfp_path']) ? $user['pfp_path'] : 'imgs/default_pfp.svg';

        // Update last login time
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // ----------------------------------------------------
        // REMEMBER ME LOGIC
        // ----------------------------------------------------
        if (isset($_POST['remember_me']) && $_POST['remember_me'] == 'on') {
            $token = bin2hex(random_bytes(32)); // Generate secure 64-char token
            
            // Save to DB
            $updToken = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $updToken->execute([$token, $user['id']]);
            
            // Set cookie for 30 days
            setcookie('rmb_token', $token, time() + (86400 * 30), "/", "", isset($_SERVER['HTTPS']), true);
        } else {
            // They didn't check the box, ensure token is cleared
            $updToken = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            $updToken->execute([$user['id']]);
            setcookie('rmb_token', '', time() - 3600, "/");
        }

        Helper::setFlash("Welcome back, " . htmlspecialchars($user['full_name']), "success");
        
        // Role-based routing
        if ($user['role'] === 'admin') {
            header("Location: ../admin/admin_dashboard.php");
        } else {
            header("Location: ../index.php");
        }
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