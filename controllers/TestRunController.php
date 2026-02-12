<?php
// controllers/TestRunController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

if (!isset($_GET['task_id']) || !isset($_GET['printer_id'])) {
    header("Location: index.php");
    exit();
}

$task_id = $_GET['task_id'];
$printer_id = $_GET['printer_id'];

// 1. Fetch Task & Printer Details
$stmt = $pdo->prepare("
    SELECT t.*, p.model_name 
    FROM tasks t 
    JOIN printers p ON p.id = ? 
    WHERE t.id = ?
");
$stmt->execute([$printer_id, $task_id]);
$task_info = $stmt->fetch();

if (!$task_info) {
    die("Task not found.");
}

// 2. Fetch All Test Cases + Live Results
// We LEFT JOIN test_results so we get all cases, even if not tested yet.
$sql = "
    SELECT 
        tc.id as case_id,
        tc.case_code,
        tc.title,
        tr.status,
        tr.jira_url,
        u.full_name as updated_by_name,
        tr.updated_at
    FROM test_cases tc
    LEFT JOIN test_results tr 
        ON tc.id = tr.test_case_id 
        AND tr.task_id = ? 
        AND tr.printer_id = ?
    LEFT JOIN users u ON tr.updated_by = u.id
    WHERE tc.printer_model = ?
    ORDER BY tc.case_code ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$task_id, $printer_id, $task_info['model_name']]);
$test_cases = $stmt->fetchAll();

// 3. Handle AJAX Status Updates (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $case_id = $_POST['case_id'];
        $status = $_POST['status'];
        $jira = $_POST['jira_url'] ?? null;
        $user = $_SESSION['user_id'];

        // Check if result row exists
        $check = $pdo->prepare("SELECT id FROM test_results WHERE task_id=? AND printer_id=? AND test_case_id=?");
        $check->execute([$task_id, $printer_id, $case_id]);
        
        if ($check->rowCount() > 0) {
            // Update
            $upd = $pdo->prepare("UPDATE test_results SET status=?, jira_url=?, updated_by=?, updated_at=NOW() WHERE task_id=? AND printer_id=? AND test_case_id=?");
            $upd->execute([$status, $jira, $user, $task_id, $printer_id, $case_id]);
        } else {
            // Insert
            $ins = $pdo->prepare("INSERT INTO test_results (task_id, printer_id, test_case_id, status, jira_url, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$task_id, $printer_id, $case_id, $status, $jira, $user]);
        }
        
        echo json_encode(['success' => true, 'updater' => $_SESSION['full_name']]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}
?>