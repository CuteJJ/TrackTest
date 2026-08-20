<?php 
require_once 'controllers/TaskController.php'; 
require_once 'configs/db.php';
require_once 'configs/helper.php';

Helper::requireRole('lead');

// Restore form data from session if validation failed
$form_data = $_SESSION['create_task_form'] ?? null;
if ($form_data) {
    unset($_SESSION['create_task_form']);
}

$data = getData($pdo);

// Build user map for assignment restoration
$user_map = [];
foreach ($data['users'] as $u) {
    $user_map[$u['id']] = $u;
}

// Prepare saved assignments and regression URLs from form data
$saved_assignments = [];
$saved_reg_urls = [];
if ($form_data) {
    if (isset($form_data['assignments']) && is_array($form_data['assignments'])) {
        foreach ($form_data['assignments'] as $pid => $uids) {
            foreach ($uids as $uid) {
                if (isset($user_map[$uid])) {
                    $u = $user_map[$uid];
                    $parts = explode(' ', $u['full_name']);
                    $pfp = !empty($u['pfp_path']) ? $u['pfp_path'] : 'imgs/default_pfp.svg';
                    $saved_assignments[$pid][] = [
                        'uid' => $uid,
                        'name' => $u['full_name'],
                        'pfp' => $pfp
                    ];
                }
            }
        }
    }
    if (isset($form_data['regression_urls']) && is_array($form_data['regression_urls'])) {
        $saved_reg_urls = $form_data['regression_urls'];
    }
}

$saved_assignments_json = json_encode((object)$saved_assignments);
$saved_reg_urls_json = json_encode((object)$saved_reg_urls);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Task | Track Manager</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
<link rel="stylesheet" href="app.css">
<script>
    // Prevent White Flash
    const savedTheme = localStorage.getItem('track-manager-theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
</script>
<script src="app.js" defer></script> 

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body { height: 100vh; overflow: hidden; display: flex; flex-direction: column; }

.topbar { flex-shrink: 0; height: var(--nav-height); background: var(--bg-surface); border-bottom: 1px solid var(--border); padding: 0 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.06); z-index: 100; }
.tb-brand { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 700; color: var(--text-main); text-decoration: none; }
.tb-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }
.tb-crumb { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted); }
.tb-crumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
.tb-crumb a:hover { color: var(--primary); }
.tb-crumb-sep { color: var(--border); }
.tb-crumb-cur { color: var(--text-main); font-weight: 600; }

.page-shell { flex: 1; display: grid; grid-template-columns: 1fr 380px; overflow: hidden; min-height: 0; }

.left-panel { overflow-y: auto; padding: 32px 36px 64px; background: var(--bg-body); display: block; }
.lp-heading { margin-bottom: 24px; }
.lp-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main); line-height: 1.2; }
.lp-sub { font-size: 0.82rem; color: var(--text-muted); margin-top: 5px; }

.s-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; overflow: visible; margin-bottom: 24px; flex-shrink: 0; }
.s-card-head { padding: 13px 20px; border-bottom: 1px solid var(--border); background: var(--bg-body); display: flex; align-items: center; gap: 10px; }
.s-num { width: 22px; height: 22px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.67rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.s-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); }
.s-card-body { padding: 22px 20px; overflow: visible; }

.f-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.f-grid .f-full { grid-column: span 2; }
@media (max-width: 900px) { .f-grid { grid-template-columns: 1fr; } .f-grid .f-full { grid-column: span 1; } }

/* Normal Inputs */
.f-field { position: relative; }
.f-input { display: block; width: 100%; padding: 20px 14px 7px; background: var(--input-bg); border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.9rem; font-family: var(--font-body); color: var(--text-main); outline: none; transition: border-color 0.15s, box-shadow 0.15s, background 0.15s; line-height: 1.35; height: 52px; }
.f-input:focus { border-color: var(--primary); background: var(--bg-surface); box-shadow: 0 0 0 3px rgba(2,136,209,0.1); }
.f-label { position: absolute; left: 14px; top: 8px; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); pointer-events: none; transition: color 0.14s; }
.f-input:focus ~ .f-label { color: var(--primary); }
input[type="date"].f-input { padding-top: 22px; padding-bottom: 5px; cursor: pointer; color: var(--text-main); }

/* Enhanced Dropdown Inputs */
.f-field-dd { display: flex; flex-direction: column; gap: 8px; justify-content: flex-end; }
.f-label-static { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.f-field-dd .enh-trigger { height: 52px !important; border-width: 1.5px !important; }
.f-field-dd .enh-single-val { font-family: var(--font-mono); font-weight: 700; color: var(--primary); font-size: 0.9rem; }

/* JS Filter Class */
.type-hidden { display: none !important; }

.f-pill-wrap { display: flex; flex-direction: column; gap: 7px; height: 100%; justify-content: flex-end; }
.f-pill-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); }
.f-pill { display: flex; background: var(--bg-body); border-radius: 8px; padding: 3px; gap: 2px; height: 42px;}
.f-pill input[type="radio"] { display: none; }
.f-pill label { flex: 1; text-align: center; padding: 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.16s; user-select: none; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 6px;}
.f-pill input[type="radio"]:checked + label { background: var(--bg-surface); color: var(--primary); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

.action-row { display: flex; gap: 12px; align-items: center; margin-top: 10px; }
.btn-create { display: inline-flex; align-items: center; gap: 7px; padding: 11px 24px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-size: 0.87rem; font-weight: 700; cursor: pointer; transition: background 0.14s, transform 0.12s, box-shadow 0.14s; }
.btn-create:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(2,136,209,0.3); }
.btn-cancel { display: inline-flex; align-items: center; padding: 11px 20px; border: 1.5px solid var(--border); border-radius: 8px; background: transparent; color: var(--text-muted); font-size: 0.87rem; font-weight: 600; text-decoration: none; transition: border-color 0.14s, color 0.14s; }
.btn-cancel:hover { border-color: var(--primary); color: var(--text-main); }

