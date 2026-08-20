<?php
// shared/printer_manager.php

require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireManagementRole();

function parseTcInputs($codes, $titles, $combined) {
    if (!empty(array_filter(array_map('trim', $codes)))) {
        return [$codes, $titles];
    }
    $parsedCodes = [];
    $parsedTitles = [];
    foreach ($combined as $c) {
        $c = trim($c);
        if (empty($c)) {
            $parsedCodes[] = '';
            $parsedTitles[] = '';
            continue;
        }
        if (strpos($c, ' - ') !== false) {
            $parts = explode(' - ', $c, 2);
            $parsedCodes[] = trim($parts[0]);
            $parsedTitles[] = trim($parts[1]);
        } else {
            $parsedCodes[] = $c;
            $parsedTitles[] = $c;
        }
    }
    return [$parsedCodes, $parsedTitles];
}

try {
    $pdo->exec("ALTER TABLE printers ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
} catch (Exception $e) {}

// Clear expired reopen session if the row doesn't exist anymore
if (isset($_SESSION['reopen_printer_id'])) {
    $checkStmt = $pdo->prepare("SELECT id FROM printers WHERE id = ?");
    $checkStmt->execute([$_SESSION['reopen_printer_id']]);
    if (!$checkStmt->fetch()) {
        unset($_SESSION['reopen_printer_id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_delete_cases'])) {
        $tcIds = explode(',', $_POST['bulk_delete_cases']);
        $tcIds = array_filter(array_map('intval', $tcIds));
        $expanded_id = $_POST['expanded_printer_id'] ?? null;
        $printer_name = 'Unknown Printer';
        
        if (!empty($tcIds)) {
            if ($expanded_id) {
                $stmt = $pdo->prepare("SELECT model_name FROM printers WHERE id = ?");
                $stmt->execute([$expanded_id]);
                $printer_name = $stmt->fetchColumn() ?: 'Unknown Printer';
            }
            $placeholders = implode(',', array_fill(0, count($tcIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM test_cases WHERE id IN ($placeholders)");
            $stmt->execute($tcIds);
            $count = count($tcIds);
            Helper::setFlash("$count test case(s) deleted from $printer_name.", "success");
            if ($expanded_id) {
                $_SESSION['reopen_printer_id'] = $expanded_id;
            }
        }
        
        echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
        exit();
    }

    if (isset($_POST['add_printer'])) {
        $pdo->beginTransaction();
        try {
            $model_name = trim($_POST['model_name']);
            
            $chk = $pdo->prepare("SELECT id FROM printers WHERE model_name = ?");
            $chk->execute([$model_name]);
            $existingId = $chk->fetchColumn();

            if ($existingId) {
                $stmtDel = $pdo->prepare("DELETE FROM test_cases WHERE printer_model = ?");
                $stmtDel->execute([$model_name]);
                $printer_id = $existingId;
            } else {
                $pdo->prepare("INSERT INTO printers (model_name) VALUES (?)")->execute([$model_name]);
                $printer_id = $pdo->lastInsertId();
            }
            
            $codes = $_POST['case_code'] ?? [];
            $titles = $_POST['case_title'] ?? [];
            $combined = $_POST['_tc_combined'] ?? [];

            list($codes, $titles) = parseTcInputs($codes, $titles, $combined);
            
            $stmt_tc = $pdo->prepare("INSERT INTO test_cases (printer_model, case_code, title) VALUES (?, ?, ?)");
            for($i=0; $i<count($codes); $i++) {
                $code = trim($codes[$i]);
                if(!empty($code)) {
                    $stmt_tc->execute([$model_name, $code, trim($titles[$i])]);
                }
            }
            $pdo->commit();
            Helper::setFlash("Printer '$model_name' and Test Cases saved.", "success");
        } catch(Exception $e) {
            $pdo->rollBack();
            Helper::setFlash("Error: " . $e->getMessage(), "error");
        }
        
        echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
        exit();
    } 

    elseif (isset($_POST['add_cases_to_printer'])) {
        $pdo->beginTransaction();
        try {
            $printer_model = trim($_POST['target_printer_model']);
            
            $codes = $_POST['case_code'] ?? [];
            $titles = $_POST['case_title'] ?? [];
            $combined = $_POST['_ptc_combined'] ?? [];
            list($codes, $titles) = parseTcInputs($codes, $titles, $combined);
            
            $ex_stmt = $pdo->prepare("SELECT case_code FROM test_cases WHERE printer_model = ?");
            $ex_stmt->execute([$printer_model]);
            $existing_codes = $ex_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt_tc = $pdo->prepare("INSERT INTO test_cases (printer_model, case_code, title) VALUES (?, ?, ?)");
            $added = 0;
            $skipped = 0;

            for($i=0; $i<count($codes); $i++) {
                $code = trim($codes[$i]);
                $title = trim($titles[$i]);
                if(!empty($code)) {
                    if(!in_array($code, $existing_codes)) {
                        $stmt_tc->execute([$printer_model, $code, $title]);
                        $existing_codes[] = $code;
                        $added++;
                    } else {
                        $skipped++;
                    }
                }
            }

            $pdo->commit();
            if ($added > 0) {
                $msg = "$added test case(s) added to '$printer_model'.";
                if ($skipped > 0) $msg .= " ($skipped duplicate(s) skipped.)";
                Helper::setFlash($msg, "success");
            } elseif ($skipped > 0) {
                Helper::setFlash("All selected test case(s) already exist for '$printer_model'.", "error");
            } else {
                Helper::setFlash("No valid test cases were entered.", "error");
            }
        } catch(Exception $e) {
            $pdo->rollBack();
            Helper::setFlash("Error adding test cases: " . $e->getMessage(), "error");
        }
        
        $stmtId = $pdo->prepare("SELECT id FROM printers WHERE model_name = ?");
        $stmtId->execute([$printer_model]);
        $pid = $stmtId->fetchColumn();
        if ($pid) {
            $_SESSION['reopen_printer_id'] = $pid;
        }

        echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
        exit();
    }
}

// --- Fetch Data ---
$printers = $pdo->query("SELECT p.*, COALESCE(p.status, 'active') as status, (SELECT COUNT(*) FROM test_cases tc WHERE tc.printer_model = p.model_name) as case_count FROM printers p ORDER BY p.model_name")->fetchAll();
$all_testcases = $pdo->query("SELECT * FROM test_cases ORDER BY case_code ASC")->fetchAll(PDO::FETCH_ASSOC);

$tc_map = [];
foreach ($all_testcases as $tc) {
    $model = $tc['printer_model'];
    if (!isset($tc_map[$model])) $tc_map[$model] = [];
    $tc_map[$model][] = $tc;
}
foreach ($tc_map as $model => &$cases) {
    usort($cases, function($a, $b) { return strnatcmp(trim($a['case_code']), trim($b['case_code'])); });
}
unset($cases);

$combined_options = [];
$seen_pairs = [];
foreach ($all_testcases as $tc) {
    $code = trim($tc['case_code']);
    $title = trim($tc['title']);
    $pair_key = $code . '|||' . $title;
    if (!isset($seen_pairs[$pair_key]) && ($code !== '' || $title !== '')) {
        $seen_pairs[$pair_key] = true;
        $display = trim($code . ' - ' . $title, ' -');
        if ($display !== '') $combined_options[$display] = $display;
    }
}
ksort($combined_options);

function getPrinterIcon(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'flare')) return 'local_fire_department';
    if (str_contains($n, 'ray'))   return 'bolt';
    if (str_contains($n, 'mfp'))  return 'content_copy';
    if (str_contains($n, 'sfp'))  return 'print';
    return 'print';
}

// ============================================================
// FIX: ROBUST renderPrinterImage WITH FILE EXISTENCE CHECK
// ============================================================
function renderPrinterImage($p) {
    $path = $p['printer_path'] ?? '';
    
    // 1. If there is a valid image path, try to display it
    if (!empty($path) && (str_contains($path, '/') || str_contains($path, '.'))) {
        // Determine the correct relative path for the browser
        // admin_printers.php is inside /admin/ folder, so we prepend '../'
        $displayPath = '../' . ltrim($path, '/');
        
        // Check if the file actually exists on the server (using absolute server path)
        $fullServerPath = __DIR__ . '/../../' . ltrim($path, '/');
        if (file_exists($fullServerPath)) {
            // Use cache-buster to force browser to reload the image
            return "<img src='" . htmlspecialchars($displayPath) . "?v=" . time() . "' style='width:100%; height:100%; object-fit:cover; border-radius:50%;' alt='Printer Image'>";
        } else {
            // File is missing on disk - fallback to icon
            $iconText = getPrinterIcon($p['model_name']);
            return "<span class='material-symbols-outlined' style='font-size: 18px; color: var(--primary);'>" . htmlspecialchars($iconText) . "</span>";
        }
    }
    
    // 2. Fallback to default icon
    $iconText = $path ?: getPrinterIcon($p['model_name']);
    return "<span class='material-symbols-outlined' style='font-size: 18px; color: var(--primary);'>" . htmlspecialchars($iconText) . "</span>";
}
// ============================================================

// --- Collect assigned test case codes to pass to JavaScript ---
$assignedCodesMap = [];
foreach ($printers as $p) {
    $model = $p['model_name'];
    $stmt = $pdo->prepare("SELECT case_code FROM test_cases WHERE printer_model = ?");
    $stmt->execute([$model]);
    $assignedCodesMap[$model] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<style>
    /* --- STYLES --- */
    .printer-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; transition: all 0.3s; }
    .printer-status-dot.active { background: var(--success, #10b981); box-shadow: 0 0 6px rgba(16,185,129,0.5); }
    .printer-status-dot.inactive { background: var(--text-muted, #9ca3af); }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.02em; text-transform: uppercase; white-space: nowrap; transition: all 0.2s; }
    .status-badge.active { background: rgba(16,185,129,0.1); color: var(--success, #10b981); }
    .status-badge.inactive { background: rgba(239,68,68,0.08); color: var(--error, #ef4444); }
    .main-row.is-inactive td { opacity: 0.5; transition: opacity 0.2s; }
    .main-row.is-inactive:hover td { opacity: 0.75; }
    .printer-status-section { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); width: 100%; }
    .status-toggle-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 18px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; border: 1.5px solid; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; min-width: 120px; }
    .status-toggle-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .status-toggle-btn.enable { background: rgba(16,185,129,0.08); color: var(--success, #10b981); border-color: var(--success, #10b981); }
    .status-toggle-btn.enable:hover:not(:disabled) { background: var(--success, #10b981); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.3); transform: translateY(-1px); }
    .status-toggle-btn.disable { background: rgba(239,68,68,0.06); color: var(--error, #ef4444); border-color: var(--error, #ef4444); }
    .status-toggle-btn.disable:hover:not(:disabled) { background: var(--error, #ef4444); color: white; box-shadow: 0 4px 12px rgba(239,68,68,0.3); transform: translateY(-1px); }
    .status-toggle-btn .material-symbols-outlined { font-size: 16px; }
    .printer-left.is-inactive-card { position: relative; }
    .printer-left.is-inactive-card::after { content: 'DISABLED'; position: absolute; top: 12px; right: -6px; background: var(--error, #ef4444); color: white; font-size: 0.55rem; font-weight: 800; letter-spacing: 0.1em; padding: 2px 8px 3px; border-radius: 4px 0 4px 4px; box-shadow: 0 2px 6px rgba(239,68,68,0.3); }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    /* ACCORDION */
    .accordion-wrapper { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: var(--bg-body); }
    .accordion-wrapper.open { grid-template-rows: 1fr; }
    .expanded-content { overflow: hidden; min-height: 0; display: flex; flex-wrap: wrap; align-items: center; opacity: 0; transition: opacity 0.2s ease; border-top: 1px solid var(--border); }
    .accordion-wrapper.open .expanded-content { opacity: 1; transition: opacity 0.3s ease 0.1s; }
    
    .printer-detail-grid { display: flex; gap: 24px; padding: 24px; align-items: stretch; width: 100%; box-sizing: border-box; }
    @media (max-width: 900px) { .printer-detail-grid { flex-direction: column; } }
    .printer-left { background: var(--bg-body); border: 1px solid var(--border); border-radius: 12px; padding: 24px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; width: 240px; flex-shrink: 0; box-sizing: border-box; justify-content: center; }
    .printer-photo-placeholder { width: 80px; height: 80px; border-radius: 50%; background: var(--bg-surface); border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; cursor: pointer; transition: all 0.2s ease; }
    .printer-photo-placeholder:hover { border-color: var(--primary); transform: scale(1.05); box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.15); }
    .printer-desc-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 6px; }
    .printer-right { display: flex; flex-direction: column; flex: 1; min-width: 0; }
    .tc-chip-container { display: flex; flex-wrap: wrap; gap: 10px; align-content: flex-start; flex: 1; padding-bottom: 24px; min-height: 60px; }
    .tc-edit-chip { padding: 0; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-body); font-size: 0.8rem; font-weight: 600; color: var(--text-main); transition: all 0.15s; display: inline-flex; align-items: stretch; font-family: 'Inter', sans-serif; overflow: hidden; position: relative; }
    .tc-edit-chip:hover { border-color: var(--primary); background: var(--bg-surface); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
    .chip-main { display: inline-flex; align-items: center; gap: 6px; padding: 6px 8px 6px 12px; cursor: pointer; transition: color 0.15s; }
    .tc-edit-chip:hover .chip-main { color: var(--primary); }
    .chip-main .code { color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; transition: color 0.15s; }
    .tc-edit-chip:hover .chip-main .code { color: var(--primary); opacity: 0.8; }
    .tc-edit-chip.select-mode { padding-left: 8px; align-items: center; }
    .tc-edit-chip.select-mode .chip-checkbox { display: flex !important; margin-right: 6px; }
    .chip-checkbox { display: none !important; width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; flex-shrink: 0; }
    .tc-edit-chip.select-mode .chip-main { padding-left: 0; }
    .tc-edit-chip.selected-chip { border-color: var(--primary); background: var(--bg-surface); box-shadow: 0 0 0 2px rgba(2, 136, 209, 0.2); }
    .printer-actions { display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap; margin-top: auto; }
    .tc-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 30px 20px; width: 100%; border: 2px dashed var(--border); border-radius: 10px; background: var(--bg-surface); }
    .tc-empty-state .material-symbols-outlined { font-size: 36px; color: var(--text-muted); opacity: 0.5; }
    .tc-empty-state p { margin: 0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }
    .tc-empty-state .btn { font-size: 0.82rem; height: 36px; padding: 0 16px; }
    .dynamic-tc-row { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; padding: 8px; background: var(--bg-body); border-radius: 8px; border: 1px dashed var(--border); }
    .dynamic-tc-row input { flex: 1; margin: 0 !important; }
    .dynamic-tc-row .btn-remove { background: var(--error-bg); color: var(--error); border: none; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .dynamic-tc-row .btn-remove:hover { background: var(--error); color: white; }
    .tc-combined-wrapper { flex: 1; min-width: 0; }
    .tc-combined-wrapper .enh-trigger { height: 42px !important; min-height: 42px !important; border-radius: 6px !important; }
    .tc-combined-wrapper .enh-dropdown { width: 100%; }
    
    /* --- REFINED MODAL LAYOUT FOR BOTH ADD PRINTER AND ADD CASES --- */
    .refined-modal-body { display: flex; flex-direction: column; max-height: 75vh; padding: 0 !important; overflow: hidden !important; }
    .refined-modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--bg-surface); border-radius: 16px 16px 0 0; }
    .refined-modal-content { flex: 1; overflow: hidden; display: flex; flex-direction: column; padding: 20px 24px; background: var(--bg-surface); }
    .refined-scroll-area { flex: 1; overflow-y: auto; padding-right: 8px; margin-bottom: 12px; }
    .refined-scroll-area::-webkit-scrollbar { width: 6px; }
    .refined-scroll-area::-webkit-scrollbar-track { background: var(--bg-surface); border-radius: 3px; }
    .refined-scroll-area::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 3px; }
    .refined-scroll-area::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    
    .refined-modal-footer { flex-shrink: 0; padding: 16px 24px 24px 24px; border-top: 1px solid var(--border); background: var(--bg-surface); border-radius: 0 0 16px 16px; display: flex; gap: 10px; align-items: center; }
    .refined-modal-footer .btn { flex: 1; }

    /* --- BULK SELECT BAR --- */
    .bulk-select-bar { position: fixed; bottom: -80px; left: 50%; transform: translateX(-50%); background: var(--bg-surface); border: 1px solid var(--border); border-radius: 14px; padding: 12px 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3); z-index: 200; transition: bottom 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
    .bulk-select-bar.visible { bottom: 28px; }
    .bulk-select-bar .count-badge { background: var(--primary); color: white; font-size: 0.75rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; white-space: nowrap; }
    .bulk-select-bar .btn-bulk-delete { background: var(--error); color: white; border: none; padding: 8px 18px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s; font-family: var(--font-body); }
    .bulk-select-bar .btn-bulk-delete:hover { filter: brightness(1.1); transform: scale(1.03); }
    .bulk-select-bar .btn-bulk-delete:disabled { opacity: 0.5; cursor: not-allowed; filter: none; transform: none; }
    .bulk-select-bar .btn-bulk-delete-all { background: transparent; color: var(--error); border: 1px solid var(--error); padding: 8px 18px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s; font-family: var(--font-body); }
    .bulk-select-bar .btn-bulk-delete-all:hover { background: var(--error); color: white; }
    .bulk-select-bar .btn-cancel-select { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 8px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s; font-family: var(--font-body); }
    .bulk-select-bar .btn-cancel-select:hover { border-color: var(--text-muted); color: var(--text-main); }
    .btn-select-mode { border: 1px solid var(--border) !important; color: var(--text-muted) !important; transition: all 0.15s; }
    .btn-select-mode:hover { border-color: var(--primary) !important; color: var(--primary) !important; }
    .btn-select-mode.active { border-color: var(--primary) !important; color: var(--primary) !important; background: rgba(2, 136, 209, 0.08) !important; }
    
    .edit-name-group { margin-bottom: 16px; width: 100%; position: relative; }
    .edit-name-group label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; letter-spacing: 0.05em; }
    .edit-name-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-body); color: var(--text-main); font-size: 0.95rem; font-family: var(--font-body); outline: none; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; }
    .edit-name-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1); }
    .edit-name-input.error { border-color: var(--error) !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important; background: var(--error-bg) !important; }
    .edit-name-error-msg { display: none; color: var(--error); font-size: 0.8rem; font-weight: 600; margin-top: 6px; padding-left: 4px; }
    .edit-name-error-msg.visible { display: block; }
</style>

<!-- ================== HTML ================== -->
<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <h1 style="margin:0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">print</span>
                Printers
            </h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: auto; height: 42px; border-radius: 8px;" onclick="openModal('addPrinterModal')">
                    <span class="material-symbols-outlined" style="font-size: 20px;">add</span> Add Printer
                </button>
            </div>
        </div>
        
        <div class="d-card span-full" id="adminPrintersPage" style="margin-bottom: 24px;">
            <div class="d-card-body" style="padding-top: 0;">
                <table class="d-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Model Name</th>
                            <th style="width: 120px;">Status</th>
                            <th>Number of Test Cases</th>
                            <th style="width: 50px; text-align:center;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($printers as $p): ?>
                        <?php 
                            $rowId = "printer_" . $p['id']; 
                            $cases = $tc_map[$p['model_name']] ?? [];
                            $status = $p['status'] ?? 'active';
                            $isActive = ($status === 'active');
                        ?>
                        <!-- MAIN ROW -->
                        <tr class="expand-trigger main-row <?= !$isActive ? 'is-inactive' : '' ?>" onclick="toggleRow('<?= $rowId ?>', this)" data-printer-id="<?= $p['id'] ?>">
                            <td style="font-weight: 700; font-size: 0.95rem;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="printer-status-dot <?= $isActive ? 'active' : 'inactive' ?>" id="dot_<?= $p['id'] ?>"></span>
                                    <span style="display:inline-flex; width: 24px; height: 24px; align-items:center; justify-content:center;">
                                        <?= renderPrinterImage($p) ?>
                                    </span>
                                    <?= htmlspecialchars($p['model_name']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?= $isActive ? 'active' : 'inactive' ?>" id="badge_<?= $p['id'] ?>">
                                    <span class="material-symbols-outlined" style="font-size:14px;"><?= $isActive ? 'check_circle' : 'cancel' ?></span>
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $p['case_count'] > 0 ? 'badge-pass' : 'badge-pending' ?>"><?= $p['case_count'] ?> Cases</span>
                            </td>
                            <td style="text-align:center;">
                                <span class="material-symbols-outlined chevron-icon" id="chev-<?= $rowId ?>">expand_more</span>
                            </td>
                        </tr>

                        <!-- EXPANDED DROPDOWN ROW -->
                        <tr class="expanded-row">
                            <td colspan="4" style="padding: 0 !important; border-bottom: none !important;">
                                <div class="accordion-wrapper" id="<?= $rowId ?>">
                                    <div class="expanded-content">
                                        <div class="printer-detail-grid">
                                            <div class="printer-left <?= !$isActive ? 'is-inactive-card' : '' ?>" id="left_panel_<?= $p['id'] ?>">
                                                <div class="printer-photo-placeholder" onclick="openEditPrinterModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['model_name'], ENT_QUOTES) ?>')">
                                                    <?= renderPrinterImage($p) ?>
                                                </div>
                                                <div class="printer-status-section">
                                                    <span style="font-size:0.65rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Printer Status</span>
                                                    <button type="button" class="status-toggle-btn <?= $isActive ? 'disable' : 'enable' ?>" id="toggle_btn_<?= $p['id'] ?>" data-status="<?= $status ?>" onclick="togglePrinterStatus(<?= $p['id'] ?>, event)">
                                                        <span class="material-symbols-outlined"><?= $isActive ? 'power_settings_new' : 'flash_on' ?></span>
                                                        <?= $isActive ? 'Disable Printer' : 'Enable Printer' ?>
                                                    </button>
                                                    <span style="font-size:0.68rem; color:var(--text-muted); line-height:1.4;" id="status_hint_<?= $p['id'] ?>">
                                                        <?= $isActive ? 'Printer is active and available for testing.' : 'Printer is disabled.' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="printer-right">
                                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                                    <span class="printer-desc-label" style="margin-bottom:0; display:block;">Assigned Test Cases</span>
                                                    <?php if(!empty($cases)): ?>
                                                    <button type="button" class="btn-select-mode btn-mini ghost" onclick="toggleSelectMode(<?= $p['id'] ?>)" id="selectBtn_<?= $p['id'] ?>" style="height:30px; padding:0 12px; font-size:0.72rem;">
                                                        <span class="material-symbols-outlined" style="font-size:14px;">checklist</span> Select
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tc-chip-container" id="chipContainer_<?= $p['id'] ?>">
                                                    <?php if(empty($cases)): ?>
                                                        <div class="tc-empty-state">
                                                            <span class="material-symbols-outlined">assignment_add</span>
                                                            <p>No test cases assigned yet</p>
                                                            <button type="button" class="btn" onclick="event.stopPropagation(); openAddCasesToPrinterModal('<?= htmlspecialchars($p['model_name'], ENT_QUOTES) ?>')">Add Test Cases</button>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php foreach($cases as $tc): ?>
                                                            <div class="tc-edit-chip" id="chip_<?= $p['id'] ?>_<?= $tc['id'] ?>">
                                                                <input type="checkbox" class="chip-checkbox" data-pid="<?= $p['id'] ?>" data-tcid="<?= $tc['id'] ?>" onchange="updateBulkSelection()">
                                                                <span class="chip-main" style="cursor: default;">
                                                                    <span class="code">#<?= htmlspecialchars($tc['case_code']) ?></span>
                                                                    <span class="title"><?= htmlspecialchars($tc['title']) ?></span>
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="printer-actions">
                                                    <?php if(!empty($cases)): ?>
                                                    <button type="button" class="btn-mini ghost" id="addCaseBtn_<?= $p['id'] ?>" onclick="openAddCasesToPrinterModal('<?= htmlspecialchars($p['model_name'], ENT_QUOTES) ?>')" style="color: var(--primary); border-color: var(--primary);">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">add_circle_outline</span> Add Case
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($printers)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted); font-style:italic;">No printers added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ======================== MODALS ======================== -->

<!-- ======================== ADD PRINTER MODAL (REFINED LAYOUT) ======================== -->
<div class="modal-overlay" id="addPrinterModal">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header refined-modal-header">
            <h3>Add New Printer</h3>
            <button type="button" class="modal-close-btn" onclick="resetAndCloseModal('addPrinterModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        
        <form method="POST" class="refined-modal-body" style="padding: 0 !important; overflow: hidden !important;">
            <input type="hidden" name="add_printer" value="1">
            
            <div class="refined-modal-content">
                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom: 20px; flex-shrink: 0;">Define the printer model. Test cases defined here are applied to <strong>Smoke Tests</strong> only.</p>
                
                <div class="form-group" style="flex-shrink: 0;">
                    <input type="text" name="model_name" id="addPrinterModelName" class="form-control" required placeholder=" ">
                    <label class="form-label">Model Name (e.g. Pixiu MFP)</label>
                </div>
                
                <div style="border-top: 1px solid var(--border); padding-top: 16px; flex-shrink: 0; margin-top: 16px;">
                    <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:12px; display:block;">Initial Test Cases (Optional)</span>
                </div>

                <!-- SCROLLABLE AREA FOR ROWS -->
                <div class="refined-scroll-area" id="dynamicTcList">
                    <?php for ($i = 0; $i < 30; $i++): ?>
                        <div class="dynamic-tc-row tc-sync-group" id="tc_row_<?= $i ?>" style="<?= $i === 0 ? '' : 'display:none;' ?>; margin-bottom: 10px; padding: 8px; background: var(--bg-surface); border-radius: 8px; border: 1px solid var(--border);">
                            <input type="hidden" name="case_code[]" value="">
                            <input type="hidden" name="case_title[]" value="">
                            <div class="tc-combined-wrapper" style="flex: 1; min-width: 0;">
                                <?= Helper::enhancedDropdown([
                                    'name' => '_tc_combined[]',
                                    'placeholder' => 'Search or type: Case ID - Name',
                                    'creatable' => false,
                                    'options' => $combined_options,
                                    'selected' => ''
                                ]) ?>
                            </div>
                            <button type="button" class="btn-remove" onclick="hideTcRow(<?= $i ?>)" style="flex-shrink: 0; margin-left: 8px;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                            </button>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- ADD ANOTHER BUTTON (Inside Scroll Area Bottom) -->
                <button type="button" class="btn ghost" onclick="addTcRowDynamic()" style="border: 1px dashed var(--border); color: var(--text-main); background-color: var(--bg-body); width: 100%; margin-top: 12px; flex-shrink: 0;">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span> Add Test Case
                </button>
            </div>

            <!-- FIXED FOOTER WITH BUTTONS -->
            <div class="refined-modal-footer">
                <button type="submit" name="add_printer" class="btn">Save Printer</button>
                <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="resetAndCloseModal('addPrinterModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================== ADD CASES TO EXISTING PRINTER MODAL (REFINED LAYOUT) ======================== -->
<div class="modal-overlay" id="addCasesToPrinterModal">
    <div class="modal-box" style="max-width: 620px;">
        <div class="modal-header refined-modal-header">
            <h3>Add Test Cases to Printer</h3>
            <button type="button" class="modal-close-btn" onclick="resetAndCloseModal('addCasesToPrinterModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        
        <form method="POST" class="refined-modal-body" style="padding: 0 !important; overflow: hidden !important;">
            <input type="hidden" name="add_cases_to_printer" value="1">
            <input type="hidden" name="target_printer_model" id="targetPrinterModel">

            <div class="refined-modal-content">
                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom: 12px; flex-shrink: 0;">
                    Adding test cases to: <strong id="targetPrinterModelDisplay" style="color: var(--primary);"></strong>
                    <br><span style="font-size:0.75rem;">Select existing cases from the pool or type new ones. <strong>Duplicates will be skipped.</strong></span>
                </p>
                
                <div style="border-top: 1px solid var(--border); padding-top: 12px; flex-shrink: 0;">
                    <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">Test Cases</span>
                </div>

                <!-- SCROLLABLE AREA FOR ROWS -->
                <div class="refined-scroll-area" id="dynamicPtcList">
                    <?php for ($i = 0; $i < 20; $i++): ?>
                        <div class="dynamic-tc-row tc-sync-group" id="ptc_row_<?= $i ?>" style="<?= $i === 0 ? '' : 'display:none;' ?>; margin-bottom: 10px; padding: 8px; background: var(--bg-surface); border-radius: 8px; border: 1px solid var(--border);">
                            <input type="hidden" name="case_code[]" value="">
                            <input type="hidden" name="case_title[]" value="">
                            <div class="tc-combined-wrapper" style="flex: 1; min-width: 0;">
                                <?= Helper::enhancedDropdown([
                                    'name' => '_ptc_combined[]',
                                    'placeholder' => 'Search or type: Case ID - Name',
                                    'creatable' => false,
                                    'options' => $combined_options,
                                    'selected' => ''
                                ]) ?>
                            </div>
                            <button type="button" class="btn-remove" onclick="hidePtcRow(<?= $i ?>)" style="flex-shrink: 0; margin-left: 8px;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                            </button>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- ADD ANOTHER BUTTON (Inside Scroll Area Bottom) -->
                <button type="button" class="btn ghost" onclick="addPtcRowDynamic()" style="border: 1px dashed var(--border); color: var(--text-main); background-color: var(--bg-body); width: 100%; margin-top: 12px; flex-shrink: 0;">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span> Add Another Test Case
                </button>
            </div>

            <!-- FIXED FOOTER WITH BUTTONS -->
            <div class="refined-modal-footer">
                <button type="submit" class="btn">Add to Printer</button>
                <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="resetAndCloseModal('addCasesToPrinterModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT PRINTER PROFILE MODAL -->
<div class="modal-overlay" id="editPrinterModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Edit Printer Profile</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editPrinterModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body" id="editPrinterForm">
            <input type="hidden" name="action" value="update_printer_profile">
            <input type="hidden" name="printer_id" id="editPrinterId">
            
            <div class="edit-name-group">
                <label for="editPrinterNameInput">Printer Name</label>
                <input type="text" id="editPrinterNameInput" name="new_name" class="edit-name-input" placeholder="Enter printer model name" required 
                       oninput="validatePrinterName(this)">
                <div class="edit-name-error-msg" id="editNameError">Printer name already exists.</div>
            </div>

            <div id="uploadSection">
                <input type="file" id="printerImageInput" accept="image/*" style="display: none;">
                <div id="imageCropWrapper" style="width: 100%; height: 250px; background: var(--bg-surface); border: 2px dashed var(--border); border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; margin-bottom: 10px;" onclick="document.getElementById('printerImageInput').click();">
                    <div id="uploadPlaceholder" style="text-align: center; color: var(--text-muted);">
                        <span class="material-symbols-outlined" style="font-size: 40px; display: block; margin-bottom: 8px; opacity: 0.5;">cloud_upload</span>
                        <span style="font-size: 0.85rem; font-weight: 600;">Click to upload new image (optional)</span>
                    </div>
                    <img id="imageToCrop" style="display: none; max-width: 100%; max-height: 100%;">
                </div>
                <input type="hidden" name="cropped_image" id="croppedImageData">
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; align-items: center;">
                <button type="submit" class="btn" id="editSaveBtn" style="width: 32%;">Save Changes</button>
                <button type="button" class="btn ghost" style="width: 32%; background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('editPrinterModal')">Cancel</button>
                <button type="button" class="btn ghost" id="resetPrinterBtn" style="width: 32%; background:var(--error-bg); border:1px solid var(--error); color:var(--error);">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align: middle;">restart_alt</span> Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- BULK ACTION BAR -->
<div class="bulk-select-bar" id="bulkSelectBar">
    <span class="count-badge" id="bulkSelectedCount">0 selected</span>
    <button type="button" class="btn-bulk-delete" id="bulkDeleteBtn" onclick="deleteSelectedCases()" disabled>
        <span class="material-symbols-outlined">delete_sweep</span> Delete Selected
    </button>
    <button type="button" class="btn-bulk-delete-all" onclick="deleteAllCases()">
        <span class="material-symbols-outlined">delete_forever</span> Delete All
    </button>
    <button type="button" class="btn-cancel-select" onclick="cancelSelectMode()">Cancel</button>
</div>

<!-- ================== JAVASCRIPT ================== -->
<script>
    let activeSelectPrinterId = null;

    // Load the assigned case codes from PHP into a JS object
    const assignedCodesMap = <?= json_encode($assignedCodesMap) ?>;

    // --- Bulk Select Functions ---
    function toggleSelectMode(printerId) {
        if (activeSelectPrinterId && activeSelectPrinterId !== printerId) cancelSelectMode();
        const btn = document.getElementById('selectBtn_' + printerId);
        const chips = document.querySelectorAll('#chipContainer_' + printerId + ' .tc-edit-chip');
        const addBtn = document.getElementById('addCaseBtn_' + printerId);
        const bar = document.getElementById('bulkSelectBar');
        const isActive = btn.classList.contains('active');
        if (isActive) {
            btn.classList.remove('active');
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">checklist</span> Select';
            chips.forEach(chip => { chip.classList.remove('select-mode', 'selected-chip'); const cb = chip.querySelector('.chip-checkbox'); if (cb) cb.checked = false; });
            if (addBtn) addBtn.disabled = false;
            bar.classList.remove('visible');
            activeSelectPrinterId = null;
            document.body.style.overflow = '';
        } else {
            activeSelectPrinterId = printerId;
            btn.classList.add('active');
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">close</span> Cancel';
            chips.forEach(chip => { chip.classList.add('select-mode'); const cb = chip.querySelector('.chip-checkbox'); if (cb) cb.checked = false; });
            if (addBtn) addBtn.disabled = true;
            bar.classList.add('visible');
            document.body.style.overflow = 'hidden';
            updateBulkSelection();
        }
    }

    function cancelSelectMode() {
        if (!activeSelectPrinterId) return;
        const btn = document.getElementById('selectBtn_' + activeSelectPrinterId);
        if (btn) { btn.classList.remove('active'); btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">checklist</span> Select'; }
        const chips = document.querySelectorAll('#chipContainer_' + activeSelectPrinterId + ' .tc-edit-chip');
        chips.forEach(chip => { chip.classList.remove('select-mode', 'selected-chip'); const cb = chip.querySelector('.chip-checkbox'); if (cb) cb.checked = false; });
        const addBtn = document.getElementById('addCaseBtn_' + activeSelectPrinterId);
        if (addBtn) addBtn.disabled = false;
        document.getElementById('bulkSelectBar').classList.remove('visible');
        document.body.style.overflow = '';
        activeSelectPrinterId = null;
    }

    function updateBulkSelection() {
        if (!activeSelectPrinterId) return;
        const checked = document.querySelectorAll('#chipContainer_' + activeSelectPrinterId + ' .chip-checkbox:checked');
        const count = checked.length;
        const countEl = document.getElementById('bulkSelectedCount');
        const deleteBtn = document.getElementById('bulkDeleteBtn');
        if (count > 0) { countEl.textContent = count + ' selected'; deleteBtn.disabled = false; } 
        else { countEl.textContent = '0 selected'; deleteBtn.disabled = true; }
        const allChips = document.querySelectorAll('#chipContainer_' + activeSelectPrinterId + ' .tc-edit-chip');
        allChips.forEach(chip => { const cb = chip.querySelector('.chip-checkbox'); if (cb && cb.checked) chip.classList.add('selected-chip'); else chip.classList.remove('selected-chip'); });
    }

    function deleteSelectedCases() {
        if (!activeSelectPrinterId) return;
        const checked = document.querySelectorAll('#chipContainer_' + activeSelectPrinterId + ' .chip-checkbox:checked');
        if (checked.length === 0) return;
        if (!confirm('Are you sure you want to delete the ' + checked.length + ' selected test case(s)?')) return;
        const tcIds = []; checked.forEach(cb => tcIds.push(cb.dataset.tcid));
        performBulkDelete(activeSelectPrinterId, tcIds);
    }

    function deleteAllCases() {
        if (!activeSelectPrinterId) return;
        if (!confirm('Are you sure you want to delete ALL test cases for this printer? This cannot be undone.')) return;
        const chips = document.querySelectorAll('#chipContainer_' + activeSelectPrinterId + ' .tc-edit-chip');
        const tcIds = []; chips.forEach(chip => { const cb = chip.querySelector('.chip-checkbox'); if (cb) tcIds.push(cb.dataset.tcid); });
        if (tcIds.length === 0) return;
        performBulkDelete(activeSelectPrinterId, tcIds);
    }

    function performBulkDelete(printerId, tcIds) {
        window.showLoader();
        const form = document.createElement('form'); form.method = 'POST'; form.action = window.location.href; form.style.display = 'none';
        const hiddenInput = document.createElement('input'); hiddenInput.type = 'hidden'; hiddenInput.name = 'expanded_printer_id'; hiddenInput.value = printerId; form.appendChild(hiddenInput);
        const input = document.createElement('input'); input.type = 'hidden'; input.name = 'bulk_delete_cases'; input.value = tcIds.join(','); form.appendChild(input);
        document.body.appendChild(form); form.submit();
    }

    function togglePrinterStatus(printerId, event) {
        event.stopPropagation();
        var btn = document.getElementById('toggle_btn_' + printerId);
        var currentStatus = btn.getAttribute('data-status'); 
        var newStatus = (currentStatus === 'active') ? 'inactive' : 'active';
        var dot = document.getElementById('dot_' + printerId); var badge = document.getElementById('badge_' + printerId);
        var hint = document.getElementById('status_hint_' + printerId); var leftPanel = document.getElementById('left_panel_' + printerId);
        var mainRow = document.querySelector('tr[data-printer-id="' + printerId + '"]');
        btn.disabled = true; var originalHTML = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined" style="animation: spin 1s linear infinite; font-size:16px;">refresh</span> Updating...';
        var formData = new FormData(); formData.append('action', 'toggle_status'); formData.append('printer_id', printerId); formData.append('new_status', newStatus);
        
        // --- FIX: USE DYNAMIC RELATIVE PATH ---
        var pathPrefix = '';
        if (window.location.pathname.includes('/admin/')) {
            pathPrefix = '../';
        }
        var url = pathPrefix + 'controllers/shared/PrinterController.php';

        fetch(url, { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var isActive = (data.new_status === 'active');
                btn.setAttribute('data-status', data.new_status);
                dot.className = 'printer-status-dot ' + (isActive ? 'active' : 'inactive');
                badge.className = 'status-badge ' + (isActive ? 'active' : 'inactive');
                badge.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">' + (isActive ? 'check_circle' : 'cancel') + '</span> ' + (isActive ? 'Active' : 'Inactive');
                btn.className = 'status-toggle-btn ' + (isActive ? 'disable' : 'enable');
                btn.innerHTML = '<span class="material-symbols-outlined">' + (isActive ? 'power_settings_new' : 'flash_on') + '</span> ' + (isActive ? 'Disable Printer' : 'Enable Printer');
                hint.textContent = isActive ? 'Printer is active and available for testing.' : 'Printer is disabled.';
                if (isActive) { leftPanel.classList.remove('is-inactive-card'); if (mainRow) mainRow.classList.remove('is-inactive'); } 
                else { leftPanel.classList.add('is-inactive-card'); if (mainRow) mainRow.classList.add('is-inactive'); }
            } else { alert('Error: ' + (data.error || 'Failed to update status')); btn.innerHTML = originalHTML; }
        }).catch(function(error) { console.error('AJAX Error:', error); alert('Network error. Please try again.'); btn.innerHTML = originalHTML; })
        .finally(function() { btn.disabled = false; });
    }

    function toggleRow(rowId, trElement) {
        var wrapper = document.getElementById(rowId);
        var chevron = document.getElementById('chev-' + rowId);
        var isOpen = wrapper.classList.contains('open');
        
        document.querySelectorAll('.accordion-wrapper.open').forEach(function(el) {
            if (el.id !== rowId) {
                el.classList.remove('open');
                var parentRow = el.closest('.expanded-row');
                if (parentRow) {
                    var mainRow = parentRow.previousElementSibling;
                    if (mainRow) mainRow.classList.remove('is-open');
                }
            }
        });
        document.querySelectorAll('.chevron-icon.open').forEach(function(el) {
            if (el.id !== 'chev-' + rowId) {
                el.classList.remove('open');
                el.style.transform = 'rotate(0deg)';
            }
        });
        document.querySelectorAll('.main-row.is-open').forEach(function(el) {
            if (el !== trElement) {
                el.classList.remove('is-open');
            }
        });
        
        if (isOpen) {
            wrapper.classList.remove('open');
            chevron.classList.remove('open');
            chevron.style.transform = 'rotate(0deg)';
            if (trElement) trElement.classList.remove('is-open');
        } else {
            wrapper.classList.add('open');
            chevron.classList.add('open');
            chevron.style.transform = 'rotate(180deg)';
            if (trElement) trElement.classList.add('is-open');
        }
    }

    function openModal(id) { document.getElementById(id).classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); document.body.style.overflow = ''; }

    // --- RESET & CLOSE MODAL FUNCTION ---
    function resetAndCloseModal(modalId) {
        const nameInput = document.getElementById('addPrinterModelName');
        if(nameInput) nameInput.value = '';

        const tcList = document.getElementById('dynamicTcList');
        if(tcList) {
            const rows = tcList.querySelectorAll('.dynamic-tc-row');
            rows.forEach((row, index) => {
                if(index === 0) {
                    row.style.display = 'flex';
                    row.querySelectorAll('input[type="hidden"]').forEach(inp => inp.value = '');
                    const dd = row.querySelector('.enh-dropdown');
                    if(dd) {
                        const hidden = dd.querySelector('.enh-hidden-inputs');
                        if(hidden) hidden.innerHTML = '';
                        const triggerContent = dd.querySelector('.enh-trigger-content');
                        if(triggerContent) triggerContent.innerHTML = '<span class="enh-placeholder">Search or type: Case ID - Name</span>';
                        dd.querySelectorAll('.enh-option').forEach(opt => opt.classList.remove('selected'));
                    }
                } else {
                    row.style.display = 'none';
                    row.querySelectorAll('input[type="hidden"]').forEach(inp => inp.value = '');
                    const dd = row.querySelector('.enh-dropdown');
                    if(dd) {
                        const hidden = dd.querySelector('.enh-hidden-inputs');
                        if(hidden) hidden.innerHTML = '';
                        const triggerContent = dd.querySelector('.enh-trigger-content');
                        if(triggerContent) triggerContent.innerHTML = '<span class="enh-placeholder">Search or type: Case ID - Name</span>';
                        dd.querySelectorAll('.enh-option').forEach(opt => opt.classList.remove('selected'));
                    }
                }
            });
            tcRowCount = 1;
        }

        const ptcList = document.getElementById('dynamicPtcList');
        if(ptcList) {
            const rows = ptcList.querySelectorAll('.dynamic-tc-row');
            rows.forEach((row, index) => {
                if(index === 0) {
                    row.style.display = 'flex';
                    row.querySelectorAll('input[type="hidden"]').forEach(inp => inp.value = '');
                    const dd = row.querySelector('.enh-dropdown');
                    if(dd) {
                        const hidden = dd.querySelector('.enh-hidden-inputs');
                        if(hidden) hidden.innerHTML = '';
                        const triggerContent = dd.querySelector('.enh-trigger-content');
                        if(triggerContent) triggerContent.innerHTML = '<span class="enh-placeholder">Search or type: Case ID - Name</span>';
                        dd.querySelectorAll('.enh-option').forEach(opt => opt.classList.remove('selected'));
                    }
                } else {
                    row.style.display = 'none';
                    row.querySelectorAll('input[type="hidden"]').forEach(inp => inp.value = '');
                    const dd = row.querySelector('.enh-dropdown');
                    if(dd) {
                        const hidden = dd.querySelector('.enh-hidden-inputs');
                        if(hidden) hidden.innerHTML = '';
                        const triggerContent = dd.querySelector('.enh-trigger-content');
                        if(triggerContent) triggerContent.innerHTML = '<span class="enh-placeholder">Search or type: Case ID - Name</span>';
                        dd.querySelectorAll('.enh-option').forEach(opt => opt.classList.remove('selected'));
                    }
                }
            });
            ptcRowCount = 1;
        }

        closeModal(modalId);
    }

    function openAddCasesToPrinterModal(modelName) { 
        document.getElementById('targetPrinterModel').value = modelName; 
        document.getElementById('targetPrinterModelDisplay').textContent = modelName; 
        openModal('addCasesToPrinterModal'); 
    }

    // --- EDIT PRINTER LOGIC ---
    var imageCropper = null;
    var currentPrinterName = ''; 

    function openEditPrinterModal(printerId, currentName) {
        currentPrinterName = currentName; 
        document.getElementById('editPrinterId').value = printerId;
        document.getElementById('editPrinterNameInput').value = currentName;
        document.getElementById('editPrinterNameInput').classList.remove('error');
        document.getElementById('editNameError').classList.remove('visible');
        document.getElementById('editSaveBtn').disabled = false;
        
        document.getElementById('croppedImageData').value = '';
        if (imageCropper) { imageCropper.destroy(); imageCropper = null; }
        document.getElementById('imageToCrop').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        document.getElementById('printerImageInput').value = '';
        
        openModal('editPrinterModal');
    }

    let validateTimeout = null;
    function validatePrinterName(input) {
        clearTimeout(validateTimeout);
        const printerId = document.getElementById('editPrinterId').value;
        const name = input.value.trim();
        const errorMsg = document.getElementById('editNameError');
        const saveBtn = document.getElementById('editSaveBtn');
        
        input.classList.remove('error');
        errorMsg.classList.remove('visible');
        saveBtn.disabled = false;

        if (name.length === 0) return;

        validateTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'validate_printer_name');
            formData.append('printer_id', printerId);
            formData.append('name', name);

            fetch('controllers/shared/PrinterController.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (!data.valid) {
                    input.classList.add('error');
                    errorMsg.classList.add('visible');
                    saveBtn.disabled = true;
                }
            })
            .catch(() => { /* ignore network errors */ });
        }, 400);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const imageInput = document.getElementById('printerImageInput');
        const imageToCrop = document.getElementById('imageToCrop');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const croppedData = document.getElementById('croppedImageData');
        
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(event) {
                    imageToCrop.src = event.target.result;
                    imageToCrop.style.display = 'block';
                    uploadPlaceholder.style.display = 'none';
                    if (imageCropper) imageCropper.destroy();
                    imageCropper = new Cropper(imageToCrop, {
                        aspectRatio: 1 / 1,
                        viewMode: 1,
                        autoCropArea: 1,
                        ready() {
                            const canvas = imageCropper.getCroppedCanvas({ width: 200, height: 200, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
                            if (canvas) croppedData.value = canvas.toDataURL('image/png');
                        },
                        cropend() {
                            const canvas = imageCropper.getCroppedCanvas({ width: 200, height: 200 });
                            if (canvas) croppedData.value = canvas.toDataURL('image/png');
                        }
                    });
                };
                reader.readAsDataURL(file);
            });
        }
        
        const editForm = document.getElementById('editPrinterForm');
        if(editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault(); 
                if (document.getElementById('editSaveBtn').disabled) {
                    alert('Please fix the duplicate name error before saving.');
                    return;
                }
                let croppedBase64 = '';
                if (imageCropper) {
                    const canvas = imageCropper.getCroppedCanvas({ width: 200, height: 200 });
                    if (canvas) {
                        croppedBase64 = canvas.toDataURL('image/png');
                        document.getElementById('croppedImageData').value = croppedBase64;
                    }
                }
                const formData = new FormData(editForm);
                window.showLoader();

                var pathPrefix = '';
                if (window.location.pathname.includes('/admin/')) {
                    pathPrefix = '../';
                }
                var url = pathPrefix + 'controllers/shared/PrinterController.php';

                fetch(url, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    window.hideLoader();
                    if (data.success) {
                        if (typeof showDynamicToast === 'function') {
                            showDynamicToast(data.message || 'Printer profile updated successfully!', 'success');
                        }
                        setTimeout(() => window.location.href = window.location.pathname + '?t=' + Date.now(), 1000);
                    } else {
                        if (typeof showDynamicToast === 'function') {
                            showDynamicToast(data.error || 'Failed to update profile.', 'error');
                        }
                    }
                })
                .catch(() => {
                    window.hideLoader();
                    if (typeof showDynamicToast === 'function') {
                        showDynamicToast('Network error. Please try again.', 'error');
                    }
                });
            });
        }

        const resetBtn = document.getElementById('resetPrinterBtn');
        if(resetBtn) {
            resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!confirm('Reset the printer image to the default icon? The name will also revert to "' + currentPrinterName + '".')) {
                    return;
                }
                document.getElementById('editPrinterNameInput').value = currentPrinterName;
                document.getElementById('editPrinterNameInput').classList.remove('error');
                document.getElementById('editNameError').classList.remove('visible');
                document.getElementById('editSaveBtn').disabled = false;
                document.getElementById('croppedImageData').value = '';
                if (imageCropper) { imageCropper.destroy(); imageCropper = null; }
                document.getElementById('imageToCrop').style.display = 'none';
                document.getElementById('uploadPlaceholder').style.display = 'block';
                document.getElementById('printerImageInput').value = '';
                
                const formData = new FormData();
                formData.append('action', 'update_printer_profile');
                formData.append('printer_id', document.getElementById('editPrinterId').value);
                formData.append('new_name', currentPrinterName);
                formData.append('reset_image', '1');
                window.showLoader();

                var pathPrefix = '';
                if (window.location.pathname.includes('/admin/')) {
                    pathPrefix = '../';
                }
                var url = pathPrefix + 'controllers/shared/PrinterController.php';

                fetch(url, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    window.hideLoader();
                    if (data.success) {
                        // KEEP MODAL OPEN. Do not reload page. Do not show toast.
                    } else {
                        if (typeof showDynamicToast === 'function') {
                            showDynamicToast(data.error || 'Failed to reset image.', 'error');
                        }
                    }
                })
                .catch(() => {
                    window.hideLoader();
                    if (typeof showDynamicToast === 'function') {
                        showDynamicToast('Network error. Please try again.', 'error');
                    }
                });
            });
        }

        <?php if (isset($_SESSION['reopen_printer_id'])): ?>
            const reopenId = 'printer_<?= $_SESSION['reopen_printer_id'] ?>';
            const row = document.getElementById(reopenId); 
            if (row) { 
                // Find the parent tr of the expanded-row and click it
                const parentTr = row.closest('tr.expanded-row')?.previousElementSibling; 
                if (parentTr) { 
                    setTimeout(() => { 
                        parentTr.click(); 
                    }, 150); 
                }
            }
            <?php unset($_SESSION['reopen_printer_id']); ?>
        <?php endif; ?>
    });

    var tcRowCount = 1; 
    var ptcRowCount = 1;
    
    function addTcRowDynamic() { if (tcRowCount < 30) { document.getElementById('tc_row_' + tcRowCount).style.display = 'flex'; tcRowCount++; var list = document.getElementById('dynamicTcList'); list.scrollTop = list.scrollHeight; } }
    function hideTcRow(i) { document.getElementById('tc_row_' + i).style.display = 'none'; document.getElementById('tc_row_' + i).querySelectorAll('input').forEach(function(inp) { inp.value = ''; }); }

    function addPtcRowDynamic() { 
        if (ptcRowCount < 20) { 
            document.getElementById('ptc_row_' + ptcRowCount).style.display = 'flex'; 
            ptcRowCount++; 
            var list = document.getElementById('dynamicPtcList'); 
            list.scrollTop = list.scrollHeight; 
        } 
    }
    
    function hidePtcRow(i) { 
        document.getElementById('ptc_row_' + i).style.display = 'none'; 
        document.getElementById('ptc_row_' + i).querySelectorAll('input').forEach(function(inp) { inp.value = ''; }); 
        setTimeout(() => filterAllDropdowns(), 100);
    }

    // --- FIX: JS FILTERING LOGIC (Runs Every Time a Dropdown Opens) ---
    function filterAllDropdowns() {
        // 1. Get the current target printer model from the modal
        const targetModel = document.getElementById('targetPrinterModel')?.value;
        const assignedCodes = assignedCodesMap[targetModel] || [];

        // 2. Collect currently selected values from ALL rows in this form
        let selectedValues = [];
        document.querySelectorAll('#dynamicPtcList .enh-dropdown').forEach(dd => {
            const hiddenInput = dd.querySelector('.enh-hidden-inputs input');
            if (hiddenInput && hiddenInput.value) {
                selectedValues.push(hiddenInput.value);
            }
        });

        // 3. Loop through ALL dropdown menus and hide the selected ones
        document.querySelectorAll('#dynamicPtcList .enh-dropdown').forEach(dd => {
            const options = dd.querySelectorAll('.enh-option');
            const currentSelectedVal = dd.querySelector('.enh-hidden-inputs input')?.value;

            options.forEach(opt => {
                // EXTRACT THE CASE CODE FROM THE LABEL
                const label = opt.querySelector('.enh-opt-label');
                let val = opt.dataset.value;
                
                // If the label contains " - ", split and take the first part (the Case Code)
                if (label && label.textContent.includes(' - ')) {
                    val = label.textContent.split(' - ')[0].trim();
                }

                // Hide if:
                // 1. It's already permanently assigned to this printer (Database check)
                // 2. OR it's selected in another row (Duplicate check)
                if (assignedCodes.includes(val) || (selectedValues.includes(val) && val !== currentSelectedVal)) {
                    opt.style.display = 'none';
                } else {
                    opt.style.display = '';
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const originalOpen = window.EnhancedDropdown?.prototype?.open;
        
        // This observer watches for new rows being added
        const observer = new MutationObserver(function(mutations) {
            // When a new row is added, schedule the filter to run after the DOM updates
            setTimeout(() => {
                // Re-initialize the filter for the new dropdowns
                const dropdowns = document.querySelectorAll('#dynamicPtcList .enh-dropdown');
                dropdowns.forEach(dd => {
                    // If the dropdown hasn't been initialized yet, initialize it quickly
                    if (!dd._enhancedDropdown && window.EnhancedDropdown) {
                        dd._enhancedDropdown = new window.EnhancedDropdown(dd);
                    }
                });
                filterAllDropdowns();
            }, 100);
        });

        const ptcList = document.getElementById('dynamicPtcList');
        if (ptcList) {
            observer.observe(ptcList, { childList: true, subtree: true });
        }

        // Patch the existing open method of EnhancedDropdown
        if (window.EnhancedDropdown) {
            window.EnhancedDropdown.prototype.open = function() {
                if (originalOpen) originalOpen.call(this);
                // Schedule filter to run after the dropdown renders
                setTimeout(() => filterAllDropdowns(), 150);
            };
        }
        
        // If using global listeners, listen for click on the trigger
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.enh-trigger');
            if (trigger) {
                setTimeout(() => filterAllDropdowns(), 150);
            }
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function(overlay) { overlay.addEventListener('click', function(e) { if (e.target === overlay) { overlay.classList.remove('active'); document.body.style.overflow = ''; } }); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { document.querySelectorAll('.modal-overlay.active').forEach(function(overlay) { overlay.classList.remove('active'); }); document.body.style.overflow = ''; } });
    document.addEventListener('change', function(e) { if (e.target.classList.contains('chip-checkbox')) { updateBulkSelection(); } });
</script>