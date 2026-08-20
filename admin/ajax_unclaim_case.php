<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';

// Only allow admin/lead to perform this action
Helper::requireRole(['admin','lead']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['printer_id']) && isset($_POST['case_id'])) {
    try {
        $task_id = $_POST['task_id'];
        $printer_id = $_POST['printer_id'];
        $case_id = $_POST['case_id'];

        // Unclaim the case: Set assigned_to to NULL, reset status to Pending, and clear JIRA
        $stmt = $pdo->prepare("
            UPDATE test_results 
            SET assigned_to = NULL, status = 'Pending', jira_url = NULL 
            WHERE task_id = ? AND printer_id = ? AND test_case_id = ?
        ");
        $stmt->execute([$task_id, $printer_id, $case_id]);

        echo json_encode(['success' => true, 'message' => 'Test case successfully removed from tester.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// If accessed directly without POST data
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid request.']);