/* --- REDESIGNED RIGHT PANEL LAYOUT (NO OVERLAP) --- */
.right-panel { 
    background: var(--bg-surface); 
    border-left: 1px solid var(--border); 
    display: flex; 
    flex-direction: column; 
    overflow: hidden; 
    min-height: 0; 
}
.rp-head { 
    flex-shrink: 0; 
    padding: 14px 18px 12px; 
    border-bottom: 1px solid var(--border); 
    background: var(--bg-body); 
}
.rp-head-title { font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); display: block; margin-bottom: 2px; }
.rp-head-sub { font-size: 0.7rem; color: var(--text-muted); }

/* 1. Printer Container - Fits content exactly (No scroll) */
.printer-grid-container { 
    flex-shrink: 0; /* Prevents it from shrinking */
    padding: 16px 12px; 
    border-bottom: 1px solid var(--border);
}
.printer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }

/* 2. Assignment Panel - Takes all remaining space (Scrolls if needed) */
.assignment-panel { 
    flex: 1; /* Takes ALL remaining space */
    background: var(--bg-body); 
    padding: 18px 16px; 
    overflow-y: auto; /* Scrollbar appears if content is too long */
    border-top: 2px solid var(--border); /* Clear separation line */
}

/* Printer Card Styling */
.p-card { position: relative; background: var(--bg-surface); border: 1.5px solid var(--border); border-radius: 12px; padding: 14px 8px 10px; display: flex; flex-direction: column; align-items: center; text-align: center; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.p-card:hover { border-color: var(--primary); box-shadow: 0 6px 14px rgba(2,136,209,0.08); background: var(--bg-body); }
.p-card.p-active { border-color: var(--primary); background: var(--bg-body); box-shadow: 0 0 0 2px var(--primary); }
.p-card.p-selected { background: var(--bg-surface); border-color: var(--primary); }
.p-card.p-selected .selected-badge { opacity: 1; }
.p-card-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; margin-bottom: 8px; transition: background 0.2s; }
.p-card.p-active .p-card-icon { background: var(--primary); }
.p-card.p-active .p-card-icon .material-symbols-outlined { color: var(--bg-surface); }
.p-card-name { font-size: 0.78rem; font-weight: 700; color: var(--text-main); line-height: 1.3; margin-bottom: 4px; max-width: 100%; word-break: break-word; }
.selected-badge { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
.selected-badge .material-symbols-outlined { font-size: 14px; }

/* Assignment Panel Styling */
.assignment-placeholder { display: flex; align-items: center; justify-content: center; height: 80px; color: var(--text-muted); font-size: 0.8rem; text-align: center; background: var(--bg-surface); border-radius: 10px; padding: 20px; border: 1px solid var(--border); }
.assignment-content { display: flex; flex-direction: column; gap: 22px; }
.hidden { display: none !important; }

.micro-label { display: block; font-size: 0.59rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 10px; }
.t-pool { display: flex; flex-wrap: wrap; gap: 12px; }
.t-av-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; cursor: pointer; transition: transform 0.15s, opacity 0.15s; }
.t-av-wrap:hover { transform: translateY(-3px); }
.t-av-wrap.t-used { opacity: 0.2; pointer-events: none; transform: none; }
.t-av { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 8px rgba(0,0,0,0.15); overflow: hidden; border: 1px solid var(--border); background: var(--bg-surface); }
.t-av img { width: 100%; height: 100%; object-fit: cover; }
.t-nm { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); max-width: 48px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; }

.a-slots { display: flex; flex-wrap: wrap; gap: 8px; min-height: 48px; padding: 8px 6px; border: 1.5px dashed var(--border); border-radius: 10px; background: var(--bg-surface); align-items: center; }
.a-ph { font-size: 0.72rem; color: var(--text-muted); width: 100%; text-align: center; pointer-events: none; }
.a-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 4px; border: 1.5px solid var(--border); border-radius: 30px; background: var(--bg-surface); cursor: pointer; transition: all 0.14s; box-shadow: 0 1px 3px rgba(0,0,0,0.04); user-select: none; }
.a-chip:hover { border-color: var(--primary); background: var(--bg-body); }
.a-chip.a-main { border-color: var(--primary); background: var(--bg-body); box-shadow: 0 0 0 1px var(--primary); }
.a-chip-av { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border); background: var(--bg-surface); flex-shrink: 0; }
.a-chip-av img { width: 100%; height: 100%; object-fit: cover; }
.a-chip-name { font-size: 0.72rem; font-weight: 600; color: var(--text-main); }
.a-chip-role { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; padding: 2px 6px; border-radius: 12px; background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border); }
.a-chip.a-main .a-chip-role { background: var(--primary); color: white; border-color: var(--primary); }
.a-chip-close { width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: background 0.14s, color 0.14s; margin-left: 2px; }
.a-chip-close:hover { background: var(--error-bg); color: var(--error); }
.a-chip-close .material-symbols-outlined { font-size: 14px; }

