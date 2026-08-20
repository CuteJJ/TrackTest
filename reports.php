<?php
require_once 'controllers/ReportController.php';
require_once 'configs/db.php';
require_once 'configs/helper.php';

Helper::requireLogin();

// ----- 1. Sorting & Pagination Inputs -----
$sort       = $_GET['sort'] ?? 'task_date';
$order      = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$page       = max(1, (int)($_GET['page'] ?? 1));
$reqPerPage = (int)($_GET['per_page'] ?? 25);
$perPage    = in_array($reqPerPage, [25, 50, 75, 100]) ? $reqPerPage : 25;
$offset     = ($page - 1) * $perPage;

$validSorts = [
    'task_date' => 'task_date',
    'testing_type' => 'testing_type',
    'model_name' => 'model_name',
    'fw_version_current' => 'fw_version_current',
    'overall_status' => 'overall_status'
];
$orderBySql = $validSorts[$sort] ?? 'task_date';

// ----- 2. Filter Inputs -----
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';
$printers   = $_GET['printers']   ?? [];
$statuses   = $_GET['statuses']   ?? [];
// FIX: Capture the single 'testing_type' from URL
$testing_type_filter = $_GET['testing_type'] ?? '';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- FIX: Define Lead sorting variables globally so they don't cause errors ---
$lead_sort = $_GET['sort'] ?? 'task_date';
$lead_order = $_GET['order'] ?? 'desc';
$valid_lead_sorts = ['task_date', 'testing_type', 'model_name', 'fw_version_current', 'fw_type', 'overall_status'];
if (!in_array($lead_sort, $valid_lead_sorts)) { $lead_sort = 'task_date'; }
$lead_order = strtoupper($lead_order) === 'ASC' ? 'ASC' : 'DESC';
$start_date_lead = $_GET['start_date'] ?? date('Y-m-01');
$end_date_lead   = $_GET['end_date'] ?? date('Y-m-d');
if ($end_date_lead < $start_date_lead) { $end_date_lead = $start_date_lead; }
// Map sort column to actual DB column
if ($lead_sort === 'model_name') {
    $order_by = 'printer_names';
} else {
    $sort_column_map = [
        'task_date' => 't.task_date',
        'testing_type' => 't.testing_type',
        'fw_version_current' => 't.fw_version_current',
        'fw_type' => 't.fw_type',
        'overall_status' => 'overall_status'
    ];
    $order_by = $sort_column_map[$lead_sort] ?? 't.task_date';
}
// -----------------------------------------------------------------------

