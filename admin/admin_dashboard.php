<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole(['admin','lead']);

// Top 3 Testers (Based on completed pass/fail cases)
$stmt = $pdo->query("
    SELECT u.full_name, u.pfp_path, COUNT(tr.id) as completed_tasks
    FROM users u
    JOIN test_results tr ON u.id = tr.updated_by
    WHERE tr.status IN ('Pass', 'Fail') AND u.role != 'admin'
    GROUP BY u.id ORDER BY completed_tasks DESC LIMIT 3
");
$top_testers = $stmt->fetchAll();

// Global Chart Data
$chart_sql = "
    SELECT p.model_name,
        SUM(CASE WHEN tr.status = 'Pass' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN tr.status = 'Fail' THEN 1 ELSE 0 END) as failed
    FROM printers p
    LEFT JOIN test_results tr ON p.id = tr.printer_id
    GROUP BY p.id
";
$chart_data = $pdo->query($chart_sql)->fetchAll();

// System Stats
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$taskCount = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$printerCount = $pdo->query("SELECT COUNT(*) FROM printers")->fetchColumn();

$TITLE = "Admin Dashboard | Track Manager";
$ASSET_PATH = "../";
require_once '../configs/header.php';
?>
<style>
    .admin-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
    .stat-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; color: var(--primary); }
    .stat-icon .material-symbols-outlined { font-size: 24px; }
    .stat-info { display: flex; flex-direction: column; }
    .stat-val { font-size: 1.8rem; font-weight: 800; color: var(--text-main); line-height: 1; margin-bottom: 4px; }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    
    .admin-split-layout { display: grid; grid-template-columns: 350px 1fr; gap: 24px; align-items: start; }
    
    .tester-rank { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 8px;}
    .tester-meta { display: flex; align-items: center; gap: 14px; }
    .rank-num { font-weight: 800; color: var(--text-muted); width: 20px; text-align: center; }
    .rank-1 { color: #eab308; } /* Gold */
    .rank-2 { color: #94a3b8; } /* Silver */
    .rank-3 { color: #b45309; } /* Bronze */
    
    @media (max-width: 1024px) {
        .admin-split-layout { grid-template-columns: 1fr; }
        .admin-stats-grid { grid-template-columns: 1fr; }
    }
</style>

<?php require_once 'admin_nav.php'; ?>

<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        
        <div style="margin-bottom: 24px;">
            <h1 style="margin:0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">dashboard</span>
                Admin Overview
            </h1>
        </div>

        <div class="admin-stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">group</span></div>
                <div class="stat-info"><span class="stat-val"><?= $userCount ?></span><span class="stat-label">Total Users</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">assignment</span></div>
                <div class="stat-info"><span class="stat-val"><?= $taskCount ?></span><span class="stat-label">Total Tasks</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">print</span></div>
                <div class="stat-info"><span class="stat-val"><?= $printerCount ?></span><span class="stat-label">Printers</span></div>
            </div>
        </div>

        <div class="admin-split-layout">
            <div class="d-card">
                <div class="d-card-header"><div class="d-card-title"><span class="material-symbols-outlined">military_tech</span> Top Testers</div></div>
                <div class="d-card-body">
                    <?php 
                    $rank = 1;
                    foreach($top_testers as $t): 
                        $pfp = !empty($t['pfp_path']) ? '../' . $t['pfp_path'] : '../imgs/default_pfp.svg';
                    ?>
                        <div class="tester-rank">
                            <div class="tester-meta">
                                <span class="rank-num rank-<?= $rank ?>">#<?= $rank ?></span>
                                <img src="<?= htmlspecialchars($pfp) ?>" style="width:32px; height:32px; border-radius:50%; border: 1px solid var(--border); object-fit:cover;">
                                <div>
                                    <div style="font-weight:700; color:var(--text-main); font-size:0.9rem;"><?= htmlspecialchars($t['full_name']) ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= $t['completed_tasks'] ?> cases resolved</div>
                                </div>
                            </div>
                        </div>
                    <?php $rank++; endforeach; ?>
                    <?php if(empty($top_testers)) echo "<p style='color:var(--text-muted); text-align:center;'>No data yet.</p>"; ?>
                </div>
            </div>

            <div class="d-card">
                <div class="d-card-header"><div class="d-card-title"><span class="material-symbols-outlined">bar_chart</span> Global Pass/Fail Ratio</div></div>
                <div class="d-card-body" style="padding: 20px;"><canvas id="adminChart" style="max-height: 280px;"></canvas></div>
            </div>
        </div>
        
    </div>
</div>

<script>
    const rawData = <?= json_encode($chart_data) ?>;
    const labels = rawData.map(d => d.model_name);
    const passed = rawData.map(d => Number(d.passed));
    const failed = rawData.map(d => Number(d.failed));

    new Chart(document.getElementById('adminChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Passed', data: passed, backgroundColor: '#10b981', borderRadius: 4 },
                { label: 'Failed', data: failed, backgroundColor: '#ef4444', borderRadius: 4 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
    });
</script>
</body>
</html>