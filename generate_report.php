<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';
Helper::requireLogin();

if (!isset($_GET['task_id']) || !isset($_GET['printer_id'])) {
    header("Location: reports.php");
    exit();
}

$task_id = $_GET['task_id'];
$printer_id = $_GET['printer_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 1. Fetch Task & Printer Info
$stmt = $pdo->prepare("
    SELECT t.*, p.model_name, p.printer_path
    FROM tasks t 
    JOIN printers p ON p.id = ? 
    WHERE t.id = ?
");
$stmt->execute([$printer_id, $task_id]);
$task_info = $stmt->fetch();
if (!$task_info) die("Task not found.");

// 2. Fetch User's specific designation
$stmt = $pdo->prepare("SELECT designation, overall_status FROM task_assignments WHERE task_id = ? AND printer_id = ? AND user_id = ?");
$stmt->execute([$task_id, $printer_id, $user_id]);
$my_assignment = $stmt->fetch();

$is_main_tester = ($my_assignment && $my_assignment['designation'] === 'Main');
$overall_status = $my_assignment ? $my_assignment['overall_status'] : 'Pending';

// Lead/Admin Override: They can always view it
if ($user_role === 'lead' || $user_role === 'admin') {
    $stmt = $pdo->prepare("SELECT MAX(overall_status) FROM task_assignments WHERE task_id = ? AND printer_id = ?");
    $stmt->execute([$task_id, $printer_id]);
    $overall_status = $stmt->fetchColumn() ?: 'Pending';
}

// ==========================================
// HANDLE FORM SUBMISSION (LOCKING THE TASK)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_report'])) {
    if (!$is_main_tester) {
        die("Unauthorized. Only the Main tester can finalize this report.");
    }
    
    $final_status = $_POST['final_status'];
    
    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare("UPDATE task_assignments SET overall_status = ? WHERE task_id = ? AND printer_id = ?");
        $updateStmt->execute([$final_status, $task_id, $printer_id]);
        
        $pdo->commit();
        Helper::setFlash("Task Successfully Locked with status: $final_status.", "success");
        header("Location: reports.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        Helper::setFlash("Error locking task: " . $e->getMessage(), "error");
    }
}

// 3. Fetch All Executed Test Cases 
$sql_cases = "
    SELECT tc.case_code, tc.title, tr.status, tr.jira_url, u.full_name as tester_name
    FROM test_cases tc
    LEFT JOIN test_results tr ON tc.id = tr.test_case_id AND tr.task_id = ? AND tr.printer_id = ?
    LEFT JOIN users u ON tr.updated_by = u.id
    WHERE tc.printer_model = ?
    ORDER BY tc.case_code ASC
";
$stmt = $pdo->prepare($sql_cases);
$stmt->execute([$task_id, $printer_id, $task_info['model_name']]);
$cases = $stmt->fetchAll();

// Calculate Stats for Chart and Boxes
$total = count($cases);
$passed = $failed = $blocked = $na = $pending = 0;

foreach ($cases as $c) {
    switch ($c['status']) {
        case 'Pass': $passed++; break;
        case 'Fail': $failed++; break;
        case 'Blocked': $blocked++; break;
        case 'N/A': $na++; break;
        default: $pending++; break;
    }
}

// Calculate Passing Rate Percentage
$pass_rate = $total > 0 ? round(($passed / $total) * 100) : 0;

$TITLE = "Report: " . htmlspecialchars($task_info['model_name']) . " | Track Manager";
require_once 'configs/header.php';
?>
<style>
    .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
    .printer-title-wrap { display: flex; align-items: center; gap: 16px; }
    .printer-title-text h1 { margin: 0; font-size: 1.8rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
    .printer-title-text p { margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-muted); font-family: var(--font-mono); }
    
    .info-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text-muted); background: var(--bg-surface); border: 1px solid var(--border); padding: 4px 10px; border-radius: 6px; font-weight: 600; }
    .info-badge .material-symbols-outlined { font-size: 16px; color: var(--primary); }

    /* Layout for Stats & Chart */
    .report-overview { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
    .stat-box { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
    .stat-val { font-size: 1.8rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; margin-bottom: 6px; }
    .stat-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
    
    .stat-box.s-rate .stat-val { color: var(--primary); font-size: 2.2rem; }
    .stat-box.s-pass .stat-val { color: var(--success); }
    .stat-box.s-fail .stat-val { color: var(--error); }
    .stat-box.s-block .stat-val { color: #f97316; } 
    .stat-box.s-na .stat-val { color: #8b5cf6; } 
    .stat-box.s-pending .stat-val { color: var(--text-muted); }

    .chart-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; }
    .chart-card h3 { margin: 0 0 16px 0; font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .table-container { width: 100%; overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .report-table { width: 100%; border-collapse: collapse; background: var(--bg-surface); min-width: 800px; }
    
    .report-table th, .report-table td { 
        white-space: nowrap !important; 
        overflow: visible !important; 
        text-overflow: clip !important; 
    }
    
    .report-table th { background: var(--bg-body); padding: 14px 20px; text-align: left; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
    .report-table td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: var(--text-main); }
    .report-table tr:last-child td { border-bottom: none; }
    
    /* Updated JIRA Link CSS to handle multiple URLs cleanly */
    .bug-links { display: flex; flex-direction: column; gap: 6px; }
    .bug-link { color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.8rem; word-break: break-all; display: inline-flex; align-items: flex-start; gap: 4px; line-height: 1.3; white-space: normal !important; }
    .bug-link .material-symbols-outlined { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
    .bug-link:hover { text-decoration: underline; }

    /* Finalize Box */
    .finalize-box { background: var(--bg-surface); border: 2px solid var(--primary); border-radius: 12px; padding: 24px; text-align: center; box-shadow: 0 8px 24px rgba(2,136,209,0.1); }
    .finalize-box h3 { margin: 0 0 12px 0; font-size: 1.4rem; color: var(--text-main); }
    .finalize-box p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }
    
    .lock-banner { background: var(--bg-body); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px;}
    
    @media (max-width: 900px) {
        .report-overview { grid-template-columns: 1fr; }
    }
</style>

<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="report-header">
            <div class="printer-title-wrap">
                <div style="width: 54px; height: 54px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; background: var(--bg-surface); overflow: hidden;">
                    <?= Helper::renderPrinterImage($task_info['printer_path'] ?? null, $task_info['model_name'], 32) ?>
                </div>
                <div class="printer-title-text">
                    <h1><?= htmlspecialchars($task_info['model_name']) ?></h1>
                    <div style="display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap;">
                        <span class="info-badge"><span class="material-symbols-outlined">tag</span> Task #<?= $task_info['id'] ?></span>
                        <span class="info-badge"><span class="material-symbols-outlined">calendar_today</span> <?= date('M d, Y', strtotime($task_info['task_date'])) ?></span>
                        <span class="info-badge"><span class="material-symbols-outlined">memory</span> FW: <?= htmlspecialchars($task_info['fw_version_current']) ?></span>
                        <span class="info-badge"><span class="material-symbols-outlined">account_tree</span> <?= htmlspecialchars($task_info['fw_type']) ?></span>
                    </div>
                </div>
            </div>
            <div>
                <a href="<?= ($user_role === 'lead' || $user_role === 'admin') ? 'admin/admin_history.php' : 'reports.php' ?>" class="btn ghost" style="display: flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; width: fit-content; height: 42px; padding: 0 16px; border-radius: 8px;">
    <span class="material-symbols-outlined" style="font-size: 18px; margin: 0;">arrow_back</span> Back to Reports
</a>
            </div>
        </div>

        <div class="report-overview">
            <div class="stats-grid">
                <div class="stat-box s-rate" style="grid-column: span 3; flex-direction: row; gap: 16px;">
                    <div style="display:flex; flex-direction:column; align-items:flex-start;">
                        <span class="stat-label">Pass Rate</span>
                        <span class="stat-val"><?= $pass_rate ?>%</span>
                    </div>
                </div>
                <div class="stat-box">
                    <span class="stat-val"><?= $total ?></span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-box s-pass">
                    <span class="stat-val"><?= $passed ?></span>
                    <span class="stat-label">Passed</span>
                </div>
                <div class="stat-box s-fail">
                    <span class="stat-val"><?= $failed ?></span>
                    <span class="stat-label">Failed</span>
                </div>
                <div class="stat-box s-pending">
                    <span class="stat-val"><?= $pending ?></span>
                    <span class="stat-label">Pending</span>
                </div>
                <div class="stat-box s-block">
                    <span class="stat-val"><?= $blocked ?></span>
                    <span class="stat-label">Blocked</span>
                </div>
                <div class="stat-box s-na">
                    <span class="stat-val"><?= $na ?></span>
                    <span class="stat-label">N/A</span>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>Test Results Breakdown</h3>
                <div style="flex: 1; position: relative; min-height: 200px;">
                    <canvas id="reportChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Case ID</th>
                        <th style="width: 35%;">Test Title</th>
                        <th style="width: 20%;">Tested By</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 15%;">JIRA Bug URLs</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($cases as $c): ?>
                        <tr>
                            <td class="mono" style="font-weight: 700; color: var(--primary);">#<?= htmlspecialchars($c['case_code']) ?></td>
                            <td style="font-weight: 500; white-space: normal !important; line-height: 1.4;"><?= htmlspecialchars($c['title']) ?></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= htmlspecialchars($c['tester_name'] ?? '--') ?></td>
                            <td>
                                <?php if ($c['status'] == 'Pass'): ?>
                                    <span class="badge badge-pass">PASS</span>
                                <?php elseif ($c['status'] == 'Fail'): ?>
                                    <span class="badge badge-fail">FAIL</span>
                                <?php elseif ($c['status'] == 'Blocked'): ?>
                                    <span class="badge" style="background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid #f97316;">BLOCKED</span>
                                <?php elseif ($c['status'] == 'N/A'): ?>
                                    <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid #8b5cf6;">N/A</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: normal !important;">
                                <?php 
                                // Split comma-separated URLs
                                $urls = array_filter(array_map('trim', explode(',', $c['jira_url'] ?? '')));
                                if(!empty($urls)): 
                                ?>
                                    <div class="bug-links">
                                        <?php foreach($urls as $url): ?>
                                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="bug-link">
                                                <span class="material-symbols-outlined">link</span> <?= htmlspecialchars($url) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($overall_status === 'Pending'): ?>
            <?php if ($is_main_tester): ?>
                
                <div class="finalize-box">
                    <h3>Finalize Task Result</h3>
                    <p>Submitting an overall result will securely lock this test execution. No further changes can be made.</p>
                    
                    <form method="POST" onsubmit="return confirm('WARNING: Submitting the overall result will permanently lock this task. Supporting testers will be unable to modify test case results. Proceed?');" style="display: flex; gap: 16px; justify-content: center; align-items: center;">
                        <input type="hidden" name="finalize_report" value="1">
                        
                        <div style="width: 200px; text-align: left;">
                            <?= Helper::enhancedDropdown([
                                'name' => 'final_status',
                                'placeholder' => 'Select Final Result...',
                                'multiple' => false,
                                'options' => ['Pass' => 'Overall Pass', 'Fail' => 'Overall Fail', 'Blocked' => 'Blocked', 'N/A' => 'Not Applicable'],
                                'selected' => ''
                            ]) ?>
                        </div>
                        
                        <button type="submit" class="btn" style="width: auto; height: 48px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">lock</span> Lock & Submit
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <div class="lock-banner">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: var(--text-muted);">hourglass_empty</span>
                    <strong style="color: var(--text-main); font-size: 1.1rem;">Waiting for Finalization</strong>
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Only the Main tester can sign off and lock this report.</span>
                </div>
            <?php endif; ?>

        <?php else: ?>
            
            <div class="lock-banner" style="border-color: <?= $overall_status == 'Pass' ? 'var(--success)' : ($overall_status == 'Fail' ? 'var(--error)' : 'var(--border)') ?>;">
                <span class="material-symbols-outlined" style="font-size: 32px; color: <?= $overall_status == 'Pass' ? 'var(--success)' : ($overall_status == 'Fail' ? 'var(--error)' : 'var(--text-muted)') ?>;">
                    <?= $overall_status == 'Pass' ? 'verified' : ($overall_status == 'Fail' ? 'gpp_bad' : 'lock') ?>
                </span>
                <strong style="color: var(--text-main); font-size: 1.2rem;">Task Execution Locked</strong>
                <span style="color: var(--text-muted); font-size: 0.9rem;">This test was finalized with an overall result of: <strong><?= strtoupper($overall_status) ?></strong>.</span>
            </div>

        <?php endif; ?>
        
        <div style="height: 60px;"></div>
    </div>
</div>

<script>
    // Initialize the Pie Chart
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('reportChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Passed', 'Failed', 'Blocked', 'N/A', 'Pending'],
                datasets: [{
                    data: [<?= $passed ?>, <?= $failed ?>, <?= $blocked ?>, <?= $na ?>, <?= $pending ?>],
                    backgroundColor: ['#10b981', '#ef4444', '#f97316', '#8b5cf6', '#9ca3af'],
                    borderWidth: 2,
                    borderColor: 'var(--bg-surface)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'right',
                        labels: {
                            color: 'var(--text-main)',
                            font: { family: "'Inter', sans-serif", size: 12 }
                        }
                    }
                }
            }
        });
    });
</script>
</body>
</html>