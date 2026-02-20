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

function getPrinterIcon(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'flare')) return 'local_fire_department';
    if (str_contains($n, 'ray'))   return 'bolt';
    if (str_contains($n, 'mfp'))  return 'content_copy';
    if (str_contains($n, 'sfp'))  return 'print';
    return 'print';
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
<link rel="stylesheet" href="app.css">

<style>
/* ══════════════════════════════════════════════════
   RESET / GLOBAL
══════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', system-ui, sans-serif;
    background: var(--bg-body);
    color: var(--text-main);
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ══════════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════════ */
.topbar {
    flex-shrink: 0;
    height: 56px;
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    z-index: 100;
}
.tb-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-main);
    text-decoration: none;
}
.tb-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }
.tb-crumb { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted); }
.tb-crumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
.tb-crumb a:hover { color: var(--primary); }
.tb-crumb-sep { color: var(--border); }
.tb-crumb-cur { color: var(--text-main); font-weight: 600; }

/* ══════════════════════════════════════════════════
   MAIN SPLIT LAYOUT (Act as a Form)
══════════════════════════════════════════════════ */
.page-shell {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 380px;
    overflow: hidden;
    min-height: 0;
}

/* ══════════════════════════════════════════════════
   LEFT PANEL (FIXED CLIPPING BUG)
══════════════════════════════════════════════════ */
.left-panel {
    overflow-y: auto;
    padding: 32px 36px 64px;
    background: var(--bg-body);
    display: block; /* Changed from Flex to Block to prevent height shrinking */
}

.lp-heading { margin-bottom: 24px; }
.lp-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main); line-height: 1.2; }
.lp-sub { font-size: 0.82rem; color: var(--text-muted); margin-top: 5px; }

.s-card { 
    background: var(--bg-surface); 
    border: 1px solid var(--border); 
    border-radius: 12px; 
    overflow: hidden; 
    margin-bottom: 24px; /* Space between cards */
    flex-shrink: 0; /* Force protection against clipping */
}
.s-card-head { padding: 13px 20px; border-bottom: 1px solid var(--border); background: #fafbfc; display: flex; align-items: center; gap: 10px; }
.s-num { width: 22px; height: 22px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.67rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.s-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); }
.s-card-body { padding: 22px 20px; }

.f-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.f-grid .f-full { grid-column: span 2; }

@media (max-width: 900px) {
    .f-grid { grid-template-columns: 1fr; }
    .f-grid .f-full { grid-column: span 1; }
}

.f-field { position: relative; }
.f-input {
    display: block; width: 100%; padding: 20px 14px 7px; background: #f9fafb;
    border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.9rem;
    font-family: 'Inter', sans-serif; color: var(--text-main); outline: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s; line-height: 1.35; height: 52px;
}
.f-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(2,136,209,0.1); }
.f-input.f-mono { font-family: 'JetBrains Mono', monospace; font-size: 0.83rem; }
.f-label {
    position: absolute; left: 14px; top: 8px; font-size: 0.6rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted);
    pointer-events: none; white-space: nowrap; transition: color 0.14s;
}
.f-input:focus ~ .f-label { color: var(--primary); }
input[type="date"].f-input { padding-top: 22px; padding-bottom: 5px; cursor: pointer; }

