<?php
require_once 'controllers/TaskController.php';
require_once 'configs/db.php';
require_once 'configs/helper.php';

// Allow both leads and admins to view tasks
Helper::requireRole(['lead', 'admin']);

$task_id = $_GET['id'] ?? 0;
if (!$task_id) {
    header('Location: index.php');
    exit();
}

// Fetch task details
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();
if (!$task) {
    Helper::setFlash("Task not found.", "error");
    header('Location: index.php');
    exit();
}

// --- GET PRINTER IDS ---
$printer_ids = $_GET['printer_ids'] ?? null;
if ($printer_ids === null && isset($_GET['printer_id'])) {
    $printer_ids = $_GET['printer_id'];
}
if ($printer_ids && strpos($printer_ids, ',') !== false) {
    $printer_ids = explode(',', $printer_ids);
} elseif ($printer_ids) {
    $printer_ids = [$printer_ids];
} else {
    $printer_ids = [];
}

// For the status badge
$printer_id_for_badge = !empty($printer_ids) ? (int)$printer_ids[0] : 0;
$stmt = $pdo->prepare("SELECT overall_status FROM task_assignments WHERE task_id = ? AND printer_id = ? LIMIT 1");
$stmt->execute([$task_id, $printer_id_for_badge]);
$badge_status = $stmt->fetchColumn();
if (empty($badge_status) || $badge_status === 'Pending') {
    $badge_status = 'In Progress';
}

$form_data = [
    'task_date'    => $task['task_date'],
    'due_date'     => $task['due_date'],
    'testing_type' => $task['testing_type'],
    'fw_prev'      => $task['fw_version_prev'],
    'fw_curr'      => $task['fw_version_current'],
    'fw_rec'       => $task['fw_version_rec'],
    'fw_type'      => $task['fw_type']
];

$data = getData($pdo);

// --- FETCH ASSIGNED PRINTERS AND DATA ---
$assignedIdsMap = [];
$stmtAll = $pdo->prepare("SELECT DISTINCT printer_id FROM task_assignments WHERE task_id = ?");
$stmtAll->execute([$task_id]);
$allIds = $stmtAll->fetchAll(PDO::FETCH_COLUMN);
foreach ($allIds as $id) {
    $assignedIdsMap[$id] = true; 
}

$assignments = [];
$regression_urls = [];

