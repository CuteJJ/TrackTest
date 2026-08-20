<?php
require_once 'controllers/TaskController.php';
require_once 'configs/db.php';
require_once 'configs/helper.php';

// Allow both leads and admins to edit tasks
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

// --- GET PRINTER IDS (supports comma-separated list) ---
$printer_ids = $_GET['printer_ids'] ?? null;

// If a single printer_id was passed (old format), convert it to an array
if ($printer_ids === null && isset($_GET['printer_id'])) {
    $printer_ids = $_GET['printer_id'];
}

// If we have a comma-separated string, explode it into an array
if ($printer_ids && strpos($printer_ids, ',') !== false) {
    $printer_ids = explode(',', $printer_ids);
} elseif ($printer_ids) {
    $printer_ids = [$printer_ids];
} else {
    $printer_ids = [];
}

// For the status badge, use the FIRST printer ID (just for display)
$printer_id_for_badge = !empty($printer_ids) ? (int)$printer_ids[0] : 0;

// Fetch the overall_status for the first printer (for the badge)
$stmt = $pdo->prepare("SELECT overall_status FROM task_assignments WHERE task_id = ? AND printer_id = ? LIMIT 1");
$stmt->execute([$task_id, $printer_id_for_badge]);
$badge_status = $stmt->fetchColumn();

if (empty($badge_status) || $badge_status === 'Pending') {
    $badge_status = 'In Progress';
}

// Restore form data from session if validation failed
$form_data = $_SESSION['edit_task_form'] ?? null;
$validation_failed = false;

if ($form_data) {
    unset($_SESSION['edit_task_form']);
    $validation_failed = true;
} else {
    $form_data = [
        'task_date'    => $task['task_date'],
        'due_date'     => $task['due_date'],
        'testing_type' => $task['testing_type'],
        'fw_prev'      => $task['fw_version_prev'],
        'fw_curr'      => $task['fw_version_current'],
        'fw_rec'       => $task['fw_version_rec'],
        'fw_type'      => $task['fw_type']
    ];
}

$data = getData($pdo);

$user_map = [];
foreach ($data['users'] as $u) {
    $user_map[$u['id']] = $u;
}

// --- FETCH ONLY ASSIGNED PRINTERS FOR THIS TASK ---
$assignedIdsMap = [];
$stmtAll = $pdo->prepare("SELECT DISTINCT printer_id FROM task_assignments WHERE task_id = ?");
$stmtAll->execute([$task_id]);
$allIds = $stmtAll->fetchAll(PDO::FETCH_COLUMN);
foreach ($allIds as $id) {
    $assignedIdsMap[$id] = true; 
}

// --- FETCH TESTERS FOR ALL PRINTERS (Smoke) OR URLS (Regression) ---
$assignments = [];
$regression_urls = [];

