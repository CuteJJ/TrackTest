<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole(['admin','lead']);

// ─── Handle Batch Delete ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_delete']) && !empty($_POST['task_ids'])) {
    $ids = $_POST['task_ids'];
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM test_results WHERE task_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM task_assignments WHERE task_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM tasks WHERE id IN ($placeholders)")->execute($ids);
        $pdo->commit();
        Helper::setFlash(count($ids) . " task(s) permanently deleted.", "success");
    } catch(Exception $e) {
        $pdo->rollBack();
        Helper::setFlash("Deletion failed: " . $e->getMessage(), "error");
    }
    header("Location: admin_history.php"); exit();
}

// ─── Dropdown Options Construction ───
$printerOpts = $pdo->query("SELECT model_name, model_name FROM printers ORDER BY model_name")->fetchAll(PDO::FETCH_KEY_PAIR);
$fwOpts = $pdo->query("SELECT DISTINCT fw_version_current, fw_version_current FROM tasks WHERE fw_version_current != '' ORDER BY fw_version_current DESC")->fetchAll(PDO::FETCH_KEY_PAIR);

$dropdownOptions = [
    'Status' => ['Pass' => 'Passed', 'Fail' => 'Failed', 'Blocked' => 'Blocked', 'N/A' => 'N/A', 'Pending' => 'Pending'],
    'Workflow Type' => ['Smoke' => 'Smoke', 'Regression' => 'Regression'],
    'Printers' => $printerOpts,
    'Target FW' => $fwOpts
];

// ─── Sorting & Pagination Setup ───
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, (int)($_GET['per_page'] ?? 15));
$offset = ($page - 1) * $perPage;

$validSorts = [
    'task_id' => 't.id', 
    'task_date' => 't.task_date', 
    'model_name' => 'p.model_name', 
    'testing_type' => 't.testing_type', 
    'overall_status' => 'overall_status'
];
$sortBy = $_GET['sort_by'] ?? 'task_id';
$sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$orderSql = $validSorts[$sortBy] ?? 't.id';

// ─── Smart Filter Extraction ───
$selected_filters = $_GET['filters'] ?? [];
if (!is_array($selected_filters)) $selected_filters = [$selected_filters];

$filter_statuses = []; $filter_types = []; $filter_printers = []; $filter_fws = [];
$valid_statuses = ['Pass', 'Fail', 'Blocked', 'N/A', 'Pending'];
$valid_types = ['Smoke', 'Regression'];

// Categorize what the user selected
foreach ($selected_filters as $f) {
    if (in_array($f, $valid_statuses)) $filter_statuses[] = $f;
    elseif (in_array($f, $valid_types)) $filter_types[] = $f;
    elseif (array_key_exists($f, $printerOpts)) $filter_printers[] = $f;
    else $filter_fws[] = $f;
}

$whereConditions = [];
if (!empty($filter_types)) {
    $inQuery = implode(',', array_map(function($s) use ($pdo) { return $pdo->quote($s); }, $filter_types));
    $whereConditions[] = "t.testing_type IN ($inQuery)";
}
if (!empty($filter_printers)) {
    $inQuery = implode(',', array_map(function($s) use ($pdo) { return $pdo->quote($s); }, $filter_printers));
    $whereConditions[] = "p.model_name IN ($inQuery)";
}
if (!empty($filter_fws)) {
    $inQuery = implode(',', array_map(function($s) use ($pdo) { return $pdo->quote($s); }, $filter_fws));
    $whereConditions[] = "t.fw_version_current IN ($inQuery)";
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$havingClause = "";
if (!empty($filter_statuses)) {
    $inQuery = implode(',', array_map(function($s) use ($pdo) { return $pdo->quote($s); }, $filter_statuses));
    $havingClause = "HAVING overall_status IN ($inQuery)";
}

// ─── Queries ───
$countSql = "
    SELECT COUNT(*) FROM (
        SELECT t.id, MAX(ta.overall_status) as overall_status
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id
        JOIN printers p ON ta.printer_id = p.id
        $whereClause
        GROUP BY t.id, p.id
        $havingClause
    ) as subquery
";
$totalRows = $pdo->query($countSql)->fetchColumn();

// Notice: p.id as printer_id is now explicitly pulled for the Report Link
$sql = "
    SELECT t.id as task_id, t.task_date, t.testing_type, t.fw_version_current, p.model_name, p.id as printer_id,
           MAX(ta.overall_status) as overall_status
    FROM tasks t
    JOIN task_assignments ta ON t.id = ta.task_id
    JOIN printers p ON ta.printer_id = p.id
    $whereClause
    GROUP BY t.id, p.id
    $havingClause
    ORDER BY $orderSql $sortDir
    LIMIT $perPage OFFSET $offset
";
$history = $pdo->query($sql)->fetchAll();

$TITLE = "Global History | Track Manager";
$ASSET_PATH = "../";
require_once '../configs/header.php';
?>
<style>
    .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { margin:0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
    
    .unified-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--border-radius); box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: visible !important; margin-bottom: 30px; }
    .filter-section { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px; overflow: visible !important;}
    
    .filter-group { flex: 1; display: flex; flex-direction: column; max-width: 600px; min-width: 250px;}
    .filter-group label { font-size: 0.68rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.05em; }

    /* Match Dropdown to inputs */
    .enh-trigger { height: var(--input-height) !important; min-height: var(--input-height) !important; }

    .btn-reset-icon {
        display: inline-flex; align-items: center; justify-content: center;
        height: var(--input-height); width: var(--input-height); background: transparent; 
        border: 1px solid transparent; border-radius: var(--border-radius); 
        cursor: pointer; color: var(--text-muted); transition: all 0.2s ease; flex-shrink: 0;
    }
    .btn-reset-icon:hover { background: var(--bg-body); color: var(--error); border-color: var(--border); }

    .table-section { overflow-x: auto; width: 100%; border-radius: 0 0 calc(var(--border-radius) - 1px) calc(var(--border-radius) - 1px); }
    .table-section::-webkit-scrollbar { height: 8px; }
    .table-section::-webkit-scrollbar-track { background: var(--bg-body); }
    .table-section::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    
    .d-table { width: 100%; min-width: 1000px; border-collapse: collapse; }
    
    /* FIX: Force columns to show overflowing content (e.g. Buttons) and never truncate with ... */
    .d-table th, .d-table td { 
        white-space: nowrap !important; 
        overflow: visible !important; 
        text-overflow: clip !important; 
    }
    .btn-mini {
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }
</style>

