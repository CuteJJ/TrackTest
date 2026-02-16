<?php require_once 'controllers/DashboardController.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="app.css">
    <script src="app.js" defer></script>
    
    <style>
        /* Dashboard Specific Grid Overrides */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
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

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }
        .card-title { font-size: 1.1rem; font-weight: 600; color: var(--text-main); }
        
        /* Navbar */
        .navbar {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th { text-align: left; color: var(--text-muted); padding: 12px 8px; border-bottom: 1px solid var(--border); }
        .data-table td { padding: 12px 8px; color: var(--text-main); border-bottom: 1px solid var(--border); }
        .data-table tr:last-child td { border-bottom: none; }
        
        /* Badges */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-lead { background: #e3f2fd; color: #1976d2; }
        .badge-tester { background: #f3e5f5; color: #7b1fa2; }
        
        .todo-date { font-family: monospace; color: var(--text-muted); }
        .todo-type-smoke { color: #e65100; background: #fff3e0; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; }
        .todo-type-reg { color: #01579b; background: #e1f5fe; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; }
        
        .role-badge { font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; font-weight: 700; }
        .role-main { background: rgba(33, 150, 243, 0.1); color: var(--primary); }
        .role-support { background: var(--border); color: var(--text-muted); }
    </style>
</head>
<body>
    
    <?php Helper::displayFlash(); ?>

    <div style="width: 100%; min-height: 100vh; display: flex; flex-direction: column;">
        
        <nav class="navbar">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2 style="margin:0;">Track Manager</h2>
                
                <?php if($_SESSION['role'] === 'lead'): ?>
                    <a href="create_task.php" class="btn" style="width:auto; padding: 8px 16px; font-size:0.9rem;">+ Create Task</a>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center;">
                <span style="font-size: 0.9rem; color: var(--text-muted);">
                    <?= htmlspecialchars($_SESSION['full_name']) ?> (<?= ucfirst($_SESSION['role']) ?>)
                </span>
                <a href="logout.php" style="color: var(--error); text-decoration: none; font-size: 0.9rem;">Logout</a>
            </div>
        </nav>

        <div class="dashboard-grid">
            
            <div class="card col-span-8">
                <div class="card-header">
                    <span class="card-title">
                        <?= $_SESSION['role'] === 'lead' ? 'All Active Testing Tasks' : 'My Pending Assignments' ?>
                    </span>
                </div>

                <?php if ($_SESSION['role'] === 'lead'): ?>
                    <?php if (empty($lead_tasks)): ?>
                        <div style="padding: 2rem; text-align: center; color: var(--text-muted);">No active tasks found.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Printer</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lead_tasks as $task): ?>
                                <?php 
                                    $is_complete = $task['completed_cases'] >= $task['total_cases'] && $task['total_cases'] > 0;
                                    $percent = $task['total_cases'] > 0 ? round(($task['completed_cases'] / $task['total_cases']) * 100) : 0;
                                ?>
                                <tr>
                                    <td class="todo-date"><?= date('M d', strtotime($task['task_date'])) ?></td>
                                    <td><span class="<?= $task['testing_type'] == 'Smoke' ? 'todo-type-smoke' : 'todo-type-reg' ?>"><?= htmlspecialchars($task['testing_type']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($task['model_name']) ?></strong></td>
                                    <td style="width: 150px; vertical-align: middle;">
                                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:4px;">
                                            <span><?= $task['completed_cases'] ?>/<?= $task['total_cases'] ?></span>
                                            <span><?= $percent ?>%</span>
                                        </div>
                                        <div style="width:100%; height:6px; background:var(--border); border-radius:3px; overflow:hidden;">
                                            <div style="width:<?= $percent ?>%; height:100%; background:var(--primary);"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($task['overall_status'] == 'Pass'): ?>
                                            <span class="badge" style="background:var(--success-bg); color:var(--success);">PASSED</span>
                                        <?php elseif($task['overall_status'] == 'Fail'): ?>
                                            <span class="badge" style="background:var(--error-bg); color:var(--error);">FAILED</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($task['testing_type'] == 'Smoke'): ?>
                                            <?php if ($is_complete): ?>
                                                <a href="report.php?task_id=<?= $task['task_id'] ?>&printer_id=<?= $task['printer_id'] ?>" 
                                                   class="btn" target="_blank" style="padding: 6px 12px; font-size: 0.85rem; width: auto;">View Report</a>
                                            <?php else: ?>
                                                <button class="btn" disabled style="padding: 6px 12px; font-size: 0.85rem; width: auto; opacity: 0.5; cursor: not-allowed; background: var(--text-muted);">In Progress</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">Regression</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if (empty($my_tasks)): ?>
                        <div style="padding: 2rem; text-align: center; color: var(--text-muted);">No tasks assigned to you yet.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Printer</th>
                                    <th>Role</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_tasks as $task): ?>
                                <tr>
                                    <td class="todo-date"><?= date('M d', strtotime($task['task_date'])) ?></td>
                                    <td>
                                        <span class="<?= $task['testing_type'] == 'Smoke' ? 'todo-type-smoke' : 'todo-type-reg' ?>">
                                            <?= htmlspecialchars($task['testing_type']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($task['model_name']) ?></strong></td>
                                    <td>
                                        <?php if($task['testing_type'] == 'Regression'): ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">Full Suite</span>
                                        <?php else: ?>
                                            <span class="role-badge <?= $task['designation'] == 'Main' ? 'role-main' : 'role-support' ?>">
                                                <?= htmlspecialchars($task['designation']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if($task['testing_type'] == 'Regression'): ?>
                                            <?php if (!empty($task['regression_url'])): ?>
                                                <a href="<?= htmlspecialchars($task['regression_url']) ?>" target="_blank" class="btn" 
                                                   style="padding: 6px 12px; font-size: 0.85rem; background: var(--bg-surface); color: var(--primary); border: 1px solid var(--border);">
                                                   Open TestRail ↗
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size:0.85rem;">No Link</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="execute_task.php?task_id=<?= $task['id'] ?>&printer_id=<?= $task['printer_id'] ?>" 
                                               class="btn" style="padding: 6px 12px; font-size: 0.85rem; width: auto;">
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
                                <div><?= htmlspecialchars($member['full_name']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= $member['last_login'] ? time_ago($member['last_login']) : 'Never' ?>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <span class="badge <?= $member['role'] === 'lead' ? 'badge-lead' : 'badge-tester' ?>">
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
                                <td><?= htmlspecialchars($fw['model']) ?></td>
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
                    <span class="card-title">30-Day Smoke Test Performance</span>
                </div>
                <div style="height: 300px;">
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
        const rawData = <?= json_encode($chart_data) ?>;
        const ctx = document.getElementById('progressChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rawData.map(d => d.model_name),
                datasets: [
                    { label: 'Passed', data: rawData.map(d => d.passed), backgroundColor: '#4caf50' },
                    { label: 'Failed', data: rawData.map(d => d.failed), backgroundColor: '#f44336' },
                    { label: 'Pending', data: rawData.map(d => d.pending), backgroundColor: '#ff9800' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { stacked: true }, y: { stacked: true } }
            }
        });
    </script>
</body>
</html>