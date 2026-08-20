<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

$action = $_POST['action'] ?? '';

// ==========================================
// ACTION 1: VERIFY STAFF ID & SHOW JOIN DATE INPUT
// ==========================================
if ($action === 'request_reset') {
    $staff_id = trim($_POST['staff_id']);

    $stmt = $pdo->prepare("SELECT id, full_name, joined_date FROM users WHERE staff_id = ?");
    $stmt->execute([$staff_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Store verified user ID in session
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_join_date'] = $user['joined_date'];
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verify Join Date | Track Manager</title>
            <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
            <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
            <link rel="stylesheet" href="../app.css">
            
            <script>
                let savedTheme = localStorage.getItem('track-manager-theme');
                if (!savedTheme) savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', savedTheme);
            </script>

            <style>
            body { margin: 0; padding: 0; display: flex; flex-direction: row !important; height: 100vh; background-color: var(--bg-surface); font-family: var(--font-body); overflow: hidden; }
            
            /* --- LEFT SIDE: FORM --- */
            .login-left { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 20px; position: relative; overflow-y: auto; background: var(--bg-body); }
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
            
            /* --- FIX: Only underline the text part --- */
            .login-footer a .hover-text { 
                transition: text-decoration 0.2s; 
            }
            .login-footer a:hover .hover-text { 
                text-decoration: underline; 
            }

            /* --- DATE INPUT STYLES --- */
            .date-input-wrapper { margin-bottom: 24px; width: 100%; }
            .date-input-wrapper label { 
                display: block; 
                font-size: 0.75rem; 
                font-weight: 700; 
                color: var(--text-muted); 
                margin-bottom: 6px; 
                text-transform: uppercase; 
                letter-spacing: 0.05em;
            }
            .date-input-wrapper input[type="date"] {
                width: 100%;
                padding: 12px 14px;
                border: 1.5px solid var(--border);
                border-radius: 8px;
                background: var(--bg-surface);
                color: var(--text-main);
                font-size: 0.95rem;
                font-family: var(--font-body);
                outline: none;
                box-sizing: border-box;
                transition: border-color 0.15s, box-shadow 0.15s;
            }
            .date-input-wrapper input[type="date"]:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
            }
            .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
                cursor: pointer;
                padding: 4px;
            }

            /* Dark Mode Calendar Icon Fix */
            [data-theme="dark"] .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator,
            [data-theme="midnight"] .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator,
            [data-theme="catppuccin"] .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
                filter: invert(0.8);
                cursor: pointer;
            }

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
            <div class="login-left">
                <div class="form-wrapper">
                    <a href="../index.php" class="brand-logo">
                        <div class="brand-dot"></div>
                        Track Manager
                    </a>
                    <h1 class="login-title">Identity Verification</h1>
                    <p class="login-subtitle">Enter your <strong>Join Date</strong> to verify your identity.</p>
                    
                    <div style="background: var(--bg-body); padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 24px; text-align: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Verifying Staff ID: <strong><?= htmlspecialchars($staff_id) ?></strong></span>
                    </div>

                    <form action="PasswordController.php" method="POST" class="login-form">
                        <input type="hidden" name="action" value="verify_date">
                        
                        <div class="date-input-wrapper">
                            <label for="join_date">Join Date</label>
                            <input type="date" name="join_date" id="join_date" required>
                        </div>
                        
                        <button type="submit" class="btn-login">
                            <span class="material-symbols-outlined">check_circle</span> Verify Date
                        </button>
                    </form>

                    <div class="login-footer">
                        <a href="../forgot_password.php">
                            <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span> 
                            <span class="hover-text">Back to Reset Password</span>
                        </a>
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
                <div class="showcase-text">Secure your workspace.</div>
            </div>
        </body>
        </html>
        <?php
        exit();
    } else {
        Helper::setFlash("Staff ID not found. Please contact your administrator.", "error");
        header("Location: ../forgot_password.php");
        exit();
    }
}

// ==========================================
// ACTION 2: VERIFY JOIN DATE
// ==========================================
if ($action === 'verify_date') {
    $input_date = trim($_POST['join_date']);
    
    if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_join_date'])) {
        Helper::setFlash("Session expired. Please start over.", "error");
        header("Location: ../forgot_password.php");
        exit();
    }

    $stored_date = $_SESSION['reset_join_date'];

    // Direct string comparison
    if ($input_date === $stored_date) {
        // Success! User can now reset their password.
        $user_id = $_SESSION['reset_user_id'];
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Set New Password | Track Manager</title>
            <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
            <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
            <link rel="stylesheet" href="../app.css">
            
            <script>
                let savedTheme = localStorage.getItem('track-manager-theme');
                if (!savedTheme) savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', savedTheme);
            </script>

            <style>
            body { margin: 0; padding: 0; display: flex; flex-direction: row !important; height: 100vh; background-color: var(--bg-surface); font-family: var(--font-body); overflow: hidden; }
            
            /* --- LEFT SIDE: FORM --- */
            .login-left { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 20px; position: relative; overflow-y: auto; background: var(--bg-body); }
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
            <div class="login-left">
                <div class="form-wrapper">
                    <a href="../index.php" class="brand-logo">
                        <div class="brand-dot"></div>
                        Track Manager
                    </a>
                    <h1 class="login-title">Set New Password</h1>
                    <p class="login-subtitle">Enter your new secure password below.</p>
                    
                    <form action="PasswordController.php" method="POST" class="login-form">
                        <input type="hidden" name="action" value="update_password">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                        
                        <div class="form-group">
                            <input type="password" name="password" id="password" class="form-control" autocomplete="off" required minlength="6">
                            <label for="password" class="form-label">New Password</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="off" required minlength="6">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                        </div>
                        
                        <button type="submit" class="btn-login">
                            <span class="material-symbols-outlined">lock_reset</span> Update Password
                        </button>
                    </form>

                    <div class="login-footer">
                        <a href="../forgot_password.php">
                            <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span> 
                            <span class="hover-text">Back to Reset Password</span>
                        </a>
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
                <div class="showcase-text">Secure your workspace.</div>
            </div>
        </body>
        </html>
        <?php
        exit();
    } else {
        // Wrong join date
        unset($_SESSION['reset_user_id'], $_SESSION['reset_join_date']);
        Helper::setFlash("Incorrect Join Date. Please try again.", "error");
        header("Location: ../forgot_password.php");
        exit();
    }
}

// ==========================================
// ACTION 3: FINALIZE NEW PASSWORD
// ==========================================
if ($action === 'update_password') {
    $user_id = $_POST['user_id'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        Helper::setFlash("Passwords do not match.", "error");
        header("Location: ../forgot_password.php");
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ?, remember_token = NULL WHERE id = ?");
    $stmt->execute([$hashedPassword, $user_id]);

    // Clean up session
    unset($_SESSION['reset_user_id'], $_SESSION['reset_join_date']);

    Helper::setFlash("Your password has been successfully updated. You may now log in.", "success");
    header("Location: ../login.php");
    exit();
}

header("Location: ../login.php");
exit();
?>