<?php require_once 'admin_nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">history</span>
                Global Task History
            </h1>
        </div>

        <div class="unified-card">
            
            <div class="filter-section">
                <form method="get" class="no-loader" id="ajax-filter-form" style="display: flex; flex: 1; gap: 16px; align-items: flex-end;">
                    <input type="hidden" name="sort_by" id="sort_input" value="<?= htmlspecialchars($sortBy) ?>">
                    <input type="hidden" name="sort_dir" id="order_input" value="<?= htmlspecialchars($sortDir) ?>">
                    <input type="hidden" name="page" id="page_input" value="<?= htmlspecialchars($page) ?>">
                    <input type="hidden" name="per_page" id="per_page_input" value="<?= htmlspecialchars($perPage) ?>">
                    
                    <div class="filter-group">
                        <label>Master Filter (Status, Type, Printer, FW)</label>
                        <?= Helper::enhancedDropdown([
                            'name' => 'filters[]', 
                            'placeholder' => 'Search and select filters...', 
                            'multiple' => true,
                            'creatable' => false,
                            'options' => $dropdownOptions, 
                            'selected' => $selected_filters
                        ]) ?>
                    </div>
                    
                    <button type="button" id="reset-filter" class="btn-reset-icon" title="Clear all filters">
                        <span class="material-symbols-outlined" style="font-size:22px;">filter_alt_off</span>
                    </button>
                </form>

                <div id="batchDeleteContainer" style="display: none; margin-left: auto;">
                    <button type="submit" form="batchForm" name="batch_delete" value="1" class="btn text-danger" style="height:var(--input-height); background:var(--error-bg); border:1px solid var(--error); color:var(--error); font-weight:700; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1); display: flex; align-items: center; gap: 6px;" onclick="return confirm('Permanently delete selected tasks and all their test results? This cannot be undone.');">
                        <span class="material-symbols-outlined" style="font-size:20px;">delete</span> Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>

            <div id="history-content">
                <form method="POST" id="batchForm">
                    <input type="hidden" name="batch_delete" value="1">
                    <div class="table-section" id="tableContainer">
                        <table class="d-table">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                                    <?= Helper::renderSortHeader('task_id', 'Task ID', $sortBy, $sortDir) ?>
                                    <?= Helper::renderSortHeader('task_date', 'Date', $sortBy, $sortDir) ?>
                                    <?= Helper::renderSortHeader('model_name', 'Printer', $sortBy, $sortDir) ?>
                                    <?= Helper::renderSortHeader('testing_type', 'Type', $sortBy, $sortDir) ?>
                                    <th>Target FW</th>
                                    <?= Helper::renderSortHeader('overall_status', 'Status', $sortBy, $sortDir) ?>
                                    <th style="text-align:right; padding-right: 24px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($history)): ?>
                                    <tr><td colspan="8" style="text-align:center; padding: 40px; color:var(--text-muted);">No history records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach($history as $h): ?>
                                        <tr>
                                            <td style="text-align: center;"><input type="checkbox" name="task_ids[]" value="<?= $h['task_id'] ?>" class="rowCheckbox" form="batchForm"></td>
                                            <td class="mono" style="font-size:0.85rem; font-weight:700; color:var(--text-main);">#<?= $h['task_id'] ?></td>
                                            <td class="mono" style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($h['task_date'])) ?></td>
                                            <td style="font-weight:600;"><?= htmlspecialchars($h['model_name']) ?></td>
                                            <td><span class="badge <?= $h['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>"><?= $h['testing_type'] ?></span></td>
                                            <td class="mono" style="font-size:0.8rem; color:var(--primary); font-weight:700;"><?= htmlspecialchars($h['fw_version_current']) ?></td>
                                            <td>
                                                <?php if ($h['overall_status'] == 'Pass'): ?>
                                                    <span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                                <?php elseif ($h['overall_status'] == 'Fail'): ?>
                                                    <span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                                <?php elseif ($h['overall_status'] == 'Blocked'): ?>
                                                    <span class="badge" style="background: var(--blocked-bg); color: var(--blocked); border: 1px solid var(--blocked);"><span class="material-symbols-outlined">block</span> BLOCKED</span>
                                                <?php elseif ($h['overall_status'] == 'N/A'): ?>
                                                    <span class="badge" style="background: var(--na-bg); color: var(--na); border: 1px solid var(--na);"><span class="material-symbols-outlined">do_not_disturb_on</span> N/A</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending"><span class="material-symbols-outlined">schedule</span> Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right; padding-right: 24px;">
                                                <a href="../generate_report.php?task_id=<?= $h['task_id'] ?>&printer_id=<?= $h['printer_id'] ?>" class="btn-mini ghost" target="_blank">
                                                    <span class="material-symbols-outlined">visibility</span> View Report
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?= Helper::renderPagination($totalRows, $perPage, $page, [15, 30, 50, 100]) ?>
            </div>
            
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const filterForm = document.getElementById('ajax-filter-form');
        
        // Dynamic Delete Button Controller
        function updateDeleteButton() {
            const checkedCount = document.querySelectorAll('.rowCheckbox:checked').length;
            const container = document.getElementById('batchDeleteContainer');
            const countSpan = document.getElementById('selectedCount');
            if (checkedCount > 0) {
                container.style.display = 'block';
                countSpan.textContent = checkedCount;
            } else {
                container.style.display = 'none';
            }
        }

        // Checkbox Binder
        function bindCheckboxes() {
            const selectAll = document.getElementById('selectAll');
            const rowCheckboxes = document.querySelectorAll('.rowCheckbox');

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    rowCheckboxes.forEach(cb => cb.checked = this.checked);
                    updateDeleteButton();
                });
            }
            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateDeleteButton);
            });
        }
        
        bindCheckboxes();

        // AJAX Loader (Only replaces the #history-content block)
        function loadData(url) {
            window.showLoader();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('history-content');
                    if (newContent) {
                        document.getElementById('history-content').innerHTML = newContent.innerHTML;
                        bindCheckboxes(); 
                        updateDeleteButton(); 
                    }
                    window.history.pushState({}, '', url);
                    window.hideLoader();
                }).catch(() => window.hideLoader());
        }

        // Global Event Delegation for Dynamic Inputs
        document.addEventListener('change', (e) => {
            // 1. Listen for Enhanced Dropdown changes
            const ajaxForm = e.target.closest('#ajax-filter-form');
            if (ajaxForm || e.target.closest('.enh-dropdown')) {
                if(!e.target.classList.contains('per-page-select')) {
                    document.getElementById('page_input').value = '1';
                }
                const url = new URL(window.location.pathname, window.location.origin);
                const formData = new FormData(filterForm);
                for (let [key, value] of formData.entries()) {
                    if (value) url.searchParams.append(key, value);
                }
                loadData(url);
            }
            
            // 2. Listen for Pagination Rows per Page changes
            if(e.target.classList.contains('per-page-select')) {
                document.getElementById('per_page_input').value = e.target.value;
                document.getElementById('page_input').value = '1';
                filterForm.dispatchEvent(new CustomEvent('change', { bubbles: true }));
            }
        });

        // Global Event Delegation for Clicks
        document.addEventListener('click', function(e) {
            // Pagination Links
            const pageLink = e.target.closest('.page-link');
            if (pageLink && pageLink.tagName === 'A') {
                e.preventDefault();
                loadData(pageLink.href);
                return;
            }

            // Table Sorting Links
            const sortHeader = e.target.closest('.sortable');
            if (sortHeader) {
                document.getElementById('sort_input').value = sortHeader.getAttribute('data-sort');
                document.getElementById('order_input').value = sortHeader.getAttribute('data-dir');
                document.getElementById('page_input').value = '1'; 
                filterForm.dispatchEvent(new CustomEvent('change', { bubbles: true }));
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
        document.getElementById('ajax-filter-form').dispatchEvent(new CustomEvent('change', { bubbles: true }));
    };
</script>
</body>
</html>