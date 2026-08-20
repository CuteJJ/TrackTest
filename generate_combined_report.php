<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';
Helper::requireLogin();

// Get selected task IDs and printer IDs
$task_ids = $_GET['task_ids'] ?? '';
$printer_ids = $_GET['printer_ids'] ?? '';

if (empty($task_ids) || empty($printer_ids)) {
    die("No reports selected.");
}

$task_id_array = explode(',', $task_ids);
$printer_id_array = explode(',', $printer_ids);

// ==========================================================
// FIX: EXPAND 'multiple' OR 'all' INTO REAL PRINTER IDs
// ==========================================================
$expanded_task_ids = [];
$expanded_printer_ids = [];

foreach ($task_id_array as $index => $tid) {
    $pid = $printer_id_array[$index] ?? 'multiple';
    
    // If it's 'multiple' or 'all', fetch REAL printer IDs from the database
    if ($pid === 'multiple' || $pid === 'all') {
        $stmt = $pdo->prepare("SELECT printer_id FROM task_assignments WHERE task_id = ?");
        $stmt->execute([$tid]);
        $real_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($real_ids)) {
            foreach ($real_ids as $real_id) {
                $expanded_task_ids[] = $tid;
                $expanded_printer_ids[] = $real_id;
            }
        }
    } else {
        $expanded_task_ids[] = $tid;
        $expanded_printer_ids[] = $pid;
    }
}

// Overwrite the input arrays with the expanded valid IDs
$task_id_array = $expanded_task_ids;
$printer_id_array = $expanded_printer_ids;
// ==========================================================

if (count($task_id_array) !== count($printer_id_array)) {
    die("Invalid report selection.");
}

// Build the data for all selected reports
$combined_reports = [];
$global_stats = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'blocked' => 0,
    'na' => 0,
    'pending' => 0,
    'in_progress' => 0
];
$all_issues = [];
$all_cases_data = [];
$is_any_regression = false;

// ==========================================================
// FIX: DEDUPLICATE DATA (Prevent duplicate blocks in the view)
// ==========================================================
$unique_report_keys = [];

