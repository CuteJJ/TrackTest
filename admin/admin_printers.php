<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole('admin');

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW PRINTER & CASES
    if (isset($_POST['add_printer'])) {
        $pdo->beginTransaction();
        try {
            $model_name = trim($_POST['model_name']);
            
            // --- NEW: DUPLICATE CHECK ---
            $chk = $pdo->prepare("SELECT id FROM printers WHERE model_name = ?");
            $chk->execute([$model_name]);
            if ($chk->fetch()) {
                throw new Exception("A printer with the model name '$model_name' already exists.");
            }

            $pdo->prepare("INSERT INTO printers (model_name) VALUES (?)")->execute([$model_name]);
            
            $codes = $_POST['case_code'] ?? [];
            $titles = $_POST['case_title'] ?? [];
            
            $stmt_tc = $pdo->prepare("INSERT INTO test_cases (printer_model, case_code, title) VALUES (?, ?, ?)");
            for($i=0; $i<count($codes); $i++) {
                if(!empty(trim($codes[$i]))) {
                    $stmt_tc->execute([$model_name, trim($codes[$i]), trim($titles[$i])]);
                }
            }
            $pdo->commit();
            Helper::setFlash("Printer '$model_name' and Test Cases saved.", "success");
        } catch(Exception $e) {
            $pdo->rollBack();
            Helper::setFlash("Error: " . $e->getMessage(), "error");
        }
        header("Location: admin_printers.php"); exit();
    } 
    
    // 2. DELETE PRINTER
    elseif (isset($_POST['delete_printer'])) {
        $pdo->beginTransaction();
        try {
            $model = $_POST['model_name'];
            $pdo->prepare("DELETE FROM test_cases WHERE printer_model = ?")->execute([$model]);
            $pdo->prepare("DELETE FROM printers WHERE id = ?")->execute([$_POST['printer_id']]);
            $pdo->commit();
            Helper::setFlash("Printer deleted successfully.", "success");
        } catch (Exception $e) {
            $pdo->rollBack();
            Helper::setFlash("Cannot delete printer: Tasks are actively using it.", "error");
        }
        header("Location: admin_printers.php"); exit();
    }

    // 3. EDIT TEST CASE (Triggered by clicking a chip)
    elseif (isset($_POST['edit_testcase'])) {
        try {
            $stmt = $pdo->prepare("UPDATE test_cases SET case_code = ?, title = ? WHERE id = ?");
            $stmt->execute([trim($_POST['case_code']), trim($_POST['title']), $_POST['tc_id']]);
            Helper::setFlash("Test case updated successfully.", "success");
        } catch (Exception $e) {
            Helper::setFlash("Error updating test case.", "error");
        }
        header("Location: admin_printers.php"); exit();
    }

    // 4. ADD CASES TO EXISTING PRINTER
    elseif (isset($_POST['add_cases_existing'])) {
        try {
            $model_name = $_POST['target_model'];
            $codes = $_POST['new_case_code'] ?? [];
            $titles = $_POST['new_case_title'] ?? [];
            
            $stmt_tc = $pdo->prepare("INSERT INTO test_cases (printer_model, case_code, title) VALUES (?, ?, ?)");
            $added = 0;
            for($i=0; $i<count($codes); $i++) {
                if(!empty(trim($codes[$i]))) {
                    $stmt_tc->execute([$model_name, trim($codes[$i]), trim($titles[$i])]);
                    $added++;
                }
            }
            Helper::setFlash("$added new test case(s) added to $model_name.", "success");
        } catch (Exception $e) {
            Helper::setFlash("Error adding test cases.", "error");
        }
        header("Location: admin_printers.php"); exit();
    }

    // 5. UPDATE PRINTER IMAGE/ICON
    elseif (isset($_POST['edit_printer_image'])) {
        try {
            $printer_id = $_POST['printer_id'];
            $model_name = $_POST['readonly_model_name'];
            $image_type = $_POST['image_type'];
            $printer_path = null;

            if ($image_type === 'upload' && !empty($_POST['cropped_image'])) {
                $base64 = $_POST['cropped_image'];
                if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                    $data = substr($base64, strpos($base64, ',') + 1);
                    $data = base64_decode($data);
                    
                    $dir = '../imgs/printers/';
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    
                    // Format: "pixiu_mfp.png"
                    $safe_name = strtolower(str_replace([' ', '/', '\\'], '_', trim($model_name)));
                    $filename = $dir . $safe_name . '.png';
                    
                    file_put_contents($filename, $data);
                    $printer_path = 'imgs/printers/' . $safe_name . '.png'; // DB stores relative path
                }
            } elseif ($image_type === 'icon' && !empty(trim($_POST['icon_url']))) {
                $printer_path = trim($_POST['icon_url']);
            }

            if ($printer_path !== null) {
                $stmt = $pdo->prepare("UPDATE printers SET printer_path = ? WHERE id = ?");
                $stmt->execute([$printer_path, $printer_id]);
                Helper::setFlash("Printer profile updated successfully!", "success");
            }
            
        } catch (Exception $e) {
            Helper::setFlash("Error updating printer image.", "error");
        }
        header("Location: admin_printers.php"); exit();
    }
}

