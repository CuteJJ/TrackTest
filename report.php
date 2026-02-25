<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

Helper::requireRole(['lead', 'admin']);

$task_id = $_GET['task_id'] ?? null;
$printer_id = $_GET['printer_id'] ?? null;

if (!$task_id || !$printer_id) {
    header("Location: index.php");
    exit();
}

// 1. Handle Form Submission (Overall Status Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['overall_status'])) {
    $overall_status = $_POST['overall_status'];
    $stmt = $pdo->prepare("UPDATE task_assignments SET overall_status = ? WHERE task_id = ? AND printer_id = ?");
    $stmt->execute([$overall_status, $task_id, $printer_id]);

    Helper::setFlash("Report status updated to: " . $overall_status, "success");
    header("Location: report.php?task_id=$task_id&printer_id=$printer_id");
    exit();
}

// 2. Fetch Data
$stmt = $pdo->prepare("
    SELECT t.task_date, t.fw_version_current, t.fw_type, p.model_name, ta.overall_status 
    FROM tasks t
    JOIN task_assignments ta ON t.id = ta.task_id
    JOIN printers p ON ta.printer_id = p.id
    WHERE t.id = ? AND p.id = ?
    LIMIT 1
");
$stmt->execute([$task_id, $printer_id]);
$info = $stmt->fetch();

$sql = "
    SELECT tc.case_code, tc.title, tr.status, tr.jira_url, u.full_name as tester_name
    FROM test_cases tc
    JOIN test_results tr ON tc.id = tr.test_case_id
    LEFT JOIN users u ON tr.updated_by = u.id
    WHERE tr.task_id = ? AND tr.printer_id = ?
    ORDER BY tc.case_code ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$task_id, $printer_id]);
$results = $stmt->fetchAll();

// 3. Filter for Issues Table (Auto-generated from test results)
$issues = array_filter($results, function ($r) {
    return !empty($r['jira_url']);
});

$is_finalized = ($info['overall_status'] == 'Pass' || $info['overall_status'] == 'Fail');

// Stats
$total   = count($results);
$passed  = count(array_filter($results, fn($r) => $r['status'] === 'Pass'));
$failed  = count(array_filter($results, fn($r) => $r['status'] !== 'Pass'));
$pct     = $total > 0 ? round($passed / $total * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Test Report | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    <script>
        let savedTheme = localStorage.getItem('track-manager-theme');
        if (!savedTheme) {
            savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <style>
*,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            background: var(--bg-body);
            margin: 0;
            min-height: 100vh;
            color: var(--text-main);
        }

        /* ── TOPBAR ── */
        .rp-topbar {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }

        .rp-topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 700;
        }

        .rp-topbar-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
        }

        .rp-topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rp-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 7px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            border: 1.5px solid transparent;
            font-family: var(--font-body);
            text-decoration: none;
        }

        .rp-btn.ghost {
            background: transparent;
            color: var(--text-muted);
            border-color: var(--border);
        }

        .rp-btn.ghost:hover {
            border-color: var(--text-muted);
            color: var(--text-main);
        }

        .rp-btn.primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .rp-btn.primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .rp-btn .material-symbols-outlined {
            font-size: 16px;
        }

        .rp-status-hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── PAGE SHELL ── */
        .rp-wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 32px 24px 80px;
        }

        /* ── REPORT HEADER CARD ── */
        .rp-header-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 22px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        .rp-header-top {
            padding: 24px 28px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .rp-doc-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.4px;
            margin: 0 0 4px;
        }

        .rp-doc-sub {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 0;
        }

        .rp-overall-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .rp-overall-badge.pass {
            background: var(--success-bg);
            color: var(--success);
            border: 1.5px solid var(--success);
        }

        .rp-overall-badge.fail {
            background: var(--error-bg);
            color: var(--error);
            border: 1.5px solid var(--error);
        }

        .rp-overall-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1.5px solid var(--warning);
        }

        .rp-overall-badge .material-symbols-outlined {
            font-size: 20px;
        }

        .rp-meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-top: 1px solid var(--border);
        }

        @media (max-width: 680px) {
            .rp-meta-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .rp-meta-item {
            padding: 14px 20px;
            border-right: 1px solid var(--border);
        }

        .rp-meta-item:last-child {
            border-right: none;
        }

        .rp-meta-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .rp-meta-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
            font-family: var(--font-mono);
        }

        .rp-meta-value.plain {
            font-family: var(--font-body);
        }

        /* ── STAT PILLS ── */
        .rp-stats-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .rp-stat {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
            min-width: 110px;
        }

        .rp-stat-num {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
        }

        .rp-stat-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .rp-stat-num.green {
            color: var(--success);
        }

        .rp-stat-num.red {
            color: var(--error);
        }

        .rp-stat-num.blue {
            color: var(--primary);
        }

        /* ── SECTION BLOCK ── */
        .rp-section {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .rp-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-body);
        }

        .rp-section-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-main);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .rp-section-count {
            margin-left: auto;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            background: var(--bg-body);
            border: 1px solid var(--border);
            padding: 2px 8px;
            border-radius: 99px;
        }

        /* ── TABLES ── */
        .rp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;
        }

        .rp-table th {
            text-align: left;
            padding: 10px 16px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .rp-table td {
            padding: 11px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text-main);
        }

        .rp-table tr:last-child td {
            border-bottom: none;
        }

        .rp-table tbody tr:hover td {
            background: var(--bg-body);
        }

        .rp-jira-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .rp-jira-link:hover {
            text-decoration: underline;
        }

        .rp-jira-link .material-symbols-outlined {
            font-size: 13px;
        }

        .rp-dash {
            color: var(--border);
            font-weight: 400;
        }

        /* ── STATUS BADGES ── */
        .rp-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .rp-badge .material-symbols-outlined {
            font-size: 12px;
        }

        .rp-badge.pass {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .rp-badge.fail {
            background: var(--error-bg);
            color: var(--error);
            border: 1px solid var(--error);
        }

        /* ── MONO ── */
        .mono {
            font-family: var(--font-mono);
            font-size: 0.82em;
        }

        /* ── FINALIZE PANEL ── */
        .rp-finalize-panel {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px 24px;
            margin-bottom: 20px;
        }

        .rp-finalize-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0 0 14px;
        }

        .rp-finalize-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .rp-select {
            padding: 9px 14px;
            border-radius: 7px;
            border: 1.5px solid var(--border);
            background: var(--input-bg);
            font-family: var(--font-body);
            font-size: 0.88rem;
            color: var(--text-main);
            cursor: pointer;
            outline: none;
            transition: border-color 0.15s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            min-width: 180px;
        }

        .rp-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
            background-color: var(--bg-surface);
        }

        /* ── GENERATED FOOTER ── */
        .rp-footer {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            padding: 12px 0;
        }

        /* ── PRINT (Fixed for white paper) ── */
        @media print {
            @page {
                margin: 12mm;
                size: A4;
            }

            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .rp-topbar {
                display: none;
            }

            .rp-wrap {
                padding: 0;
                max-width: 100%;
            }

            .rp-header-card,
            .rp-section,
            .rp-stats-bar .rp-stat {
                box-shadow: none;
                border-color: #d0d0d0;
                border-radius: 6px;
            }

            .rp-badge.pass, .rp-overall-badge.pass {
                background: #dcfce7 !important;
                color: #15803d !important;
                border-color: #86efac !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .rp-badge.fail, .rp-overall-badge.fail {
                background: #fee2e2 !important;
                color: #b91c1c !important;
                border-color: #fca5a5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .rp-meta-grid {
                border-top: 1px solid #ccc;
            }

            .rp-meta-item {
                border-right: 1px solid #ccc;
            }
        }
    </style>
