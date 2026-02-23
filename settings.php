<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

Helper::requireLogin();
$user_id = $_SESSION['user_id'];

// --- 1. Process Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // A. Upload & Crop Profile Picture
        if (isset($_POST['update_pfp']) && !empty($_POST['cropped_image'])) {
            $base64 = $_POST['cropped_image'];
            
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                $data = substr($base64, strpos($base64, ',') + 1);
                $type = strtolower($type[1]); 

                if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                    throw new Exception('Invalid image format.');
                }
                
                $data = base64_decode($data);
                if ($data === false) throw new Exception('Image decode failed.');
                
                $dir = 'imgs/profile_pics/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $filename = $dir . 'user_' . $user_id . '_' . time() . '.png';
                file_put_contents($filename, $data);

                // Update DB
                $stmt = $pdo->prepare("UPDATE users SET pfp_path = ? WHERE id = ?");
                $stmt->execute([$filename, $user_id]);
                
                $_SESSION['pfp_path'] = $filename; // Update session
                Helper::setFlash("Profile picture updated successfully!", "success");
            }
        }

        // B. Update Personal Info
        if (isset($_POST['update_info'])) {
            $full_name = trim($_POST['full_name']);
            $username = trim($_POST['username']);
            
            if(empty($full_name) || empty($username)) throw new Exception("Name and Username are required.");
            
            // Ensure unique username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if($stmt->fetch()) throw new Exception("That username is already taken.");

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ?");
            $stmt->execute([$full_name, $username, $user_id]);
            
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            Helper::setFlash("Profile information updated!", "success");
        }

        // C. Update Password
        if (isset($_POST['update_password'])) {
            $curr = $_POST['current_password'];
            $new = $_POST['new_password'];
            $conf = $_POST['confirm_password'];

            if($new !== $conf) throw new Exception("New passwords do not match.");
            if(strlen($new) < 6) throw new Exception("Password must be at least 6 characters.");

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if(!password_verify($curr, $user['password'])) throw new Exception("Current password is incorrect.");

            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
            Helper::setFlash("Password changed successfully!", "success");
        }

        // PRG Pattern to clear POST data
        header("Location: settings.php");
        exit();

    } catch (Exception $e) {
        Helper::setFlash($e->getMessage(), "error");
        header("Location: settings.php");
        exit();
    }
}

