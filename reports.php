<?php
require_once 'controllers/ReportController.php';

// Augment the reports data with Progress, Dates, and Participants (and enforce Smoke Tests only)
foreach ($my_reports as $key => &$row) {
    // 1. Fetch missing tasks data & filter for Smoke Tests only
    $stmt = $pdo->prepare("SELECT testing_type, due_date, fw_version_prev, fw_version_rec FROM tasks WHERE id = ?");
    $stmt->execute([$row['task_id']]);
    $tInfo = $stmt->fetch();
    
    if (!$tInfo || $tInfo['testing_type'] !== 'Smoke') {
        unset($my_reports[$key]);
        continue;
    }
    
    $row['due_date'] = $tInfo['due_date'];
    $row['fw_version_prev'] = $tInfo['fw_version_prev'];
    $row['fw_version_rec'] = $tInfo['fw_version_rec'];
    
    // 2. Calculate Progress (Completed vs Total Cases)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_cases WHERE printer_model = ?");
    $stmt->execute([$row['model_name']]);
    $total_cases = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_results WHERE task_id = ? AND printer_id = ? AND status IN ('Pass', 'Fail', 'Blocked', 'N/A')");
    $stmt->execute([$row['task_id'], $row['printer_id']]);
    $completed_cases = $stmt->fetchColumn();
    
    $row['total_cases'] = $total_cases;
    $row['completed_cases'] = $completed_cases;
    $row['progress'] = $total_cases > 0 ? round(($completed_cases / $total_cases) * 100) : 0;
    
    // 3. Fetch assigned participants for this printer
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.pfp_path, ta.designation 
        FROM task_assignments ta 
        JOIN users u ON ta.user_id = u.id 
        WHERE ta.task_id = ? AND ta.printer_id = ? 
        ORDER BY ta.designation ASC
    ");
    $stmt->execute([$row['task_id'], $row['printer_id']]);
    $row['participants'] = $stmt->fetchAll();
}
unset($row);

// Icon Helper for Printers
function getPrinterIcon(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'flare')) return 'local_fire_department';
    if (str_contains($n, 'ray'))   return 'bolt';
    if (str_contains($n, 'mfp'))  return 'content_copy';
    if (str_contains($n, 'sfp'))  return 'print';
    return 'print';
}

function renderPrinterImage($path, $name) {
    if (str_contains($path, '/') || str_contains($path, '.')) {
        // We are in the root directory, so NO '../' is needed here.
        $displayPath = str_starts_with($path, 'http') ? $path : $path;
        return "<img src='".htmlspecialchars($displayPath)."?v=".time()."' style='width:100%; height:100%; object-fit:cover; border-radius:50%;'>";
    }
    $iconText = $path ?: getPrinterIcon($name);
    return "<span class='material-symbols-outlined' style='font-size: 18px; color: var(--primary);'>".htmlspecialchars($iconText)."</span>";
}

