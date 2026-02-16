<?php
// controllers/DashboardController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 1. Team Members
$stmt = $pdo->query("SELECT full_name, role, last_login FROM users ORDER BY role ASC, full_name ASC");
$team_members = $stmt->fetchAll();

// 2. Firmware Overview
$firmware_overview = [];
$printers = $pdo->query("SELECT * FROM printers")->fetchAll();

foreach ($printers as $p) {
    $pid = $p['id'];
    $stmt = $pdo->prepare("SELECT t.fw_version_current FROM tasks t JOIN task_assignments ta ON t.id = ta.task_id WHERE ta.printer_id = ? AND t.fw_type = ? ORDER BY t.task_date DESC LIMIT 1");
    
    $stmt->execute([$pid, 'Branch']);
    $branch = $stmt->fetchColumn() ?: '-';
    
    $stmt->execute([$pid, 'Trunk']);
    $trunk = $stmt->fetchColumn() ?: '-';

    $firmware_overview[] = ['model' => $p['model_name'], 'branch' => $branch, 'trunk' => $trunk];
}

// 3. Chart Data
$stats_sql = "
    SELECT p.model_name,
        COUNT(CASE WHEN tr.status = 'Pass' THEN 1 END) as passed,
        COUNT(CASE WHEN tr.status = 'Fail' THEN 1 END) as failed,
        COUNT(CASE WHEN tr.status = 'Pending' THEN 1 END) as pending
    FROM test_results tr
    JOIN printers p ON tr.printer_id = p.id
    JOIN tasks t ON tr.task_id = t.id
    WHERE t.task_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.model_name
";
$chart_data = $pdo->query($stats_sql)->fetchAll();

// 4. LEAD VIEW: Active Tasks Only
$lead_tasks = [];
if ($user_role === 'lead') {
    $lead_sql = "
        SELECT 
            t.id as task_id,
            t.task_date,
            t.due_date,
            t.testing_type,
            t.fw_version_prev,
            t.fw_version_current,
            t.fw_version_rec,
            t.fw_type,
            p.id as printer_id,
            p.model_name,
            MAX(ta.overall_status) as overall_status,
            (SELECT COUNT(*) FROM test_cases tc WHERE tc.printer_model = p.model_name) as total_cases,
            (SELECT COUNT(*) FROM test_results tr WHERE tr.task_id = t.id AND tr.printer_id = p.id AND tr.status IN ('Pass', 'Fail')) as completed_cases
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        WHERE t.task_date >= CURRENT_DATE  -- FIXED: Restored original 'Active' filter
        GROUP BY t.id, p.id
        ORDER BY t.task_date ASC
    ";
    $lead_tasks = $pdo->query($lead_sql)->fetchAll();
}

// 5. TESTER VIEW: My Assignments
$my_tasks = [];
if ($user_role !== 'lead') {
    $my_sql = "
        SELECT 
            t.id,
            t.task_date, 
            t.testing_type,
            t.fw_version_current,
            t.fw_type,
            p.model_name, 
            ta.printer_id, 
            ta.designation,
            ta.overall_status,
            ta.regression_url
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        WHERE ta.user_id = ? 
        AND t.task_date >= CURRENT_DATE -- Filter for active tasks
        ORDER BY t.task_date ASC
    ";
    $stmt = $pdo->prepare($my_sql);
    $stmt->execute([$user_id]);
    $my_tasks = $stmt->fetchAll();
}
?>