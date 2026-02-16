<?php require_once 'controllers/DashboardController.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="app.css">
    
    <style>
        /* Dashboard Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .col-span-12 { grid-column: span 12; }
        .col-span-8 { grid-column: span 8; }
        .col-span-4 { grid-column: span 4; }
        
        @media (max-width: 1000px) {
            .col-span-8, .col-span-4 { grid-column: span 12; }
        }

        /* Navbar */
        .navbar {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Fixed Width Table to prevent jumping */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; table-layout: fixed; }
        
        .data-table th { 
            text-align: left; 
            color: var(--text-muted); 
            font-weight: 600;
            padding: 10px 16px; 
            border-bottom: 1px solid var(--border); 
            background: #f9fafb;
            white-space: nowrap; /* Prevent headers form wrapping */
        }
        
        /* UPDATED: Table cells with text truncation */
        .data-table td { 
            padding: 10px 16px; 
            color: var(--text-main); 
            border-bottom: 1px solid var(--border); 
            vertical-align: middle;
            
            /* Truncate text */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .data-table tr:last-child td { border-bottom: none; }

        /* Expandable Rows */
        .expand-trigger { cursor: pointer; transition: background 0.1s; }
        .expand-trigger:hover { background: #f9fafb; }
        
        .expanded-row { display: none; background: #f8fafc; }
        .expanded-row.show { display: table-row; }
        
        .expanded-content {
            padding: 16px 20px;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }
        
        .detail-group label { display: block; font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 4px; }
        .detail-group div { font-size: 0.85rem; font-weight: 500; font-family: 'Roboto Mono', monospace; }

        /* Icons */
        .icon-btn {
            border: none; background: transparent; cursor: pointer;
            color: var(--text-muted); padding: 6px; border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .icon-btn:hover { background: #e0f2fe; color: var(--primary); }
        .icon-btn.delete:hover { background: #fee2e2; color: var(--error); }

        /* Progress Bar */
        .progress-track { width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--primary); transition: width 0.3s; }
        
        /* Badges */
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.02em; display: inline-block; }
        .role-badge { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-weight: 700; }
        .role-main { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .role-support { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
        
        .todo-type-smoke { color: #c2410c; background: #ffedd5; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fed7aa; }
        .todo-type-reg { color: #0369a1; background: #e0f2fe; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; border: 1px solid #bae6fd; }
    </style>
</head>
<body>
    
    <?php Helper::displayFlash(); ?>

    <div style="width: 100%; min-height: 100vh; display: flex; flex-direction: column;">
        
        <nav class="navbar">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2 style="margin:0; font-size: 1.25rem; letter-spacing: -0.5px;">Track Manager</h2>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                    <?= htmlspecialchars($_SESSION['full_name']) ?> <span style="color:var(--border)">|</span> <?= ucfirst($_SESSION['role']) ?>
                </span>
                <a href="logout.php" style="color: var(--error); text-decoration: none; font-size: 0.85rem; font-weight: 600;">Logout</a>
            </div>
        </nav>

        <div class="dashboard-grid">
            
            <div class="card col-span-8">
                <div class="card-header">
                    <span class="card-title">
                        <?= $_SESSION['role'] === 'lead' ? 'Active Testing Tasks' : 'My Assignments' ?>
                    </span>
                    
                    <?php if($_SESSION['role'] === 'lead'): ?>
                        <a href="create_task.php" class="btn" style="width:auto; padding: 6px 12px; font-size:0.8rem;">+ Create Task</a>
                    <?php endif; ?>
                </div>

                <?php if ($_SESSION['role'] === 'lead'): ?>
                    <?php if (empty($lead_tasks)): ?>
                        <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <p>No active tasks found.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <colgroup>
                                <col style="width: 15%"> <col style="width: 12%"> <col style="width: 25%"> <col style="width: 25%"> <col style="width: 18%"> <col style="width: 5%">  </colgroup>
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
                                    // Logic for Report Availability
                                    $is_complete = ($task['completed_cases'] >= $task['total_cases']) && ($task['total_cases'] > 0);
                                    $percent = $task['total_cases'] > 0 ? round(($task['completed_cases'] / $task['total_cases']) * 100) : 0;
                                    $rowId = "task_" . $task['task_id'] . "_" . $task['printer_id'];
                                    $printerName = htmlspecialchars($task['model_name']);
                                ?>
                                <tr class="expand-trigger" onclick="toggleRow('<?= $rowId ?>')">
                                    <td class="todo-date"><?= date('M d', strtotime($task['task_date'])) ?></td>
                                    <td><span class="<?= $task['testing_type'] == 'Smoke' ? 'todo-type-smoke' : 'todo-type-reg' ?>"><?= htmlspecialchars($task['testing_type']) ?></span></td>
                                    
                                    <td title="<?= $printerName ?>">
                                        <strong><?= $printerName ?></strong>
                                    </td>
                                    
                                    <td>
                                        <div style="display:flex; justify-content:space-between; font-size:0.7rem; margin-bottom:3px; color:var(--text-muted);">
                                            <span><?= $task['completed_cases'] ?>/<?= $task['total_cases'] ?></span>
                                            <span><?= $percent ?>%</span>
                                        </div>
                                        <div class="progress-track">
                                            <div class="progress-fill" style="width:<?= $percent ?>%;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($task['overall_status'] == 'Pass'): ?>
                                            <span class="badge" style="background:var(--success-bg); color:var(--success); border:1px solid #bbf7d0;">PASSED</span>
                                        <?php elseif($task['overall_status'] == 'Fail'): ?>
                                            <span class="badge" style="background:var(--error-bg); color:var(--error); border:1px solid #fecaca;">FAILED</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.75rem;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="material-symbols-outlined chevron-icon" style="font-size:20px; color:var(--text-muted);">expand_more</span>
                                    </td>
                                </tr>

                                <tr class="expanded-row" id="<?= $rowId ?>">
                                    <td colspan="6" style="padding: 0;">
                                        <div class="expanded-content">
                                            
                                            <div class="detail-group">
                                                <label>Due Date</label>
                                                <div><?= date('M d', strtotime($task['due_date'])) ?></div>
                                            </div>
                                            <div class="detail-group">
                                                <label>Target FW</label>
                                                <div style="color:var(--primary);"><?= htmlspecialchars($task['fw_version_current']) ?></div>
                                            </div>
                                            <div class="detail-group">
                                                <label>Branch</label>
                                                <div><?= htmlspecialchars($task['fw_type']) ?></div>
                                            </div>
                                            <div class="detail-group">
                                                <label>Prev / Rec FW</label>
                                                <div style="font-size:0.8rem; color:var(--text-muted);">
                                                    <?= htmlspecialchars($task['fw_version_prev']) ?> / 
                                                    <span style="color:var(--error);"><?= htmlspecialchars($task['fw_version_rec']) ?></span>
                                                </div>
                                            </div>

                                            <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                                <?php if ($task['testing_type'] == 'Smoke'): ?>
                                                    <?php if ($is_complete): ?>
                                                        <a href="report.php?task_id=<?= $task['task_id'] ?>&printer_id=<?= $task['printer_id'] ?>" 
                                                           class="btn" style="width:auto; font-size:0.75rem; padding:6px 12px; white-space:nowrap;">View Report</a>
                                                    <?php else: ?>
                                                        <button disabled class="btn" style="width:auto; font-size:0.75rem; padding:6px 12px; background:#e5e7eb; color:#9ca3af; cursor:not-allowed; white-space:nowrap;">In Progress</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <div style="width:1px; height:20px; background:var(--border); margin:0 4px;"></div>

                                                <a href="edit_task.php?id=<?= $task['task_id'] ?>" class="icon-btn" title="Edit Task">
                                                    <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                                                </a>
                                                <a href="delete_task.php?id=<?= $task['task_id'] ?>" class="icon-btn delete" title="Delete Task" onclick="return confirm('Delete this task?');">
                                                    <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
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
                        <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <p>No tasks assigned to you yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <colgroup>
                                <col style="width: 12%">
                                <col style="width: 10%">
                                <col style="width: 15%">
                                <col style="width: 15%">
                                <col style="width: 10%">
                                <col style="width: 10%">
                                <col style="width: 13%">
                                <col style="width: 15%">
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
                                    <th style="text-align: left;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_tasks as $task): ?>
                                <?php $printerName = htmlspecialchars($task['model_name']); ?>
                                <tr>
                                    <td class="todo-date"><?= date('M d', strtotime($task['task_date'])) ?></td>
                                    <td>
                                        <span class="<?= $task['testing_type'] == 'Smoke' ? 'todo-type-smoke' : 'todo-type-reg' ?>">
                                            <?= htmlspecialchars($task['testing_type']) ?>
                                        </span>
                                    </td>
                                    
                                    <td title="<?= $printerName ?>">
                                        <strong><?= $printerName ?></strong>
                                    </td>

                                    <td style="font-family:monospace; color:var(--primary); font-weight:600;">
                                        <?= htmlspecialchars($task['fw_version_current']) ?>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= htmlspecialchars($task['fw_type']) ?>
                                    </td>
                                    <td>
                                        <span class="role-badge <?= $task['designation'] == 'Main' ? 'role-main' : 'role-support' ?>">
                                            <?= htmlspecialchars($task['designation']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($task['overall_status'] == 'Pass'): ?>
                                            <span class="badge" style="background:var(--success-bg); color:var(--success); border:1px solid #bbf7d0;">PASSED</span>
                                        <?php elseif($task['overall_status'] == 'Fail'): ?>
                                            <span class="badge" style="background:var(--error-bg); color:var(--error); border:1px solid #fecaca;">FAILED</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.75rem;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: left;">
                                        <?php if($task['testing_type'] == 'Regression'): ?>
                                            <a href="<?= htmlspecialchars($task['regression_url']) ?>" target="_blank" class="btn" 
                                               style="padding: 6px 12px; font-size: 0.8rem; background: white; color: var(--primary); border: 1px solid var(--border); white-space:nowrap;">
                                               Open TestRail
                                            </a>
                                        <?php else: ?>
                                            <a href="execute_task.php?task_id=<?= $task['id'] ?>&printer_id=<?= $task['printer_id'] ?>" 
                                               class="btn" style="padding: 6px 12px; font-size: 0.8rem; width: auto; white-space:nowrap;">
                                               Execute Test
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

            <div class="card col-span-4">
                <div class="card-header">
                    <span class="card-title">Team Status</span>
                </div>
                <table class="data-table">
                    <tbody>
                        <?php foreach ($team_members as $member): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($member['full_name']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= $member['last_login'] ? time_ago($member['last_login']) : 'Never' ?>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <span class="badge" style="background:#f3f4f6; color:#4b5563;">
                                    <?= ucfirst($member['role']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card col-span-12">
                <div class="card-header">
                    <span class="card-title">Latest Firmware Versions</span>
                </div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Branch Version</th>
                                <th>Trunk Version</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($firmware_overview as $fw): ?>
                            <tr>
                                <td title="<?= htmlspecialchars($fw['model']) ?>"><?= htmlspecialchars($fw['model']) ?></td>
                                <td style="font-family:monospace;"><?= htmlspecialchars($fw['branch']) ?></td>
                                <td style="font-family:monospace;"><?= htmlspecialchars($fw['trunk']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card col-span-12">
                <div class="card-header">
                    <span class="card-title">30-Day Performance</span>
                </div>
                <div style="height: 300px; padding: 20px;">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <?php
    function time_ago($datetime) {
        $interval = time() - strtotime($datetime);
        if ($interval < 60) return 'Just now';
        if ($interval < 3600) return floor($interval/60) . 'm ago';
        if ($interval < 86400) return floor($interval/3600) . 'h ago';
        return floor($interval/86400) . 'd ago';
    }
    ?>

    <script>
        // Accordion Logic (Single Row Open)
        function toggleRow(rowId) {
            const targetRow = document.getElementById(rowId);
            const isCurrentlyOpen = targetRow.classList.contains('show');
            const targetIcon = document.querySelector(`tr[onclick="toggleRow('${rowId}')"] .chevron-icon`);

            // 1. Close ALL expanded rows
            document.querySelectorAll('.expanded-row.show').forEach(row => {
                row.classList.remove('show');
            });
            document.querySelectorAll('.chevron-icon').forEach(icon => {
                icon.innerText = 'expand_more';
            });

            // 2. If it wasn't open before, open it now
            if (!isCurrentlyOpen) {
                targetRow.classList.add('show');
                targetIcon.innerText = 'expand_less';
            }
        }

        const rawData = <?= json_encode($chart_data) ?>;
        const ctx = document.getElementById('progressChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rawData.map(d => d.model_name),
                datasets: [
                    { label: 'Passed', data: rawData.map(d => d.passed), backgroundColor: '#15803d' },
                    { label: 'Failed', data: rawData.map(d => d.failed), backgroundColor: '#b91c1c' },
                    { label: 'Pending', data: rawData.map(d => d.pending), backgroundColor: '#d1d5db' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { 
                    x: { stacked: true, grid: { display: false } }, 
                    y: { stacked: true, beginAtZero: true } 
                },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true } }
                }
            }
        });
    </script>
</body>
</html>