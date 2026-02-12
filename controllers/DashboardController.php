<?php
// controllers/DashboardController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 1. Team Members Overview (Existing)
$stmt = $pdo->query("SELECT full_name, role, last_login FROM users ORDER BY role ASC, full_name ASC");
$team_members = $stmt->fetchAll();

// 2. Firmware Overview (Existing)
$firmware_overview = [];
$printers = $pdo->query("SELECT * FROM printers")->fetchAll();

foreach ($printers as $p) {
    $pid = $p['id'];
    
    // Helper query for latest version
    $verSql = "SELECT t.fw_version_current FROM tasks t 
               JOIN task_assignments ta ON t.id = ta.task_id 
               WHERE ta.printer_id = ? AND t.fw_type = ? 
               ORDER BY t.task_date DESC LIMIT 1";
               
    $stmt = $pdo->prepare($verSql);
    $stmt->execute([$pid, 'Branch']);
    $branch = $stmt->fetchColumn() ?: '-';
    
    $stmt->execute([$pid, 'Trunk']);
    $trunk = $stmt->fetchColumn() ?: '-';

    $firmware_overview[] = ['model' => $p['model_name'], 'branch' => $branch, 'trunk' => $trunk];
}

// 3. NEW: My To-Do List (Assignments for today or future)
// We join tasks + assignments + printers to get the full picture
$todo_sql = "
    SELECT 
        t.id, 
        ta.printer_id,
        t.task_date, 
        t.testing_type, 
        t.due_date,
        p.model_name, 
        ta.designation, 
        ta.regression_url
    FROM task_assignments ta
    JOIN tasks t ON ta.task_id = t.id
    JOIN printers p ON ta.printer_id = p.id
    WHERE ta.user_id = ? 
    AND t.task_date >= CURRENT_DATE
    ORDER BY t.task_date ASC, t.testing_type DESC
";
$stmt = $pdo->prepare($todo_sql);
$stmt->execute([$user_id]);
$my_tasks = $stmt->fetchAll();

// 4. Chart Data (Existing)
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
?>