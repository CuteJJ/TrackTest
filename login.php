<?php
require_once 'configs/helper.php';

// Auto-Login check (Keep your existing logic)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rmb_token'])) {
    require_once 'configs/db.php';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->execute([$_COOKIE['rmb_token']]);
    $user = $stmt->fetch();
    if ($user && (!isset($user['status']) || $user['status'] !== 'blocked')) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['pfp_path'] = !empty($user['pfp_path']) ? $user['pfp_path'] : 'imgs/default_pfp.svg';
        session_regenerate_id(true);
        header("Location: index.php");
        exit();
    }
}

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Track Manager</title>
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
    
    /* --- LEFT SIDE: FORM --- */
    .login-left { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 20px; position: relative; overflow-y: auto; }
    .form-wrapper { width: 100%; max-width: 380px; }
    .brand-logo { display: flex; align-items: center; gap: 10px; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 40px; letter-spacing: -0.5px; }
    .brand-dot { width: 12px; height: 12px; background: var(--primary); border-radius: 4px; }
    .login-title { font-size: 2rem; font-weight: 800; color: var(--text-main); letter-spacing: -1px; margin: 0 0 8px 0; }
    .login-subtitle { font-size: 0.95rem; color: var(--text-muted); margin: 0 0 32px 0; }
    .login-form .form-group { margin-bottom: 24px; width: 100%; position: relative; }

    /* --- SIMPLE FLOATING LABEL CSS --- */
    .form-control {
        width: 100%;
        padding: 20px 14px 6px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        background: var(--bg-surface);
        color: var(--text-main);
        font-size: 0.95rem;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.15s;
        height: 52px;
    }
    .form-control:focus {
        border-color: var(--primary);
    }
    
    .form-label {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%) scale(1);
        transform-origin: left top;
        pointer-events: none;
        transition: all 0.15s ease;
        color: var(--text-muted);
        font-size: 0.95rem;
        background-color: transparent;
    }
    
    /* Move label when user types or focuses */
    .form-control:focus ~ .form-label,
    .form-control:valid ~ .form-label {
        transform: translateY(-160%) scale(0.85);
        color: var(--primary);
        background-color: var(--bg-surface);
        padding: 0 4px;
    }

    /* Login Actions */
    .login-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; width: 100%; }
    
    .custom-checkbox { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; user-select: none; transition: color 0.2s; }
    .custom-checkbox:hover { color: var(--text-main); }
    .custom-checkbox input { display: none; }
    .checkmark { width: 16px; height: 16px; border: 2px solid var(--border); border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; background: var(--bg-body); }
    .custom-checkbox:hover .checkmark { border-color: var(--primary); }
    .custom-checkbox input:checked ~ .checkmark { background: var(--primary); border-color: var(--primary); }
    .custom-checkbox input:checked ~ .checkmark::after { content: ''; width: 3px; height: 7px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); margin-bottom: 2px; }
    
    .forgot-link { color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: color 0.15s; }
    .forgot-link:hover { text-decoration: underline; color: var(--primary-hover); }

    .btn-login { width: 100%; padding: 14px; font-size: 0.95rem; font-weight: 700; border-radius: 8px; background: var(--primary); color: white; border: none; cursor: pointer; transition: background 0.2s, transform 0.1s; }
    .btn-login:hover { background: var(--primary-hover); }
    .btn-login:active { transform: scale(0.98); }
    .login-footer { margin-top: 32px; font-size: 0.85rem; color: var(--text-muted); }
    .login-footer a { color: var(--primary); text-decoration: none; font-weight: 600; }
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

    <!-- 
      ULTIMATE FIX: This script runs BEFORE the browser paints anything.
      It instantly clears the values, preventing Chrome from overriding them.
    -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Force blank values immediately on load
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            
            // Re-run after 50ms and 200ms to catch delayed Chrome autofill
            setTimeout(function() {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
            }, 50);
            
            setTimeout(function() {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
            }, 200);
        });
    </script>

    <?php Helper::displayFlash(); ?>

    <div class="login-left">
        <div class="form-wrapper">
            <div class="brand-logo">
                <div class="brand-dot"></div>
                Track Manager
            </div>

            <h1 class="login-title">Log in</h1>
            <p class="login-subtitle">Enter your details to access your.</p>

            <form action="controllers/LoginController.php" method="POST" class="login-form">
                
                <div class="form-group">
                    <input type="text" name="username" id="username" class="form-control" required>
                    <label for="username" class="form-label">Username</label>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="password" class="form-control" required>
                    <label for="password" class="form-label">Password</label>
                </div>

                <div class="login-actions">
                    <label class="custom-checkbox">
                        <input type="checkbox" name="remember_me">
                        <span class="checkmark"></span>
                        Remember Me
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

                <div class="login-footer">
                Need help? No account? Contact PIC.
            </div>
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
        <div class="showcase-text">Good work today.</div>
    </div>
    
    <script src="app.js" defer></script>
</body>
</html>