.reg-wrap { position: relative; }
.reg-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; display: flex; align-items: center; }
.reg-icon .material-symbols-outlined { font-size: 14px; color: var(--text-muted); }
.reg-input { width: 100%; padding: 10px 10px 10px 32px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.78rem; font-family: var(--font-body); color: var(--text-main); background: var(--input-bg); outline: none; transition: border-color 0.14s, box-shadow 0.14s; }
.reg-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(2,136,209,0.1); }

.rp-foot { flex-shrink: 0; border-top: 1px solid var(--border); padding: 10px 16px; background: var(--bg-body); font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
.rp-foot-count { font-weight: 800; color: var(--primary); min-width: 14px; display: inline-block; text-align: center; }

/* Dark Mode Calendar Fix */
[data-theme="dark"] input[type="date"].f-input::-webkit-calendar-picker-indicator,
[data-theme="midnight"] input[type="date"].f-input::-webkit-calendar-picker-indicator,
[data-theme="catppuccin"] input[type="date"].f-input::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }

/* --- PAGE-SPECIFIC FIX FOR CREATABLE DROPDOWNS --- */
.enh-menu {
    position: absolute !important;
    top: calc(100% + 4px) !important;
    left: 0 !important;
    width: 100% !important;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    max-height: 320px;
    transform-origin: top center;
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
    pointer-events: none;
    visibility: hidden;
}

.enh-dropdown.open .enh-menu {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
    visibility: visible;
}

/* DISABLE AUTO-FLIP TO TOP - FORCE DROPDOWN TO STAY BELOW */
.enh-menu.drop-up {
    top: calc(100% + 4px) !important;
    bottom: auto !important;
    transform-origin: top center;
    transform: translateY(-8px) scale(0.98);
    box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.15);
}

.enh-dropdown.open .enh-menu.drop-up {
    transform: translateY(0) scale(1);
}

/* --- DELETE BUTTON STYLES --- */
.fw-delete-btn {
    cursor: pointer;
    color: #ef4444 !important;
    font-weight: 800;
    font-size: 18px;
    padding: 0 6px;
    border-radius: 4px;
    transition: background 0.2s;
    margin-left: 10px;
    line-height: 1;
}
.fw-delete-btn:hover {
    background: rgba(239, 68, 68, 0.2);
}
.enh-option .enh-opt-label {
    flex: 1;
    white-space: normal;
    word-break: break-word;
}
</style>
</head>
<body>

<?php Helper::displayLoader(); ?>
<?php Helper::displayFlash(); ?>

<header class="topbar">
    <a href="index.php" class="tb-brand">
        <span class="tb-dot"></span>
        Track Manager
    </a>
    <nav class="tb-crumb">
        <a href="tasks.php">Tasks</a>
        <span class="tb-crumb-sep">›</span>
        <span class="tb-crumb-cur">Create Task</span>
    </nav>
</header>

