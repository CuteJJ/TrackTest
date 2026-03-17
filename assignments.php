<?php
require_once 'controllers/AssignmentsController.php';
$TITLE = "Assignments | Track Manager";
require_once 'configs/header.php';
?>
    <style>
    </style>
<?php require_once 'configs/nav.php'; ?>

    <div class="page-content-scroll">
        <div class="dash-wrapper">
            <div class="d-card">
                <div class="d-card-header">
                    <div class="d-card-title">
                        <span class="material-symbols-outlined">task_alt</span>
                        <?= $_SESSION['role'] === 'lead' ? 'Active Testing Tasks' : 'My Assignments' ?>
                    </div>
                    <?php if ($_SESSION['role'] === 'lead'): ?>
                        <a href="create_task.php" class="btn-mini">
                            <span class="material-symbols-outlined">add</span> Create Task
                        </a>
                    <?php endif; ?>
                </div>

                <div class="d-card-body">
                    <form method="get" class="filter-bar no-loader" id="ajax-filter-form">
                        <div class="filter-group">
                            <label for="start_date">From</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">To</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                        </div>
                        <div class="filter-group">
                            <label for="type">Type</label>
                            <select id="type" name="type">
                                <option value="" <?= empty($_GET['type']) ? 'selected' : '' ?>>All</option>
                                <option value="Smoke" <?= ($_GET['type'] ?? '') == 'Smoke' ? 'selected' : '' ?>>Smoke</option>
                                <option value="Regression" <?= ($_GET['type'] ?? '') == 'Regression' ? 'selected' : '' ?>>Regression</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-mini" style="width: auto;">Apply Filters</button>
                        <button type="button" id="reset-filter" class="btn-mini ghost" style="width: auto;">Reset</button>
                    </form>

                    <div id="tasks-container">
                        <?php if ($_SESSION['role'] === 'lead'): ?>
                            <?php if (empty($lead_tasks)): ?>
                                <div class="empty-state">
                                    <span class="material-symbols-outlined">inbox</span>
                                    <p>No active tasks found matching criteria.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="d-table">
                                        <colgroup>
                                            <col style="width:12%">
                                            <col style="width:12%">
                                            <col style="width:26%">
                                            <col style="width:26%">
                                            <col style="width:18%">
                                            <col style="width:6%">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Printer</th>
                                                <th>Progress</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lead_tasks as $task): ?>
                                                <?php
                                                $is_complete = ($task['completed_cases'] >= $task['total_cases']) && ($task['total_cases'] > 0);
                                                $percent = $task['total_cases'] > 0 ? round(($task['completed_cases'] / $task['total_cases']) * 100) : 0;
                                                $rowId = "task_" . $task['task_id'] . "_" . $task['printer_id'];
                                                $printerName = htmlspecialchars($task['model_name']);
                                                ?>

                                                <tr class="expand-trigger main-row" onclick="toggleRow('<?= $rowId ?>', this)">
                                                    <td>
                                                        <span class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                                            <?= date('M d', strtotime($task['task_date'])) ?>
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
                                                                <?= Helper::renderPrinterImage($task['printer_path'] ?? null, $printerName, 16) ?>
                                                            </div>
                                                            <strong style="font-size:0.88rem;" title="<?= $printerName ?>"><?= $printerName ?></strong>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="prog-wrap">
                                                            <div class="prog-meta">
                                                                <span><?= $task['completed_cases'] ?>/<?= $task['total_cases'] ?></span>
                                                                <span><?= $percent ?>%</span>
                                                            </div>
                                                            <div class="prog-track">
                                                                <div class="prog-fill <?= $is_complete ? 'complete' : '' ?>" style="width:<?= $percent ?>%;"></div>
                                                            </div>
                                                        </div>
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
                                                            <span class="badge badge-pending">
                                                                <span class="material-symbols-outlined">schedule</span> Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <span class="material-symbols-outlined chevron-icon" id="chev-<?= $rowId ?>">expand_more</span>
                                                    </td>
                                                </tr>

                                                <tr class="expanded-row">
                                                    <td colspan="6">
                                                        <div class="accordion-wrapper" id="<?= $rowId ?>">
                                                            <div class="expanded-content">
                                                                <div class="expand-detail">
                                                                    <span class="expand-detail-label">Due Date</span>
                                                                    <span class="expand-detail-value" style="font-family:var(--font-body);"><?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                                                                </div>
                                                                <div class="expand-detail">
                                                                    <span class="expand-detail-label">Target FW</span>
                                                                    <span class="expand-detail-value" style="color:var(--primary);"><?= htmlspecialchars($task['fw_version_current']) ?></span>
                                                                </div>
                                                                <div class="expand-detail">
                                                                    <span class="expand-detail-label">Branch</span>
                                                                    <span class="expand-detail-value"><?= htmlspecialchars($task['fw_type']) ?></span>
                                                                </div>
                                                                <div class="expand-detail">
                                                                    <span class="expand-detail-label">Prev / Rec FW</span>
                                                                    <span class="expand-detail-value">
                                                                        <span style="color:var(--text-muted); opacity:0.8;"><?= htmlspecialchars($task['fw_version_prev']) ?></span>
                                                                        <span style="color:var(--border); margin:0 4px;">/</span>
                                                                        <span style="color:var(--error);"><?= htmlspecialchars($task['fw_version_rec']) ?></span>
                                                                    </span>
                                                                </div>
                                                                
                                                                <div class="expand-actions">
                                                                    <?php if ($task['testing_type'] == 'Smoke'): ?>
                                                                        <?php if ($is_complete): ?>
                                                                            <a href="report.php?task_id=<?= $task['task_id'] ?>&printer_id=<?= $task['printer_id'] ?>" class="btn-mini ghost">
                                                                                <span class="material-symbols-outlined">description</span> Report
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <span class="btn-mini disabled">
                                                                                <span class="material-symbols-outlined">hourglass_top</span> In Progress
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                    <span class="divider-line"></span>

                                                                    <?php if ($task['testing_type'] == 'Regression'): ?>
                                                                        <a href="<?= htmlspecialchars($task['regression_url'] ?? '#') ?>" target="_blank" class="icon-btn tooltip-trigger" data-tip="Open TestRail">
                                                                            <span class="material-symbols-outlined">open_in_new</span>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <a href="execute_task.php?task_id=<?= $task['task_id'] ?>&printer_id=<?= $task['printer_id'] ?>" class="icon-btn tooltip-trigger" data-tip="Update Results">
                                                                            <span class="material-symbols-outlined">fact_check</span>
                                                                        </a>
                                                                    <?php endif; ?>

                                                                    <a href="edit_task.php?id=<?= $task['task_id'] ?>" class="icon-btn tooltip-trigger" data-tip="Edit Task">
                                                                        <span class="material-symbols-outlined">edit</span>
                                                                    </a>

                                                                    <a href="delete_task.php?id=<?= $task['task_id'] ?>" class="icon-btn delete tooltip-trigger" data-tip="Delete Task" onclick="return confirm('Delete this task?');">
                                                                        <span class="material-symbols-outlined">delete</span>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <?php if (empty($my_tasks)): ?>
                                <div class="empty-state">
                                    <span class="material-symbols-outlined">assignment</span>
                                    <p>No tasks found matching criteria.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="d-table">
                                        <colgroup>
                                            <col style="width:10%">
                                            <col style="width:12%">
                                            <col style="width:17%">
                                            <col style="width:13%">
                                            <col style="width:11%">
                                            <col style="width:10%">
                                            <col style="width:13%">
                                            <col style="width:14%">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Printer</th>
                                                <th>Target FW</th>
                                                <th>Branch</th>
                                                <th>My Role</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($my_tasks as $task): ?>
                                                <?php $printerName = htmlspecialchars($task['model_name']); ?>
                                                <tr class="main-row">
                                                    <td>
                                                        <span class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                                            <?= date('M d', strtotime($task['task_date'])) ?>
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
                                                                <?= Helper::renderPrinterImage($task['printer_path'] ?? null, $printerName, 16) ?>
                                                            </div>
                                                            <strong style="font-size:0.88rem;" title="<?= $printerName ?>"><?= $printerName ?></strong>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="mono" style="font-size:0.82rem; color:var(--primary); font-weight:600;">
                                                            <?= htmlspecialchars($task['fw_version_current']) ?>
                                                        </span>
                                                    </td>
                                                    <td style="font-size:0.8rem; color:var(--text-muted);">
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
                                                            <span class="badge badge-pending"><span class="material-symbols-outlined">schedule</span> Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($task['testing_type'] == 'Regression'): ?>
                                                            <a href="<?= htmlspecialchars($task['regression_url']) ?>" target="_blank" class="btn-mini ghost">
                                                                <span class="material-symbols-outlined">open_in_new</span> Open TestRail
                                                            </a>
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
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php
                        $totalRows = ($_SESSION['role'] === 'lead') ? $pagination['leadRows'] : $pagination['myRows'];
                        $totalPages = ($_SESSION['role'] === 'lead') ? $pagination['leadPages'] : $pagination['myPages'];
                        $currentPage = $pagination['currentPage'];

                        if ($totalPages > 1):
                        ?>
                            <div class="pagination">
                                <?php if ($currentPage > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>" class="page-link prev">← Prev</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span class="page-link active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>" class="page-link next">Next →</a>
                                <?php endif; ?>
                            </div>
                            <p class="pagination-info">Showing <?= min(($currentPage - 1) * $perPage + 1, $totalRows) ?> – <?= min($currentPage * $perPage, $totalRows) ?> of <?= $totalRows ?> tasks</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
        // ── Row Toggle ───────────────────────────────────────
        function toggleRow(rowId, triggerElement) {
            const wrapper = document.getElementById(rowId);
            const chevron = document.getElementById('chev-' + rowId);
            const isOpen = wrapper.classList.contains('open');

            // Close all
            document.querySelectorAll('.accordion-wrapper.open').forEach(el => el.classList.remove('open'));
            document.querySelectorAll('.chevron-icon.open').forEach(el => el.classList.remove('open'));
            document.querySelectorAll('.main-row.is-open').forEach(el => el.classList.remove('is-open'));

            // Open target if it wasn't already open
            if (!isOpen) {
                wrapper.classList.add('open');
                if (chevron) chevron.classList.add('open');
                if (triggerElement) triggerElement.classList.add('is-open');
            }
        }

        // ── Tooltip ──────────────────────────────────────────
        const tooltip = document.getElementById('custom-tooltip');

        function attachTooltips() {
            document.querySelectorAll('[data-tip]').forEach(el => {
                el.addEventListener('mouseenter', (e) => {
                    tooltip.textContent = el.dataset.tip;
                    tooltip.classList.add('visible');
                });
                el.addEventListener('mousemove', (e) => {
                    tooltip.style.left = (e.clientX + 14) + 'px';
                    tooltip.style.top = (e.clientY - 32) + 'px';
                });
                el.addEventListener('mouseleave', () => tooltip.classList.remove('visible'));
            });
        }
        attachTooltips();

        // ── AJAX Filter & Pagination ─────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const filterForm = document.getElementById('ajax-filter-form');
            const tasksContainer = document.getElementById('tasks-container');

            if (!filterForm || !tasksContainer) return;

            function loadData(url) {
                window.showLoader();
                fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        const newContainer = doc.getElementById('tasks-container');
                        if (newContainer) {
                            tasksContainer.innerHTML = newContainer.innerHTML;
                        }

                        window.history.pushState({}, '', url);
                        attachTooltips(); // Reattach tooltips to new DOM elements
                        window.hideLoader();
                    })
                    .catch(err => {
                        console.error('AJAX Error:', err);
                        window.hideLoader();
                    });
            }

            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const url = new URL(window.location.href);
                const formData = new FormData(this);
                url.searchParams.set('start_date', formData.get('start_date'));
                url.searchParams.set('end_date', formData.get('end_date'));
                url.searchParams.set('type', formData.get('type'));
                url.searchParams.set('page', '1'); // Reset to page 1 on filter
                loadData(url);
            });

            document.getElementById('reset-filter').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('start_date').value = '<?= date('Y-m-d') ?>';
                document.getElementById('end_date').value = '';
                document.getElementById('type').value = '';
                filterForm.dispatchEvent(new Event('submit'));
            });

            // Delegate pagination clicks inside container
            document.addEventListener('click', function(e) {
                const link = e.target.closest('.page-link');
                if (link && link.tagName === 'A') {
                    e.preventDefault();
                    loadData(link.href);
                }
            });
        });
</script>
<script src="app.js"></script>
</body>
</html>