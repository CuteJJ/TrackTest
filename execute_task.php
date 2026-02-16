<?php require_once 'controllers/TestRunController.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Execute Test | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="app.css">

    <style>
        /* Dynamic Colors for Testers */
        <?php 
        $colors = ['#E91E63', '#9C27B0', '#2196F3', '#009688', '#FF9800', '#795548'];
        $i = 0;
        $tester_colors = [];
        foreach($testers as $tid => $info) { // Changed $name to $info
            $col = $colors[$i % count($colors)];
            $tester_colors[$tid] = $col;
            echo ".tester-bg-$tid { background-color: $col !important; color: white; }";
            echo ".tester-text-$tid { color: $col; }";
            $i++;
        }
        ?>
        
        .cell-unassigned {
            background: var(--bg-body);
            border: 1px dashed var(--border);
            color: var(--text-muted);
        }

        /* Interactive Grid Cell */
        .grid-cell {
            position: relative;
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .grid-cell:hover {
            z-index: 100;
            transform: scale(1.15);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body style="height: 100vh; overflow: hidden; display: block;">

    <div id="custom-tooltip"></div>

    <div class="execution-container">

        <div class="exec-header">
            <div style="display:flex; flex-direction: column; gap: 20px;">

                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div style="display:flex; align-items:center; gap: 12px; margin-bottom: 4px;">
                            <h2 style="margin:0; font-size: 1.25rem;">
                                <?= htmlspecialchars($task_info['testing_type']) ?> Test:
                                <span style="color:var(--primary);"><?= htmlspecialchars($task_info['model_name']) ?></span>
                            </h2>
                            <span class="role-badge <?= ($user_role_label == 'Main') ? 'role-main' : 'role-support' ?>"
                                style="font-size:0.8rem; padding: 4px 10px;">
                                <?= htmlspecialchars($user_role_label) ?>
                            </span>
                        </div>
                        <div style="color:var(--text-muted); font-size:0.85rem;">
                            Task ID: #<?= $task_info['id'] ?> &bull; Created: <?= date('M d, Y', strtotime($task_info['created_at'])) ?>
                        </div>
                    </div>
                    <a href="index.php" class="btn" style="width:auto; background:transparent; border:1px solid var(--border); color:var(--text-main); padding: 6px 16px; font-size:0.9rem;">
                        &larr; Dashboard
                    </a>
                </div>

                <div class="task-info-grid">

                    <div class="info-card highlight">
                        <span class="info-label">Firmware Upgrade Path</span>
                        <div class="fw-transition">
                            <div class="fw-ver old">
                                <span>From</span>
                                <strong><?= htmlspecialchars($task_info['fw_version_prev']) ?></strong>
                            </div>
                            <div class="fw-arrow">
                                <span class="material-symbols-outlined">arrow_right_alt</span>
                            </div>
                            <div class="fw-ver new">
                                <span>To (Current)</span>
                                <strong><?= htmlspecialchars($task_info['fw_version_current']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="mini-row">
                            <div>
                                <span class="info-label">Recovery FW</span><br>
                                <strong style="color: #d08770;"><?= htmlspecialchars($task_info['fw_version_rec']) ?></strong>
                            </div>
                            <div>
                                <span class="info-label">FW Type</span><br>
                                <strong><?= htmlspecialchars($task_info['fw_type']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="mini-row">
                            <div>
                                <span class="info-label">Task Date</span><br>
                                <strong><?= date('M d', strtotime($task_info['task_date'])) ?></strong>
                            </div>
                            <div>
                                <span class="info-label">Due Date</span><br>
                                <strong style="color:var(--error);"><?= date('M d', strtotime($task_info['due_date'])) ?></strong>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="exec-body">

            <div class="exec-left">
                <div class="selection-box">
                    <h3 style="margin:0 0 10px; font-size:1rem;">Step 1: Select Cases to Execute</h3>
                    <p style="font-size:0.85rem; color:var(--text-muted);">Click a case below to add it to your execution list.</p>

                    <?php if (empty($available_cases)): ?>
                        <div style="font-size:0.9rem; padding:15px; color:var(--text-muted); font-style:italic;">
                            All cases have been assigned. Good job!
                        </div>
                    <?php else: ?>
                        <div class="chip-grid">
                            <?php foreach ($available_cases as $case): ?>
                                <button class="case-chip" onclick="claimCase(<?= $case['case_id'] ?>)" title="ID: <?= $case['case_code'] ?>">
                                    <span class="material-symbols-outlined" style="font-size:18px; color:var(--primary); flex-shrink:0;">add_circle</span>
                                    <span style="font-weight:500; color:var(--text-main); text-align:left;"><?= htmlspecialchars($case['title']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 style="margin:0 0 15px; font-size:1rem;">Step 2: My Execution List</h3>

                <?php if (empty($my_cases)): ?>
                    <div style="text-align:center; padding:40px; border:2px dashed var(--border); border-radius:8px; color:var(--text-muted);">
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
                                            <span class="material-symbols-outlined" style="color:var(--success);">check_circle</span>
                                        <?php elseif (($case['status'] ?? '') == 'Fail'): ?>
                                            <span class="material-symbols-outlined" style="color:var(--error);">cancel</span>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined" style="color:var(--text-muted);">radio_button_unchecked</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="case-info">
                                        <div class="case-title"><?= htmlspecialchars($case['title']) ?></div>
                                        <div class="case-code" style="font-size:0.7rem; color:var(--text-muted);">#<?= htmlspecialchars($case['case_code']) ?></div>
                                    </div>

                                    <div class="status-actions">
                                        <button type="button" class="status-btn btn-pass" onclick="updateStatus(<?= $case['case_id'] ?>, 'Pass')">Pass</button>
                                        <button type="button" class="status-btn btn-fail" onclick="updateStatus(<?= $case['case_id'] ?>, 'Fail')">Fail</button>
                                    </div>
                                </div>

                                <div class="jira-box">
                                    <input type="text" class="form-control" id="jira_<?= $case['case_id'] ?>" placeholder="JIRA URL..."
                                        value="<?= htmlspecialchars($case['jira_url'] ?? '') ?>" onblur="updateStatus(<?= $case['case_id'] ?>, 'Fail')">
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="exec-right">
                <div>
                    <h4 style="margin:0 0 15px; font-size:0.9rem; text-transform:uppercase; color:var(--text-muted);">Testers</h4>
                    <?php foreach($testers as $tid => $t): ?>
                        <div class="tester-legend-item" style="background: var(--bg-body); justify-content: space-between;">
                            
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="color-dot tester-bg-<?= $tid ?>"></div>
                                <span><?= htmlspecialchars($t['name']) ?></span>
                            </div>

                            <?php if($t['role'] === 'Main'): ?>
                                <span class="mini-badge-main">MAIN</span>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>

                <div>
                    <h4 style="margin:0 0 15px; font-size:0.9rem; text-transform:uppercase; color:var(--text-muted);">Overall Progress</h4>
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
                            $statusColor = match ($c['status']) {
                                'Pass' => '#4caf50',
                                'Fail' => '#f44336',
                                default => '#9e9e9e'
                            };
                            ?>

                            <div class="grid-cell <?= $bgClass ?>"
                                data-code="<?= htmlspecialchars($c['case_code']) ?>"
                                data-title="<?= htmlspecialchars($c['title']) ?>"
                                data-tester="<?= $testerName ?>"
                                data-status="<?= $c['status'] ?>"
                                data-color="<?= $statusColor ?>">

                                <span class="material-symbols-outlined" style="font-size:18px; color: inherit; filter: brightness(2);">
                                    <?= $icon ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
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

                // Build HTML Content
                tooltip.innerHTML = `
                    <div class="tooltip-row">
                        <span class="tooltip-label">Case ID</span>
                        <span class="tooltip-value" style="font-family:monospace">${code}</span>
                    </div>
                    <div style="margin-bottom:8px; font-size:0.9rem; font-weight:600; line-height:1.4;">${title}</div>
                    
                    <div style="border-top:1px solid rgba(255,255,255,0.1); margin:8px 0;"></div>
                    
                    <div class="tooltip-row">
                        <span class="tooltip-label">Tester</span>
                        <span class="tooltip-value">${tester}</span>
                    </div>
                    <div class="tooltip-row">
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
                // Position tooltip near mouse but slightly offset
                // Check bounds to prevent going off screen
                let top = e.clientY + 15;
                let left = e.clientX + 15;

                // Simple check for right edge
                if (left + 220 > window.innerWidth) {
                    left = e.clientX - 225;
                }

                // Simple check for bottom edge
                if (top + 150 > window.innerHeight) {
                    top = e.clientY - 160;
                }

                tooltip.style.top = `${top}px`;
                tooltip.style.left = `${left}px`;
            });

            cell.addEventListener('mouseleave', () => {
                tooltip.classList.remove('visible');
            });
        });


        // --- 2. BUSINESS LOGIC (Claim & Update) ---
        function claimCase(caseId) {
            const formData = new FormData();
            formData.append('claim_case', '1');
            formData.append('case_id', caseId);

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                });
        }

        function updateStatus(caseId, status) {
            const card = document.getElementById(`card_${caseId}`);
            const jiraUrl = document.getElementById(`jira_${caseId}`) ? document.getElementById(`jira_${caseId}`).value : '';

            card.classList.remove('status-Pass', 'status-Fail', 'status-Pending');
            card.classList.add(`status-${status}`);

            const iconDiv = card.querySelector('.status-icon');
            if (status === 'Pass') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--success);">check_circle</span>';
            if (status === 'Fail') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--error);">cancel</span>';

            const formData = new FormData();
            formData.append('update_status', '1');
            formData.append('case_id', caseId);
            formData.append('status', status);
            formData.append('jira_url', jiraUrl);

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Logic to update grid cell live would go here
                        // For now, refresh page updates grid
                        location.reload();
                    }
                });
        }
        // 1. TOGGLE SLIDE ANIMATION
        function toggleDeleteMode(cardId) {
            const card = document.getElementById(cardId);
            // Toggle the class that triggers CSS transform
            card.classList.toggle('delete-mode');
        }

        // 2. UNCLAIM LOGIC (With Confirmation)
        function unclaimCase(event, caseId) {
            // STOP the click from bubbling up to the panel (which would close the slide)
            event.stopPropagation();

            if (!confirm("Are you sure you want to remove this case from your list?")) {
                return; // Stop if user cancels
            }

            const formData = new FormData();
            formData.append('unclaim_case', '1');
            formData.append('case_id', caseId);

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const card = document.getElementById(`card_${caseId}`);
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-100%)';
                        setTimeout(() => location.reload(), 300);
                    } else {
                        alert("Error: " + (data.error || "Could not remove case"));
                    }
                });
        }
    </script>
</body>

</html>