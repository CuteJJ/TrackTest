<?php
require_once 'controllers/ReportController.php';
$TITLE = "Reports | Track Manager";
require_once 'configs/header.php';
?>
<style>
    .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { margin:0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
    
    .unified-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: visible !important; margin-bottom: 30px; }
    .filter-section { padding: 24px 24px 20px; border-bottom: 1px solid var(--border); overflow: visible !important; }
    
    .filter-bar { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
    .filter-group { flex: 1; min-width: 160px; display: flex; flex-direction: column; }
    .filter-group.date-group { flex: 1.2; min-width: 280px; }
    .filter-group label { font-size: 0.68rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.05em; }
    
    .date-flex { display: flex; gap: 8px; align-items: center; }
    .date-flex input { flex: 1; height: 48px; padding: 0 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-body); color: var(--text-main); font-size: 0.88rem; outline: none; transition: all 0.2s cubic-bezier(0.4,0,0.2,1); }
    .date-flex input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(2,136,209,0.15); background: var(--bg-surface); }
    
    .btn-reset-icon { display: inline-flex; align-items: center; justify-content: center; height: 48px; width: 48px; background: transparent; border: 1px solid transparent; border-radius: 8px; cursor: pointer; color: var(--text-muted); transition: all 0.2s ease; flex-shrink: 0; }
    .btn-reset-icon:hover { background: var(--bg-body); color: var(--error); border-color: var(--border); }

    .table-section { overflow-x: auto; width: 100%; border-radius: 0 0 12px 12px; }
    .d-table { width: 100%; min-width: 1000px; border-collapse: collapse; }
    
    /* FIX: Prevent truncation */
    .d-table th, .d-table td { 
        white-space: nowrap !important; 
        overflow: visible !important; 
        text-overflow: clip !important; 
    }

    /* Disabled Button Style for Support Testers */
    .btn-disabled { background: var(--bg-body); color: var(--text-muted); border: 1px solid var(--border); cursor: not-allowed; opacity: 0.7; }
</style>