$TITLE = "Reports | Track Manager";
require_once 'configs/header.php';
?>
<style>
    /* --- Page Header --- */
    .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { margin: 0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }

    /* --- Unified SaaS Card & Table Controls --- */
    .unified-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--border-radius); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02); display: flex; flex-direction: column; margin-bottom: 30px; }
    .table-controls { padding: 8px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: flex-end; align-items: center; gap: 12px; background: var(--bg-surface); border-radius: var(--border-radius) var(--border-radius) 0 0; }
    
    .btn-control { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 14px; background: var(--bg-body); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
    .btn-control:hover { background: var(--border); color: var(--text-main); }
    .btn-control.ghost { background: transparent; color: var(--text-muted); border-color: transparent; }
    .btn-control.ghost:hover { background: var(--error-bg); color: var(--error); }
    .btn-control .material-symbols-outlined { font-size: 18px; }

    /* --- Table & Progress Bar Styles --- */
    .table-section { overflow-x: auto; width: 100%; border-radius: 0 0 calc(var(--border-radius) - 1px) calc(var(--border-radius) - 1px); }
    .d-table { width: 100%; min-width: 1050px; border-collapse: collapse; }
    .d-table th, .d-table td { white-space: nowrap !important; overflow: visible !important; text-overflow: clip !important; }
    
    .main-row { cursor: pointer; transition: background 0.15s; }
    .main-row:hover { background: var(--bg-body); }
    .main-row.is-open { background: var(--bg-body); }

    .progress-wrap { width: 160px; }
    .progress-labels { display: flex; justify-content: space-between; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 5px; }
    .progress-bar-bg { width: 100%; height: 6px; background: var(--border); border-radius: 4px; overflow: hidden; }
    .progress-bar-fill { height: 100%; background: var(--primary); transition: width 0.4s cubic-bezier(0.4,0,0.2,1); }

    .btn-disabled { background: var(--bg-body); color: var(--text-muted); border: 1px solid var(--border); cursor: not-allowed; opacity: 0.7; }

    /* --- Accordion Smooth Slide Styles --- */
    .expanded-row td { padding: 0 !important; border-bottom: none !important; }
    
    .accordion-wrapper {
        display: grid;
        grid-template-rows: 0fr;
        transition: grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .accordion-wrapper.open {
        grid-template-rows: 1fr;
    }
    .expanded-content {
        overflow: hidden;
    }
    .accordion-content { 
        padding: 20px 24px; 
        background: var(--bg-body); 
        border-bottom: 1px solid var(--border); 
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); 
    }
    
    .acc-grid { display: flex; flex-wrap: wrap; gap: 32px; align-items: center; }
    .acc-item { display: flex; flex-direction: column; gap: 6px; }
    .acc-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
    .acc-value { font-size: 0.88rem; font-weight: 600; color: var(--text-main); }
    
    /* Participants Horizontal Scroll */
    .participant-list { display: flex; gap: 8px; overflow-x: auto; max-width: 340px; padding-bottom: 4px; align-items: center; }
    .participant-list::-webkit-scrollbar { height: 4px; }
    .participant-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
    .participant-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); flex-shrink: 0; background: var(--bg-surface); cursor: help; }
    .participant-main { border: 2px solid var(--primary); padding: 2px; }

    /* --- Right Filter Drawer UI --- */
    .drawer-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
    .drawer-overlay.show { opacity: 1; visibility: visible; }
    .filter-drawer { position: fixed; top: 0; right: -400px; width: 100%; max-width: 360px; height: 100vh; background: var(--bg-surface); z-index: 9999; box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-left: 1px solid var(--border); }
    .filter-drawer.open { right: 0; }
    .drawer-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .drawer-header h3 { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    .drawer-header h3 .material-symbols-outlined { color: var(--primary); }
    .drawer-body { padding: 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 24px; }
    
    .filter-group { display: flex; flex-direction: column; margin-bottom: 24px; }
    .filter-group label { font-size: 0.68rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.05em; }
    .date-flex { display: flex; gap: 8px; align-items: center; }
    .date-flex input { flex: 1; height: var(--input-height); padding: 0 12px; width: 100%; border: 1px solid var(--border); border-radius: var(--border-radius); background: var(--bg-body); color: var(--text-main); font-size: 0.85rem; outline: none; transition: all 0.2s; }
    .date-flex input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.15); }
    
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
    [data-theme="midnight"] input[type="date"]::-webkit-calendar-picker-indicator,
    [data-theme="catppuccin"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.8); cursor: pointer; }
    
    .drawer-body .enh-trigger { height: auto !important; min-height: var(--input-height) !important; padding: 6px 14px !important; }
    .drawer-body .enh-trigger-content { flex-wrap: wrap !important; overflow-x: visible !important; margin: 4px 0 !important; }
    .drawer-body .enh-chip { white-space: normal !important; height: auto; line-height: 1.3; }
    
    .enh-menu { max-height: 250px; }
    .modal-filter-reset-btn { background: transparent; border: none; color: var(--text-muted); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; margin-left: auto; margin-right: 4px; }
    .modal-filter-reset-btn:hover { color: var(--primary); }
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

                <div class="table-controls">
                    <button type="button" class="btn-control ghost" onclick="confirmReset()">
                        <span class="material-symbols-outlined">restart_alt</span> Reset
                    </button>
                    <button type="button" class="btn-control" onclick="toggleFilterDrawer()">
                        <span class="material-symbols-outlined">tune</span> Filters
                    </button>
                </div>

                <div id="reports-container">
                    <?php if (empty($my_reports)): ?>
                        <div class="empty-state" style="border:none; border-radius:0;">
                            <span class="material-symbols-outlined">folder_open</span>
                            <p>No smoke test tasks found requiring a report.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-section">
                            <table class="d-table">
                                <thead>
                                    <tr>
                                        <?= Helper::renderSortHeader('task_date', 'Date', $sort, $order) ?>
                                        <?= Helper::renderSortHeader('model_name', 'Printer', $sort, $order) ?>
                                        <?= Helper::renderSortHeader('fw_version_current', 'Target FW', $sort, $order) ?>
                                        <th>Progress</th>
                                        <?= Helper::renderSortHeader('overall_status', 'Status', $sort, $order) ?>
                                        <th style="text-align: right; padding-right: 24px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_reports as $row): 
                                        $rowId = "task_" . $row['task_id'] . "_" . $row['printer_id'];
                                    ?>
                                        <tr class="main-row expand-trigger" onclick="toggleRow('<?= $rowId ?>', this)">
                                            <td>
                                                <span class="mono" style="font-size:0.8rem; color:var(--text-muted);">
                                                    <?= date('M d, Y', strtotime($row['task_date'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border-radius: 50%; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border);">
                                                        <?= renderPrinterImage($row['printer_path'] ?? null, htmlspecialchars($row['model_name'])) ?>
                                                    </div>
                                                    <strong style="font-size:0.88rem;"><?= htmlspecialchars($row['model_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><span class="mono" style="font-size:0.85rem; color:var(--primary); font-weight:600;"><?= htmlspecialchars($row['fw_version_current']) ?></span></td>
                                            
                                            <td>
                                                <div class="progress-wrap">
                                                    <div class="progress-labels">
                                                        <span>COMPLETED</span>
                                                        <span style="color: <?= $row['progress'] == 100 ? 'var(--success)' : 'var(--primary)' ?>;">
                                                            <?= $row['completed_cases'] ?>/<?= $row['total_cases'] ?> (<?= $row['progress'] ?>%)
                                                        </span>
                                                    </div>
                                                    <div class="progress-bar-bg">
                                                        <div class="progress-bar-fill" style="width: <?= $row['progress'] ?>%; background: <?= $row['progress'] == 100 ? 'var(--success)' : 'var(--primary)' ?>;"></div>
                                                    </div>
                                                </div>
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
                                            <td style="text-align: right; padding-right: 24px;" onclick="event.stopPropagation();">
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

                                        <tr class="expanded-row" id="<?= $rowId ?>_exp">
                                            <td colspan="6">
                                                <div class="accordion-wrapper" id="<?= $rowId ?>">
                                                    <div class="expanded-content">
                                                        <div class="accordion-content">
                                                            <div class="acc-grid">
                                                                <div class="acc-item">
                                                                    <span class="acc-label">Due Date</span>
                                                                    <span class="acc-value"><?= date('M d, Y', strtotime($row['due_date'])) ?></span>
                                                                </div>
                                                                <div class="acc-item">
                                                                    <span class="acc-label">Prev. Firmware</span>
                                                                    <span class="acc-value mono"><?= htmlspecialchars($row['fw_version_prev']) ?></span>
                                                                </div>
                                                                <div class="acc-item">
                                                                    <span class="acc-label">Recovery Firmware</span>
                                                                    <span class="acc-value mono"><?= htmlspecialchars($row['fw_version_rec']) ?></span>
                                                                </div>
                                                                <div class="acc-item">
                                                                    <span class="acc-label">My Role</span>
                                                                    <span class="badge <?= $row['designation'] == 'Main' ? 'badge-main' : 'badge-support' ?>"><?= htmlspecialchars($row['designation']) ?></span>
                                                                </div>
                                                                
                                                                <div class="acc-item" style="flex: 1; padding-left: 20px; border-left: 1px solid var(--border);">
                                                                    <span class="acc-label">Test Participants</span>
                                                                    <div class="participant-list">
                                                                        <?php foreach($row['participants'] as $pt): 
                                                                            $isMain = $pt['designation'] === 'Main'; 
                                                                        ?>
                                                                            <img src="<?= htmlspecialchars($pt['pfp_path'] ?? 'imgs/default_pfp.svg') ?>" 
                                                                                 class="participant-avatar tooltip-trigger <?= $isMain ? 'participant-main' : '' ?>" 
                                                                                 data-tip="<?= htmlspecialchars($pt['full_name']) . ($isMain ? ' (Main)' : ' (Support)') ?>">
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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

<div class="drawer-overlay" id="filterOverlay" onclick="toggleFilterDrawer()"></div>
<div class="filter-drawer" id="filterDrawer">
    <div class="drawer-header">
        <h3><span class="material-symbols-outlined">tune</span> Filters</h3>
        <button type="button" class="modal-filter-reset-btn" onclick="confirmReset()">
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

            <div class="filter-group">
                <label>Date Range</label>
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
        </form>
    </div>
</div>

<div id="custom-tooltip"></div>

<script>
    // --- Drawer Logic ---
    function toggleFilterDrawer() {
        document.getElementById('filterDrawer').classList.toggle('open');
        const overlay = document.getElementById('filterOverlay');
        overlay.classList.toggle('show');
        document.body.style.overflow = overlay.classList.contains('show') ? 'hidden' : '';
    }

    function confirmReset() {
        if (confirm("Are you sure you want to clear all filters?")) {
            window.location.href = window.location.pathname;
        }
    }

    // --- Accordion Logic with Slide ---
    function toggleRow(rowId, triggerElement) {
        const wrapper = document.getElementById(rowId);
        const isOpen = wrapper.classList.contains('open');

        // Close all others
        document.querySelectorAll('.accordion-wrapper.open').forEach(el => el.classList.remove('open'));
        document.querySelectorAll('.main-row.is-open').forEach(el => el.classList.remove('is-open'));

        // Open selected
        if (!isOpen) {
            wrapper.classList.add('open');
            if (triggerElement) triggerElement.classList.add('is-open');
        }
    }

    // --- Auto AJAX Form Handler & Tooltip ---
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('ajax-filter-form');
        const container = document.getElementById('reports-container');

        // Initialize tooltips dynamically
        const tooltip = document.getElementById('custom-tooltip');
        document.body.addEventListener('mouseenter', (e) => {
            if (e.target.classList && e.target.classList.contains('tooltip-trigger')) {
                tooltip.textContent = e.target.getAttribute('data-tip');
                tooltip.classList.add('visible');
            }
        }, true);
        document.body.addEventListener('mousemove', (e) => {
            if (tooltip.classList.contains('visible')) {
                tooltip.style.left = e.pageX + 15 + 'px';
                tooltip.style.top = e.pageY + 15 + 'px';
            }
        }, true);
        document.body.addEventListener('mouseleave', (e) => {
            if (e.target.classList && e.target.classList.contains('tooltip-trigger')) {
                tooltip.classList.remove('visible');
            }
        }, true);

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
                }).catch(() => window.hideLoader());
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