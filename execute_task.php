<?php require_once 'controllers/TestRunController.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Execute Test | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    <script>
        const savedTheme = localStorage.getItem('track-manager-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <script src="app.js" defer></script> 

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', system-ui, sans-serif; background: var(--bg-body); color: var(--text-main); height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
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
        .lp-heading { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; }
        .lp-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main); line-height: 1.2; margin-bottom: 6px; }
        .lp-sub { font-size: 0.82rem; color: var(--text-muted); }
        .role-badge { font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        .role-main { background: var(--primary); color: white; border: 1px solid var(--primary); }
        .role-support { background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border); }
        
        .task-info-grid { display: grid; grid-template-columns: 2fr 1.5fr 1fr; gap: 16px; margin-bottom: 30px; }
        .info-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .info-card.highlight { background: var(--bg-body); border-color: var(--border); }
        .info-label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 8px; letter-spacing: 0.05em; }
        .fw-transition { display: flex; align-items: center; gap: 16px; }
        .fw-ver { display: flex; flex-direction: column; }
        .fw-ver span { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 2px;}
        .fw-ver strong { font-family: 'JetBrains Mono', monospace; font-size: 1.1rem; color: var(--text-main); }
        .fw-ver.new strong { color: var(--primary); font-weight: 700; }
        .fw-ver.old strong { color: var(--text-muted); text-decoration: line-through; opacity: 0.8; font-size: 0.95rem; }
        .mini-row { display: flex; gap: 32px; }
        .mini-row strong { font-size: 1rem; color: var(--text-main); font-weight: 600; font-family: 'Inter', sans-serif;}
        
        .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0 0 12px; }
        .section-sub { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px; display: block; }
        .selection-box { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 30px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .chip-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .case-chip { padding: 8px 14px; border-radius: 20px; background: var(--bg-body); border: 1px solid var(--border); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 8px; max-width: 100%; color: var(--text-main); font-family: 'Inter', sans-serif; }
        .case-chip:hover { border-color: var(--primary); background: var(--bg-surface); color: var(--primary); transform: translateY(-1px); box-shadow: 0 2px 5px rgba(2, 136, 209, 0.1); }
        
        .case-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 12px; display: flex; flex-direction: column; overflow: hidden; position: relative; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .case-row { display: flex; align-items: center; padding: 18px 20px; gap: 16px; }
        .status-icon { display: flex; align-items: center; justify-content: center; }
        .case-info { flex-grow: 1; font-family: 'Inter', sans-serif; }
        .case-title { font-weight: 600; color: var(--text-main); font-size: 0.95rem; line-height: 1.3; }
        .case-code { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
        .status-actions { display: flex; gap: 8px; }
        .status-btn { padding: 8px 16px; border: 1.5px solid var(--border); background: transparent; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); transition: all 0.15s; font-family: 'DM Sans', sans-serif; }
        .status-btn:hover { background: var(--bg-body); color: var(--text-main); }
        
        .case-card.status-Pass { border-left: 5px solid var(--success); }
        .case-card.status-Pass .btn-pass { background: var(--success); color: white; border-color: var(--success); }
        .case-card.status-Fail { border-left: 5px solid var(--error); }
        .case-card.status-Fail .btn-fail { background: var(--error); color: white; border-color: var(--error); }
        
        .jira-box { background: var(--bg-body); padding: 12px 20px; border-top: 1px solid var(--border); display: none; }
        .case-card.status-Fail .jira-box { display: block; }
        .jira-box input { width: 100%; padding: 10px 14px; background: var(--input-bg); color: var(--text-main); border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.85rem; font-family: 'Inter', sans-serif; outline: none; transition: border-color 0.15s; }
        .jira-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(2,136,209,0.1); }
        
        .card-content-wrapper { background: var(--bg-surface); width: 100%; height: 100%; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2; position: relative; }
        .remove-trigger { position: absolute; top: 0; right: 0; width: 6px; height: 100%; background-color: transparent; cursor: pointer; transition: all 0.2s; z-index: 10; }
        .remove-trigger:hover { background-color: var(--error-bg); width: 10px; }
        .delete-panel { position: absolute; top: 0; right: 0; bottom: 0; width: 80px; background-color: var(--error-bg); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 1; }
        .delete-btn { color: var(--error); background: var(--bg-surface); border-radius: 50%; padding: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.15s; }
        .delete-btn:hover { transform: scale(1.15) rotate(10deg); color: white; background: var(--error); }
        .case-card.delete-mode .card-content-wrapper { transform: translateX(-80px); }
        
        .right-panel { background: var(--bg-surface); border-left: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
        .rp-head { flex-shrink: 0; padding: 18px 24px; border-bottom: 1px solid var(--border); background: var(--bg-body); }
        .rp-head-title { font-size: 0.73rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); display: block; margin-bottom: 4px; }
        .rp-body { padding: 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 32px; }

        <?php 
        $colors = ['#0288d1', '#16a34a', '#7c3aed', '#e11d48', '#d97706', '#0d9488'];
        $i = 0;
        $tester_colors = [];
        foreach($testers as $tid => $info) { 
            $col = $colors[$i % count($colors)];
            $tester_colors[$tid] = $col;
            echo ".tester-bg-$tid { background-color: $col !important; color: white; }\n";
            echo ".tester-text-$tid { color: $col; }\n";
            $i++;
        }
        ?>
        
        .tester-legend-item { display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; padding: 10px 14px; border-radius: 8px; background: var(--bg-body); border: 1px solid var(--border); margin-bottom: 8px; font-weight: 600; font-family: 'Inter', sans-serif; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; }
        .mini-badge-main { font-size: 0.65rem; font-weight: 800; color: var(--primary); background: var(--bg-surface); border: 1px solid var(--primary); padding: 2px 8px; border-radius: 12px; font-family: 'DM Sans', sans-serif; }
        .calendar-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .grid-cell { aspect-ratio: 1; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: help; transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: 1px solid transparent; position: relative; }
        .cell-unassigned { background: var(--bg-body); border: 1px dashed var(--border); color: var(--text-muted); }
        .grid-cell:hover { z-index: 100; transform: scale(1.15); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12); }
        
        #custom-tooltip { position: fixed; background: #1e293b; color: #fff; padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; pointer-events: none; opacity: 0; transform: translateY(10px) scale(0.95); transition: opacity 0.15s ease, transform 0.15s ease; z-index: 1000; box-shadow: 0 10px 25px rgba(0,0,0,0.2); font-family: 'Inter', sans-serif; min-width: 220px; border: 1px solid rgba(255,255,255,0.1); }
        #custom-tooltip.visible { opacity: 1; transform: translateY(0) scale(1); }
        .tooltip-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .tooltip-label { color: #94a3b8; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        .tooltip-value { font-weight: 600; text-align: right; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
    </style>
</head>

<body>

    <?php Helper::displayLoader(); ?>
    <div id="custom-tooltip"></div>

    <header class="topbar">
        <a href="index.php" class="tb-brand">
            <span class="tb-dot"></span>
            Track Manager
        </a>
        <nav class="tb-crumb">
            <a href="index.php">Dashboard</a>
            <span class="tb-crumb-sep">›</span>
            <span class="tb-crumb-cur">Execute Test</span>
        </nav>
    </header>

    <div class="page-shell">
        <main class="left-panel">
            <div class="lp-heading">
                <div>
                    <h1 class="lp-title">
                        <?= htmlspecialchars($task_info['testing_type']) ?> Test:
                        <span style="color:var(--primary);"><?= htmlspecialchars($task_info['model_name']) ?></span>
                    </h1>
                    <p class="lp-sub">Task ID: #<?= $task_info['id'] ?> &bull; Created: <?= date('M d, Y', strtotime($task_info['created_at'])) ?></p>
                </div>
                <span class="role-badge <?= ($user_role_label == 'Main') ? 'role-main' : 'role-support' ?>" style="font-size:0.8rem; padding: 6px 12px; border-radius: 20px;">
                    Role: <?= htmlspecialchars($user_role_label) ?>
                </span>
            </div>

            <div class="task-info-grid">
                <div class="info-card highlight">
                    <span class="info-label">Firmware Upgrade Path</span>
                    <div class="fw-transition">
                        <div class="fw-ver old">
                            <span>From</span>
                            <strong><?= htmlspecialchars($task_info['fw_version_prev']) ?></strong>
                        </div>
                        <div class="fw-arrow"><span class="material-symbols-outlined" style="color:var(--text-muted);">arrow_right_alt</span></div>
                        <div class="fw-ver new">
                            <span>To (Target)</span>
                            <strong><?= htmlspecialchars($task_info['fw_version_current']) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="mini-row">
                        <div>
                            <span class="info-label">Recovery FW</span>
                            <strong style="color: var(--error);"><?= htmlspecialchars($task_info['fw_version_rec']) ?></strong>
                        </div>
                        <div>
                            <span class="info-label">FW Type</span>
                            <strong><?= htmlspecialchars($task_info['fw_type']) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="mini-row">
                        <div>
                            <span class="info-label">Task Date</span>
                            <strong><?= date('M d', strtotime($task_info['task_date'])) ?></strong>
                        </div>
                        <div>
                            <span class="info-label">Due Date</span>
                            <strong style="color: var(--primary);"><?= date('M d', strtotime($task_info['due_date'])) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="selection-box">
                <h3 class="section-title">Step 1: Select Cases to Execute</h3>
                <span class="section-sub">Click a case below to add it to your execution list.</span>

                <?php if (empty($available_cases)): ?>
                    <div style="font-size:0.9rem; padding:15px; color:var(--text-muted); font-style:italic; text-align:center; background:var(--bg-body); border-radius:8px;">
                        All cases have been assigned. Good job!
                    </div>
                <?php else: ?>
                    <div class="chip-grid">
                        <?php foreach ($available_cases as $case): ?>
                            <button class="case-chip" onclick="claimCase(<?= $case['case_id'] ?>, this)" title="ID: <?= $case['case_code'] ?>">
                                <span class="material-symbols-outlined" style="font-size:18px; color:var(--primary); flex-shrink:0;">add_circle</span>
                                <span><?= htmlspecialchars($case['title']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <h3 class="section-title">Step 2: My Execution List</h3>

            <?php if (empty($my_cases)): ?>
                <div style="text-align:center; padding:50px 20px; border:2px dashed var(--border); border-radius:12px; color:var(--text-muted); font-weight:500;">
                    No cases selected yet.<br>Select cases from Step 1 above.
                </div>
            <?php else: ?>
                <?php foreach ($my_cases as $case): ?>
                    <div class="case-card status-<?= $case['status'] ?? 'Pending' ?>" id="card_<?= $case['case_id'] ?>">
                        <div class="delete-panel" onclick="toggleDeleteMode('card_<?= $case['case_id'] ?>')">
                            <div class="delete-btn" onclick="unclaimCase(event, <?= $case['case_id'] ?>)">
                                <span class="material-symbols-outlined">delete</span>
                            </div>
                        </div>

                        <div class="card-content-wrapper">
                            <div class="remove-trigger" onclick="toggleDeleteMode('card_<?= $case['case_id'] ?>')"></div>
                            <div class="case-row">
                                <div class="status-icon">
                                    <?php if (($case['status'] ?? '') == 'Pass'): ?>
                                        <span class="material-symbols-outlined" style="color:var(--success); font-size: 28px;">check_circle</span>
                                    <?php elseif (($case['status'] ?? '') == 'Fail'): ?>
                                        <span class="material-symbols-outlined" style="color:var(--error); font-size: 28px;">cancel</span>
                                    <?php else: ?>
                                        <span class="material-symbols-outlined" style="color:var(--text-muted); font-size: 28px;">radio_button_unchecked</span>
                                    <?php endif; ?>
                                </div>

                                <div class="case-info">
                                    <div class="case-title"><?= htmlspecialchars($case['title']) ?></div>
                                    <div class="case-code">ID: #<?= htmlspecialchars($case['case_code']) ?></div>
                                </div>

                                <div class="status-actions">
                                    <button type="button" class="status-btn btn-pass" onclick="updateStatus(<?= $case['case_id'] ?>, 'Pass')">Pass</button>
                                    <button type="button" class="status-btn btn-fail" onclick="updateStatus(<?= $case['case_id'] ?>, 'Fail')">Fail</button>
                                </div>
                            </div>

                            <div class="jira-box">
                                <input type="text" id="jira_<?= $case['case_id'] ?>" placeholder="Paste JIRA URL here and press Enter to save..."
                                    value="<?= htmlspecialchars($case['jira_url'] ?? '') ?>" onblur="updateStatus(<?= $case['case_id'] ?>, 'Fail')">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <aside class="right-panel">
            <div class="rp-head">
                <span class="rp-head-title">Testing Overview</span>
            </div>
            
            <div class="rp-body">
                <div>
                    <h4 class="rp-head-title" style="margin-bottom: 12px; color: var(--text-muted);">Team Roster</h4>
                    <?php foreach($testers as $tid => $t): ?>
                        <div class="tester-legend-item">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?= htmlspecialchars($t['pfp'] ?? 'imgs/default_pfp.svg') ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border);">
                                <div class="color-dot tester-bg-<?= $tid ?>"></div>
                                <span style="color: var(--text-main);"><?= htmlspecialchars($t['name']) ?></span>
                            </div>
                            <?php if($t['role'] === 'Main'): ?>
                                <span class="mini-badge-main">MAIN</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div>
                    <h4 class="rp-head-title" style="margin-bottom: 12px; color: var(--text-muted);">Overall Progress</h4>
                    <div class="calendar-grid">
                        <?php foreach ($all_cases as $c): ?>
                            <?php
                            $bgClass = $c['assigned_to'] ? "tester-bg-{$c['assigned_to']}" : "cell-unassigned";
                            $icon = match ($c['status']) {
                                'Pass' => 'check',
                                'Fail' => 'close',
                                default => 'more_horiz'
                            };
                            $testerName = htmlspecialchars($c['assigned_name'] ?? 'Unassigned');
                            
                            // Map PHP status colors to CSS Variables
                            $statusColor = match ($c['status']) {
                                'Pass' => 'var(--success)', 
                                'Fail' => 'var(--error)', 
                                default => 'var(--text-muted)' 
                            };
                            ?>

                            <div id="grid_cell_<?= $c['case_id'] ?>" 
                                class="grid-cell <?= $bgClass ?>"
                                data-code="<?= htmlspecialchars($c['case_code']) ?>"
                                data-title="<?= htmlspecialchars($c['title']) ?>"
                                data-tester="<?= $testerName ?>"
                                data-status="<?= $c['status'] ?>"
                                data-color="<?= $statusColor ?>">
                                <span class="material-symbols-outlined" style="font-size:20px; color: inherit; filter: brightness(2);">
                                    <?= $icon ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        // --- 1. TOOLTIP LOGIC ---
        const tooltip = document.getElementById('custom-tooltip');
        const gridCells = document.querySelectorAll('.grid-cell');

        gridCells.forEach(cell => {
            cell.addEventListener('mouseenter', (e) => {
                const code = cell.getAttribute('data-code');
                const title = cell.getAttribute('data-title');
                const tester = cell.getAttribute('data-tester');
                const status = cell.getAttribute('data-status');
                const color = cell.getAttribute('data-color');

                tooltip.innerHTML = `
                    <div class="tooltip-row">
                        <span class="tooltip-label">Case ID</span>
                        <span class="tooltip-value" style="font-family: 'JetBrains Mono', monospace;">#${code}</span>
                    </div>
                    <div style="margin-bottom:10px; font-size:0.95rem; font-weight:700; line-height:1.4;">${title}</div>
                    <div style="border-top:1px solid rgba(255,255,255,0.1); margin:10px 0;"></div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">Assigned</span>
                        <span class="tooltip-value">${tester}</span>
                    </div>
                    <div class="tooltip-row" style="margin-bottom:0;">
                        <span class="tooltip-label">Status</span>
                        <div class="tooltip-value" style="display:flex; align-items:center; justify-content:flex-end; color:${color}">
                            <span class="status-dot" style="background:${color}"></span>
                            ${status}
                        </div>
                    </div>
                `;
                tooltip.classList.add('visible');
            });

            cell.addEventListener('mousemove', (e) => {
                let top = e.clientY + 15;
                let left = e.clientX + 15;
                if (left + 240 > window.innerWidth) left = e.clientX - 250;
                if (top + 160 > window.innerHeight) top = e.clientY - 170;
                tooltip.style.top = `${top}px`;
                tooltip.style.left = `${left}px`;
            });

            cell.addEventListener('mouseleave', () => {
                tooltip.classList.remove('visible');
            });
        });

        // --- 2. BUSINESS LOGIC (AJAX & UI) ---
        function updateStatus(caseId, status) {
            const card = document.getElementById(`card_${caseId}`);
            const jiraInput = document.getElementById(`jira_${caseId}`);
            const jiraUrl = jiraInput ? jiraInput.value : '';

            // Optimistic UI Update - Card
            card.classList.remove('status-Pass', 'status-Fail', 'status-Pending');
            card.classList.add(`status-${status}`);

            const iconDiv = card.querySelector('.status-icon');
            if (status === 'Pass') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--success); font-size: 28px;">check_circle</span>';
            if (status === 'Fail') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--error); font-size: 28px;">cancel</span>';

            // Optimistic UI Update - Grid (Using CSS Variables)
            const gridCell = document.getElementById(`grid_cell_${caseId}`);
            if (gridCell) {
                let color = 'var(--text-muted)'; let icon = 'more_horiz';
                if (status === 'Pass') { color = 'var(--success)'; icon = 'check'; }
                if (status === 'Fail') { color = 'var(--error)'; icon = 'close'; }

                gridCell.setAttribute('data-status', status);
                gridCell.setAttribute('data-color', color);
                const iconSpan = gridCell.querySelector('.material-symbols-outlined');
                if (iconSpan) iconSpan.textContent = icon;
            }

            // Database Save
            window.showLoader();
            const formData = new FormData();
            formData.append('update_status', '1');
            formData.append('case_id', caseId);
            formData.append('status', status);
            formData.append('jira_url', jiraUrl);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                window.hideLoader();
                if (!data.success) alert("Error saving to database: " + (data.error || "Unknown error"));
            })
            .catch(err => {
                window.hideLoader();
                console.error('Fetch error:', err);
            });
        }

        function toggleDeleteMode(cardId) {
            const card = document.getElementById(cardId);
            card.classList.toggle('delete-mode');
        }

        function showDynamicToast(message, type = 'error') {
            // Remove any existing toast so they don't stack infinitely
            const existing = document.querySelector('.flash-toast.js-dynamic-toast');
            if(existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = `flash-toast js-dynamic-toast ${type}`;
            
            const icon = type === 'error' ? 'cancel' : 'check_circle';
            toast.innerHTML = `<span class="material-symbols-outlined">${icon}</span> ${message}`;
            
            document.body.appendChild(toast);
            
            // Trigger the CSS slide-down animation
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }

        // --- CLAIM CASE AJAX ---
        function claimCase(caseId, btnElement) {
            window.showLoader();
            const formData = new FormData();
            formData.append('claim_case', '1');
            formData.append('case_id', caseId);
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        // FIX: Task was stolen! Hide loader & show the flash message
                        window.hideLoader();
                        showDynamicToast(data.error || "Could not claim task.", 'error');
                        
                        // Shrink and remove the stolen chip so they can't click it again
                        if(btnElement) {
                            btnElement.style.transition = 'all 0.3s ease';
                            btnElement.style.transform = 'scale(0)';
                            btnElement.style.opacity = '0';
                            setTimeout(() => btnElement.remove(), 300);
                        }
                        
                        // Gracefully reload after 2.5 seconds so the rest of the board syncs up
                        setTimeout(() => location.reload(), 2500);
                    }
                })
                .catch(() => window.hideLoader());
        }

        function unclaimCase(event, caseId) {
            event.stopPropagation();
            if (!confirm("Are you sure you want to remove this case from your list?")) return;

            window.showLoader();
            const formData = new FormData();
            formData.append('unclaim_case', '1');
            formData.append('case_id', caseId);

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const card = document.getElementById(`card_${caseId}`);
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-100%)';
                        setTimeout(() => location.reload(), 300);
                    } else {
                        alert("Error: " + (data.error || "Could not remove case"));
                        window.hideLoader();
                    }
                })
                .catch(() => window.hideLoader());
        }

        
    </script>
</body>
</html>