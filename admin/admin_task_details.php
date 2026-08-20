<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole(['admin','lead']);

$task_id = $_GET['id'] ?? 0;
if (!$task_id) {
    header("Location: admin_history.php");
    exit();
}

// 1. Fetch Main Task Details
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();
if (!$task) {
    Helper::setFlash("Task not found.", "error");
    header("Location: admin_history.php");
    exit();
}

// 2. Fetch Task Assignments (Printers, Testers, URLs)
$stmt = $pdo->prepare("
    SELECT 
        ta.printer_id,
        p.model_name,
        p.printer_path,
        ta.regression_url,
        ta.overall_status,
        u.id as user_id,
        u.full_name,
        u.pfp_path,
        ta.designation
    FROM task_assignments ta
    JOIN printers p ON ta.printer_id = p.id
    LEFT JOIN users u ON ta.user_id = u.id
    WHERE ta.task_id = ?
    ORDER BY p.model_name ASC
");
$stmt->execute([$task_id]);
$assignments = $stmt->fetchAll();

// Group assignments by Printer ID
$printer_groups = [];
foreach ($assignments as $row) {
    $pid = $row['printer_id'];
    if (!isset($printer_groups[$pid])) {
        $printer_groups[$pid] = [
            'model_name' => $row['model_name'],
            'printer_path' => $row['printer_path'],
            'regression_url' => $row['regression_url'],
            'overall_status' => $row['overall_status'],
            'testers' => []
        ];
    }
    if ($row['user_id']) {
        $printer_groups[$pid]['testers'][] = [
            'user_id' => $row['user_id'],
            'full_name' => $row['full_name'],
            'pfp_path' => $row['pfp_path'],
            'designation' => $row['designation']
        ];
    }
}

// 3. If Smoke Test, Fetch Test Case Details for each Printer
$smoke_cases = [];
if ($task['testing_type'] == 'Smoke') {
    foreach ($printer_groups as $pid => $group) {
        $model_name = $group['model_name'];
        
        $sql = "
            SELECT 
                tc.id as case_id,
                tc.case_code,
                tc.title,
                tr.status,
                tr.assigned_to,
                u_assign.full_name as assignee_name,
                u_update.full_name as updater_name,
                tr.updated_at
            FROM test_cases tc
            LEFT JOIN test_results tr 
                ON tc.id = tr.test_case_id AND tr.task_id = ? AND tr.printer_id = ?
            LEFT JOIN users u_assign ON tr.assigned_to = u_assign.id
            LEFT JOIN users u_update ON tr.updated_by = u_update.id
            WHERE tc.printer_model = ?
            ORDER BY tc.case_code ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$task_id, $pid, $model_name]);
        $smoke_cases[$pid] = $stmt->fetchAll();
    }
}

