<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// MANUAL PHPMailer loading (Assumes your 'lib' folder is in the root directory next to 'controllers')
require __DIR__ . '/../lib/Exception.php'; // Required for error handling
require __DIR__ . '/../lib/PHPMailer.php';
require __DIR__ . '/../lib/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

$action = $_POST['action'] ?? '';

// ==========================================
// ACTION 1: REQUEST PASSWORD RESET LINK
// ==========================================
if ($action === 'request_reset') {
    $email = trim($_POST['email']);

    // 1. Check if user exists
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Generate secure token & expiry (1 Hour)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // 3. Save to database
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires]);

        // 4. Construct Reset URL
        // Adjust the base URL to match your local/production environment
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'];
        // Assume 'controllers' is 1 directory deep
        $dir = dirname($_SERVER['REQUEST_URI']); 
        if(str_ends_with($dir, '/controllers')) {
            $dir = str_replace('/controllers', '', $dir);
        }
        $resetLink = $protocol . "://" . $domain . $dir . "/reset_password.php?token=" . $token;

        // 5. Send Email via PHPMailer using Gmail SMTP
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = Helper::getEnv('GMAIL_USERNAME');         
            $mail->Password   = Helper::getEnv('GMAIL_APP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('no-reply@trackmanager.local', 'Track Manager');
            $mail->addAddress($email, $user['full_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - Track Manager';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #0288d1;'>Password Reset Request</h2>
                    <p>Hello {$user['full_name']},</p>
                    <p>We received a request to reset your Track Manager password. Click the button below to choose a new password. This link will expire in 1 hour.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' style='background-color: #0288d1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Reset Password</a>
                    </div>
                    <p style='color: #64748b; font-size: 0.85rem;'>If you did not request a password reset, please ignore this email.</p>
                </div>
            ";

            $mail->send();
        } catch (Exception $e) {
            // Silently log the error, don't expose SMTP errors to the user
            error_log("Mailer Error: {$mail->ErrorInfo}");
        }
    }

    // Always show success message to prevent email enumeration attacks
    Helper::setFlash("If an account with that email exists, a password reset link has been sent.", "success");
    header("Location: ../login.php");
    exit();
}

// ==========================================
// ACTION 2: UPDATE NEW PASSWORD
// ==========================================
if ($action === 'update_password') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Verify passwords match
    if ($password !== $confirm_password) {
        Helper::setFlash("Passwords do not match.", "error");
        header("Location: ../reset_password.php?token=" . htmlspecialchars($token));
        exit();
    }

    // 2. Validate Token
    $stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();

    if (!$resetData) {
        Helper::setFlash("Invalid or expired password reset token.", "error");
        header("Location: ../login.php");
        exit();
    }

    // 3. Check Expiry
    if (strtotime($resetData['expires_at']) < time()) {
        Helper::setFlash("This password reset link has expired. Please request a new one.", "error");
        header("Location: ../forgot_password.php");
        exit();
    }

    // 4. Update the Password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashedPassword, $resetData['email']]);

    // 5. Clean up old tokens for this email
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
    $stmt->execute([$resetData['email']]);

    // 6. Force log out across all devices (Wipe Remember Me token)
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE email = ?");
    $stmt->execute([$resetData['email']]);

    Helper::setFlash("Your password has been successfully updated. You may now log in.", "success");
    header("Location: ../login.php");
    exit();
}

// Fallback
header("Location: ../login.php");
exit();