</head>

<body>

    <?php Helper::displayFlash(); ?>

    <!-- TOPBAR -->
    <div class="rp-topbar no-print">
        <div class="rp-topbar-brand">
            <div class="rp-topbar-dot"></div>
            Track Manager
        </div>
        <div class="rp-topbar-actions">
            <a href="javascript:history.back()" class="rp-btn ghost">
                <span class="material-symbols-outlined">arrow_back</span> Back
            </a>
            <?php if ($is_finalized): ?>
                <button onclick="window.print()" class="rp-btn primary">
                    <span class="material-symbols-outlined">print</span> Print / Save PDF
                </button>
            <?php else: ?>
                <span class="rp-status-hint">
                    <span class="material-symbols-outlined" style="font-size:15px;">info</span>
                    Finalize report to enable export
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="page-content-scroll">
        <div class="rp-wrap">

            <!-- ── REPORT HEADER ── -->
            <div class="rp-header-card">
                <div class="rp-header-top">
                    <div>
                        <p class="rp-doc-title">Smoke Test Report</p>
                        <p class="rp-doc-sub">Beam SOHO Test Track System · Generated <?= date('d M Y, g:i A') ?></p>
                    </div>
                    <?php if ($info['overall_status'] == 'Pass'): ?>
                        <div class="rp-overall-badge pass">
                            <span class="material-symbols-outlined">verified</span> PASSED
                        </div>
                    <?php elseif ($info['overall_status'] == 'Fail'): ?>
                        <div class="rp-overall-badge fail">
                            <span class="material-symbols-outlined">cancel</span> FAILED
                        </div>
                    <?php else: ?>
                        <div class="rp-overall-badge pending">
                            <span class="material-symbols-outlined">schedule</span> PENDING
                        </div>
                    <?php endif; ?>
                </div>
                <div class="rp-meta-grid">
                    <div class="rp-meta-item">
                        <div class="rp-meta-label">Printer Model</div>
                        <div class="rp-meta-value plain"><?= htmlspecialchars($info['model_name']) ?></div>
                    </div>
                    <div class="rp-meta-item">
                        <div class="rp-meta-label">Test Date</div>
                        <div class="rp-meta-value plain"><?= date('d M Y', strtotime($info['task_date'])) ?></div>
                    </div>
                    <div class="rp-meta-item">
                        <div class="rp-meta-label">Firmware</div>
                        <div class="rp-meta-value"><?= htmlspecialchars($info['fw_version_current']) ?></div>
                    </div>
                    <div class="rp-meta-item">
                        <div class="rp-meta-label">Branch Type</div>
                        <div class="rp-meta-value plain"><?= htmlspecialchars($info['fw_type']) ?></div>
                    </div>
                </div>
            </div>

            <!-- ── STATS ── -->
            <div class="rp-stats-bar no-print">
                <div class="rp-stat">
                    <span class="rp-stat-num blue"><?= $total ?></span>
                    <span class="rp-stat-label">Total Cases</span>
                </div>
                <div class="rp-stat">
                    <span class="rp-stat-num green"><?= $passed ?></span>
                    <span class="rp-stat-label">Passed</span>
                </div>
                <div class="rp-stat">
                    <span class="rp-stat-num red"><?= $failed ?></span>
                    <span class="rp-stat-label">Failed</span>
                </div>
                <div class="rp-stat">
                    <span class="rp-stat-num <?= $pct >= 80 ? 'green' : ($pct >= 50 ? 'blue' : 'red') ?>"><?= $pct ?>%</span>
                    <span class="rp-stat-label">Pass Rate</span>
                </div>
                <div class="rp-stat">
                    <span class="rp-stat-num red"><?= count($issues) ?></span>
                    <span class="rp-stat-label">Issues Filed</span>
                </div>
            </div>

            <!-- ── FINALIZE PANEL ── -->
            <div class="rp-finalize-panel no-print">
                <p class="rp-finalize-title"><?= $is_finalized ? 'Update Overall Result' : 'Finalize Report' ?></p>
                <form method="POST">
                    <div class="rp-finalize-row">
                        <select name="overall_status" class="rp-select" required>
                            <option value="" disabled <?= empty($info['overall_status']) || $info['overall_status'] == 'Pending' ? 'selected' : '' ?>>Select status…</option>
                            <option value="Pass" <?= $info['overall_status'] == 'Pass' ? 'selected' : '' ?>>✓ Pass</option>
                            <option value="Fail" <?= $info['overall_status'] == 'Fail' ? 'selected' : '' ?>>✗ Fail</option>
                        </select>
                        <button type="submit" class="rp-btn primary">
                            <span class="material-symbols-outlined">save</span>
                            <?= $is_finalized ? 'Update Result' : 'Finalize & Enable Export' ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── ISSUES TABLE ── -->
            <div class="rp-section">
                <div class="rp-section-header">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--error);">bug_report</span>
                    <span class="rp-section-title">New Issues Found</span>
                    <span class="rp-section-count"><?= count($issues) ?> issues</span>
                </div>
                <?php if (!empty($issues)): ?>
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>JIRA Issue URL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $count = 1;
                            foreach ($issues as $issue): ?>
                                <tr>
                                    <td style="color:var(--text-muted); font-size:0.78rem;"><?= $count++ ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($issue['jira_url']) ?>" target="_blank" class="rp-jira-link">
                                            <span class="material-symbols-outlined">open_in_new</span>
                                            <?= htmlspecialchars($issue['jira_url']) ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 24px 20px; font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                        No new issues reported for this test cycle.
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── RESULTS TABLE ── -->
            <div class="rp-section">
                <div class="rp-section-header">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">checklist</span>
                    <span class="rp-section-title">Detailed Test Results</span>
                    <span class="rp-section-count"><?= count($results) ?> cases</span>
                </div>
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th style="width:120px;">Case ID</th>
                            <th>Title</th>
                            <th style="width:90px; text-align:center;">Result</th>
                            <th style="width:160px;">Tested By</th>
                            <th style="width:90px;">Bug</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><span class="mono"><?= htmlspecialchars($row['case_code']) ?></span></td>
                                <td style="font-size:0.85rem;"><?= htmlspecialchars($row['title']) ?></td>
                                <td style="text-align:center;">
                                    <?php if ($row['status'] == 'Pass'): ?>
                                        <span class="rp-badge pass">
                                            <span class="material-symbols-outlined">check_circle</span> PASS
                                        </span>
                                    <?php else: ?>
                                        <span class="rp-badge fail">
                                            <span class="material-symbols-outlined">cancel</span> FAIL
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.82rem; color:var(--text-muted);">
                                    <?= htmlspecialchars($row['tester_name'] ?? 'Pending') ?>
                                </td>
                                <td>
                                    <?php if ($row['jira_url']): ?>
                                        <a href="<?= htmlspecialchars($row['jira_url']) ?>" target="_blank" class="rp-jira-link">
                                            <span class="material-symbols-outlined">open_in_new</span> Link
                                        </a>
                                    <?php else: ?>
                                        <span class="rp-dash">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="rp-footer">Generated by Track Manager · <?= date('Y-m-d H:i:s') ?></div>

        </div>
    </div>
</body>

</html>