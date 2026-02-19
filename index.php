<?php require_once 'controllers/DashboardController.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="app.css">
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
                <div class="table-responsive">
                    <table class="d-table">
                        <colgroup>
                            <col style="width:12%">
                            <col style="width:12%">
                            <col style="width:26%">
                            <col style="width:26%">
                            <col style="width:18%">
                            <col style="width:6%">
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
                                    <strong style="font-size:0.88rem;" title="<?= $printerName ?>"><?= $printerName ?></strong>
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

                            <tr class="expanded-row">
                                <td colspan="6">
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
                                            <span class="expand-detail-label">Prev / Rec FW</span>
                                            <span class="expand-detail-value">
                                                <span style="color:var(--text-muted); opacity:0.8;"><?= htmlspecialchars($task['fw_version_prev']) ?></span>
                                                <span style="color:var(--border); margin:0 4px;">/</span>
                                                <span style="color:var(--error);"><?= htmlspecialchars($task['fw_version_rec']) ?></span>
                                            </span>
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
                </div>
            <?php endif; ?>

        <?php else: ?>
            <?php if (empty($my_tasks)): ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined">assignment</span>
                    <p>No tasks assigned to you yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="d-table">
                        <colgroup>
                            <col style="width:10%">
                            <col style="width:12%">
                            <col style="width:17%">
                            <col style="width:13%">
                            <col style="width:11%">
                            <col style="width:10%">
                            <col style="width:13%">
                            <col style="width:14%">
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
                                <td><strong style="font-size:0.88rem;" title="<?= $printerName ?>"><?= $printerName ?></strong></td>
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
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>

    <div class="dash-split-row">
        
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

    <div class="d-card">
        <div class="d-card-header">
            <div class="d-card-title">
                <span class="material-symbols-outlined">donut_large</span>
                30-Day Performance by Printer
            </div>
        </div>
        <div class="chart-layout">
            
            <div class="chart-sidebar">
                <?php if(empty($chart_data)): ?>
                    <p style="font-size:0.85rem; color:var(--text-muted); text-align:center; padding: 20px;">No testing data available.</p>
                <?php else: ?>
                    <div class="p-card-grid" id="chartPrinterSelect">
                        <?php foreach ($chart_data as $idx => $data): ?>
                            <?php
                                $pName = htmlspecialchars($data['model_name']);
                                $n = strtolower($pName);
                                $icon = 'print';
                                if (str_contains($n, 'flare')) $icon = 'local_fire_department';
                                if (str_contains($n, 'ray'))   $icon = 'bolt';
                                if (str_contains($n, 'mfp'))  $icon = 'content_copy';
                            ?>
                            <div class="p-card <?= $idx === 0 ? 'p-active' : '' ?>" data-idx="<?= $idx ?>" onclick="selectChartPrinter(<?= $idx ?>)">
                                <div class="p-card-icon">
                                    <span class="material-symbols-outlined"><?= $icon ?></span>
                                </div>
                                <div class="p-card-name"><?= $pName ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-display">
                <?php if(empty($chart_data)): ?>
                    <div class="empty-state" style="padding:0;">
                        <span class="material-symbols-outlined" style="font-size:48px;">pie_chart_outline</span>
                    </div>
                <?php else: ?>
                    <div class="chart-canvas-wrap">
                        <canvas id="progressChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div><?php
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

    // ── Split Chart Logic (Printer Specific Doughnut) ──────
    const rawData = <?= json_encode($chart_data) ?>;
    let chartInstance = null;

    function renderChart(index) {
        if (!rawData || rawData.length === 0) return;
        const data = rawData[index];
        if (!data) return;

        const passed = Number(data.passed);
        const failed = Number(data.failed);
        const pending = Number(data.pending);
        const ctx = document.getElementById('progressChart').getContext('2d');

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Passed', 'Failed', 'Pending'],
                datasets: [{
                    data: [passed, failed, pending],
                    backgroundColor: ['#15803d', '#b91c1c', '#d1d5db'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%', 
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { usePointStyle: true, font: { family: 'Inter', size: 13 }, padding: 20 }
                    },
                    tooltip: { 
                        bodyFont: { family: 'Inter' }, 
                        titleFont: { family: 'Inter', weight: '700' } 
                    }
                }
            }
        });
    }

    // Function called by clicking the side cards
    window.selectChartPrinter = function(index) {
        // 1. Update UI classes
        document.querySelectorAll('#chartPrinterSelect .p-card').forEach(card => {
            card.classList.remove('p-active');
        });
        const activeCard = document.querySelector(`#chartPrinterSelect .p-card[data-idx="${index}"]`);
        if (activeCard) activeCard.classList.add('p-active');

        // 2. Render Chart
        renderChart(index);
    };

    // Initialize chart with the first printer on page load
    if (rawData && rawData.length > 0) {
        renderChart(0);
    }

</script>
</body>
</html>