for ($i = 0; $i < count($task_id_array); $i++) {
    $task_id = $task_id_array[$i];
    $printer_id = $printer_id_array[$i];
    
    // Create a unique key to prevent duplicates
    $unique_key = $task_id . '_' . $printer_id;
    if (in_array($unique_key, $unique_report_keys)) {
        continue; // Skip this iteration because we already processed this pair
    }
    $unique_report_keys[] = $unique_key;
    
    // Fetch Task & Printer Info
    $stmt = $pdo->prepare("
        SELECT t.*, p.model_name, p.printer_path
        FROM tasks t 
        JOIN printers p ON p.id = ? 
        WHERE t.id = ?
    ");
    $stmt->execute([$printer_id, $task_id]);
    $task_info = $stmt->fetch();
    if (!$task_info) continue;
    
    // Determine if it's a regression task
    if ($task_info['testing_type'] == 'Regression') {
        $is_any_regression = true;
    }
    
    // Fetch the MANUAL overall status and the exact Completion Timestamp
    $stmt_assign = $pdo->prepare("
        SELECT overall_status, updated_at 
        FROM task_assignments 
        WHERE task_id = ? AND printer_id = ? 
        LIMIT 1
    ");
    $stmt_assign->execute([$task_id, $printer_id]);
    $assign_data = $stmt_assign->fetch(PDO::FETCH_ASSOC);
    
    $manual_status = $assign_data['overall_status'] ?? null;
    $completed_timestamp = null;
    
    // If status is Completed, grab the updated_at timestamp
    if ($manual_status === 'Completed' && !empty($assign_data['updated_at'])) {
        $completed_timestamp = $assign_data['updated_at'];
    }
    
    // Fetch the Regression URL for this task
    $stmt_url = $pdo->prepare("SELECT regression_url FROM task_assignments WHERE task_id = ? AND printer_id = ? LIMIT 1");
    $stmt_url->execute([$task_id, $printer_id]);
    $regression_url = $stmt_url->fetchColumn();
    
    // Fetch All Executed Test Cases
    $sql_cases = "
        SELECT 
            tc.case_code, 
            tc.title, 
            tr.status, 
            tr.jira_url, 
            tr.assigned_to,
            u_assign.full_name as assignee_name,
            u_update.full_name as updater_name
        FROM test_cases tc
        LEFT JOIN test_results tr ON tc.id = tr.test_case_id AND tr.task_id = ? AND tr.printer_id = ?
        LEFT JOIN users u_assign ON tr.assigned_to = u_assign.id
        LEFT JOIN users u_update ON tr.updated_by = u_update.id
        WHERE tc.printer_model = ?
        ORDER BY tc.case_code ASC
    ";
    $stmt = $pdo->prepare($sql_cases);
    $stmt->execute([$task_id, $printer_id, $task_info['model_name']]);
    $cases = $stmt->fetchAll();
    
    // Calculate Stats
    $total = count($cases);
    $passed = $failed = $blocked = $na = $pending = $in_progress = 0;
    $issues = [];
    
    foreach ($cases as $case) {
        switch ($case['status']) {
            case 'Pass': $passed++; break;
            case 'Fail': $failed++; 
                if (!empty(trim($case['jira_url']))) {
                    $issues[] = $case;
                }
                break;
            case 'Blocked': $blocked++; 
                if (!empty(trim($case['jira_url']))) {
                    $issues[] = $case;
                }
                break;
            case 'N/A': $na++; break;
            case 'In Progress': $in_progress++; break;
            default: $pending++; break;
        }
        
        // Collect all issues for global summary
        if (!empty(trim($case['jira_url'])) && in_array($case['status'], ['Fail', 'Blocked'])) {
            $all_issues[] = array_merge($case, ['model_name' => $task_info['model_name']]);
        }
    }
    
    $pass_rate = $total > 0 ? round(($passed / $total) * 100) : 0;
    
    // Update global stats
    $global_stats['total'] += $total;
    $global_stats['passed'] += $passed;
    $global_stats['failed'] += $failed;
    $global_stats['blocked'] += $blocked;
    $global_stats['na'] += $na;
    $global_stats['pending'] += $pending;
    $global_stats['in_progress'] += $in_progress;
    
    $combined_reports[] = [
        'task_info' => $task_info,
        'cases' => $cases,
        'stats' => [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'blocked' => $blocked,
            'na' => $na,
            'pending' => $pending,
            'in_progress' => $in_progress,
            'pass_rate' => $pass_rate
        ],
        'issues' => $issues,
        'regression_url' => $regression_url,
        'completed_timestamp' => $completed_timestamp
    ];
    
    // Collect all cases for CSV export
    foreach ($cases as $case) {
        $all_cases_data[] = [
            'task_id' => $task_id,
            'printer' => $task_info['model_name'],
            'task_date' => $task_info['task_date'],
            'fw_version' => $task_info['fw_version_current'],
            'case_code' => $case['case_code'],
            'title' => $case['title'],
            'status' => $case['status'] ?: 'Pending',
            'assigned_to' => $case['assignee_name'] ?? 'Unassigned',
            'jira_url' => $case['jira_url'] ?? ''
        ];
    }
}

$global_pass_rate = $global_stats['total'] > 0 ? round(($global_stats['passed'] / $global_stats['total']) * 100) : 0;

// ==========================================================
// Calculate Overall Status based on LEADER's MANUAL input
// ==========================================================
$global_badge_class = 'in-progress';
$global_badge_label = 'IN PROGRESS';

if (!empty($combined_reports)) {
    $first_report = $combined_reports[0];
    $first_task_id = $first_report['task_info']['id'];
    
    $stmt_badge = $pdo->prepare("SELECT overall_status FROM task_assignments WHERE task_id = ? LIMIT 1");
    $stmt_badge->execute([$first_task_id]);
    $primary_status = $stmt_badge->fetchColumn();
    
    if ($primary_status === 'Pass') {
        $global_badge_class = 'pass';
        $global_badge_label = 'PASSED';
    } elseif ($primary_status === 'Fail') {
        $global_badge_class = 'fail';
        $global_badge_label = 'FAILED';
    } elseif ($primary_status === 'Blocked') {
        $global_badge_class = 'blocked';
        $global_badge_label = 'BLOCKED';
    } elseif ($primary_status === 'N/A') {
        $global_badge_class = 'na';
        $global_badge_label = 'N/A';
    } elseif ($primary_status === 'Completed') {
        $global_badge_class = 'pass';
        $global_badge_label = 'COMPLETED';
    } else {
        $global_badge_class = 'in-progress';
        $global_badge_label = 'IN PROGRESS';
    }
}

// Helper to determine testing type label
$testTypeLabel = $is_any_regression ? 'Regression Test' : 'Smoke Test';

// Handle CSV export
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="combined_report_export.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fputs($output, "\xEF\xBB\xBF");
    
    // Header row
    fputcsv($output, [
        'Task ID',
        'Printer Model',
        'Task Date',
        'FW Version',
        'Case ID',
        'Test Title',
        'Status',
        'Assigned To',
        'JIRA URL'
    ]);
    
    // Data rows
    foreach ($all_cases_data as $row) {
        fputcsv($output, [
            $row['task_id'],
            $row['printer'],
            $row['task_date'],
            $row['fw_version'],
            $row['case_code'],
            $row['title'],
            $row['status'],
            $row['assigned_to'],
            $row['jira_url']
        ]);
    }
    
    fclose($output);
    exit();
}

// =========================================================================
// FIX: Replaced extractJiraId with extractJiraIds for multiple matches
// =========================================================================
function extractJiraIds($url) {
    $trimmed = trim($url);
    if (empty($trimmed)) return [];

    // 1. If the URL has a query string, decode it to prevent partial matches
    $parsed = parse_url($trimmed);
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query_params);
        $search_string = implode(' ', $query_params);
    } else {
        $search_string = $trimmed;
    }

    // 2. Use preg_match_all with \b boundaries to ensure exact matches (e.g., FIRM-XXXX)
    preg_match_all('/\bFIRM-\d+\b/', $search_string, $matches);
    
    if (!empty($matches[0])) {
        return array_unique($matches[0]);
    }
    return [];
}

