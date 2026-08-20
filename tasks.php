<?php
require_once 'controllers/AssignmentsController.php';
 $TITLE = "All Tasks | Track Manager";
require_once 'configs/header.php';
?>
<style>
    /* --- Page Header & Modern Create Button --- */
    .page-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-title {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-create-task {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 20px;
        height: 42px;
        border-radius: 8px;
        background: var(--primary);
        color: white;
        font-size: 0.9rem;
        font-weight: 700;
        text-decoration: none;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(2, 136, 209, 0.25);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-create-task:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(2, 136, 209, 0.35);
        color: white;
        background: var(--primary-hover);
    }

    .btn-create-task .material-symbols-outlined {
        font-size: 20px;
    }

    /* --- Unified SaaS Card --- */
    .unified-card {
        background: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: var(--border-radius);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        display: flex;
        flex-direction: column;
        margin-bottom: 30px;
    }

    /* --- Table Control Bar --- */
    .table-controls {
        padding: 8px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 12px;
        background: var(--bg-surface);
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .btn-control {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 38px;
        padding: 0 14px;
        background: var(--bg-body);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text-main);
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-control:hover {
        background: var(--border);
        color: var(--text-main);
    }

    .btn-control.ghost {
        background: transparent;
        color: var(--text-muted);
        border-color: transparent;
    }

    .btn-control.ghost:hover {
        background: var(--error-bg);
        color: var(--error);
    }

    .btn-control .material-symbols-outlined {
        font-size: 18px;
    }

    /* --- Right Filter Drawer UI --- */
    .drawer-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        z-index: 9998;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .drawer-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .filter-drawer {
        position: fixed;
        top: 0;
        right: -400px;
        width: 100%;
        max-width: 360px;
        height: 100vh;
        background: var(--bg-surface);
        z-index: 9999;
        box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-left: 1px solid var(--border);
    }

    .filter-drawer.open {
        right: 0;
    }

    .drawer-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .drawer-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .drawer-header h3 .material-symbols-outlined {
        color: var(--primary);
    }

    .drawer-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 24px;
    }

    .filter-group label {
        font-size: 0.68rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 8px;
        letter-spacing: 0.05em;
    }

    /* Date Inputs inside Drawer */
    .date-flex {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .date-flex input {
        flex: 1;
        height: var(--input-height);
        padding: 0 12px;
        width: 100%;
        border: 1px solid var(--border);
        border-radius: var(--border-radius);
        background: var(--bg-body);
        color: var(--text-main);
        font-size: 0.85rem;
        outline: none;
        transition: all 0.2s;
    }

    .date-flex input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.15);
    }

    /* Dark Mode Calendar Icon Fix */
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
    [data-theme="midnight"] input[type="date"]::-webkit-calendar-picker-indicator,
    [data-theme="catppuccin"] input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(0.8);
        cursor: pointer;
    }

    /* --- OVERRIDE: Wrap Chips & Kill Horizontal Scroll in Drawer --- */
    .drawer-body .enh-trigger {
        height: auto !important;
        min-height: var(--input-height) !important;
        padding: 6px 14px !important;
    }

    .drawer-body .enh-trigger-content {
        flex-wrap: wrap !important;
        overflow-x: visible !important;
        margin: 4px 0 !important;
    }

    .drawer-body .enh-chip {
        white-space: normal !important;
        height: auto;
        line-height: 1.3;
    }

    /* --- FIX: Prevent Filter Drawer Dropdowns from being cut off --- */
    .filter-drawer .enh-menu {
        max-height: 150px !important;
    }

    /* --- Table Scroll Section - FIXED DROPDOWN VISIBILITY --- */
    .table-section {
        overflow-x: auto;
        overflow-y: visible !important;
        width: 100%;
        border-radius: 0 0 calc(var(--border-radius) - 1px) calc(var(--border-radius) - 1px);
        position: relative;
    }

    .table-section::-webkit-scrollbar {
        height: 8px;
    }

    .table-section::-webkit-scrollbar-track {
        background: var(--bg-body);
    }

    .table-section::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 4px;
    }

    .table-section::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    .d-table {
        width: 100%;
        min-width: 1150px;
        border-collapse: collapse;
    }

    .d-table th,
    .d-table td {
        padding: 12px 16px;
        color: var(--text-main);
        vertical-align: middle;
    }

    .d-table th, .d-table td {
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
    }
    
    .d-table tbody tr {
        height: auto;
        min-height: 40px;
    }

    .d-table tbody tr td {
        color: var(--text-main) !important;
        font-weight: 500;
    }

    .d-table tbody tr td .mono {
        color: var(--text-main) !important;
        font-weight: 700;
    }

    .d-table tbody tr td:first-child .mono {
        color: #1a1a2e !important;
        font-weight: 700;
        font-size: 0.85rem;
    }

    [data-theme="dark"] .d-table tbody tr td:first-child .mono {
        color: #e2e8f0 !important;
    }

    .text-muted {
        color: #4b5563 !important;
    }

    [data-theme="dark"] .text-muted {
        color: #9ca3af !important;
    }

    /* Sort Headers */
    .d-table th {
        cursor: pointer;
        user-select: none;
        white-space: nowrap !important;
    }

    /* Enhanced Dropdown Styles */
    .enh-dropdown-container {
        position: relative;
        z-index: 9999;
        overflow: visible !important;
    }

    .enh-menu {
        position: fixed !important;
        z-index: 99999 !important;
        max-height: 250px;
        overflow-y: auto;
        background: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: var(--border-radius);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        min-width: 200px;
    }

    .enh-trigger {
        position: relative;
        z-index: 1;
        overflow: visible !important;
    }

    .table-section .enh-menu {
        position: fixed !important;
        z-index: 99999 !important;
    }

    .action-icons {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        padding-right: 14px;
        align-items: center;
    }

    .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        transition: all 0.2s ease;
        text-decoration: none;
        color: var(--text-muted);
        background: transparent;
        border: none;
        cursor: pointer;
    }

    .icon-btn:hover {
        background: var(--bg-body);
        color: var(--primary);
    }

    .icon-btn.delete:hover {
        color: var(--error);
        background: var(--error-bg);
    }

    .icon-btn .material-symbols-outlined {
        font-size: 18px;
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        border-top: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 10px;
    }

    .pagination-controls select.per-page-select {
        background: #374151 !important;
        color: #ffffff !important;
        border: 1px solid #4b5563 !important;
        border-radius: 6px;
        padding: 4px 28px 4px 10px;
        width: auto;
        font-size: 0.8rem;
        min-height: 30px;
        cursor: pointer;
        outline: none;
        appearance: auto;
        transition: border-color 0.2s;
        font-weight: 600;
    }

    .pagination-controls select.per-page-select:hover {
        border-color: var(--primary) !important;
    }

    .pagination-controls select.per-page-select:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
    }

    .pagination-controls select.per-page-select option {
        background: #374151 !important;
        color: #ffffff !important;
        padding: 6px 12px;
        font-weight: 600;
    }

    .pagination-controls select.per-page-select option:hover,
    .pagination-controls select.per-page-select option:checked {
        background: var(--primary) !important;
        color: #ffffff !important;
    }

    [data-theme="light"] .pagination-controls select.per-page-select {
        background: #f3f4f6 !important;
        color: #1a1a2e !important;
        border-color: #d1d5db !important;
    }

    [data-theme="light"] .pagination-controls select.per-page-select option {
        background: #f3f4f6 !important;
        color: #1a1a2e !important;
    }

    [data-theme="light"] .pagination-controls select.per-page-select option:hover,
    [data-theme="light"] .pagination-controls select.per-page-select option:checked {
        background: var(--primary) !important;
        color: #ffffff !important;
    }

    .pagination-controls .pagination-links {
        display: flex;
        gap: 4px;
        align-items: center;
        flex-wrap: wrap;
    }

    .pagination-controls .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg-surface);
        color: var(--text-main);
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .pagination-controls .page-link:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
    }

    .pagination-controls .page-link.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .pagination-controls .page-link.disabled {
        opacity: 0.4;
        pointer-events: none;
    }

    .pagination-controls .pagination-info {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .printer-chip-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
    }
    
    .printer-chip-small {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px 2px 4px;
        border-radius: 12px;
        background: var(--bg-body);
        border: 1px solid var(--border);
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-main);
        white-space: nowrap;
    }
    .printer-chip-small .chip-icon {
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
    }
    .printer-chip-small .chip-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .printer-chip-small .chip-icon .material-symbols-outlined {
        font-size: 12px;
        color: var(--primary);
    }

    /* ---------- TYPE BADGE REFINEMENTS ---------- */
    .badge-smoke-type {
        background: rgba(245, 158, 11, 0.15);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.35);
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }
    .badge-smoke-type .material-symbols-outlined {
        font-size: 12px;
    }
    
    .badge-regression-type {
        background: rgba(139, 92, 246, 0.15);
        color: #7c3aed;
        border: 1px solid rgba(139, 92, 246, 0.35);
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }
    .badge-regression-type .material-symbols-outlined {
        font-size: 12px;
    }
    
    [data-theme="dark"] .badge-smoke-type {
        background: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
    }
    [data-theme="dark"] .badge-regression-type {
        background: rgba(139, 92, 246, 0.2);
        color: #a78bfa;
    }

    .assigned-testers-container {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .assigned-testers-container .tester-line {
        color: #4b5563 !important;
        font-weight: 600;
        font-size: 0.85rem;
    }
    [data-theme="dark"] .assigned-testers-container .tester-line {
        color: #d1d5db !important;
    }
    
    .assigned-all {
        color: #4b5563 !important;
        font-weight: 600;
        font-size: 0.85rem;
    }
    [data-theme="dark"] .assigned-all {
        color: #d1d5db !important;
    }

    .enh-menu {
        max-height: 250px;
    }

    .modal-filter-reset-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
        margin-left: auto;
        margin-right: 4px;
    }

    .modal-filter-reset-btn:hover {
        color: var(--primary);
    }

    .modal-filter-reset-btn .material-symbols-outlined {
        font-size: 20px;
    }

    .modal-close-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
    }

    .modal-close-btn:hover {
        background: var(--bg-body);
        color: var(--text-main);
    }

    .modal-close-btn .material-symbols-outlined {
        font-size: 20px;
    }

    .form-control {
        width: 100%;
        border: solid 1.5px var(--border);
        border-radius: 8px;
        background: #374151;
        padding: 1rem;
        font-size: 0.95rem;
        color: var(--text-main);
        transition: border 150ms cubic-bezier(0.4, 0, 0.2, 1);
        box-sizing: border-box;
    }

    [data-theme="light"] .form-control {
        background: #f3f4f6;
        color: #1a1a2e;
    }

    /* --- IN PROGRESS Badge --- */
    .badge-in-progress {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .badge-in-progress .material-symbols-outlined {
        font-size: 13px;
    }

    /* --- COMPLETED Badge --- */
    .badge-completed {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .badge-completed .material-symbols-outlined {
        font-size: 13px;
    }
</style>
<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">

        <?php if ($_SESSION['role'] !== 'admin'): ?>
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">task</span>
                Task Masterlist
            </h1>
            <a href="create_task.php" class="btn-create-task">
                <span class="material-symbols-outlined">add</span> Create Task
            </a>
        </div>

        <div class="unified-card">

            <div class="table-controls">
                <button type="button" class="btn-control ghost" onclick="confirmReset()">
                    <span class="material-symbols-outlined">restart_alt</span> Reset
                </button>
                <button type="button" class="btn-control" onclick="toggleFilterDrawer()">
                    <span class="material-symbols-outlined">tune</span> Filters
                </button>
            </div>

            <div id="tasks-container">
                <?php if (empty($lead_tasks)): ?>
                    <div class="empty-state" style="border:none; border-radius:0;">
                        <span class="material-symbols-outlined">inbox_customize</span>
                        <p>No tasks found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-section">
                        <table class="d-table">
                            <thead>
                                <tr>
                                    <?= Helper::renderSortHeader('task_date', 'Date', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('testing_type', 'Testing', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('model_name', 'Printer', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('fw_version_current', 'Version', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('fw_type', 'Firmware', $sort, $order) ?>
                                    <th>Assigned Testers</th>
                                    <?= Helper::renderSortHeader('overall_status', 'Status', $sort, $order) ?>
                                    <th style="text-align: right; padding-right: 24px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lead_tasks as $task): 
                                    // Determine if the task is locked (status is NOT Pending or In Progress)
                                    $isLocked = !(empty($task['overall_status']) || $task['overall_status'] === 'Pending' || $task['overall_status'] === 'In Progress');
                                ?>
                                    <tr class="main-row">
                                        <td>
                                            <span class="date-cell">
                                                <?= date('M d, Y', strtotime($task['task_date'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($task['testing_type'] == 'Smoke'): ?>
                                                <span class="badge-smoke-type">
                                                    <span class="material-symbols-outlined">local_fire_department</span> Smoke
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-regression-type">
                                                    <span class="material-symbols-outlined">checklist</span> Regression
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // Split printer names and paths for both Smoke and Regression
                                            // This handles comma-separated values from the GROUP BY fix
                                            $printerNames = explode(',', $task['model_name']);
                                            $printerPaths = explode(',', $task['printer_path'] ?? '');
                                            ?>
                                            <div class="printer-chip-wrap">
                                                <?php for ($i = 0; $i < count($printerNames); $i++): 
                                                    $name = trim($printerNames[$i]);
                                                    $path = isset($printerPaths[$i]) ? trim($printerPaths[$i]) : '';
                                                ?>
                                                    <span class="printer-chip-small">
                                                        <span class="chip-icon">
                                                            <?= Helper::renderPrinterImage($path ?: null, $name, 16) ?>
                                                        </span>
                                                        <?= htmlspecialchars($name) ?>
                                                    </span>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-current"><?= htmlspecialchars($task['fw_version_current']) ?></span>
                                        </td>
                                        <td>
                                            <span class="branch-name"><?= htmlspecialchars($task['fw_type']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($task['testing_type'] == 'Regression'): ?>
                                                <span class="assigned-all">All</span>
                                            <?php else: 
                                                $testerNames = explode(',', $task['assigned_to_names'] ?? '');
                                            ?>
                                                <div class="assigned-testers-container">
                                                    <?php foreach ($testerNames as $name): 
                                                        $trimmedName = trim($name);
                                                        if (!empty($trimmedName)):
                                                    ?>
                                                        <span class="tester-line"><?= htmlspecialchars($trimmedName) ?></span>
                                                    <?php endif; endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($task['overall_status'] == 'Pass'): ?>
                                                <span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                            <?php elseif ($task['overall_status'] == 'Fail'): ?>
                                                <span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                            <?php elseif ($task['overall_status'] == 'Blocked'): ?>
                                                <span class="badge" style="background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked);"><span class="material-symbols-outlined">block</span> BLOCKED</span>
                                            <?php elseif ($task['overall_status'] == 'N/A'): ?>
                                                <span class="badge" style="background: var(--na-bg); color: var(--na); border: 1px solid var(--na);"><span class="material-symbols-outlined">do_not_disturb_on</span> N/A</span>
                                            <?php elseif ($task['overall_status'] == 'Completed'): ?>
                                                <span class="badge-completed"><span class="material-symbols-outlined">check_circle</span> COMPLETED</span>
                                            <?php else: ?>
                                                <span class="badge-in-progress"><span class="material-symbols-outlined" style="font-size: 13px;">progress_activity</span> IN PROGRESS
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-icons">
                                                <?php 
                                                // Fetch all printer IDs for this task from the database
                                                $stmtPrinterIds = $pdo->prepare("SELECT GROUP_CONCAT(DISTINCT printer_id SEPARATOR ',') FROM task_assignments WHERE task_id = ?");
                                                $stmtPrinterIds->execute([$task['task_id']]);
                                                $allPrinterIds = $stmtPrinterIds->fetchColumn();

                                                // Build URLs
                                                $viewUrl = 'view_task.php?id=' . $task['task_id'];
                                                $editUrl = 'edit_task.php?id=' . $task['task_id'];
                                                if ($allPrinterIds) {
                                                    $viewUrl .= '&printer_ids=' . $allPrinterIds;
                                                    $editUrl .= '&printer_ids=' . $allPrinterIds;
                                                }
                                                ?>

                                                <?php if ($isLocked): ?>
                                                    <!-- LOCKED STATE: ONLY SHOW THE VIEW (EYE) ICON -->
                                                    <a href="<?= $viewUrl ?>" class="icon-btn" title="View Task">
                                                        <span class="material-symbols-outlined">visibility</span>
                                                    </a>
                                                <?php else: ?>
                                                    <!-- ACTIVE STATE: SHOW EDIT BUTTON -->
                                                    <a href="<?= $editUrl ?>" class="icon-btn">
                                                        <span class="material-symbols-outlined">edit</span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= Helper::renderPagination($lead_totalRows, $perPage, $page, [25, 50, 75, 100]) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="drawer-overlay" id="filterOverlay" onclick="toggleFilterDrawer()"></div>
        <div class="filter-drawer" id="filterDrawer">
            <div class="drawer-header">
                <h3><span class="material-symbols-outlined">tune</span> Filters</h3>
                <button type="button" class="modal-filter-reset-btn" onclick="confirmReset()">
                    <span class="material-symbols-outlined"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M2.39 1.73L1.11 3l8.39 8.37l.47.63H10v5.87c-.04.29.06.6.29.83l2.01 2.01c.39.39 1.02.39 1.41 0c.23-.21.33-.53.29-.83v-3.99l6.84 6.84l1.27-1.27L14 13.35L9.41 8.76L4.15 3.5zM6.21 3l8.33 8.34l5.25-6.72a1 1 0 0 0-.17-1.4c-.19-.14-.4-.22-.62-.22z" />
                        </svg></span>
                </button>
                <button type="button" class="modal-close-btn" onclick="toggleFilterDrawer()" title="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="drawer-body">
                <form method="get" class="no-loader" id="ajax-filter-form">
                    <input type="hidden" name="sort" id="sort_input" value="<?= htmlspecialchars($sort) ?>">
                    <input type="hidden" name="order" id="order_input" value="<?= htmlspecialchars($order) ?>">
                    <input type="hidden" name="page" id="page_input" value="<?= htmlspecialchars($page) ?>">
                    <input type="hidden" name="per_page" id="per_page_input" value="<?= htmlspecialchars($perPage) ?>">

                    <div class="filter-group">
                        <label>Date Range</label>
                        <div class="date-flex">
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" title="Start Date">
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" title="End Date">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>Workflow Type</label>
                        <?= Helper::enhancedDropdown([
                            'name' => 'type',
                            'placeholder' => 'All Types',
                            'multiple' => false,
                            'options' => ['Smoke' => 'Smoke', 'Regression' => 'Regression'],
                            'selected' => $type
                        ]) ?>
                    </div>

                    <div class="filter-group">
                        <label>Printers</label>
                        <?= Helper::enhancedDropdown([
                            'name' => 'printers[]',
                            'placeholder' => 'Any Printer...',
                            'multiple' => true,
                            'options' => $printerOpts,
                            'selected' => $printers
                        ]) ?>
                    </div>

                    <div class="filter-group">
                        <label>Assignees</label>
                        <?= Helper::enhancedDropdown([
                            'name' => 'assignees[]',
                            'placeholder' => 'Any Tester...',
                            'multiple' => true,
                            'options' => $userOpts,
                            'selected' => $assignees
                        ]) ?>
                    </div>

                    <div class="filter-group">
                        <label>Status</label>
                        <?= Helper::enhancedDropdown([
                            'name' => 'statuses[]',
                            'placeholder' => 'Any Status...',
                            'multiple' => true,
                            'options' => $statusOpts,
                            'selected' => $statuses
                        ]) ?>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
            <div class="unified-card" style="margin-top: 20px;">
                <div class="empty-state" style="border:none; border-radius:12px;">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    <p>Task execution views are not available for Admins.</p>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($_SESSION['role'] !== 'admin'): ?>
<script>
    // --- Drawer Logic ---
    function toggleFilterDrawer() {
        document.getElementById('filterDrawer').classList.toggle('open');
        const overlay = document.getElementById('filterOverlay');
        overlay.classList.toggle('show');
        if (overlay.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    function confirmReset() {
        if (confirm("Are you sure you want to clear all filters?")) {
            window.location.href = window.location.pathname;
        }
    }

    // --- Auto AJAX Logic ---
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('ajax-filter-form');
        const container = document.getElementById('tasks-container');

        function loadData(url) {
            window.showLoader();
            fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('tasks-container');
                    if (newContent) container.innerHTML = newContent.innerHTML;
                    window.history.pushState({}, '', url);
                    window.hideLoader();
                })
                .catch(() => window.hideLoader());
        }

        form.addEventListener('change', (e) => {
            if (!e.target.classList.contains('per-page-select')) {
                document.getElementById('page_input').value = '1';
            }
            const url = new URL(window.location.pathname, window.location.origin);
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (value) url.searchParams.append(key, value);
            }
            loadData(url);
        });

        document.addEventListener('click', (e) => {
            const link = e.target.closest('.page-link');
            if (link && link.tagName === 'A') {
                e.preventDefault();
                loadData(link.href);
            }
        });

        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('per-page-select')) {
                document.getElementById('per_page_input').value = e.target.value;
                document.getElementById('page_input').value = '1';
                form.dispatchEvent(new Event('change'));
            }
        });
    });

    window.updateSort = function(column, order) {
        document.getElementById('sort_input').value = column;
        document.getElementById('order_input').value = order;
        document.getElementById('page_input').value = '1';
        document.getElementById('ajax-filter-form').dispatchEvent(new Event('change'));
    };
</script>
<?php endif; ?>
</body>

</html>