<?php
// controllers/DashboardController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

// =================================================================
// AJAX HANDLER: Firmware Modal History
// =================================================================
if (isset($_GET['fetch_firmware_history']) && isset($_GET['printer_id'])) {
    header('Content-Type: application/json');
    $pid = $_GET['printer_id'];
    
    // Fetch all distinct firmwares assigned to this printer via tasks
    $stmt = $pdo->prepare("SELECT DISTINCT t.fw_version_current, t.fw_type FROM tasks t JOIN task_assignments ta ON t.id = ta.task_id WHERE ta.printer_id = ? AND t.fw_version_current != ''");
    $stmt->execute([$pid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $branch = []; $trunk = [];
    foreach ($rows as $row) {
        if ($row['fw_type'] === 'Branch') $branch[] = $row['fw_version_current'];
        if ($row['fw_type'] === 'Trunk') $trunk[] = $row['fw_version_current'];
    }

    // Sort descending using standard version numbering logic (e.g., 25.10.1 > 25.9.0)
    usort($branch, 'version_compare'); $branch = array_reverse($branch);
    usort($trunk, 'version_compare'); $trunk = array_reverse($trunk);

    echo json_encode(['success' => true, 'branch' => $branch, 'trunk' => $trunk]);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ----- Pagination Setup -----
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, (int)($_GET['per_page'] ?? 15)); // Default to 15, dynamic from UI
$offset  = ($page - 1) * $perPage;

// 1. Team Members
$stmt = $pdo->query("SELECT full_name, role, last_login, pfp_path FROM users ORDER BY role ASC, full_name ASC");
$team_members = $stmt->fetchAll();

// 2. Firmware Overview
$firmware_overview = [];
// FIX: Added "WHERE status = 'active'" to exclude inactive printers
$printers = $pdo->query("SELECT * FROM printers WHERE status = 'active'")->fetchAll();
foreach ($printers as $p) {
    $pid = $p['id'];
    $stmt = $pdo->prepare("SELECT t.fw_version_current FROM tasks t JOIN task_assignments ta ON t.id = ta.task_id WHERE ta.printer_id = ? AND t.fw_type = ? ORDER BY t.task_date DESC LIMIT 1");
    $stmt->execute([$pid, 'Branch']);
    $branch = $stmt->fetchColumn() ?: '-';
    $stmt->execute([$pid, 'Trunk']);
    $trunk = $stmt->fetchColumn() ?: '-';
    // Added printer_id for the modal click
    $firmware_overview[] = ['printer_id' => $pid, 'model' => $p['model_name'], 'printer_path' => $p['printer_path'], 'branch' => $branch, 'trunk' => $trunk];
}

// 3. Chart Data (30-Day Snapshot)
$stats_sql = "
    SELECT 
        p.model_name,
        COALESCE(SUM(tr_stats.passed), 0) as passed,
        COALESCE(SUM(tr_stats.failed), 0) as failed,
        COALESCE(SUM(tr_stats.blocked), 0) as blocked,
        COALESCE(SUM(tr_stats.na), 0) as na,
        COALESCE(SUM(
            GREATEST(0, (SELECT COUNT(*) FROM test_cases tc WHERE tc.printer_model = p.model_name) 
            - COALESCE(tr_stats.passed, 0) 
            - COALESCE(tr_stats.failed, 0)
            - COALESCE(tr_stats.blocked, 0)
            - COALESCE(tr_stats.na, 0))
        ), 0) as pending
    FROM printers p
    JOIN (SELECT printer_id, task_id FROM task_assignments GROUP BY printer_id, task_id) ta ON p.id = ta.printer_id
    JOIN tasks t ON ta.task_id = t.id
    LEFT JOIN (
        SELECT task_id, printer_id,
            SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as passed,
            SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'Blocked' THEN 1 ELSE 0 END) as blocked,
            SUM(CASE WHEN status = 'N/A' THEN 1 ELSE 0 END) as na
        FROM test_results GROUP BY task_id, printer_id
    ) tr_stats ON t.id = tr_stats.task_id AND p.id = tr_stats.printer_id
    WHERE t.task_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.id, p.model_name
";
$chart_data = $pdo->query($stats_sql)->fetchAll();


$lead_tasks = []; $lead_totalRows = 0;
$my_tasks = []; $my_totalRows = 0;

// 4. LEAD VIEW (Current Week Only) - FIXED: Group by task only, merge printers for Regression
if ($user_role === 'lead') {
    // YEARWEEK(..., 1) sets Monday as the start of the week.
    $whereClause = "WHERE YEARWEEK(t.task_date, 1) = YEARWEEK(CURDATE(), 1)";

    $lead_totalRows = $pdo->query("
        SELECT COUNT(DISTINCT t.id) FROM task_assignments ta 
        JOIN tasks t ON ta.task_id = t.id 
        $whereClause
    ")->fetchColumn();

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
            t.status,
            -- For Regression: merge all model names and paths
            IF(t.testing_type = 'Regression', GROUP_CONCAT(DISTINCT p.model_name SEPARATOR ', '), MAX(p.model_name)) as model_name,
            IF(t.testing_type = 'Regression', GROUP_CONCAT(DISTINCT p.printer_path SEPARATOR ','), MAX(p.printer_path)) as printer_path,
            -- Get the overall_status from task_assignments (first found is sufficient)
            MAX(ta.overall_status) as overall_status,
            -- Calculate total and completed cases
            (SELECT COUNT(*) FROM test_cases tc WHERE tc.printer_model = MAX(p.model_name)) as total_cases,
            (SELECT COUNT(*) FROM test_results tr WHERE tr.task_id = t.id AND tr.status IN ('Pass', 'Fail', 'Blocked', 'N/A')) as completed_cases
        FROM task_assignments ta 
        JOIN tasks t ON ta.task_id = t.id 
        JOIN printers p ON ta.printer_id = p.id
        $whereClause 
        GROUP BY t.id 
        ORDER BY t.task_date DESC 
        LIMIT $perPage OFFSET $offset
    ";
    $lead_tasks = $pdo->query($lead_sql)->fetchAll();
}

// 5. TESTER VIEW (Today Only)
if ($user_role !== 'lead') {
    $whereClause = "WHERE t.task_date = CURDATE() AND (ta.user_id = " . (int)$user_id . " OR t.testing_type = 'Regression')";

    $my_totalRows = $pdo->query("
        SELECT COUNT(*) FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id JOIN printers p ON ta.printer_id = p.id $whereClause
    ")->fetchColumn();

    $my_sql = "
        SELECT 
            t.id, t.task_date, t.testing_type, 
            t.fw_version_current, t.fw_type, t.status,
            p.model_name, p.printer_path, ta.printer_id, ta.designation,
            ta.overall_status, ta.regression_url
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause 
        ORDER BY t.task_date DESC 
        LIMIT $perPage OFFSET $offset
    ";
    $my_tasks = $pdo->query($my_sql)->fetchAll();
}

$pagination = ['currentPage' => $page, 'perPage' => $perPage, 'leadRows' => $lead_totalRows, 'myRows' => $my_totalRows];
?>