if ($task['testing_type'] === 'Smoke') {
    // SMOKE: Fetch testers for ALL printers in this task
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
    // REGRESSION: Fetch URLs for all assigned printers
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
$saved_reg_urls = $regression_urls;

if ($validation_failed) {
    $saved_reg_urls = [];
    if (isset($form_data['assignments']) && is_array($form_data['assignments'])) {
        foreach ($form_data['assignments'] as $pid => $uids) {
            foreach ($uids as $uid) {
                if (isset($user_map[$uid])) {
                    $u = $user_map[$uid];
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
} else {
    if (!empty($assignments)) {
        foreach ($assignments as $pid => $list) {
            foreach ($list as $t) {
                $saved_assignments[$pid][] = $t;
            }
        }
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
<title>Edit Task | Track Manager</title>
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
body {
    font-family: 'Manrope', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

h1, h2, h3, h4, h5, h6, .tb-brand, .rp-head-title, .micro-label, .lp-title, .f-label, .f-label-static {
    font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.mono, .fw-value, .mono-text, .f-input.f-mono {
    font-family: 'JetBrains Mono', monospace;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body { background: var(--bg-body); color: var(--text-main); height: 100vh; overflow: hidden; display: flex; flex-direction: column; }

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
.f-input { display: block; width: 100%; padding: 20px 14px 7px; background: var(--input-bg); border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.9rem; color: var(--text-main); outline: none; transition: border-color 0.15s, box-shadow 0.15s, background 0.15s; line-height: 1.35; height: 52px; font-family: 'Manrope', sans-serif; }
.f-input:focus { border-color: var(--primary); background: var(--bg-surface); box-shadow: 0 0 0 3px rgba(2,136,209,0.1); }
.f-input.f-mono { font-family: 'JetBrains Mono', monospace; font-size: 0.83rem; }
.f-label { position: absolute; left: 14px; top: 8px; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); pointer-events: none; transition: color 0.14s; font-family: 'DM Sans', sans-serif; }
.f-input:focus ~ .f-label { color: var(--primary); }
input[type="date"].f-input { padding-top: 22px; padding-bottom: 5px; cursor: pointer; color: var(--text-main); }

.f-pill-wrap { display: flex; flex-direction: column; gap: 7px; height: 100%; justify-content: flex-end; }
.f-pill-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); font-family: 'DM Sans', sans-serif; }
.f-pill { display: flex; background: var(--bg-body); border-radius: 8px; padding: 3px; gap: 2px; height: 38px;}
.f-pill input[type="radio"] { display: none; }
.f-pill label { flex: 1; text-align: center; padding: 7px 8px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.16s; user-select: none; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 4px; font-family: 'Manrope', sans-serif; }
.f-pill input[type="radio"]:checked + label { background: var(--bg-surface); color: var(--primary); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

.action-row { display: flex; gap: 12px; align-items: center; margin-top: 10px; }
.btn-create { display: inline-flex; align-items: center; gap: 7px; padding: 11px 24px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-size: 0.87rem; font-weight: 700; cursor: pointer; transition: background 0.14s, transform 0.12s, box-shadow 0.14s; font-family: 'Manrope', sans-serif; }
.btn-create:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(2,136,209,0.3); }
.btn-cancel { display: inline-flex; align-items: center; padding: 11px 20px; border: 1.5px solid var(--border); border-radius: 8px; background: transparent; color: var(--text-muted); font-size: 0.87rem; font-weight: 600; text-decoration: none; transition: border-color 0.14s, color 0.14s; font-family: 'Manrope', sans-serif; }
.btn-cancel:hover { border-color: var(--primary); color: var(--text-main); }

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
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.rp-head-title { font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); font-family: 'DM Sans', sans-serif; }
.rp-head-sub { font-size: 0.7rem; color: var(--text-muted); font-family: 'Manrope', sans-serif; }

.printer-grid-container { 
    flex-shrink: 0; 
    padding: 16px 12px; 
    border-bottom: 1px solid var(--border);
    overflow-y: auto;
    max-height: 55vh; 
}
.printer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }

.p-card { position: relative; background: var(--bg-surface); border: 1.5px solid var(--border); border-radius: 12px; padding: 14px 8px 10px; display: flex; flex-direction: column; align-items: center; text-align: center; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.p-card:hover { border-color: var(--primary); box-shadow: 0 6px 14px rgba(2,136,209,0.08); background: var(--bg-body); }
.p-card.p-active { border-color: var(--primary); background: var(--bg-body); box-shadow: 0 0 0 2px var(--primary); }
.p-card.p-selected { background: var(--bg-surface); border-color: var(--primary); }
.p-card.p-selected .selected-badge { opacity: 1; }
.p-card-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; margin-bottom: 8px; transition: background 0.2s; }
.p-card.p-active .p-card-icon { background: var(--primary); }
.p-card.p-active .p-card-icon .material-symbols-outlined { color: var(--bg-surface); }
.p-card-name { font-size: 0.78rem; font-weight: 700; color: var(--text-main); line-height: 1.3; margin-bottom: 4px; max-width: 100%; word-break: break-word; font-family: 'Manrope', sans-serif; }
.selected-badge { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
.selected-badge .material-symbols-outlined { font-size: 14px; }

.assignment-panel { 
    flex: 1; 
    background: var(--bg-body); 
    padding: 18px 16px; 
    overflow-y: auto; 
    border-top: 2px solid var(--border); 
}
.assignment-placeholder { display: flex; align-items: center; justify-content: center; height: 80px; color: var(--text-muted); font-size: 0.8rem; text-align: center; background: var(--bg-surface); border-radius: 10px; padding: 20px; border: 1px solid var(--border); font-family: 'Manrope', sans-serif; }
.assignment-content { display: flex; flex-direction: column; gap: 22px; }
.hidden { display: none !important; }

.micro-label { display: block; font-size: 0.59rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-family: 'DM Sans', sans-serif; margin-bottom: 10px; }
.t-pool { display: flex; flex-wrap: wrap; gap: 12px; }
.t-av-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; cursor: pointer; transition: transform 0.15s, opacity 0.15s; }
.t-av-wrap:hover { transform: translateY(-3px); }
.t-av-wrap.t-used { opacity: 0.2; pointer-events: none; transform: none; }
.t-av { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 8px rgba(0,0,0,0.15); overflow: hidden; border: 1px solid var(--border); background: var(--bg-surface); }
.t-av img { width: 100%; height: 100%; object-fit: cover; }
.t-nm { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); max-width: 48px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; font-family: 'Manrope', sans-serif; }

.a-slots { display: flex; flex-wrap: wrap; gap: 8px; min-height: 48px; padding: 8px 6px; border: 1.5px dashed var(--border); border-radius: 10px; background: var(--bg-surface); align-items: center; }
.a-ph { font-size: 0.72rem; color: var(--text-muted); width: 100%; text-align: center; pointer-events: none; font-family: 'Manrope', sans-serif; }
.a-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 4px; border: 1.5px solid var(--border); border-radius: 30px; background: var(--bg-surface); cursor: pointer; transition: all 0.14s; box-shadow: 0 1px 3px rgba(0,0,0,0.04); user-select: none; font-family: 'Manrope', sans-serif; }
.a-chip:hover { border-color: var(--primary); background: var(--bg-body); }
.a-chip.a-main { border-color: var(--primary); background: var(--bg-body); box-shadow: 0 0 0 1px var(--primary); }
.a-chip-av { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border); background: var(--bg-surface); flex-shrink: 0; }
.a-chip-av img { width: 100%; height: 100%; object-fit: cover; }
.a-chip-name { font-size: 0.72rem; font-weight: 600; color: var(--text-main); }
.a-chip-role { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; padding: 2px 6px; border-radius: 12px; background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border); font-family: 'DM Sans', sans-serif; }
.a-chip.a-main .a-chip-role { background: var(--primary); color: white; border-color: var(--primary); }
.a-chip-close { width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: background 0.14s, color 0.14s; margin-left: 2px; }
.a-chip-close:hover { background: var(--error-bg); color: var(--error); }
.a-chip-close .material-symbols-outlined { font-size: 14px; }

.reg-wrap { position: relative; }
.reg-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; display: flex; align-items: center; }
.reg-icon .material-symbols-outlined { font-size: 14px; color: var(--text-muted); }
.reg-input { width: 100%; padding: 10px 10px 10px 32px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.78rem; color: var(--text-main); background: var(--input-bg); outline: none; transition: border-color 0.14s, box-shadow 0.14s; font-family: 'Manrope', sans-serif; }
.reg-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(2,136,209,0.1); }

