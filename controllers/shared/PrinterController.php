<?php
// controllers/shared/PrinterController.php

require_once __DIR__ . '/../../configs/db.php';
require_once __DIR__ . '/../../configs/helper.php';

Helper::requireManagementRole();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    if ($action === 'toggle_status') {
        $printer_id = (int) $_POST['printer_id'];
        $new_status = ($_POST['new_status'] === 'active') ? 'active' : 'inactive';
        $pdo->prepare("UPDATE printers SET status = ? WHERE id = ?")->execute([$new_status, $printer_id]);
        echo json_encode(['success' => true, 'new_status' => $new_status]);
        exit();
    }
    
    if ($action === 'validate_printer_name') {
        $printer_id = (int) $_POST['printer_id'];
        $name = trim($_POST['name']);
        $stmt = $pdo->prepare("SELECT id FROM printers WHERE model_name = ? AND id != ?");
        $stmt->execute([$name, $printer_id]);
        if ($stmt->fetch()) {
            echo json_encode(['valid' => false, 'message' => 'Printer name already exists.']);
        } else {
            echo json_encode(['valid' => true]);
        }
        exit();
    }
    
    if ($action === 'update_printer_profile') {
        $printer_id = (int) $_POST['printer_id'];
        $new_name = trim($_POST['new_name'] ?? '');
        $base64_image = $_POST['cropped_image'] ?? null;
        $reset_image = isset($_POST['reset_image']) && $_POST['reset_image'] == '1';

        $pdo->beginTransaction();

        // ============================================================
        // FIX: ALWAYS USE THE NEW NAME FOR THE FILENAME
        // ============================================================
        // A. Update Name if provided
        if (!empty($new_name)) {
            $checkStmt = $pdo->prepare("SELECT id FROM printers WHERE model_name = ? AND id != ?");
            $checkStmt->execute([$new_name, $printer_id]);
            if ($checkStmt->fetch()) {
                throw new Exception("Printer name already exists.");
            }
            $stmt = $pdo->prepare("UPDATE printers SET model_name = ? WHERE id = ?");
            $stmt->execute([$new_name, $printer_id]);
        }

        // B. Handle Image
        $printer_path = null;
        if ($reset_image) {
            // Set to NULL to reset to default icon
            $printer_path = null;
        } elseif (!empty($base64_image)) {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $type)) {
                $data = substr($base64_image, strpos($base64_image, ',') + 1);
                $data = base64_decode($data);
                $dir = __DIR__ . '/../../imgs/printers/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                // ============================================================
                // CRITICAL FIX: Use the NEW name for the filename
                // ============================================================
                $safe_name = strtolower(str_replace([' ', '/', '\\'], '_', trim($new_name)));
                
                // If for some reason new_name is empty (shouldn't happen), fallback to fetching old name
                if (empty($safe_name)) {
                    $oldNameStmt = $pdo->prepare("SELECT model_name FROM printers WHERE id = ?");
                    $oldNameStmt->execute([$printer_id]);
                    $safe_name = strtolower(str_replace([' ', '/', '\\'], '_', trim($oldNameStmt->fetchColumn())));
                }
                
                $filename = $dir . $safe_name . '.png';
                file_put_contents($filename, $data);
                $printer_path = 'imgs/printers/' . $safe_name . '.png';
            }
        }

        // ============================================================
        // FIX: ALWAYS RUN THE UPDATE IF WE HAVE A VALID PATH OR RESET
        // ============================================================
        if ($printer_path !== null) {
            $stmt = $pdo->prepare("UPDATE printers SET printer_path = ? WHERE id = ?");
            $stmt->execute([$printer_path, $printer_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'new_name' => $new_name, 'message' => 'Printer profile updated successfully!']);
        exit();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}