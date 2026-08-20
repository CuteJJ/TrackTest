<?php
// controllers/TaskController.php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireLogin();

// Fetch Data for the Form
function getData($pdo) {
    $printers = $pdo->query("SELECT * FROM printers WHERE status = 'active' ORDER BY model_name")->fetchAll();
    $users = $pdo->query("SELECT * FROM users ORDER BY full_name")->fetchAll();
    
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
    
    $trunk_fws = [];
    $branch_fws = [];
    
    foreach ($fw_data as $row) {
        if ($row['fw_type'] === 'Trunk') {
            $trunk_fws[] = $row['fw'];
        } elseif ($row['fw_type'] === 'Branch') {
            $branch_fws[] = $row['fw'];
        }
    }
    
    usort($trunk_fws, 'version_compare');
    $trunk_fws = array_reverse($trunk_fws);
    
    usort($branch_fws, 'version_compare');
    $branch_fws = array_reverse($branch_fws);

    return [
        'printers' => $printers, 
        'users' => $users,
        'firmwares' => [
            'Trunk' => array_values($trunk_fws),
            'Branch' => array_values($branch_fws)
        ]
    ];
}

// ============================================
// CREATE TASK
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    try {
        // --- 1. Validate Task Details ---
        if (empty($_POST['task_date']) || empty($_POST['due_date'])) {
            throw new Exception("Please fill in both Task Date and Due Date.");
        }

        // --- 2. Validate Firmware Configuration ---
        if (empty($_POST['fw_type'])) {
            throw new Exception("Please select a Firmware Type (Trunk or Branch).");
        }
        
        // Validate all 3 Firmware versions
        if (empty($_POST['fw_prev'])) {
            throw new Exception("Please enter the Previous Firmware version.");
        }
        if (empty($_POST['fw_curr'])) {
            throw new Exception("Please enter the Current Firmware version.");
        }
        if (empty($_POST['fw_rec'])) {
            throw new Exception("Please enter the Recovery Firmware version.");
        }

        // --- 3. Validate Printers ---
        $selected_printers = $_POST['printers'] ?? [];
        if (empty($selected_printers)) {
            throw new Exception("Please select at least one printer in the sidebar.");
        }

        // --- 4. Validate Assignments/URLs based on Workflow ---
        $type = $_POST['testing_type'];

        if ($type === 'Smoke') {
            foreach ($selected_printers as $pid) {
                $assigned_users = $_POST['assignments'][$pid] ?? [];
                if (empty($assigned_users)) {
                    throw new Exception("Please assign at least one tester to the selected printer(s).");
                }
            }
        } elseif ($type === 'Regression') {
            foreach ($selected_printers as $pid) {
                $reg_url = $_POST['regression_urls'][$pid] ?? '';
                $trimmed_url = trim($reg_url);
                
                // --- CRITICAL FIX: Check against pure prefixes ---
                if (empty($trimmed_url) || $trimmed_url === 'https://' || $trimmed_url === 'http://' || $trimmed_url === 'https:///' || $trimmed_url === 'http:///') {
                    throw new Exception("Please enter a valid TestRail URL for all selected Regression printer(s).");
                }
            }
        }

        // --- PROCEED WITH DATABASE INSERTION ---
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO tasks (task_date, testing_type, fw_version_current, fw_version_prev, fw_version_rec, fw_type, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['task_date'],
            $type,
            $_POST['fw_curr'],
            $_POST['fw_prev'],
            $_POST['fw_rec'],
            $_POST['fw_type'],
            $_POST['due_date']
        ]);
        $task_id = $pdo->lastInsertId();

        foreach ($selected_printers as $pid) {
            if ($type === 'Regression') {
                $reg_url = $_POST['regression_urls'][$pid] ?? '';
                $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation, regression_url) VALUES (?, ?, ?, 'Main', ?)");
                $stmt->execute([$task_id, $pid, $_SESSION['user_id'], $reg_url]);
                
            } elseif ($type === 'Smoke') {
                $assigned_users = $_POST['assignments'][$pid] ?? [];
                $main_tester = $_POST['main_tester'][$pid] ?? null;

                foreach ($assigned_users as $uid) {
                    $role = ($uid == $main_tester) ? 'Main' : 'Support';
                    $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$task_id, $pid, $uid, $role]);
                }
            }
        }

        $pdo->commit();
        Helper::setFlash("Task created successfully!", "success");
        unset($_SESSION['create_task_form']);
        header("Location: ../tasks.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['create_task_form'] = $_POST; 
        Helper::setFlash("Error: " . $e->getMessage(), "error");
        header("Location: ../create_task.php");
        exit();
    }
}