.rp-foot { flex-shrink: 0; border-top: 1px solid var(--border); padding: 10px 16px; background: var(--bg-body); font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; font-family: 'Manrope', sans-serif; }
.rp-foot-count { font-weight: 800; color: var(--primary); min-width: 14px; display: inline-block; text-align: center; }

[data-theme="dark"] input[type="date"].f-input::-webkit-calendar-picker-indicator,
[data-theme="midnight"] input[type="date"].f-input::-webkit-calendar-picker-indicator,
[data-theme="catppuccin"] input[type="date"].f-input::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }

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
        <a href="index.php">Dashboard</a>
        <span class="tb-crumb-sep">›</span>
        <span class="tb-crumb-cur">Edit Task #<?= $task_id ?></span>
    </nav>
</header>

<form action="controllers/TaskController.php" method="POST" id="mainForm" class="page-shell">
    <input type="hidden" name="update_task" value="1">
    <input type="hidden" name="task_id" value="<?= $task_id ?>">
    
    <!-- Pass the current URL state back to the controller -->
    <?php 
    $referer = $_SERVER['HTTP_REFERER'] ?? 'tasks.php';
    if (strpos($referer, 'edit_task.php') !== false) {
        $referer = 'tasks.php';
    }
    ?>
    <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($referer) ?>">

    <main class="left-panel">
        <div class="lp-heading">
            <h1 class="lp-title">
                Edit Task
                <span class="status-badge-header <?= str_replace(' ', '-', $badge_status) ?>">
                    <span class="material-symbols-outlined" style="font-size: 16px;">
                        <?= match($badge_status) {
                            'Pass' => 'check_circle',
                            'Fail' => 'cancel',
                            'Blocked' => 'block',
                            'N/A' => 'do_not_disturb_on',
                            default => 'schedule'
                        } ?>
                    </span>
                    <?= $badge_status ?>
                </span>
            </h1>
            <p class="lp-sub">Modify task details and assignments below. Test results will be preserved.</p>
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
                                <label for="wf_smoke" style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">local_fire_department</span> Smoke Test
                                </label>

                                <input type="radio" name="testing_type" id="wf_reg" value="Regression"
                                    <?= ($form_data['testing_type'] ?? '') == 'Regression' ? 'checked' : '' ?>>
                                <label for="wf_reg" style="display: flex; align-items: center; justify-content: center; gap: 6px;">
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
            <button type="submit" class="btn-create" onclick="return confirm('Update task details? Assignments and test results will be preserved.');">
                <span class="material-symbols-outlined">save</span>
                Update Task
            </button>
            
            <a href="tasks.php" class="btn-cancel">Cancel</a>
        </div>
    </main>

    <aside class="right-panel">
        <div class="rp-head">
            <span class="rp-head-title">PRINTER &amp; ASSIGNMENTS</span>
            <span class="rp-head-sub">Click a printer to assign testers</span>
        </div>

        <div class="printer-grid-container">
            <div class="printer-grid" id="printerGrid">
                <?php foreach ($data['printers'] as $pi => $p): 
                    // --- FIX: Skip inactive printers ---
                    if (isset($p['status']) && $p['status'] === 'inactive') continue;
                    
                    $isSelected = false;
                    if ($task['testing_type'] === 'Smoke') {
                        $isSelected = isset($saved_assignments[$p['id']]) && !empty($saved_assignments[$p['id']]);
                    } else {
                        $isSelected = isset($regression_urls[$p['id']]) && !empty($regression_urls[$p['id']]);
                    }
                ?>
                <div class="p-card <?= $isSelected ? 'p-selected' : '' ?>" 
                     data-pid="<?= $p['id'] ?>" id="pc_<?= $p['id'] ?>">
                    <div class="p-card-icon" style="overflow: hidden; padding: 2px;">
                        <?= Helper::renderPrinterImage($p['printer_path'] ?? null, $p['model_name'], 24) ?>
                    </div>
                    <div class="p-card-name"><?= htmlspecialchars($p['model_name']) ?></div>
                    <div class="selected-badge"><span class="material-symbols-outlined">check</span></div>
                    
                    <input type="checkbox" name="printers[]" value="<?= $p['id'] ?>" class="printer-checkbox" 
                           id="cb_<?= $p['id'] ?>" <?= $isSelected ? 'checked' : '' ?> 
                           style="display:none;">
                    
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

    // ============================================================
    // DATA INITIALIZATION
    // ============================================================
    const assignments = <?= $saved_assignments_json ?>;
    const regressionUrls = <?= $saved_reg_urls_json ?>;
    
    // CRITICAL: Store a DEEP COPY of the original regression data
    // This will NEVER be modified
    const ORIGINAL_REGRESSION_URLS = JSON.parse(JSON.stringify(regressionUrls));
    
    let activePrinter = null;
    let workflow = '<?= $task['testing_type'] ?? 'Smoke' ?>';

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

    // Initialize data structures for all printers
    <?php foreach ($data['printers'] as $p): ?>
        if (!assignments[<?= $p['id'] ?>]) assignments[<?= $p['id'] ?>] = [];
        if (!regressionUrls[<?= $p['id'] ?>]) regressionUrls[<?= $p['id'] ?>] = '';
    <?php endforeach; ?>

    // ============================================================
    // HELPER FUNCTIONS
    // ============================================================
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
        
        if (checkbox) checkbox.checked = isSelected;
        if (card) card.classList.toggle('p-selected', isSelected);
        
        const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
        footCount.textContent = selectedCount;
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

    function showSmokeAssignment(pid) {
        activePrinter = pid;
        assignmentPlaceholder.classList.add('hidden');
        assignmentContent.classList.remove('hidden');
        smokeUI.classList.remove('hidden');
        regUI.classList.add('hidden');
        
        // Render the assignments (which are already loaded from PHP)
        renderActiveSlots();
        updatePoolUsed(pid);
        
        printerCards.forEach(card => {
            card.classList.toggle('p-active', parseInt(card.dataset.pid) === pid);
        });
    }

    function hideSmokeAssignment() {
        activePrinter = null;
        assignmentPlaceholder.classList.remove('hidden');
        assignmentContent.classList.add('hidden');
        printerCards.forEach(card => card.classList.remove('p-active'));
    }

    // ============================================================
    // RESTORE REGRESSION DATA - Uses ORIGINAL data
    // ============================================================
    function restoreRegressionData() {
        // Restore URLs from the original data (never modified)
        Object.keys(ORIGINAL_REGRESSION_URLS).forEach(pid => {
            regressionUrls[pid] = ORIGINAL_REGRESSION_URLS[pid] || '';
            const hidden = document.getElementById('reg_hidden_' + pid);
            if (hidden) {
                hidden.value = regressionUrls[pid] || '';
            }
        });
        
        // Update printer cards to show selections based on URLs
        printerCards.forEach(card => {
            const pid = parseInt(card.dataset.pid);
            const isSelected = (regressionUrls[pid] && regressionUrls[pid].trim() !== '');
            const checkbox = document.getElementById('cb_' + pid);
            if (checkbox) {
                checkbox.checked = isSelected;
            }
            card.classList.toggle('p-selected', isSelected);
            card.classList.remove('p-active');
        });
        
        // Update footer count
        const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
        footCount.textContent = selectedCount;
    }

    // ============================================================
    // WORKFLOW SWITCHING
    // ============================================================
    document.querySelectorAll('input[name="testing_type"]').forEach(r => {
        r.addEventListener('change', function(e) {
            const newWorkflow = this.value;
            if (workflow === newWorkflow) return;

            if (newWorkflow === 'Smoke') {
                // --- SWITCHING TO SMOKE ---
                // Clear all printer selections
                printerCards.forEach(card => {
                    const pid = parseInt(card.dataset.pid);
                    const checkbox = document.getElementById('cb_' + pid);
                    if (checkbox) {
                        checkbox.checked = false;
                    }
                    card.classList.remove('p-selected');
                    card.classList.remove('p-active');
                });
                
                // Clear all assignments
                Object.keys(assignments).forEach(pid => {
                    assignments[pid] = [];
                });
                
                // Reset UI
                hideSmokeAssignment();
                footCount.textContent = '0';
                workflow = 'Smoke';
                
            } else if (newWorkflow === 'Regression') {
                // --- SWITCHING TO REGRESSION ---
                // Restore original regression data
                restoreRegressionData();
                
                // Update workflow
                workflow = 'Regression';
                
                // Find the first printer with a URL and show it
                let firstPid = null;
                Object.keys(regressionUrls).forEach(id => {
                    if (regressionUrls[id] && regressionUrls[id].trim() !== '' && firstPid === null) {
                        firstPid = parseInt(id);
                    }
                });
                
                if (firstPid !== null) {
                    activePrinter = firstPid;
                    assignmentPlaceholder.classList.add('hidden');
                    assignmentContent.classList.remove('hidden');
                    smokeUI.classList.add('hidden');
                    regUI.classList.remove('hidden');
                    
                    printerCards.forEach(card => {
                        card.classList.toggle('p-active', parseInt(card.dataset.pid) === activePrinter);
                    });
                    
                    loadRegUrlForActive();
                } else {
                    activePrinter = null;
                    assignmentPlaceholder.classList.remove('hidden');
                    assignmentContent.classList.add('hidden');
                }
            }
        });
    });

    // ============================================================
    // PRINTER CLICK HANDLER - COMBINED RULES
    // ============================================================
    printerCards.forEach(card => {
        card.addEventListener('click', (e) => {
            const pid = Number(card.dataset.pid);
            const checkbox = document.getElementById('cb_' + pid);
            
            if (workflow === 'Smoke') {
                if (checkbox) {
                    const isCurrentlyChecked = checkbox.checked;

                    // RULE 1: If it is already checked, just switch the view.
                    if (isCurrentlyChecked) {
                        showSmokeAssignment(pid);
                    } 
                    // RULE 2: If it is NOT checked, check it and load the view.
                    else {
                        checkbox.checked = true;
                        card.classList.add('p-selected');
                        if (!assignments[pid]) assignments[pid] = [];
                        showSmokeAssignment(pid);
                        
                        // Update the footer count
                        const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
                        footCount.textContent = selectedCount;
                    }
                }
            } else {
                // Regression: show URL for selected printer
                setActivePrinter(pid);
            }
        });
    });

    // ============================================================
    // AUTO-UNCHECK EMPTY PRINTERS (SPARK LOGIC)
    // ============================================================
    // This runs whenever the user clicks a DIFFERENT printer
    function cleanupUnusedPrinters(newPid) {
        printerCards.forEach(card => {
            const pid = Number(card.dataset.pid);
            if (pid === newPid) return; // Don't check the one we just clicked

            const checkbox = document.getElementById('cb_' + pid);
            // If it's checked, but it has NO testers, uncheck it
            if (checkbox && checkbox.checked) {
                if (!assignments[pid] || assignments[pid].length === 0) {
                    checkbox.checked = false;
                    card.classList.remove('p-selected');
                }
            }
        });
        
        // Update footer count after cleanup
        const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
        footCount.textContent = selectedCount;
    }

    // Override showSmokeAssignment to include cleanup
    const originalShow = showSmokeAssignment;
    showSmokeAssignment = function(pid) {
        // Clean up empty printers before switching
        cleanupUnusedPrinters(pid);
        // Call the original function
        originalShow(pid);
    };

    // Override setActivePrinter for regression (just in case)
    const originalSet = setActivePrinter;
    setActivePrinter = function(pid) {
        cleanupUnusedPrinters(pid);
        originalSet(pid);
    };

    // ============================================================
    // TESTER POOL CLICK HANDLER
    // ============================================================
    globalPool.addEventListener('click', (e) => {
        const poolChip = e.target.closest('.t-av-wrap');
        if (!poolChip) return;
        if (poolChip.classList.contains('t-used')) return;
        if (!activePrinter) {
            alert('Please select a printer first.');
            return;
        }
        
        if (workflow !== 'Smoke') {
            alert('Testers can only be assigned in Smoke Test workflow.');
            return;
        }
        
        const uid = poolChip.dataset.uid;
        const name = poolChip.dataset.name;
        const pfp = poolChip.dataset.pfp;

        if (assignments[activePrinter].some(t => String(t.uid) === String(uid))) return;

        assignments[activePrinter].push({ uid, name, pfp });
        renderActiveSlots();
        renderHiddenInputs(activePrinter);
        updatePrinterCard(activePrinter);
        updatePoolUsed(activePrinter);
    });

    // ============================================================
    // REGRESSION URL INPUT HANDLER
    // ============================================================
    regInputActive.addEventListener('input', () => {
        if (activePrinter && workflow === 'Regression') {
            saveRegUrlFromActive();
        }
    });

    // ============================================================
    // FORM SUBMIT HANDLER
    // ============================================================
    document.getElementById('mainForm').addEventListener('submit', function(e) {
        if (workflow === 'Regression' && activePrinter) {
            saveRegUrlFromActive();
        }
        
        printerCards.forEach(card => {
            const pid = Number(card.dataset.pid);
            const container = document.getElementById('tester_list_' + pid);
            if (container) {
                container.innerHTML = '';
                if (workflow === 'Smoke') {
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
            }
        });
    });

    // ============================================================
    // SET ACTIVE PRINTER (for Regression)
    // ============================================================
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

    // ============================================================
    // PRE-SELECT ACTIVE PRINTERS ON LOAD
    // ============================================================
    function preSelectActivePrinters() {
        let firstPid = null;

        if (workflow === 'Smoke') {
            // For Smoke, find the first printer that has assignments
            const assignedPrinterIds = Object.keys(assignments).filter(pid => 
                assignments[pid] && assignments[pid].length > 0
            );
            
            if (assignedPrinterIds.length > 0) {
                firstPid = parseInt(assignedPrinterIds[0]);
            }
        } else {
            const assignedIds = [];
            Object.keys(regressionUrls).forEach(id => {
                const url = regressionUrls[id];
                if (url && url.trim() !== '') {
                    assignedIds.push(parseInt(id));
                }
            });
            
            if (assignedIds.length > 0) {
                firstPid = assignedIds[0];
            }
        }

        if (firstPid !== null) {
            if (workflow === 'Smoke') {
                showSmokeAssignment(firstPid);
            } else {
                activePrinter = firstPid;
                assignmentPlaceholder.classList.add('hidden');
                assignmentContent.classList.remove('hidden');
                smokeUI.classList.add('hidden');
                regUI.classList.remove('hidden');
                
                printerCards.forEach(card => {
                    card.classList.toggle('p-active', parseInt(card.dataset.pid) === activePrinter);
                });
                
                loadRegUrlForActive();
            }
            
            printerCards.forEach(card => {
                const pid = parseInt(card.dataset.pid);
                let isSelected = false;
                
                if (workflow === 'Smoke') {
                    isSelected = (assignments[pid] && assignments[pid].length > 0);
                } else {
                    isSelected = (regressionUrls[pid] && regressionUrls[pid].trim() !== '');
                }
                
                card.classList.toggle('p-selected', isSelected);
                
                const checkbox = document.getElementById('cb_' + pid);
                if (checkbox) {
                    checkbox.checked = isSelected;
                }
            });

            const selectedCount = document.querySelectorAll('.printer-checkbox:checked').length;
            footCount.textContent = selectedCount;
        }
    }

    // Initialize
    preSelectActivePrinters();

})();
</script>

</body>
</html>