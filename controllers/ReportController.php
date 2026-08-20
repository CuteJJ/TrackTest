<?php
// controllers/ReportController.php
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
    'model_name' => 'p.model_name',
    'fw_version_current' => 't.fw_version_current',
    'overall_status' => 'ta.overall_status'
];
$orderBySql = $validSorts[$sort] ?? 't.task_date';

// ----- 2. Filter Inputs -----
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';
$printers   = $_GET['printers']   ?? [];
$statuses   = $_GET['statuses']   ?? [];

// ----- 3. Fetch Dropdown Options -----
// FIX: Added "WHERE status = 'active'" to exclude inactive printers
$printerOpts = $pdo->query("SELECT id, model_name FROM printers WHERE status = 'active' ORDER BY model_name")->fetchAll(PDO::FETCH_KEY_PAIR);
$statusOpts = ['Pass' => 'Passed', 'Fail' => 'Failed', 'Blocked' => 'Blocked', 'N/A' => 'N/A', 'Pending' => 'Pending'];

$my_reports = []; 
$my_totalRows = 0;

// ----- 4. FETCH DATA -----
if ($user_role === 'lead' || $user_role === 'admin') {
    // PLACEHOLDER: Lead/Admin view logic will go here later
} else {
    // TESTER VIEW
    $conditions = [];
    $params = [];
    
    // STRICT RULE: Exclude Regression tests
    $conditions[] = "t.testing_type != 'Regression'";
    $conditions[] = "ta.user_id = :user_id";
    $params['user_id'] = $user_id;

    if (!empty($start_date)) { $conditions[] = "t.task_date >= :start_date"; $params['start_date'] = $start_date; }
    if (!empty($end_date))   { $conditions[] = "t.task_date <= :end_date";   $params['end_date'] = $end_date; }
    
    if (!empty($printers) && is_array($printers)) {
        $in = implode(',', array_map('intval', $printers));
        if ($in) $conditions[] = "p.id IN ($in)";
    }
    if (!empty($statuses) && is_array($statuses)) {
        $inQuery = implode(',', array_map(function($s) use ($pdo) { return $pdo->quote($s); }, $statuses));
        if ($inQuery) $conditions[] = "ta.overall_status IN ($inQuery)";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    // Count
    $countSql = "SELECT COUNT(*) FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id JOIN printers p ON ta.printer_id = p.id $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $my_totalRows = $countStmt->fetchColumn();

    // Query
    $sql = "
        SELECT 
            t.id as task_id, t.task_date, t.fw_version_current, t.fw_type,
            p.id as printer_id, p.model_name, p.printer_path, 
            ta.designation, ta.overall_status
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
        ORDER BY $orderBySql $order
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $my_reports = $stmt->fetchAll();
}
?>