<?php
require_once '../configs/db.php';
require_once '../configs/helper.php';
Helper::requireRole('admin');

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,500,0,0" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../app.css">
    <script>
        const savedTheme = localStorage.getItem('track-manager-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>
    <?php Helper::displayFlash(); ?>

    <nav class="navbar">
        <div class="nav-brand"><span class="nav-brand-dot"></span> Admin Center</div>
        <div class="nav-right relative">
            <div class="nav-user-dropdown" onclick="toggleProfileMenu(event)" id="profileDropdownBtn">
                <img src="../<?= htmlspecialchars($_SESSION['pfp_path'] ?? 'imgs/default_pfp.svg') ?>" class="nav-avatar pfp-img">
                <div class="nav-user-info">
                    <div class="nav-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                    <div class="nav-user-role">Administrator</div>
                </div>
            </div>
            <div class="profile-menu" id="profileMenu">
                <div class="profile-menu-header">Signed in as<br><strong><?= htmlspecialchars($_SESSION['username']) ?></strong></div>
                <div class="profile-menu-divider"></div>
                <div class="theme-section">
                    <span class="theme-label">Theme</span>
                    <div class="theme-swatches">
                        <div class="theme-swatch active" data-set-theme="light" style="background: #f0f2f5; border: 1px solid #d1d5db;" title="Light"></div>
                        <div class="theme-swatch" data-set-theme="dark" style="background: #111827; border: 1px solid #374151;" title="Dark"></div>
                        <div class="theme-swatch" data-set-theme="catppuccin" style="background-color: #303446; background-image: url('https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/catppuccin.svg'); background-size: cover; border: 1px solid #51576d;" title="Catppuccin"></div>
                    </div>
                </div>
                <div class="profile-menu-divider"></div>
                <a href="../logout.php" class="profile-menu-item text-danger"><span class="material-symbols-outlined">logout</span> Sign out</a>
            </div>
        </div>
    </nav>

    <button class="admin-burger" onclick="toggleAdminSidebar()"><span class="material-symbols-outlined">menu</span></button>
    <div class="admin-overlay" id="adminOverlay" onclick="toggleAdminSidebar()"></div>
    <aside class="admin-sidebar" id="adminSidebar">
        <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); margin: 10px 0 10px 16px; text-transform: uppercase;">MANAGEMENT</div>
        <a href="admin_dashboard.php" class="admin-nav-item active"><span class="material-symbols-outlined">dashboard</span> Home Overview</a>
        <a href="admin_history.php" class="admin-nav-item"><span class="material-symbols-outlined">history</span> Global History</a>
        <a href="admin_printers.php" class="admin-nav-item"><span class="material-symbols-outlined">print</span> Printers & Cases</a>
        <a href="admin_users.php" class="admin-nav-item"><span class="material-symbols-outlined">group</span> User Directory</a>
    </aside>

    <div class="page-content-scroll">
        <main class="admin-content">
            <h1 style="font-size: 1.6rem; font-weight: 800; margin: 0;">Dashboard Overview</h1>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="d-card"><div class="d-card-body" style="padding: 20px; text-align: center;"><div style="font-size: 2rem; font-weight: 800; color: var(--primary);"><?= $userCount ?></div><div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Users</div></div></div>
                <div class="d-card"><div class="d-card-body" style="padding: 20px; text-align: center;"><div style="font-size: 2rem; font-weight: 800; color: var(--primary);"><?= $taskCount ?></div><div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Tasks</div></div></div>
                <div class="d-card"><div class="d-card-body" style="padding: 20px; text-align: center;"><div style="font-size: 2rem; font-weight: 800; color: var(--primary);"><?= $printerCount ?></div><div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Active Printers</div></div></div>
            </div>

            <div class="dash-split-row" style="grid-template-columns: 1fr 1.5fr;">
                <div class="d-card">
                    <div class="d-card-header"><div class="d-card-title"><span class="material-symbols-outlined">military_tech</span> Top 3 Performers</div></div>
                    <div class="d-card-body" style="padding: 20px; display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach($top_testers as $idx => $tester): ?>
                            <div style="display: flex; align-items: center; gap: 16px; padding: 12px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border);">
                                <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary); width: 30px;">#<?= $idx + 1 ?></div>
                                <img src="../<?= htmlspecialchars($tester['pfp_path'] ?? 'imgs/default_pfp.svg') ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <div style="flex: 1;"><div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($tester['full_name']) ?></div></div>
                                <div style="font-size: 1.2rem; font-weight: 800; color: var(--success);"><?= $tester['completed_tasks'] ?> <span style="font-size: 0.6rem; color: var(--text-muted);">CASES</span></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($top_testers)) echo "<p style='color:var(--text-muted); text-align:center;'>No data yet.</p>"; ?>
                    </div>
                </div>

                <div class="d-card">
                    <div class="d-card-header"><div class="d-card-title"><span class="material-symbols-outlined">bar_chart</span> Global Pass/Fail Ratio</div></div>
                    <div class="d-card-body" style="padding: 20px;"><canvas id="adminChart" style="max-height: 280px;"></canvas></div>
                </div>
            </div>
        </main>
    </div>

    <script src="../app.js"></script>
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
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } }, plugins: { legend: { labels: { color: 'gray' } } } }
        });
    </script>
</body>
</html>