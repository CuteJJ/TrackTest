<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole(['admin','lead']);

// Check if admin can be added (only 1 allowed)
$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$canAddAdmin = $adminCount < 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Server-side validation: block admin creation if one exists
        if ($_POST['role'] === 'admin' && !$canAddAdmin) {
            Helper::setFlash("Only one admin account is allowed. This limit has been reached.", "error");
            header("Location: admin_users.php"); exit();
        }
        
        // Validate lengths
        if (strlen(trim($_POST['username'])) > 20) {
            Helper::setFlash("Username cannot exceed 20 characters.", "error");
            header("Location: admin_users.php"); exit();
        }
        
        // Check for duplicate username
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$_POST['username']]);
        if ($check->fetch()) {
            Helper::setFlash("Username already exists.", "error");
            header("Location: admin_users.php"); exit();
        }
        
        // Check for duplicate Staff ID (if provided)
        if (!empty($_POST['staff_id'])) {
            $checkStaff = $pdo->prepare("SELECT id FROM users WHERE staff_id = ?");
            $checkStaff->execute([$_POST['staff_id']]);
            if ($checkStaff->fetch()) {
                Helper::setFlash("Staff ID already exists.", "error");
                header("Location: admin_users.php"); exit();
            }
        }
        
        $pwd = password_hash('1234', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, status, staff_id, joined_date) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)");
        $stmt->execute([
            $_POST['username'], 
            $pwd, 
            $_POST['full_name'], 
            $_POST['email'], 
            $_POST['role'],
            !empty($_POST['staff_id']) ? $_POST['staff_id'] : null,
            !empty($_POST['joined_date']) ? $_POST['joined_date'] : null
        ]);
        Helper::setFlash("User created. Default password is '1234'.", "success");
    } 
    elseif (isset($_POST['edit_user'])) {
        if (strlen(trim($_POST['full_name'])) > 50) {
            Helper::setFlash("Full name is too long.", "error");
            header("Location: admin_users.php"); exit();
        }
        
        // Server-side validation: prevent changing role to admin if one exists
        if ($_POST['role'] === 'admin' && !$canAddAdmin) {
            $currentUser = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $currentUser->execute([$_POST['user_id']]);
            $current = $currentUser->fetch();
            if ($current['role'] !== 'admin') {
                Helper::setFlash("Only one admin account is allowed. Cannot assign admin role.", "error");
                header("Location: admin_users.php"); exit();
            }
        }
        
        // FIXED: Added username = ? to the query and passed 7 variables
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, staff_id = ?, joined_date = ?, username = ? WHERE id = ?");
        $stmt->execute([
            $_POST['full_name'], 
            $_POST['email'], 
            $_POST['role'],
            !empty($_POST['staff_id']) ? $_POST['staff_id'] : null,
            !empty($_POST['joined_date']) ? $_POST['joined_date'] : null,
            $_POST['username'],
            $_POST['user_id']
        ]);
        Helper::setFlash("User updated.", "success");
    }
    elseif (isset($_POST['reset_pfp'])) {
        $pdo->prepare("UPDATE users SET pfp_path = NULL WHERE id = ?")->execute([$_POST['user_id']]);
        Helper::setFlash("Profile picture cleared.", "success");
    }
    elseif (isset($_POST['toggle_status'])) {
        $new_status = $_POST['current_status'] === 'active' ? 'blocked' : 'active';
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
    .d-table { width: 100%; table-layout: auto !important; border-collapse: collapse; }
    .d-table th, .d-table td { white-space: nowrap !important; }
    .d-table .col-email { 
        white-space: normal !important; 
        word-break: break-all; 
        width: 25% !important; 
        min-width: 180px !important;
    }
    .admin-limit-note { font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; display: flex; align-items: center; gap: 4px; }
    .admin-limit-note .material-symbols-outlined { font-size: 14px; color: #f59e0b; }

    /* --- CENTER ALIGNMENT --- */
    .d-table th { text-align: center !important; }
    .d-table th:first-child { text-align: left !important; padding-left: 20px; }
    .d-table th.name-header { text-align: left !important; }
    .d-table th:last-child { text-align: right !important; padding-right: 24px; }
    
    .d-table td.role-col, .d-table td.status-col { text-align: center !important; }
    .d-table td:last-child { text-align: right !important; padding-right: 24px; }
    
    /* --- HIDE COLUMNS (Staff ID, Joined Date, & Last Login) --- */
    .col-staff-id, .col-joined-date, .col-last-login { display: none !important; }
    .d-table td.col-staff-id, .d-table td.col-joined-date, .d-table td.col-last-login { display: none !important; }

    /* --- FIX: ALLOW FULL NAME TO WRAP --- */
    .d-table td.name-col { white-space: normal !important; word-break: break-word; max-width: 200px; }

    /* --- SEARCH BOX STYLES --- */
    .search-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-surface);
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }
    .search-wrapper {
        position: relative;
        width: 320px;
    }
    .search-wrapper .material-symbols-outlined {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 18px;
        pointer-events: none;
    }
    .search-wrapper input {
        width: 100%;
        height: 36px;
        padding: 0 12px 0 38px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-main);
        font-size: 0.85rem;
        outline: none;
        transition: all 0.2s ease;
        font-family: var(--font-body);
    }
    .search-wrapper input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
    }
    .search-wrapper input::placeholder {
        color: var(--text-muted);
    }

    /* --- EMPTY STATE (OUTSIDE TABLE WRAPPER) --- */
    .empty-search-container {
        display: none;
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
        font-weight: 500;
        border-top: 1px solid var(--border);
        width: 100%;
        box-sizing: border-box;
        background: var(--bg-surface);
    }
    .empty-search-container.visible {
        display: block;
    }
    .empty-search-container .material-symbols-outlined {
        font-size: 48px;
        display: block;
        margin-bottom: 16px;
        color: var(--border);
    }

    /* --- ACTION BUTTONS (REFINED COLORS) --- */
    .action-btn-group {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: none;
        color: white;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        background: transparent;
    }
    .action-btn .material-symbols-outlined {
        font-size: 18px;
    }
    
    /* Edit Button - #51A2FF */
    .btn-edit-info {
        background: #51A2FF;
    }
    .btn-edit-info:hover {
        filter: brightness(1.1);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(81, 162, 255, 0.4);
    }

    /* Block/Unblock Button - #FF6467 */
    .btn-toggle-status {
        background: #FF6467;
    }
    .btn-toggle-status:hover {
        filter: brightness(1.1);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 100, 103, 0.4);
    }

    /* --- EDIT MODAL BUTTON REFINEMENTS --- */
    .btn-cancel-edit {
        background: #E2E8F0 !important;
        color: #1E293B !important;
        border: none !important;
        transition: all 0.2s ease;
    }
    .btn-cancel-edit:hover {
        filter: brightness(0.95);
        transform: translateY(-1px);
    }
    
    .btn-clear-photo {
        background: #C6D2FF !important;
        color: #1E293B !important;
        border: none !important;
        transition: all 0.2s ease;
    }
    .btn-clear-photo:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(198, 210, 255, 0.4);
    }

    /* --- FIX: BUTTON ALIGNMENT IN EDIT MODAL --- */
    .edit-modal-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 24px;
        gap: 10px;
    }
    .edit-modal-actions .left-buttons {
        display: flex;
        gap: 10px;
        flex: 1;
    }
    .edit-modal-actions .left-buttons .btn {
        flex: 1;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .edit-modal-actions .btn-clear-photo {
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border-radius: 6px;
        padding: 0 16px;
        font-weight: 600;
    }
</style>
<?php require_once 'admin_nav.php'; ?>
<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">group</span>
                User Management
            </h1>
            <button class="btn" style="width:auto; display: inline-flex; align-items: center; justify-content: center; gap: 8px;" onclick="openModal('addModal')">
                <span class="material-symbols-outlined">person_add</span> Add User
            </button>
        </div>

        <div class="unified-card">
            <div class="search-controls">
                <!-- Search Box on the LEFT -->
                <div class="search-wrapper">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="userSearchInput" placeholder="Search by name, username, email, ID..." oninput="filterUsers(this.value)">
                </div>
                
                <!-- Total Users on the RIGHT -->
                <div style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">
                    Total Users: <span style="color:var(--text-main);"><?= count($users) ?></span>
                </div>
            </div>

            <div class="table-section">
                <table class="d-table" id="userTable">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding-left:20px;">User</th>
                            <th class="col-staff-id">Staff ID</th>
                            <th class="name-header">Full Name</th>
                            <th class="col-email">Email</th>
                            <th class="role-col">Role</th>
                            <th class="status-col">Status</th>
                            <th class="col-joined-date">Joined Date</th>
                            <th class="col-last-login">Last Login</th>
                            <th style="text-align:right; padding-right:24px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php foreach($users as $u): 
                            $pfp = !empty($u['pfp_path']) ? '../' . $u['pfp_path'] : '../imgs/default_pfp.svg';
                            $rowStaffId = htmlspecialchars($u['staff_id'] ?? '—');
                        ?>
                            <tr class="user-row" data-search="<?= strtolower(htmlspecialchars($u['full_name'].' '.$u['username'].' '.($u['email']??'').' '.$u['staff_id']??''.' '.$u['role'])) ?>">
                                <td style="text-align:left; padding-left:20px;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <img src="<?= htmlspecialchars($pfp) ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid var(--border);">
                                        <span style="font-size:0.9rem; font-weight:600; color:var(--text-main);"><?= htmlspecialchars($u['username']) ?></span>
                                    </div>
                                </td>
                                <td class="col-staff-id mono" style="font-size:0.85rem; color:var(--text-muted);">
                                    <?= $rowStaffId ?>
                                </td>
                                <td class="name-col" style="font-size:0.85rem; color:var(--text-main); font-weight:500;"><?= htmlspecialchars($u['full_name']) ?></td>
                                <td class="col-email" style="color:var(--text-main); font-size:0.85rem; font-weight:500;"><?= !empty($u['email']) ? htmlspecialchars($u['email']) : '<span style="opacity:0.4;">—</span>' ?></td>
                                <td class="role-col">
                                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-fail' : ($u['role'] === 'lead' ? 'badge-smoke' : 'badge-pass') ?>" style="text-transform:uppercase;">
                                        <?= $u['role'] ?>
                                    </span>
                                </td>
                                <td class="status-col">
                                    <?php if($u['status'] === 'active'): ?>
                                        <span class="badge" style="background:rgba(16,185,129,0.1); color:#10b981; border:1px solid #10b981;">Active</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid #ef4444;">Blocked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-joined-date mono" style="font-size:0.85rem; color:var(--text-muted);">
                                    <?= !empty($u['joined_date']) ? date('M d, Y', strtotime($u['joined_date'])) : '—' ?>
                                </td>
                                <td class="col-last-login mono" style="font-size:0.8rem; color:var(--text-muted);">
                                    <?= $u['last_login'] ? date('M d, Y g:ia', strtotime($u['last_login'])) : 'Never' ?>
                                </td>
                                <td style="text-align:right; padding-right:24px;">
                                    <div class="action-btn-group">
                                        <button class="action-btn btn-edit-info tooltip-trigger" data-tip="Edit Info" onclick="openEdit(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', '<?= $u['username'] ?>', '<?= $u['role'] ?>', '<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($u['staff_id'] ?? '', ENT_QUOTES) ?>', '<?= !empty($u['joined_date']) ? $u['joined_date'] : '' ?>', <?= $canAddAdmin ? 'false' : 'true' ?>)">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                            <button type="button" class="action-btn btn-toggle-status tooltip-trigger" data-tip="<?= $u['status'] === 'active' ? 'Block Access' : 'Unblock Access' ?>" onclick="toggleStatus(this, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= $u['status'] ?>')">
                                                <span class="material-symbols-outlined"><?= $u['status'] === 'active' ? 'block' : 'check_circle' ?></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="empty-search-container" id="emptySearchContainer">
                    <span class="material-symbols-outlined">search_off</span>
                    No users found matching your search.
                </div>
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
        <form method="POST" class="modal-body" style="overflow: visible;">
            <input type="hidden" name="add_user" value="1">
            
            <div class="form-group" style="margin-top: 10px;">
                <input type="text" name="full_name" id="add_full_name" class="form-control" autocomplete="off" required>
                <label class="form-label">Full Name</label>
            </div>
            
            <div class="form-group">
                <input type="text" name="staff_id" id="add_staff_id" class="form-control" autocomplete="off">
                <label class="form-label">Staff ID</label>
            </div>
            
            <div class="form-group">
                <input type="date" name="joined_date" id="add_joined_date" class="form-control">
                <label class="form-label">Joined Date</label>
            </div>
            
            <div class="form-group">
                <input type="text" name="username" class="form-control" autocomplete="off" required maxlength="20">
                <label class="form-label">Login ID (Username) <span style="font-weight:400; opacity:0.6;">(max 20)</span></label>
            </div>
            
            <div class="form-group">
                <input type="email" name="email" class="form-control" autocomplete="off">
                <label class="form-label">Email Address</label>
            </div>
            
            <div class="form-group" style="margin-top: 10px;">
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom:8px; display:block;">Role</label>
                <?php 
                    $addRoleOptions = ['tester' => 'Tester', 'lead' => 'Lead'];
                    if ($canAddAdmin) {
                        $addRoleOptions['admin'] = 'Admin';
                    }
                    echo Helper::enhancedDropdown([
                        'id' => 'add_role_dd',
                        'name' => 'role',
                        'placeholder' => 'Select Role...',
                        'multiple' => false,
                        'options' => $addRoleOptions,
                        'selected' => 'tester'
                    ]);
                ?>
                <?php if (!$canAddAdmin): ?>
                    <div class="admin-limit-note">
                        <span class="material-symbols-outlined">info</span>
                        Admin slot is already taken
                    </div>
                <?php endif; ?>
            </div>
            <!-- FIXED: Create button margin-top set to 5px -->
            <button type="submit" class="btn" style="width:100%; margin-top:5px;">Create User</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="modal-close-btn" onclick="closeModal('editModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body" style="overflow: visible;">
            <input type="hidden" name="user_id" id="edit_id">
            
            <div class="form-group" style="margin-top: 10px;">
                <input type="text" name="full_name" id="edit_name" class="form-control" autocomplete="off" required>
                <label class="form-label">Full Name</label>
            </div>
            
            <div class="form-group">
                <input type="text" name="staff_id" id="edit_staff_id" class="form-control" autocomplete="off">
                <label class="form-label">Staff ID</label>
            </div>
            
            <div class="form-group">
                <input type="date" name="joined_date" id="edit_joined_date" class="form-control">
                <label class="form-label">Joined Date</label>
            </div>
            
            <div class="form-group">
                <input type="text" name="username" id="edit_username" class="form-control" autocomplete="off" required maxlength="20">
                <label class="form-label">Login ID (Username) <span style="font-weight:400; opacity:0.6;">(max 20)</span></label>
            </div>
            
            <div class="form-group">
                <input type="email" name="email" id="edit_email" class="form-control" autocomplete="off">
                <label class="form-label">Email Address</label>
            </div>
            
            <div class="form-group" style="margin-top: 10px;">
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom:8px; display:block;">Role</label>
                <?php 
                    $editRoleOptions = ['tester' => 'Tester', 'lead' => 'Lead'];
                    if ($canAddAdmin) {
                        $editRoleOptions['admin'] = 'Admin';
                    }
                    echo Helper::enhancedDropdown([
                        'id' => 'edit_role_dd',
                        'name' => 'role',
                        'placeholder' => 'Select Role...',
                        'multiple' => false,
                        'options' => $editRoleOptions,
                        'selected' => '' 
                    ]);
                ?>
                <div class="admin-limit-note" id="edit_admin_note" style="display:none;">
                    <span class="material-symbols-outlined">info</span>
                    Admin slot is already taken
                </div>
            </div>
            
            <!-- FIXED: Align buttons perfectly using Flexbox -->
            <div class="edit-modal-actions">
                <div class="left-buttons">
                    <button type="submit" name="edit_user" class="btn">Save Changes</button>
                    <button type="button" class="btn btn-cancel-edit" onclick="closeModal('editModal')">Cancel</button>
                </div>
                
                <button type="submit" name="reset_pfp" class="btn-clear-photo">
                    <span class="material-symbols-outlined" style="font-size: 18px;">photo</span> Clear Photo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleStatus(btn, fullName, currentStatus) {
        const action = currentStatus === 'active' ? 'block' : 'unblock';
        if (confirm('Are you sure you want to ' + action + ' access for ' + fullName + '?')) {
            btn.closest('form').submit();
        }
    }

    function openEdit(id, name, username, role, email, staffId, joinedDate, adminSlotTaken) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_email').value = email || '';
        document.getElementById('edit_staff_id').value = staffId || '';
        document.getElementById('edit_joined_date').value = joinedDate || '';
        
        const dd = document.getElementById('edit_role_dd');
        const isAdminUser = role === 'admin';
        
        const adminNote = document.getElementById('edit_admin_note');
        const adminOption = dd.querySelector('.enh-option[data-value="admin"]');
        
        if (adminSlotTaken && !isAdminUser) {
            if (adminOption) adminOption.style.display = 'none';
            adminNote.style.display = 'flex';
        } else {
            if (adminOption) adminOption.style.display = '';
            adminNote.style.display = 'none';
        }
        
        const hiddenContainer = dd.querySelector('.enh-hidden-inputs');
        hiddenContainer.innerHTML = `<input type='hidden' name='role' value='${role}'>`;
        
        const triggerContent = dd.querySelector('.enh-trigger-content');
        const roleLabels = { 'tester': 'Tester', 'lead': 'Lead', 'admin': 'Admin' };
        triggerContent.textContent = roleLabels[role] || 'Select...';
        
        dd.querySelectorAll('.enh-option').forEach(opt => {
            if(opt.dataset.value === role) {
                opt.classList.add('selected');
            } else {
                opt.classList.remove('selected');
            }
        });
        
        openModal('editModal');
        
        setTimeout(() => {
            const nameInput = document.getElementById('edit_name');
            nameInput.focus();
            nameInput.blur();
        }, 50);
    }

    /* --- LIVE SEARCH FUNCTION --- */
    function filterUsers(query) {
        const searchTerm = query.toLowerCase().trim();
        const rows = document.querySelectorAll('#userTableBody .user-row');
        const emptyContainer = document.getElementById('emptySearchContainer');
        let visibleCount = 0;

        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const matches = searchTerm === '' || searchData.includes(searchTerm);
            
            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Show/Hide the empty container
        if (visibleCount === 0 && searchTerm !== '') {
            emptyContainer.classList.add('visible');
        } else {
            emptyContainer.classList.remove('visible');
        }
    }
</script>
</body>
</html>