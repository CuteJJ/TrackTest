<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole(['admin','lead']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_printer'])) {
        $pdo->beginTransaction();
        try {
            $model_name = trim($_POST['model_name']);
            $chk = $pdo->prepare("SELECT id FROM printers WHERE model_name = ?");
            $chk->execute([$model_name]);
            if ($chk->fetch()) throw new Exception("A printer with the model name '$model_name' already exists.");

            $pdo->prepare("INSERT INTO printers (model_name) VALUES (?)")->execute([$model_name]);
            $codes = $_POST['case_code'] ?? [];
            $titles = $_POST['case_title'] ?? [];
            $stmt_tc = $pdo->prepare("INSERT INTO test_cases (printer_model, case_code, title) VALUES (?, ?, ?)");
            for($i=0; $i<count($codes); $i++) {
                if(!empty(trim($codes[$i]))) $stmt_tc->execute([$model_name, trim($codes[$i]), trim($titles[$i])]);
            }
            $pdo->commit();
            Helper::setFlash("Printer '$model_name' added.", "success");
        } catch(Exception $e) {
            $pdo->rollBack(); Helper::setFlash("Error: " . $e->getMessage(), "error");
        }
    } 
    elseif (isset($_POST['edit_printer'])) {
        try {
            $pid = $_POST['printer_id'];
            $new_name = trim($_POST['model_name']);
            $old_name = $pdo->prepare("SELECT model_name FROM printers WHERE id = ?");
            $old_name->execute([$pid]);
            $old = $old_name->fetchColumn();

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE printers SET model_name = ? WHERE id = ?")->execute([$new_name, $pid]);
            $pdo->prepare("UPDATE test_cases SET printer_model = ? WHERE printer_model = ?")->execute([$new_name, $old]);
            
            $base64Image = $_POST['cropped_image'] ?? '';
            if (!empty($base64Image)) {
                $image_parts = explode(";base64,", $base64Image);
                if (count($image_parts) == 2) {
                    $image_base64 = base64_decode($image_parts[1]);
                    $fileName = 'imgs/printer_' . time() . '.png'; 
                    $uploadPath = '../' . $fileName;
                    if (file_put_contents($uploadPath, $image_base64)) {
                        $pdo->prepare("UPDATE printers SET printer_path = ? WHERE id = ?")->execute([$fileName, $pid]);
                    }
                }
            }
            $pdo->commit();
            Helper::setFlash("Printer updated.", "success");
        } catch(Exception $e) {
            $pdo->rollBack(); Helper::setFlash("Update failed: " . $e->getMessage(), "error");
        }
    }
    elseif (isset($_POST['add_case'])) {
        $pdo->prepare("INSERT INTO test_cases (printer_model, case_code, title) VALUES (?, ?, ?)")->execute([$_POST['model_name'], $_POST['case_code'], $_POST['title']]);
        Helper::setFlash("Test case added.", "success");
    }
    elseif (isset($_POST['delete_case'])) {
        $pdo->prepare("DELETE FROM test_cases WHERE id = ?")->execute([$_POST['case_id']]);
        Helper::setFlash("Test case deleted.", "success");
    }
    header("Location: admin_printers.php"); exit();
}

$printers = $pdo->query("SELECT * FROM printers ORDER BY model_name")->fetchAll();
$all_cases = $pdo->query("SELECT * FROM test_cases ORDER BY printer_model, case_code")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

$TITLE = "Printers & Cases | Track Manager";
$ASSET_PATH = "../";
require_once '../configs/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<style>
    .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { margin:0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
    
    .printer-accordion { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 10px; overflow: hidden; transition: all 0.2s; }
    .pa-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; cursor: pointer; transition: background 0.15s; }
    .pa-head:hover { background: var(--bg-body); }
    .pa-title-wrap { display: flex; align-items: center; gap: 12px; }
    .pa-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; }
    .pa-title { font-size: 1.05rem; font-weight: 700; color: var(--text-main); }
    .pa-count { font-size: 0.75rem; color: var(--text-muted); background: var(--input-bg); padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); font-weight: 600; }
    .pa-body { display: none; padding: 0; border-top: 1px dashed var(--border); background: var(--bg-body); }
    .printer-accordion.open .pa-body { display: block; }
    
    .dropzone { border: 2px dashed var(--border); border-radius: 8px; padding: 20px; text-align: center; color: var(--text-muted); cursor: pointer; transition: all 0.2s; background: var(--bg-body); }
    .dropzone:hover { border-color: var(--primary); background: rgba(2,136,209,0.05); color: var(--primary); }
    .cropper-container { max-height: 400px; width: 100%; }