if ($task['testing_type'] === 'Smoke') {
    $targetPrinterIds = !empty($printer_ids) ? $printer_ids : $allIds;
    foreach ($targetPrinterIds as $pid) {
        $stmt = $pdo->prepare("
            SELECT ta.*, u.full_name, u.pfp_path 
            FROM task_assignments ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.task_id = ? AND ta.printer_id = ?
        ");
        $stmt->execute([$task_id, $pid]);
        $rows = $stmt->fetchAll();
        
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if (!isset($assignments[$pid])) $assignments[$pid] = [];
                $pfp = !empty($row['pfp_path']) ? $row['pfp_path'] : 'imgs/default_pfp.svg';
                $item = [
                    'uid' => $row['user_id'],
                    'name' => $row['full_name'],
                    'pfp' => $pfp,
                    'designation' => $row['designation']
                ];
                if ($row['designation'] === 'Main') {
                    array_unshift($assignments[$pid], $item);
                } else {
                    $assignments[$pid][] = $item;
                }
            }
        }
    }
} elseif ($task['testing_type'] === 'Regression') {
    $stmt = $pdo->prepare("
        SELECT printer_id, regression_url 
        FROM task_assignments 
        WHERE task_id = ?
    ");
    $stmt->execute([$task_id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $regression_urls[$row['printer_id']] = $row['regression_url'];
    }
}

$saved_assignments = [];
if (!empty($assignments)) {
    foreach ($assignments as $pid => $list) {
        foreach ($list as $t) {
            $saved_assignments[$pid][] = $t;
        }
    }
}

// Prepare JSON for JS
$saved_assignments_json = json_encode((object)$saved_assignments);
$saved_reg_urls_json = json_encode((object)$regression_urls);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Task #<?= $task_id ?> | Track Manager</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
<link rel="stylesheet" href="app.css">
<script>
    let savedTheme = localStorage.getItem('track-manager-theme');
    if (!savedTheme) {
        savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', savedTheme);
</script>
<script src="app.js" defer></script> 

<style>
/* =========================================
   EXACT GRID & LAYOUT MATCH FOR READ-ONLY
   ========================================= */
.f-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.f-grid .f-full {
    grid-column: span 2;
}
@media (max-width: 900px) {
    .f-grid { grid-template-columns: 1fr; }
    .f-grid .f-full { grid-column: span 1; }
}

/* Force inputs to exactly match edit_task layout */
.f-input {
    display: block;
    width: 100%;
    padding: 20px 14px 7px !important;
    background: var(--input-bg);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    font-family: var(--font-body);
    color: var(--text-main);
    outline: none;
    line-height: 1.35;
    height: 52px !important;
    box-sizing: border-box !important;
}
.f-input.f-mono {
    font-family: var(--font-mono);
    font-size: 0.83rem;
}

/* Read-only locks */
.f-input[readonly] {
    pointer-events: none;
    cursor: default;
}
input[type="date"][readonly]::-webkit-calendar-picker-indicator {
    display: none;
}
.f-pill input[type="radio"]:disabled + label {
    cursor: default;
}

/* Exact label positioning */
.f-label {
    position: absolute;
    left: 14px;
    top: 8px;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted);
    pointer-events: none;
    transition: color 0.14s;
    font-family: 'DM Sans', sans-serif;
}

/* --- PRINTER CARDS (Read-Only) --- */
.p-card {
    cursor: default !important;
    transition: none !important;
}
.p-card:hover {
    border-color: var(--border) !important;
    box-shadow: none !important;
    background: var(--bg-surface) !important;
}
.p-card.p-selected {
    background: var(--bg-surface);
    border-color: var(--primary);
}
.p-card.p-selected .selected-badge { opacity: 1; }
.p-card .selected-badge { opacity: 0; }

/* --- VIEW PANEL STYLES --- */
.view-details-panel {
    flex: 1;
    background: var(--bg-body);
    padding: 18px 16px;
    overflow-y: auto;
    border-top: 2px solid var(--border);
}
.view-details-panel .placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 80px;
    color: var(--text-muted);
    font-size: 0.8rem;
    text-align: center;
    background: var(--bg-surface);
    border-radius: 10px;
    padding: 20px;
    border: 1px solid var(--border);
}
.view-details-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 16px;
}
.view-details-card h4 {
    margin: 0 0 8px 0;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 8px;
}
.view-details-card .label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-top: 12px;
    display: block;
}
.view-details-card .value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-main);
    word-break: break-all;
    margin-top: 2px;
}

/* --- REFINED URL UI (Clean link) --- */
.url-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    word-break: break-all;
    font-weight: 500;
}
.url-link:hover {
    text-decoration: underline;
}
.no-url {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 0.85rem;
}

.view-details-card .testers-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 6px;
}
.view-details-card .tester-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 4px 10px 4px 4px;
    font-size: 0.78rem;
    font-weight: 600;
}
.view-details-card .tester-chip img {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border);
}
.view-details-card .tester-chip .role-badge {
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
    background: var(--primary);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
}

/* --- REST OF THE LAYOUT (Matches Edit exactly) --- */
body { font-family: 'Manrope', sans-serif; background: var(--bg-body); color: var(--text-main); height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
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
.lp-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main); line-height: 1.2; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.lp-sub { font-size: 0.82rem; color: var(--text-muted); margin-top: 5px; }

.s-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 24px; flex-shrink: 0; }
.s-card-head { padding: 13px 20px; border-bottom: 1px solid var(--border); background: var(--bg-body); display: flex; align-items: center; gap: 10px; }
.s-num { width: 22px; height: 22px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.67rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.s-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); }
.s-card-body { padding: 22px 20px; }

