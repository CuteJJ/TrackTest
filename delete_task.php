<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

// Only leads can delete tasks
Helper::requireRole('lead');

$task_id = $_GET['id'] ?? null;

// If no ID is provided, redirect back
if (!$task_id) {
    Helper::setFlash("Invalid task ID.", "error");
    header("Location: index.php");
    exit();
}

try {
    // Start a transaction to ensure clean deletion across all related tables
    $pdo->beginTransaction();

    // 1. Delete all test execution results associated with this task
    $stmt_results = $pdo->prepare("DELETE FROM test_results WHERE task_id = ?");
    $stmt_results->execute([$task_id]);

    // 2. Delete all printer and user assignments for this task
    $stmt_assignments = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ?");
    $stmt_assignments->execute([$task_id]);

    // 3. Finally, delete the main task record itself
    $stmt_task = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt_task->execute([$task_id]);

    // Commit the transaction
    $pdo->commit();

    // Trigger your success slide-in toast
    Helper::setFlash("Task #{$task_id} has been successfully deleted.", "success");

} catch (Exception $e) {
    // If anything goes wrong, rollback the database so nothing is partially deleted
    $pdo->rollBack();
    Helper::setFlash("Error deleting task: " . $e->getMessage(), "error");
}

// Return to the Task Masterlist
header("Location: tasks.php");
exit();