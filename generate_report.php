<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';
Helper::requireLogin();

// Check if function already exists before declaring it
if (!function_exists('extractJiraIds')) {
    function extractJiraIds($url) {
        $trimmed = trim($url);
        if (empty($trimmed)) return [];

        $parsed = parse_url($trimmed);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query_params);
            $search_string = implode(' ', $query_params);
        } else {
            $search_string = $trimmed;
        }

        preg_match_all('/\bFIRM-\d+\b/', $search_string, $matches);
        
        if (!empty($matches[0])) {
            return array_unique($matches[0]);
        }
        return [];
    }
}

$task_id = $_GET['task_id'] ?? 0;
if (!$task_id) {
    header("Location: reports.php");
    exit();
}

// --- NEW: Handle multiple printer_ids passed as comma-separated string ---
$printer_ids_input = $_GET['printer_ids'] ?? $_GET['printer_id'] ?? '';
if (strpos($printer_ids_input, ',') !== false) {
    $printer_ids = array_filter(array_map('intval', explode(',', $printer_ids_input)));
} elseif (!empty($printer_ids_input)) {
    $printer_ids = [(int)$printer_ids_input];
} else {
    // If no printer_id is provided, fetch all for this task
    $stmt = $pdo->prepare("SELECT printer_id FROM task_assignments WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $printer_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($printer_ids)) {
    die("No printers found for this task.");
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 1. Fetch Task Info (Static for the whole task)
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task_info = $stmt->fetch();
if (!$task_info) die("Task not found.");

$is_regression = ($task_info['testing_type'] == 'Regression');

// ==========================================
// HANDLE FORM SUBMISSION (Leads/Admins only)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_report'])) {
    if ($user_role !== 'lead' && $user_role !== 'admin') {
        die("Unauthorized. Only Leads/Admins can submit this report.");
    }
    
    $final_status = $_POST['final_status'];

    // CRITICAL VALIDATION: CHECK FOR PENDING CASES ACROSS ALL PRINTERS
    $placeholders = implode(',', array_fill(0, count($printer_ids), '?'));
    $stmtPending = $pdo->prepare("
        SELECT COUNT(*)
        FROM test_cases tc
        LEFT JOIN test_results tr ON tc.id = tr.test_case_id AND tr.task_id = ? AND tr.printer_id = tr.printer_id
        WHERE tc.printer_model IN (
            SELECT DISTINCT p.model_name 
            FROM task_assignments ta2 
            JOIN printers p ON ta2.printer_id = p.id 
            WHERE ta2.task_id = ?
        )
        AND (tr.status IS NULL OR tr.status = '' OR tr.status = 'Pending')
    ");
    $stmtPending->execute([$task_id, $task_id]);
    $pending_count = $stmtPending->fetchColumn();

    if ($pending_count > 0) {
        Helper::setFlash("Action Blocked: You cannot update the overall result because there are still $pending_count pending test case(s). All test cases must be executed first.", "error");
        header("Location: " . $_SERVER['PHP_SELF'] . "?task_id=$task_id&printer_ids=" . implode(',', $printer_ids));
        exit();
    }

    $pdo->beginTransaction();
    try {
        // Update ALL printers under this task
        $updateStmt = $pdo->prepare("UPDATE task_assignments SET overall_status = ? WHERE task_id = ?");
        $updateStmt->execute([$final_status, $task_id]);
        $pdo->commit();
        Helper::setFlash("Task Successfully Updated with status: $final_status.", "success");
        header("Location: " . $_SERVER['PHP_SELF'] . "?task_id=$task_id&printer_ids=" . implode(',', $printer_ids));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        Helper::setFlash("Error saving task: " . $e->getMessage(), "error");
        header("Location: " . $_SERVER['PHP_SELF'] . "?task_id=$task_id&printer_ids=" . implode(',', $printer_ids));
        exit();
    }
}

// ==========================================
// FETCH TRUE OVERALL STATUS
// ==========================================
$stmt = $pdo->prepare("
    SELECT DISTINCT p.id, p.model_name, p.printer_path, ta.regression_url, ta.overall_status
    FROM task_assignments ta
    JOIN printers p ON ta.printer_id = p.id
    WHERE ta.task_id = ?
    ORDER BY p.model_name ASC
");
$stmt->execute([$task_id]);
$assigned_printers = $stmt->fetchAll();

if (empty($assigned_printers)) {
    die("No printers assigned to this task.");
}

$status_check = [];
foreach ($assigned_printers as $ap) {
    $status_check[] = $ap['overall_status'];
}
$unique_statuses = array_unique($status_check);
$overall_status = count($unique_statuses) === 1 ? $unique_statuses[0] : 'In Progress';

// 3. Fetch Test Cases and Stats PER PRINTER (Only for Smoke)
$printer_data = [];

if (!$is_regression) {
    // Loop through the EXPLICITLY requested printer IDs
    foreach ($printer_ids as $pid) {
        $found_printer = null;
        foreach ($assigned_printers as $ap) {
            if ($ap['id'] == $pid) {
                $found_printer = $ap;
                break;
            }
        }
        if (!$found_printer) continue;

        $model_name = $found_printer['model_name'];
        
        $sql_cases = "
            SELECT 
                tc.case_code, 
                tc.title, 
                tr.status, 
                tr.jira_url, 
                tr.assigned_to,
                u_assign.full_name as assignee_name,
                u_update.full_name as updater_name,
                ? as printer_model,
                CASE 
                    WHEN tr.assigned_to IS NOT NULL AND (tr.status IS NULL OR tr.status = '' OR tr.status = 'Pending') THEN 'In Progress'
                    ELSE tr.status
                END as display_status
            FROM test_cases tc
            LEFT JOIN test_results tr ON tc.id = tr.test_case_id AND tr.task_id = ? AND tr.printer_id = ?
            LEFT JOIN users u_assign ON tr.assigned_to = u_assign.id
            LEFT JOIN users u_update ON tr.updated_by = u_update.id
            WHERE tc.printer_model = ?
            ORDER BY tc.case_code ASC
        ";
        $stmt = $pdo->prepare($sql_cases);
        $stmt->execute([$model_name, $task_id, $pid, $model_name]);
        $cases = $stmt->fetchAll();
        
        // Calculate Stats for this printer
        $passed = $failed = $blocked = $na = $pending = $in_progress = 0;
        foreach ($cases as $c) {
            switch ($c['display_status']) {
                case 'Pass': $passed++; break;
                case 'Fail': $failed++; break;
                case 'Blocked': $blocked++; break;
                case 'N/A': $na++; break;
                case 'In Progress': $in_progress++; break;
                default: $pending++; break;
            }
        }
        
        $printer_data[] = [
            'id' => $pid,
            'model_name' => $model_name,
            'printer_path' => $found_printer['printer_path'],
            'cases' => $cases,
            'stats' => [
                'passed' => $passed,
                'failed' => $failed,
                'blocked' => $blocked,
                'na' => $na,
                'pending' => $pending,
                'in_progress' => $in_progress,
                'total' => count($cases)
            ]
        ];
    }
}

// -------------------------------------------------------------
// NEW: JAVASCRIPT TO HANDLE VIEW-BACK NAVIGATION PROPERLY
// -------------------------------------------------------------
$TITLE = "Report: Task #" . $task_id . " | Track Manager";
require_once 'configs/header.php';
?>
<style>
    /* ... EXACT SAME CSS AS BEFORE ... */
    .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
    .printer-title-wrap { display: flex; align-items: center; gap: 16px; }
    .printer-title-text h1 { margin: 0; font-size: 1.8rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
    .printer-title-text p { margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-muted); font-family: var(--font-mono); }
    .info-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text-muted); background: var(--bg-surface); border: 1px solid var(--border); padding: 4px 10px; border-radius: 6px; font-weight: 600; }
    .info-badge .material-symbols-outlined { font-size: 16px; color: var(--primary); }
    .status-badge-top { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .status-badge-top.completed { background: var(--success-bg); color: var(--success); border: 1.5px solid var(--success); }
    .status-badge-top.pass { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1.5px solid #10b981; }
    .status-badge-top.fail { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1.5px solid #ef4444; }
    .status-badge-top.blocked { background: rgba(249, 115, 22, 0.15); color: #f97316; border: 1.5px solid #f97316; }
    .status-badge-top.na { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1.5px solid #8b5cf6; }
    .status-badge-top.in-progress { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1.5px solid #3b82f6; }
    .status-badge-top .material-symbols-outlined { font-size: 14px; }
    .fw-badge-top { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: rgba(2, 136, 209, 0.15); color: var(--primary); border: 1.5px solid var(--primary); }
    .fw-badge-top .material-symbols-outlined { font-size: 14px; }
    
    .regression-details-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
    .regression-detail-item { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; display: flex; flex-direction: column; gap: 2px; transition: border-color 0.2s ease; min-height: 56px; }
    .regression-detail-item:hover { border-color: var(--text-muted); }
    .regression-detail-item .label { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
    .regression-detail-item .value { font-size: 0.85rem; font-weight: 600; color: var(--text-main); word-break: break-all; line-height: 1.3; }
    .regression-detail-item .value.mono { font-family: var(--font-mono); font-size: 0.82rem; }
    .regression-detail-item .value a { color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 4px; }
    .regression-detail-item .value a:hover { text-decoration: underline; }
    .regression-detail-item .value a .material-symbols-outlined { font-size: 14px; }
    
    .regression-complete-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; background: var(--success); color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.2s ease; font-family: var(--font-body); }
    .regression-complete-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3); filter: brightness(1.05); }
    .regression-complete-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
    .regression-complete-btn .material-symbols-outlined { font-size: 18px; }

    .regression-printers-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; padding: 12px 16px; background: var(--bg-body); border-radius: 10px; border: 1px solid var(--border); }
    .regression-printer-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px 6px 10px; border-radius: 20px; background: var(--bg-surface); border: 1px solid var(--border); font-size: 0.82rem; font-weight: 600; color: var(--text-main); transition: all 0.15s ease; }
    .regression-printer-chip:hover { border-color: var(--primary); background: var(--bg-body); color: var(--primary); }
    .regression-printer-chip .chip-icon { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
    .regression-printer-chip .chip-icon img { width: 100%; height: 100%; object-fit: cover; }
    .regression-printer-chip .chip-icon .material-symbols-outlined { font-size: 14px; color: var(--primary); }
    .regression-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; display: block; margin-bottom: 8px; }

    .finalize-box { background: var(--bg-surface); border: 2px solid var(--primary); border-radius: 12px; padding: 24px; text-align: center; box-shadow: 0 8px 24px rgba(2,136,209,0.1); }
    .finalize-box h3 { margin: 0 0 12px 0; font-size: 1.4rem; color: var(--text-main); }
    .finalize-box p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }
    
    .printer-section { margin-bottom: 40px; }
    .printer-section-title { font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
    .printer-section-title .icon-wrap { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--bg-surface); }
    
    .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 20px; }
    .stat-box { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
    .stat-val { font-size: 1.8rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; margin-bottom: 6px; }
    .stat-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
    
    .stat-box.s-pass .stat-val { color: var(--success); }
    .stat-box.s-fail .stat-val { color: var(--error); }
    .stat-box.s-block .stat-val { color: #f97316; } 
    .stat-box.s-na .stat-val { color: #8b5cf6; } 
    .stat-box.s-inprogress .stat-val { color: #3b82f6; }
    .stat-box.s-pending .stat-val { color: var(--text-muted); }

    .table-container { width: 100%; overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .report-table { width: 100%; border-collapse: collapse; background: var(--bg-surface); min-width: 800px; }
    .report-table th, .report-table td { white-space: nowrap !important; overflow: visible !important; text-overflow: clip !important; }
    .report-table th { background: var(--bg-body); padding: 14px 20px; text-align: left; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
    .report-table td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: var(--text-main); }
    .report-table tr:last-child td { border-bottom: none; }
    .bug-links { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
    .bug-link { color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; line-height: 1.4; }
    .bug-link:hover { text-decoration: underline; }
    
    .result-banner { background: var(--bg-surface); border-radius: 12px; padding: 20px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; border: 2px solid var(--border); }
    .result-banner .result-icon { font-size: 36px; }
    .result-banner .result-status { font-size: 1.4rem; font-weight: 800; }
    .result-banner .result-sub { font-size: 0.9rem; color: var(--text-muted); }
    .tester-avatar { display: inline-flex; align-items: center; gap: 8px; }
    .tester-avatar .avatar-small { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
    
    @media (max-width: 900px) { 
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
        .regression-details-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) { .regression-details-grid { grid-template-columns: 1fr; } }

    .btn-back-to-reports { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; background: transparent; color: var(--text-muted); border: 1px solid var(--border); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; height: 42px; }
    .btn-back-to-reports:hover { background: var(--bg-body); color: var(--text-main); border-color: var(--text-muted); }
    .btn-back-to-reports .material-symbols-outlined { font-size: 18px; }
</style>

<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="report-header">
            <div class="printer-title-wrap">
                <div style="width: 48px; height: 48px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; background: var(--bg-surface); overflow: hidden; flex-shrink: 0;">
                    <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">assignment</span>
                </div>
                <div class="printer-title-text">
                    <h1>Task Report</h1>
                    <div style="display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; align-items: center;">
                        <span class="info-badge"><span class="material-symbols-outlined">tag</span> Task #<?= $task_info['id'] ?></span>
                        <span class="info-badge" style="background: <?= $is_regression ? 'rgba(139, 92, 246, 0.1)' : 'rgba(245, 158, 11, 0.1)' ?>; border-color: <?= $is_regression ? '#8b5cf6' : '#f59e0b' ?>; color: <?= $is_regression ? '#7c3aed' : '#d97706' ?>;">
                            <span class="material-symbols-outlined" style="color: <?= $is_regression ? '#7c3aed' : '#d97706' ?>; font-size: 16px;"><?= $is_regression ? 'checklist' : 'local_fire_department' ?></span>
                            <?= $is_regression ? 'Regression' : 'Smoke' ?>
                        </span>
                        <span class="fw-badge-top">
                            <span class="material-symbols-outlined">memory</span>
                            <?= htmlspecialchars($task_info['fw_version_current']) ?> (<?= htmlspecialchars($task_info['fw_type']) ?>)
                        </span>
                        
                        <?php 
                        $badgeClass = 'in-progress';
                        $badgeIcon = 'schedule';
                        $badgeLabel = 'In Progress';

                        if ($overall_status === 'Pass') {
                            $badgeClass = 'pass';
                            $badgeIcon = 'check_circle';
                            $badgeLabel = 'Passed';
                        } elseif ($overall_status === 'Fail') {
                            $badgeClass = 'fail';
                            $badgeIcon = 'cancel';
                            $badgeLabel = 'Failed';
                        } elseif ($overall_status === 'Blocked') {
                            $badgeClass = 'blocked';
                            $badgeIcon = 'block';
                            $badgeLabel = 'Blocked';
                        } elseif ($overall_status === 'N/A') {
                            $badgeClass = 'na';
                            $badgeIcon = 'do_not_disturb_on';
                            $badgeLabel = 'N/A';
                        } elseif ($overall_status === 'Completed') {
                            $badgeClass = 'completed';
                            $badgeIcon = 'check_circle';
                            $badgeLabel = 'Completed';
                        }
                        ?>
                        <span class="status-badge-top <?= $badgeClass ?>">
                            <span class="material-symbols-outlined"><?= $badgeIcon ?></span> <?= $badgeLabel ?>
                        </span>
                    </div>
                </div>
            </div>
            <div>
                <a href="javascript:void(0)" onclick="goBackToReports()" class="btn-back-to-reports" style="cursor: pointer;">
                    <span class="material-symbols-outlined">arrow_back</span> Back
                </a>
            </div>
        </div>

        <?php if ($is_regression): ?>
            
            <!-- ============================================== -->
            <!--     REGRESSION VIEW (MULTIPLE PRINTERS)        -->
            <!-- ============================================== -->
            <span class="regression-label">Printer(s) in this Regression Task</span>
            <div class="regression-printers-list">
                <?php 
                // Show ONLY the selected printer(s) passed in the URL
                foreach ($printer_ids as $pid): 
                    foreach ($assigned_printers as $rp):
                        if ($rp['id'] == $pid): 
                ?>
                    <span class="regression-printer-chip">
                        <span class="chip-icon">
                            <?= Helper::renderPrinterImage($rp['printer_path'] ?? null, $rp['model_name'], 14) ?>
                        </span>
                        <?= htmlspecialchars($rp['model_name']) ?>
                    </span>
                <?php 
                        endif; 
                    endforeach; 
                endforeach; 
                ?>
            </div>

            <div class="regression-details-grid">
                <div class="regression-detail-item">
                    <span class="label">Task Date</span>
                    <span class="value"><?= date('M d, Y', strtotime($task_info['task_date'])) ?></span>
                </div>
                <div class="regression-detail-item">
                    <span class="label">Due Date</span>
                    <span class="value"><?= date('M d, Y', strtotime($task_info['due_date'])) ?></span>
                </div>
                <div class="regression-detail-item">
                    <span class="label">Firmware Version</span>
                    <span class="value mono" style="color: var(--primary);"><?= htmlspecialchars($task_info['fw_version_current']) ?></span>
                </div>
                <div class="regression-detail-item">
                    <span class="label">Firmware Type</span>
                    <span class="value"><?= htmlspecialchars($task_info['fw_type']) ?></span>
                </div>
                <div class="regression-detail-item">
                    <span class="label">Recovery FW</span>
                    <span class="value mono" style="color: var(--error);"><?= htmlspecialchars($task_info['fw_version_rec']) ?></span>
                </div>
                <div class="regression-detail-item" style="grid-column: span 3;">
                    <span class="label">TestRail Run URLs</span>
                    <span class="value">
                        <?php 
                        $urls_found = false;
                        foreach ($printer_ids as $pid):
                            foreach ($assigned_printers as $rp):
                                if ($rp['id'] == $pid && !empty($rp['regression_url'])): 
                                    $urls_found = true; ?>
                                    <a href="<?= htmlspecialchars($rp['regression_url']) ?>" target="_blank" style="display: block; margin-bottom: 4px;">
                                        <?= htmlspecialchars($rp['model_name']) ?>: <?= htmlspecialchars($rp['regression_url']) ?>
                                        <span class="material-symbols-outlined">open_in_new</span>
                                    </a>
                                <?php endif; 
                            endforeach; 
                        endforeach; 
                        if (!$urls_found): ?>
                            <span style="color: var(--text-muted); font-weight: 500;">No URLs assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if ($user_role === 'lead' || $user_role === 'admin'): ?>
                <div class="finalize-box" style="border-color: <?= $overall_status === 'Completed' ? 'var(--success)' : 'var(--primary)' ?>;">
                    <h3 style="color: <?= $overall_status === 'Completed' ? 'var(--success)' : 'var(--text-main)' ?>; font-size: 1.2rem; margin-bottom: 8px;">
                        <?= $overall_status === 'Completed' ? '✅ Task Completed' : '📋 Mark as Completed' ?>
                    </h3>
                    <p style="font-size: 0.85rem; margin-bottom: 16px;">
                        <?= $overall_status === 'Completed' 
                            ? 'This regression task has been marked as completed.' 
                            : 'Once the regression test execution in TestRail is finished, mark this task as completed.' ?>
                    </p>
                    
                    <?php if ($overall_status !== 'Completed'): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to mark this regression task as COMPLETED?');">
                            <input type="hidden" name="finalize_report" value="1">
                            <input type="hidden" name="final_status" value="Completed">
                            <button type="submit" class="regression-complete-btn">
                                <span class="material-symbols-outlined">check_circle</span>
                                Mark as Completed
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                            <span style="display: inline-flex; align-items: center; gap: 6px; color: var(--success); font-weight: 700; font-size: 0.9rem;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span>
                                Completed
                            </span>
                            <form method="POST" onsubmit="return confirm('Re-open this task? This will set status back to In Progress.');" style="display: inline;">
                                <input type="hidden" name="finalize_report" value="1">
                                <input type="hidden" name="final_status" value="Pending">
                                <button type="submit" class="btn-mini ghost" style="border-color: var(--error); color: var(--error); font-size: 0.78rem;">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">refresh</span> Re-open
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="result-banner" style="border-color: <?= $overall_status === 'Completed' ? 'var(--success)' : 'var(--border)' ?>; padding: 16px;">
                    <span class="result-icon material-symbols-outlined" style="font-size: 28px; color: <?= $overall_status === 'Completed' ? 'var(--success)' : 'var(--text-muted)' ?>;">
                        <?= $overall_status === 'Completed' ? 'verified' : 'hourglass_empty' ?>
                    </span>
                    <span class="result-status" style="font-size: 1.2rem; color: <?= $overall_status === 'Completed' ? 'var(--success)' : 'var(--text-muted)' ?>;">
                        <?= strtoupper($overall_status) ?>
                    </span>
                    <span class="result-sub" style="font-size: 0.8rem;">
                        <?= $overall_status === 'Completed' 
                            ? 'This regression task has been completed.' 
                            : 'Pending completion review by Lead or Admin.' ?>
                    </span>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <!-- ============================================== -->
            <!--     SMOKE VIEW (MULTIPLE PRINTERS)             -->
            <!-- ============================================== -->
            <?php if (empty($printer_data)): ?>
                <div class="empty-state" style="padding: 40px; border: 2px dashed var(--border); border-radius: 12px; text-align: center; color: var(--text-muted);">
                    <span class="material-symbols-outlined" style="font-size: 48px; display: block; margin-bottom: 12px;">print</span>
                    <p>No data found for the selected printer(s).</p>
                </div>
            <?php else: ?>
                <?php foreach ($printer_data as $pd): ?>
                    <div class="printer-section">
                        <div class="printer-section-title">
                            <div class="icon-wrap">
                                <?= Helper::renderPrinterImage($pd['printer_path'] ?? null, $pd['model_name'], 20) ?>
                            </div>
                            <?= htmlspecialchars($pd['model_name']) ?>
                        </div>

                        <!-- Individual Stats Grid -->
                        <div class="stats-grid">
                            <div class="stat-box s-pass">
                                <span class="stat-val"><?= $pd['stats']['passed'] ?></span>
                                <span class="stat-label">Passed</span>
                            </div>
                            <div class="stat-box s-fail">
                                <span class="stat-val"><?= $pd['stats']['failed'] ?></span>
                                <span class="stat-label">Failed</span>
                            </div>
                            <div class="stat-box s-block">
                                <span class="stat-val"><?= $pd['stats']['blocked'] ?></span>
                                <span class="stat-label">Blocked</span>
                            </div>
                            <div class="stat-box s-na">
                                <span class="stat-val"><?= $pd['stats']['na'] ?></span>
                                <span class="stat-label">N/A</span>
                            </div>
                            <div class="stat-box s-inprogress">
                                <span class="stat-val"><?= $pd['stats']['in_progress'] ?></span>
                                <span class="stat-label">In Progress</span>
                            </div>
                            <div class="stat-box s-pending">
                                <span class="stat-val"><?= $pd['stats']['pending'] ?></span>
                                <span class="stat-label">Pending</span>
                            </div>
                        </div>

                        <!-- Per Printer Test Case Table -->
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th style="width: 15%;">Case ID</th>
                                        <th style="width: 35%;">Test Title</th>
                                        <th style="width: 20%;">Assigned To</th>
                                        <th style="width: 15%;">Status</th>
                                        <th style="width: 15%;">JIRA Bug IDs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pd['cases'] as $c): 
                                        $statusDisplay = $c['display_status'] ?? 'Pending';
                                        $jiraIds = extractJiraIds($c['jira_url'] ?? '');
                                    ?>
                                        <tr>
                                            <td class="mono" style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($c['case_code']) ?></td>
                                            <td style="font-weight: 500; white-space: normal !important; line-height: 1.4;">
                                                <?= htmlspecialchars($c['title']) ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($c['assigned_to'])): ?>
                                                    <div class="tester-avatar">
                                                        <?php 
                                                        $pfp_stmt = $pdo->prepare("SELECT pfp_path FROM users WHERE id = ?");
                                                        $pfp_stmt->execute([$c['assigned_to']]);
                                                        $pfp_path = $pfp_stmt->fetchColumn();
                                                        $pfp_path = !empty($pfp_path) ? $pfp_path : 'imgs/default_pfp.svg';
                                                        ?>
                                                        <img src="<?= htmlspecialchars($pfp_path) ?>" alt="<?= htmlspecialchars($c['assignee_name'] ?? '') ?>" class="avatar-small">
                                                        <span style="font-size: 0.85rem; color: var(--text-main);"><?= htmlspecialchars($c['assignee_name'] ?? 'Unknown') ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($statusDisplay == 'Pass'): ?>
                                                    <span class="badge badge-pass">PASS</span>
                                                <?php elseif ($statusDisplay == 'Fail'): ?>
                                                    <span class="badge badge-fail">FAIL</span>
                                                <?php elseif ($statusDisplay == 'Blocked'): ?>
                                                    <span class="badge" style="background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid #f97316;">BLOCKED</span>
                                                <?php elseif ($statusDisplay == 'N/A'): ?>
                                                    <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid #8b5cf6;">N/A</span>
                                                <?php elseif ($statusDisplay == 'In Progress'): ?>
                                                    <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid #3b82f6;">IN PROGRESS</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending">PENDING</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if(!empty($jiraIds)): ?>
                                                    <div class="bug-links">
                                                        <?php foreach($jiraIds as $jiraId): ?>
                                                            <a href="<?= htmlspecialchars($c['jira_url']) ?>" target="_blank" class="bug-link">
                                                                <?= htmlspecialchars($jiraId) ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">--</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- SMOKE TASK FINALIZE BOX -->
            <?php if ($user_role === 'lead' || $user_role === 'admin'): ?>
                <div class="finalize-box">
                    <h3><?= ($overall_status !== 'Pending' && $overall_status !== '') ? 'Edit Task Result' : 'Submit Task Result' ?></h3>
                    <p>Submit or update the overall result for this smoke test execution.</p>
                    
                    <form method="POST" class="no-loader" onsubmit="return confirmAction(this);" style="display: flex; gap: 16px; justify-content: center; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="finalize_report" value="1">
                        
                        <div style="width: 200px; text-align: left;">
                            <select name="final_status" style="width: 100%; padding: 14px; text-align: left; text-align-last: center; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; outline: none; cursor: pointer; appearance: none;">
                                <option value="Pass"    <?= ($overall_status == 'Pass')    ? 'selected' : '' ?>>Pass</option>
                                <option value="Fail"    <?= ($overall_status == 'Fail')    ? 'selected' : '' ?>>Fail</option>
                                <option value="Blocked" <?= ($overall_status == 'Blocked') ? 'selected' : '' ?>>Blocked</option>
                                <option value="N/A"     <?= ($overall_status == 'N/A')     ? 'selected' : '' ?>>N/A</option>
                                <option value="Pending" <?= ($overall_status == 'Pending' || $overall_status == 'In Progress') ? 'selected' : '' ?>>Pending / Re-open</option>
                            </select>
                        </div>

                        <button type="submit" class="btn" style="width: auto; height: 44px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 0 24px; font-size: 0.9rem;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">save</span> Update
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="result-banner" style="border-color: <?= $overall_status == 'Pass' ? 'var(--success)' : ($overall_status == 'Fail' ? 'var(--error)' : ($overall_status == 'Blocked' ? 'var(--blocked)' : ($overall_status == 'N/A' ? 'var(--na)' : 'var(--border)'))) ?>;">
                    <span class="result-icon material-symbols-outlined" style="font-size: 28px; color: <?= $overall_status == 'Pass' ? 'var(--success)' : ($overall_status == 'Fail' ? 'var(--error)' : ($overall_status == 'Blocked' ? 'var(--blocked)' : ($overall_status == 'N/A' ? 'var(--na)' : 'var(--text-muted)'))) ?>;">
                        <?= $overall_status == 'Pass' ? 'verified' : ($overall_status == 'Fail' ? 'gpp_bad' : ($overall_status == 'Blocked' ? 'block' : ($overall_status == 'N/A' ? 'do_not_disturb_on' : 'hourglass_empty'))) ?>
                    </span>
                    <span class="result-status" style="font-size: 1.2rem; color: <?= $overall_status == 'Pass' ? 'var(--success)' : ($overall_status == 'Fail' ? 'var(--error)' : ($overall_status == 'Blocked' ? 'var(--blocked)' : ($overall_status == 'N/A' ? 'var(--na)' : 'var(--text-muted)'))) ?>;">
                        <?= strtoupper($overall_status) ?>
                    </span>
                    <span class="result-sub" style="font-size: 0.8rem;">
                        <?php if ($overall_status === 'Pending' || $overall_status === 'In Progress'): ?>
                            Waiting for Team Leader to submit the overall result.
                        <?php else: ?>
                            Finalized by a Lead or Admin.
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <div style="height: 40px;"></div>
    </div>
</div>

<!-- FIXED JAVASCRIPT TO HANDLE BACK BUTTON WITH MULTIPLE PRINTER IDS -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const referrer = document.referrer;
        if (referrer && referrer.includes('reports.php')) {
            localStorage.setItem('track_reports_prev_url', referrer);
        }
    });

    function goBackToReports() {
        const savedUrl = localStorage.getItem('track_reports_prev_url');
        if (savedUrl) {
            window.location.href = savedUrl;
        } else {
            window.location.href = 'reports.php';
        }
    }

    function confirmAction(form) {
        if (confirm("Update the overall result for this task? Proceed?")) {
            if (typeof window.showLoader === 'function') {
                window.showLoader();
            }
            return true;
        }
        return false;
    }
</script>

</body>
</html>