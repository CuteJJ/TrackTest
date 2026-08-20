<?php
require_once 'controllers/AssignmentsController.php';
 $TITLE = "My Assignments | Track Manager";
require_once 'configs/header.php';
?>
<style>
    /* --- Page Header --- */
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
        gap: 24px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 0;
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
        align-items: flex-start;
        flex-direction: column;
    }

    .date-inputs-row {
        display: flex;
        gap: 8px;
        width: 100%;
    }

    .date-flex input[type="date"] {
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

    .date-flex input[type="date"]:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.15);
    }

    /* Date Error States */
    .date-flex input[type="date"].date-error {
        border-color: var(--error) !important;
        background: var(--error-bg);
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    .date-flex input[type="date"].date-error:focus {
        border-color: var(--error) !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
    }

    .date-error-msg {
        display: flex;
        align-items: center;
        gap: 5px;
        color: var(--error);
        font-size: 0.75rem;
        font-weight: 500;
        margin-top: 8px;
        padding: 8px 10px;
        background: var(--error-bg);
        border-radius: 6px;
        border: 1px solid rgba(239, 68, 68, 0.2);
        animation: dateErrorSlide 0.25s ease-out;
    }

    .date-error-msg .material-symbols-outlined {
        font-size: 16px;
        flex-shrink: 0;
    }

    @keyframes dateErrorSlide {
        from { opacity: 0; transform: translateY(-6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Date Valid State */
    .date-flex input[type="date"].date-valid {
        border-color: #22c55e;
    }

    .date-valid-msg {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #22c55e;
        font-size: 0.75rem;
        font-weight: 500;
        margin-top: 8px;
        padding: 8px 10px;
        background: rgba(34, 197, 94, 0.08);
        border-radius: 6px;
        border: 1px solid rgba(34, 197, 94, 0.2);
        animation: dateErrorSlide 0.25s ease-out;
    }

    .date-valid-msg .material-symbols-outlined {
        font-size: 16px;
        flex-shrink: 0;
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

    /* --- Table Scroll Section --- */
    .table-section {
        overflow-x: auto;
        width: 100%;
        border-radius: 0 0 calc(var(--border-radius) - 1px) calc(var(--border-radius) - 1px);
    }

    .table-section::-webkit-scrollbar { height: 8px; }
    .table-section::-webkit-scrollbar-track { background: var(--bg-body); }
    .table-section::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    .table-section::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

    .d-table {
        width: 100%;
        min-width: 1050px;
        border-collapse: collapse;
    }

    .d-table th,
    .d-table td {
        white-space: nowrap !important;
    }

    .enh-menu { max-height: 250px; }

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

    .modal-filter-reset-btn:hover { color: var(--primary); }
    .modal-filter-reset-btn .material-symbols-outlined { font-size: 20px; }
    
    /* --- Locked Disabled Button --- */
    .btn-disabled {
        background: var(--bg-body);
        color: var(--text-muted);
        border: 1px solid var(--border);
        cursor: not-allowed;
        opacity: 0.7;
    }

    .btn-disabled:hover {
        background: none;
        border-color: var(--border);
    }

    /* --- Per Page Dropdown Fix (Duplicate Arrow Fixed) --- */
    .per-page-select {
        height: var(--input-height);
        padding: 0 28px 0 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background-color: var(--bg-body);
        color: var(--text-main) !important;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        outline: none;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        transition: all 0.2s;
    }

    .per-page-select:hover {
        border-color: var(--primary);
    }

    .per-page-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.15);
    }

    /* Fix dropdown options text color */
    .per-page-select option {
        background-color: var(--bg-surface);
        color: var(--text-main) !important;
        padding: 8px 10px;
    }

    /* Dark theme dropdown option fix */
    [data-theme="dark"] .per-page-select option,
    [data-theme="midnight"] .per-page-select option,
    [data-theme="catppuccin"] .per-page-select option {
        background-color: #1e293b;
        color: #e2e8f0 !important;
    }

</style>
<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">

        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">assignment</span>
                My Assignments
            </h1>
        </div>

        <div class="unified-card">

            <div class="table-controls">
                <button type="button" class="btn-control ghost" onclick="resetToAssignments()" title="Clear all filters">
                    <span class="material-symbols-outlined">restart_alt</span> Reset
                </button>
                <button type="button" class="btn-control" onclick="toggleFilterDrawer()">
                    <span class="material-symbols-outlined">tune</span> Filters
                </button>
            </div>

            <div id="tasks-container">
                <?php if (empty($my_tasks)): ?>
                    <div class="empty-state" style="border:none; border-radius:0;">
                        <span class="material-symbols-outlined">inbox_customize</span>
                        <p>No assignments found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-section">
                        <table class="d-table">
                            <thead>
                                <tr>
                                    <?= Helper::renderSortHeader('task_date', 'Date', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('testing_type', 'Type', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('model_name', 'Printer', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('fw_version_current', 'Version', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('fw_type', 'Firmware', $sort, $order) ?>
                                    <th>My Role</th>
                                    <?= Helper::renderSortHeader('overall_status', 'Status', $sort, $order) ?>
                                    <th style="text-align: right; padding-right: 24px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_tasks as $task): ?>
                                    <tr class="main-row">
                                        <td>
                                            <span class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                                <?= date('M d, Y', strtotime($task['task_date'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $task['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>">
                                                <?= htmlspecialchars($task['testing_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border-radius: 50%; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border);">
                                                    <?= Helper::renderPrinterImage($task['printer_path'] ?? null, htmlspecialchars($task['model_name']), 16) ?>
                                                </div>
                                                <strong style="font-size:0.88rem;"><?= htmlspecialchars($task['model_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="mono" style="font-size:0.85rem; color:var(--primary); font-weight:600;">
                                                <?= htmlspecialchars($task['fw_version_current']) ?>
                                            </span>
                                        </td>
                                        <td style="font-size:0.85rem; color:var(--text-muted);">
                                            <?= htmlspecialchars($task['fw_type']) ?>
                                        </td>
                                        <td style="font-size:0.85rem; color:var(--text-muted); font-weight:500;">
                                            <?= htmlspecialchars($task['testing_type'] == 'Regression' ? 'ALL' : $task['designation']) ?>
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
                                                <!-- MATCHED "In Progress" styling but Green -->
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.3); display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; white-space: nowrap;">
                                                    <span class="material-symbols-outlined" style="font-size: 13px;">check_circle</span> COMPLETED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);">
                                                    <span class="material-symbols-outlined" style="font-size: 12px;">progress_activity</span> In Progress
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right; padding-right: 24px;">
                                            <?php 
                                            // --- REFINED LOGIC: LOCK for ALL finalized statuses ---
                                            $finalizedStatuses = ['Pass', 'Fail', 'Blocked', 'N/A', 'Completed'];
                                            $isLocked = in_array(trim($task['overall_status'] ?? ''), $finalizedStatuses);
                                            ?>
                                            
                                            <?php if ($isLocked): ?>
                                                <!-- LOCKED STATE (Pass, Fail, Blocked, N/A, Completed) -->
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
                                                <!-- EXECUTABLE STATE (Only for Pending or In Progress) -->
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
                    <?= Helper::renderPagination($my_totalRows, $perPage, $page, [25, 50, 75, 100]) ?>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<div class="drawer-overlay" id="filterOverlay" onclick="toggleFilterDrawer()"></div>
<div class="filter-drawer" id="filterDrawer">
    <div class="drawer-header">
        <h3><span class="material-symbols-outlined">tune</span> Filters</h3>
        <button type="button" class="modal-filter-reset-btn" onclick="clearFiltersOnly()" title="Clear all filters">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><defs><mask id="SVG7xZIMtwn"><g fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path stroke="#fff" stroke-dasharray="54" d="M5 4h14l-5 6.5v9.5l-4 -4v-5.5Z"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.39s" values="54;0"/></path><path stroke="#000" stroke-dasharray="28" stroke-dashoffset="28" d="M-1 11h26" transform="rotate(45 12 12)"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.455s" dur="0.26s" to="0"/></path></g></mask></defs><path fill="currentColor" d="M0 0h24v24H0z" mask="url(#SVG7xZIMtwn)"/><path fill="none" stroke="currentColor" stroke-dasharray="28" stroke-dashoffset="28" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M-1 13h26" transform="rotate(45 12 12)"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.455s" dur="0.26s" to="0"/></path></svg>
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

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Date Range</label>
                <div class="date-flex" id="dateRangeGroup">
                    <div class="date-inputs-row">
                        <input type="date" name="start_date" id="startDate" value="<?= htmlspecialchars($start_date) ?>" title="Start Date">
                        <input type="date" name="end_date" id="endDate" value="<?= htmlspecialchars($end_date) ?>" title="End Date">
                    </div>
                    <div id="dateMessage"></div>
                </div>
            </div>

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Workflow Type</label>
                <?= Helper::enhancedDropdown([
                    'name' => 'type',
                    'placeholder' => 'All Types',
                    'multiple' => false,
                    'options' => ['Smoke' => 'Smoke', 'Regression' => 'Regression'],
                    'selected' => $type
                ]) ?>
            </div>

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Printers</label>
                <?= Helper::enhancedDropdown([
                    'name' => 'printers[]',
                    'placeholder' => 'Any Printer...',
                    'multiple' => true,
                    'options' => $printerOpts,
                    'selected' => $printers
                ]) ?>
            </div>

            <div class="filter-group" style="margin-bottom: 24px;">
                <label>Status</label>
                <?= Helper::enhancedDropdown([
                    'name' => 'statuses[]',
                    'placeholder' => 'Any Status...',
                    'multiple' => true,
                    'options' => [
                        'Pass' => 'Passed',
                        'Fail' => 'Failed',
                        'Blocked' => 'Blocked',
                        'N/A' => 'N/A',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed'
                    ],
                    'selected' => $statuses
                ]) ?>
            </div>
        </form>
    </div>
</div>

<script>
    // --- Date Validation ---
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const dateMessageContainer = document.getElementById('dateMessage');
    let dateValidationTimeout = null;

    function clearDateMessage() {
        dateMessageContainer.innerHTML = '';
        startDateInput.classList.remove('date-error', 'date-valid');
        endDateInput.classList.remove('date-error', 'date-valid');
    }

    function showDateError(message) {
        clearDateMessage();
        startDateInput.classList.add('date-error');
        endDateInput.classList.add('date-error');
        dateMessageContainer.innerHTML = `
            <div class="date-error-msg">
                <span class="material-symbols-outlined">error</span>
                <span>${message}</span>
            </div>
        `;
    }

    function showDateValid(message) {
        clearDateMessage();
        startDateInput.classList.add('date-valid');
        endDateInput.classList.add('date-valid');
        dateMessageContainer.innerHTML = `
            <div class="date-valid-msg">
                <span class="material-symbols-outlined">check_circle</span>
                <span>${message}</span>
            </div>
        `;
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function calculateDays(start, end) {
        const startDate = new Date(start + 'T00:00:00');
        const endDate = new Date(end + 'T00:00:00');
        const diffTime = endDate - startDate;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    }

    function validateDateRange(showMessages = true) {
        const startVal = startDateInput.value;
        const endVal = endDateInput.value;

        if (!startVal && !endVal) { clearDateMessage(); return true; }
        if ((startVal && !endVal) || (!startVal && endVal)) { if (showMessages) clearDateMessage(); return true; }

        const startDate = new Date(startVal + 'T00:00:00');
        const endDate = new Date(endVal + 'T00:00:00');

        if (endDate < startDate) {
            if (showMessages) showDateError(`End date (${formatDate(endVal)}) cannot be before start date (${formatDate(startVal)})`);
            return false;
        }
        if (endDate.getTime() === startDate.getTime()) {
            if (showMessages) showDateValid(`Showing results for ${formatDate(startVal)}`);
            return true;
        }
        if (showMessages) {
            const days = calculateDays(startVal, endVal);
            showDateValid(`${formatDate(startVal)} → ${formatDate(endVal)} (${days} days)`);
        }
        return true;
    }

    function debouncedValidation(showMessages) {
        clearTimeout(dateValidationTimeout);
        dateValidationTimeout = setTimeout(() => validateDateRange(showMessages), 150);
    }

    startDateInput.addEventListener('change', function() {
        if (this.value && endDateInput.value && new Date(endDateInput.value + 'T00:00:00') < new Date(this.value + 'T00:00:00')) {
            endDateInput.value = '';
            showDateError(`End date was cleared because it was before ${formatDate(this.value)}`);
            return;
        }
        debouncedValidation(true);
    });
    endDateInput.addEventListener('change', () => debouncedValidation(true));
    startDateInput.addEventListener('input', () => debouncedValidation(true));
    endDateInput.addEventListener('input', () => debouncedValidation(true));

    // --- Drawer Logic ---
    function toggleFilterDrawer() {
        document.getElementById('filterDrawer').classList.toggle('open');
        const overlay = document.getElementById('filterOverlay');
        overlay.classList.toggle('show');
        if (overlay.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
            setTimeout(() => validateDateRange(false), 100);
        } else {
            document.body.style.overflow = '';
        }
    }

    // --- 1. TABLE RESET: Reloads page, drawer stays CLOSED ---
    function resetToAssignments() {
        const path = window.location.pathname;
        window.location.href = path.substring(path.lastIndexOf('/') + 1);
    }

    // --- 2. DRAWER CLEAR: Uses AJAX, drawer stays OPEN without reopening ---
    const dropdownInitialHTML = new Map();

    function clearFiltersOnly() {
        const form = document.getElementById('ajax-filter-form');
        const keepNames = ['sort', 'order', 'page', 'per_page'];

        form.querySelectorAll('input[type="date"]').forEach(input => input.value = '');
        clearDateMessage();

        form.querySelectorAll('.enh-dropdown').forEach(dropdown => {
            const trigger = dropdown.querySelector('.enh-trigger');
            const triggerContent = trigger?.querySelector('.enh-trigger-content');
            if (!triggerContent) return;

            // Restore exact initial HTML if we have it
            const savedHTML = dropdownInitialHTML.get(dropdown);
            if (savedHTML && savedHTML.includes('<') && savedHTML.replace(/<[^>]*>/g, '').trim().length > 0) {
                triggerContent.innerHTML = savedHTML;
            } else {
                // Fallback: Rebuild manually using data attributes (failsafe for correct text)
                triggerContent.innerHTML = '';
                let text = trigger?.getAttribute('data-placeholder') || dropdown.getAttribute('data-placeholder') || '';
                
                if (!text) {
                    const name = dropdown.querySelector('input[type="hidden"], select')?.getAttribute('name') || '';
                    if (name.includes('printer')) text = 'Any Printer...';
                    else if (name.includes('status')) text = 'Any Status...';
                    else text = 'All Types';
                }
                
                const span = document.createElement('span');
                span.className = 'enh-placeholder';
                span.textContent = text;
                triggerContent.prepend(span);
            }

            // Clear option states
            dropdown.querySelectorAll('.enh-option').forEach(opt => {
                opt.classList.remove('selected', 'active', 'is-selected');
                opt.removeAttribute('data-selected');
                const check = opt.querySelector('.enh-check, .enh-tick, input[type="checkbox"]');
                if (check) {
                    if (check.type === 'checkbox') check.checked = false;
                    else check.classList.remove('checked', 'visible');
                }
            });

            // Reset state classes that might hide the placeholder
            trigger?.classList.remove('has-value', 'is-dirty', 'has-selection');
            dropdown.classList.remove('has-value', 'is-dirty', 'has-selection');
            dropdown.setAttribute('data-has-value', 'false');
            trigger?.setAttribute('data-has-value', 'false');
        });

        // Remove dynamic hidden inputs
        Array.from(form.querySelectorAll('input[type="hidden"]')).forEach(input => {
            if (!keepNames.includes(input.name)) input.remove();
        });

        // Trigger AJAX reload (Table updates, but page/drawer does NOT move)
        document.getElementById('page_input').value = '1';
        form.dispatchEvent(new Event('change'));
    }

    // --- Auto AJAX Form Handler ---
    document.addEventListener('DOMContentLoaded', () => {
        // Capture exact dropdown states on load for the clear function
        document.querySelectorAll('.enh-dropdown').forEach(dropdown => {
            const triggerContent = dropdown.querySelector('.enh-trigger-content');
            if (triggerContent) {
                dropdownInitialHTML.set(dropdown, triggerContent.innerHTML);
            }
        });

        const form = document.getElementById('ajax-filter-form');
        const container = document.getElementById('tasks-container');

        function loadData(url) {
            if (!validateDateRange(true)) { window.hideLoader(); return; }
            window.showLoader();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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

        function buildAndLoadUrl() {
            const url = new URL(window.location.pathname, window.location.origin);
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (key === 'type') {
                    url.searchParams.append('type', value);
                } else if (value) {
                    url.searchParams.append(key, value);
                }
            }
            loadData(url);
        }

        form.addEventListener('change', (e) => {
            if (e.target.id === 'endDate' && !e.target.value) {
                if (startDateInput.value) return;
            }
            if (!e.target.classList.contains('per-page-select')) {
                document.getElementById('page_input').value = '1';
            }
            buildAndLoadUrl();
        });

        document.addEventListener('click', (e) => {
            const link = e.target.closest('.page-link');
            if (link && link.tagName === 'A') { e.preventDefault(); loadData(link.href); }
        });

        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('per-page-select')) {
                document.getElementById('per_page_input').value = e.target.value;
                document.getElementById('page_input').value = '1';
                buildAndLoadUrl();
            }
        });

        validateDateRange(false);
    });

    window.updateSort = function(column, order) {
        document.getElementById('sort_input').value = column;
        document.getElementById('order_input').value = order;
        document.getElementById('page_input').value = '1';
        document.getElementById('ajax-filter-form').dispatchEvent(new Event('change'));
    };
</script>
</body>

</html>