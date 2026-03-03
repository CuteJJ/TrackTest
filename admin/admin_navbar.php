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
                <?php if ($_SESSION['role'] === 'lead'): ?>
                    <div class="profile-menu-divider"></div>
                    <a href="../index.php" class="profile-menu-item"><span class="material-symbols-outlined">dashboard</span> Back to TrackTest</a>
                <?php endif; ?>
                <div class="profile-menu-divider"></div>
                <a href="../logout.php" class="profile-menu-item text-danger"><span class="material-symbols-outlined">logout</span> Sign out</a>
            </div>
        </div>
    </nav>

    <button class="admin-burger" onclick="toggleAdminSidebar()"><span class="material-symbols-outlined">menu</span></button>
    <div class="admin-overlay" id="adminOverlay" onclick="toggleAdminSidebar()"></div>
    <aside class="admin-sidebar" id="adminSidebar">
        <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); margin: 10px 0 10px 16px;">MANAGEMENT</div>
        <a href="admin_dashboard.php" class="admin-nav-item"><span class="material-symbols-outlined">dashboard</span> Home Overview</a>
        <a href="admin_history.php" class="admin-nav-item active"><span class="material-symbols-outlined">history</span> Global History</a>
        <a href="admin_printers.php" class="admin-nav-item"><span class="material-symbols-outlined">print</span> Printers & Cases</a>
        <a href="admin_users.php" class="admin-nav-item"><span class="material-symbols-outlined">group</span> User Directory</a>
    </aside>