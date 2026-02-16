<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

Helper::requireLogin();

// Security: Only Leads can view reports
if ($_SESSION['role'] !== 'lead') {
    Helper::setFlash("Access Denied", "error");
    header("Location: index.php");
    exit();
}

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
$issues = array_filter($results, function($r) {
    return !empty($r['jira_url']);
});

$is_finalized = ($info['overall_status'] == 'Pass' || $info['overall_status'] == 'Fail');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Report | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    
    <style>
        /* Screen Styles */
        .report-container {
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Formal Table Styles */
        .formal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 30px;
            font-size: 0.9rem;
            color: #000;
        }

        .formal-table th, .formal-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            text-align: left;
        }

        .formal-table th {
            background-color: #f0f0f0 !important;
            font-weight: 700;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Status Coloring */
        .cell-pass {
            background-color: #d4edda !important;
            color: #155724 !important;
            font-weight: bold;
            text-align: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .cell-fail {
            background-color: #f8d7da !important;
            color: #721c24 !important;
            font-weight: bold;
            text-align: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .section-title {
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-top: 30px;
            margin-bottom: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Print/PDF Optimization */
        @media print {
            @page { margin: 15mm; size: A4; }
            body { background: white; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .report-container { 
                box-shadow: none; border: none; padding: 0; margin: 0; width: 100%;
            }
            a { text-decoration: none; color: #000; }
            a[href]:after { content: none !important; } 
        }
    </style>
</head>
<body>
    
    <?php Helper::displayFlash(); ?>

    <div class="no-print" style="max-width: 1000px; margin: 20px auto; display:flex; justify-content:space-between; align-items:center;">
        <a href="index.php" class="btn" style="width:auto; background:transparent; border:1px solid var(--border); color:var(--text-main);">&larr; Back</a>
        
        <?php if($is_finalized): ?>
            <button onclick="window.print()" class="btn" style="width:auto; display:flex; gap:8px; align-items:center; background:var(--primary); color:white;">
                <span class="material-symbols-outlined">print</span> Print / Save as PDF
            </button>
        <?php else: ?>
             <span style="color:var(--text-muted); font-size:0.9rem;">(Set status to enable export)</span>
        <?php endif; ?>
    </div>

    <div class="report-container">
        
        <div style="text-align:center; margin-bottom: 40px; border-bottom: 2px solid #000; padding-bottom: 20px;">
            <h1 style="margin:0; font-size:24px; text-transform: uppercase; letter-spacing: 1px;">Smoke Test Report</h1>
            <p style="margin:5px 0 0; color:#555;">Beam SOHO Test Track System</p>
        </div>

        <table style="width:100%; margin-bottom: 20px; border:none; font-size: 1rem;">
            <tr>
                <td style="padding:5px; border:none; width: 50%;"><strong>Printer Model:</strong> <?= htmlspecialchars($info['model_name']) ?></td>
                <td style="padding:5px; border:none; width: 50%;"><strong>Date:</strong> <?= date('d M Y', strtotime($info['task_date'])) ?></td>
            </tr>
            <tr>
                <td style="padding:5px; border:none;"><strong>Firmware Ver:</strong> <?= htmlspecialchars($info['fw_version_current']) ?></td>
                <td style="padding:5px; border:none;"><strong>Type:</strong> <?= htmlspecialchars($info['fw_type']) ?></td>
            </tr>
            <tr>
                <td style="padding:5px; border:none; padding-top:15px;" colspan="2">
                    <strong>Overall Status:</strong> 
                    <?php if($info['overall_status'] == 'Pass'): ?>
                        <span style="color:#155724; font-weight:800; font-size: 1.1rem; text-transform:uppercase;">PASS</span>
                    <?php elseif($info['overall_status'] == 'Fail'): ?>
                        <span style="color:#721c24; font-weight:800; font-size: 1.1rem; text-transform:uppercase;">FAIL</span>
                    <?php else: ?>
                        <span style="color:orange;">PENDING</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div class="section-title">New Issues Found</div>
        <?php if(!empty($issues)): ?>
            <table class="formal-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Num</th>
                        <th style="width: 90%;">New Issue (JIRA URL)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1; foreach($issues as $issue): ?>
                    <tr>
                        <td style="text-align:center;"><?= $count++ ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($issue['jira_url']) ?>" style="color:#0056b3; text-decoration:underline;">
                                <?= htmlspecialchars($issue['jira_url']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="font-style:italic; color:#555; margin-bottom:30px;">No new issues reported.</p>
        <?php endif; ?>

        <div class="section-title">Detailed Smoke Test Results</div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Case ID</th>
                    <th style="width: 35%;">Test Case Title</th>
                    <th style="width: 15%; text-align:center;">Result</th>
                    <th style="width: 20%;">Tested By</th> <th style="width: 15%;">Bug (JIRA)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td style="font-family:monospace;"><?= htmlspecialchars($row['case_code']) ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    
                    <?php if($row['status'] == 'Pass'): ?>
                        <td class="cell-pass">PASS</td>
                    <?php else: ?>
                        <td class="cell-fail">FAIL</td>
                    <?php endif; ?>
                    
                    <td><?= htmlspecialchars($row['tester_name'] ?? 'Pending') ?></td>

                    <td style="font-size:0.85rem;">
                        <?php if($row['jira_url']): ?>
                            <a href="<?= htmlspecialchars($row['jira_url']) ?>" style="color:#0056b3; text-decoration:underline;">Link</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 50px; font-size: 0.8rem; color: #777; border-top: 1px solid #eee; padding-top: 10px;">
            <p>Generated by Track Manager on <?= date('Y-m-d H:i:s') ?></p>
        </div>
    </div>

    <div class="no-print" style="max-width: 1000px; margin: 0 auto 50px; background:var(--bg-surface); border:1px solid var(--border); padding:24px; border-radius:8px;">
        <h3 style="margin-top:0;"><?= $is_finalized ? 'Update Overall Result' : 'Finalize Report' ?></h3>
        
        <form method="POST">
            <div style="display:flex; gap:16px; align-items:center;">
                <select name="overall_status" class="form-control" style="max-width:200px;" required>
                    <option value="" disabled <?= empty($info['overall_status']) || $info['overall_status']=='Pending' ? 'selected' : '' ?>>Select Status...</option>
                    <option value="Pass" <?= $info['overall_status'] == 'Pass' ? 'selected' : '' ?>>Pass</option>
                    <option value="Fail" <?= $info['overall_status'] == 'Fail' ? 'selected' : '' ?>>Fail</option>
                </select>
                <button type="submit" class="btn" style="width:auto;">
                    <?= $is_finalized ? 'Update Result' : 'Finalize & Enable Export' ?>
                </button>
            </div>
        </form>
    </div>

</body>
</html>