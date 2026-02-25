<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $pwd = password_hash('1234', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$_POST['username'], $pwd, $_POST['full_name'], $_POST['role']]);
        Helper::setFlash("User created. Default password is '1234'.", "success");
    } 
    elseif (isset($_POST['edit_user'])) {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ? WHERE id = ?");
        $stmt->execute([$_POST['full_name'], $_POST['role'], $_POST['user_id']]);
        Helper::setFlash("User updated.", "success");
    }
    elseif (isset($_POST['reset_pass'])) {
        $pwd = password_hash('1234', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$pwd, $_POST['user_id']]);
        Helper::setFlash("Password reset to '1234'.", "success");
    }
    elseif (isset($_POST['reset_pfp'])) {
        $pdo->prepare("UPDATE users SET pfp_path = NULL WHERE id = ?")->execute([$_POST['user_id']]);
        Helper::setFlash("Profile picture removed.", "success");
    }
    elseif (isset($_POST['toggle_status'])) {
        $new_status = $_POST['current_status'] === 'active' ? 'blocked' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $_POST['user_id']]);
        Helper::setFlash("User status changed to $new_status.", "success");
    }
    header("Location: admin_users.php"); exit();
}

$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, full_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../app.css">
</head>
<body>
    <?php Helper::displayFlash(); ?>
    <nav class="navbar"><div class="nav-brand"><span class="nav-brand-dot"></span> Admin Center</div></nav>

    <button class="admin-burger" onclick="toggleAdminSidebar()"><span class="material-symbols-outlined">menu</span></button>
    <div class="admin-overlay" id="adminOverlay" onclick="toggleAdminSidebar()"></div>
    <aside class="admin-sidebar" id="adminSidebar">
        <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); margin: 10px 0 10px 16px;">MANAGEMENT</div>
        <a href="admin_dashboard.php" class="admin-nav-item"><span class="material-symbols-outlined">dashboard</span> Home Overview</a>
        <a href="admin_history.php" class="admin-nav-item"><span class="material-symbols-outlined">history</span> Global History</a>
        <a href="admin_printers.php" class="admin-nav-item"><span class="material-symbols-outlined">print</span> Printers & Cases</a>
        <a href="admin_users.php" class="admin-nav-item active"><span class="material-symbols-outlined">group</span> User Directory</a>
    </aside>

    <div class="page-content-scroll">
        <main class="admin-content">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 style="font-size: 1.6rem; font-weight: 800; margin:0;">User Directory</h1>
                <button onclick="openModal('addModal')" class="btn" style="width: auto;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align:middle;">add</span> Add New User</button>
            </div>

            <div class="d-card">
                <table class="d-table">
                    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr style="<?= $u['status'] === 'blocked' ? 'opacity: 0.6; background: var(--bg-body);' : '' ?>">
                            <td style="display:flex; align-items:center; gap:10px;">
                                <img src="../<?= $u['pfp_path'] ?? 'imgs/default_pfp.svg' ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border: 1px solid var(--border);">
                                <strong style="<?= $u['status'] === 'blocked' ? 'text-decoration: line-through;' : '' ?>"><?= htmlspecialchars($u['full_name']) ?></strong>
                            </td>
                            <td><span style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($u['username']) ?></span></td>
                            <td><span class="badge badge-pending"><?= ucfirst($u['role']) ?></span></td>
                            <td>
                                <span class="badge <?= $u['status'] === 'active' ? 'badge-pass' : 'badge-fail' ?>"><?= strtoupper($u['status']) ?></span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <button onclick="openEdit(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>', '<?= $u['role'] ?>')" class="btn-mini ghost">Edit</button>
                                    
                                    <form method="POST" onsubmit="return confirm('Reset password to 1234?');">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="reset_pass" class="btn-mini ghost">Reset Pwd</button>
                                    </form>

                                    <form method="POST" onsubmit="return confirm('Change block status?');">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                        <button type="submit" name="toggle_status" class="btn-mini <?= $u['status'] === 'active' ? 'ghost' : '' ?>" style="<?= $u['status'] === 'active' ? 'color:var(--error); border-color:var(--error);' : '' ?>">
                                            <?= $u['status'] === 'active' ? 'Block' : 'Unblock' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <h3 style="margin-top:0; font-size:1.2rem;">Create User</h3>
            <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom: 20px;">New users will be assigned the default password <strong>1234</strong>.</p>
            <form method="POST">
                <div class="form-group"><input type="text" name="username" class="form-control" required><label class="form-label">Username</label></div>
                <div class="form-group"><input type="text" name="full_name" class="form-control" required><label class="form-label">Full Name</label></div>
                <div class="form-group" style="margin-top: 20px;">
                    <label style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Account Role</label>
                    <select name="role" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border); background:var(--bg-body); color:var(--text-main); margin-top:5px; font-family:var(--font-body);">
                        <option value="tester">Tester</option>
                        <option value="lead">Lead</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px; margin-top:24px;">
                    <button type="submit" name="add_user" class="btn">Create Account</button>
                    <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <h3 style="margin-top:0; font-size:1.2rem;">Edit User</h3>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_id">
                <div class="form-group"><input type="text" name="full_name" id="edit_name" class="form-control" required><label class="form-label">Full Name</label></div>
                <div class="form-group" style="margin-top: 20px;">
                    <label style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Account Role</label>
                    <select name="role" id="edit_role" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border); background:var(--bg-body); color:var(--text-main); margin-top:5px; font-family:var(--font-body);">
                        <option value="tester">Tester</option>
                        <option value="lead">Lead</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:24px; align-items: center;">
                    <div style="display:flex; gap:10px; flex: 1;">
                        <button type="submit" name="edit_user" class="btn">Save Changes</button>
                        <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('editModal')">Cancel</button>
                    </div>
                    <button type="submit" name="reset_pfp" class="btn-mini ghost" style="margin-left: 10px;" title="Reset Avatar">Clear PFP</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../app.js"></script>
    <script>
        function openEdit(id, name, role) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_role').value = role;
            
            // Force MUI label to float immediately since value is injected
            document.getElementById('edit_name').focus();
            document.getElementById('edit_name').blur();
            
            openModal('editModal');
        }
    </script>
</body>
</html>