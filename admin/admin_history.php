<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole(['admin','lead']);

// Handle Batch Delete
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

// ─── Filtering, Sorting & Pagination Setup ───
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, (int)($_GET['per_page'] ?? 15));
$offset = ($page - 1) * $perPage;

$sort_by = $_GET['sort_by'] ?? 'task_date';
$sort_dir = strtoupper($_GET['sort_dir'] ?? 'DESC');
if (!in_array($sort_dir, ['ASC', 'DESC'])) $sort_dir = 'DESC';

$allowed_sorts = [
    'id' => 't.id',
    'task_date' => 't.task_date',
    'model_name' => 'p.model_name',
    'testing_type' => 't.testing_type',
    'fw_version_current' => 't.fw_version_current',
    'overall_status' => 'overall_status'
];
$order_col = $allowed_sorts[$sort_by] ?? 't.task_date';

// Filters & Search
$where = [];
$params = [];

// NEW: Global Search Parameter
if (!empty($_GET['search'])) {
    $search = '%' . trim($_GET['search']) . '%';
    // Search across ID, Model, and Firmware configs
    $where[] = "(t.id LIKE ? OR p.model_name LIKE ? OR t.fw_version_current LIKE ? OR t.fw_type LIKE ? OR t.fw_version_prev LIKE ? OR t.fw_version_rec LIKE ?)";
    for ($i = 0; $i < 6; $i++) $params[] = $search;
}

if (!empty($_GET['start_date'])) { $where[] = "t.task_date >= ?"; $params[] = $_GET['start_date']; }
if (!empty($_GET['end_date'])) { $where[] = "t.task_date <= ?"; $params[] = $_GET['end_date']; }
if (!empty($_GET['type'])) { $where[] = "t.testing_type = ?"; $params[] = $_GET['type']; }

