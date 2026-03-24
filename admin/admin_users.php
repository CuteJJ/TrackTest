<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole(['admin','lead']);

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
        Helper::setFlash("Profile picture cleared.", "success");
    }
    elseif (isset($_POST['toggle_status'])) {
        $new_status = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $_POST['user_id']]);
        Helper::setFlash("User status updated.", "success");
    }
    header("Location: admin_users.php"); exit();
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();

$TITLE = "User Management | Track Manager";
$ASSET_PATH = "../";
require_once '../configs/header.php';
?>
<style>
    .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { margin:0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
    .unified-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: visible !important; margin-bottom: 30px; padding-bottom: 8px;}
    .table-section { overflow-x: auto; width: 100%; border-radius: 12px; }
    .d-table { width: 100%; min-width: 800px; border-collapse: collapse; }
    .d-table th, .d-table td { white-space: nowrap !important; }
</style>

<?php require_once 'admin_nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">group</span>
                User Management
            </h1>
            <button class="btn" style="width:auto;" onclick="openModal('addModal')">
                <span class="material-symbols-outlined">person_add</span> Add User
            </button>
        </div>

        <div class="unified-card">
            <div class="table-section">
                <table class="d-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th style="text-align:right; padding-right:24px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): 
                            $pfp = !empty($u['pfp_path']) ? '../' . $u['pfp_path'] : '../imgs/default_pfp.svg';
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <img src="<?= htmlspecialchars($pfp) ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid var(--border);">
                                        <strong style="font-size:0.9rem; color:var(--text-main);"><?= htmlspecialchars($u['full_name']) ?></strong>
                                    </div>
                                </td>
                                <td class="mono" style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($u['username']) ?></td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-fail' : ($u['role'] === 'lead' ? 'badge-smoke' : 'badge-pass') ?>" style="text-transform:uppercase;">
                                        <?= $u['role'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($u['status'] === 'active'): ?>
                                        <span class="badge" style="background:rgba(16,185,129,0.1); color:#10b981; border:1px solid #10b981;">Active</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid #ef4444;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                    <?= $u['last_login'] ? date('M d, Y g:ia', strtotime($u['last_login'])) : 'Never' ?>
                                </td>
                                <td style="text-align:right; padding-right:24px;">
                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                        <button class="icon-btn tooltip-trigger" data-tip="Edit Info" onclick="openEdit(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', '<?= $u['role'] ?>')">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reset password to 1234?');">
                                            <input type="hidden" name="reset_pass" value="1">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="icon-btn tooltip-trigger" data-tip="Reset Password"><span class="material-symbols-outlined">key</span></button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                            <button type="submit" class="icon-btn tooltip-trigger <?= $u['status'] === 'active' ? 'delete' : '' ?>" data-tip="<?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                                <span class="material-symbols-outlined"><?= $u['status'] === 'active' ? 'block' : 'check_circle' ?></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button class="modal-close-btn" onclick="closeModal('addModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="add_user" value="1">
            <div class="form-group">
                <input type="text" name="full_name" class="form-control" required>
                <label>Full Name</label>
            </div>
            <div class="form-group">
                <input type="text" name="username" class="form-control" required>
                <label>Login ID (Username)</label>
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom:8px; display:block;">Role</label>
                <select name="role" class="form-control">
                    <option value="tester">Tester</option>
                    <option value="lead">Lead</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn" style="width:100%; margin-top:24px;">Create User</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="modal-close-btn" onclick="closeModal('editModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="user_id" id="edit_id">
            <div class="form-group">
                <input type="text" name="full_name" id="edit_name" class="form-control" required>
                <label>Full Name</label>
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom:8px; display:block;">Role</label>
                <select name="role" id="edit_role" class="form-control">
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

<script>
    function openEdit(id, name, role) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_role').value = role;
        
        // Force label to float
        document.getElementById('edit_name').focus();
        document.getElementById('edit_name').blur();
        
        openModal('editModal');
    }
</script>
</body>
</html>