<form action="controllers/TaskController.php" method="POST" id="mainForm" class="page-shell">
    <input type="hidden" name="create_task" value="1">

    <main class="left-panel">
        <div class="lp-heading">
            <h1 class="lp-title">Create New Task</h1>
            <p class="lp-sub">Configure task details, then assign printers and testers in the sidebar →</p>
        </div>

        <div class="s-card">
            <div class="s-card-head">
                <div class="s-num">1</div>
                <span class="s-title">Task Details</span>
            </div>
            <div class="s-card-body">
                <div class="f-grid">
                    <div class="f-field">
                        <input type="date" name="task_date" class="f-input" 
                               value="<?= htmlspecialchars($form_data['task_date'] ?? date('Y-m-d')) ?>" required>
                        <label class="f-label">Task Date</label>
                    </div>
                    <div class="f-field">
                        <input type="date" name="due_date" class="f-input" 
                               value="<?= htmlspecialchars($form_data['due_date'] ?? date('Y-m-d')) ?>" required>
                        <label class="f-label">Due Date</label>
                    </div>
                    <div class="f-full">
                        <div class="f-pill-wrap">
                            <span class="f-pill-label">Testing Workflow</span>
                            <div class="f-pill">
                                <input type="radio" name="testing_type" id="wf_smoke" value="Smoke" 
                                    <?= ($form_data['testing_type'] ?? 'Smoke') == 'Smoke' ? 'checked' : '' ?>>
                                <label for="wf_smoke">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">local_fire_department</span> Smoke Test
                                </label>

                                <input type="radio" name="testing_type" id="wf_reg" value="Regression"
                                    <?= ($form_data['testing_type'] ?? '') == 'Regression' ? 'checked' : '' ?>>
                                <label for="wf_reg">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">checklist</span> Regression Test
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="s-card">
            <div class="s-card-head">
                <div class="s-num">2</div>
                <span class="s-title">Firmware Configuration</span>
            </div>
            <div class="s-card-body">
                <div class="f-grid">
                    
                    <div>
                        <div class="f-pill-wrap">
                            <span class="f-pill-label">Firmware Type</span>
                            <div class="f-pill">
                                <input type="radio" name="fw_type" id="ft_trunk" value="Trunk" 
                                       <?= ($form_data['fw_type'] ?? 'Trunk') == 'Trunk' ? 'checked' : '' ?>>
                                <label for="ft_trunk">Trunk</label>
                                <input type="radio" name="fw_type" id="ft_branch" value="Branch"
                                       <?= ($form_data['fw_type'] ?? '') == 'Branch' ? 'checked' : '' ?>>
                                <label for="ft_branch">Branch</label>
                            </div>
                        </div>
                    </div>
                    
                    <div></div>

                    <div class="f-field-dd">
                        <span class="f-label-static">Previous Firmware</span>
                        <?= Helper::enhancedDropdown([
                            'name' => 'fw_prev',
                            'id' => 'fw_prev_dd',
                            'placeholder' => 'e.g. 24.1.0',
                            'creatable' => true,
                            'options' => $data['firmwares'],
                            'selected' => $form_data['fw_prev'] ?? ''
                        ]) ?>
                    </div>
                    
                    <div class="f-field-dd">
                        <span class="f-label-static">Current Firmware</span>
                        <?= Helper::enhancedDropdown([
                            'name' => 'fw_curr',
                            'id' => 'fw_curr_dd',
                            'placeholder' => 'e.g. 24.2.0',
                            'creatable' => true,
                            'options' => $data['firmwares'],
                            'selected' => $form_data['fw_curr'] ?? ''
                        ]) ?>
                    </div>
                    
                    <div class="f-field-dd">
                        <span class="f-label-static">Recovery Firmware</span>
                        <?= Helper::enhancedDropdown([
                            'name' => 'fw_rec',
                            'id' => 'fw_rec_dd',
                            'placeholder' => 'e.g. 24.0.5',
                            'creatable' => true,
                            'options' => $data['firmwares'],
                            'selected' => $form_data['fw_rec'] ?? ''
                        ]) ?>
                    </div>
                    
                </div>
            </div>
        </div>

        <div class="action-row">
            <button type="submit" class="btn-create">
                <span class="material-symbols-outlined">check_circle</span>
                Create Task
            </button>
            <a href="tasks.php" class="btn-cancel">Cancel</a>
        </div>
    </main>

    <aside class="right-panel">
        <div class="rp-head">
            <span class="rp-head-title">Printer &amp; Assignments</span>
            <span class="rp-head-sub">Click a printer to assign testers</span>
        </div>

        <div class="printer-grid-container">
            <div class="printer-grid" id="printerGrid">
                <?php foreach ($data['printers'] as $pi => $p):
                    // --- FIX: Skip inactive printers (they are already filtered in controller, but extra safety) ---
                    if (isset($p['status']) && $p['status'] === 'inactive') continue;
                    
                    $hasSaved = isset($saved_assignments[$p['id']]) || !empty($saved_reg_urls[$p['id']]);
                ?>
                <div class="p-card <?= $hasSaved ? 'p-selected' : '' ?>" data-pid="<?= $p['id'] ?>" id="pc_<?= $p['id'] ?>">
                    <div class="p-card-icon" style="overflow: hidden; padding: 2px;">
                        <?= Helper::renderPrinterImage($p['printer_path'] ?? null, $p['model_name'], 24) ?>
                    </div>
                    <div class="p-card-name"><?= htmlspecialchars($p['model_name']) ?></div>
                    <div class="selected-badge"><span class="material-symbols-outlined">check</span></div>
                    
                    <input type="checkbox" name="printers[]" value="<?= $p['id'] ?>" class="printer-checkbox" 
                           id="cb_<?= $p['id'] ?>" <?= $hasSaved ? 'checked' : '' ?> style="display:none;">
                    
                    <div id="tester_list_<?= $p['id'] ?>" style="display:none;"></div>
                    
                    <input type="hidden" name="regression_urls[<?= $p['id'] ?>]" id="reg_hidden_<?= $p['id'] ?>" 
                           value="<?= htmlspecialchars($saved_reg_urls[$p['id']] ?? '') ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="assignment-panel" id="assignmentPanel">
            <div class="assignment-placeholder" id="assignmentPlaceholder">
                Select a printer above to assign testers.
            </div>
            <div class="assignment-content hidden" id="assignmentContent">
                <div id="smokeAssignmentUI">
                    <div class="tester-pool-section">
                        <span class="micro-label">Tester Pool</span>
                        <div class="t-pool" id="globalPool">
                            <?php foreach ($data['users'] as $u):
                                if ($u['role'] !== 'tester') continue;
                                $fn = trim($u['full_name']);
                                $parts = explode(' ', $fn);
                                $pfp = !empty($u['pfp_path']) ? $u['pfp_path'] : 'imgs/default_pfp.svg';
                            ?>
                            <div class="t-av-wrap" data-uid="<?= $u['id'] ?>" data-name="<?= htmlspecialchars($fn, ENT_QUOTES) ?>" data-pfp="<?= htmlspecialchars($pfp) ?>">
                                <div class="t-av">
                                    <img src="<?= htmlspecialchars($pfp) ?>" class="pfp-img">
                                </div>
                                <span class="t-nm"><?= htmlspecialchars($parts[0]) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="assigned-section">
                        <span class="micro-label" style="margin-top: 20px; display: flex; align-items: center; flex-wrap: wrap;">
                            Assigned — click chip to toggle MAIN, 
                            <span class="material-symbols-outlined" style="font-size: 14px; margin: 0 4px;">close</span> 
                            to remove
                        </span>
                        <div class="a-slots" id="activeSlots">
                            <span class="a-ph" id="activePlaceholder">No testers assigned</span>
                        </div>
                    </div>
                </div>
                <div id="regressionAssignmentUI" class="hidden">
                    <span class="micro-label">TestRail Run URL</span>
                    <div class="reg-wrap">
                        <span class="reg-icon"><span class="material-symbols-outlined">link</span></span>
                        <input type="url" id="regInputActive" class="reg-input" placeholder="https://testrail.com/runs/view/...">
                    </div>
                </div>
            </div>
        </div>

        <div class="rp-foot">
            <span class="rp-foot-count" id="footCount">0</span>
            printer(s) selected
        </div>
    </aside>