$TITLE = "Task #" . $task_id . " Details | Track Manager";
$ASSET_PATH = "../";
require_once '../configs/header.php';
?>
<style>
    .detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px; }
    .detail-title { font-size: 1.6rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 10px; }
    
    .task-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; background: var(--bg-surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 24px; }
    .meta-item { display: flex; flex-direction: column; }
    .meta-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; }
    .meta-value { font-size: 0.95rem; font-weight: 600; color: var(--text-main); }
    .meta-value.mono { font-family: var(--font-mono); }

    .printer-block { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 24px; overflow: hidden; }
    .printer-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: var(--bg-body); border-bottom: 1px solid var(--border); }
    .printer-name { display: flex; align-items: center; gap: 10px; font-size: 1.1rem; font-weight: 700; color: var(--primary); }
    .printer-name img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
    
    .tester-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px 6px 6px; border-radius: 30px; background: var(--bg-surface); border: 1px solid var(--border); margin-right: 8px; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; }
    .tester-chip img { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
    .tester-chip .role-tag { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; }
    .tester-chip .role-tag.support { background: var(--text-muted); }

    .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .status-badge.Pass { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
    .status-badge.Fail { background: var(--error-bg); color: var(--error); border: 1px solid var(--error); }
    .status-badge.Blocked { background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked); }
    .status-badge.NA { background: var(--na-bg); color: var(--na); border: 1px solid var(--na); }
    .status-badge.Pending { background: var(--bg-body); color: var(--text-muted); border: 1px solid var(--border); }
    .status-badge.Completed { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid #3b82f6; }

    .url-display { display: inline-flex; align-items: center; gap: 6px; background: var(--bg-body); padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border); font-weight: 500; }
    .url-display a { color: var(--primary); text-decoration: none; }
    .url-display a:hover { text-decoration: underline; }

    .back-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: transparent; border: 1px solid var(--border); border-radius: 6px; color: var(--text-muted); text-decoration: none; font-weight: 600; transition: all 0.2s; }
    .back-btn:hover { background: var(--bg-body); border-color: var(--text-main); color: var(--text-main); }

    /* Test Case Table Styles */
    .case-section { padding: 16px 20px; }
    .case-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 12px; }
    .case-table th, .case-table td { padding: 8px 12px; border-bottom: 1px solid var(--border); text-align: left; }
    .case-table th { background: var(--bg-body); font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }
    .case-table tr:last-child td { border-bottom: none; }
    
    .case-status-tag { padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
    .case-status-tag.Pass { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
    .case-status-tag.Fail { background: var(--error-bg); color: var(--error); border: 1px solid var(--error); }
    .case-status-tag.Blocked { background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked); }
    .case-status-tag.NA { background: var(--na-bg); color: var(--na); border: 1px solid var(--na); }
    .case-status-tag.Pending { background: var(--bg-body); color: var(--text-muted); border: 1px solid var(--border); }
    .case-status-tag.In-Progress { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
    
    .progress-summary { display: flex; gap: 16px; margin-bottom: 12px; font-size: 0.85rem; color: var(--text-muted); }
    .progress-summary strong { color: var(--text-main); }

    /* Remove Button Styles */
    .btn-remove-case {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
        border: 1px solid var(--error);
        border-radius: 6px;
        padding: 4px 4px;
        min-width: 32px;
        height: 32px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-remove-case:hover:not(:disabled) {
        background: var(--error);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    .btn-remove-case:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .btn-remove-case .material-symbols-outlined { font-size: 18px; }

    /* Update Picked Up stat count after removal */
    .stat-picked-up { transition: all 0.2s; }
</style>

<?php require_once 'admin_nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="detail-header">
            <h1 class="detail-title">
                <span class="material-symbols-outlined" style="color: var(--primary);">assignment</span>
                Task #<?= $task_id ?> Details
            </h1>
            <a href="admin_history.php" class="back-btn">
                <span class="material-symbols-outlined">arrow_back</span> Back to History
            </a>
        </div>

        <!-- Task Meta Information -->
        <div class="task-meta-grid">
            <div class="meta-item">
                <span class="meta-label">Date</span>
                <span class="meta-value"><?= date('M d, Y', strtotime($task['task_date'])) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Type</span>
                <span class="meta-value">
                    <span class="badge <?= $task['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>">
                        <?= htmlspecialchars($task['testing_type']) ?>
                    </span>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Current FW</span>
                <span class="meta-value mono"><?= htmlspecialchars($task['fw_version_current']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">FW Type</span>
                <span class="meta-value"><?= htmlspecialchars($task['fw_type']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Due Date</span>
                <span class="meta-value"><?= date('M d, Y', strtotime($task['due_date'])) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Recovery FW</span>
                <span class="meta-value mono" style="color: var(--error);"><?= htmlspecialchars($task['fw_version_rec']) ?></span>
            </div>
        </div>

        <!-- Printers & Assignments -->
        <h3 style="font-size: 0.9rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 12px;">
            Assigned Printers &amp; Details
        </h3>

        <?php if (empty($printer_groups)): ?>
            <div class="empty-state" style="border: none; background: var(--bg-surface); border-radius: 12px; padding: 40px;">
                <span class="material-symbols-outlined">print_disabled</span>
                <p>No printers assigned to this task.</p>
            </div>
        <?php else: ?>
            <?php foreach ($printer_groups as $pid => $group): ?>
                <div class="printer-block">
                    <div class="printer-header">
                        <div class="printer-name">
                            <?= Helper::renderPrinterImage($group['printer_path'] ?? null, $group['model_name'], 24) ?>
                            <?= htmlspecialchars($group['model_name']) ?>
                        </div>
                        <div>
                            <span class="status-badge <?= $group['overall_status'] ?>">
                                <?= $group['overall_status'] ?: 'Pending' ?>
                            </span>
                        </div>
                    </div>

                    <div style="padding: 16px 20px;">
                        <?php if ($task['testing_type'] == 'Smoke'): ?>
                            <!-- SMOKE: Show Assigned Testers & Case Details -->
                            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 8px;">
                                Assigned Testers
                            </div>
                            
                            <?php if (empty($group['testers'])): ?>
                                <span style="color: var(--text-muted); font-style: italic;">No testers assigned.</span>
                            <?php else: ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;">
                                    <?php foreach ($group['testers'] as $tester): ?>
                                        <span class="tester-chip">
                                            <img src="<?= htmlspecialchars(!empty($tester['pfp_path']) ? '../' . $tester['pfp_path'] : '../imgs/default_pfp.svg') ?>" alt="<?= htmlspecialchars($tester['full_name']) ?>">
                                            <?= htmlspecialchars($tester['full_name']) ?>
                                            <span class="role-tag <?= $tester['designation'] == 'Support' ? 'support' : '' ?>">
                                                <?= htmlspecialchars($tester['designation']) ?>
                                            </span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Test Case Execution Details -->
                            <?php 
                            $cases = $smoke_cases[$pid] ?? [];
                            $total_cases = count($cases);
                            $completed_cases = 0;
                            $picked_up = 0;
                            foreach ($cases as $c) {
                                if (!empty($c['status']) && $c['status'] !== 'Pending') {
                                    $completed_cases++;
                                }
                                if (!empty($c['assigned_to'])) {
                                    $picked_up++;
                                }
                            }
                            ?>
                            
                            <div class="case-section" style="border-top: 1px solid var(--border); padding-top: 16px; margin-top: 16px;">
                                <div class="progress-summary">
                                    <span><strong>Total Cases:</strong> <?= $total_cases ?></span>
                                    <span><strong class="stat-picked-up">Picked Up:</strong> <span class="stat-picked-up" id="pickedUpCount_<?= $pid ?>"><?= $picked_up ?></span></span>
                                    <span><strong>Completed:</strong> <?= $completed_cases ?></span>
                                </div>

                                <?php if ($total_cases > 0): ?>
                                    <table class="case-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 18%;">Case ID</th>
                                                <th style="width: 32%;">Title</th>
                                                <th style="width: 20%;">Assigned To / Updated By</th>
                                                <th style="width: 18%;">Status</th>
                                                <th style="width: 12%; text-align: center;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cases as $case): 
                                                $status = $case['status'] ?: 'Pending';
                                                $statusClass = str_replace(' ', '-', $status);
                                                $isPickedUp = !empty($case['assigned_to']);
                                                
                                                // --- NEW: Skip Unassigned rows completely ---
                                                if (!$isPickedUp) { continue; }
                                            ?>
                                            <tr id="row_<?= $case['case_id'] ?>">
                                                <td class="mono" style="font-weight: 700; color: var(--primary);">#<?= htmlspecialchars($case['case_code']) ?></td>
                                                <td><?= htmlspecialchars($case['title']) ?></td>
                                                <td>
                                                    <?php if (!empty($case['assigned_to'])): ?>
                                                        <?= htmlspecialchars($case['assignee_name']) ?>
                                                        <?php if (!empty($case['updater_name']) && $case['updater_name'] !== $case['assignee_name']): ?>
                                                            <br><span style="font-size: 0.7rem; color: var(--text-muted);">(Updated by: <?= htmlspecialchars($case['updater_name']) ?>)</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style: italic;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="case-status-tag <?= $statusClass ?>">
                                                        <?= $status ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <?php if ($status === 'Pending'): ?>
                                                        <button type="button" 
                                                                class="btn-remove-case" 
                                                                onclick="unclaimCase(<?= $task_id ?>, <?= $pid ?>, <?= $case['case_id'] ?>, this)"
                                                                title="Unassign test case from tester">
                                                            <span class="material-symbols-outlined">delete</span>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Button is completely hidden for non-Pending statuses -->
                                                        <span style="opacity: 0.3; pointer-events: none;">
                                                            <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p style="color: var(--text-muted); font-style: italic; margin: 10px 0;">No test cases defined for this printer model.</p>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <!-- REGRESSION: Show URL -->
                            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 8px;">
                                TestRail Run URL
                            </div>
                            
                            <?php if (!empty($group['regression_url'])): ?>
                                <div class="url-display">
                                    <span class="material-symbols-outlined" style="font-size: 16px; color: var(--primary);">link</span>
                                    <a href="<?= htmlspecialchars($group['regression_url']) ?>" target="_blank">
                                        <?= htmlspecialchars($group['regression_url']) ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic;">No URL assigned.</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<script>
function unclaimCase(taskId, printerId, caseId, buttonElement) {
    if (!confirm('Are you sure you want to remove this test case from the assigned tester? This will reset its status to Pending.')) {
        return;
    }

    // Disable button to prevent double clicks
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<span class="material-symbols-outlined" style="animation: spin 1s linear infinite;">refresh</span>';

    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('printer_id', printerId);
    formData.append('case_id', caseId);

    fetch('ajax_unclaim_case.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the entire row from the DOM
            const row = document.getElementById('row_' + caseId);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    row.remove();
                    
                    // Update the "Picked Up" count in the header
                    const countSpan = document.getElementById('pickedUpCount_' + printerId);
                    if (countSpan) {
                        let currentCount = parseInt(countSpan.textContent);
                        if (!isNaN(currentCount) && currentCount > 0) {
                            countSpan.textContent = currentCount - 1;
                        }
                    }
                }, 300);
            }
            
            // Show success feedback
            if (typeof showDynamicToast === 'function') {
                showDynamicToast('Test case removed successfully.', 'success');
            } else {
                alert('Test case removed successfully.');
            }
        } else {
            alert('Error: ' + (data.error || 'Failed to remove test case.'));
            buttonElement.disabled = false;
            buttonElement.innerHTML = '<span class="material-symbols-outlined">delete</span>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error. Please try again.');
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<span class="material-symbols-outlined">delete</span>';
    });
}
</script>

</body>
</html>