<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">summarize</span>
                Test Reports
            </h1>
        </div>
        
        <div class="unified-card">
            
            <?php if ($user_role === 'lead' || $user_role === 'admin'): ?>
                <div class="empty-state" style="border:none; padding: 60px 20px;">
                    <span class="material-symbols-outlined" style="font-size: 48px; color: var(--primary);">construction</span>
                    <h3 style="margin: 16px 0 8px; color: var(--text-main);">Lead Dashboard In Progress</h3>
                    <p style="color: var(--text-muted);">The master overview for reporting is currently being configured.</p>
                </div>
            <?php else: ?>

                <div class="filter-section">
                    <form method="get" class="no-loader" id="ajax-filter-form">
                        <input type="hidden" name="sort" id="sort_input" value="<?= htmlspecialchars($sort) ?>">
                        <input type="hidden" name="order" id="order_input" value="<?= htmlspecialchars($order) ?>">
                        <input type="hidden" name="page" id="page_input" value="<?= htmlspecialchars($page) ?>">
                        <input type="hidden" name="per_page" id="per_page_input" value="<?= htmlspecialchars($perPage) ?>">
                        
                        <div class="filter-bar">
                            <div class="filter-group date-group">
                                <label>Task Date</label>
                                <div class="date-flex">
                                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" title="Start Date">
                                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" title="End Date">
                                </div>
                            </div>

                            <div class="filter-group">
                                <label>Printers</label>
                                <?= Helper::enhancedDropdown([
                                    'name' => 'printers[]', 'placeholder' => 'Any Printer...', 'multiple' => true,
                                    'options' => $printerOpts, 'selected' => $printers
                                ]) ?>
                            </div>

                            <div class="filter-group">
                                <label>Status</label>
                                <?= Helper::enhancedDropdown([
                                    'name' => 'statuses[]', 'placeholder' => 'Any Status...', 'multiple' => true,
                                    'options' => $statusOpts, 'selected' => $statuses
                                ]) ?>
                            </div>

                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" id="reset-filter" class="btn-reset-icon" title="Reset Filters">
                                    <span class="material-symbols-outlined" style="font-size:22px;">filter_alt_off</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div id="reports-container">
                    <?php if (empty($my_reports)): ?>
                        <div class="empty-state" style="border:none; border-radius:0;">
                            <span class="material-symbols-outlined">folder_open</span>
                            <p>No tasks found requiring a report.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-section">
                            <table class="d-table">
                                <thead>
                                    <tr>
                                        <?= Helper::renderSortHeader('task_date', 'Date', $sort, $order) ?>
                                        <?= Helper::renderSortHeader('model_name', 'Printer', $sort, $order) ?>
                                        <?= Helper::renderSortHeader('fw_version_current', 'Target FW', $sort, $order) ?>
                                        <th>Branch</th>
                                        <th>My Role</th>
                                        <?= Helper::renderSortHeader('overall_status', 'Status', $sort, $order) ?>
                                        <th style="text-align: right; padding-right: 24px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_reports as $row): ?>
                                        <tr class="main-row">
                                            <td>
                                                <span class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                                    <?= date('M d, Y', strtotime($row['task_date'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border-radius: 50%; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border);">
                                                        <?= Helper::renderPrinterImage($row['printer_path'] ?? null, htmlspecialchars($row['model_name']), 16) ?>
                                                    </div>
                                                    <strong style="font-size:0.88rem;"><?= htmlspecialchars($row['model_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><span class="mono" style="font-size:0.85rem; color:var(--primary); font-weight:600;"><?= htmlspecialchars($row['fw_version_current']) ?></span></td>
                                            <td style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($row['fw_type']) ?></td>
                                            <td>
                                                <span class="badge <?= $row['designation'] == 'Main' ? 'badge-main' : 'badge-support' ?>"><?= htmlspecialchars($row['designation']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($row['overall_status'] == 'Pass'): ?>
                                                    <span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                                <?php elseif ($row['overall_status'] == 'Fail'): ?>
                                                    <span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                                <?php elseif ($row['overall_status'] == 'Blocked'): ?>
                                                    <span class="badge" style="background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked);"><span class="material-symbols-outlined">block</span> BLOCKED</span>
                                                <?php elseif ($row['overall_status'] == 'N/A'): ?>
                                                    <span class="badge" style="background: var(--na-bg); color: var(--na); border: 1px solid var(--na);"><span class="material-symbols-outlined">do_not_disturb_on</span> N/A</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending"><span class="material-symbols-outlined">schedule</span> Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right; padding-right: 24px;">
                                                <?php if ($row['overall_status'] !== 'Pending'): ?>
                                                    <a href="generate_report.php?task_id=<?= $row['task_id'] ?>&printer_id=<?= $row['printer_id'] ?>" class="btn-mini ghost">
                                                        <span class="material-symbols-outlined">visibility</span> View Report
                                                    </a>
                                                <?php else: ?>
                                                    <?php if ($row['designation'] === 'Main'): ?>
                                                        <a href="generate_report.php?task_id=<?= $row['task_id'] ?>&printer_id=<?= $row['printer_id'] ?>" class="btn-mini" style="background: var(--primary); color: white; border: none;">
                                                            <span class="material-symbols-outlined">edit_document</span> Update Result
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn-mini btn-disabled tooltip-trigger" data-tip="Only the Main tester can finalize this report.">
                                                            <span class="material-symbols-outlined">lock</span> Update Result
                                                        </button>
                                                    <?php endif; ?>
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

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('ajax-filter-form');
        const container = document.getElementById('reports-container');

        if (!form) return; 

        function loadData(url) {
            window.showLoader();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('reports-container');
                    if (newContent) container.innerHTML = newContent.innerHTML;
                    window.history.pushState({}, '', url);
                    window.hideLoader();
                })
                .catch(() => window.hideLoader());
        }

        form.addEventListener('change', (e) => {
            if(!e.target.classList.contains('per-page-select')) {
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

        document.getElementById('reset-filter').addEventListener('click', () => {
            window.location.href = window.location.pathname; 
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