</form>

<script>
(function() {
    'use strict';

    // --- HELPER: ADD "X" BUTTON TO A SINGLE DROPDOWN OPTION ---
    function addDeleteButtonToOption(opt, type) {
        if (opt.querySelector('.fw-delete-btn')) return;
        const fw = opt.dataset.value;
        if (!fw) return;
        const rowWrapper = document.createElement('div');
        rowWrapper.style.cssText = 'display: flex; align-items: center; justify-content: space-between; width: 100%;';
        const labelSpan = opt.querySelector('.enh-opt-label');
        if (labelSpan) {
            rowWrapper.appendChild(labelSpan);
        } else {
            const newLabel = document.createElement('span');
            newLabel.className = 'enh-opt-label';
            newLabel.textContent = fw;
            rowWrapper.appendChild(newLabel);
        }
        const deleteBtn = document.createElement('span');
        deleteBtn.className = 'fw-delete-btn';
        deleteBtn.textContent = '×';
        deleteBtn.title = 'Delete "' + fw + '" from the list';
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            if (confirm('Are you sure you want to permanently delete firmware "' + fw + '" from the system?')) {
                window.deleteFirmware(fw, type, opt);
            }
        });
        rowWrapper.appendChild(deleteBtn);
        opt.appendChild(rowWrapper);
    }

    function rebuildFwDropdowns(type) {
        ['fw_prev_dd', 'fw_curr_dd', 'fw_rec_dd'].forEach(id => {
            const container = document.getElementById(id);
            if (!container) return;
            const triggerContent = container.querySelector('.enh-trigger-content');
            if (triggerContent) {
                triggerContent.innerHTML = `<span class="enh-placeholder">${container.config ? container.config.placeholder : 'e.g. 24.1.0'}</span>`;
            }
            let firmwaresData = {};
            try {
                firmwaresData = <?= json_encode($data['firmwares']) ?>;
            } catch (e) {
                console.error("PHP JSON Data is corrupted or invalid:", e);
                return; 
            }
            const filteredFws = (firmwaresData && firmwaresData[type]) ? firmwaresData[type] : [];
            const optionsContainer = container.querySelector('.enh-options');
            if (!optionsContainer) return;
            const children = optionsContainer.children;
            for (let i = children.length - 1; i >= 0; i--) {
                const child = children[i];
                if (child.classList.contains('enh-option') || child.classList.contains('enh-optgroup-label')) {
                    optionsContainer.removeChild(child);
                }
            }
            if (filteredFws.length === 0) {
            } else {
                const groupLabel = document.createElement('div');
                groupLabel.className = 'enh-optgroup-label';
                groupLabel.textContent = type.toUpperCase();
                optionsContainer.appendChild(groupLabel);
                filteredFws.forEach(fw => {
                    const opt = document.createElement('div');
                    opt.className = 'enh-option';
                    opt.dataset.value = fw;
                    const labelSpan = document.createElement('span');
                    labelSpan.className = 'enh-opt-label';
                    labelSpan.textContent = fw;
                    opt.appendChild(labelSpan);
                    addDeleteButtonToOption(opt, type);
                    optionsContainer.appendChild(opt);
                });
            }
            if (container._enhancedDropdown) {
                delete container._enhancedDropdown;
            }
            if (window.EnhancedDropdown) {
                container._enhancedDropdown = new window.EnhancedDropdown(container);
                container._enhancedDropdown.selectedValues = [];
                container._enhancedDropdown.renderTrigger();
            }
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && node.classList.contains('enh-option')) {
                            if (!node.querySelector('.fw-delete-btn')) {
                                addDeleteButtonToOption(node, type);
                            }
                        }
                    });
                });
            });
            observer.observe(optionsContainer, { childList: true, subtree: false });
        });
    }

    window.deleteFirmware = function(fw, type, optElement) {
        const container = optElement ? optElement.closest('.enh-dropdown') : null;
        if (container) {
            const triggerContent = container.querySelector('.enh-trigger-content');
            if (triggerContent) {
                const placeholderText = container.config ? container.config.placeholder : 'e.g. 24.1.0';
                triggerContent.innerHTML = `<span class="enh-placeholder">${placeholderText}</span>`;
            }
        }
        const formData = new FormData();
        formData.append('fw', fw);
        formData.append('type', type);
        fetch('delete_firmware.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (optElement && optElement.parentNode) {
                    optElement.remove();
                }
                if (container && container._enhancedDropdown) {
                    delete container._enhancedDropdown;
                }
                if (container && window.EnhancedDropdown) {
                    container._enhancedDropdown = new window.EnhancedDropdown(container);
                    container._enhancedDropdown.selectedValues = [];
                    container._enhancedDropdown.renderTrigger();
                }
                if (typeof showDynamicToast === 'function') {
                    showDynamicToast('Firmware "' + fw + '" deleted successfully.', 'success');
                }
            } else {
                if (typeof showDynamicToast === 'function') {
                    showDynamicToast(data.error || 'Failed to delete firmware.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showDynamicToast === 'function') {
                showDynamicToast('Network error. Could not delete firmware.', 'error');
            }
        });
    };

    document.querySelectorAll('input[name="fw_type"]').forEach(r => {
        r.addEventListener('change', (e) => {
            const selectedType = e.target.value;
            rebuildFwDropdowns(selectedType);
        });
    });

    const initialFwType = document.querySelector('input[name="fw_type"]:checked');
    if (initialFwType) {
        rebuildFwDropdowns(initialFwType.value);
    }

    const assignments = JSON.parse(JSON.stringify(<?= $saved_assignments_json ?>));
    const regressionUrls = JSON.parse(JSON.stringify(<?= $saved_reg_urls_json ?>));
    let activePrinter = null;
    let workflow = '<?= $form_data['testing_type'] ?? 'Smoke' ?>';

    const printerCards = document.querySelectorAll('.p-card');
    const globalPool = document.getElementById('globalPool');
    const assignmentPlaceholder = document.getElementById('assignmentPlaceholder');
    const assignmentContent = document.getElementById('assignmentContent');
    const smokeUI = document.getElementById('smokeAssignmentUI');
    const regUI = document.getElementById('regressionAssignmentUI');
    const activeSlots = document.getElementById('activeSlots');
    const activePlaceholder = document.getElementById('activePlaceholder');
    const regInputActive = document.getElementById('regInputActive');
    const footCount = document.getElementById('footCount');

    <?php foreach ($data['printers'] as $p): ?>
        if (!assignments[<?= $p['id'] ?>]) assignments[<?= $p['id'] ?>] = [];
        if (!regressionUrls[<?= $p['id'] ?>]) regressionUrls[<?= $p['id'] ?>] = '';
    <?php endforeach; ?>

    function firstName(fullName) {
        return (fullName || '').split(' ')[0];
    }

    function updatePrinterCard(pid) {
        const card = document.getElementById('pc_' + pid);
        const checkbox = document.getElementById('cb_' + pid);
        let isSelected = false;
        if (workflow === 'Smoke') {
            isSelected = assignments[pid] && assignments[pid].length > 0;
        } else {
            isSelected = !!(regressionUrls[pid] && regressionUrls[pid].trim() !== '');
        }
        checkbox.checked = isSelected;
        card.classList.toggle('p-selected', isSelected);
        const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
        footCount.textContent = selectedCount;
    }

    function refreshAllPrinterCards() {
        printerCards.forEach(card => {
            const pid = card.dataset.pid;
            updatePrinterCard(pid);
        });
    }

    function renderHiddenInputs(pid) {
        const container = document.getElementById('tester_list_' + pid);
        if (!container) return;
        container.innerHTML = '';
        const list = assignments[pid] || [];
        list.forEach((t, idx) => {
            const isMain = idx === 0;
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `assignments[${pid}][]`;
            inp.value = t.uid;
            container.appendChild(inp);
            if (isMain) {
                const mainInp = document.createElement('input');
                mainInp.type = 'hidden';
                mainInp.name = `main_tester[${pid}]`;
                mainInp.value = t.uid;
                container.appendChild(mainInp);
            }
        });
    }

    function renderActiveSlots() {
        if (!activePrinter) return;
        const list = assignments[activePrinter] || [];
        activeSlots.innerHTML = '';
        if (list.length === 0) {
            activeSlots.appendChild(activePlaceholder);
            return;
        }
        list.forEach((t, idx) => {
            const isMain = idx === 0;
            const chip = document.createElement('div');
            chip.className = `a-chip ${isMain ? 'a-main' : ''}`;
            chip.dataset.uid = t.uid;
            chip.innerHTML = `
                <div class="a-chip-av">
                    <img src="${t.pfp}" class="pfp-img">
                </div>
                <span class="a-chip-name">${firstName(t.name)}</span>
                <span class="a-chip-role">${isMain ? 'MAIN' : 'SUP'}</span>
                <span class="a-chip-close" title="Remove"><span class="material-symbols-outlined">close</span></span>
            `;
            chip.addEventListener('click', (e) => {
                if (e.target.classList.contains('a-chip-close') || e.target.closest('.a-chip-close')) return;
                if (!isMain) {
                    const uid = chip.dataset.uid;
                    const index = list.findIndex(x => String(x.uid) === String(uid));
                    if (index > 0) {
                        const [item] = list.splice(index, 1);
                        list.unshift(item);
                        renderActiveSlots();
                        renderHiddenInputs(activePrinter);
                        updatePrinterCard(activePrinter);
                        updatePoolUsed(activePrinter);
                    }
                }
            });
            const closeBtn = chip.querySelector('.a-chip-close');
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const uid = chip.dataset.uid;
                assignments[activePrinter] = assignments[activePrinter].filter(x => String(x.uid) !== String(uid));
                renderActiveSlots();
                renderHiddenInputs(activePrinter);
                updatePrinterCard(activePrinter);
                updatePoolUsed(activePrinter);
            });
            activeSlots.appendChild(chip);
        });
    }

    function updatePoolUsed(pid) {
        if (!assignments[pid]) assignments[pid] = [];
        const usedUids = new Set(assignments[pid].map(t => String(t.uid)));
        document.querySelectorAll('#globalPool .t-av-wrap').forEach(el => {
            const uid = el.dataset.uid;
            el.classList.toggle('t-used', usedUids.has(uid));
        });
    }

    function loadRegUrlForActive() {
        if (!activePrinter) return;
        regInputActive.value = regressionUrls[activePrinter] || '';
    }

    function saveRegUrlFromActive() {
        if (!activePrinter) return;
        const url = regInputActive.value;
        regressionUrls[activePrinter] = url;
        const hidden = document.getElementById('reg_hidden_' + activePrinter);
        if (hidden) hidden.value = url;
        updatePrinterCard(activePrinter);
    }

    function setActivePrinter(pid) {
        if (activePrinter === pid) {
            activePrinter = null;
            printerCards.forEach(card => card.classList.remove('p-active'));
            assignmentPlaceholder.classList.remove('hidden');
            assignmentContent.classList.add('hidden');
            return;
        }
        if (workflow === 'Regression' && activePrinter) {
            saveRegUrlFromActive();
        }
        activePrinter = pid;
        printerCards.forEach(card => {
            card.classList.toggle('p-active', card.dataset.pid == pid);
        });
        if (!pid) {
            assignmentPlaceholder.classList.remove('hidden');
            assignmentContent.classList.add('hidden');
            return;
        }
        assignmentPlaceholder.classList.add('hidden');
        assignmentContent.classList.remove('hidden');
        if (workflow === 'Smoke') {
            smokeUI.classList.remove('hidden');
            regUI.classList.add('hidden');
            renderActiveSlots();
            updatePoolUsed(pid);
        } else {
            smokeUI.classList.add('hidden');
            regUI.classList.remove('hidden');
            loadRegUrlForActive();
        }
    }
    
    printerCards.forEach(card => {
        card.addEventListener('click', (e) => {
            const pid = Number(card.dataset.pid);
            if (workflow === 'Smoke') {
                // --- SMOKE: MULTI-SELECT (Toggle independent selection) ---
                const checkbox = document.getElementById('cb_' + pid);
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.closest('.p-card').classList.toggle('p-selected', checkbox.checked);
                    const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
                    footCount.textContent = selectedCount;
                }
                // Show assignment panel for the clicked printer
                if (checkbox && checkbox.checked) {
                    activePrinter = pid;
                    assignmentPlaceholder.classList.add('hidden');
                    assignmentContent.classList.remove('hidden');
                    smokeUI.classList.remove('hidden');
                    regUI.classList.add('hidden');
                    // --- CRITICAL FIX: Clear any previous assignments for this printer ---
                    assignments[pid] = [];
                    renderActiveSlots();
                    updatePoolUsed(pid);
                    printerCards.forEach(c => c.classList.remove('p-active'));
                    card.classList.add('p-active');
                } else if (checkbox && !checkbox.checked && activePrinter === pid) {
                    // If deselecting the active printer, close the panel
                    activePrinter = null;
                    assignmentPlaceholder.classList.remove('hidden');
                    assignmentContent.classList.add('hidden');
                    printerCards.forEach(c => c.classList.remove('p-active'));
                }
            } else {
                // --- REGRESSION: MULTI-SELECT (Toggle independent selection) ---
                const checkbox = document.getElementById('cb_' + pid);
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.closest('.p-card').classList.toggle('p-selected', checkbox.checked);
                    if (checkbox.checked) {
                        if (!regressionUrls[pid]) regressionUrls[pid] = '';
                        const hidden = document.getElementById('reg_hidden_' + pid);
                        if (hidden) hidden.value = regressionUrls[pid] || '';
                    } else {
                        const hidden = document.getElementById('reg_hidden_' + pid);
                        if (hidden) hidden.value = '';
                    }
                    const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
                    footCount.textContent = selectedCount;
                }
                if (checkbox && checkbox.checked) {
                    activePrinter = pid;
                    assignmentPlaceholder.classList.add('hidden');
                    assignmentContent.classList.remove('hidden');
                    smokeUI.classList.add('hidden');
                    regUI.classList.remove('hidden');
                    loadRegUrlForActive();
                    printerCards.forEach(c => c.classList.remove('p-active'));
                    card.classList.add('p-active');
                } else if (checkbox && !checkbox.checked && activePrinter === pid) {
                    activePrinter = null;
                    assignmentPlaceholder.classList.remove('hidden');
                    assignmentContent.classList.add('hidden');
                    printerCards.forEach(c => c.classList.remove('p-active'));
                }
            }
        });
    });

    globalPool.addEventListener('click', (e) => {
        const poolChip = e.target.closest('.t-av-wrap');
        if (!poolChip) return;
        if (poolChip.classList.contains('t-used')) return;
        if (!activePrinter) {
            if(typeof showDynamicToast === 'function') showDynamicToast('Please select a printer first.', 'error');
            return;
        }
        const uid = poolChip.dataset.uid;
        const name = poolChip.dataset.name;
        const pfp = poolChip.dataset.pfp;
        const list = assignments[activePrinter];
        if (list.some(t => String(t.uid) === String(uid))) return;
        list.push({ uid, name, pfp });
        renderActiveSlots();
        renderHiddenInputs(activePrinter);
        updatePrinterCard(activePrinter);
        updatePoolUsed(activePrinter);
    });

    regInputActive.addEventListener('input', () => {
        if (activePrinter) {
            saveRegUrlFromActive();
        }
    });

    document.querySelectorAll('input[name="testing_type"]').forEach(r => {
        r.addEventListener('change', (e) => {
            const newWorkflow = e.target.value;
            if (workflow === newWorkflow) return;
            
            if (workflow === 'Regression' && activePrinter) {
                saveRegUrlFromActive();
            }
            
            workflow = newWorkflow;
            
            // --- CRITICAL RESET LOGIC ---
            
            // 1. Reset ALL printer cards (remove selection, uncheck boxes, remove active highlight)
            document.querySelectorAll('.printer-checkbox').forEach(cb => {
                cb.checked = false;
                cb.closest('.p-card').classList.remove('p-selected', 'p-active');
            });
            
            // 2. Clear regression URL data and hidden inputs
            if (workflow === 'Regression') {
                document.querySelectorAll('input[name^="regression_urls"]').forEach(input => {
                    input.value = '';
                });
                Object.keys(regressionUrls).forEach(key => {
                    regressionUrls[key] = '';
                });
            }
            
            // 3. Clear all smoke assignment data
            Object.keys(assignments).forEach(pid => {
                assignments[pid] = [];
            });
            
            // 4. Clear active printer and close assignment panel
            activePrinter = null;
            assignmentPlaceholder.classList.remove('hidden');
            assignmentContent.classList.add('hidden');
            
            // 5. Reset footer counter
            footCount.textContent = '0';
            
            // 6. Re-render hidden inputs for all printers to ensure no stale data is submitted
            printerCards.forEach(card => {
                const pid = card.dataset.pid;
                renderHiddenInputs(pid);
            });
        });
    });

    document.getElementById('mainForm').addEventListener('submit', (e) => {
        if (workflow === 'Regression' && activePrinter) {
            saveRegUrlFromActive();
        }
        // REMOVED: All validation code. Let the PHP handle validation fully.
    });

    // -------------------------------------------------------------------------
    // FINAL FIX: Force the UI to fully re-render after a page reload
    // -------------------------------------------------------------------------
    function restorePageState() {
        let hasData = false;
        // Check if we have saved assignments or regression URLs from PHP
        Object.keys(assignments).forEach(pid => {
            if (assignments[pid] && assignments[pid].length > 0) {
                hasData = true;
            }
        });
        Object.keys(regressionUrls).forEach(pid => {
            if (regressionUrls[pid] && regressionUrls[pid].trim() !== '') {
                hasData = true;
            }
        });

        if (hasData) {
            refreshAllPrinterCards();
            // Find the first selected printer and open the panel for it
            let firstPid = null;
            if (workflow === 'Smoke') {
                for (const pid in assignments) {
                    if (assignments[pid] && assignments[pid].length > 0) {
                        firstPid = parseInt(pid);
                        break;
                    }
                }
            } else {
                for (const pid in regressionUrls) {
                    if (regressionUrls[pid] && regressionUrls[pid].trim() !== '') {
                        firstPid = parseInt(pid);
                        break;
                    }
                }
            }
            if (firstPid !== null) {
                activePrinter = firstPid;
                assignmentPlaceholder.classList.add('hidden');
                assignmentContent.classList.remove('hidden');
                if (workflow === 'Smoke') {
                    smokeUI.classList.remove('hidden');
                    regUI.classList.add('hidden');
                    renderActiveSlots();
                    updatePoolUsed(firstPid);
                } else {
                    smokeUI.classList.add('hidden');
                    regUI.classList.remove('hidden');
                    loadRegUrlForActive();
                }
                printerCards.forEach(card => {
                    card.classList.toggle('p-active', parseInt(card.dataset.pid) === firstPid);
                });
            }
        }
    }

    // Initialize
    refreshAllPrinterCards();
    restorePageState(); 
    
    printerCards.forEach(card => {
        const pid = card.dataset.pid;
        renderHiddenInputs(pid);
    });

})();
</script>

</body>
</html>