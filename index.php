<?php require_once 'controllers/DashboardController.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="app.css">

    <style>
        :root {
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --nav-height: 60px;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
        }

        /* ── NAVBAR ─────────────────────────────── */
        .navbar {
            position: sticky; top: 0; z-index: 100;
            height: var(--nav-height);
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .nav-brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 1.05rem; font-weight: 700; color: var(--text-main); letter-spacing: -0.3px;
        }
        .nav-brand-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--primary); display: inline-block;
        }
        .nav-right {
            display: flex; align-items: center; gap: 20px;
        }
        .nav-user {
            display: flex; align-items: center; gap: 8px;
        }
        .nav-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #0288d1, #01579b);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;
        }
        .nav-user-info { line-height: 1.25; }
        .nav-user-name { font-size: 0.82rem; font-weight: 600; color: var(--text-main); }
        .nav-user-role { font-size: 0.7rem; color: var(--text-muted); text-transform: capitalize; }
        .nav-logout {
            font-size: 0.8rem; font-weight: 600; color: var(--error);
            text-decoration: none; padding: 5px 10px; border-radius: 6px;
            border: 1px solid transparent; transition: all 0.15s;
        }
        .nav-logout:hover { background: var(--error-bg); border-color: #fecaca; }

        /* ── LAYOUT ─────────────────────────────── */
        .dash-wrapper {
            max-width: 1480px;
            margin: 0 auto;
            padding: 28px 24px 48px;
            display: flex; flex-direction: column; gap: 20px;
        }
        .dash-top-row {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
        }
        @media (max-width: 1100px) {
            .dash-top-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .dash-wrapper { padding: 16px 14px 32px; }
        }

        /* ── CARD ────────────────────────────────── */
        .d-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .d-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: #fafbfc;
        }
        .d-card-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.88rem; font-weight: 700; color: var(--text-main);
            letter-spacing: -0.1px;
        }
        .d-card-title .material-symbols-outlined {
            font-size: 18px; color: var(--primary);
        }
        .d-card-body { padding: 0; }
        .d-card-body.padded { padding: 20px; }

        /* ── BTN MINI ────────────────────────────── */
        .btn-mini {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: 6px; font-size: 0.78rem;
            font-weight: 600; cursor: pointer; transition: all 0.12s;
            border: 1px solid var(--primary); background: var(--primary); color: white;
            text-decoration: none; white-space: nowrap;
        }
        .btn-mini:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-mini.ghost {
            background: white; color: var(--primary); border-color: var(--border);
        }
        .btn-mini.ghost:hover { border-color: var(--primary); background: #f0f9ff; }
        .btn-mini.danger-ghost { background: white; color: var(--error); border-color: var(--border); }
        .btn-mini.danger-ghost:hover { border-color: var(--error); background: var(--error-bg); }
        .btn-mini.disabled {
            background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0;
            cursor: not-allowed; pointer-events: none;
        }
        .btn-mini .material-symbols-outlined { font-size: 15px; }

        /* ── DATA TABLE ──────────────────────────── */
        .d-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;
        }
        .d-table th {
            text-align: left;
            padding: 10px 16px;
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            background: #f9fafb;
            white-space: nowrap;
        }
        .d-table td {
            padding: 12px 16px;
            color: var(--text-main);
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            white-space: nowrap;
        }
        .d-table tr:last-child td { border-bottom: none; }
        .d-table tbody tr.main-row:hover > td { background: #f8fafc; }

        /* ── BADGES ──────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 5px;
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .badge .material-symbols-outlined { font-size: 12px; }
        .badge-pass { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-fail { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-pending { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
        .badge-smoke { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-reg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-main { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-support { background: #f9fafb; color: #6b7280; border: 1px solid #e5e7eb; }

        /* ── PROGRESS BAR ────────────────────────── */
        .prog-wrap { display: flex; flex-direction: column; gap: 4px; min-width: 120px; }
        .prog-meta { display: flex; justify-content: space-between; font-size: 0.68rem; color: var(--text-muted); }
        .prog-track { height: 5px; background: #e5e7eb; border-radius: 99px; overflow: hidden; }
        .prog-fill { height: 100%; border-radius: 99px; background: var(--primary); transition: width 0.4s ease; }
        .prog-fill.complete { background: #15803d; }

        /* ── EXPANDABLE ROW ──────────────────────── */
        .expand-trigger { cursor: pointer; }
        .expand-trigger:hover > td { background: #f0f9ff !important; }

        .chevron-icon {
            font-size: 18px; color: var(--text-muted);
            transition: transform 0.22s ease;
            display: block;
        }
        .chevron-icon.open { transform: rotate(180deg); color: var(--primary); }

        .expanded-row td { padding: 0; border-bottom: 2px solid var(--border); }
        .expanded-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0;
            background: #f8fafc;
            border-top: 1px solid #e8edf2;
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.28s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s;
            opacity: 0;
        }
        .expanded-content.open {
            max-height: 200px;
            opacity: 1;
        }
        .expand-detail {
            padding: 14px 20px;
            border-right: 1px solid #e8edf2;
            display: flex; flex-direction: column; gap: 4px;
        }
        .expand-detail:last-child { border-right: none; }
        .expand-detail-label {
            font-size: 0.63rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; color: var(--text-muted);
        }
        .expand-detail-value {
            font-size: 0.85rem; font-weight: 600; color: var(--text-main);
            font-family: var(--font-mono);
        }
        .expand-actions {
            padding: 14px 20px;
            display: flex; align-items: center; justify-content: flex-end; gap: 8px;
            background: #f8fafc;
        }

        /* ── ICON BUTTON ─────────────────────────── */
        .icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 6px;
            border: 1px solid var(--border); background: white;
            color: var(--text-muted); cursor: pointer; transition: all 0.12s;
            text-decoration: none;
        }
        .icon-btn .material-symbols-outlined { font-size: 16px; }
        .icon-btn:hover { border-color: var(--primary); background: #f0f9ff; color: var(--primary); }
        .icon-btn.delete:hover { border-color: var(--error); background: var(--error-bg); color: var(--error); }

        /* ── TEAM STATUS ─────────────────────────── */
        .member-row {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 18px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.12s;
        }
        .member-row:last-child { border-bottom: none; }
        .member-row:hover { background: #f9fafb; }
        .member-avatar {
            width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 700; color: white; letter-spacing: 0.5px;
        }
        .member-info { flex: 1; min-width: 0; }
        .member-name { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }
        .member-last { font-size: 0.72rem; color: var(--text-muted); margin-top: 1px; }
        .member-role {
            font-size: 0.68rem; font-weight: 700; text-transform: capitalize;
            padding: 3px 8px; border-radius: 4px;
        }
        .member-role.lead { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .member-role.tester { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

        /* ── FIRMWARE TABLE ──────────────────────── */
        .fw-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px; padding: 16px;
        }
        .fw-card {
            background: #f9fafb; border: 1px solid var(--border);
            border-radius: 8px; padding: 14px 16px;
        }
        .fw-model { font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 10px; }
        .fw-row { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; }
        .fw-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
        .fw-value { font-family: var(--font-mono); font-size: 0.78rem; font-weight: 600; color: var(--text-main); }
        .fw-value.trunk { color: var(--primary); }

        /* ── CHART SECTION ───────────────────────── */
        .chart-container { padding: 20px; height: 320px; }

        /* ── EMPTY STATE ─────────────────────────── */
        .empty-state {
            text-align: center; padding: 3.5rem 2rem;
            color: var(--text-muted);
        }
        .empty-state .material-symbols-outlined {
            font-size: 40px; color: #d1d5db; display: block; margin-bottom: 12px;
        }
        .empty-state p { font-size: 0.88rem; margin: 0; }

        /* ── TOOLTIP ─────────────────────────────── */
        #custom-tooltip {
            position: fixed;
            background: #1f2937; color: #fff;
            padding: 8px 12px; border-radius: 6px;
            font-size: 0.78rem; font-family: var(--font-body);
            pointer-events: none; opacity: 0;
            transform: translateY(6px);
            transition: opacity 0.12s, transform 0.12s;
            z-index: 2000;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            white-space: nowrap;
        }
        #custom-tooltip.visible { opacity: 1; transform: translateY(0); }

        /* ── MONO TEXT ───────────────────────────── */
        .mono { font-family: var(--font-mono); }

        /* ── SECTION DIVIDER ─────────────────────── */
        .divider-line {
            width: 1px; height: 18px; background: var(--border); display: inline-block;
        }

        /* Avatar colors by initials hash */
        .av-blue   { background: linear-gradient(135deg,#0288d1,#01579b); }
        .av-green  { background: linear-gradient(135deg,#16a34a,#166534); }
        .av-violet { background: linear-gradient(135deg,#7c3aed,#4c1d95); }
        .av-rose   { background: linear-gradient(135deg,#e11d48,#9f1239); }
        .av-amber  { background: linear-gradient(135deg,#d97706,#92400e); }
        .av-teal   { background: linear-gradient(135deg,#0d9488,#115e59); }
    </style>
</head>
<body>

<?php Helper::displayFlash(); ?>
<div id="custom-tooltip"></div>

<nav class="navbar">
    <div class="nav-brand">
        <span class="nav-brand-dot"></span>
        Track Manager
    </div>
    <div class="nav-right">
        <div class="nav-user">
            <?php
                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $_SESSION['full_name']), 0, 2)));
                $avColors = ['av-blue','av-green','av-violet','av-rose','av-amber','av-teal'];
                $avClass = $avColors[crc32($initials) % count($avColors)];
            ?>
            <div class="nav-avatar <?= $avClass ?>"><?= $initials ?></div>
            <div class="nav-user-info">
                <div class="nav-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="nav-user-role"><?= htmlspecialchars($_SESSION['role']) ?></div>
            </div>
        </div>
        <a href="logout.php" class="nav-logout">Logout</a>
    </div>
</nav>

<div class="dash-wrapper">

    <div class="dash-top-row">

        <!-- ══ MAIN TASKS CARD ══ -->
        <div class="d-card">
            <div class="d-card-header">
                <div class="d-card-title">
                    <span class="material-symbols-outlined">task_alt</span>
                    <?= $_SESSION['role'] === 'lead' ? 'Active Testing Tasks' : 'My Assignments' ?>
                </div>
                <?php if ($_SESSION['role'] === 'lead'): ?>
                    <a href="create_task.php" class="btn-mini">
                        <span class="material-symbols-outlined">add</span> Create Task
                    </a>
                <?php endif; ?>
            </div>

            <div class="d-card-body">
            <?php if ($_SESSION['role'] === 'lead'): ?>
                <?php if (empty($lead_tasks)): ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">inbox</span>
                        <p>No active tasks found. Create one to get started.</p>
                    </div>
                <?php else: ?>
                    <table class="d-table">
                        <colgroup>
                            <col style="width:90px"><col style="width:110px"><col><col style="width:175px"><col style="width:100px"><col style="width:50px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Printer</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lead_tasks as $task): ?>
                            <?php
                                $is_complete = ($task['completed_cases'] >= $task['total_cases']) && ($task['total_cases'] > 0);
                                $percent = $task['total_cases'] > 0 ? round(($task['completed_cases'] / $task['total_cases']) * 100) : 0;
                                $rowId = "task_" . $task['task_id'] . "_" . $task['printer_id'];
                                $printerName = htmlspecialchars($task['model_name']);
                            ?>
                            <tr class="expand-trigger main-row" onclick="toggleRow('<?= $rowId ?>')">
                                <td>
                                    <span class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= date('M d', strtotime($task['task_date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $task['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>">
                                        <?= htmlspecialchars($task['testing_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="font-size:0.88rem;"><?= $printerName ?></strong>
                                </td>
                                <td>
                                    <div class="prog-wrap">
                                        <div class="prog-meta">
                                            <span><?= $task['completed_cases'] ?>/<?= $task['total_cases'] ?></span>
                                            <span><?= $percent ?>%</span>
                                        </div>
                                        <div class="prog-track">
                                            <div class="prog-fill <?= $is_complete ? 'complete' : '' ?>" style="width:<?= $percent ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($task['overall_status'] == 'Pass'): ?>
                                        <span class="badge badge-pass">
                                            <span class="material-symbols-outlined">check_circle</span> PASSED
                                        </span>
                                    <?php elseif ($task['overall_status'] == 'Fail'): ?>
                                        <span class="badge badge-fail">
                                            <span class="material-symbols-outlined">cancel</span> FAILED
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">
                                            <span class="material-symbols-outlined">schedule</span> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="material-symbols-outlined chevron-icon" id="chev-<?= $rowId ?>">expand_more</span>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="6" style="padding:0; border-bottom:1px solid #e8edf2;">
                                    <div class="expanded-content" id="<?= $rowId ?>">
                                        <div class="expand-detail">
                                            <span class="expand-detail-label">Due Date</span>
                                            <span class="expand-detail-value" style="font-family:var(--font-body);"><?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                                        </div>
                                        <div class="expand-detail">
                                            <span class="expand-detail-label">Target FW</span>
                                            <span class="expand-detail-value" style="color:var(--primary);"><?= htmlspecialchars($task['fw_version_current']) ?></span>
                                        </div>
                                        <div class="expand-detail">
                                            <span class="expand-detail-label">Branch</span>
                                            <span class="expand-detail-value"><?= htmlspecialchars($task['fw_type']) ?></span>
                                        </div>
                                        <div class="expand-detail">
                                            <span class="expand-detail-label">Prev FW</span>
                                            <span class="expand-detail-value" style="color:var(--text-muted); text-decoration:line-through; opacity:0.7;"><?= htmlspecialchars($task['fw_version_prev']) ?></span>
                                        </div>
                                        <div class="expand-detail">
                                            <span class="expand-detail-label">Rec FW</span>
                                            <span class="expand-detail-value" style="color:var(--error);"><?= htmlspecialchars($task['fw_version_rec']) ?></span>
                                        </div>
                                        <div class="expand-actions">
                                            <?php if ($task['testing_type'] == 'Smoke'): ?>
                                                <?php if ($is_complete): ?>
                                                    <a href="report.php?task_id=<?= $task['task_id'] ?>&printer_id=<?= $task['printer_id'] ?>" class="btn-mini ghost">
                                                        <span class="material-symbols-outlined">description</span> Report
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn-mini disabled">
                                                        <span class="material-symbols-outlined">hourglass_top</span> In Progress
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <span class="divider-line"></span>
                                            <a href="edit_task.php?id=<?= $task['task_id'] ?>" class="icon-btn" title="Edit Task">
                                                <span class="material-symbols-outlined">edit</span>
                                            </a>
                                            <a href="delete_task.php?id=<?= $task['task_id'] ?>" class="icon-btn delete" title="Delete Task" onclick="return confirm('Delete this task?');">
                                                <span class="material-symbols-outlined">delete</span>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php else: ?>
                <?php if (empty($my_tasks)): ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">assignment</span>
                        <p>No tasks assigned to you yet.</p>
                    </div>
                <?php else: ?>
                    <table class="d-table">
                        <colgroup>
                            <col style="width:80px"><col style="width:105px"><col><col style="width:130px"><col style="width:100px"><col style="width:90px"><col style="width:90px"><col style="width:140px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Printer</th>
                                <th>Target FW</th>
                                <th>Branch</th>
                                <th>My Role</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_tasks as $task): ?>
                            <?php $printerName = htmlspecialchars($task['model_name']); ?>
                            <tr class="main-row">
                                <td>
                                    <span class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= date('M d', strtotime($task['task_date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $task['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>">
                                        <?= htmlspecialchars($task['testing_type']) ?>
                                    </span>
                                </td>
                                <td><strong style="font-size:0.88rem;"><?= $printerName ?></strong></td>
                                <td>
                                    <span class="mono" style="font-size:0.82rem; color:var(--primary); font-weight:600;">
                                        <?= htmlspecialchars($task['fw_version_current']) ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem; color:var(--text-muted);">
                                    <?= htmlspecialchars($task['fw_type']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $task['designation'] == 'Main' ? 'badge-main' : 'badge-support' ?>">
                                        <?= htmlspecialchars($task['designation']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($task['overall_status'] == 'Pass'): ?>
                                        <span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                    <?php elseif ($task['overall_status'] == 'Fail'): ?>
                                        <span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending"><span class="material-symbols-outlined">schedule</span> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($task['testing_type'] == 'Regression'): ?>
                                        <a href="<?= htmlspecialchars($task['regression_url']) ?>" target="_blank" class="btn-mini ghost">
                                            <span class="material-symbols-outlined">open_in_new</span> Open TestRail
                                        </a>
                                    <?php else: ?>
                                        <a href="execute_task.php?task_id=<?= $task['id'] ?>&printer_id=<?= $task['printer_id'] ?>" class="btn-mini">
                                            <span class="material-symbols-outlined">play_arrow</span> Execute
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>

        <!-- ══ TEAM STATUS CARD ══ -->
        <div class="d-card" style="align-self: start;">
            <div class="d-card-header">
                <div class="d-card-title">
                    <span class="material-symbols-outlined">group</span>
                    Team Status
                </div>
            </div>
            <div class="d-card-body">
                <?php
                    $memberColors = ['av-blue','av-green','av-violet','av-rose','av-amber','av-teal'];
                    foreach ($team_members as $idx => $member):
                        $mInitials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $member['full_name']), 0, 2)));
                        $mColor = $memberColors[$idx % count($memberColors)];
                        $lastSeen = $member['last_login'] ? time_ago($member['last_login']) : 'Never';
                        $lastFull = $member['last_login'] ? date('M d, Y g:i A', strtotime($member['last_login'])) : 'No login recorded';
                ?>
                <div class="member-row">
                    <div class="member-avatar <?= $mColor ?>"><?= $mInitials ?></div>
                    <div class="member-info">
                        <div class="member-name"><?= htmlspecialchars($member['full_name']) ?></div>
                        <div class="member-last tooltip-trigger" data-tip="Last login: <?= $lastFull ?>"><?= $lastSeen ?></div>
                    </div>
                    <span class="member-role <?= $member['role'] === 'lead' ? 'lead' : 'tester' ?>">
                        <?= ucfirst($member['role']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ══ FIRMWARE OVERVIEW ══ -->
    <div class="d-card">
        <div class="d-card-header">
            <div class="d-card-title">
                <span class="material-symbols-outlined">memory</span>
                Firmware Overview
            </div>
        </div>
        <div class="fw-grid">
            <?php foreach ($firmware_overview as $fw): ?>
            <div class="fw-card">
                <div class="fw-model"><?= htmlspecialchars($fw['model']) ?></div>
                <div class="fw-row">
                    <span class="fw-label">Branch</span>
                    <span class="fw-value"><?= htmlspecialchars($fw['branch']) ?></span>
                </div>
                <div class="fw-row">
                    <span class="fw-label">Trunk</span>
                    <span class="fw-value trunk"><?= htmlspecialchars($fw['trunk']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══ CHART ══ -->
    <div class="d-card">
        <div class="d-card-header">
            <div class="d-card-title">
                <span class="material-symbols-outlined">bar_chart</span>
                30-Day Performance
            </div>
        </div>
        <div class="chart-container">
            <canvas id="progressChart"></canvas>
        </div>
    </div>

</div><!-- /.dash-wrapper -->

<?php
function time_ago($datetime) {
    $interval = time() - strtotime($datetime);
    if ($interval < 60) return 'Just now';
    if ($interval < 3600) return floor($interval/60) . 'm ago';
    if ($interval < 86400) return floor($interval/3600) . 'h ago';
    return floor($interval/86400) . 'd ago';
}
?>

<script src="app.js"></script>
<script>
    // ── Row Toggle ───────────────────────────────────────
    function toggleRow(rowId) {
        const content = document.getElementById(rowId);
        const chevron = document.getElementById('chev-' + rowId);
        const isOpen = content.classList.contains('open');

        // Close all
        document.querySelectorAll('.expanded-content.open').forEach(el => el.classList.remove('open'));
        document.querySelectorAll('.chevron-icon.open').forEach(el => el.classList.remove('open'));

        if (!isOpen) {
            content.classList.add('open');
            chevron.classList.add('open');
        }
    }

    // ── Tooltip ──────────────────────────────────────────
    const tooltip = document.getElementById('custom-tooltip');
    document.querySelectorAll('[data-tip]').forEach(el => {
        el.addEventListener('mouseenter', (e) => {
            tooltip.textContent = el.dataset.tip;
            tooltip.classList.add('visible');
        });
        el.addEventListener('mousemove', (e) => {
            tooltip.style.left = (e.clientX + 14) + 'px';
            tooltip.style.top  = (e.clientY - 32) + 'px';
        });
        el.addEventListener('mouseleave', () => tooltip.classList.remove('visible'));
    });

    // ── Chart ─────────────────────────────────────────────
    const rawData = <?= json_encode($chart_data) ?>;
    const ctx = document.getElementById('progressChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: rawData.map(d => d.model_name),
            datasets: [
                { label: 'Passed',  data: rawData.map(d => d.passed),  backgroundColor: '#15803d' },
                { label: 'Failed',  data: rawData.map(d => d.failed),  backgroundColor: '#b91c1c' },
                { label: 'Pending', data: rawData.map(d => d.pending), backgroundColor: '#d1d5db' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { font: { family: 'DM Sans' } } },
                y: { stacked: true, beginAtZero: true, ticks: { font: { family: 'DM Sans' } } }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, font: { family: 'DM Sans', size: 12 }, padding: 20 }
                },
                tooltip: { bodyFont: { family: 'DM Sans' }, titleFont: { family: 'DM Sans', weight: '700' } }
            }
        }
    });
</script>
</body>
</html>