$havingClause = "";
if ($status_filter) {
    $havingClause = "HAVING MAX(ta.overall_status) = " . $pdo->quote($status_filter);
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// ─── 1. Count Total Rows ───
$countSql = "
    SELECT COUNT(*) FROM (
        SELECT t.id
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id
        JOIN printers p ON ta.printer_id = p.id
        $whereSQL
        GROUP BY t.id, p.id
        $havingClause
    ) as cnt_table
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// ─── 2. Fetch Paginated Data (ADDED DUE DATE & FW DETAILS) ───
$sql = "
    SELECT 
        t.id, t.task_date, t.due_date, t.testing_type, 
        t.fw_version_current, t.fw_version_prev, t.fw_version_rec, t.fw_type, 
        p.id as printer_id, p.model_name,
        MAX(ta.overall_status) as overall_status
    FROM tasks t
    JOIN task_assignments ta ON t.id = ta.task_id
    JOIN printers p ON ta.printer_id = p.id
    $whereSQL
    GROUP BY t.id, p.id
    $havingClause
    ORDER BY $order_col $sort_dir
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

function renderSortHeader($colKey, $label, $currentSort, $currentDir) {
    $icon = 'unfold_more'; 
    $nextDir = 'DESC'; 
    $activeStyle = "color: var(--text-muted);";
    
    if ($currentSort === $colKey) {
        $activeStyle = "color: var(--primary); font-weight: 800;";
        if ($currentDir === 'DESC') {
            $icon = 'arrow_downward';
            $nextDir = 'ASC';
        } else {
            $icon = 'arrow_upward';
            $nextDir = 'DESC';
        }
    }
    
    return "
    <th class='sortable' data-sort='$colKey' data-dir='$nextDir' style='cursor:pointer; user-select:none; transition:background 0.2s;'>
        <div style='display:flex; align-items:center; gap:4px; $activeStyle transition: color 0.2s;'>
            $label <span class='material-symbols-outlined' style='font-size:14px;'>$icon</span>
        </div>
    </th>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Global History | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../app.css">
    <script>
        const savedTheme = localStorage.getItem('track-manager-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <style>
        .sortable:hover { background: var(--bg-surface) !important; }
        .sortable:hover div { color: var(--text-main) !important; }
        
        /* Search Bar Input Icon wrapper */
        .search-wrap { position: relative; display: flex; align-items: center; }
        .search-wrap .material-symbols-outlined { position: absolute; left: 10px; color: var(--text-muted); font-size: 18px; }
        .search-input { padding-left: 36px !important; }
    </style>
</head>
<body>
    <?php Helper::displayFlash(); ?>
    <?php Helper::displayLoader(); ?>

    <!-- Include navbar and sidebar -->
    <?php include 'admin_navbar.php'; ?>
    <div class="page-content-scroll">
        <main class="admin-content">
            
            <div class="d-card">
                <div class="d-card-header"><div class="d-card-title"><span class="material-symbols-outlined">filter_list</span> Live Filters</div></div>
                <div class="d-card-body" style="padding: 20px;">
                    <form id="ajax-filter-form" style="display:flex; gap:16px; align-items:flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.7rem; font-weight:700; color:var(--text-muted);">From Date</label>
                            <input type="date" name="start_date" class="form-control" style="padding:8px;" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.7rem; font-weight:700; color:var(--text-muted);">To Date</label>
                            <input type="date" name="end_date" class="form-control" style="padding:8px;" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.7rem; font-weight:700; color:var(--text-muted);">Testing Type</label>
                            <select name="type" class="form-control" style="padding:8px; min-width: 120px;">
                                <option value="">All Types</option>
                                <option value="Smoke" <?= ($_GET['type']??'')=='Smoke'?'selected':'' ?>>Smoke</option>
                                <option value="Regression" <?= ($_GET['type']??'')=='Regression'?'selected':'' ?>>Regression</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.7rem; font-weight:700; color:var(--text-muted);">Status</label>
                            <select name="status" class="form-control" style="padding:8px; min-width: 120px;">
                                <option value="">All Statuses</option>
                                <option value="Pass" <?= ($_GET['status']??'')=='Pass'?'selected':'' ?>>Passed</option>
                                <option value="Fail" <?= ($_GET['status']??'')=='Fail'?'selected':'' ?>>Failed</option>
                                <option value="Pending" <?= ($_GET['status']??'')=='Pending'?'selected':'' ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.7rem; font-weight:700; color:var(--text-muted);">Search Records</label>
                            <div class="search-wrap">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" name="search" id="searchInput" class="form-control search-input" style="padding:8px; min-width: 200px;" placeholder="Task ID, Model, FW..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>

                        <button type="button" id="reset-filter" class="btn-mini ghost" style="height: 37px; padding: 0 16px; margin-left: auto;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">refresh</span> Reset
                        </button>
                    </form>
                </div>
            </div>

            <div class="d-card">
                <form method="POST" onsubmit="return confirm('WARNING: You are about to permanently delete the selected tasks and all their data. Continue?');">
                    
                    <div id="history-container">
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg-body); flex-wrap: wrap; gap: 12px;">
                            <div style="display:flex; align-items:center; gap: 16px;">
                                <span style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">Master Record (<?= $totalRows ?> total)</span>
                                
                                <select id="perPageSelect" class="form-control" style="padding: 6px 12px; width: auto; font-size: 0.8rem;">
                                    <option value="15" <?= $perPage == 15 ? 'selected' : '' ?>>15 rows</option>
                                    <option value="30" <?= $perPage == 30 ? 'selected' : '' ?>>30 rows</option>
                                    <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50 rows</option>
                                    <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100 rows</option>
                                </select>
                            </div>
                            
                            <button type="submit" name="batch_delete" class="btn-mini ghost" style="color:var(--error); border-color:var(--error);">
                                <span class="material-symbols-outlined">delete</span> Delete Selected
                            </button>
                        </div>

                        <?php if (empty($history)): ?>
                            <div class="empty-state">
                                <span class="material-symbols-outlined">history</span>
                                <p>No records found matching these criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="d-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px; text-align:center;">
                                                <input type="checkbox" onclick="document.querySelectorAll('.tc-check').forEach(c=>c.checked=this.checked)">
                                            </th>
                                            <?= renderSortHeader('id', 'Task ID', $sort_by, $sort_dir) ?>
                                            <?= renderSortHeader('task_date', 'Date', $sort_by, $sort_dir) ?>
                                            <?= renderSortHeader('model_name', 'Model', $sort_by, $sort_dir) ?>
                                            <?= renderSortHeader('testing_type', 'Type', $sort_by, $sort_dir) ?>
                                            <?= renderSortHeader('fw_version_current', 'Target FW', $sort_by, $sort_dir) ?>
                                            <?= renderSortHeader('overall_status', 'Result', $sort_by, $sort_dir) ?>
                                            <th style="width: 40px; text-align:center;"></th> </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($history as $h): ?>
                                        <?php $rowId = "task_" . $h['id'] . "_" . $h['printer_id']; ?>
                                        
                                        <tr class="expand-trigger main-row" onclick="toggleRow('<?= $rowId ?>', this)">
                                            <td style="text-align:center;" onclick="event.stopPropagation();">
                                                <input type="checkbox" name="task_ids[]" value="<?= $h['id'] ?>" class="tc-check">
                                            </td>
                                            <td><span style="font-family: 'JetBrains Mono', monospace; color: var(--text-muted); font-size: 0.8rem;">#<?= $h['id'] ?></span></td>
                                            <td><span class="mono" style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($h['task_date'])) ?></span></td>
                                            <td><strong style="font-size:0.88rem;"><?= htmlspecialchars($h['model_name']) ?></strong></td>
                                            <td><span class="badge <?= $h['testing_type'] == 'Smoke' ? 'badge-smoke' : 'badge-reg' ?>"><?= $h['testing_type'] ?></span></td>
                                            <td><span style="font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; color: var(--primary); font-weight: 600;"><?= htmlspecialchars($h['fw_version_current']) ?></span></td>
                                            <td>
                                                <?php if ($h['overall_status'] == 'Pass'): ?><span class="badge badge-pass"><span class="material-symbols-outlined">check_circle</span> PASSED</span>
                                                <?php elseif ($h['overall_status'] == 'Fail'): ?><span class="badge badge-fail"><span class="material-symbols-outlined">cancel</span> FAILED</span>
                                                <?php else: ?><span class="badge badge-pending"><span class="material-symbols-outlined">schedule</span> PENDING</span><?php endif; ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <span class="material-symbols-outlined chevron-icon" id="chev-<?= $rowId ?>">expand_more</span>
                                            </td>
                                        </tr>

                                        <tr class="expanded-row">
                                            <td colspan="8">
                                                <div class="accordion-wrapper" id="<?= $rowId ?>">
                                                    <div class="expanded-content">
                                                        <div class="expand-detail">
                                                            <span class="expand-detail-label">Due Date</span>
                                                            <span class="expand-detail-value" style="font-family:var(--font-body);"><?= date('M d, Y', strtotime($h['due_date'])) ?></span>
                                                        </div>
                                                        <div class="expand-detail">
                                                            <span class="expand-detail-label">Branch Type</span>
                                                            <span class="expand-detail-value"><?= htmlspecialchars($h['fw_type']) ?></span>
                                                        </div>
                                                        <div class="expand-detail">
                                                            <span class="expand-detail-label">Prev / Rec FW</span>
                                                            <span class="expand-detail-value">
                                                                <span style="color:var(--text-muted); opacity:0.8;"><?= htmlspecialchars($h['fw_version_prev']) ?></span>
                                                                <span style="color:var(--border); margin:0 4px;">/</span>
                                                                <span style="color:var(--error);"><?= htmlspecialchars($h['fw_version_rec']) ?></span>
                                                            </span>
                                                        </div>
                                                        <div class="expand-actions">
                                                            <a href="../report.php?task_id=<?= $h['id'] ?>&printer_id=<?= $h['printer_id'] ?>" class="btn-mini ghost">
                                                                <span class="material-symbols-outlined">description</span> View Report
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

                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link prev">← Prev</a>
                            <?php endif; ?>

                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="page-link active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link next">Next →</a>
                            <?php endif; ?>
                        </div>
                        <p class="pagination-info">Showing <?= min(($page - 1) * $perPage + 1, $totalRows) ?> – <?= min($page * $perPage, $totalRows) ?> of <?= $totalRows ?> records</p>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </main>
    </div>

    <script src="../app.js"></script>
    <script>
        // ── Row Toggle Logic ───────────────────────────────────────
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

        document.addEventListener('DOMContentLoaded', () => {
            const filterForm = document.getElementById('ajax-filter-form');
            const container = document.getElementById('history-container');
            const searchInput = document.getElementById('searchInput');

            if (!filterForm || !container) return;

            function loadData(url) {
                window.showLoader();
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        const newContainer = doc.getElementById('history-container');
                        if (newContainer) {
                            container.innerHTML = newContainer.innerHTML;
                        }
                        
                        window.history.pushState({}, '', url);
                        window.hideLoader();
                    })
                    .catch(err => {
                        console.error('AJAX Error:', err);
                        window.hideLoader();
                    });
            }

            // --- Updated Filter Change Event ---
            filterForm.addEventListener('change', function(e) {
                // Ignore the search input here, handled by keyup debounce below
                if(e.target.id === 'searchInput') return; 

                submitFilters();
            });

            // --- DEBOUNCED SEARCH EVENT ---
            let searchTimeout = null;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(submitFilters, 400); // Wait 400ms after user stops typing
            });

            function submitFilters() {
                const url = new URL(window.location.href);
                const formData = new FormData(filterForm);
                
                url.searchParams.set('start_date', formData.get('start_date') || '');
                url.searchParams.set('end_date', formData.get('end_date') || '');
                url.searchParams.set('type', formData.get('type') || '');
                url.searchParams.set('status', formData.get('status') || '');
                url.searchParams.set('search', formData.get('search') || '');
                url.searchParams.set('page', '1'); // Reset to page 1 on new filter
                
                const perPage = document.getElementById('perPageSelect');
                if(perPage) url.searchParams.set('per_page', perPage.value);

                // Preserve existing sort configuration if it exists
                const currentUrl = new URL(window.location.href);
                if(currentUrl.searchParams.has('sort_by')) url.searchParams.set('sort_by', currentUrl.searchParams.get('sort_by'));
                if(currentUrl.searchParams.has('sort_dir')) url.searchParams.set('sort_dir', currentUrl.searchParams.get('sort_dir'));

                loadData(url);
            }

            document.getElementById('reset-filter').addEventListener('click', function() {
                filterForm.reset();
                filterForm.querySelectorAll('input, select').forEach(el => el.value = '');
                submitFilters();
            });

            // --- Unified Event Delegation for Clickables ---
            document.addEventListener('click', function(e) {
                // Pagination Clicks
                const pageLink = e.target.closest('.page-link');
                if (pageLink && pageLink.tagName === 'A') {
                    e.preventDefault();
                    loadData(pageLink.href);
                    return;
                }

                // Sort Header Clicks
                const sortHeader = e.target.closest('.sortable');
                if (sortHeader) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('sort_by', sortHeader.getAttribute('data-sort'));
                    url.searchParams.set('sort_dir', sortHeader.getAttribute('data-dir'));
                    url.searchParams.set('page', '1'); // Reset to page 1 when sort order changes
                    loadData(url);
                }
            });

            // Rows per page dropdown
            document.addEventListener('change', function(e) {
                if(e.target.id === 'perPageSelect') {
                    const url = new URL(window.location.href);
                    url.searchParams.set('per_page', e.target.value);
                    url.searchParams.set('page', '1');
                    loadData(url);
                }
            });
        });
    </script>
</body>
</html>