.f-pill-wrap { display: flex; flex-direction: column; gap: 7px; height: 100%; justify-content: flex-end; }
.f-pill-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); }
.f-pill { display: flex; background: #f1f5f9; border-radius: 8px; padding: 3px; gap: 2px; height: 38px;}
.f-pill input[type="radio"] { display: none; }
.f-pill label {
    flex: 1; text-align: center; padding: 7px 8px; border-radius: 6px;
    font-size: 0.82rem; font-weight: 600; color: var(--text-muted); cursor: pointer;
    transition: all 0.16s; user-select: none; white-space: nowrap;
}
.f-pill input[type="radio"]:checked + label { background: #fff; color: var(--primary); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

.action-row { display: flex; gap: 12px; align-items: center; margin-top: 10px; }
.btn-create {
    display: inline-flex; align-items: center; gap: 7px; padding: 11px 24px;
    background: var(--primary); color: #fff; border: none; border-radius: 8px;
    font-size: 0.87rem; font-weight: 700; cursor: pointer; transition: background 0.14s, transform 0.12s, box-shadow 0.14s;
}
.btn-create:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(2,136,209,0.3); }
.btn-cancel {
    display: inline-flex; align-items: center; padding: 11px 20px;
    border: 1.5px solid var(--border); border-radius: 8px; background: transparent;
    color: var(--text-muted); font-size: 0.87rem; font-weight: 600; text-decoration: none; transition: border-color 0.14s, color 0.14s;
}
.btn-cancel:hover { border-color: #9ca3af; color: var(--text-main); }

/* ══════════════════════════════════════════════════
   RIGHT SIDEBAR
══════════════════════════════════════════════════ */
.right-panel {
    background: var(--bg-surface);
    border-left: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}
.rp-head {
    flex-shrink: 0; padding: 14px 18px 12px; border-bottom: 1px solid var(--border); background: #fafbfc;
}
.rp-head-title { font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); display: block; margin-bottom: 2px; }
.rp-head-sub { font-size: 0.7rem; color: var(--text-muted); }

/* ── Printer Grid (Dynamic Size) ── */
.printer-grid-container {
    flex: 1 1 0; /* Grow and Shrink seamlessly */
    overflow-y: auto;
    padding: 16px 12px;
    border-bottom: 1px solid var(--border);
}
.printer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }

