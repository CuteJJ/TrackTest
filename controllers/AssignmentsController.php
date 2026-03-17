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
            (SELECT COUNT(*) FROM test_results tr WHERE tr.task_id = t.id AND tr.printer_id = p.id AND tr.status IN ('Pass', 'Fail', 'Blocked', 'N/A')) as completed_cases
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