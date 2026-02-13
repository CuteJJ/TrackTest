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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $overall_status = $_POST['overall_status'];
    $stmt = $pdo->prepare("UPDATE task_assignments SET overall_status = ? WHERE task_id = ? AND printer_id = ?");
    $stmt->execute([$overall_status, $task_id, $printer_id]);
    
    Helper::setFlash("Report finalized as " . $overall_status, "success");
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

$is_finalized = ($info['overall_status'] == 'Pass' || $info['overall_status'] == 'Fail');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Report | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="app.css">
    
    <style>
        /* Report Specific Styles */
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
            margin-top: 20px;
            margin-bottom: 30px;
            font-size: 0.9rem;
            color: #000;
        }

        .formal-table th, .formal-table td {
            border: 1px solid #000; /* Strict Outline */
            padding: 12px;
            text-align: left;
        }

        .formal-table th {
            background-color: #f0f0f0;
            font-weight: 700;
        }

        /* Status Coloring */
        .cell-pass {
            background-color: #d4edda !important; /* Light Green */
            color: #155724;
            font-weight: bold;
            text-align: center;
        }

        .cell-fail {
            background-color: #f8d7da !important; /* Light Red */
            color: #721c24;
            font-weight: bold;
            text-align: center;
        }

        /* Print/PDF Tweaks */
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .report-container { box-shadow: none; border: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>
    
    <?php Helper::displayFlash(); ?>

    <div class="no-print" style="max-width: 1000px; margin: 20px auto; display:flex; justify-content:space-between; align-items:center;">
        <a href="index.php" class="btn" style="width:auto; background:transparent; border:1px solid var(--border); color:var(--text-main);">&larr; Back</a>
        
        <?php if($is_finalized): ?>
            <button onclick="downloadPDF()" class="btn" style="width:auto; display:flex; gap:8px; align-items:center;">
                <span class="material-symbols-outlined">download</span> Export to PDF
            </button>
        <?php else: ?>
             <span style="color:var(--text-muted); font-size:0.9rem;">(Finalize report to enable export)</span>
        <?php endif; ?>
    </div>

    <div class="report-container" id="printable-area">
        
        <div style="text-align:center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px;">
            <h1 style="margin:0; font-size:24px; text-transform: uppercase;">Smoke Test Report</h1>
            <p style="margin:5px 0 0; color:#555;">Beam SOHO Test Track System</p>
        </div>

        <table style="width:100%; margin-bottom: 30px; border:none;">
            <tr>
                <td style="padding:5px; border:none;"><strong>Printer Model:</strong> <?= htmlspecialchars($info['model_name']) ?></td>
                <td style="padding:5px; border:none;"><strong>Date:</strong> <?= date('d M Y', strtotime($info['task_date'])) ?></td>
            </tr>
            <tr>
                <td style="padding:5px; border:none;"><strong>Firmware Ver:</strong> <?= htmlspecialchars($info['fw_version_current']) ?></td>
                <td style="padding:5px; border:none;"><strong>Type:</strong> <?= htmlspecialchars($info['fw_type']) ?></td>
            </tr>
            <tr>
                <td style="padding:5px; border:none; padding-top:15px;" colspan="2">
                    <strong>Overall Status:</strong> 
                    <?php if($info['overall_status'] == 'Pass'): ?>
                        <span style="display:inline-block; padding:4px 12px; background:#d4edda; color:#155724; border:1px solid #155724; border-radius:4px; font-weight:bold;">PASS</span>
                    <?php elseif($info['overall_status'] == 'Fail'): ?>
                        <span style="display:inline-block; padding:4px 12px; background:#f8d7da; color:#721c24; border:1px solid #721c24; border-radius:4px; font-weight:bold;">FAIL</span>
                    <?php else: ?>
                        <span style="color:orange;">PENDING</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h3 style="border-bottom: 1px solid #ccc; padding-bottom: 8px;">Detailed Test Results</h3>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Case ID</th>
                    <th style="width: 35%;">Test Case Title</th>
                    <th style="width: 15%;">Result</th>
                    <th style="width: 20%;">Tested By</th>
                    <th style="width: 15%;">Issue (Jira)</th>
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

                    <td><?= htmlspecialchars($row['tester_name'] ?? 'N/A') ?></td>
                    
                    <td style="font-size:0.85rem;">
                        <?php if($row['jira_url']): ?>
                            <a href="<?= htmlspecialchars($row['jira_url']) ?>" style="color:blue; text-decoration:underline;">Link</a>
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

    <?php if(!$is_finalized): ?>
    <div class="no-print" style="max-width: 1000px; margin: 0 auto 50px; background:var(--bg-surface); border:1px solid var(--border); padding:24px; border-radius:8px;">
        <h3 style="margin-top:0;">Finalize Report</h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:16px;">Review the results above and select the overall outcome.</p>
        
        <form method="POST">
            <div style="display:flex; gap:16px; align-items:center;">
                <select name="overall_status" class="form-control" style="max-width:200px;" required>
                    <option value="" disabled selected>Select Status...</option>
                    <option value="Pass">Pass</option>
                    <option value="Fail">Fail</option>
                </select>
                <button type="submit" class="btn" style="width:auto;">Finalize & Enable Export</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <script>
        function downloadPDF() {
            const element = document.getElementById('printable-area');
            const filename = 'Report_<?= $info['model_name'] ?>_<?= $info['fw_version_current'] ?>.pdf';
            
            const opt = {
                margin:       [10, 10],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Generate
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>