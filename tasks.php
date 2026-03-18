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

    /* --- Table Scroll Section --- */
    .table-section {
        overflow-x: auto;
        width: 100%;
        border-radius: 0 0 calc(var(--border-radius) - 1px) calc(var(--border-radius) - 1px);
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
        white-space: nowrap !important;
    }

    .action-icons {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        padding-right: 14px;
    }

    /* Reduce the height of dropdown so the filter header dont block it */
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
</style>
<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">

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
                                    <?= Helper::renderSortHeader('testing_type', 'Type', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('model_name', 'Printer', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('fw_version_current', 'Target FW', $sort, $order) ?>
                                    <?= Helper::renderSortHeader('fw_type', 'Branch', $sort, $order) ?>
                                    <th>Assigned Testers</th>
                                    <?= Helper::renderSortHeader('overall_status', 'Status', $sort, $order) ?>
                                    <th style="text-align: right; padding-right: 24px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lead_tasks as $task): ?>
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
                                        <td><span class="mono" style="font-size:0.85rem; color:var(--primary); font-weight:600;"><?= htmlspecialchars($task['fw_version_current']) ?></span></td>
                                        <td style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($task['fw_type']) ?></td>
                                        <td>
                                            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                                                <?= htmlspecialchars($task['assigned_to_names'] ?: 'Unassigned') ?>
                                            </span>
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
                                            <?php else: ?>
                                                <span class="badge badge-pending"><span class="material-symbols-outlined">schedule</span> Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-icons">
                                                <a href="edit_task.php?id=<?= $task['task_id'] ?>" class="icon-btn tooltip-trigger" data-tip="Edit Task">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </a>
                                                <a href="delete_task.php?id=<?= $task['task_id'] ?>" class="icon-btn delete tooltip-trigger" data-tip="Delete Task" onclick="return confirm('Delete this task completely?');">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </a>
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
</body>

</html>