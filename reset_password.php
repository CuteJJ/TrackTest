<?php
require_once 'configs/helper.php';

// Validate token presence
if (!isset($_GET['token']) || empty($_GET['token'])) {
    Helper::setFlash("Invalid or missing password reset token.", "error");
    header("Location: login.php");
    exit();
}
$token = $_GET['token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Password | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    
    <script>
        let savedTheme = localStorage.getItem('track-manager-theme');
        if (!savedTheme) savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>

    <style>
    body { margin: 0; padding: 0; display: flex; flex-direction: row !important; height: 100vh; background-color: var(--bg-surface); font-family: var(--font-body); overflow: hidden; }
    
    .login-left { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 20px; position: relative; overflow-y: auto; }
    .form-wrapper { width: 100%; max-width: 380px; }
    .brand-logo { display: flex; align-items: center; gap: 10px; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 40px; letter-spacing: -0.5px; text-decoration: none;}
    .brand-dot { width: 12px; height: 12px; background: var(--primary); border-radius: 4px; }
    .login-title { font-size: 2rem; font-weight: 800; color: var(--text-main); letter-spacing: -1px; margin: 0 0 8px 0; }
    .login-subtitle { font-size: 0.95rem; color: var(--text-muted); margin: 0 0 32px 0; line-height: 1.5;}
    .login-form .form-group { margin-bottom: 24px; width: 100%; }

    .btn-login { width: 100%; padding: 14px; font-size: 0.95rem; font-weight: 700; border-radius: 8px; background: var(--primary); color: white; border: none; cursor: pointer; transition: background 0.2s, transform 0.1s; display: flex; align-items: center; justify-content: center; gap: 8px;}
    .btn-login:hover { background: var(--primary-hover); }
    .btn-login:active { transform: scale(0.98); }
    
    .login-footer { margin-top: 32px; font-size: 0.9rem; color: var(--text-muted); text-align: center; }
    .login-footer a { color: var(--primary); text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
    .login-footer a:hover { text-decoration: underline; }

    /* --- RIGHT SIDE: SHOWCASE --- */
    .login-right { flex: 1; display: flex; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%); position: relative; align-items: center; justify-content: center; overflow: hidden; padding: 40px; }
    .showcase-wrapper { position: relative; width: 100%; max-width: 500px; height: 400px; }
    .glass-card { position: absolute; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 16px; box-shadow: 0 24px 40px rgba(0, 0, 0, 0.1); }
    .card-main { width: 80%; height: 80%; top: 10%; left: 10%; z-index: 2; padding: 24px; display: flex; flex-direction: column; gap: 16px; box-sizing: border-box; }
    .card-floater-1 { width: 40%; height: 30%; bottom: 0; right: 0; z-index: 3; }
    .card-floater-2 { width: 30%; height: 40%; top: 0; left: 0; z-index: 1; background: rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.05); }
    .fake-line { height: 12px; border-radius: 6px; background: rgba(255, 255, 255, 0.2); }
    .w-80 { width: 80%; } .w-50 { width: 50%; } .w-100 { width: 100%; height: 60px; margin-top: auto; }
    .showcase-text { position: absolute; bottom: 40px; left: 40px; color: rgba(255, 255, 255, 0.9); font-size: 1.1rem; font-weight: 500; line-height: 1.5; max-width: 400px; }
    
    @media (max-width: 900px) { .login-right { display: none; } }
    </style>
</head>
<body>

    <?php Helper::displayFlash(); ?>

    <div class="login-left">
        <div class="form-wrapper">
            
            <a href="index.php" class="brand-logo">
                <div class="brand-dot"></div>
                Track Manager
            </a>

            <h1 class="login-title">New Password</h1>
            <p class="login-subtitle">Create a new, strong password for your account.</p>

            <form action="controllers/PasswordController.php" method="POST" class="login-form">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <input type="password" name="password" id="password" class="form-control" autocomplete="off" required minlength="8">
                    <label for="password" class="form-label">New Password</label>
                </div>

                <div class="form-group">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="off" required minlength="8">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                </div>

                <button type="submit" class="btn-login">
                    <span class="material-symbols-outlined">lock_reset</span> Reset Password
                </button>
            </form>

        </div>
    </div>

    <div class="login-right">
        <div class="showcase-wrapper">
            <div class="glass-card card-floater-2"></div>
            <div class="glass-card card-main">
                <div class="fake-line w-50"></div>
                <div class="fake-line w-80"></div>
                <div class="fake-line w-100"></div>
            </div>
            <div class="glass-card card-floater-1"></div>
        </div>
        <div class="showcase-text">Secure your workspace.</div>
    </div>
    
    <script src="app.js" defer></script>
</body>
</html>