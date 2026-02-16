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
$user_id = $_SESSION['user_id'];

// 1. Fetch Task Info (Joined with Printer)
$stmt = $pdo->prepare("
    SELECT t.*, p.model_name 
    FROM tasks t 
    JOIN printers p ON p.id = ? 
    WHERE t.id = ?
");
$stmt->execute([$printer_id, $task_id]);
$task_info = $stmt->fetch();

if (!$task_info) die("Task not found.");

// 1b. Fetch User's Role (Main/Support) for this specific assignment
$stmt = $pdo->prepare("SELECT designation FROM task_assignments WHERE task_id = ? AND printer_id = ? AND user_id = ?");
$stmt->execute([$task_id, $printer_id, $user_id]);
$my_assignment = $stmt->fetchColumn(); 
$user_role_label = $my_assignment ? $my_assignment : 'Support'; // Default to Support if not found

// ==========================================
//              AJAX HANDLERS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        // ACTION 1: CLAIM CASE (Step 1 Selection)
        if (isset($_POST['claim_case'])) {
            $case_id = $_POST['case_id'];
            
            // Check if row exists first
            $check = $pdo->prepare("SELECT id FROM test_results WHERE task_id=? AND printer_id=? AND test_case_id=?");
            $check->execute([$task_id, $printer_id, $case_id]);
            
            if ($check->rowCount() > 0) {
                // Update existing row
                $upd = $pdo->prepare("UPDATE test_results SET assigned_to = ?, status = 'Pending' WHERE task_id=? AND printer_id=? AND test_case_id=?");
                $upd->execute([$user_id, $task_id, $printer_id, $case_id]);
            } else {
                // Insert new row
                $ins = $pdo->prepare("INSERT INTO test_results (task_id, printer_id, test_case_id, status, assigned_to) VALUES (?, ?, ?, 'Pending', ?)");
                $ins->execute([$task_id, $printer_id, $case_id, $user_id]);
            }
            echo json_encode(['success' => true]);
            exit();
        }

        // ACTION 2: UNCLAIM CASE (Slide to Delete)
        if (isset($_POST['unclaim_case'])) {
            $case_id = $_POST['case_id'];
            
            // Set assigned_to NULL and reset status to Pending. 
            // Only allow if currently assigned to this user.
            $stmt = $pdo->prepare("
                UPDATE test_results 
                SET assigned_to = NULL, status = 'Pending', jira_url = NULL 
                WHERE task_id=? AND printer_id=? AND test_case_id=? AND assigned_to=?
            ");
            $stmt->execute([$task_id, $printer_id, $case_id, $user_id]);
            
            echo json_encode(['success' => true]);
            exit();
        }

        // ACTION 3: UPDATE STATUS (Pass/Fail)
        if (isset($_POST['update_status'])) {
            $case_id = $_POST['case_id'];
            $status = $_POST['status'];
            $jira = $_POST['jira_url'] ?? null;
            
            // Ensure row exists (it should if claimed)
            $check = $pdo->prepare("SELECT id FROM test_results WHERE task_id=? AND printer_id=? AND test_case_id=?");
            $check->execute([$task_id, $printer_id, $case_id]);
            
            if ($check->rowCount() > 0) {
                // Update status, JIRA, and "Updated By"
                $upd = $pdo->prepare("
                    UPDATE test_results 
                    SET status=?, jira_url=?, updated_by=?, updated_at=NOW() 
                    WHERE task_id=? AND printer_id=? AND test_case_id=?
                ");
                $upd->execute([$status, $jira, $user_id, $task_id, $printer_id, $case_id]);
            } else {
                // Fallback insert (rare edge case)
                $ins = $pdo->prepare("
                    INSERT INTO test_results (task_id, printer_id, test_case_id, status, jira_url, updated_by, assigned_to) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$task_id, $printer_id, $case_id, $status, $jira, $user_id, $user_id]);
            }
            
            echo json_encode(['success' => true, 'updater' => $_SESSION['full_name']]);
            exit();
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// ==========================================
//           DATA FETCHING (VIEW)
// ==========================================

// A. Get All Cases with Assignments and Updaters
$sql_all = "
    SELECT 
        tc.id as case_id, 
        tc.case_code, 
        tc.title,
        tr.status, 
        tr.assigned_to, 
        tr.jira_url,
        tr.updated_at,
        u_assign.full_name as assigned_name, 
        u_assign.id as assignee_id,
        u_update.full_name as updater_name
    FROM test_cases tc
    LEFT JOIN test_results tr 
        ON tc.id = tr.test_case_id AND tr.task_id = ? AND tr.printer_id = ?
    LEFT JOIN users u_assign ON tr.assigned_to = u_assign.id
    LEFT JOIN users u_update ON tr.updated_by = u_update.id
    WHERE tc.printer_model = ?
    ORDER BY tc.case_code ASC
";
$stmt = $pdo->prepare($sql_all);
$stmt->execute([$task_id, $printer_id, $task_info['model_name']]);
$all_cases = $stmt->fetchAll();

// B. Filter: My Execution List (Step 2)
$my_cases = array_filter($all_cases, function($c) use ($user_id) {
    return $c['assigned_to'] == $user_id;
});

// C. Filter: Available/Unassigned Cases (Step 1)
$available_cases = array_filter($all_cases, function($c) {
    return empty($c['assigned_to']);
});

$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, ta.designation 
    FROM task_assignments ta 
    JOIN users u ON ta.user_id = u.id 
    WHERE ta.task_id = ? AND ta.printer_id = ?
    ORDER BY CASE WHEN ta.designation = 'Main' THEN 0 ELSE 1 END, u.full_name ASC
");
$stmt->execute([$task_id, $printer_id]);
$assigned_rows = $stmt->fetchAll();

// D. Get Unique Testers involved (For the Grid Legend)
$testers = [];
foreach ($assigned_rows as $row) {
    $testers[$row['id']] = [
        'name' => $row['full_name'],
        'role' => $row['designation']
    ];
}
?>