<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

// Only leads and admins can delete firmware
Helper::requireRole(['lead', 'admin']);

// Get the firmware string and type from the POST request
$fw = $_POST['fw'] ?? '';
$type = $_POST['type'] ?? '';

if (empty($fw) || empty($type)) {
    echo json_encode(['success' => false, 'error' => 'Missing firmware or type.']);
    exit;
}

try {
    // Since firmware is stored as plain text in the 'tasks' table, 
    // we simply update any task that has this exact firmware string to NULL.
    // This preserves task history but removes the firmware from the available pool.
    $stmt = $pdo->prepare("UPDATE tasks SET fw_version_current = NULL WHERE fw_version_current = ? AND fw_type = ?");
    $stmt->execute([$fw, $type]);
    
    // Also check prev and rec columns to be thorough
    $stmt = $pdo->prepare("UPDATE tasks SET fw_version_prev = NULL WHERE fw_version_prev = ? AND fw_type = ?");
    $stmt->execute([$fw, $type]);
    
    $stmt = $pdo->prepare("UPDATE tasks SET fw_version_rec = NULL WHERE fw_version_rec = ? AND fw_type = ?");
    $stmt->execute([$fw, $type]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}