</style>

<?php require_once 'admin_nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">print</span>
                Printers & Test Cases
            </h1>
            <button class="btn" style="width:auto;" onclick="openModal('addPrinterModal')">
                <span class="material-symbols-outlined">add</span> Add Printer
            </button>
        </div>

        <?php foreach($printers as $p): 
            $cases = array_filter($all_cases, fn($c) => $c['printer_model'] == $p['model_name']);
            $count = count($cases);
            $pfp = !empty($p['printer_path']) ? '../' . $p['printer_path'] : '';
        ?>
            <div class="printer-accordion" id="acc_<?= $p['id'] ?>">
                <div class="pa-head" onclick="toggleAcc('acc_<?= $p['id'] ?>')">
                    <div class="pa-title-wrap">
                        <div class="pa-icon">
                            <?= Helper::renderPrinterImage($p['printer_path'] ?? null, $p['model_name'], 20) ?>
                        </div>
                        <span class="pa-title"><?= htmlspecialchars($p['model_name']) ?></span>
                        <span class="pa-count"><?= $count ?> Cases</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" class="btn-mini ghost" onclick="event.stopPropagation(); openEditPrinter(<?= $p['id'] ?>, '<?= htmlspecialchars($p['model_name'], ENT_QUOTES) ?>')">
                            <span class="material-symbols-outlined">edit</span> Edit
                        </button>
                        <span class="material-symbols-outlined chev">expand_more</span>
                    </div>
                </div>
                <div class="pa-body">
                    <div style="padding: 16px 20px; background: var(--bg-surface); border-bottom: 1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">TEST CASES</span>
                        <button type="button" class="btn-mini" onclick="openAddCase('<?= htmlspecialchars($p['model_name'], ENT_QUOTES) ?>')">
                            <span class="material-symbols-outlined">add</span> Add Case
                        </button>
                    </div>
                    <?php if($count == 0): ?>
                        <div style="padding: 30px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">No cases defined.</div>
                    <?php else: ?>
                        <table class="d-table" style="background: var(--bg-surface);">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Case Code</th>
                                    <th>Title</th>
                                    <th style="width: 10%; text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cases as $c): ?>
                                    <tr>
                                        <td class="mono" style="color:var(--primary); font-weight:700;">#<?= htmlspecialchars($c['case_code']) ?></td>
                                        <td style="font-weight:500;"><?= htmlspecialchars($c['title']) ?></td>
                                        <td style="text-align:right;">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this case?');">
                                                <input type="hidden" name="delete_case" value="1">
                                                <input type="hidden" name="case_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="icon-btn delete"><span class="material-symbols-outlined">delete</span></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
    </div>
</div>