// Fetch printers
$printers = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM test_cases tc WHERE tc.printer_model = p.model_name) as case_count FROM printers p ORDER BY p.model_name")->fetchAll();

// Fetch all test cases and group them
$tc_stmt = $pdo->query("SELECT * FROM test_cases ORDER BY case_code ASC");
$all_testcases = $tc_stmt->fetchAll();
$tc_map = [];
foreach ($all_testcases as $tc) {
    $tc_map[$tc['printer_model']][] = $tc;
}

// Icon Helper
function getPrinterIcon(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'flare')) return 'local_fire_department';
    if (str_contains($n, 'ray'))   return 'bolt';
    if (str_contains($n, 'mfp'))  return 'content_copy';
    if (str_contains($n, 'sfp'))  return 'print';
    return 'print';
}

function renderPrinterImage($p) {
    $path = $p['printer_path'] ?? '';
    // If it looks like a path or a URL
    if (str_contains($path, '/') || str_contains($path, '.')) {
        // Adjust path since we are inside /admin/
        $displayPath = str_starts_with($path, 'http') ? $path : '../' . $path;
        return "<img src='".htmlspecialchars($displayPath)."?v=".time()."' style='width:100%; height:100%; object-fit:cover; border-radius:50%;'>";
    }
    
    // Otherwise it's a material icon (or fallback)
    $iconText = $path ?: getPrinterIcon($p['model_name']);
        return "<span class='material-symbols-outlined' style='font-size: 18px; color: var(--primary);'>".htmlspecialchars($iconText)."</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Printers | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <link rel="stylesheet" href="../app.css">
    <script>
        const savedTheme = localStorage.getItem('track-manager-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <style>
        /* Accordion Grid Layout */
        .printer-detail-grid {
            display: flex;          /* CHANGE grid → flex */
            flex-direction: row;    /* side by side */
            gap: 24px;
            padding: 24px;
            align-items: stretch;   /* children stretch to same height */
        }
        @media (max-width: 900px) {
            .printer-detail-grid { flex-direction: column; }
        }

        /* Left Side: Photo & Desc */
        .printer-left {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 240px;
            flex-shrink: 0;
            box-sizing: border-box;
            justify-content: center;
        }
        .printer-photo-placeholder {
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--bg-surface); border: 2px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .printer-desc-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 6px; }
        
        /* FIX: UI Issue text wrap out of bounds */
        .printer-desc { 
            font-size: 0.8rem; 
            color: var(--text-main); 
            line-height: 1.5; 
            width: 100%; 
            word-break: break-word; 
            overflow-wrap: break-word; 
        }

        /* Right Side: Chips & Actions */
        .printer-right {
            display: flex;
            flex-direction: column;
            flex: 1;                /* take remaining width */
            min-width: 0;
            justify-content: space-between;  /* push actions to bottom */
        }

        /* Override global expanded-content flex for this page only */
        #adminPrintersPage .expanded-content,
        .printer-expanded-content {
            display: block !important;
        }
        .tc-chip-container {
            display: flex; flex-wrap: wrap; gap: 10px; align-content: flex-start;
            flex: 1; padding-bottom: 24px;
        }
        .tc-edit-chip {
            padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--bg-body); font-size: 0.8rem; font-weight: 600;
            color: var(--text-main); cursor: pointer; transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 6px; font-family: 'Inter', sans-serif;
        }
        .tc-edit-chip:hover {
            border-color: var(--primary); background: var(--bg-surface);
            color: var(--primary); transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .tc-edit-chip .code { color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; }
        .tc-edit-chip:hover .code { color: var(--primary); opacity: 0.8; }

        .printer-actions {
            display: flex; justify-content: flex-end; gap: 12px;
            border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap;
        }

        /* Dynamic Form Row */
        .dynamic-tc-row {
            display: flex; gap: 8px; align-items: center; margin-bottom: 10px;
            padding: 8px; background: var(--bg-body); border-radius: 8px; border: 1px dashed var(--border);
        }
        .dynamic-tc-row input { flex: 1; margin: 0 !important; }
        .dynamic-tc-row .btn-remove {
            background: var(--error-bg); color: var(--error); border: none;
            width: 32px; height: 32px; border-radius: 6px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .dynamic-tc-row .btn-remove:hover { background: var(--error); color: white; }
    </style>
</head>
<body>
    <?php Helper::displayLoader(); ?>
    <?php Helper::displayFlash(); ?>
    
    <nav class="navbar">
        <div class="nav-brand"><span class="nav-brand-dot"></span> Admin Center</div>
        <div class="nav-right relative">
            <a href="../logout.php" class="nav-logout"><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: text-bottom;">logout</span> Exit</a>
        </div>
    </nav>
    <button class="admin-burger" onclick="toggleAdminSidebar()"><span class="material-symbols-outlined">menu</span></button>
    <div class="admin-overlay" id="adminOverlay" onclick="toggleAdminSidebar()"></div>
    <aside class="admin-sidebar" id="adminSidebar">
        <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); margin: 10px 0 10px 16px;">MANAGEMENT</div>
        <a href="admin_dashboard.php" class="admin-nav-item"><span class="material-symbols-outlined">dashboard</span> Home Overview</a>
        <a href="admin_history.php" class="admin-nav-item"><span class="material-symbols-outlined">history</span> Global History</a>
        <a href="admin_printers.php" class="admin-nav-item active"><span class="material-symbols-outlined">print</span> Printers & Cases</a>
        <a href="admin_users.php" class="admin-nav-item"><span class="material-symbols-outlined">group</span> User Directory</a>
    </aside>

    <div class="page-content-scroll">
        <main class="admin-content">
            
            <div class="d-card span-full">
                <div class="d-card-header">
                    <div class="d-card-title"><span class="material-symbols-outlined">print</span> Printer Database</div>
                    <button class="btn-mini" onclick="openModal('addPrinterModal')">
                        <span class="material-symbols-outlined">add</span> Add Printer
                    </button>
                </div>
                <div class="d-card-body">
                    <table class="d-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Model Name</th>
                                <th>Number of Test Cases</th>
                                <th style="width: 50px; text-align:center;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($printers as $p): ?>
                            <?php 
                                $rowId = "printer_" . $p['id']; 
                                $cases = $tc_map[$p['model_name']] ?? [];
                            ?>
                            <tr class="expand-trigger main-row" onclick="toggleRow('<?= $rowId ?>', this)">
                                <td style="font-weight: 700; font-size: 0.95rem;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="display:inline-flex; width: 24px; height: 24px; align-items:center; justify-content:center;">
                                            <?= renderPrinterImage($p) ?>
                                        </span>
                                        <?= htmlspecialchars($p['model_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $p['case_count'] > 0 ? 'badge-pass' : 'badge-pending' ?>"><?= $p['case_count'] ?> Cases</span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="material-symbols-outlined chevron-icon" id="chev-<?= $rowId ?>">expand_more</span>
                                </td>
                            </tr>
                            <tr class="expanded-row">
                                <td colspan="3">
                                    <div class="accordion-wrapper" id="<?= $rowId ?>">
                                        <div class="expanded-content printer-expanded-content">
                                            
                                            <div class="printer-detail-grid">
                                                <div class="printer-left">
                                                    <div class="printer-photo-placeholder">
                                                        <?= renderPrinterImage($p) ?>
                                                    </div>
                                                </div>

                                                <div class="printer-right">
                                                    <span class="printer-desc-label" style="margin-bottom: 12px; display:block;">Test Cases (Click to Edit)</span>
                                                    
                                                    <div class="tc-chip-container">
                                                        <?php if(empty($cases)): ?>
                                                            <div style="font-size:0.85rem; color:var(--text-muted); font-style:italic;">No test cases assigned yet.</div>
                                                        <?php else: ?>
                                                            <?php foreach($cases as $tc): ?>
                                                                <button type="button" class="tc-edit-chip" onclick="openEditTcModal(<?= $tc['id'] ?>, '<?= htmlspecialchars($tc['case_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tc['title'], ENT_QUOTES) ?>')">
                                                                    <span class="code">#<?= htmlspecialchars($tc['case_code']) ?></span>
                                                                    <span class="title"><?= htmlspecialchars($tc['title']) ?></span>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="printer-actions">
                                                        <button class="btn-mini ghost" onclick="openAddCasesModal('<?= htmlspecialchars($p['model_name'], ENT_QUOTES) ?>')">
                                                            <span class="material-symbols-outlined">post_add</span> Add Cases
                                                        </button>

                                                        <button class="btn-mini ghost" onclick="openEditPrinterImageModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['model_name'], ENT_QUOTES) ?>')">
                                                            <span class="material-symbols-outlined">edit</span> Edit Profile
                                                        </button>

                                                        <form method="POST" onsubmit="return confirm('DELETE this printer and ALL its cases? This action cannot be undone.');" style="margin:0;">
                                                            <input type="hidden" name="printer_id" value="<?= $p['id'] ?>">
                                                            <input type="hidden" name="model_name" value="<?= htmlspecialchars($p['model_name']) ?>">
                                                            <button type="submit" name="delete_printer" class="btn-mini ghost" style="color:var(--error); border-color:var(--error);">
                                                                <span class="material-symbols-outlined">delete</span> Delete Profile
                                                            </button>
                                                        </form>
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
            </div>
        </main>
    </div>

    <div class="modal-overlay" id="addPrinterModal">
        <div class="modal-box" style="max-width: 550px;">
            <h3 style="margin-top:0; font-size:1.3rem;">Add New Printer</h3>
            <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom: 20px;">Define the printer model. Test cases defined here are applied to <strong>Smoke Tests</strong> only.</p>
            
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="model_name" class="form-control" required placeholder=" ">
                    <label class="form-label">Model Name (e.g. Pixiu MFP)</label>
                </div>
                
                <div style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 16px;">
                    <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:12px; display:block;">Test Cases</span>
                    
                    <div id="dynamicTcList" style="max-height: 250px; overflow-y: auto; padding-right: 4px; margin-bottom: 12px;">
                        </div>

                    <button type="button" class="btn ghost" onclick="addTcRow('dynamicTcList', 'case_code[]', 'case_title[]')" style="border: 1px dashed var(--border); color: var(--text-muted);">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span> Add Test Case
                    </button>
                </div>

                <div style="display:flex; gap:10px; margin-top:24px;">
                    <button type="submit" name="add_printer" class="btn">Save Printer Profile</button>
                    <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('addPrinterModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editTcModal">
        <div class="modal-box" style="max-width: 400px;">
            <h3 style="margin-top:0; font-size:1.2rem;">Rename Test Case</h3>
            <form method="POST">
                <input type="hidden" name="tc_id" id="edit_tc_id">
                <div class="form-group">
                    <input type="text" name="case_code" id="edit_tc_code" class="form-control" style="font-family: 'JetBrains Mono', monospace;" required>
                    <label class="form-label">Case Code</label>
                </div>
                <div class="form-group">
                    <input type="text" name="title" id="edit_tc_title" class="form-control" required>
                    <label class="form-label">Case Title</label>
                </div>
                <div style="display:flex; gap:10px; margin-top:24px;">
                    <button type="submit" name="edit_testcase" class="btn">Update Case</button>
                    <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('editTcModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="addCasesExistingModal">
        <div class="modal-box" style="max-width: 550px;">
            <h3 style="margin-top:0; font-size:1.3rem;">Add Cases to <span id="addExistingTargetName" style="color:var(--primary);"></span></h3>
            <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom: 20px;">These cases will be added to the existing Smoke Test profile.</p>
            
            <form method="POST">
                <input type="hidden" name="target_model" id="addExistingTargetInput">
                
                <div id="dynamicExistingTcList" style="max-height: 300px; overflow-y: auto; padding-right: 4px; margin-bottom: 12px;">
                    </div>

                <button type="button" class="btn ghost" onclick="addTcRow('dynamicExistingTcList', 'new_case_code[]', 'new_case_title[]')" style="border: 1px dashed var(--border); color: var(--text-muted);">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span> Add Test Case
                </button>

                <div style="display:flex; gap:10px; margin-top:24px;">
                    <button type="submit" name="add_cases_existing" class="btn">Append Cases</button>
                    <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('addCasesExistingModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editPrinterProfileModal">
        <div class="modal-box" style="max-width: 450px;">
            <h3 style="margin-top:0; font-size:1.3rem;">Edit Printer Profile</h3>
            
            <form method="POST" id="editPrinterForm">
                <input type="hidden" name="edit_printer_image" value="1">
                <input type="hidden" name="printer_id" id="editProfileId">
                <input type="hidden" name="cropped_image" id="croppedImageInput">
                
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">
                    Editing profile for: <strong id="editProfileModelDisplay" style="color: var(--text-main);"></strong>
                </p>
                <!-- Keep this hidden input so the value still submits with the form -->
                <input type="hidden" name="readonly_model_name" id="editProfileModel">

                <div style="display:flex; gap:20px; margin: 20px 0;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.9rem; font-weight:600;">
                        <input type="radio" name="image_type" value="upload" id="radioUpload" checked> Upload Photo
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.9rem; font-weight:600;">
                        <input type="radio" name="image_type" value="icon" id="radioIcon"> Pick Icon / URL
                    </label>
                </div>

                <div id="uploadSection">
                    <div class="dropzone" id="printerDropzone" style="padding: 30px 10px;">
                        <span class="material-symbols-outlined">cloud_upload</span>
                        <span class="dropzone-text" id="dropzoneText">Drag & Drop image here or <strong>browse</strong></span>
                        <span class="dropzone-sub">Aspect ratio: 1:1 (Square)</span>
                        <input type="file" id="printerImageInput" accept="image/*" class="hidden">
                    </div>
                </div>

                <div id="iconSection" class="hidden">
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px;">Enter a Google Material Icon name (e.g., <code>print</code>, <code>bolt</code>) or paste an image URL.</p>
                    <div class="form-group" style="margin-top: 0;">
                        <input type="text" name="icon_url" id="iconUrlInput" class="form-control" placeholder=" ">
                        <label class="form-label">Icon Name or Image URL</label>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:24px;">
                    <button type="submit" class="btn">Save Changes</button>
                    <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('editPrinterProfileModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="cropperModal" class="cropper-modal">
        <div class="cropper-content">
            <h3 style="margin-top:0; font-size:1.2rem;">Crop Printer Image</h3>
            <div class="img-container" style="width: 100%; height: 350px; background: #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
                <img id="imageToCrop" src="" style="max-width: 100%; display: block;">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn ghost" style="width: auto; background: var(--bg-body); color: var(--text-main); border: none;" onclick="closeCropper()">Cancel</button>
                <button type="button" class="btn" style="width: auto;" id="cropSubmitBtn">Apply Picture</button>
            </div>
        </div>
    </div>

    <script src="../app.js"></script>
    <script>
        // --- Accordion Logic ---
        function toggleRow(rowId, triggerElement) {
            const wrapper = document.getElementById(rowId);
            const chevron = document.getElementById('chev-' + rowId);
            const isOpen = wrapper.classList.contains('open');

            document.querySelectorAll('.accordion-wrapper.open').forEach(el => el.classList.remove('open'));
            document.querySelectorAll('.chevron-icon.open').forEach(el => el.classList.remove('open'));
            document.querySelectorAll('.main-row.is-open').forEach(el => el.classList.remove('is-open'));

            if (!isOpen) {
                wrapper.classList.add('open');
                if (chevron) chevron.classList.add('open');
                if (triggerElement) triggerElement.classList.add('is-open');
            }
        }

        // --- Edit TC Logic ---
        function openEditTcModal(id, code, title) {
            event.stopPropagation();
            document.getElementById('edit_tc_id').value = id;
            document.getElementById('edit_tc_code').value = code;
            document.getElementById('edit_tc_title').value = title;
            document.getElementById('edit_tc_code').focus();
            document.getElementById('edit_tc_title').focus();
            document.getElementById('edit_tc_title').blur();
            openModal('editTcModal');
        }

        // --- Add Cases to Existing Logic ---
        function openAddCasesModal(modelName) {
            event.stopPropagation();
            document.getElementById('addExistingTargetName').textContent = modelName;
            document.getElementById('addExistingTargetInput').value = modelName;
            const list = document.getElementById('dynamicExistingTcList');
            list.innerHTML = '';
            addTcRow('dynamicExistingTcList', 'new_case_code[]', 'new_case_title[]');
            openModal('addCasesExistingModal');
        }

        // --- Dynamic Row Builder ---
        function addTcRow(containerId, codeName, titleName) {
            const container = document.getElementById(containerId);
            const row = document.createElement('div');
            row.className = 'dynamic-tc-row';
            row.innerHTML = `
                <input type="text" name="${codeName}" class="form-control" placeholder="Case ID" style="max-width: 140px; font-family: monospace; padding: 10px;" required>
                <input type="text" name="${titleName}" class="form-control" placeholder="Test Title" style="padding: 10px;" required>
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">
                    <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                </button>
            `;
            container.appendChild(row);
            container.scrollTop = container.scrollHeight;
        }

        document.getElementById('addPrinterModal').addEventListener('show', function() {
            document.getElementById('dynamicTcList').innerHTML = '';
        });

        // ==========================================
        // NEW: EDIT PRINTER PROFILE & CROPPER LOGIC
        // ==========================================
        
        function openEditPrinterImageModal(id, modelName) {
            event.stopPropagation(); // Don't trigger accordion collapse
            
            document.getElementById('editProfileId').value = id;
            document.getElementById('editProfileModel').value = modelName;
            document.getElementById('editProfileModelDisplay').textContent = modelName;

            // Reset UI
            document.getElementById('croppedImageInput').value = '';
            document.getElementById('dropzoneText').innerHTML = "Drag & Drop image here or <strong>browse</strong>";
            document.getElementById('iconUrlInput').value = '';
            document.getElementById('radioUpload').checked = true;
            toggleEditSections();

            openModal('editPrinterProfileModal');
        }

        // Radio Button Toggles
        document.getElementById('radioUpload').addEventListener('change', toggleEditSections);
        document.getElementById('radioIcon').addEventListener('change', toggleEditSections);

        function toggleEditSections() {
            if (document.getElementById('radioUpload').checked) {
                document.getElementById('uploadSection').classList.remove('hidden');
                document.getElementById('iconSection').classList.add('hidden');
            } else {
                document.getElementById('uploadSection').classList.add('hidden');
                document.getElementById('iconSection').classList.remove('hidden');
            }
        }

        // Cropper Logic (Same as settings.php)
        let cropper;
        const dropzone = document.getElementById('printerDropzone');
        const fileInput = document.getElementById('printerImageInput');
        const imageToCrop = document.getElementById('imageToCrop');
        const cropModal = document.getElementById('cropperModal');

        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) handleFile(e.target.files[0]);
        });

        function handleFile(file) {
            if (!file.type.startsWith('image/')) { alert('Please select a valid image file.'); return; }
            if (file.size > 5 * 1024 * 1024) { alert('Image exceeds 5MB limit.'); return; }

            const reader = new FileReader();
            reader.onload = (e) => {
                imageToCrop.src = e.target.result;
                cropModal.classList.add('show');
                
                if (cropper) cropper.destroy(); 
                
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1, 
                    viewMode: 1,    
                    autoCropArea: 1,
                    dragMode: 'move',
                    guides: true,
                    center: true,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            };
            reader.readAsDataURL(file);
        }

        function closeCropper() {
            cropModal.classList.remove('show');
            fileInput.value = '';
        }

        document.getElementById('cropSubmitBtn').addEventListener('click', () => {
            if (!cropper) return;
            
            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            const base64 = canvas.toDataURL('image/png');
            document.getElementById('croppedImageInput').value = base64;
            
            // Visual feedback inside the dropzone
            document.getElementById('dropzoneText').innerHTML = "<span style='color:var(--success); font-weight:700;'>✓ Image Cropped & Ready!</span><br><span style='font-size:0.75rem;'>Click Save Changes to finish</span>";
            
            closeCropper();
        });
    </script>
</body>
</html>