$TITLE = "Testing Report | Track Manager";
require_once 'configs/header.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap');
    
    /* Reset and base */
    * { box-sizing: border-box; }
    
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
        --in-progress: #3b82f6;
    }

    /* Page container with scrolling */
    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow: hidden;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg-body);
        color: var(--text-main);
        display: flex;
        flex-direction: column;
    }
    
    /* Fixed header with actions */
    .report-header {
        flex-shrink: 0;
        background: var(--bg-surface);
        border-bottom: 2px solid var(--border);
        padding: 16px 24px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 12px;
        z-index: 100;
        position: sticky;
        top: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .btn-action { 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        padding: 10px 18px; 
        border-radius: 8px; 
        font-weight: 700; 
        font-family: 'Inter', sans-serif; 
        font-size: 0.85rem; 
        cursor: pointer; 
        border: none; 
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
    }
    
    .btn-action.back { 
        background: var(--bg-surface); 
        color: var(--text-main); 
        border: 1px solid var(--border); 
    }
    .btn-action.back:hover { 
        background: var(--bg-body); 
        border-color: var(--text-muted);
    }
    
    /* BUTTON COLORS */
    .btn-action.print { 
        background: #FF6467; 
        color: white; 
    }
    .btn-action.print:hover { 
        background: #e04548; 
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 100, 103, 0.3);
    }
    .btn-action.csv { 
        background: #31D492; 
        color: white; 
    }
    .btn-action.csv:hover { 
        background: #28b57d; 
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(49, 212, 146, 0.3);
    }
    .btn-action .material-symbols-outlined { 
        font-size: 18px; 
    }
    
    /* Scrollable content */
    .report-content {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        background: var(--bg-body);
    }
    
    .container {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Master Header */
    .master-header { 
        text-align: center; 
        border-bottom: 2px solid var(--text-main); 
        padding-bottom: 20px; 
        margin-bottom: 30px; 
        page-break-after: avoid; 
        position: relative;
    }
    
    /* Master Header Row - Flexbox for alignment */
    .master-header-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 15px;
    }
    .master-header-left {
        text-align: left;
    }
    .master-header-left h1 {
        margin: 0 0 4px 0;
        text-align: left;
        font-size: 1.8rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-main);
    }
    .master-header-left p {
        margin: 0;
        font-size: 0.95rem;
        color: var(--text-muted);
    }
    
    /* Global Badge at the top - Right aligned */
    .global-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .global-badge.pass { background: var(--success); color: white; }
    .global-badge.fail { background: var(--error); color: white; }
    .global-badge.blocked { background: var(--blocked); color: white; }
    .global-badge.in-progress { background: var(--in-progress); color: white; }
    .global-badge.na { background: var(--na); color: white; }
    .global-badge.completed { background: var(--success); color: white; }

    /* Global Stats */
    .global-stats-grid { 
        display: grid; 
        grid-template-columns: repeat(6, 1fr); 
        gap: 12px; 
        margin-bottom: 30px; 
    }
    .stat-box { 
        background: var(--bg-surface); 
        border: 1px solid var(--border); 
        border-radius: 8px; 
        padding: 16px; 
        text-align: center; 
    }
    .stat-box .stat-val { 
        font-size: 1.8rem; 
        font-weight: 800; 
        font-family: 'JetBrains Mono', monospace; 
    }
    .stat-box .stat-label { 
        font-size: 0.65rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--text-muted); 
        letter-spacing: 0.05em; 
    }
    .stat-box.total .stat-val { color: var(--text-main); }
    .stat-box.passed .stat-val { color: var(--success); }
    .stat-box.failed .stat-val { color: var(--error); }
    .stat-box.blocked .stat-val { color: var(--blocked); }
    .stat-box.na .stat-val { color: var(--na); }
    .stat-box.pass-rate .stat-val { color: var(--primary); font-size: 2.2rem; }

    /* Printer Blocks */
    .printer-block { 
        margin-bottom: 40px; 
        page-break-inside: avoid; 
        background: var(--bg-surface);
        border-radius: 12px;
        border: 1px solid var(--border);
        overflow: hidden;
    }
    .p-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 16px 20px;
        background: var(--bg-body);
        border-bottom: 2px solid var(--primary);
    }
    .p-title { 
        margin: 0; 
        font-size: 1.2rem; 
        font-weight: 800; 
        color: var(--primary); 
    }
    .p-meta { 
        font-size: 0.78rem; 
        color: var(--text-muted); 
        font-weight: 600; 
        margin-top: 2px;
    }

    /* Sub-stats per printer */
    .p-body {
        padding: 16px 20px;
    }
    
    .sub-stats { 
        display: flex; 
        gap: 20px; 
        margin-bottom: 16px; 
        flex-wrap: wrap; 
    }
    .sub-stat { 
        font-size: 0.8rem; 
        font-weight: 600; 
    }
    .sub-stat .label { 
        color: var(--text-muted); 
    }
    .sub-stat .value { 
        font-weight: 800; 
    }

    /* Data Table */
    .table-responsive {
        overflow-x: auto;
        margin-bottom: 16px;
    }
    
    .data-table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 0.82rem; 
        border: 1px solid var(--border); 
        min-width: 700px;
    }
    .data-table th, 
    .data-table td { 
        padding: 10px 14px; 
        border: 1px solid var(--border); 
        text-align: left;
    }
    /* =========================================================================
       REFINED COLUMN ALIGNMENT
       ========================================================================= */
    .data-table th:first-child { padding-left: 20px; }
    .data-table th:last-child { padding-right: 20px; }
    .data-table th { 
        background: var(--bg-body); 
        font-size: 0.7rem; 
        text-transform: uppercase; 
        color: var(--text-muted); 
        font-weight: 800; 
        letter-spacing: 0.05em; 
        position: sticky;
        top: 0;
        z-index: 5;
    }
    /* Center the Status Header */
    .data-table th.status-col {
        text-align: center;
    }
    /* Center the Status Badges */
    .data-table td.status-col {
        text-align: center;
    }

    /* =========================================================================
       FORCE JIRA URLs TO STACK VERTICALLY
       ========================================================================= */
    .bug-links { 
        display: flex; 
        flex-direction: column; 
        gap: 4px; 
    }
    .bug-link { 
        color: var(--primary); 
        text-decoration: none; 
        font-weight: 600; 
        font-size: 0.78rem; 
        font-family: 'JetBrains Mono', monospace; 
        display: block; /* Ensures each link is on its own line */
    }
    .bug-link:hover { text-decoration: underline; }

    .data-table tr:nth-child(even) { background: #fafbfc; }
    .data-table tr:hover { background: #f1f5f9; }

    .tag { 
        padding: 3px 8px; 
        border-radius: 4px; 
        font-size: 0.7rem; 
        font-weight: 700; 
        display: inline-block; 
    }
    .tag.pass { background: var(--success); color: white; }
    .tag.fail { background: var(--error); color: white; }
    .tag.blocked { background: var(--blocked); color: white; }
    .tag.na { background: var(--na); color: white; }
    .tag.in-progress { background: var(--in-progress); color: white; }
    .tag.pending { background: #94a3b8; color: white; }

    /* =========================================================================
       ISSUES SECTION - FORCED PAGE BREAK AND ANCHORING FOR PDF PRINTING
       ========================================================================= */
    .global-issues { 
        margin-top: 40px; 
        padding-top: 30px; 
        border-top: 2px solid var(--border); 
        
        /* Keep the title and the table on the exact same page */
        page-break-inside: avoid !important;
        /* Start this section on a fresh page for clean layout */
        page-break-before: always !important;
        /* Ensure the background and padding are respected */
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
    .global-issues h2 { 
        font-size: 1.2rem; 
        font-weight: 800; 
        color: var(--error); 
        margin-bottom: 16px; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
    }

    /* No issues message REMOVED - we hide it entirely instead */
    .no-issues {
        display: none !important;
    }

    /* Print styles */
    @media print {
        html, body {
            height: auto;
            overflow: visible;
        }
        .report-header {
            position: static;
            border-bottom: 2px solid #333;
        }
        .report-content {
            overflow: visible;
            padding: 20px;
        }
        .btn-action { display: none !important; }
        .data-table { border: 1px solid #ccc; }
        .data-table th, .data-table td { border: 1px solid #ccc !important; }
        .data-table tr { page-break-inside: avoid; }
        * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
        .printer-block { page-break-inside: avoid; }
        .global-stats-grid { page-break-inside: avoid; }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .global-stats-grid { 
            grid-template-columns: repeat(3, 1fr); 
        }
        .report-header {
            flex-direction: column;
            align-items: stretch;
            padding: 12px 16px;
        }
        .action-buttons {
            justify-content: flex-start;
        }
        .btn-action {
            padding: 8px 14px;
            font-size: 0.78rem;
        }
        .report-content {
            padding: 16px;
        }
        .p-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .data-table {
            font-size: 0.7rem;
            min-width: 500px;
        }
        .data-table th, .data-table td {
            padding: 6px 8px;
        }
        .sub-stats {
            gap: 10px;
        }
        .sub-stat {
            font-size: 0.7rem;
        }
    }
    
    @media (max-width: 480px) {
        .global-stats-grid { 
            grid-template-columns: repeat(2, 1fr); 
        }
        .stat-box .stat-val {
            font-size: 1.2rem;
        }
        .stat-box.pass-rate .stat-val {
            font-size: 1.5rem;
        }
    }
</style>
</head>
<body>

<!-- Fixed Header - Now only has Action Buttons -->
<div class="report-header">
    <div class="action-buttons">
        <button class="btn-action back" onclick="goBackToReports()">
            <span class="material-symbols-outlined">arrow_back</span> Back to Reports
        </button>
        <button class="btn-action csv" onclick="downloadCSV()">
            <span class="material-symbols-outlined">table_chart</span> Export CSV
        </button>
        <button class="btn-action print" onclick="window.print()">
            <span class="material-symbols-outlined">print</span> Save as PDF
        </button>
    </div>
</div>

<!-- Scrollable Content -->
<div class="report-content">
    <div class="container">

        <!-- Master Header -->
        <div class="master-header">
            <div class="master-header-row">
                <div class="master-header-left">
                    <h1>TESTING REPORT</h1>
                    <p><?= $testTypeLabel ?> generated on <?= date('F d, Y') ?></p>
                </div>
                <div class="global-badge <?= $global_badge_class ?>">
                    <?= $global_badge_label ?>
                </div>
            </div>
        </div>

        <!-- Global Statistics (Hidden if Regression) -->
        <?php if (!$is_any_regression): ?>
        <div class="global-stats-grid">
            <div class="stat-box pass-rate">
                <div class="stat-val"><?= $global_pass_rate ?>%</div>
                <div class="stat-label">Pass Rate</div>
            </div>
            <div class="stat-box total">
                <div class="stat-val"><?= $global_stats['total'] ?></div>
                <div class="stat-label">Total Cases</div>
            </div>
            <div class="stat-box passed">
                <div class="stat-val"><?= $global_stats['passed'] ?></div>
                <div class="stat-label">Passed</div>
            </div>
            <div class="stat-box failed">
                <div class="stat-val"><?= $global_stats['failed'] ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-box blocked">
                <div class="stat-val"><?= $global_stats['blocked'] + $global_stats['na'] ?></div>
                <div class="stat-label">Blocked / N/A</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= $global_stats['pending'] + $global_stats['in_progress'] ?></div>
                <div class="stat-label">Pending / In Progress</div>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($combined_reports as $report): 
            $tInfo = $report['task_info'];
            $stats = $report['stats'];
            $cases = $report['cases'];
            $issues = $report['issues'];
            $regression_url = $report['regression_url'];
            $completed_timestamp = $report['completed_timestamp'];
            
            // Determine if this specific task is Regression
            $is_regression_task = ($tInfo['testing_type'] == 'Regression');
        ?>
            <div class="printer-block">
                <div class="p-header">
                    <div>
                        <div class="p-title"><?= htmlspecialchars($tInfo['model_name']) ?></div>
                        
                        <!-- METADATA LINE 1: Removed Task #, added margin-top -->
                        <div class="p-meta" style="margin-top: 8px;">
                            FW: <?= htmlspecialchars($tInfo['fw_version_current']) ?> • 
                            <?= htmlspecialchars($tInfo['fw_type']) ?> • 
                            Date: <?= date('M d, Y', strtotime($tInfo['task_date'])) ?>
                        </div>
                        
                        <!-- METADATA LINE 2: Dates and URL -->
                        <?php if ($is_regression_task): ?>
                            <div class="p-meta" style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 15px;">
                                <!-- Date row -->
                                <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                                    <span><strong>Due Date:</strong> <?= date('M d, Y', strtotime($tInfo['due_date'])) ?></span>
                                    <?php if ($completed_timestamp): ?>
                                        <span><strong>Completed Date:</strong> <?= date('M d, Y', strtotime($completed_timestamp)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- URL row (moved below the dates) -->
                            <?php if (!empty($regression_url)): ?>
                                <div class="p-meta" style="margin-top: 2px;">
                                    <strong>TestRail Run URL:</strong> 
                                    <a href="<?= htmlspecialchars($regression_url) ?>" target="_blank" style="color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        <?= htmlspecialchars($regression_url) ?>
                                        <span class="material-symbols-outlined" style="font-size: 14px;">open_in_new</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="p-body">
                    <?php if (!$is_regression_task): ?>
                        <!-- SMOKE / NON-REGRESSION TASKS: Show stats and table -->
                        <div class="sub-stats">
                            <span class="sub-stat"><span class="label">Pass Rate:</span> <span class="value"><?= $stats['pass_rate'] ?>%</span></span>
                            <span class="sub-stat"><span class="label">Passed:</span> <span class="value" style="color:var(--success);"><?= $stats['passed'] ?></span></span>
                            <span class="sub-stat"><span class="label">Failed:</span> <span class="value" style="color:var(--error);"><?= $stats['failed'] ?></span></span>
                            <span class="sub-stat"><span class="label">Blocked:</span> <span class="value" style="color:var(--blocked);"><?= $stats['blocked'] ?></span></span>
                            <span class="sub-stat"><span class="label">N/A:</span> <span class="value" style="color:var(--na);"><?= $stats['na'] ?></span></span>
                            <span class="sub-stat"><span class="label">Total:</span> <span class="value"><?= $stats['total'] ?></span></span>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr>
                                    <th style="width:120px;">Case ID</th>
                                    <th>Test Title</th>
                                    <th class="status-col" style="width:100px;">Status</th>
                                    <th style="width:180px;">JIRA URL</th>
                                </tr></thead>
                                <tbody>
                                    <?php foreach ($cases as $case): 
                                        $case_status = $case['status'] ?: 'Pending';
                                        $status_tag = match($case_status) {
                                            'Pass' => 'pass', 
                                            'Fail' => 'fail', 
                                            'Blocked' => 'blocked', 
                                            'N/A' => 'na', 
                                            'In Progress' => 'in-progress', 
                                            default => 'pending'
                                        };
                                        // ==========================================================
                                        // FIX: USE extractJiraIds() INSTEAD OF extractJiraId()
                                        // ==========================================================
                                        $jiraIds = extractJiraIds($case['jira_url'] ?? '');
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($case['case_code']) ?></strong></td>
                                            <td><?= htmlspecialchars($case['title']) ?></td>
                                            <td class="status-col"><span class="tag <?= $status_tag ?>"><?= $case_status ?></span></td>
                                            <td>
                                                <div class="bug-links">
                                                    <?php foreach ($jiraIds as $jiraId): ?>
                                                        <a href="<?= htmlspecialchars($case['jira_url']) ?>" target="_blank" class="bug-link"><?= htmlspecialchars($jiraId) ?></a>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($jiraIds)) echo '<span style="color:var(--text-muted);">—</span>'; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- REGRESSION TASKS: Only show a simple message (no stats, no cases) -->
                        <div style="padding: 20px; background: var(--bg-body); border-radius: 8px; color: var(--text-muted); text-align: center; border: 1px solid var(--border);">
                            <span class="material-symbols-outlined" style="font-size: 24px; color: var(--primary); display: block; margin-bottom: 16px;">checklist</span>
                            <strong style="color: var(--text-main); display: block; margin-bottom: 12px;">Regression Task</strong>
                            <div style="margin-top: 12px;">
                                This regression task was executed via TestRail. Detailed test cases are not listed in this report.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Global Issues Registry (Hidden if Regression) -->
        <?php if (!$is_any_regression && !empty($all_issues)): ?>
            <div class="global-issues">
                <h2>⚠️ Issues Registry</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr>
                            <th style="width:40px;">#</th>
                            <th style="width:150px;">Printer</th>
                            <th style="width:100px;">Case ID</th>
                            <th>Title</th>
                            <th class="status-col" style="width:100px;">Status</th>
                            <th style="width:180px;">JIRA URL</th>
                        </tr></thead>
                        <tbody>
                            <?php $counter = 1; foreach ($all_issues as $issue): 
                                $jiraIds = extractJiraIds($issue['jira_url'] ?? '');
                            ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><strong><?= htmlspecialchars($issue['model_name']) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($issue['case_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($issue['title']) ?></td>
                                    <td class="status-col"><span class="tag <?= strtolower($issue['status']) ?>"><?= $issue['status'] ?></span></td>
                                    <td>
                                        <div class="bug-links">
                                            <?php foreach ($jiraIds as $jiraId): ?>
                                                <a href="<?= htmlspecialchars($issue['jira_url']) ?>" target="_blank" class="bug-link"><?= htmlspecialchars($jiraId) ?></a>
                                            <?php endforeach; ?>
                                            <?php if (empty($jiraIds)) echo '<span style="color:var(--text-muted);">—</span>'; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function downloadCSV() {
        const url = new URL(window.location.href);
        url.searchParams.set('format', 'csv');
        window.location.href = url.toString();
    }

    // === FIXED: Now reads the FULL URL saved by reports.php ===
    function goBackToReports() {
        const savedUrl = localStorage.getItem('track_reports_prev_url');
        if (savedUrl) {
            // Clear it after use so it doesn't get stuck on old filters later
            localStorage.removeItem('track_reports_prev_url');
            window.location.href = savedUrl;
        } else {
            // Fallback
            window.location.href = 'reports.php';
        }
    }
</script>
</body>
</html>