<?php
// controllers/DashboardController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ----- Filters & Pagination -----
$start_date = $_GET['start_date'] ?? date('Y-m-d');          // default today
$end_date   = $_GET['end_date']   ?? '';
$type       = $_GET['type']       ?? '';                     // '' = all, 'Smoke', 'Regression'
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 5;
$offset     = ($page - 1) * $perPage;

// Helper to build date+type conditions (used in both main and count queries)
function buildTaskFilters($start_date, $end_date, $type) {
    $conditions = [];
    $params = [];

    if (!empty($start_date)) {
        $conditions[] = "t.task_date >= :start_date";
        $params['start_date'] = $start_date;
    }
    if (!empty($end_date)) {
        $conditions[] = "t.task_date <= :end_date";
        $params['end_date'] = $end_date;
    }
    if (!empty($type) && in_array($type, ['Smoke', 'Regression'])) {
        $conditions[] = "t.testing_type = :type";
        $params['type'] = $type;
    }

    return [$conditions, $params];
}

// 1. Team Members
$stmt = $pdo->query("SELECT full_name, role, last_login, pfp_path FROM users ORDER BY role ASC, full_name ASC");
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
    $firmware_overview[] = ['model' => $p['model_name'], 'printer_path' => $p['printer_path'], 'branch' => $branch, 'trunk' => $trunk];
}

// 3. Chart Data (FIXED: Calculates Pending from Total Cases minus Pass/Fail)
$stats_sql = "
    SELECT 
        p.model_name,p.printer_path,
        COALESCE(SUM(tr_stats.passed), 0) as passed,
        COALESCE(SUM(tr_stats.failed), 0) as failed,
        COALESCE(SUM(
            GREATEST(0, (SELECT COUNT(*) FROM test_cases tc WHERE tc.printer_model = p.model_name) 
            - COALESCE(tr_stats.passed, 0) 
            - COALESCE(tr_stats.failed, 0))
        ), 0) as pending
    FROM printers p
    JOIN (
        SELECT printer_id, task_id 
        FROM task_assignments 
        GROUP BY printer_id, task_id
    ) ta ON p.id = ta.printer_id
    JOIN tasks t ON ta.task_id = t.id
    LEFT JOIN (
        SELECT task_id, printer_id,
            SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as passed,
            SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as failed
        FROM test_results
        GROUP BY task_id, printer_id
    ) tr_stats ON t.id = tr_stats.task_id AND p.id = tr_stats.printer_id
    WHERE t.task_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.id, p.model_name
";
$chart_data = $pdo->query($stats_sql)->fetchAll();

// 4. LEAD VIEW: Active Tasks (with filters & pagination)
$lead_tasks = [];
$lead_totalRows = 0;
$lead_totalPages = 1;

if ($user_role === 'lead') {
    // Build filter conditions for tasks
    [$filterConditions, $filterParams] = buildTaskFilters($start_date, $end_date, $type);
    $whereClause = empty($filterConditions) ? '' : 'WHERE ' . implode(' AND ', $filterConditions);

    // Count query (distinct task+printer combinations)
    $countSql = "
        SELECT COUNT(DISTINCT CONCAT(t.id, '-', p.id))
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($filterParams);
    $lead_totalRows = $countStmt->fetchColumn();
    $lead_totalPages = ceil($lead_totalRows / $perPage);

    // Main query with LIMIT
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
            p.printer_path,
            MAX(ta.overall_status) as overall_status,
            (SELECT COUNT(*) FROM test_cases tc WHERE tc.printer_model = p.model_name) as total_cases,
            (SELECT COUNT(*) FROM test_results tr WHERE tr.task_id = t.id AND tr.printer_id = p.id AND tr.status IN ('Pass', 'Fail')) as completed_cases
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
        GROUP BY t.id, p.id
        ORDER BY t.task_date ASC
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($lead_sql);
    $stmt->execute($filterParams);
    $lead_tasks = $stmt->fetchAll();
}

// 5. TESTER VIEW: My Assignments (with filters & pagination)
$my_tasks = [];
$my_totalRows = 0;
$my_totalPages = 1;

if ($user_role !== 'lead') {
    [$filterConditions, $filterParams] = buildTaskFilters($start_date, $end_date, $type);
    
    // Add user condition OR Regression tasks (so regression is global to testers)
    $filterConditions[] = "(ta.user_id = :user_id OR t.testing_type = 'Regression')";
    $filterParams['user_id'] = $user_id;
    $whereClause = 'WHERE ' . implode(' AND ', $filterConditions);

    // Count query
    $countSql = "
        SELECT COUNT(*)
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($filterParams);
    $my_totalRows = $countStmt->fetchColumn();
    $my_totalPages = ceil($my_totalRows / $perPage);

    // Main query with LIMIT
    $my_sql = "
        SELECT 
            t.id,
            t.task_date, 
            t.testing_type,
            t.fw_version_current,
            t.fw_type,
            p.model_name, 
            p.printer_path,
            ta.printer_id, 
            ta.designation,
            ta.overall_status,
            ta.regression_url
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
        ORDER BY t.task_date ASC
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($my_sql);
    $stmt->execute($filterParams);
    $my_tasks = $stmt->fetchAll();
}

// Pagination data for view
$pagination = [
    'currentPage' => $page,
    'perPage'     => $perPage,
    'leadRows'    => $lead_totalRows,
    'leadPages'   => $lead_totalPages,
    'myRows'      => $my_totalRows,
    'myPages'     => $my_totalPages,
];
?>