// --- 2. Fetch User Data ---
$stmt = $pdo->prepare("SELECT username, full_name, role, pfp_path, last_login FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u = $stmt->fetch();
$pfp = !empty($u['pfp_path']) ? $u['pfp_path'] : 'imgs/default_pfp.svg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    
    <link rel="stylesheet" href="app.css">
    <script src="app.js" defer></script>
    <style>
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 99px; /* Pill shape */
            background: var(--bg-body);
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-back:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateX(-4px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        .btn-back .material-symbols-outlined {
            font-size: 18px;
            transition: transform 0.3s ease;
        }
        .btn-back:hover .material-symbols-outlined {
            transform: translateX(-4px);
        }
    </style>
</head>
<body>

<?php Helper::displayLoader(); ?>
<?php Helper::displayFlash(); ?>

<nav class="navbar">
    <div class="nav-brand">
        <span class="nav-brand-dot"></span>
        Account Settings
    </div>
    <div class="nav-right">
        <a href="index.php" class="btn-back">
            <span class="material-symbols-outlined">arrow_back</span> Back to Dashboard
        </a>
    </div>
</nav>

<div class="dash-wrapper" style="padding-top: 40px;">
    
    <div class="settings-grid">
        
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <div class="d-card">
                <div class="d-card-header">
                    <div class="d-card-title"><span class="material-symbols-outlined">account_circle</span> Profile Picture</div>
                </div>
                <div class="d-card-body padded" style="text-align: center;">
                    <div style="width: 100px; height: 100px; margin: 0 auto 20px; border-radius: 50%; padding: 4px; border: 2px solid var(--border);">
                        <img src="<?= htmlspecialchars($pfp) ?>" class="pfp-img" alt="Profile">
                    </div>
                    
                    <div class="dropzone" id="pfpDropzone">
                        <span class="material-symbols-outlined">cloud_upload</span>
                        <span class="dropzone-text">Drag & Drop image here or <strong>click to browse</strong></span>
                        <span class="dropzone-sub">Max size: 5MB (PNG, JPG)</span>
                        <input type="file" id="pfpInput" accept="image/*" class="hidden">
                    </div>
                    
                    <form method="POST" id="pfpForm" class="hidden no-loader">
                        <input type="hidden" name="update_pfp" value="1">
                        <input type="hidden" name="cropped_image" id="croppedImageInput">
                    </form>
                </div>
            </div>

            <div class="d-card">
                <div class="d-card-header">
                    <div class="d-card-title"><span class="material-symbols-outlined">info</span> System Details</div>
                </div>
                <div class="d-card-body padded">
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">System Role</span>
                            <span class="badge <?= $u['role'] === 'lead' ? 'badge-smoke' : 'badge-main' ?>" style="text-transform: uppercase;"><?= htmlspecialchars($u['role']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Last Login</span>
                            <span class="mono" style="font-size: 0.85rem; font-weight: 500;"><?= date('M d, Y g:i A', strtotime($u['last_login'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>

        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <div class="d-card">
                <div class="d-card-header">
                    <div class="d-card-title"><span class="material-symbols-outlined">badge</span> Personal Information</div>
                </div>
                <div class="d-card-body padded">
                    <form method="POST">
                        <input type="hidden" name="update_info" value="1">
           
                        <div class="form-group" style="margin-top: 10px;">
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($u['full_name']) ?>" autocomplete="off" required>
                            <label class="form-label">Full Name</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" autocomplete="off" required>
                            <label class="form-label">Username</label>
                        </div>
                        
                        <button type="submit" class="btn" style="width: auto; float: right;">Save Information</button>
                        <div style="clear: both;"></div>
                    </form>
                </div>
            </div>

            <div class="d-card">
                <div class="d-card-header">
                    <div class="d-card-title"><span class="material-symbols-outlined">lock</span> Account Security</div>
                </div>
                <div class="d-card-body padded">
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="form-group" style="margin-top: 10px;">
                            <input type="password" name="current_password" class="form-control" required>
                            <label class="form-label">Current Password</label>
                        </div>
                        
                        <div style="height: 1px; background: var(--border); margin: 20px 0;"></div>
                        
                        <div class="form-group">
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <label class="form-label">New Password</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            <label class="form-label">Confirm New Password</label>
                        </div>
                        
                        <button type="submit" class="btn" style="width: auto; float: right;">Update Password</button>
                        <div style="clear: both;"></div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="cropperModal" class="cropper-modal">
    <div class="cropper-content">
        <h3>Crop Profile Picture</h3>
        <div class="img-container">
            <img id="imageToCrop" src="">
        </div>
        <div class="cropper-actions">
            <button type="button" class="btn ghost" style="width: auto; background: #f1f5f9; color: var(--text-main); border: none;" onclick="closeCropper()">Cancel</button>
            <button type="button" class="btn" style="width: auto;" id="cropSubmitBtn">Apply Picture</button>
        </div>
    </div>
</div>

<script>
    // --- Cropper.js Drag and Drop Integration ---
    let cropper;
    const dropzone = document.getElementById('pfpDropzone');
    const fileInput = document.getElementById('pfpInput');
    const imageToCrop = document.getElementById('imageToCrop');
    const modal = document.getElementById('cropperModal');

    // Drag & Drop events
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
    });
    
    // File input event
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) handleFile(e.target.files[0]);
    });

    function handleFile(file) {
        if (!file.type.startsWith('image/')) {
            alert('Please select a valid image file.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            alert('Image exceeds 5MB size limit.');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            imageToCrop.src = e.target.result;
            modal.classList.add('show');
            
            if (cropper) cropper.destroy(); // Reset previous cropper
            
            cropper = new Cropper(imageToCrop, {
                aspectRatio: 1, // Force square crop
                viewMode: 1,    // Restrict crop box to canvas
                autoCropArea: 1,
                dragMode: 'move',
                guides: true,
                center: true,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        };
        reader.readAsDataURL(file);
    }

    function closeCropper() {
        modal.classList.remove('show');
        fileInput.value = ''; // Reset input
    }

    // Process and submit base64
    document.getElementById('cropSubmitBtn').addEventListener('click', () => {
        if (!cropper) return;
        
        window.showLoader();
        
        // Get high quality cropped canvas
        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        
        // Convert to base64 string
        const base64 = canvas.toDataURL('image/png');
        
        // Push into hidden form and submit
        document.getElementById('croppedImageInput').value = base64;
        document.getElementById('pfpForm').submit();
    });
</script>

</body>
</html>