// ============================================
// UPDATE TASK (EDIT) - REFINED LOGIC
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    try {
        $task_id = $_POST['task_id'];
        $selected_printers = $_POST['printers'] ?? [];
        $printer_id_to_edit = $_POST['printer_id'] ?? null;

        if (empty($selected_printers)) {
            throw new Exception("Please select at least one printer and assign testers/URL.");
        }

        $pdo->beginTransaction();

        // 1. Fetch current task type
        $stmt = $pdo->prepare("SELECT testing_type FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $current_type = $stmt->fetchColumn();
        $new_type = $_POST['testing_type'];

        // 2. Update the main task details
        $stmt = $pdo->prepare("
            UPDATE tasks SET
                task_date = ?,
                due_date = ?,
                testing_type = ?,
                fw_version_current = ?,
                fw_version_prev = ?,
                fw_version_rec = ?,
                fw_type = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['task_date'],
            $_POST['due_date'],
            $new_type,
            $_POST['fw_curr'],
            $_POST['fw_prev'],
            $_POST['fw_rec'],
            $_POST['fw_type'],
            $task_id
        ]);

        // 3. Handle assignments based on workflow type
        if ($current_type !== $new_type) {
            // Type changed workflow
            if ($new_type === 'Regression') {
                $stmt = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ?");
                $stmt->execute([$task_id]);
                foreach ($selected_printers as $pid) {
                    $reg_url = $_POST['regression_urls'][$pid] ?? '';
                    $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation, regression_url) VALUES (?, ?, ?, 'Main', ?)");
                    $stmt->execute([$task_id, $pid, $_SESSION['user_id'], $reg_url]);
                }
            } elseif ($new_type === 'Smoke') {
                $stmt = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ?");
                $stmt->execute([$task_id]);
                foreach ($selected_printers as $pid) {
                    $assigned_users = $_POST['assignments'][$pid] ?? [];
                    $main_tester = $_POST['main_tester'][$pid] ?? null;
                    if (empty($assigned_users)) {
                        $assigned_users = [$_SESSION['user_id']];
                        $main_tester = $_SESSION['user_id'];
                    }
                    foreach ($assigned_users as $uid) {
                        $role = ($uid == $main_tester) ? 'Main' : 'Support';
                        $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$task_id, $pid, $uid, $role]);
                    }
                }
            }
        } else {
            // Type did NOT change
            $type = $_POST['testing_type'];

            if ($type === 'Smoke') {
                // --- CRITICAL FIX: Fetch EXISTING printers for this task ---
                $stmt = $pdo->prepare("SELECT DISTINCT printer_id FROM task_assignments WHERE task_id = ?");
                $stmt->execute([$task_id]);
                $existingPrinters = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // --- 1. DELETE printers that were UNCHECKED (User removed them) ---
                $printersToRemove = array_diff($existingPrinters, $selected_printers);
                if (!empty($printersToRemove)) {
                    $placeholders = implode(',', array_fill(0, count($printersToRemove), '?'));
                    $stmt = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ? AND printer_id IN ($placeholders)");
                    $stmt->execute(array_merge([$task_id], $printersToRemove));
                }

                // --- 2. Process NEWLY SELECTED or REMAINING printers ---
                foreach ($selected_printers as $pid) {
                    $new_user_ids = $_POST['assignments'][$pid] ?? [];
                    $main_tester = $_POST['main_tester'][$pid] ?? null;

                    if (empty($new_user_ids)) {
                        $new_user_ids = [$_SESSION['user_id']];
                        $main_tester = $_SESSION['user_id'];
                    }

                    // Fetch existing users for this specific printer
                    $stmt = $pdo->prepare("SELECT user_id FROM task_assignments WHERE task_id = ? AND printer_id = ?");
                    $stmt->execute([$task_id, $pid]);
                    $existing_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    // 3. DELETE users that are in DB but NOT in new list
                    $users_to_delete = array_diff($existing_user_ids, $new_user_ids);
                    if (!empty($users_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($users_to_delete), '?'));
                        $stmt = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ? AND printer_id = ? AND user_id IN ($placeholders)");
                        $stmt->execute(array_merge([$task_id, $pid], $users_to_delete));
                    }

                    // 4. INSERT or UPDATE remaining users
                    foreach ($new_user_ids as $uid) {
                        $role = ($uid == $main_tester) ? 'Main' : 'Support';
                        $stmt = $pdo->prepare("SELECT id FROM task_assignments WHERE task_id = ? AND printer_id = ? AND user_id = ?");
                        $stmt->execute([$task_id, $pid, $uid]);
                        
                        if ($stmt->fetch()) {
                            $stmt = $pdo->prepare("UPDATE task_assignments SET designation = ? WHERE task_id = ? AND printer_id = ? AND user_id = ?");
                            $stmt->execute([$role, $task_id, $pid, $uid]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$task_id, $pid, $uid, $role]);
                        }
                    }
                }
            } else {
                // REGRESSION LOGIC (Keep as is)
                $stmt = $pdo->prepare("SELECT printer_id FROM task_assignments WHERE task_id = ?");
                $stmt->execute([$task_id]);
                $existingPrinters = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($existingPrinters)) {
                    $printersToRemove = array_diff($existingPrinters, $selected_printers);
                    if (!empty($printersToRemove)) {
                        $placeholders = implode(',', array_fill(0, count($printersToRemove), '?'));
                        $stmt = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ? AND printer_id IN ($placeholders)");
                        $stmt->execute(array_merge([$task_id], $printersToRemove));
                    }
                }

                foreach ($selected_printers as $pid) {
                    $stmt = $pdo->prepare("SELECT user_id, designation, regression_url FROM task_assignments WHERE task_id = ? AND printer_id = ?");
                    $stmt->execute([$task_id, $pid]);
                    $existingAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $hasExisting = !empty($existingAssignments);

                    $reg_url = $_POST['regression_urls'][$pid] ?? '';
                    
                    if ($hasExisting) {
                        $stmt = $pdo->prepare("UPDATE task_assignments SET regression_url = ? WHERE task_id = ? AND printer_id = ?");
                        $stmt->execute([$reg_url, $task_id, $pid]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, printer_id, user_id, designation, regression_url) VALUES (?, ?, ?, 'Main', ?)");
                        $stmt->execute([$task_id, $pid, $_SESSION['user_id'], $reg_url]);
                    }
                }
            }
        }

        $pdo->commit();
        Helper::setFlash("Task updated successfully! Assignments and test results preserved.", "success");
        unset($_SESSION['edit_task_form']);
        
        // --- FIX: Redirect to tasks.php without forcing a printer_id ---
        header("Location: ../tasks.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['edit_task_form'] = $_POST;
        Helper::setFlash("Error updating task: " . $e->getMessage(), "error");
        
        // --- FIX: Redirect to edit_task.php with ONLY the task_id ---
        header("Location: ../edit_task.php?id=" . $_POST['task_id']);
        exit();
    }
}
?>