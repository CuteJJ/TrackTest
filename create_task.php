<?php 
require_once 'controllers/TaskController.php'; 
require_once 'configs/db.php';
require_once 'configs/helper.php';

Helper::requireLogin();
$data = getData($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    <script src="app.js" defer></script>
</head>
<body>

    <?php Helper::displayFlash(); ?>

    <div class="login-card" style="max-width: 900px; margin: 40px auto;">
        
        <div class="brand-section">
            <h1>Create New Task</h1>
            <p>Assign testing activities</p>
        </div>

        <form action="controllers/TaskController.php" method="POST">
            <input type="hidden" name="create_task" value="1">

            <div class="task-form-grid">
                <div class="form-group">
                    <input type="date" name="task_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    <label class="form-label">Task Date</label>
                </div>
                
                <div class="form-group">
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    <label class="form-label">Due Date</label>
                </div>

                <div class="form-group full-width">
                    <select name="testing_type" id="testingType" class="form-control">
                        <option value="Smoke">Smoke Test</option>
                        <option value="Regression">Regression Test</option>
                    </select>
                    <label class="form-label">Testing Workflow</label>
                </div>

                <div class="form-group">
                    <input type="text" name="fw_prev" class="form-control" placeholder=" " required>
                    <label class="form-label">Previous Firmware</label>
                </div>
                <div class="form-group">
                    <input type="text" name="fw_curr" class="form-control" placeholder=" " required>
                    <label class="form-label">Current Firmware</label>
                </div>
                <div class="form-group">
                    <input type="text" name="fw_rec" class="form-control" placeholder=" " required>
                    <label class="form-label">Recovery Firmware</label>
                </div>
                <div class="form-group">
                    <select name="fw_type" class="form-control">
                        <option value="Trunk">Trunk</option>
                        <option value="Branch">Branch</option>
                    </select>
                    <label class="form-label">Firmware Type</label>
                </div>
            </div>

            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--text-main);">Printer Selection</h3>
            
            <div id="printerList">
                <?php foreach ($data['printers'] as $p): ?>
                <div class="printer-card" id="card_<?= $p['id'] ?>">
                    <label class="printer-header">
                        <input type="checkbox" name="printers[]" value="<?= $p['id'] ?>" class="printer-checkbox">
                        <span><?= htmlspecialchars($p['model_name']) ?></span>
                    </label>

                    <div class="assignment-area smoke-area" id="smoke_<?= $p['id'] ?>">
                        <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                            <select id="user_select_<?= $p['id'] ?>" class="form-control" style="padding: 8px;">
                                <option value="">Select Team Member...</option>
                                <?php foreach ($data['users'] as $u): ?>
                                    <option value="<?= $u['id'] ?>" data-name="<?= htmlspecialchars($u['full_name']) ?>">
                                        <?= htmlspecialchars($u['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn" style="width: auto; padding: 0 20px;" 
                                    onclick="addTester(<?= $p['id'] ?>)">Add</button>
                        </div>
                        <div id="tester_list_<?= $p['id'] ?>"></div>
                    </div>

                    <div class="assignment-area regression-area hidden" id="reg_<?= $p['id'] ?>">
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="url" name="regression_urls[<?= $p['id'] ?>]" class="form-control" 
                                   placeholder="https://testrail.com/runs/view/...">
                            <label class="form-label">TestRail URL for <?= htmlspecialchars($p['model_name']) ?></label>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn">Create Task</button>
                <a href="index.php" class="btn" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border);">Cancel</a>
            </div>

        </form>
    </div>
</body>
</html>