<?php 
require_once 'controllers/DashboardController.php'; 
require_once 'configs/db.php';
require_once 'configs/helper.php';

// --- MISSING EMAIL ALERT ---
if (isset($_SESSION['user_id']) && !isset($_SESSION['flash'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_email = $stmt->fetchColumn();
    
    // If the email column is completely empty, throw the flash error
    if (empty(trim((string)$user_email))) {
        Helper::setFlash("⚠️ Action Required: Please update your Account Settings with a valid email address for password recovery.", "error");
    }
}

$TITLE = "Dashboard | Track Manager";
require_once 'configs/header.php';
?>

    <style>
        .custom-chart-layout { display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 24px; flex-wrap: wrap; }
        .custom-chart-legend { display: grid; grid-template-columns: 1fr 1fr; column-gap: 20px; row-gap: 16px; flex: 1; min-width: 250px; }
        .leg-item { display: flex; align-items: flex-start; gap: 12px; }
        .leg-color { width: 16px; height: 16px; border-radius: 50%; display: inline-block; margin-top: 3px; flex-shrink: 0; }
        .leg-item div { display: flex; flex-direction: column; }
        .leg-item strong { font-size: 0.95rem; font-weight: 700; color: var(--text-main); line-height: 1.2; margin-bottom: 2px; }
        .leg-item span { font-size: 0.8rem; color: var(--text-muted); }
        .custom-chart-summary { border-left: 1px solid var(--border); padding-left: 32px; min-width: 150px; display: flex; flex-direction: column; justify-content: center; }
        .summary-val { font-size: 3.5rem; font-weight: 300; line-height: 1; color: var(--text-main); letter-spacing: -2px; font-family: 'DM Sans', sans-serif; }
        .summary-label { font-size: 1.2rem; color: var(--text-muted); margin-bottom: 12px; font-weight: 400; }
        .summary-sub { font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; }
        @media (max-width: 1100px) {
            .custom-chart-layout { justify-content: center; }
            .custom-chart-summary { border-left: none; padding-left: 0; border-top: 1px solid var(--border); padding-top: 20px; width: 100%; text-align: center; }
        }
        @media (max-width: 600px) { .custom-chart-legend { grid-template-columns: 1fr; } }
        
        /* Interactive FW Card */
        .fw-card { transition: all 0.2s ease; cursor: pointer; }
        .fw-card:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(2,136,209,0.1); transform: translateY(-2px); }
        
        /* Modal Table Overrides for cleaner look */
        .fw-modal-table th { padding: 14px 24px !important; background: var(--bg-surface) !important; }
        .fw-modal-table td { padding: 14px 24px !important; }
        
        /* Fix for 13-inch laptop cutoff */
        .d-table th, .d-table td {
            padding: 8px 10px;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .d-table .btn-mini {
            padding: 4px 10px;
            font-size: 0.7rem;
        }
        .d-table .badge {
            font-size: 0.65rem;
            padding: 2px 8px;
        }
        .dash-wrapper {
            padding: 12px;
        }
        @media (max-width: 1100px) {
            .dash-split-row {
                grid-template-columns: 1fr;
            }
        }

        /* --- ALIGNMENT FIX: Full width rows --- */
        .dash-wrapper {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 12px;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .d-card {
            width: 100%;
            box-sizing: border-box;
        }
        
        .d-card-body {
            width: 100%;
        }
        
        .fw-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            padding: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .chart-layout {
            display: flex;
            min-height: 350px;
            width: 100%;
        }
        
        .chart-sidebar {
            flex: 0 0 340px;
        }
        
        .chart-display {
            flex: 1;
            min-width: 0;
        }
        
        @media (max-width: 768px) {
            .chart-sidebar {
                flex: auto;
                flex-basis: auto;
            }
        }
        
        /* Ensure the main content wrapper stretches to fit */
        .page-content-scroll {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .dash-wrapper {
            flex: 1;
        }

        /* --- In Progress Badge Styles --- */
        .badge-in-progress {
            background: rgba(234, 179, 8, 0.15);
            color: #ca8a04;
            border: 1px solid rgba(234, 179, 8, 0.35);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .badge-in-progress .pulse-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ca8a04;
            display: inline-block;
            position: relative;
        }
        .badge-in-progress .pulse-dot::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            background: rgba(234, 179, 8, 0.35);
            animation: pulse-ring 1.5s ease-out infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.8); opacity: 0; }
        }
    </style>

<?php require_once 'configs/nav.php'; ?>

    <div class="page-content-scroll">
        <div class="dash-wrapper">
            
            <!-- 1. MY ASSIGNMENTS TABLE (Full Width) -->
            <?php if ($_SESSION['role'] !== 'admin'): ?>
            <div class="d-card">
                <div class="d-card-header">
                    <div class="d-card-title">
                        <span class="material-symbols-outlined">task_alt</span>
                        <?php if ($_SESSION['role'] === 'lead'): ?>
                            Active Testing Tasks <span class="badge badge-smoke" style="margin-left: 10px; font-size: 0.7rem;">This Week</span>
                        <?php else: ?>
                            My Assignments <span class="badge badge-smoke" style="margin-left: 10px; font-size: 0.7rem;">Today</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-card-body" style="padding-top: 0;">
                    <div id="tasks-container">
                        <?php if ($_SESSION['role'] === 'lead'): ?>
                            <?php if (empty($lead_tasks)): ?>
                                <div class="empty-state">
                                    <span class="material-symbols-outlined">inbox</span>
                                    <p>No active tasks scheduled for this week.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="d-table">
                                        <colgroup>
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Printer(s)</th>
                                                <th>Progress</th>
                                                <th>Firmware</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lead_tasks as $task): ?>
                                                <?php
                                                $is_complete = ($task['completed_cases'] >= $task['total_cases']) && ($task['total_cases'] > 0);
                                                // Calculate percentage: Smoke uses cases, Regression uses 100% if Completed, otherwise 0%
                                                if ($task['testing_type'] == 'Regression') {
                                                    $percent = ($task['overall_status'] == 'Completed') ? 100 : 0;
                                                } else {
                                                    $percent = $task['total_cases'] > 0 ? round(($task['completed_cases'] / $task['total_cases']) * 100) : 0;
                                                }
                                                $is_complete = ($percent == 100);
                                                $rowId = "task_" . $task['task_id'];
                                                $printerName = htmlspecialchars($task['model_name']);
                                                ?>

                                                <tr class="expand-trigger main-row" onclick="toggleRow('<?= $rowId ?>', this)">
                                                    <td>
                                                        <span class="mono" style="font-size:0.75rem; color:var(--text-muted);">
                                                            <?= date('M d', strtotime($task['task_date'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $task['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>">
                                                            <?= htmlspecialchars($task['testing_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($task['testing_type'] == 'Regression'): ?>
                                                            <!-- Regression: Display printers as a vertical list -->
                                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                                <?php 
                                                                $printerNames = explode(', ', $printerName);
                                                                $printerPaths = explode(',', $task['printer_path'] ?? '');
                                                                foreach ($printerNames as $idx => $name): 
                                                                    $path = isset($printerPaths[$idx]) ? trim($printerPaths[$idx]) : '';
                                                                ?>
                                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                                        <div style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border-radius: 50%; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border);">
                                                                            <?= Helper::renderPrinterImage($path ?: null, trim($name), 12) ?>
                                                                        </div>
                                                                        <span style="font-size:0.82rem; font-weight:600; color:var(--text-main);"><?= htmlspecialchars(trim($name)) ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <!-- Smoke: Single printer -->
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <div style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border-radius: 50%; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border);">
                                                                    <?= Helper::renderPrinterImage($task['printer_path'] ?? null, $printerName, 14) ?>
                                                                </div>
                                                                <strong style="font-size:0.82rem;"><?= $printerName ?></strong>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="prog-wrap">
                                                            <div class="prog-meta">
                                                                <?php if ($task['testing_type'] == 'Regression'): ?>
                                                                    <!-- Regression: Only show percentage, no fraction -->
                                                                    <span></span>
                                                                    <span><?= ($task['overall_status'] == 'Completed') ? '100%' : '0%' ?></span>
                                                                <?php else: ?>
                                                                    <!-- Smoke: Show fraction and percentage -->
                                                                    <span><?= $task['completed_cases'] ?>/<?= $task['total_cases'] ?></span>
                                                                    <span><?= $percent ?>%</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="prog-track">
                                                                <div class="prog-fill <?= $is_complete ? 'complete' : '' ?>" 
                                                                     style="width:<?= ($task['testing_type'] == 'Regression' && $task['overall_status'] == 'Completed') ? '100%' : $percent ?>%;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="font-size:0.8rem; color:var(--text-muted);">
                                                        <?= htmlspecialchars($task['fw_type']) ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($task['overall_status'] == 'Pass'): ?>
                                                            <span class="badge badge-pass">
                                                                <span class="material-symbols-outlined">check_circle</span> PASSED
                                                            </span>
                                                        <?php elseif ($task['overall_status'] == 'Fail'): ?>
                                                            <span class="badge badge-fail">
                                                                <span class="material-symbols-outlined">cancel</span> FAILED
                                                            </span>
                                                        <?php elseif ($task['overall_status'] == 'Blocked'): ?>
                                                            <span class="badge" style="background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked);">
                                                                <span class="material-symbols-outlined">block</span> BLOCKED
                                                            </span>
                                                        <?php elseif ($task['overall_status'] == 'N/A'): ?>
                                                            <span class="badge" style="background: var(--na-bg); color: var(--na); border: 1px solid var(--na);">
                                                                <span class="material-symbols-outlined">do_not_disturb_on</span> N/A
                                                            </span>
                                                        <?php else: ?>
                                                            <!-- FIXED: Show "In Progress" for Pending or empty status -->
                                                            <span class="badge-in-progress">
                                                                <span class="pulse-dot"></span> IN PROGRESS
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <span class="material-symbols-outlined chevron-icon" id="chev-<?= $rowId ?>">expand_more</span>
                                                    </td>
                                                </tr>

                                                <tr class="expanded-row">
                                                    <td colspan="7" style="padding: 0;">
                                                        <div class="accordion-wrapper" id="<?= $rowId ?>">
                                                            <div class="expanded-content">
                                                                <div class="expand-detail">
                                                                    <span class="expand-detail-label">Due Date</span>
                                                                    <span class="expand-detail-value" style="font-family:var(--font-body);"><?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                                                                </div>
                                                                <div class="expand-detail">
                                                                    <span class="expand-detail-label">Current FW</span>
                                                                    <span class="expand-detail-value" style="color:var(--primary);"><?= htmlspecialchars($task['fw_version_current']) ?></span>
                                                                </div>
                                                                <div class="expand-detail">
                                                                    <span class="expand-detail-label">Prev / Rec FW</span>
                                                                    <span class="expand-detail-value">
                                                                        <span style="color:var(--text-muted); opacity:0.8;"><?= htmlspecialchars($task['fw_version_prev']) ?></span>
                                                                        <span style="color:var(--border); margin:0 4px;">/</span>
                                                                        <span style="color:var(--error);"><?= htmlspecialchars($task['fw_version_rec']) ?></span>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?= Helper::renderPagination($pagination['leadRows'], $pagination['perPage'], $pagination['currentPage']) ?>
                            <?php endif; ?>

                        <?php else: ?>
                            <?php if (empty($my_tasks)): ?>
                                <div class="empty-state">
                                    <span class="material-symbols-outlined">assignment</span>
                                    <p>No tasks assigned to you for today.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="d-table">
                                        <colgroup>
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                            <col style="width:auto;">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <?= Helper::renderSortHeader('task_date', 'Date', $sort ?? null, $order ?? null) ?>
                                                <?= Helper::renderSortHeader('testing_type', 'Type', $sort ?? null, $order ?? null) ?>
                                                <?= Helper::renderSortHeader('model_name', 'Printer', $sort ?? null, $order ?? null) ?>
                                                <?= Helper::renderSortHeader('fw_version_current', 'Current FW', $sort ?? null, $order ?? null) ?>
                                                <?= Helper::renderSortHeader('fw_type', 'Firmware', $sort ?? null, $order ?? null) ?>
                                                <th>Role</th>
                                                <?= Helper::renderSortHeader('overall_status', 'Status', $sort ?? null, $order ?? null) ?>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($my_tasks as $task): ?>
                                                <?php $printerName = htmlspecialchars($task['model_name']); ?>
                                                <tr class="main-row">
                                                    <td>
                                                        <span class="mono" style="font-size:0.75rem; color:var(--text-muted);">
                                                            <?= date('M d', strtotime($task['task_date'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $task['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>">
                                                            <?= htmlspecialchars($task['testing_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <div style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border-radius: 50%; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border);">
                                                                <?= Helper::renderPrinterImage($task['printer_path'] ?? null, $printerName, 14) ?>
                                                            </div>
                                                            <strong style="font-size:0.82rem;" title="<?= $printerName ?>"><?= $printerName ?></strong>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="mono" style="font-size:0.78rem; color:var(--primary); font-weight:600;">
                                                            <?= htmlspecialchars($task['fw_version_current']) ?>
                                                        </span>
                                                    </td>
                                                    <td style="font-size:0.75rem; color:var(--text-muted);">
                                                        <?= htmlspecialchars($task['fw_type']) ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($task['testing_type'] == 'Regression'): ?>
                                                            <span class="badge badge-reg">ALL</span>
                                                        <?php else: ?>
                                                            <span class="badge <?= $task['designation'] == 'Main' ? 'badge-main' : 'badge-support' ?>">
                                                                <?= htmlspecialchars($task['designation']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($task['overall_status'] == 'Pass'): ?>
                                                            <span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                                        <?php elseif ($task['overall_status'] == 'Fail'): ?>
                                                            <span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                                        <?php elseif ($task['overall_status'] == 'Blocked'): ?>
                                                            <span class="badge" style="background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked);">
                                                                <span class="material-symbols-outlined">block</span> BLOCKED
                                                            </span>
                                                        <?php elseif ($task['overall_status'] == 'N/A'): ?>
                                                            <span class="badge" style="background: var(--na-bg); color: var(--na); border: 1px solid var(--na);">
                                                                <span class="material-symbols-outlined">do_not_disturb_on</span> N/A
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);">
                                                                <span class="material-symbols-outlined" style="font-size: 12px;">progress_activity</span> In Progress
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        // --- LOCK ACTION IF STATUS IS FINALIZED ---
                                                        $finalizedStatuses = ['Pass', 'Fail', 'Blocked', 'N/A', 'Completed'];
                                                        $isLocked = in_array(trim($task['overall_status'] ?? ''), $finalizedStatuses);
                                                        ?>
                                                        
                                                        <?php if ($isLocked): ?>
                                                            <!-- EXACT CSS FROM ASSIGNMENTS.PHP - Applied inline to guarantee matching -->
                                                            <button class="btn-disabled" style="
                                                                display: inline-flex; 
                                                                align-items: center; 
                                                                gap: 6px; 
                                                                padding: 4px 14px; 
                                                                border-radius: 6px; 
                                                                background: var(--bg-body) !important; 
                                                                color: var(--text-muted) !important; 
                                                                border: 1px solid var(--border) !important; 
                                                                cursor: not-allowed; 
                                                                font-size: 0.82rem; 
                                                                font-weight: 600;
                                                                opacity: 0.8;
                                                            " title="Task is finalized">
                                                                <span class="material-symbols-outlined" style="font-size: 16px; color: var(--text-muted);">lock</span> 
                                                                Locked
                                                            </button>
                                                        <?php else: ?>
                                                            <a href="execute_task.php?task_id=<?= $task['id'] ?>&printer_id=<?= $task['printer_id'] ?>" class="btn-mini">
                                                                <span class="material-symbols-outlined">play_arrow</span> Execute
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?= Helper::renderPagination($pagination['myRows'], $pagination['perPage'], $pagination['currentPage']) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 2. FIRMWARE OVERVIEW (Full Width, Fixed Alignment) -->
            <div class="d-card">
                <div class="d-card-header">
                    <div class="d-card-title">
                        <span class="material-symbols-outlined">memory</span>
                        Firmware Overview
                    </div>
                </div>
                <div class="d-card-body" style="padding: 0;">
                    <div class="fw-grid">
                        <?php foreach ($firmware_overview as $fw): ?>
                            <div class="fw-card tooltip-trigger" data-tip="View History" onclick="openFwModal(<?= $fw['printer_id'] ?>, '<?= htmlspecialchars($fw['model'], ENT_QUOTES) ?>')">
                                <div class="fw-model" style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden;">
                                        <?= Helper::renderPrinterImage($fw['printer_path'] ?? null, $fw['model'], 14) ?>
                                    </div>
                                    <?= htmlspecialchars($fw['model']) ?>
                                </div>
                                <div class="fw-row">
                                    <span class="fw-label">Branch</span>
                                    <span class="fw-value"><?= htmlspecialchars($fw['branch']) ?></span>
                                </div>
                                <div class="fw-row">
                                    <span class="fw-label">Trunk</span>
                                    <span class="fw-value trunk"><?= htmlspecialchars($fw['trunk']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

        <div class="modal-overlay" id="fwModal">
        <div class="modal-box" style="max-width: 480px;">
            
            <div class="modal-header">
                <h3 style="display:flex; align-items:center; gap:10px;">
                    <span class="material-symbols-outlined" style="color:var(--primary);">memory</span>
                    <span id="fwModalTitle">Firmware History</span>
                </h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('fwModal')" title="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <div class="modal-body" style="padding: 0;">
                <!-- Search Box -->
                <div style="padding: 16px 20px 12px; border-bottom: 1px solid var(--border);">
                    <div style="position: relative; display: flex; align-items: center;">
                        <span class="material-symbols-outlined" style="position: absolute; left: 12px; font-size: 18px; color: var(--text-muted); pointer-events: none;">search</span>
                        <input 
                            type="text" 
                            id="fwSearchInput" 
                            placeholder="Search firmware versions..." 
                            oninput="filterFwTable(this.value)"
                            style="
                                width: 100%; 
                                padding: 10px 12px 10px 38px; 
                                border: 1px solid var(--border); 
                                border-radius: 10px; 
                                background: var(--bg-surface); 
                                color: var(--text-main); 
                                font-family: var(--font-mono); 
                                font-size: 0.82rem; 
                                outline: none; 
                                transition: border-color 0.2s, box-shadow 0.2s;
                            "
                            onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(2,136,209,0.1)';"
                            onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                        >
                        <button 
                            id="fwSearchClear" 
                            onclick="clearFwSearch()" 
                            title="Clear search"
                            style="
                                position: absolute; right: 8px; 
                                background: none; border: none; 
                                color: var(--text-muted); cursor: pointer; 
                                padding: 4px; border-radius: 6px; 
                                display: none; 
                                transition: color 0.15s, background 0.15s;
                            "
                            onmouseover="this.style.color='var(--text-main)'; this.style.background='var(--bg-surface)';"
                            onmouseout="this.style.color='var(--text-muted)'; this.style.background='none';"
                        >
                            <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
                        </button>
                    </div>
                    <div id="fwSearchCount" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 8px; padding-left: 2px; display: none;"></div>
                </div>

                <!-- Table with Scrollbar -->
                <div class="table-responsive" style="max-height: 380px; overflow-y: auto; border-radius: 0 0 16px 16px; scroll-behavior: smooth;" id="fwTableScroll">
                    <table class="d-table fw-modal-table" style="margin: 0; border: none;">
                        <thead style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th style="cursor:pointer; user-select:none; width: 50%;" onclick="sortFw('branch')">
                                    <div style="display:flex; align-items:center; gap:6px; font-size:0.75rem;">
                                        Branch <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);" id="sortIcon_branch">arrow_downward</span>
                                    </div>
                                </th>
                                <th style="cursor:pointer; user-select:none; width: 50%;" onclick="sortFw('trunk')">
                                    <div style="display:flex; align-items:center; gap:6px; font-size:0.75rem;">
                                        Trunk <span class="material-symbols-outlined" style="font-size:16px; color:var(--text-muted);" id="sortIcon_trunk">arrow_downward</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="fwModalBody">
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    <?php
    function time_ago($datetime)
    {
        $interval = time() - strtotime($datetime);
        if ($interval < 60) return 'Just now';
        if ($interval < 3600) return floor($interval / 3600) . 'm ago';
        if ($interval < 86400) return floor($interval / 3600) . 'h ago';
        return floor($interval / 86400) . 'd ago';
    }
    ?>

    <script>
        // ── Row Toggle ───────────────────────────────────────
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

        // ── AJAX Pagination ─────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const tasksContainer = document.getElementById('tasks-container');

            function loadData(url) {
                window.showLoader();
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('tasks-container');
                        if (newContainer) tasksContainer.innerHTML = newContainer.innerHTML;

                        window.history.pushState({}, '', url);
                        attachTooltips(); 
                        window.hideLoader();
                    })
                    .catch(err => {
                        console.error('AJAX Error:', err);
                        window.hideLoader();
                    });
            }

                        // Capture Page Links and Sort Headers for AJAX reload
            document.addEventListener('click', function(e) {
                const link = e.target.closest('a');
                // Intercept pagination links, or sort links inside <th> that contain 'sort='
                if (link && (link.classList.contains('page-link') || (link.closest('th') && link.href.includes('sort=')))) {
                    e.preventDefault();
                    loadData(link.href);
                }
            });
            
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('per-page-select')) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('per_page', e.target.value);
                    url.searchParams.set('page', '1');
                    loadData(url);
                }
            });
        });

                // ── Firmware Modal Logic ─────────────────────────
        let fwData = { branch: [], trunk: [] };
        let sortState = { branch: 'desc', trunk: 'desc' };
        let fwSearchTerm = '';

        function openFwModal(printerId, printerName) {
            document.getElementById('fwModalTitle').textContent = printerName;
            document.getElementById('fwModalBody').innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 40px;"><div class="loader-spinner"></div></td></tr>';
            
            // Reset search
            const searchInput = document.getElementById('fwSearchInput');
            searchInput.value = '';
            fwSearchTerm = '';
            document.getElementById('fwSearchClear').style.display = 'none';
            document.getElementById('fwSearchCount').style.display = 'none';
            
            openModal('fwModal');

            fetch(`index.php?fetch_firmware_history=1&printer_id=${printerId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        fwData.branch = data.branch;
                        fwData.trunk = data.trunk;
                        sortState = { branch: 'desc', trunk: 'desc' };
                        renderFwTable();
                    } else {
                        document.getElementById('fwModalBody').innerHTML = '<tr><td colspan="2" style="text-align:center; color:var(--error); padding: 40px;">Failed to load data.</td></tr>';
                    }
                });
        }

        function filterFwTable(term) {
            fwSearchTerm = term.trim().toLowerCase();
            const clearBtn = document.getElementById('fwSearchClear');
            clearBtn.style.display = fwSearchTerm.length > 0 ? 'block' : 'none';
            renderFwTable();
        }

        function clearFwSearch() {
            const searchInput = document.getElementById('fwSearchInput');
            searchInput.value = '';
            fwSearchTerm = '';
            document.getElementById('fwSearchClear').style.display = 'none';
            document.getElementById('fwSearchCount').style.display = 'none';
            searchInput.focus();
            renderFwTable();
        }

        function sortFw(column) {
            sortState[column] = sortState[column] === 'desc' ? 'asc' : 'desc';
            
            document.getElementById('sortIcon_branch').style.color = column === 'branch' ? 'var(--primary)' : 'var(--text-muted)';
            document.getElementById('sortIcon_branch').textContent = sortState.branch === 'desc' ? 'arrow_downward' : 'arrow_upward';
            
            document.getElementById('sortIcon_trunk').style.color = column === 'trunk' ? 'var(--primary)' : 'var(--text-muted)';
            document.getElementById('sortIcon_trunk').textContent = sortState.trunk === 'desc' ? 'arrow_downward' : 'arrow_upward';

            fwData[column].reverse();
            renderFwTable();
        }

        function renderFwTable() {
            const tbody = document.getElementById('fwModalBody');
            const countEl = document.getElementById('fwSearchCount');
            tbody.innerHTML = '';
            
            const maxRows = Math.max(fwData.branch.length, fwData.trunk.length);
            
            if (maxRows === 0) {
                tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; font-style:italic; color:var(--text-muted); padding: 40px;">No firmware history found.</td></tr>';
                countEl.style.display = 'none';
                return;
            }

            // Build row indices, apply search filter
            let indices = [];
            for (let i = 0; i < maxRows; i++) {
                indices.push(i);
            }

            if (fwSearchTerm.length > 0) {
                indices = indices.filter(i => {
                    const bVal = (fwData.branch[i] || '').toLowerCase();
                    const tVal = (fwData.trunk[i] || '').toLowerCase();
                    return bVal.includes(fwSearchTerm) || tVal.includes(fwSearchTerm);
                });
            }

            // Show result count when searching
            if (fwSearchTerm.length > 0) {
                const total = maxRows;
                const shown = indices.length;
                countEl.textContent = shown === total 
                    ? `Showing all ${total} versions` 
                    : `Showing ${shown} of ${total} versions`;
                countEl.style.display = 'block';
                
                if (shown === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="2" style="text-align:center; padding: 40px;">
                                <span class="material-symbols-outlined" style="font-size:32px; color:var(--border); display:block; margin-bottom:8px;">search_off</span>
                                <span style="color:var(--text-muted); font-size:0.85rem;">No versions matching "<strong style="color:var(--text-main);">${escapeHtml(fwSearchTerm)}</strong>"</span>
                            </td>
                        </tr>`;
                    return;
                }
            } else {
                countEl.style.display = 'none';
            }

            // Highlight helper
            function highlight(text, term) {
                if (!term) return escapeHtml(text);
                const escaped = escapeHtml(text);
                const escapedTerm = escapeHtml(term);
                const regex = new RegExp(`(${escapedTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                return escaped.replace(regex, '<mark style="background:rgba(2,136,209,0.15); color:var(--primary); padding:1px 2px; border-radius:3px;">$1</mark>');
            }

            for (const i of indices) {
                const bVal = fwData.branch[i] || '-';
                const tVal = fwData.trunk[i] || '-';
                
                const bIsEmpty = bVal === '-';
                const tIsEmpty = tVal === '-';
                
                const bStyle = bIsEmpty ? 'color:var(--border);' : 'font-family:var(--font-mono); font-weight:600; color:var(--text-main);';
                const tStyle = tIsEmpty ? 'color:var(--border);' : 'font-family:var(--font-mono); font-weight:600; color:var(--text-main);';

                const bDisplay = bIsEmpty ? '-' : highlight(bVal, fwSearchTerm);
                const tDisplay = tIsEmpty ? '-' : highlight(tVal, fwSearchTerm);
                
                tbody.innerHTML += `
                    <tr>
                        <td style="${bStyle}">${bDisplay}</td>
                        <td style="${tStyle}">${tDisplay}</td>
                    </tr>
                `;
            }

            // Scroll back to top on re-render
            document.getElementById('fwTableScroll').scrollTop = 0;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ── Custom Analytical Chart Logic REMOVED ──────────────────────
        // Chart logic has been removed as it is not needed for the Leader dashboard.

        // ── Attach Tooltips ──────────────────────────────
        const tooltip = document.getElementById('custom-tooltip');

        function attachTooltips() {
            document.querySelectorAll('[data-tip]').forEach(el => {
                el.addEventListener('mouseenter', (e) => {
                    tooltip.textContent = el.dataset.tip;
                    tooltip.classList.add('visible');
                });
                el.addEventListener('mousemove', (e) => {
                    let leftPos = e.clientX + 14;
                    let topPos = e.clientY - 32;

                    // Check if tooltip goes out of bounds on the right
                    if (leftPos + tooltip.offsetWidth > window.innerWidth) {
                        // Flip to the left side of the cursor
                        leftPos = e.clientX - tooltip.offsetWidth - 14;
                    }

                    tooltip.style.left = leftPos + 'px';
                    tooltip.style.top = topPos + 'px';
                });
                el.addEventListener('mouseleave', () => tooltip.classList.remove('visible'));
            });
        }
        attachTooltips();

    </script>
</body>
</html>