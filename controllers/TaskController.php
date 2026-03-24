<?php
// controllers/TaskController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

// Fetch Data for the Form
function getData($pdo) {
    $printers = $pdo->query("SELECT * FROM printers ORDER BY model_name")->fetchAll();
    $users = $pdo->query("SELECT * FROM users ORDER BY full_name")->fetchAll();
    
    // 1. Combined Query: Fetch ALL distinct firmwares across all 3 columns
    $sql = "
        SELECT DISTINCT fw, fw_type FROM (
            SELECT fw_version_current AS fw, fw_type FROM tasks WHERE fw_version_current != '' AND fw_type IS NOT NULL
            UNION
            SELECT fw_version_prev AS fw, fw_type FROM tasks WHERE fw_version_prev != '' AND fw_type IS NOT NULL
            UNION
            SELECT fw_version_rec AS fw, fw_type FROM tasks WHERE fw_version_rec != '' AND fw_type IS NOT NULL
        ) as combined
        WHERE fw IS NOT NULL AND fw != ''
    ";
    
    $fw_data = $pdo->query($sql)->fetchAll();
    
    // 2. Group by Type
    $trunk_fws = [];
    $branch_fws = [];
    
    foreach ($fw_data as $row) {
        if ($row['fw_type'] === 'Trunk') {
            $trunk_fws[] = $row['fw'];
        } elseif ($row['fw_type'] === 'Branch') {
            $branch_fws[] = $row['fw'];
        }
    }
    
    // 3. Sort Descending (Using native version_compare to handle strings like '25.1.0' correctly)
    usort($trunk_fws, 'version_compare');
    $trunk_fws = array_reverse($trunk_fws);
    
    usort($branch_fws, 'version_compare');
    $branch_fws = array_reverse($branch_fws);

    return [
        'printers' => $printers, 
        'users' => $users,
        'firmwares' => [
            'Trunk' => $trunk_fws,
            'Branch' => $branch_fws
        ]
    ];
}

// ============================================
// CREATE TASK
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    try {
        // 1. Validation: Ensure at least one printer is selected
        $selected_printers = $_POST['printers'] ?? [];
        if (empty($selected_printers)) {
            throw new Exception("Please select at least one printer and assign testers/URL.");
        }

        $pdo->beginTransaction();

        // 2. Create the Main Task
        $stmt = $pdo->prepare("INSERT INTO tasks (task_date, testing_type, fw_version_current, fw_version_prev, fw_version_rec, fw_type, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['task_date'],
            $_POST['testing_type'],
            $_POST['fw_curr'],
            $_POST['fw_prev'],
            $_POST['fw_rec'],
            $_POST['fw_type'],
            $_POST['due_date']
        ]);
        $task_id = $pdo->lastInsertId();

        // 3. Handle Assignments
        $type = $_POST['testing_type'];

        foreach ($selected_printers as $pid) {
            if ($type === 'Regression') {
                // Regression: Get the specific URL for this printer
                $reg_url = $_POST['regression_urls'][$pid] ?? '';
                
                // Assign to current user (Lead) as placeholder/owner
                $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation, regression_url) VALUES (?, ?, ?, 'Main', ?)");
                $stmt->execute([$task_id, $pid, $_SESSION['user_id'], $reg_url]);
                
            } elseif ($type === 'Smoke') {
                // Smoke: Process Main/Support assignments
                $assigned_users = $_POST['assignments'][$pid] ?? [];
                
                // Fallback: If no testers were dragged in, auto-assign the Lead so the task is created and visible
                if (empty($assigned_users)) {
                    $assigned_users = [$_SESSION['user_id']];
                    $main_tester = $_SESSION['user_id'];
                } else {
                    $main_tester = $_POST['main_tester'][$pid] ?? null;
                }

                foreach ($assigned_users as $uid) {
                    $role = ($uid == $main_tester) ? 'Main' : 'Support';
                    $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$task_id, $pid, $uid, $role]);
                }
            }
        }

        $pdo->commit();
        Helper::setFlash("Task created successfully!", "success");
        unset($_SESSION['create_task_form']); // Clear saved form data on success
        header("Location: ../index.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Save form data into session so the user doesn't lose their inputs
        $_SESSION['create_task_form'] = $_POST; 
        
        Helper::setFlash("Error: " . $e->getMessage(), "error");
        header("Location: ../create_task.php");
        exit();
    }
}

// ============================================
// UPDATE TASK (EDIT)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    try {
        $task_id = $_POST['task_id'];
        $selected_printers = $_POST['printers'] ?? [];

        if (empty($selected_printers)) {
            throw new Exception("Please select at least one printer and assign testers/URL.");
        }

        $pdo->beginTransaction();

        // 1. Update the main task
        $stmt = $pdo->prepare("
            UPDATE tasks SET
                task_date = ?,
                testing_type = ?,
                fw_version_current = ?,
                fw_version_prev = ?,
                fw_version_rec = ?,
                fw_type = ?,
                due_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['task_date'],
            $_POST['testing_type'],
            $_POST['fw_curr'],
            $_POST['fw_prev'],
            $_POST['fw_rec'],
            $_POST['fw_type'],
            $_POST['due_date'],
            $task_id
        ]);

        // 2. Delete all existing assignments for this task
        $stmt = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ?");
        $stmt->execute([$task_id]);

        // 3. Re-insert assignments based on current form data
        $type = $_POST['testing_type'];

        foreach ($selected_printers as $pid) {
            if ($type === 'Regression') {
                $reg_url = $_POST['regression_urls'][$pid] ?? '';
                // Assign to current user (lead) as placeholder
                $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation, regression_url) VALUES (?, ?, ?, 'Main', ?)");
                $stmt->execute([$task_id, $pid, $_SESSION['user_id'], $reg_url]);
            } else {
                $assigned_users = $_POST['assignments'][$pid] ?? [];
                // If no testers, auto-assign the lead so task remains visible
                if (empty($assigned_users)) {
                    $assigned_users = [$_SESSION['user_id']];
                    $main_tester = $_SESSION['user_id'];
                } else {
                    $main_tester = $_POST['main_tester'][$pid] ?? null;
                }

                foreach ($assigned_users as $uid) {
                    $role = ($uid == $main_tester) ? 'Main' : 'Support';
                    $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$task_id, $pid, $uid, $role]);
                }
            }
        }

        $pdo->commit();
        Helper::setFlash("Task updated successfully! All assignments have been reset.", "success");
        unset($_SESSION['edit_task_form']);
        header("Location: ../index.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['edit_task_form'] = $_POST;
        Helper::setFlash("Error updating task: " . $e->getMessage(), "error");
        header("Location: ../edit_task.php?id=" . $_POST['task_id']);
        exit();
    }
}