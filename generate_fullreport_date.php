<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';
Helper::requireRole(['lead', 'admin']);

// === Accept both old single-date and new date-range parameters ===
if (isset($_GET['date']) && !isset($_GET['start_date'])) {
    $start_date = $_GET['date'];
    $end_date   = $_GET['date'];
} else {
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;
}

if (!$start_date || !$end_date) {
    die("Date parameter is missing.");
}

if ($end_date < $start_date) {
    die("End date cannot be before start date.");
}

 $date_label = ($start_date === $end_date)
    ? date('F d, Y', strtotime($start_date))
    : date('F d, Y', strtotime($start_date)) . ' — ' . date('F d, Y', strtotime($end_date));

// Helper: extract JIRA ticket ID from full URL
function extractJiraId($url) {
    if (preg_match('/[A-Za-z]+-\d+/', trim($url), $matches)) {
        return $matches[0];
    }
    return trim($url);
}

// 1. Fetch All Smoke Tasks for the Date Range
 $stmt = $pdo->prepare("
    SELECT t.id as task_id, t.task_date, t.fw_version_current, t.fw_type, p.id as printer_id, p.model_name,
           (SELECT MAX(overall_status) FROM task_assignments ta2 WHERE ta2.task_id = t.id AND ta2.printer_id = p.id) as overall_status
    FROM tasks t
    JOIN task_assignments ta ON t.id = ta.task_id
    JOIN printers p ON ta.printer_id = p.id
    WHERE t.task_date BETWEEN ? AND ? AND t.testing_type = 'Smoke'
    GROUP BY t.id, t.task_date, t.fw_version_current, t.fw_type, p.id, p.model_name
    ORDER BY t.task_date DESC, p.model_name ASC
");
 $stmt->execute([$start_date, $end_date]);
 $tasks = $stmt->fetchAll();

if (empty($tasks)) {
    die("No Smoke tests found between " . $date_label . ".");
}

// Global Stats Trackers
 $g_total = $g_passed = $g_failed = $g_blocked = $g_na = $g_pending = 0;
 $global_issues = [];

 $printer_reports = [];
 $charts_data = [];

foreach ($tasks as $t) {
    $stmt_res = $pdo->prepare("
        SELECT tc.case_code, tc.title, tr.status, tr.jira_url, u.full_name as tester_name
        FROM test_cases tc
        LEFT JOIN test_results tr ON tc.id = tr.test_case_id AND tr.task_id = ? AND tr.printer_id = ?
        LEFT JOIN users u ON tr.updated_by = u.id
        WHERE tc.printer_model = ?
        ORDER BY tc.case_code ASC
    ");
    $stmt_res->execute([$t['task_id'], $t['printer_id'], $t['model_name']]);
    $cases = $stmt_res->fetchAll();

    $total = count($cases);
    $passed = $failed = $blocked = $na = $pending = 0;
    $local_issues = [];

    foreach ($cases as $c) {
        switch ($c['status']) {
            case 'Pass': $passed++; break;
            case 'Fail': $failed++; break;
            case 'Blocked': $blocked++; break;
            case 'N/A': $na++; break;
            default: $pending++; break;
        }

        if (!empty(trim($c['jira_url']))) {
            $issue_data = [
                'printer' => $t['model_name'],
                'case_code' => $c['case_code'],
                'title' => $c['title'],
                'urls' => array_filter(array_map('trim', explode(',', $c['jira_url'] ?? '')))
            ];
            $local_issues[] = $issue_data;
            $global_issues[] = $issue_data;
        }
    }

    $g_total += $total; $g_passed += $passed; $g_failed += $failed; 
    $g_blocked += $blocked; $g_na += $na; $g_pending += $pending;

    $display_status = $t['overall_status'];
    if ($display_status !== 'Pass' && $display_status !== 'Fail' && $display_status !== 'Blocked' && $display_status !== 'N/A') {
        $display_status = 'In Progress';
    }

    $printer_reports[] = [
        'info' => $t,
        'display_status' => $display_status,
        'cases' => $cases,
        'issues' => $local_issues,
        'stats' => [
            'total' => $total, 'passed' => $passed, 'failed' => $failed,
            'blocked' => $blocked, 'na' => $na, 'pending' => $pending,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100) : 0
        ]
    ];
}

 $g_pass_rate = $g_total > 0 ? round(($g_passed / $g_total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Smoke Test Report - <?= htmlspecialchars($date_label) ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap');
        
        :root {
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --primary: #0288d1;
            --success: #10b981;
            --error: #ef4444;
            --blocked: #f97316;
            --na: #8b5cf6;
            --in-progress: #ca8a04;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; padding: 40px 20px; }
        
        .container { max-width: 1000px; margin: 0 auto; background: var(--bg-surface); box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 50px; border-radius: 12px; }

        .action-buttons { position: fixed; top: 20px; right: 20px; display: flex; gap: 12px; z-index: 1000; }
        .btn-action { 
            display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; 
            border-radius: 8px; font-weight: 700; font-family: 'Inter', sans-serif; 
            font-size: 0.9rem; cursor: pointer; border: none; text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.2s;
        }
        .btn-action.back { background: var(--bg-surface); color: var(--text-main); border: 1px solid var(--border); }
        .btn-action.back:hover { background: var(--bg-body); }
        .btn-action.print { background: var(--primary); color: white; }
        .btn-action.print:hover { filter: brightness(1.1); }
        .btn-action .material-symbols-outlined { font-size: 18px; }

        .master-header { text-align: center; border-bottom: 2px solid var(--text-main); padding-bottom: 30px; margin-bottom: 40px; page-break-after: avoid; }
        .master-header h1 { margin: 0 0 10px 0; font-size: 2.2rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .master-header p { margin: 0; font-size: 1.2rem; font-weight: 600; color: var(--text-muted); }

        .printer-block { margin-bottom: 60px; }
        .p-header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--border); padding-bottom: 16px; margin-bottom: 24px; page-break-after: avoid; }
        .p-title { margin: 0; font-size: 1.6rem; font-weight: 800; color: var(--primary); }
        .p-meta { font-size: 0.9rem; font-family: 'JetBrains Mono', monospace; font-weight: 600; color: var(--text-muted); margin-top: 4px; }
        .p-date-tag { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); background: var(--bg-body); border: 1px solid var(--border); padding: 3px 10px; border-radius: 4px; margin-top: 6px; display: inline-block; }
        .p-status { font-weight: 800; font-size: 1rem; text-transform: uppercase; padding: 6px 14px; border-radius: 6px; border: 2px solid; white-space: nowrap; }

        .status-pass { color: var(--success); border-color: var(--success); background: #ecfdf5; }
        .status-fail { color: var(--error); border-color: var(--error); background: #fef2f2; }
        .status-blocked { color: var(--blocked); border-color: var(--blocked); background: #fff7ed; }
        .status-na { color: var(--na); border-color: var(--na); background: #f5f3ff; }
        .status-in-progress { color: var(--in-progress); border-color: var(--in-progress); background: #fefce8; }

        /* Global stats grid */
        .grid-overview { display: flex; gap: 30px; margin-bottom: 30px; align-items: stretch; page-break-inside: avoid; }
        .stats-box { flex: 1; border: 1px solid var(--border); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; text-align: center; }
        .stat-item { display: flex; flex-direction: column; justify-content: center; }
        .stat-val { font-size: 1.8rem; font-weight: 800; font-family: 'JetBrains Mono', monospace; line-height: 1.2; }
        .stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
        .chart-box { width: 250px; flex-shrink: 0; border: 1px solid var(--border); border-radius: 8px; padding: 10px; position: relative; display: flex; align-items: center; justify-content: center; }
        .chart-container { width: 100%; height: 180px; position: relative; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 0.85rem; border: 1px solid var(--border); }
        .data-table th, .data-table td { padding: 10px 14px; border: 1px solid var(--border); }
        .data-table th { background: var(--bg-body); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); }
        
        .section-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; page-break-after: avoid; }

        .tag { padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
        .tag.pass { background: var(--success); color: white; }
        .tag.fail { background: var(--error); color: white; }
        .tag.blocked { background: var(--blocked); color: white; }
        .tag.na { background: var(--na); color: white; }
        .tag.pending { background: var(--in-progress); color: white; }

        .bug-links { display: flex; flex-direction: column; gap: 4px; }
        .bug-link { color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.8rem; font-family: 'JetBrains Mono', monospace; }
        .bug-link:hover { text-decoration: underline; }

        @media print {
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 0; max-width: 100%; border-radius: 0; }
            .action-buttons { display: none !important; }
            .master-header { margin-bottom: 20px; padding-bottom: 10px; }
            .printer-block { margin-bottom: 40px; page-break-inside: auto; }
            .global-stats { page-break-before: always; }
            .data-table { border: 1px solid #ccc; page-break-inside: auto; }
            .data-table th, .data-table td { border: 1px solid #ccc !important; }
            .data-table tr { page-break-inside: avoid !important; page-break-after: auto; }
            .grid-overview { page-break-inside: avoid !important; align-items: stretch; }
            .stats-box, .chart-box { border-color: #ccc; }
            a[href]:after { content: none !important; }
            * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<div class="action-buttons no-print">
    <button class="btn-action back" onclick="window.history.length > 1 ? window.history.back() : window.location.href='reports.php'">
        <span class="material-symbols-outlined">arrow_back</span> Back
    </button>
    <button class="btn-action print" onclick="window.print()">
        <span class="material-symbols-outlined">print</span> Print PDF
    </button>
</div>

<div class="container">
    <div class="master-header">
        <h1>Smoke Test Report</h1>
        <p><?= htmlspecialchars($date_label) ?></p>
    </div>

    <?php foreach($printer_reports as $index => $rp): 
        $tInfo = $rp['info'];
        $displayStatus = $rp['display_status'];
        
        $safeStatus = match($displayStatus) {
            'Pass' => 'pass',
            'Fail' => 'fail',
            'Blocked' => 'blocked',
            'N/A' => 'na',
            default => 'in-progress'
        };
    ?>
    
    <div class="printer-block">
        <div class="p-header">
            <div>
                <h2 class="p-title"><?= htmlspecialchars($tInfo['model_name']) ?></h2>
                <div class="p-meta">FW: <?= htmlspecialchars($tInfo['fw_version_current']) ?> &bull; <?= htmlspecialchars($tInfo['fw_type']) ?></div>
                <?php if ($start_date !== $end_date): ?>
                    <div class="p-date-tag"><?= date('M d, Y', strtotime($tInfo['task_date'])) ?></div>
                <?php endif; ?>
            </div>
            <div class="p-status status-<?= $safeStatus ?>">
                <?= strtoupper($displayStatus) ?>
            </div>
        </div>

        <?php if (!empty($rp['issues'])): ?>
            <div class="section-title" style="color: var(--error);">
                &#9888; Found Issues
            </div>
            <table class="data-table">
                <thead><tr><th style="width: 40px;">No.</th><th>Test Title</th><th>JIRA ID</th></tr></thead>
                <tbody>
                    <?php $c=1; foreach($rp['issues'] as $iss): ?>
                        <tr>
                            <td style="color: var(--text-muted); text-align: center;"><?= $c++ ?></td>
                            <td><strong style="font-family: 'JetBrains Mono', monospace; font-size:0.75rem; color: var(--primary);">#<?= htmlspecialchars($iss['case_code']) ?></strong> <br><?= htmlspecialchars($iss['title']) ?></td>
                            <td>
                                <div class="bug-links">
                                    <?php foreach($iss['urls'] as $url): ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="bug-link"><?= htmlspecialchars(extractJiraId($url)) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="section-title">Detailed Execution Log</div>
        <table class="data-table">
            <thead><tr><th style="width: 100px;">Case ID</th><th>Test Title</th><th style="width: 150px;">Tested By</th><th style="width: 80px; text-align: center;">Status</th><th style="width: 140px;">JIRA ID</th></tr></thead>
            <tbody>
                <?php foreach($rp['cases'] as $case): 
                    $case_status = $case['status'];
                    if ($case_status !== 'Pass' && $case_status !== 'Fail' && $case_status !== 'Blocked' && $case_status !== 'N/A') {
                        $case_status = 'In Progress';
                    }
                    $status_tag = match($case_status) {
                        'Pass' => 'pass', 'Fail' => 'fail', 'Blocked' => 'blocked', 'N/A' => 'na', default => 'pending'
                    };
                    $urls = array_filter(array_map('trim', explode(',', $case['jira_url'] ?? '')));
                ?>
                    <tr>
                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">#<?= htmlspecialchars($case['case_code']) ?></td>
                        <td><?= htmlspecialchars($case['title']) ?></td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($case['tester_name'] ?? '--') ?></td>
                        <td style="text-align: center;"><span class="tag <?= $status_tag ?>"><?= strtoupper($case_status) ?></span></td>
                        <td>
                            <div class="bug-links">
                                <?php foreach($urls as $url): ?>
                                    <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="bug-link"><?= htmlspecialchars(extractJiraId($url)) ?></a>
                                <?php endforeach; ?>
                                <?php if (empty($urls)) echo "<span style='color: var(--text-muted);'>--</span>"; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>


    <div class="global-stats">
        <div class="master-header">
            <h1>Global Statistics Summary</h1>
            <p>Cycle Overview across all Models</p>
        </div>

        <div class="grid-overview">
            <div class="stats-box">
                <div class="stat-item" style="grid-column: span 2; background: var(--bg-body); border-radius: 6px; padding: 10px; margin-bottom: 10px;">
                    <span class="stat-label">Global Pass Rate</span>
                    <span class="stat-val" style="color: var(--primary); font-size: 2.5rem;"><?= $g_pass_rate ?>%</span>
                </div>
                <div class="stat-item"><span class="stat-val"><?= $g_total ?></span><span class="stat-label">Total Cases</span></div>
                <div class="stat-item"><span class="stat-val" style="color: var(--success);"><?= $g_passed ?></span><span class="stat-label">Total Passed</span></div>
                <div class="stat-item"><span class="stat-val" style="color: var(--error);"><?= $g_failed ?></span><span class="stat-label">Total Failed</span></div>
                <div class="stat-item"><span class="stat-val" style="color: var(--blocked);"><?= $g_blocked + $g_na ?></span><span class="stat-label">Blocked / N/A</span></div>
            </div>
            
            <div class="chart-box">
                <div class="chart-container">
                    <canvas id="chart_global"></canvas>
                </div>
            </div>
        </div>

        <?php if (!empty($global_issues)): ?>
            <div class="section-title" style="color: var(--error); font-size: 1.3rem; margin-top: 40px;">
                &#9888; Master Issue Registry
            </div>
            <table class="data-table">
                <thead><tr><th style="width: 40px;">No.</th><th style="width: 150px;">Printer Model</th><th>Test Title</th><th>JIRA ID</th></tr></thead>
                <tbody>
                    <?php $c=1; foreach($global_issues as $iss): ?>
                        <tr>
                            <td style="color: var(--text-muted); text-align: center;"><?= $c++ ?></td>
                            <td><strong style="color: var(--primary);"><?= htmlspecialchars($iss['printer']) ?></strong></td>
                            <td><strong style="font-family: 'JetBrains Mono', monospace; font-size:0.75rem; color: var(--text-muted);">#<?= htmlspecialchars($iss['case_code']) ?></strong> <br><?= htmlspecialchars($iss['title']) ?></td>
                            <td>
                                <div class="bug-links">
                                    <?php foreach($iss['urls'] as $url): ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="bug-link"><?= htmlspecialchars(extractJiraId($url)) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; border: 1px dashed var(--success); border-radius: 8px; color: var(--success); font-weight: 700; margin-top: 40px;">
                ✓ Excellent. No issues were logged during this test cycle.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctxGlobal = document.getElementById("chart_global");
        if(ctxGlobal) {
            new Chart(ctxGlobal, {
                type: 'pie',
                data: {
                    labels: ['Passed', 'Failed', 'Blocked', 'N/A', 'In Progress'],
                    datasets: [{
                        data: [<?= $g_passed ?>, <?= $g_failed ?>, <?= $g_blocked ?>, <?= $g_na ?>, <?= $g_pending ?>],
                        backgroundColor: ['#10b981', '#ef4444', '#f97316', '#8b5cf6', '#eab308'],
                        borderWidth: 1, borderColor: '#ffffff'
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, animation: false, 
                    plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: {size: 10, family: 'Inter'} } } } 
                }
            });
        }
    });
</script>
</body>
</html>