// Augment the reports data with Progress, Dates, and Participants
if ($user_role !== 'lead' && $user_role !== 'admin') {
    
    // --- QUERY 1: FETCH ASSIGNED TASKS ---
    $conditions = [];
    $params = [];
    
    $conditions[] = "t.testing_type != 'Regression'";
    $conditions[] = "ta.user_id = :user_id";
    $params['user_id'] = $user_id;

    if (!empty($start_date)) { $conditions[] = "t.task_date >= :start_date"; $params['start_date'] = $start_date; }
    if (!empty($end_date))   { $conditions[] = "t.task_date <= :end_date";   $params['end_date'] = $end_date; }
    
    // FIX: Added testing_type to DB query conditions
    if (!empty($testing_type_filter)) {
        $conditions[] = "t.testing_type = :testing_type";
        $params['testing_type'] = $testing_type_filter;
    }

    if (!empty($printers) && is_array($printers)) {
        $in = implode(',', array_map('intval', $printers));
        if ($in) $conditions[] = "p.id IN ($in)";
    }
    if (!empty($statuses) && is_array($statuses)) {
        $inQuery = implode(',', array_map(function($s) use ($pdo) { return $pdo->quote($s); }, $statuses));
        if ($inQuery) $conditions[] = "ta.overall_status IN ($inQuery)";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    
    // Count Assigned
    $countSql = "SELECT COUNT(*) FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id JOIN printers p ON ta.printer_id = p.id $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $assigned_totalRows = $countStmt->fetchColumn();

    // Query Assigned without sorting
    $assigned_sql = "
        SELECT 
            t.id as task_id, t.task_date, t.fw_version_current, t.fw_type,
            t.due_date, t.fw_version_prev, t.fw_version_rec, t.status AS task_status,
            t.testing_type,
            p.id as printer_id, p.model_name, p.printer_path, 
            ta.designation, ta.overall_status
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
    ";
    $stmt = $pdo->prepare($assigned_sql);
    $stmt->execute($params);
    $assigned_reports = $stmt->fetchAll();

    // --- QUERY 2: FETCH UNASSIGNED TASKS ---
    $unassigned_conditions = [];
    $unassigned_params = [];

    $unassigned_conditions[] = "t.testing_type = 'Smoke'";
    $unassigned_conditions[] = "t.status IS NULL OR t.status != 'Deleted'";
    $unassigned_conditions[] = "NOT EXISTS (SELECT 1 FROM task_assignments ta3 WHERE ta3.task_id = t.id AND ta3.printer_id = p.id AND ta3.user_id = :user_id)";
    $unassigned_params['user_id'] = $user_id;

    if (!empty($start_date)) { $unassigned_conditions[] = "t.task_date >= :start_date"; $unassigned_params['start_date'] = $start_date; }
    if (!empty($end_date))   { $unassigned_conditions[] = "t.task_date <= :end_date";   $unassigned_params['end_date'] = $end_date; }
    if (!empty($testing_type_filter)) {
        $unassigned_conditions[] = "t.testing_type = :testing_type";
        $unassigned_params['testing_type'] = $testing_type_filter;
    }
    if (!empty($printers) && is_array($printers)) {
        $in = implode(',', array_map('intval', $printers));
        if ($in) $unassigned_conditions[] = "p.id IN ($in)";
    }
    if (!empty($statuses) && is_array($statuses)) {
        $inQuery = implode(',', array_map(function($s) use ($pdo) { return $pdo->quote($s); }, $statuses));
        if ($inQuery) $unassigned_conditions[] = "ta2.overall_status IN ($inQuery)";
    }

    $unassigned_whereClause = 'WHERE ' . implode(' AND ', $unassigned_conditions);

    // Count Unassigned
    $countUnassignedSql = "
        SELECT COUNT(DISTINCT CONCAT(t.id, '-', p.id)) 
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id
        JOIN printers p ON ta.printer_id = p.id
        LEFT JOIN task_assignments ta2 ON t.id = ta2.task_id AND p.id = ta2.printer_id
        $unassigned_whereClause
    ";
    $countUnassignedStmt = $pdo->prepare($countUnassignedSql);
    $countUnassignedStmt->execute($unassigned_params);
    $unassigned_totalRows = $countUnassignedStmt->fetchColumn();

    // Query Unassigned without sorting
    $unassigned_sql = "
        SELECT DISTINCT t.id as task_id, t.task_date, t.fw_version_current, t.fw_type,
               t.due_date, t.fw_version_prev, t.fw_version_rec, t.status AS task_status,
               t.testing_type,
               p.id as printer_id, p.model_name, p.printer_path, 
               ta2.overall_status
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id
        JOIN printers p ON ta.printer_id = p.id
        LEFT JOIN task_assignments ta2 ON t.id = ta2.task_id AND p.id = ta2.printer_id
        $unassigned_whereClause
    ";
    $stmtUnassigned = $pdo->prepare($unassigned_sql);
    $stmtUnassigned->execute($unassigned_params);
    $unassigned_reports = $stmtUnassigned->fetchAll();

    // --- MERGE DATA ---
    $my_reports = array_merge($assigned_reports, $unassigned_reports);
    $my_totalRows = $assigned_totalRows + $unassigned_totalRows;

    // Augment assigned rows
    foreach ($my_reports as &$row) {
        $row['is_assigned'] = true;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_cases WHERE printer_model = ?");
        $stmt->execute([$row['model_name']]);
        $total_cases = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_results WHERE task_id = ? AND printer_id = ? AND status IN ('Pass', 'Fail', 'Blocked', 'N/A')");
        $stmt->execute([$row['task_id'], $row['printer_id']]);
        $completed_cases = $stmt->fetchColumn();
        
        $row['total_cases'] = $total_cases;
        $row['completed_cases'] = min($completed_cases, $total_cases);
        $progress_raw = $total_cases > 0 ? round(($row['completed_cases'] / $total_cases) * 100) : 0;
        $row['progress'] = min(100, $progress_raw);
        
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.pfp_path, ta.designation 
            FROM task_assignments ta 
            JOIN users u ON ta.user_id = u.id 
            WHERE ta.task_id = ? AND ta.printer_id = ? 
            ORDER BY ta.designation ASC
        ");
        $stmt->execute([$row['task_id'], $row['printer_id']]);
        $row['participants'] = $stmt->fetchAll();
    }
    unset($row);

    // Augment unassigned rows
    foreach ($unassigned_reports as $ut) {
        foreach ($my_reports as &$row) {
            if ($row['task_id'] == $ut['task_id'] && $row['printer_id'] == $ut['printer_id']) {
                $row['is_assigned'] = false;
                $row['designation'] = null;
                
                $total = $row['total_cases'];
                $completed = $row['completed_cases'];
                $row['progress'] = $total > 0 ? min(100, round(($completed / $total) * 100)) : 0;
                $row['is_completed'] = ($row['overall_status'] == 'Pass' || $row['overall_status'] == 'Fail' || $row['overall_status'] == 'Blocked' || $row['overall_status'] == 'N/A');
                
                $stmtSub = $pdo->prepare("SELECT u.full_name, u.pfp_path, ta.designation FROM task_assignments ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = ? AND ta.printer_id = ? ORDER BY ta.designation ASC");
                $stmtSub->execute([$ut['task_id'], $ut['printer_id']]);
                $row['participants'] = $stmtSub->fetchAll();
                break;
            }
        }
    }
    unset($row);

    // --- FIX: CUSTOM SORT LOGIC ---
    usort($my_reports, function($a, $b) use ($sort, $order) {
        $valA = $a[$sort] ?? '';
        $valB = $b[$sort] ?? '';

        // Handle null/empty values
        if ($valA === null || $valA === '') $valA = 'zzzzz';
        if ($valB === null || $valB === '') $valB = 'zzzzz';

        // --- IF SORTING BY STATUS, USE CUSTOM PRIORITY ---
        if ($sort === 'overall_status') {
            $priority = [
                'Fail'    => 1,
                'In Progress' => 2,
                'Pass'    => 3,
                'Blocked' => 4,
                'N/A'     => 5,
                'Pending' => 6,
                'Completed' => 7
            ];
            $prioA = $priority[$valA] ?? 99;
            $prioB = $priority[$valB] ?? 99;

            if ($prioA == $prioB) return 0;
            return ($order === 'asc') ? ($prioA < $prioB ? -1 : 1) : ($prioA > $prioB ? -1 : 1);
        }

        // --- REGULAR SORTING FOR OTHER COLUMNS ---
        if ($valA == $valB) return 0;
        return ($order === 'asc') ? (strcasecmp($valA, $valB)) : (strcasecmp($valB, $valA));
    });

    // Apply pagination offset and limit manually after sorting
    $my_reports = array_slice($my_reports, $offset, $perPage);

} else {
    // Lead/Admin view logic - FULLY MERGED FOR BOTH SMOKE AND REGRESSION
    
    // --- FIXED SQL: Group by task_id for BOTH Smoke and Regression, calculating progress correctly for both ---
    $stmt = $pdo->prepare("
        SELECT 
            t.id as task_id, 
            t.task_date, 
            t.testing_type, 
            t.fw_version_current, 
            t.fw_type, 
            t.status as task_status,
            GROUP_CONCAT(DISTINCT p.id ORDER BY p.model_name SEPARATOR ',') as printer_ids,
            GROUP_CONCAT(DISTINCT p.model_name ORDER BY p.model_name SEPARATOR ', ') as printer_names,
            GROUP_CONCAT(DISTINCT p.printer_path ORDER BY p.model_name SEPARATOR ',') as printer_paths,
            -- Calculate aggregate status for BOTH Smoke and Regression
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
            ) as calculated_overall_status,
            -- Calculate progress for BOTH Smoke and Regression
            IF(t.testing_type = 'Regression',
                IF(
                    (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id) = 
                    (SELECT COUNT(*) FROM task_assignments ta2 WHERE ta2.task_id = t.id AND ta2.overall_status = 'Completed'),
                    100,
                    0
                ),
                -- SMOKE progress calculation based on test cases
                (
                    SELECT SUM(
                        CASE WHEN tr.status IN ('Pass', 'Fail', 'Blocked', 'N/A') THEN 1 ELSE 0 END
                    ) / COUNT(tc.id) * 100
                    FROM test_cases tc
                    LEFT JOIN test_results tr ON tc.id = tr.test_case_id AND tr.task_id = t.id AND tr.printer_id = p.id
                    WHERE tc.printer_model = p.model_name
                )
            ) as calculated_progress
        FROM tasks t 
        JOIN task_assignments ta ON t.id = ta.task_id 
        JOIN printers p ON ta.printer_id = p.id 
        WHERE t.task_date BETWEEN ? AND ? 
          AND t.testing_type IN ('Smoke', 'Regression')
          AND (t.status IS NULL OR t.status != 'Deleted')
        GROUP BY t.id, t.task_date, t.testing_type, t.fw_version_current, t.fw_type, t.status
        ORDER BY $order_by $lead_order
    ");
    $stmt->execute([$start_date_lead, $end_date_lead]); 
    $lead_reports = $stmt->fetchAll();
    
    // Normalize data for the view
    foreach ($lead_reports as &$lr) {
        // Set status from the calculated column
        $lr['overall_status'] = $lr['calculated_overall_status'] ?? 'Pending';
        $lr['is_completed'] = ($lr['overall_status'] == 'Completed');
        
        // Set progress from the calculated column
        $lr['progress'] = round($lr['calculated_progress'] ?? 0);
        
        // Extract merged printer info
        $lr['model_name'] = $lr['printer_names'];
        $lr['printer_path'] = $lr['printer_paths'];
        $lr['printer_id'] = 'multiple';
    }
    unset($lr);
    
    $my_reports = $lead_reports;
    $my_totalRows = count($my_reports);
    $perPage = 25;
    $page = 1;
}

function isInProgress($status) { 
    return $status !== 'Pass' && $status !== 'Fail' && $status !== 'Blocked' && $status !== 'N/A' && $status !== 'Completed'; 
}
function getPrinterIcon(string $name): string { $n = strtolower($name); if (str_contains($n, 'flare')) return 'local_fire_department'; if (str_contains($n, 'ray')) return 'bolt'; if (str_contains($n, 'mfp')) return 'content_copy'; if (str_contains($n, 'sfp')) return 'print'; return 'print'; }
function renderPrinterImage($path, $name) { 
    if (!empty($path) && (str_contains($path, '/') || str_contains($path, '.'))) { 
        $displayPath = str_starts_with($path, 'http') ? $path : $path; 
        return "<img src='".htmlspecialchars($displayPath)."?v=".time()."' style='width:100%; height:100%; object-fit:cover; border-radius:50%;'>"; 
    } 
    $iconText = $path ?: getPrinterIcon($name); 
    return "<span class='material-symbols-outlined' style='font-size: 14px; color: var(--primary);'>".htmlspecialchars($iconText)."</span>"; 
}

// Build sort URL helper function
function buildSortUrl($column, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $newOrder;
    return '?' . http_build_query($params);
}

function getSortIcon($column, $currentSort, $currentOrder) {
    if ($currentSort !== $column) {
        return '↕';
    }
    return $currentOrder === 'asc' ? '↑' : '↓';
}

$TITLE = "Reports | Track Manager";
require_once 'configs/header.php';
?>
<style>
    .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { margin: 0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
    .unified-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--border-radius); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02); display: flex; flex-direction: column; margin-bottom: 30px; }
    .table-controls { padding: 8px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: flex-end; align-items: center; gap: 12px; background: var(--bg-surface); border-radius: var(--border-radius) var(--border-radius) 0 0; }
    .table-controls-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .table-controls-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    
    .btn-control { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; background: var(--bg-body); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; white-space: nowrap; }
    .btn-control:hover { background: var(--border); color: var(--text-main); }
    .btn-control.ghost { background: transparent; color: var(--text-muted); border-color: transparent; }
    .btn-control.ghost:hover { background: var(--error-bg); color: var(--error); }
    .btn-control .material-symbols-outlined { font-size: 18px; }
    .btn-control.primary { background: var(--primary); color: white; border-color: var(--primary); }
    .btn-control.primary:hover { background: var(--primary-hover); }
    .btn-control.primary:disabled { opacity: 0.5; cursor: not-allowed; }
    
    /* NEW CANCEL BUTTON COLOR - #FF6467 */
    .btn-control.cancel { 
        background: #FF6467 !important; 
        color: white !important; 
        border-color: #FF6467 !important; 
    }
    .btn-control.cancel:hover { 
        background: #e04548 !important; 
        border-color: #e04548 !important; 
    }

    .btn-control.select-toggle { 
        background: var(--bg-body); 
        color: var(--text-muted); 
        border-color: var(--border);
        min-width: 90px;
        justify-content: center;
    }
    .btn-control.select-toggle.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .btn-control.select-toggle.active:hover {
        background: var(--primary-hover);
    }
    .table-section { overflow-x: auto; width: 100%; border-radius: 0 0 calc(var(--border-radius) - 1px) calc(var(--border-radius) - 1px); }
    .d-table { width: 100%; min-width: 900px; border-collapse: collapse; border-radius: 8px; overflow: hidden; table-layout: fixed; }
    .d-table th, .d-table td { white-space: nowrap !important; overflow: visible !important; text-overflow: clip !important; }
    
    .checkbox-col {
        display: none;
        width: 40px !important;
        min-width: 40px !important;
        max-width: 40px !important;
        text-align: center;
        padding: 8px 8px !important;
    }
    .checkbox-col.show {
        display: table-cell;
    }
    /* REFINED CHECKBOX UI */
    .checkbox-col input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
        display: block;
        margin: 0 auto;
        transform: scale(1.1);
    }

    .d-table th {
        background: #f8f9fa;
        padding: 12px 16px;
        text-align: left;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .d-table th:first-child { padding-left: 20px; }
    .d-table th:last-child { padding-right: 20px; }
    .d-table th:hover { background: #e9ecef; border-bottom-color: #adb5bd; }
    .d-table th a { color: #495057; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; width: 100%; transition: color 0.2s ease; }
    .d-table th a:hover { color: #212529; }
    .d-table th .sort-icon { display: inline-block; font-size: 0.65rem; opacity: 0.4; transition: all 0.2s ease; }
    .d-table th:hover .sort-icon { opacity: 1; }
    .d-table th .sort-active { color: var(--primary) !important; font-weight: 900 !important; }
    .d-table th .sort-active .sort-icon { opacity: 1 !important; color: var(--primary); }
    
    [data-theme="dark"] .d-table th,
    [data-theme="midnight"] .d-table th,
    [data-theme="catppuccin"] .d-table th {
        background: #2d3748;
        color: #e2e8f0;
        border-bottom: 2px solid #4a5568;
    }
    [data-theme="dark"] .d-table th:hover,
    [data-theme="midnight"] .d-table th:hover,
    [data-theme="catppuccin"] .d-table th:hover {
        background: #374151;
        border-bottom-color: #6b7280;
    }
    [data-theme="dark"] .d-table th a,
    [data-theme="midnight"] .d-table th a,
    [data-theme="catppuccin"] .d-table th a {
        color: #e2e8f0;
    }
    [data-theme="dark"] .d-table th a:hover,
    [data-theme="midnight"] .d-table th a:hover,
    [data-theme="catppuccin"] .d-table th a:hover {
        color: #ffffff;
    }
    
    .d-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
        font-size: 0.85rem;
        color: var(--text-main);
        background: var(--bg-surface);
        transition: background 0.15s ease;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .d-table tr:last-child td { border-bottom: none; }
    .d-table tbody tr:hover td { background: var(--bg-body); }
    
    [data-theme="dark"] .d-table td,
    [data-theme="midnight"] .d-table td,
    [data-theme="catppuccin"] .d-table td {
        background: var(--bg-surface);
        border-bottom-color: var(--border);
    }
    [data-theme="dark"] .d-table tbody tr:hover td,
    [data-theme="midnight"] .d-table tbody tr:hover td,
    [data-theme="catppuccin"] .d-table tbody tr:hover td {
        background: var(--bg-body);
    }
    
    .main-row { cursor: pointer; transition: background 0.15s; }
    .main-row:hover { background: var(--bg-body); }
    .main-row.is-open { background: var(--bg-body); }
    .main-row.row-unassigned { /* Opacity removed - now matches regular rows */ }
    .main-row.row-unassigned:hover { /* Opacity removed - now matches regular rows */ }
    .progress-wrap { width: 100%; max-width: 140px; }
    .progress-labels { display: flex; justify-content: space-between; font-size: 0.6rem; font-weight: 800; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 3px; }
    .progress-bar-bg { width: 100%; height: 5px; background: var(--border); border-radius: 4px; overflow: hidden; }
    .progress-bar-fill { height: 100%; background: var(--primary); transition: width 0.4s cubic-bezier(0.4,0,0.2,1); }
    .btn-disabled { background: var(--bg-body); color: var(--text-muted); border: 1px solid var(--border); cursor: not-allowed; opacity: 0.7; }
    .expanded-row td { padding: 0 !important; border-bottom: none !important; }
    .accordion-wrapper { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .accordion-wrapper.open { grid-template-rows: 1fr; }
    .expanded-content { overflow: hidden; }
    .accordion-content { padding: 16px 20px; background: var(--bg-body); border-bottom: 1px solid var(--border); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
    .acc-grid { display: flex; flex-wrap: wrap; gap: 24px; align-items: center; }
    .acc-item { display: flex; flex-direction: column; gap: 4px; }
    .acc-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
    .acc-value { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }
    .participant-list { display: flex; gap: 6px; overflow-x: auto; max-width: 280px; padding-bottom: 4px; align-items: center; }
    .participant-list::-webkit-scrollbar { height: 4px; }
    .participant-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
    .participant-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); flex-shrink: 0; background: var(--bg-surface); cursor: help; }
    .participant-main { border: 2px solid var(--primary); padding: 2px; }
    
    .report-checkbox { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
    .report-checkbox:disabled { opacity: 0.4; cursor: not-allowed; }
    
    .drawer-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
    .drawer-overlay.show { opacity: 1; visibility: visible; }
    .filter-drawer { position: fixed; top: 0; right: -400px; width: 100%; max-width: 360px; height: 100vh; background: var(--bg-surface); z-index: 9999; box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-left: 1px solid var(--border); }
    .filter-drawer.open { right: 0; }
    .drawer-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .drawer-header h3 { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    .drawer-header h3 .material-symbols-outlined { color: var(--primary); }
    .drawer-body { padding: 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 24px; }
    .filter-group { display: flex; flex-direction: column; margin-bottom: 0; }
    .filter-group label { font-size: 0.68rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.05em; }
    .drawer-body .enh-trigger { height: auto !important; min-height: var(--input-height) !important; padding: 6px 14px !important; }
    .drawer-body .enh-trigger-content { flex-wrap: wrap !important; overflow-x: visible !important; margin: 4px 0 !important; }
    .drawer-body .enh-chip { white-space: normal !important; height: auto; line-height: 1.3; }
    /* FIX: Allow status dropdown to scroll correctly */
    .enh-menu { max-height: 250px; overflow-y: auto !important; }
    .modal-filter-reset-btn { background: transparent; border: none; color: var(--text-muted); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; margin-left: auto; margin-right: 4px; }
    .modal-filter-reset-btn:hover { color: var(--primary); }
    .modal-filter-reset-btn .material-symbols-outlined { font-size: 20px; }
    .badge-not-assigned { background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border); font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; }
    .badge-not-assigned .material-symbols-outlined { font-size: 12px; }
    
    .filter-toggle-group { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--border); margin-bottom: 0; }
    .filter-toggle-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    .filter-toggle-label .material-symbols-outlined { font-size: 20px; color: var(--text-muted); }
    .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border); transition: .3s; border-radius: 24px; }
    .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
    .toggle-switch input:checked + .toggle-slider { background-color: var(--primary); }
    .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

    .lead-date-box { display: flex; align-items: center; background: var(--bg-body); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; transition: border-color 0.2s ease, box-shadow 0.2s ease; flex-shrink: 0; }
    .lead-date-box.is-invalid { border-color: var(--error); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12); }
    .lead-date-box input[type="date"] { flex: 1; min-width: 0; border: none !important; background: transparent !important; box-shadow: none !important; height: 36px; font-size: 0.85rem; padding: 0 12px; color: var(--text-main); font-weight: 600; outline: none; cursor: pointer; }
    .lead-date-box input[type="date"]:focus { box-shadow: none !important; }
    .lead-date-box .date-range-sep { color: var(--text-muted); font-size: 0.85rem; font-weight: 700; padding: 0 6px; flex-shrink: 0; user-select: none; }
    [data-theme="dark"] .lead-date-box input[type="date"]::-webkit-calendar-picker-indicator, [data-theme="midnight"] .lead-date-box input[type="date"]::-webkit-calendar-picker-indicator, [data-theme="catppuccin"] .lead-date-box input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }
    
    .lead-controls-row {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 10px 20px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-body);
    }
    .lead-controls-row .spacer {
        flex: 1;
    }
    .lead-controls-row .btn-group {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }

    .date-flex { display: flex; gap: 8px; align-items: flex-start; flex-direction: column; }
    .date-inputs-row { display: flex; gap: 8px; width: 100%; }
    .date-flex input[type="date"] { flex: 1; height: var(--input-height); padding: 0 12px; width: 100%; border: 1px solid var(--border); border-radius: var(--border-radius); background: var(--bg-body); color: var(--text-main); font-size: 0.85rem; outline: none; transition: all 0.2s; }
    .date-flex input[type="date"]:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.15); }
    .date-flex input[type="date"].date-error { border-color: var(--error) !important; background: var(--error-bg); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
    .date-flex input[type="date"].date-error:focus { border-color: var(--error) !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
    .date-error-msg { display: flex; align-items: center; gap: 5px; color: var(--error); font-size: 0.75rem; font-weight: 500; margin-top: 8px; padding: 8px 10px; background: var(--error-bg); border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); animation: dateErrorSlide 0.25s ease-out; }
    .date-error-msg .material-symbols-outlined { font-size: 16px; flex-shrink: 0; }
    @keyframes dateErrorSlide { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    .date-flex input[type="date"].date-valid { border-color: #22c55e; }
    .date-valid-msg { display: flex; align-items: center; gap: 5px; color: #22c55e; font-size: 0.75rem; font-weight: 500; margin-top: 8px; padding: 8px 10px; background: rgba(34, 197, 94, 0.08); border-radius: 6px; border: 1px solid rgba(34, 197, 94, 0.2); animation: dateErrorSlide 0.25s ease-out; }
    .date-valid-msg .material-symbols-outlined { font-size: 16px; flex-shrink: 0; }
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator, [data-theme="midnight"] input[type="date"]::-webkit-calendar-picker-indicator, [data-theme="catppuccin"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }
    .btn-view-report { display: inline-flex; align-items: center; gap: 4px; height: 30px; padding: 0 12px; background: transparent; border: 1px solid var(--border); border-radius: 6px; color: var(--primary); font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; white-space: nowrap; }
    .btn-view-report:hover { background: rgba(2, 136, 209, 0.08); border-color: var(--primary); }
    .btn-view-report .material-symbols-outlined { font-size: 14px; }
    
    .badge-in-progress { background: rgba(234, 179, 8, 0.1); color: #ca8a04; border: 1px solid rgba(234, 179, 8, 0.35); font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
    .badge-in-progress .material-symbols-outlined { font-size: 13px; }
    .badge-in-progress .pulse-dot { width: 6px; height: 6px; border-radius: 50%; background: #ca8a04; display: inline-block; position: relative; }
    .badge-in-progress .pulse-dot::before { content: ''; position: absolute; inset: -3px; border-radius: 50%; background: rgba(234, 179, 8, 0.35); animation: pulse-ring 1.5s ease-out infinite; }
    @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 1; } 100% { transform: scale(1.8); opacity: 0; } }
    
    .printer-cell-wrap { display: flex; flex-direction: column; gap: 4px; white-space: normal !important; overflow: hidden !important; }
    .printer-cell-top { display: flex; align-items: center; gap: 8px; min-width: 0; overflow: hidden; }
    .printer-cell-top strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.82rem; }
    .printer-cell-wrap .badge-not-assigned { align-self: flex-start; margin-left: 34px; flex-shrink: 0; }

    .pagination-row select, .pag-perpage select {
        color-scheme: light !important;
        -webkit-appearance: menulist !important;
        -moz-appearance: menulist !important;
        appearance: menulist !important;
        color: #1a1a1a !important;
        background-color: #ffffff !important;
        border: 1px solid #d1d5db !important;
        -webkit-text-fill-color: #1a1a1a !important;
        filter: none !important;
        mix-blend-mode: normal !important;
        opacity: 1 !important;
        text-shadow: none !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
    }
    .pagination-row select option, .pagination-row select optgroup {
        background-color: #ffffff !important;
        color: #1a1a1a !important;
        -webkit-text-fill-color: #1a1a1a !important;
        background-image: none !important;
    }
    .pagination-row select option:checked, .pagination-row select option:hover {
        background-color: #0288d1 !important;
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
    .pagination-row, .pag-perpage { filter: none !important; mix-blend-mode: normal !important; }
    [data-theme="dark"] .pagination-row select, [data-theme="midnight"] .pagination-row select, [data-theme="catppuccin"] .pagination-row select, [data-theme="dark"] .pag-perpage select, [data-theme="midnight"] .pag-perpage select, [data-theme="catppuccin"] .pag-perpage select {
        color: #1a1a1a !important;
        background-color: #ffffff !important;
        -webkit-text-fill-color: #1a1a1a !important;
        color-scheme: light !important;
    }
    
    .selection-count { 
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        background: var(--bg-body);
        color: var(--text-muted);
        white-space: nowrap;
    }
    .selection-count.active {
        background: var(--primary);
        color: white;
    }
    
    th.sortable { cursor: pointer; }
    th.sortable:hover { background: #e9ecef; }
    [data-theme="dark"] th.sortable:hover { background: #374151; }
    .sort-icon { font-size: 0.6rem; opacity: 0.4; margin-left: 4px; }
    .sort-active .sort-icon { opacity: 1 !important; color: var(--primary); }
    
    .btn-reset { 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        height: 36px; 
        padding: 0 14px; 
        background: transparent; 
        border: 1px solid var(--border); 
        border-radius: 6px; 
        color: var(--text-muted); 
        font-size: 0.82rem; 
        font-weight: 600; 
        cursor: pointer; 
        transition: all 0.2s ease; 
        text-decoration: none; 
        white-space: nowrap;
    }
    .btn-reset:hover { background: var(--error-bg); color: var(--error); border-color: var(--error); }
    
    .download-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .download-modal-overlay.active {
        display: flex;
    }
    .download-modal {
        background: var(--bg-surface);
        border-radius: 16px;
        padding: 32px;
        max-width: 480px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border: 1px solid var(--border);
    }
    .download-modal h2 {
        margin: 0 0 8px 0;
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .download-modal p {
        color: var(--text-muted);
        margin-bottom: 20px;
        font-size: 0.9rem;
        line-height: 1.5;
    }
    .download-modal .selected-count {
        background: var(--bg-body);
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 600;
        color: var(--text-main);
        border: 1px solid var(--border);
    }
    .download-modal .selected-count span {
        color: var(--primary);
    }
    
    /* REMOVED FORMAT INFO & BUTTONS */
    .download-modal .format-info { display: none; }
    .download-modal .format-options { display: none; }

    .download-modal .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }
    .download-modal .modal-actions .btn {
        width: auto;
        padding: 10px 24px;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .download-modal .modal-actions .btn-cancel {
        background: #B3C3D5;
        color: #1a1a2e;
        border: 1px solid #B3C3D5;
    }
    .download-modal .modal-actions .btn-cancel:hover {
        background: #9fafc1;
    }
    .download-modal .modal-actions .btn-download {
        background: var(--primary);
        color: white;
    }
    .download-modal .modal-actions .btn-download:hover {
        background: var(--primary-hover);
    }

    .badge-smoke-type {
        background: rgba(245, 158, 11, 0.15);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.35);
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }
    .badge-smoke-type .material-symbols-outlined {
        font-size: 12px;
    }
    .badge-regression-type {
        background: rgba(139, 92, 246, 0.15);
        color: #7c3aed;
        border: 1px solid rgba(139, 92, 246, 0.35);
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }
    .badge-regression-type .material-symbols-outlined {
        font-size: 12px;
    }
    [data-theme="dark"] .badge-smoke-type {
        background: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
    }
    [data-theme="dark"] .badge-regression-type {
        background: rgba(139, 92, 246, 0.2);
        color: #a78bfa;
    }

    .badge-completed {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .badge-completed .material-symbols-outlined {
        font-size: 13px;
    }

    .badge.badge-pass, .badge.badge-fail {
        font-size: 0.7rem;
        padding: 3px 10px;
        white-space: nowrap;
    }
    .badge.badge-pass .material-symbols-outlined,
    .badge.badge-fail .material-symbols-outlined {
        font-size: 13px;
    }
    .badge {
        font-size: 0.7rem;
        padding: 3px 10px;
        white-space: nowrap;
    }
    .badge .material-symbols-outlined {
        font-size: 13px;
    }

    .printer-icon-wrap {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: 50%;
        overflow: hidden;
        background: var(--bg-surface);
        border: 1px solid var(--border);
    }
    .printer-icon-wrap .material-symbols-outlined {
        font-size: 14px;
    }
    .printer-icon-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .printer-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
    }
    .printer-chip-small {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px 2px 4px;
        border-radius: 12px;
        background: var(--bg-body);
        border: 1px solid var(--border);
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-main);
        white-space: nowrap;
    }
    .printer-chip-small .chip-icon {
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
    }
    .printer-chip-small .chip-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .printer-chip-small .chip-icon .material-symbols-outlined {
        font-size: 12px;
        color: var(--primary);
    }

    .col-date { width: 95px; min-width: 85px; max-width: 110px; }
    .col-type { width: 105px; min-width: 95px; max-width: 120px; }
    .col-printer { width: 200px; min-width: 170px; max-width: 250px; }
    .col-fw { width: 105px; min-width: 95px; max-width: 120px; }
    .col-branch { width: 85px; min-width: 75px; max-width: 100px; }
    .col-progress { width: 150px; min-width: 130px; max-width: 170px; }
    .col-status { width: 125px; min-width: 115px; max-width: 140px; }
    .col-report { width: 110px; min-width: 100px; max-width: 130px; text-align: right; padding-right: 20px; }

    .d-table th.col-report,
    .d-table td.col-report {
        text-align: right;
        padding-right: 20px;
    }
</style>

<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">summarize</span>
                Test Reports
            </h1>
        </div>
        
        <div class="unified-card">
            <?php if ($user_role === 'lead' || $user_role === 'admin'): ?>
                <!-- SINGLE ROW CONTROLS -->
                <div class="lead-controls-row">
                    <!-- DATE PICKER (Left) -->
                    <form method="GET" id="lead-date-form" style="display: flex; align-items: center; gap: 0;" onsubmit="updateBrowserURL()">
                        <div class="lead-date-box" id="lead-date-box">
                            <input type="date" id="lead_start_date" name="start_date" value="<?= htmlspecialchars($start_date_lead) ?>" onchange="saveDatesAndSubmit()">
                            <span class="date-range-sep">-</span>
                            <input type="date" id="lead_end_date" name="end_date" value="<?= htmlspecialchars($end_date_lead) ?>" onchange="saveDatesAndSubmit()">
                        </div>
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($lead_sort) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($lead_order) ?>">
                    </form>

                    <!-- BUTTON GROUP (Next to Date Picker) -->
                    <div class="btn-group">
                        <!-- SELECT / CANCEL BUTTON (Background changes to #FF6467 when active) -->
                        <button type="button" id="leadSelectToggle" class="btn-control select-toggle" onclick="toggleSelectMode('lead')">
                            <span class="material-symbols-outlined">checklist</span> <span id="selectToggleText">Select</span>
                        </button>

                        <!-- SELECT ALL BUTTON (Hidden by default, appears after clicking Select) -->
                        <button type="button" class="btn-control ghost" onclick="toggleSelectAllLeadReports()" style="display: none;" id="leadSelectAllBtn">
                            <span class="material-symbols-outlined">select_all</span> Select All
                        </button>

                        <!-- FILTERS -->
                        <button type="button" class="btn-control" onclick="toggleFilterDrawer()">
                            <span class="material-symbols-outlined">tune</span> Filters
                        </button>

                        <!-- RESET -->
                        <button type="button" class="btn-reset" onclick="resetLeadReports()">
                            <span class="material-symbols-outlined">restart_alt</span> Reset
                        </button>
                    </div>

                    <span class="spacer"></span>

                    <!-- DOWNLOAD BUTTON & SELECTION COUNT (Right) -->
                    <div class="btn-group" style="display: flex; align-items: center; gap: 12px;">
                        <span id="leadSelectionCount" class="selection-count empty">0 selected</span>
                        <button type="button" id="leadDownloadBtn" class="btn-control primary" onclick="openDownloadModal('lead')">
                            <span class="material-symbols-outlined">download</span> Download
                        </button>
                    </div>
                </div>
                
                <div class="table-section">
                    <?php if (empty($lead_reports)): ?>
                        <div class="empty-state" style="border:none; border-radius:0; padding: 60px 20px;">
                            <span class="material-symbols-outlined" style="font-size: 48px;">event_busy</span>
                            <p style="margin-top: 16px;">No tests scheduled between <?= date('M d, Y', strtotime($start_date_lead)) ?> and <?= date('M d, Y', strtotime($end_date_lead)) ?>.</p>
                        </div>
                    <?php else: ?>
                        <table class="d-table">
                            <thead>
                                <tr>
                                    <!-- REMOVED THE SELECT ALL CHECKBOX FROM THE HEADER -->
                                    <th class="checkbox-col" id="leadCheckboxHeader" style="width:40px; min-width:40px; max-width:40px; padding: 10px 8px;"></th>
                                    <th class="sortable col-date <?= $lead_sort === 'task_date' ? 'sort-active' : '' ?>" onclick="sortLeadTable('task_date')">
                                        Date <span class="sort-icon"><?= $lead_sort === 'task_date' ? ($lead_order === 'ASC' ? '↑' : '↓') : '↕' ?></span>
                                    </th>
                                    <th class="sortable col-type <?= $lead_sort === 'testing_type' ? 'sort-active' : '' ?>" onclick="sortLeadTable('testing_type')">
                                        Type <span class="sort-icon"><?= $lead_sort === 'testing_type' ? ($lead_order === 'ASC' ? '↑' : '↓') : '↕' ?></span>
                                    </th>
                                    <th class="sortable col-printer <?= $lead_sort === 'model_name' ? 'sort-active' : '' ?>" onclick="sortLeadTable('model_name')">
                                        Printer <span class="sort-icon"><?= $lead_sort === 'model_name' ? ($lead_order === 'ASC' ? '↑' : '↓') : '↕' ?></span>
                                    </th>
                                    <th class="sortable col-fw <?= $lead_sort === 'fw_version_current' ? 'sort-active' : '' ?>" onclick="sortLeadTable('fw_version_current')">
                                        Target FW <span class="sort-icon"><?= $lead_sort === 'fw_version_current' ? ($lead_order === 'ASC' ? '↑' : '↓') : '↕' ?></span>
                                    </th>
                                    <th class="sortable col-branch <?= $lead_sort === 'fw_type' ? 'sort-active' : '' ?>" onclick="sortLeadTable('fw_type')">
                                        Firmware <span class="sort-icon"><?= $lead_sort === 'fw_type' ? ($lead_order === 'ASC' ? '↑' : '↓') : '↕' ?></span>
                                    </th>
                                    <th class="col-progress">Progress</th>
                                    <th class="sortable col-status <?= $lead_sort === 'overall_status' ? 'sort-active' : '' ?>" onclick="sortLeadTable('overall_status')">
                                        Status <span class="sort-icon"><?= $lead_sort === 'overall_status' ? ($lead_order === 'ASC' ? '↑' : '↓') : '↕' ?></span>
                                    </th>
                                    <th class="col-report">Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($lead_reports as $lr): 
                                    $displayPrinterNames = $lr['printer_names'];
                                    $displayPrinterPaths = $lr['printer_paths'];
                                    $printerIdForCheckbox = 'multiple';
                                ?>
                                    <tr>
                                        <td class="checkbox-col" style="width:40px; min-width:40px; max-width:40px; padding: 8px 8px; text-align:center;">
                                            <input type="checkbox" class="lead-report-checkbox" 
                                                   data-task-id="<?= $lr['task_id'] ?>" 
                                                   data-printer-id="<?= $printerIdForCheckbox ?>"
                                                   data-task-date="<?= $lr['task_date'] ?>"
                                                   data-model="<?= htmlspecialchars($displayPrinterNames) ?>"
                                                   onchange="updateLeadSelectionCount()" style="width:16px; height:16px; accent-color:var(--primary); cursor:pointer;">
                                        </td>
                                        <td class="col-date"><span class="mono" style="font-size:0.8rem; color: var(--text-main); font-weight:600;"><?= date('M d, Y', strtotime($lr['task_date'])) ?></span></td>
                                        <td class="col-type">
                                            <?php if ($lr['testing_type'] == 'Smoke'): ?>
                                                <span class="badge-smoke-type">
                                                    <span class="material-symbols-outlined">local_fire_department</span> Smoke
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-regression-type">
                                                    <span class="material-symbols-outlined">checklist</span> Regression
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-printer">
                                            <?php 
                                            $printerNames = explode(', ', $lr['printer_names'] ?? '');
                                            $printerPaths = explode(',', $lr['printer_paths'] ?? '');
                                            ?>
                                            <div class="printer-chips">
                                                <?php foreach($printerNames as $idx => $name): 
                                                    $path = isset($printerPaths[$idx]) ? $printerPaths[$idx] : '';
                                                ?>
                                                    <span class="printer-chip-small">
                                                        <span class="chip-icon">
                                                            <?= renderPrinterImage($path ?: null, trim($name)) ?>
                                                        </span>
                                                        <?= htmlspecialchars(trim($name)) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td class="col-fw"><span class="mono" style="font-size:0.8rem; color:var(--primary); font-weight:600;"><?= htmlspecialchars($lr['fw_version_current']) ?></span></td>
                                        <td class="col-branch" style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($lr['fw_type']) ?></td>
                                        <td class="col-progress">
                                            <div class="progress-wrap">
                                                <div class="progress-labels">
                                                    <span>COMPLETED</span>
                                                    <span style="color: <?= $lr['progress'] == 100 ? 'var(--success)' : 'var(--primary)' ?>;"><?= $lr['progress'] ?>%</span>
                                                </div>
                                                <div class="progress-bar-bg">
                                                    <div class="progress-bar-fill" style="width: <?= $lr['progress'] ?>%; background: <?= $lr['progress'] == 100 ? 'var(--success)' : 'var(--primary)' ?>;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="col-status">
                                            <?php if ($lr['overall_status'] == 'Pass'): ?>
                                                <span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                            <?php elseif ($lr['overall_status'] == 'Fail'): ?>
                                                <span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                            <?php elseif ($lr['overall_status'] == 'Blocked'): ?>
                                                <span class="badge" style="background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid #f97316;">BLOCKED</span>
                                            <?php elseif ($lr['overall_status'] == 'N/A'): ?>
                                                <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid #8b5cf6;">N/A</span>
                                            <?php elseif ($lr['overall_status'] == 'Completed'): ?>
                                                <span class="badge-completed">
                                                    <span class="material-symbols-outlined">check_circle</span> COMPLETED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-in-progress"><span class="pulse-dot"></span> IN PROGRESS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-report">
                                            <?php 
                                            // === CRITICAL FIX: Pass ALL printer_ids as a comma-separated string ===
                                            $printerIds = explode(',', $lr['printer_ids'] ?? '');
                                            $printerIdsString = implode(',', $printerIds);
                                            ?>
                                            <a href="generate_report.php?task_id=<?= $lr['task_id'] ?>&printer_ids=<?= $printerIdsString ?>" class="btn-view-report" target="_blank">
                                                <span class="material-symbols-outlined">visibility</span> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <script>
                    let leadCurrentSort = '<?= $lead_sort ?>';
                    let leadSortOrder = '<?= $lead_order ?>';
                    let leadSelectModeActive = false;
                    
                    function sortLeadTable(column) {
                        if (leadCurrentSort === column) {
                            leadSortOrder = leadSortOrder === 'ASC' ? 'DESC' : 'ASC';
                        } else {
                            leadCurrentSort = column;
                            leadSortOrder = 'ASC';
                        }
                        
                        const url = new URL(window.location.href);
                        url.searchParams.set('sort', column);
                        url.searchParams.set('order', leadSortOrder);
                        window.location.href = url.toString();
                    }
                    
                    function resetLeadReports() {
                        const url = new URL(window.location.href);
                        url.searchParams.delete('start_date');
                        url.searchParams.delete('end_date');
                        url.searchParams.delete('sort');
                        url.searchParams.delete('order');
                        window.location.href = url.pathname;
                    }
                    
                    function toggleSelectMode(type) {
                        const isLead = type === 'lead';
                        const toggleBtn = document.getElementById(isLead ? 'leadSelectToggle' : 'testerSelectToggle');
                        const toggleText = document.getElementById('selectToggleText');
                        const checkboxCols = document.querySelectorAll(isLead ? '.checkbox-col' : '.tester-checkbox-col');
                        const selectAllBtn = document.getElementById(isLead ? 'leadSelectAllBtn' : 'testerSelectAllBtn');
                        
                        if (isLead) {
                            leadSelectModeActive = !leadSelectModeActive;
                        }
                        
                        checkboxCols.forEach(col => col.classList.toggle('show'));
                        
                        toggleBtn.classList.toggle('active');
                        
                        if (toggleBtn.classList.contains('active')) {
                            toggleBtn.classList.add('cancel');
                            toggleBtn.innerHTML = '<span class="material-symbols-outlined">close</span> <span id="selectToggleText">Cancel</span>';
                            selectAllBtn.style.display = 'inline-flex';
                        } else {
                            toggleBtn.classList.remove('cancel');
                            toggleBtn.innerHTML = '<span class="material-symbols-outlined">checklist</span> <span id="selectToggleText">Select</span>';
                            selectAllBtn.style.display = 'none';
                            document.querySelectorAll(isLead ? '.lead-report-checkbox' : '.tester-report-checkbox').forEach(cb => cb.checked = false);
                            updateLeadSelectionCount();
                        }
                    }
                    
                    function updateLeadSelectionCount() {
                        const checkboxes = document.querySelectorAll('.lead-report-checkbox:checked');
                        const count = checkboxes.length;
                        const countEl = document.getElementById('leadSelectionCount');
                        const btn = document.getElementById('leadDownloadBtn');
                        
                        // Count logic (Displayed on screen next to Download button)
                        if (count > 0) {
                            countEl.textContent = count + ' selected';
                            countEl.className = 'selection-count active';
                            btn.disabled = false;
                        } else {
                            countEl.textContent = '0 selected';
                            countEl.className = 'selection-count empty';
                            btn.disabled = false;
                        }
                    }
                    
                    function toggleAllLeadCheckboxes(checked) {
                        document.querySelectorAll('.lead-report-checkbox').forEach(cb => cb.checked = checked);
                        updateLeadSelectionCount();
                    }
                    
                    function toggleSelectAllLeadReports() {
                        const checkboxes = document.querySelectorAll('.lead-report-checkbox');
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        toggleAllLeadCheckboxes(!allChecked);
                    }
                    
                    function openDownloadModal(type) {
                        const checkboxes = document.querySelectorAll(type === 'lead' ? '.lead-report-checkbox:checked' : '.tester-report-checkbox:checked:not(:disabled)');
                        if (checkboxes.length === 0) {
                            alert('Please select at least one report to download.');
                            return;
                        }
                        
                        // === NEW: Save the FULL current URL with filters BEFORE opening the modal ===
                        const fullCurrentUrl = window.location.href;
                        localStorage.setItem('track_reports_prev_url', fullCurrentUrl);
                        
                        const modal = document.getElementById('downloadModal');
                        const countEl = document.getElementById('selectedReportCount');
                        countEl.textContent = checkboxes.length;
                        modal.dataset.type = type;
                        modal.classList.add('active');
                    }
                    
                    function closeDownloadModal() {
                        document.getElementById('downloadModal').classList.remove('active');
                    }
                    
                    function downloadReports() {
                        const modal = document.getElementById('downloadModal');
                        const type = modal.dataset.type;
                        const checkboxes = document.querySelectorAll(type === 'lead' ? '.lead-report-checkbox:checked' : '.tester-report-checkbox:checked:not(:disabled)');
                        
                        // ==========================================================
                        // NEW VALIDATION: PREVENT MIXING SMOKE AND REGRESSION
                        // ==========================================================
                        let hasSmoke = false;
                        let hasRegression = false;
                        
                        checkboxes.forEach(cb => {
                            const modelName = (cb.dataset.model || '').toLowerCase();
                            // Simple heuristic: if data-model contains a comma, it's likely a merged Regression row
                            if (cb.dataset.model && cb.dataset.model.includes(',')) {
                                hasRegression = true;
                            } else {
                                hasSmoke = true;
                            }
                        });

                        // If they selected both types, block the download
                        if (hasSmoke && hasRegression) {
                            alert("Download blocked: You cannot select and download both Smoke Tests and Regression Tests at the same time. Please select reports of only one testing type.");
                            return;
                        }
                        // ==========================================================
                        
                        const taskIds = [];
                        const printerIds = [];
                        
                        checkboxes.forEach(cb => {
                            taskIds.push(cb.dataset.taskId);
                            const pId = cb.dataset.printerId;
                            if (pId === 'multiple') {
                                // Get all printer IDs for this task from the "printer_ids" data attribute
                                const taskId = cb.dataset.taskId;
                                const allIds = document.querySelectorAll(`.lead-report-checkbox[data-task-id="${taskId}"]`);
                                let foundIds = [];
                                
                                if (allIds.length > 0) {
                                    // Actually, the data-printer-id is stored on the specific row. 
                                    // We need to map the existing checkboxes.
                                    const relatedCheckbox = document.querySelector(`.lead-report-checkbox[data-task-id="${taskId}"]`);
                                    if(relatedCheckbox && relatedCheckbox.dataset.printerId !== 'multiple') {
                                        foundIds = [relatedCheckbox.dataset.printerId];
                                    }
                                }
                                
                                // Since the table merges the task, we need to grab the *actual* printer IDs 
                                // from the $printer_ids PHP var passed to the row.
                                // QUICK FIX: Force send the IDs based on the first checkbox found since it's a merged row.
                                // A more robust fix is to send comma-separated IDs from the server side.
                                
                                // To make it work 100% instantly: just send the string "multiple" and fix the backend.
                                printerIds.push('multiple');
                            } else {
                                printerIds.push(pId);
                            }
                        });
                        
                        if (taskIds.length === 0) {
                            alert('Please select at least one report.');
                            return;
                        }
                        
                        const url = `generate_combined_report.php?task_ids=${taskIds.join(',')}&printer_ids=${printerIds.join(',')}`;
                        window.open(url, '_blank');
                        
                        closeDownloadModal();
                    }
                    
                    document.addEventListener('DOMContentLoaded', function() {
                        updateLeadSelectionCount();
                    });
                </script>
            <?php else: ?>

                <div class="table-controls">
                    <div class="table-controls-right">
                        <button type="button" class="btn-reset" onclick="resetToReports()">
                            <span class="material-symbols-outlined">restart_alt</span> Reset
                        </button>
                        <button type="button" class="btn-control" onclick="toggleFilterDrawer()">
                            <span class="material-symbols-outlined">tune</span> Filters
                        </button>
                    </div>
                </div>

                <div id="reports-container">
                    <?php if (empty($my_reports)): ?>
                        <div class="empty-state" style="border:none; border-radius:0;">
                            <span class="material-symbols-outlined">folder_open</span>
                            <p>No smoke test tasks found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-section">
                            <table class="d-table">
                                <thead>
                                    <tr>
                                        <th class="col-date">
                                            <a href="<?= buildSortUrl('task_date', $sort, $order) ?>" class="<?= $sort === 'task_date' ? 'sort-active' : '' ?>">
                                                Date <span class="sort-icon"><?= getSortIcon('task_date', $sort, $order) ?></span>
                                            </a>
                                        </th>
                                        <th class="col-type">
                                            <a href="<?= buildSortUrl('testing_type', $sort, $order) ?>" class="<?= $sort === 'testing_type' ? 'sort-active' : '' ?>">
                                                Type <span class="sort-icon"><?= getSortIcon('testing_type', $sort, $order) ?></span>
                                            </a>
                                        </th>
                                        <th class="col-printer">
                                            <a href="<?= buildSortUrl('model_name', $sort, $order) ?>" class="<?= $sort === 'model_name' ? 'sort-active' : '' ?>">
                                                Printer <span class="sort-icon"><?= getSortIcon('model_name', $sort, $order) ?></span>
                                            </a>
                                        </th>
                                        <th class="col-fw">
                                            <a href="<?= buildSortUrl('fw_version_current', $sort, $order) ?>" class="<?= $sort === 'fw_version_current' ? 'sort-active' : '' ?>">
                                                Current FW <span class="sort-icon"><?= getSortIcon('fw_version_current', $sort, $order) ?></span>
                                            </a>
                                        </th>
                                        <th class="col-progress">Progress</th>
                                        <th class="col-status">
                                            <a href="<?= buildSortUrl('overall_status', $sort, $order) ?>" class="<?= $sort === 'overall_status' ? 'sort-active' : '' ?>">
                                                Status <span class="sort-icon"><?= getSortIcon('overall_status', $sort, $order) ?></span>
                                            </a>
                                        </th>
                                        <th class="col-report">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_reports as $row): 
                                        $rowId = "task_" . $row['task_id'] . "_" . $row['printer_id'];
                                        $notAssigned = empty($row['is_assigned']);
                                        $rowInProgress = isInProgress($row['overall_status']);
                                        $isCompleted = $row['is_completed'] ?? false;
                                    ?>
                                        <tr class="main-row expand-trigger <?= $notAssigned ? 'row-unassigned' : '' ?>" onclick="toggleRow('<?= $rowId ?>', this)">
                                            <td class="col-date"><span class="mono" style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($row['task_date'])) ?></span></td>
                                            <td class="col-type">
                                                <span class="badge <?= $row['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>">
                                                    <?= htmlspecialchars($row['testing_type']) ?>
                                                </span>
                                            </td>
                                            <td class="col-printer">
                                                <div class="printer-cell-wrap">
                                                    <div class="printer-cell-top">
                                                        <div class="printer-icon-wrap"><?= renderPrinterImage($row['printer_path'] ?? null, htmlspecialchars($row['model_name'])) ?></div>
                                                        <strong style="font-size:0.82rem;"><?= htmlspecialchars($row['model_name']) ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="col-fw"><span class="mono" style="font-size:0.8rem; color:var(--primary); font-weight:600;"><?= htmlspecialchars($row['fw_version_current']) ?></span></td>
                                            <td class="col-progress">
                                                <div class="progress-wrap">
                                                    <div class="progress-labels">
                                                        <span>COMPLETED</span>
                                                        <span style="color: <?= ($row['overall_status'] == 'Completed') ? 'var(--success)' : 'var(--primary)' ?>;">
                                                            <?php if ($row['testing_type'] == 'Regression'): ?>
                                                                <?= ($row['overall_status'] == 'Completed') ? '(100%)' : '(0%)' ?>
                                                            <?php else: ?>
                                                                <?= $row['completed_cases'] . '/' . max(1, $row['total_cases']) . ' (' . $row['progress'] . '%)' ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <div class="progress-bar-bg">
                                                        <div class="progress-bar-fill" style="width: <?= ($row['overall_status'] == 'Completed') ? '100%' : $row['progress'] . '%' ?>; background: <?= ($row['overall_status'] == 'Completed') ? 'var(--success)' : 'var(--primary)' ?>;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="col-status">
                                                <?php if ($row['overall_status'] == 'Pass'): ?>
                                                    <span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                                <?php elseif ($row['overall_status'] == 'Fail'): ?>
                                                    <span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                                <?php elseif ($row['overall_status'] == 'Blocked'): ?>
                                                    <span class="badge" style="background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked);"><span class="material-symbols-outlined">block</span> BLOCKED</span>
                                                <?php elseif ($row['overall_status'] == 'N/A'): ?>
                                                    <span class="badge" style="background: var(--na-bg); color: var(--na); border: 1px solid var(--na);"><span class="material-symbols-outlined">do_not_disturb_on</span> N/A</span>
                                                <?php elseif ($row['overall_status'] == 'Completed'): ?>
                                                    <span class="badge-completed"><span class="material-symbols-outlined">check_circle</span> COMPLETED</span>
                                                <?php else: ?>
                                                    <span class="badge-in-progress"><span class="pulse-dot"></span> IN PROGRESS</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-report" onclick="event.stopPropagation();">
                                                <!-- FIX: Pass the exact printer_id from the $row, not the merged task -->
                                                <a href="generate_report.php?task_id=<?= $row['task_id'] ?>&printer_id=<?= $row['printer_id'] ?>" class="btn-view-report" target="_blank">
                                                    <span class="material-symbols-outlined">visibility</span> View
                                                </a>
                                            </td>
                                        </tr>
                                        <tr class="expanded-row <?= $notAssigned ? 'row-unassigned' : '' ?>" id="<?= $rowId ?>_exp">
                                            <td colspan="7">
                                                <div class="accordion-wrapper" id="<?= $rowId ?>">
                                                    <div class="expanded-content">
                                                        <div class="accordion-content">
                                                            <div class="acc-grid">
                                                                <div class="acc-item"><span class="acc-label">Due Date</span><span class="acc-value"><?= date('M d, Y', strtotime($row['due_date'])) ?></span></div>
                                                                <div class="acc-item"><span class="acc-label">Prev. Firmware</span><span class="acc-value mono"><?= htmlspecialchars($row['fw_version_prev']) ?></span></div>
                                                                <div class="acc-item"><span class="acc-label">Recovery Firmware</span><span class="acc-value mono"><?= htmlspecialchars($row['fw_version_rec']) ?></span></div>
                                                                <?php if ($notAssigned): ?>
                                                                    <div class="acc-item"><span class="acc-label">My Role</span><span class="badge-not-assigned"><span class="material-symbols-outlined">person_off</span> Not Assigned</span></div>
                                                                <?php else: ?>
                                                                    <div class="acc-item"><span class="acc-label">My Role</span><span class="badge <?= $row['designation'] == 'Main' ? 'badge-main' : 'badge-support' ?>"><?= htmlspecialchars($row['designation']) ?></span></div>
                                                                <?php endif; ?>
                                                                <div class="acc-item" style="flex: 1; padding-left: 20px; border-left: 1px solid var(--border);">
                                                                    <span class="acc-label">Test Participants</span>
                                                                    <div class="participant-list">
                                                                        <?php if (!empty($row['participants'])): ?>
                                                                            <?php foreach($row['participants'] as $pt): $isMain = $pt['designation'] === 'Main'; ?>
                                                                                <img src="<?= htmlspecialchars($pt['pfp_path'] ?? 'imgs/default_pfp.svg') ?>" class="participant-avatar tooltip-trigger <?= $isMain ? 'participant-main' : '' ?>" data-tip="<?= htmlspecialchars($pt['full_name']) . ($isMain ? ' (Main)' : ' (Support)') ?>">
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <span style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">No participants assigned</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?= Helper::renderPagination($my_totalRows, $perPage, $page, [25, 50, 75, 100]) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Download Modal -->
<div class="download-modal-overlay" id="downloadModal">
    <div class="download-modal">
        <h2>
            <span class="material-symbols-outlined" style="font-size: 24px;">download</span>
            Download Report
        </h2>
        <p>Combine all selected reports into a single comprehensive document of detailed test results.</p>
        <div class="selected-count">
            <span id="selectedReportCount">0</span> report(s) selected for download
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-cancel" onclick="closeDownloadModal()">
                <span class="material-symbols-outlined">close</span>
                Cancel
            </button>
            <button type="button" class="btn btn-download" onclick="downloadReports()">
                <span class="material-symbols-outlined">download</span>
                Download
            </button>
        </div>
    </div>
</div>

<div class="drawer-overlay" id="filterOverlay" onclick="toggleFilterDrawer()"></div>
<div class="filter-drawer" id="filterDrawer">
    <div class="drawer-header">
        <h3><span class="material-symbols-outlined">tune</span> Filters</h3>
        <button type="button" class="modal-filter-reset-btn" onclick="clearFiltersOnly()" title="Clear all filters">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><defs><mask id="SVG7xZIMtwn"><g fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path stroke="#fff" stroke-dasharray="54" d="M5 4h14l-5 6.5v9.5l-4 -4v-5.5Z"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.39s" values="54;0"/></path><path stroke="#000" stroke-dasharray="28" stroke-dashoffset="28" d="M-1 11h26" transform="rotate(45 12 12)"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.455s" dur="0.26s" to="0"/></path></g></mask></defs><path fill="currentColor" d="M0 0h24v24H0z" mask="url(#SVG7xZIMtwn)"/><path fill="none" stroke="currentColor" stroke-dasharray="28" stroke-dashoffset="28" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M-1 13h26" transform="rotate(45 12 12)"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.455s" dur="0.26s" to="0"/></path></svg>
        </button>
        <button type="button" class="modal-close-btn" onclick="toggleFilterDrawer()" title="Close"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="drawer-body">
        <form method="get" class="no-loader" id="ajax-filter-form">
            <input type="hidden" name="sort" id="sort_input" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="order" id="order_input" value="<?= htmlspecialchars($order) ?>">
            <input type="hidden" name="page" id="page_input" value="<?= htmlspecialchars($page) ?>">
            <input type="hidden" name="per_page" id="per_page_input" value="<?= htmlspecialchars($perPage) ?>">

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Date Range</label>
                <div class="date-flex" id="dateRangeGroup">
                    <div class="date-inputs-row">
                        <input type="date" name="start_date" id="startDate" value="<?= htmlspecialchars($start_date) ?>" title="Start Date">
                        <input type="date" name="end_date" id="endDate" value="<?= htmlspecialchars($end_date) ?>" title="End Date">
                    </div>
                    <div id="dateMessage"></div>
                </div>
            </div>

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Testing Type</label>
                <?= Helper::enhancedDropdown([
                    'name' => 'testing_type',
                    'placeholder' => 'All Types',
                    'multiple' => false,
                    'options' => ['Smoke' => 'Smoke', 'Regression' => 'Regression'],
                    'selected' => $testing_type_filter
                ]) ?>
            </div>

            <!-- REMOVED: Firmware Branch Filter -->

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Printers</label>
                <?= Helper::enhancedDropdown(['name' => 'printers[]', 'placeholder' => 'Any Printer...', 'multiple' => true, 'options' => $printerOpts, 'selected' => $printers]) ?>
            </div>

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Status</label>
                <?= Helper::enhancedDropdown([
                    'name' => 'statuses[]',
                    'placeholder' => 'Any Status...',
                    'multiple' => true,
                    'options' => [
                        'Pass' => 'Passed',
                        'Fail' => 'Failed',
                        'Blocked' => 'Blocked',
                        'N/A' => 'N/A',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed'
                    ],
                    'selected' => $statuses
                ]) ?>
            </div>
        </form>
    </div>
</div>

<script>
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const dateMessageContainer = document.getElementById('dateMessage');
    const hideUnassignedToggle = document.getElementById('hideUnassignedToggle');
    let dateValidationTimeout = null;

    function applyUnassignedVisibility() {
        if (!hideUnassignedToggle) return;
        const isHidden = hideUnassignedToggle.checked;
        document.querySelectorAll('#reports-container .row-unassigned').forEach(row => {
            row.style.display = isHidden ? 'none' : '';
        });
    }

    if (hideUnassignedToggle) {
        hideUnassignedToggle.addEventListener('change', applyUnassignedVisibility);
    }

    let testerSelectedReports = [];

    function updateTesterSelectionCount() {
        const checkboxes = document.querySelectorAll('.tester-report-checkbox:checked:not(:disabled)');
        const count = checkboxes.length;
        const countEl = document.getElementById('testerSelectionCount');
        const btn = document.getElementById('testerDownloadBtn');
        
        if (count > 0) {
            countEl.textContent = count + ' selected';
            countEl.className = 'selection-count';
            btn.disabled = false;
        } else {
            countEl.textContent = '0 selected';
            countEl.className = 'selection-count empty';
            btn.disabled = true;
        }
    }

    function toggleAllTesterCheckboxes(checked) {
        document.querySelectorAll('.tester-report-checkbox:not(:disabled)').forEach(cb => cb.checked = checked);
        updateTesterSelectionCount();
    }

    function toggleSelectAllTesterReports() {
        const checkboxes = document.querySelectorAll('.tester-report-checkbox:not(:disabled)');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        toggleAllTesterCheckboxes(!allChecked);
    }

    function clearDateMessage() { if(!dateMessageContainer) return; dateMessageContainer.innerHTML = ''; if(startDateInput) startDateInput.classList.remove('date-error', 'date-valid'); if(endDateInput) endDateInput.classList.remove('date-error', 'date-valid'); }
    function showDateError(message) { clearDateMessage(); if(startDateInput) startDateInput.classList.add('date-error'); if(endDateInput) endDateInput.classList.add('date-error'); if(dateMessageContainer) dateMessageContainer.innerHTML = `<div class="date-error-msg"><span class="material-symbols-outlined">error</span><span>${message}</span></div>`; }
    function showDateValid(message) { clearDateMessage(); if(startDateInput) startDateInput.classList.add('date-valid'); if(endDateInput) endDateInput.classList.add('date-valid'); if(dateMessageContainer) dateMessageContainer.innerHTML = `<div class="date-valid-msg"><span class="material-symbols-outlined">check_circle</span><span>${message}</span></div>`; }
    function formatDate(dateStr) { return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
    function calculateDays(start, end) { return Math.ceil((new Date(end + 'T00:00:00') - new Date(start + 'T00:00:00')) / (1000 * 60 * 60 * 24)); }

    function validateDateRange(showMessages = true) {
        if(!startDateInput || !endDateInput) return true;
        const s = startDateInput.value, e = endDateInput.value;
        if (!s && !e) { clearDateMessage(); return true; }
        if ((s && !e) || (!s && e)) { if (showMessages) clearDateMessage(); return true; }
        const sd = new Date(s + 'T00:00:00'), ed = new Date(e + 'T00:00:00');
        if (ed < sd) { if (showMessages) showDateError(`End date (${formatDate(e)}) cannot be before start date (${formatDate(s)})`); return false; }
        if (ed.getTime() === sd.getTime()) { if (showMessages) showDateValid(`Showing results for ${formatDate(s)}`); return true; }
        if (showMessages) showDateValid(`${formatDate(s)} → ${formatDate(e)} (${calculateDays(s, e)} days)`);
        return true;
    }

    function debouncedValidation(showMessages) { clearTimeout(dateValidationTimeout); dateValidationTimeout = setTimeout(() => validateDateRange(showMessages), 150); }

    if(startDateInput) { startDateInput.addEventListener('change', function() { if (this.value && endDateInput && endDateInput.value && new Date(endDateInput.value + 'T00:00:00') < new Date(this.value + 'T00:00:00')) { endDateInput.value = ''; showDateError(`End date was cleared because it was before ${formatDate(this.value)}`); return; } debouncedValidation(true); }); startDateInput.addEventListener('input', () => debouncedValidation(true)); }
    if(endDateInput) { endDateInput.addEventListener('change', () => debouncedValidation(true)); endDateInput.addEventListener('input', () => debouncedValidation(true)); }

    function toggleFilterDrawer() {
        document.getElementById('filterDrawer').classList.toggle('open');
        const overlay = document.getElementById('filterOverlay'); overlay.classList.toggle('show');
        if (overlay.classList.contains('show')) { document.body.style.overflow = 'hidden'; setTimeout(() => validateDateRange(false), 100); } else { document.body.style.overflow = ''; }
    }

    function resetToReports() { 
        const url = new URL(window.location.href);
        url.searchParams.delete('start_date');
        url.searchParams.delete('end_date');
        url.searchParams.delete('printers');
        url.searchParams.delete('statuses');
        url.searchParams.delete('sort');
        url.searchParams.delete('order');
        url.searchParams.delete('page');
        window.location.href = url.pathname;
    }

    const dropdownInitialHTML = new Map();

    function clearFiltersOnly() {
        const form = document.getElementById('ajax-filter-form'); if (!form) return; 
        const keepNames = ['sort', 'order', 'page', 'per_page'];
        form.querySelectorAll('input[type="date"]').forEach(input => input.value = ''); 
        clearDateMessage();
        
        if (hideUnassignedToggle) { hideUnassignedToggle.checked = false; }

        form.querySelectorAll('.enh-dropdown').forEach(dropdown => {
            const trigger = dropdown.querySelector('.enh-trigger'); const triggerContent = trigger?.querySelector('.enh-trigger-content'); if (!triggerContent) return;
            const savedHTML = dropdownInitialHTML.get(dropdown);
            if (savedHTML && savedHTML.includes('<') && savedHTML.replace(/<[^>]*>/g, '').trim().length > 0) { triggerContent.innerHTML = savedHTML; } 
            else { triggerContent.innerHTML = ''; let text = trigger?.getAttribute('data-placeholder') || dropdown.getAttribute('data-placeholder') || ''; if (!text) { const name = dropdown.querySelector('input[type="hidden"], select')?.getAttribute('name') || ''; if (name.includes('printer')) text = 'Any Printer...'; else if (name.includes('status')) text = 'Any Status...'; else text = 'All Types'; } const span = document.createElement('span'); span.className = 'enh-placeholder'; span.textContent = text; triggerContent.prepend(span); }
            dropdown.querySelectorAll('.enh-option').forEach(opt => { opt.classList.remove('selected', 'active', 'is-selected'); opt.removeAttribute('data-selected'); const check = opt.querySelector('.enh-check, .enh-tick, input[type="checkbox"]'); if (check) { if (check.type === 'checkbox') check.checked = false; else check.classList.remove('checked', 'visible'); } });
            trigger?.classList.remove('has-value', 'is-dirty', 'has-selection'); dropdown.classList.remove('has-value', 'is-dirty', 'has-selection'); dropdown.setAttribute('data-has-value', 'false'); trigger?.setAttribute('data-has-value', 'false');
        });
        Array.from(form.querySelectorAll('input[type="hidden"]')).forEach(input => { if (!keepNames.includes(input.name)) input.remove(); });
        document.getElementById('page_input').value = '1';
        form.dispatchEvent(new Event('change'));
    }

    function toggleRow(rowId, element) {
        const wrapper = document.getElementById(rowId);
        if (!wrapper) return;
        const isOpen = wrapper.classList.contains('open');
        document.querySelectorAll('.accordion-wrapper.open').forEach(el => {
            if (el.id !== rowId) {
                el.classList.remove('open');
                const parentRow = el.closest('.expanded-row');
                if (parentRow) {
                    const mainRow = parentRow.previousElementSibling;
                    if (mainRow) mainRow.classList.remove('is-open');
                }
            }
        });
        if (isOpen) {
            wrapper.classList.remove('open');
            element.classList.remove('is-open');
        } else {
            wrapper.classList.add('open');
            element.classList.add('is-open');
        }
    }

    document.getElementById('downloadModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDownloadModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDownloadModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.enh-dropdown').forEach(dropdown => { const triggerContent = dropdown.querySelector('.enh-trigger-content'); if (triggerContent) dropdownInitialHTML.set(dropdown, triggerContent.innerHTML); });
        const form = document.getElementById('ajax-filter-form'); const container = document.getElementById('reports-container'); if (!form || !container) return; 

        function loadData(url) {
            if (!validateDateRange(true)) { window.hideLoader(); return; } window.showLoader();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('reports-container');
                    if (newContent) {
                        container.innerHTML = newContent.innerHTML;
                    }
                    
                    applyUnassignedVisibility();
                    fixPerPageSelect();
                    
                    window.history.pushState({}, '', url); window.hideLoader();
                })
                .catch(() => window.hideLoader());
        }

        function buildAndLoadUrl() {
            const url = new URL(window.location.pathname, window.location.origin);
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (value) {
                    url.searchParams.append(key, value);
                }
            }
            loadData(url);
        }

        form.addEventListener('change', (e) => {
            if (e.target.id === 'endDate' && !e.target.value) {
                if (document.getElementById('startDate').value) return;
            }
            if (!e.target.classList.contains('per-page-select')) {
                document.getElementById('page_input').value = '1';
            }
            buildAndLoadUrl();
        });

        document.addEventListener('click', (e) => {
            const link = e.target.closest('.page-link');
            if (link && link.tagName === 'A') { e.preventDefault(); loadData(link.href); }
        });

        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('per-page-select')) {
                document.getElementById('per_page_input').value = e.target.value;
                document.getElementById('page_input').value = '1';
                buildAndLoadUrl();
            }
        });

        validateDateRange(false);
    });

    function fixPerPageSelect() {
        const select = document.querySelector('.pagination-row select, .pag-perpage select');
        if (!select) return;
        select.style.setProperty('color-scheme', 'light', 'important');
        select.style.setProperty('color', '#1a1a1a', 'important');
        select.style.setProperty('background-color', '#ffffff', 'important');
        select.style.setProperty('-webkit-text-fill-color', '#1a1a1a', 'important');
        select.style.setProperty('filter', 'none', 'important');
        select.querySelectorAll('option').forEach(function(opt) {
            opt.style.backgroundColor = '#ffffff';
            opt.style.color = '#1a1a1a';
            opt.style.setProperty('-webkit-text-fill-color', '#1a1a1a', 'important');
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        fixPerPageSelect();
        applyUnassignedVisibility();
    });
</script>

<!-- SCRIPT: Fix the Back Button losing date filters -->
<script>
    // 1. Saves the dates to Session Storage and submits the form
    function saveDatesAndSubmit() {
        const start = document.getElementById('lead_start_date').value;
        const end = document.getElementById('lead_end_date').value;
        sessionStorage.setItem('track_reports_start', start);
        sessionStorage.setItem('track_reports_end', end);
        document.getElementById('lead-date-form').submit();
    }

    // 2. Updates URL to keep filters, but not strictly needed due to Session Storage fallback
    function updateBrowserURL() {
        const start = document.getElementById('lead_start_date').value;
        const end = document.getElementById('lead_end_date').value;
        const sort = document.querySelector('input[name="sort"]').value;
        const order = document.querySelector('input[name="order"]').value;
        // Just in case the form doesn't reload for some reason
        if(start && end) {
           const newURL = `?start_date=${start}&end_date=${end}&sort=${sort}&order=${order}`;
           window.history.replaceState({}, '', newURL);
        }
    }

    // 3. Runs immediately when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        // If the URL already has dates, the page loaded normally. Do nothing.
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('start_date')) {
            return; 
        }

        // If there are NO dates in the URL (Back button was clicked), check Session Storage
        const savedStart = sessionStorage.getItem('track_reports_start');
        const savedEnd = sessionStorage.getItem('track_reports_end');

        // If we found saved dates, redirect to force the URL to use them
        if (savedStart && savedEnd) {
            // Get current sort/order from the hidden inputs
            const sort = document.querySelector('input[name="sort"]').value;
            const order = document.querySelector('input[name="order"]').value;
            window.location.href = `?start_date=${savedStart}&end_date=${savedEnd}&sort=${sort}&order=${order}`;
        }
    });
</script>

</body>
</html>