/* Printer Card */
.p-card {
    position: relative; background: var(--bg-surface); border: 1.5px solid var(--border);
    border-radius: 12px; padding: 14px 8px 10px; display: flex; flex-direction: column;
    align-items: center; text-align: center; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.p-card:hover { border-color: #93c5fd; box-shadow: 0 6px 14px rgba(2,136,209,0.08); background: #fafdff; }
.p-card.p-active { border-color: var(--primary); background: #f0f7ff; box-shadow: 0 0 0 3px rgba(2,136,209,0.15); }
.p-card.p-selected { background: #ffffff; }
.p-card.p-selected .selected-badge { opacity: 1; }

.p-card-icon { width: 44px; height: 44px; border-radius: 12px; background: #eef2f6; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; transition: background 0.2s; }
.p-card-icon .material-symbols-outlined { font-size: 24px; color: #546e7a; }
.p-card.p-active .p-card-icon { background: #d4e2fc; }
.p-card.p-active .p-card-icon .material-symbols-outlined { color: var(--primary); }
.p-card-name { font-size: 0.78rem; font-weight: 700; color: var(--text-main); line-height: 1.3; margin-bottom: 4px; max-width: 100%; word-break: break-word; }

.selected-badge { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
.selected-badge .material-symbols-outlined { font-size: 14px; }

/* ── Assignment Panel (Dynamic Size) ── */
.assignment-panel {
    flex: 0 0 auto; /* Allow to size to content without forcing big height */
    background: #f9fbfd;
    padding: 18px 16px;
    overflow-y: auto;
    max-height: 50%; /* Prevent taking up the entire screen */
}

.assignment-placeholder { display: flex; align-items: center; justify-content: center; height: 100px; color: var(--text-muted); font-size: 0.8rem; text-align: center; background: #f1f5f9; border-radius: 10px; padding: 20px; }
.assignment-content { display: flex; flex-direction: column; gap: 22px; }
.hidden { display: none !important; }

.micro-label { display: block; font-size: 0.59rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 10px; }

/* Tester Pool */
.t-pool { display: flex; flex-wrap: wrap; gap: 12px; }
.t-av-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; cursor: pointer; transition: transform 0.15s, opacity 0.15s; }
.t-av-wrap:hover { transform: translateY(-3px); }
.t-av-wrap.t-used { opacity: 0.2; pointer-events: none; transform: none; }
.t-av { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; color: #fff; box-shadow: 0 3px 8px rgba(0,0,0,0.15); }
.t-nm { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); max-width: 48px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; }

/* Assigned Slots */
.a-slots { display: flex; flex-wrap: wrap; gap: 8px; min-height: 48px; padding: 8px 6px; border: 1.5px dashed var(--border); border-radius: 10px; background: white; align-items: center; }
.a-ph { font-size: 0.72rem; color: #b6c4d0; width: 100%; text-align: center; pointer-events: none; }
.a-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 4px; border: 1.5px solid var(--border); border-radius: 30px; background: white; cursor: pointer; transition: all 0.14s; box-shadow: 0 1px 3px rgba(0,0,0,0.04); user-select: none; }
.a-chip:hover { border-color: var(--primary); background: #f0f9ff; }
.a-chip.a-main { border-color: var(--primary); background: #e6f2ff; box-shadow: 0 0 0 2px rgba(2,136,209,0.2); }
.a-chip-av { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 800; color: white; flex-shrink: 0; }
.a-chip-name { font-size: 0.72rem; font-weight: 600; color: var(--text-main); }
.a-chip-role { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; padding: 2px 6px; border-radius: 12px; background: #eef2f6; color: #4b5563; }
.a-chip.a-main .a-chip-role { background: var(--primary); color: white; }
.a-chip-close { width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #8b9aa8; transition: background 0.14s, color 0.14s; margin-left: 2px; }
.a-chip-close:hover { background: #fee2e2; color: var(--error); }
.a-chip-close .material-symbols-outlined { font-size: 14px; }

/* Regression URL input */
.reg-wrap { position: relative; }
.reg-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; display: flex; align-items: center; }
.reg-icon .material-symbols-outlined { font-size: 14px; color: #94a3b8; }
.reg-input { width: 100%; padding: 10px 10px 10px 32px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.78rem; font-family: 'Inter', sans-serif; color: var(--text-main); background: white; outline: none; transition: border-color 0.14s, box-shadow 0.14s; }
.reg-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(2,136,209,0.1); }

/* Footer */
.rp-foot { flex-shrink: 0; border-top: 1px solid var(--border); padding: 10px 16px; background: #fafbfc; font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
.rp-foot-count { font-weight: 800; color: var(--primary); min-width: 14px; display: inline-block; text-align: center; }

/* Avatar color palette */
.av0 { background: linear-gradient(135deg,#0288d1,#01579b); }
.av1 { background: linear-gradient(135deg,#16a34a,#166534); }
.av2 { background: linear-gradient(135deg,#7c3aed,#4c1d95); }
.av3 { background: linear-gradient(135deg,#e11d48,#9f1239); }
.av4 { background: linear-gradient(135deg,#d97706,#92400e); }
.av5 { background: linear-gradient(135deg,#0d9488,#115e59); }
.av6 { background: linear-gradient(135deg,#6366f1,#4338ca); }
.av7 { background: linear-gradient(135deg,#ec4899,#be185d); }
</style>
</head>
<body>

<?php Helper::displayFlash(); ?>

<header class="topbar">
    <a href="index.php" class="tb-brand">
        <span class="tb-dot"></span>
        Track Manager
    </a>
    <nav class="tb-crumb">
        <a href="index.php">Dashboard</a>
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
                                <label for="wf_smoke">🔥&nbsp; Smoke Test</label>
                                <input type="radio" name="testing_type" id="wf_reg" value="Regression"
                                       <?= ($form_data['testing_type'] ?? '') == 'Regression' ? 'checked' : '' ?>>
                                <label for="wf_reg">📋&nbsp; Regression Test</label>
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
                    <div class="f-field">
                        <input type="text" name="fw_prev" class="f-input f-mono" 
                               value="<?= htmlspecialchars($form_data['fw_prev'] ?? '') ?>" placeholder="e.g. 24.1.0" required>
                        <label class="f-label">Previous Firmware</label>
                    </div>
                    <div class="f-field">
                        <input type="text" name="fw_curr" class="f-input f-mono" 
                               value="<?= htmlspecialchars($form_data['fw_curr'] ?? '') ?>" placeholder="e.g. 24.2.0" required>
                        <label class="f-label">Current Firmware</label>
                    </div>
                    <div class="f-field">
                        <input type="text" name="fw_rec" class="f-input f-mono" 
                               value="<?= htmlspecialchars($form_data['fw_rec'] ?? '') ?>" placeholder="e.g. 24.0.5" required>
                        <label class="f-label">Recovery Firmware</label>
                    </div>
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
                </div>
            </div>
        </div>

        <div class="action-row">
            <button type="submit" class="btn-create">
                <span class="material-symbols-outlined">check_circle</span>
                Create Task
            </button>
            <a href="index.php" class="btn-cancel">Cancel</a>
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
                    $icon = getPrinterIcon($p['model_name']);
                    $hasSaved = isset($saved_assignments[$p['id']]) || !empty($saved_reg_urls[$p['id']]);
                ?>
                <div class="p-card <?= $hasSaved ? 'p-selected' : '' ?>" data-pid="<?= $p['id'] ?>" id="pc_<?= $p['id'] ?>">
                    <div class="p-card-icon">
                        <span class="material-symbols-outlined"><?= $icon ?></span>
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
    $fn = trim($u['full_name']);
    $parts = explode(' ', $fn);
    $pfp = !empty($u['pfp_path']) ? $u['pfp_path'] : 'imgs/default_pfp.svg';
?>
                            <div class="t-av-wrap" data-uid="<?= $u['id'] ?>" data-name="<?= htmlspecialchars($fn, ENT_QUOTES) ?>" data-pfp="<?= htmlspecialchars($pfp) ?>">
    <div class="t-av" style="background: transparent;">
        <img src="<?= htmlspecialchars($pfp) ?>" class="pfp-img">
    </div>
    <span class="t-nm"><?= htmlspecialchars($parts[0]) ?></span>
</div>
<?php endforeach; ?>
                        </div>
                    </div>
                    <div class="assigned-section">
                        <span class="micro-label" style="margin-top: 20px;">Assigned — click chip to toggle MAIN, <span class="material-symbols-outlined" style="font-size:12px;">close</span> to remove</span>
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

    const assignments = <?= $saved_assignments_json ?>;
    const regressionUrls = <?= $saved_reg_urls_json ?>;
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
                <div class="a-chip-av" style="background: transparent;">
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
        if (activePrinter === pid) return;

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
            const pid = card.dataset.pid;
            setActivePrinter(Number(pid));
        });
    });

    globalPool.addEventListener('click', (e) => {
        const poolChip = e.target.closest('.t-av-wrap');
        if (!poolChip) return;
        if (poolChip.classList.contains('t-used')) return;
        if (!activePrinter) {
            alert('Please select a printer first.');
            return;
        }
        const uid = poolChip.dataset.uid;
        const name = poolChip.dataset.name;
        const pfp = poolChip.dataset.pfp;
        const av = poolChip.dataset.av;

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
            
            refreshAllPrinterCards(); 
            
            const currentPid = activePrinter;
            activePrinter = null; 
            if (currentPid) {
                setActivePrinter(currentPid);
            }
        });
    });

    document.getElementById('mainForm').addEventListener('submit', () => {
        if (workflow === 'Regression' && activePrinter) {
            saveRegUrlFromActive();
        }
    });

    refreshAllPrinterCards();
    setActivePrinter(null); 
    
    printerCards.forEach(card => {
        const pid = card.dataset.pid;
        updatePrinterCard(pid);
        renderHiddenInputs(pid);
    });

})();
</script>

</body>
</html>