.f-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.f-grid .f-full { grid-column: span 2; }
@media (max-width: 900px) { .f-grid { grid-template-columns: 1fr; } .f-grid .f-full { grid-column: span 1; } }

.f-field { position: relative; }
.f-input { display: block; width: 100%; padding: 20px 14px 7px; background: var(--input-bg); border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.9rem; font-family: var(--font-body); color: var(--text-main); outline: none; transition: border-color 0.15s, box-shadow 0.15s, background 0.15s; line-height: 1.35; height: 52px; }
.f-input.f-mono { font-family: var(--font-mono); font-size: 0.83rem; }
.f-label { position: absolute; left: 14px; top: 8px; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); pointer-events: none; transition: color 0.14s; font-family: 'DM Sans', sans-serif; }
input[type="date"].f-input { padding-top: 22px; padding-bottom: 5px; cursor: default; color: var(--text-main); }

.f-pill-wrap { display: flex; flex-direction: column; gap: 7px; height: 100%; justify-content: flex-end; }
.f-pill-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); font-family: 'DM Sans', sans-serif; }
.f-pill { display: flex; background: var(--bg-body); border-radius: 8px; padding: 3px; gap: 2px; height: 38px;}
.f-pill input[type="radio"] { display: none; }
.f-pill label { flex: 1; text-align: center; padding: 7px 8px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); cursor: default; transition: all 0.16s; user-select: none; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 4px; font-family: 'Manrope', sans-serif; }
.f-pill input[type="radio"]:checked + label { background: var(--bg-surface); color: var(--primary); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

.right-panel { background: var(--bg-surface); border-left: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
.rp-head { flex-shrink: 0; padding: 14px 18px 12px; border-bottom: 1px solid var(--border); background: var(--bg-body); display: flex; flex-direction: column; gap: 2px; }
.rp-head-title { font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); font-family: 'DM Sans', sans-serif; }
.rp-head-sub { font-size: 0.7rem; color: var(--text-muted); font-family: 'Manrope', sans-serif; }

.printer-grid-container { flex-shrink: 0; padding: 16px 12px; border-bottom: 1px solid var(--border); overflow-y: auto; max-height: 55vh; }
.printer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }

.p-card { position: relative; background: var(--bg-surface); border: 1.5px solid var(--border); border-radius: 12px; padding: 14px 8px 10px; display: flex; flex-direction: column; align-items: center; text-align: center; cursor: default !important; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.p-card.p-selected { background: var(--bg-surface); border-color: var(--primary); }
.p-card.p-selected .selected-badge { opacity: 1; }
.p-card-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
.p-card-name { font-size: 0.78rem; font-weight: 700; color: var(--text-main); line-height: 1.3; margin-bottom: 4px; max-width: 100%; word-break: break-word; font-family: 'Manrope', sans-serif; }
.selected-badge { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
.selected-badge .material-symbols-outlined { font-size: 14px; }

.rp-foot { flex-shrink: 0; border-top: 1px solid var(--border); padding: 10px 16px; background: var(--bg-body); font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; font-family: 'Manrope', sans-serif; }
.rp-foot-count { font-weight: 800; color: var(--primary); min-width: 14px; display: inline-block; text-align: center; }

[data-theme="dark"] input[type="date"].f-input::-webkit-calendar-picker-indicator,
[data-theme="midnight"] input[type="date"].f-input::-webkit-calendar-picker-indicator,
[data-theme="catppuccin"] input[type="date"].f-input::-webkit-calendar-picker-indicator { filter: invert(0.8); }

.status-badge-header {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-family: 'DM Sans', sans-serif;
}
.status-badge-header.Pass { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
.status-badge-header.Fail { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.status-badge-header.Blocked { background: rgba(249, 115, 22, 0.15); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.3); }
.status-badge-header.NA { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); }
.status-badge-header.Completed { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
.status-badge-header.In-Progress { background: rgba(234, 179, 8, 0.15); color: #ca8a04; border: 1px solid rgba(234, 179, 8, 0.3); }
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
        <a href="tasks.php">Tasks</a>
        <span class="tb-crumb-sep">›</span>
        <span class="tb-crumb-cur">View Task #<?= $task_id ?></span>
    </nav>
</header>

<div class="page-shell">
    <main class="left-panel">
        <div class="lp-heading">
            <h1 class="lp-title">
                View Task
                <span class="status-badge-header <?= str_replace(' ', '-', $badge_status) ?>">
                    <span class="material-symbols-outlined" style="font-size: 16px;">
                        <?= match($badge_status) {
                            'Pass' => 'check_circle',
                            'Fail' => 'cancel',
                            'Blocked' => 'block',
                            'N/A' => 'do_not_disturb_on',
                            'Completed' => 'check_circle',
                            default => 'schedule'
                        } ?>
                    </span>
                    <?= $badge_status ?>
                </span>
            </h1>
            <p class="lp-sub">View-only mode. Task details and assignments are read-only.</p>
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
                               value="<?= htmlspecialchars($form_data['task_date']) ?>" readonly>
                        <label class="f-label">Task Date</label>
                    </div>
                    <div class="f-field">
                        <input type="date" name="due_date" class="f-input" 
                               value="<?= htmlspecialchars($form_data['due_date']) ?>" readonly>
                        <label class="f-label">Due Date</label>
                    </div>
                    
                    <div class="f-full">
                        <div class="f-pill-wrap">
                            <span class="f-pill-label">Testing Workflow</span>
                            <div class="f-pill">
                                <input type="radio" name="testing_type" id="wf_smoke" value="Smoke" 
                                    <?= ($form_data['testing_type'] == 'Smoke') ? 'checked' : '' ?> disabled>
                                <label for="wf_smoke">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">local_fire_department</span> Smoke Test
                                </label>

                                <input type="radio" name="testing_type" id="wf_reg" value="Regression"
                                    <?= ($form_data['testing_type'] == 'Regression') ? 'checked' : '' ?> disabled>
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
                    <div class="f-field">
                        <input type="text" name="fw_prev" class="f-input f-mono" 
                               value="<?= htmlspecialchars($form_data['fw_prev'] ?? '') ?>" placeholder="e.g. 24.1.0" readonly>
                        <label class="f-label">Previous Firmware</label>
                    </div>
                    <div class="f-field">
                        <input type="text" name="fw_curr" class="f-input f-mono" 
                               value="<?= htmlspecialchars($form_data['fw_curr'] ?? '') ?>" placeholder="e.g. 24.2.0" readonly>
                        <label class="f-label">Current Firmware</label>
                    </div>
                    
                    <div class="f-field">
                        <input type="text" name="fw_rec" class="f-input f-mono" 
                               value="<?= htmlspecialchars($form_data['fw_rec'] ?? '') ?>" placeholder="e.g. 24.0.5" readonly>
                        <label class="f-label">Recovery Firmware</label>
                    </div>
                    <div>
                        <div class="f-pill-wrap">
                            <span class="f-pill-label">Firmware Type</span>
                            <div class="f-pill">
                                <input type="radio" name="fw_type" id="ft_trunk" value="Trunk" 
                                       <?= ($form_data['fw_type'] == 'Trunk') ? 'checked' : '' ?> disabled>
                                <label for="ft_trunk">Trunk</label>
                                <input type="radio" name="fw_type" id="ft_branch" value="Branch"
                                       <?= ($form_data['fw_type'] == 'Branch') ? 'checked' : '' ?> disabled>
                                <label for="ft_branch">Branch</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <aside class="right-panel">
        <div class="rp-head">
            <span class="rp-head-title">PRINTER &amp; ASSIGNMENTS</span>
            <span class="rp-head-sub">Click a printer to view its assignments.</span>
        </div>

        <div class="printer-grid-container">
            <div class="printer-grid" id="printerGrid">
                <?php foreach ($data['printers'] as $p): 
                    if (isset($p['status']) && $p['status'] === 'inactive') continue;
                    
                    $isSelected = false;
                    if ($task['testing_type'] === 'Smoke') {
                        $isSelected = isset($saved_assignments[$p['id']]) && !empty($saved_assignments[$p['id']]);
                    } else {
                        $isSelected = isset($regression_urls[$p['id']]) && !empty($regression_urls[$p['id']]);
                    }
                ?>
                <div class="p-card <?= $isSelected ? 'p-selected' : '' ?>" data-pid="<?= $p['id'] ?>" id="pc_<?= $p['id'] ?>">
                    <div class="p-card-icon" style="overflow: hidden; padding: 2px;">
                        <?= Helper::renderPrinterImage($p['printer_path'] ?? null, $p['model_name'], 24) ?>
                    </div>
                    <div class="p-card-name"><?= htmlspecialchars($p['model_name']) ?></div>
                    <div class="selected-badge"><span class="material-symbols-outlined">check</span></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="view-details-panel" id="assignmentPanel">
            <div class="placeholder" id="assignmentPlaceholder">
                Select a printer above to view assignments.
            </div>
            <div id="assignmentContent"></div>
        </div>

        <div class="rp-foot">
            <span class="rp-foot-count" id="footCount">0</span>
            printer(s) selected
        </div>
    </aside>
</div>

<script>
(function() {
    'use strict';

    // PHP Data
    const assignments = <?= $saved_assignments_json ?>;
    const regressionUrls = <?= $saved_reg_urls_json ?>;
    const workflow = '<?= $task['testing_type'] ?? 'Smoke' ?>';

    const printerCards = document.querySelectorAll('.p-card');
    const footCount = document.getElementById('footCount');
    const assignmentPanel = document.getElementById('assignmentContent');
    const assignmentPlaceholder = document.getElementById('assignmentPlaceholder');

    // Count selected printers on load
    const selectedCount = document.querySelectorAll('.p-card.p-selected').length;
    footCount.textContent = selectedCount;

    function firstName(fullName) {
        return (fullName || '').split(' ')[0];
    }

    function renderDetails(pid) {
        if (!pid) {
            assignmentPanel.innerHTML = '';
            assignmentPlaceholder.classList.remove('hidden');
            return;
        }
        assignmentPlaceholder.classList.add('hidden');
        
        const card = document.getElementById('pc_' + pid);
        if (!card) return;
        const modelName = card.querySelector('.p-card-name').textContent;
        
        let html = `<div class="view-details-card"><h4>${modelName}</h4>`;

        if (workflow === 'Smoke') {
            const list = assignments[pid] || [];
            html += `<div class="label">Assigned Testers</div>`;
            if (list.length === 0) {
                html += `<div class="value" style="color:var(--text-muted); font-weight:500;">No testers assigned</div>`;
            } else {
                html += `<div class="testers-list">`;
                list.forEach(t => {
                    const isMain = (t.designation === 'Main');
                    html += `
                        <div class="tester-chip">
                            <img src="${t.pfp}" alt="${t.name}">
                            ${firstName(t.name)}
                            ${isMain ? `<span class="role-badge">MAIN</span>` : ''}
                        </div>
                    `;
                });
                html += `</div>`;
            }
        } else {
            // Regression
            const url = regressionUrls[pid] || '';
            html += `<div class="label">TestRail Run URL</div>`;
            if (url) {
                html += `<div class="value"><a href="${url}" target="_blank" class="url-link">${url}</a></div>`;
            } else {
                html += `<div class="no-url">No URL assigned</div>`;
            }
        }

        html += `</div>`;
        assignmentPanel.innerHTML = html;
    }

    // Click handler to show details
    printerCards.forEach(card => {
        card.addEventListener('click', (e) => {
            // Remove active highlight from all
            printerCards.forEach(c => c.classList.remove('p-active'));
            // Highlight the clicked one
            card.classList.add('p-active');
            
            const pid = Number(card.dataset.pid);
            renderDetails(pid);
        });
    });

    // Pre-load the first selected printer if any
    const firstSelected = document.querySelector('.p-card.p-selected');
    if (firstSelected) {
        const pid = Number(firstSelected.dataset.pid);
        renderDetails(pid);
        firstSelected.classList.add('p-active');
    }

})();
</script>
</body>
</html>