<div class="modal-overlay" id="addPrinterModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New Printer</h3>
            <button class="modal-close-btn" onclick="closeModal('addPrinterModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="add_printer" value="1">
            <div class="form-group">
                <input type="text" name="model_name" class="form-control" required placeholder="e.g., Flare MFP">
                <label>Printer Model Name</label>
            </div>
            
            <div style="margin: 20px 0 10px; font-weight:800; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">Initial Test Cases</div>
            <div id="case_container" style="display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; gap:10px;">
                    <input type="text" name="case_code[]" class="form-control" placeholder="ID (e.g. 1001)" style="width:30%;">
                    <input type="text" name="case_title[]" class="form-control" placeholder="Title (e.g. Copy Test)" style="flex:1;">
                </div>
            </div>
            <button type="button" class="btn-mini ghost" style="margin-top:10px; width:100%;" onclick="addCaseRow()">+ Add Another Case Row</button>
            
            <button type="submit" class="btn" style="width:100%; margin-top:24px;">Save Printer</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editPrinterModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Printer</h3>
            <button class="modal-close-btn" onclick="closeModal('editPrinterModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body" enctype="multipart/form-data">
            <input type="hidden" name="edit_printer" value="1">
            <input type="hidden" name="printer_id" id="edit_pid">
            <input type="hidden" name="cropped_image" id="croppedImageInput">
            
            <div class="form-group">
                <input type="text" name="model_name" id="edit_model" class="form-control" required>
                <label>Model Name</label>
            </div>
            
            <div style="margin-top: 20px;">
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Update Image</label>
                <div class="dropzone" id="imageDropzone" onclick="document.getElementById('imageUploader').click()">
                    <div id="dropzoneText">
                        <span class="material-symbols-outlined" style="font-size:32px; margin-bottom:8px;">cloud_upload</span><br>
                        Click to upload new image
                    </div>
                </div>
                <input type="file" id="imageUploader" accept="image/*" style="display:none;" onchange="handleImageSelect(event)">
            </div>
            
            <button type="submit" class="btn" style="width:100%; margin-top:24px;">Save Changes</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="addCaseModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add Test Case</h3>
            <button class="modal-close-btn" onclick="closeModal('addCaseModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="add_case" value="1">
            <input type="hidden" name="model_name" id="case_model_name">
            <div class="form-group">
                <input type="text" name="case_code" class="form-control" required>
                <label>Case Code (ID)</label>
            </div>
            <div class="form-group">
                <input type="text" name="title" class="form-control" required>
                <label>Case Title</label>
            </div>
            <button type="submit" class="btn" style="width:100%; margin-top:24px;">Add Case</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="cropModal" style="z-index: 10000;">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Crop Image</h3>
            <button class="modal-close-btn" onclick="closeCropper()"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="modal-body" style="padding:0; background:#000;">
            <div class="cropper-container">
                <img id="imageToCrop" src="" style="max-width: 100%;">
            </div>
        </div>
        <div style="padding: 16px 24px; border-top: 1px solid var(--border); display:flex; justify-content:flex-end; gap:12px;">
            <button type="button" class="btn ghost" onclick="closeCropper()">Cancel</button>
            <button type="button" class="btn" id="cropSubmitBtn">Confirm Crop</button>
        </div>
    </div>
</div>

<script>
    function toggleAcc(id) {
        document.getElementById(id).classList.toggle('open');
        const chev = document.querySelector(`#${id} .chev`);
        chev.style.transform = document.getElementById(id).classList.contains('open') ? 'rotate(180deg)' : 'rotate(0)';
    }

    function addCaseRow() {
        const row = document.createElement('div');
        row.style.display = 'flex'; row.style.gap = '10px';
        row.innerHTML = `<input type="text" name="case_code[]" class="form-control" placeholder="ID" style="width:30%;">
                         <input type="text" name="case_title[]" class="form-control" placeholder="Title" style="flex:1;">`;
        document.getElementById('case_container').appendChild(row);
    }

    function openEditPrinter(id, name) {
        document.getElementById('edit_pid').value = id;
        document.getElementById('edit_model').value = name;
        document.getElementById('edit_model').focus(); document.getElementById('edit_model').blur();
        document.getElementById('croppedImageInput').value = '';
        document.getElementById('dropzoneText').innerHTML = "<span class='material-symbols-outlined' style='font-size:32px; margin-bottom:8px;'>cloud_upload</span><br>Click to upload new image";
        openModal('editPrinterModal');
    }

    function openAddCase(modelName) {
        document.getElementById('case_model_name').value = modelName;
        openModal('addCaseModal');
    }

    // Cropper JS Logic
    let cropper = null;
    const cropModal = document.getElementById('cropModal');
    const imageToCrop = document.getElementById('imageToCrop');
    const fileInput = document.getElementById('imageUploader');

    function handleImageSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            imageToCrop.src = e.target.result;
            cropModal.classList.add('show');
            if (cropper) cropper.destroy();
            cropper = new Cropper(imageToCrop, {
                aspectRatio: 1, viewMode: 1, autoCropArea: 1, dragMode: 'move',
                guides: true, center: true, cropBoxMovable: true, cropBoxResizable: true,
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
        const canvas = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
        document.getElementById('croppedImageInput').value = canvas.toDataURL('image/png');
        document.getElementById('dropzoneText').innerHTML = "<span style='color:var(--success); font-weight:700;'>✓ Image Cropped & Ready!</span><br><span style='font-size:0.75rem;'>Click Save Changes to finish</span>";
        closeCropper();
    });
</script>
</body>
</html>