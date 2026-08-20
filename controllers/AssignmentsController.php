<?php
// controllers/AssignmentsController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ----- 1. Sorting & Pagination Inputs -----
$sort       = $_GET['sort'] ?? 'task_date';
$order      = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$page       = max(1, (int)($_GET['page'] ?? 1));
$reqPerPage = (int)($_GET['per_page'] ?? 25);
$perPage    = in_array($reqPerPage, [25, 50, 75, 100]) ? $reqPerPage : 25;
$offset     = ($page - 1) * $perPage;

$validSorts = [
    'task_date' => 't.task_date',
    'testing_type' => 't.testing_type',
    'model_name' => 'model_name',
    'fw_version_current' => 't.fw_version_current',
    'fw_type' => 't.fw_type',
    'overall_status' => 'overall_status'
];
$orderBySql = $validSorts[$sort] ?? 't.task_date';

// ----- 2. Filter Inputs -----
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';

// ----- 3. FIX: Read raw URL for multi-select 'type' -----
$type = [];
if (isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $rawParams);
    if (isset($rawParams['type'])) {
        $type = is_array($rawParams['type']) ? $rawParams['type'] : [$rawParams['type']];
    }
}
$type = array_filter($type);

$printers   = $_GET['printers']   ?? [];
$statuses   = $_GET['statuses']   ?? [];
$assignees  = $_GET['assignees']  ?? [];

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

if (!empty($type)) {
    $typePlaceholders = [];
    foreach ($type as $index => $val) {
        $key = "type_$index";
        $typePlaceholders[] = ":$key";
        $params[$key] = $val;
    }
    $conditions[] = "t.testing_type IN (" . implode(', ', $typePlaceholders) . ")";
}

if (!empty($printers) && is_array($printers)) {
    $in = implode(',', array_map('intval', $printers));
    if ($in) $conditions[] = "p.id IN ($in)";
}
if (!empty($statuses) && is_array($statuses)) {
    $inQuery = implode(',', array_map(function ($s) use ($pdo) {
        return $pdo->quote($s);
    }, $statuses));
    if ($inQuery) $conditions[] = "ta.overall_status IN ($inQuery)";
}
if ($user_role === 'lead' && !empty($assignees) && is_array($assignees)) {
    $in = implode(',', array_map('intval', $assignees));
    if ($in) $conditions[] = "ta.user_id IN ($in)";
}

// ----- 4. Fetch Dropdown Options -----
// FIX: Added "WHERE status = 'active'" to exclude inactive printers
$printerOpts = $pdo->query("SELECT id, model_name FROM printers WHERE status = 'active' ORDER BY model_name")->fetchAll(PDO::FETCH_KEY_PAIR);
$userOpts = $pdo->query("SELECT id, full_name FROM users WHERE role = 'tester' ORDER BY full_name")->fetchAll(PDO::FETCH_KEY_PAIR);

// FIX: Added 'Completed' to the status options so it appears in the filter drawer
$statusOpts = [
    'Pass' => 'Passed', 
    'Fail' => 'Failed', 
    'Blocked' => 'Blocked', 
    'N/A' => 'N/A', 
    'Pending' => 'In Progress',
    'Completed' => 'Completed'
];

// ----- 5. LEAD VIEW: "tasks.php" Data (REGRESSION FIX + SMOKE MULTI-PRINTER FIX) -----
$lead_tasks = [];
$lead_totalRows = 0;
$lead_totalPages = 1;

if ($user_role === 'lead') {
    $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

    // Fix: Use subquery to calculate the ACTUAL aggregate status for Regression
    $countSql = "
        SELECT COUNT(DISTINCT t.id)
        FROM task_assignments ta 
        JOIN tasks t ON ta.task_id = t.id 
        JOIN printers p ON ta.printer_id = p.id 
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $lead_totalRows = $countStmt->fetchColumn();

    $lead_sql = "
        SELECT 
            t.id as task_id, 
            t.task_date, 
            t.testing_type, 
            t.fw_version_current, 
            t.fw_version_prev,
            t.fw_version_rec,
            t.fw_type, 
            t.status,
            GROUP_CONCAT(DISTINCT p.model_name ORDER BY p.model_name SEPARATOR ', ') as model_name,
            GROUP_CONCAT(DISTINCT p.printer_path ORDER BY p.model_name SEPARATOR ',') as printer_path,
            IF(t.testing_type = 'Regression', NULL, MIN(p.id)) as printer_id,
            IF(t.testing_type = 'Regression', NULL, MAX(ta.overall_status)) as overall_status,
            GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') as assigned_to_names,
            MAX(ta.regression_url) as regression_url,
            -- REGRESSION AGGREGATION FIX: Calculate aggregate status on the fly
            IF(t.testing_type = 'Regression', 
                IF(
                    (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id) = 
                    (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id AND ta2.overall_status = 'Completed'),
                    'Completed',
                    IF(
                        (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id AND ta2.overall_status IN ('Pass', 'Fail', 'Blocked', 'N/A')) = 
                        (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id),
                        IF(
                            (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id AND ta2.overall_status = 'Pass') = 
                            (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id),
                            'Pass',
                            'Fail'
                        ),
                        'In Progress'
                    )
                ),
                MAX(ta.overall_status)
            ) as calculated_overall_status
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        LEFT JOIN users u ON ta.user_id = u.id
        $whereClause
        GROUP BY 
            t.id
        ORDER BY $orderBySql $order
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($lead_sql);
    $stmt->execute($params);
    $lead_tasks = $stmt->fetchAll();

    // Normalize the column name for the view (so tasks.php always reads 'overall_status')
    foreach ($lead_tasks as &$task) {
        $task['overall_status'] = $task['calculated_overall_status'] ?? $task['overall_status'] ?? 'Pending';
    }
    unset($task);
}

// ----- 6. TESTER VIEW: "assignments.php" Data (unchanged) -----
$my_tasks = [];
$my_totalRows = 0;
$my_totalPages = 1;

if ($user_role !== 'lead') {
    $conditions[] = "(ta.user_id = :user_id OR t.testing_type = 'Regression')";
    $params['user_id'] = $user_id;

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    $countSql = "SELECT COUNT(*) FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id JOIN printers p ON ta.printer_id = p.id $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $my_totalRows = $countStmt->fetchColumn();

    $my_sql = "
        SELECT 
            t.id, t.task_date, t.testing_type, t.fw_version_current, t.fw_type, t.status,
            p.model_name, p.printer_path, ta.printer_id, ta.designation,
            ta.overall_status, ta.regression_url
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
        ORDER BY $orderBySql $order
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($my_sql);
    $stmt->execute($params);
    $my_tasks = $stmt->fetchAll();
}
?>