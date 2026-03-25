<style>
    /* ── Fixed Side Navbar Styles ── */
    .main-sidebar {
        position: fixed;
        top: var(--nav-height, 60px);
        left: 0;
        width: 240px;
        bottom: 0;
        background: var(--bg-surface);
        border-right: 1px solid var(--border);
        padding: 20px 0;
        overflow-y: auto;
        z-index: 90;
    }

    .sidebar-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 24px;
        color: var(--text-main);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.15s;
        border-right: 3px solid transparent;
    }

    .sidebar-item:hover,
    .sidebar-item.active {
        background: var(--bg-body);
        color: var(--primary);
        border-right-color: var(--primary);
    }

    .sidebar-item .material-symbols-outlined {
        font-size: 20px;
        color: var(--text-muted);
        transition: color 0.15s;
    }

    .sidebar-item:hover .material-symbols-outlined,
    .sidebar-item.active .material-symbols-outlined {
        color: var(--primary);
    }

    /* Global Page Offset to prevent content hiding under sidebar */
    .page-content-scroll {
        margin-left: 240px;
        width: calc(100% - 240px);
    }

    /* Responsive fallback for mobile */
    @media (max-width: 768px) {
        .main-sidebar {
            display: none;
        }

        .page-content-scroll {
            margin-left: 0;
            width: 100%;
        }
    }
</style>
</head>

<body>

    <?php Helper::displayLoader(); ?>
    <?php Helper::displayFlash(); ?>
    <div id="custom-tooltip"></div>

    <nav class="navbar">
        <div class="nav-brand">
            <span class="nav-brand-dot"></span>
            Track Manager
        </div>

        <div class="nav-right relative" style="display: flex; align-items: center;">
            <div class="nav-user-dropdown" id="profileDropdownBtn" onclick="toggleProfileMenu(event)">
                <div class="nav-avatar" style="background: transparent; border: 1px solid var(--border);">
                    <img src="<?= htmlspecialchars($_SESSION['pfp_path'] ?? 'imgs/default_pfp.svg') ?>" alt="Profile" class="pfp-img">
                </div>
                <div class="nav-user-info">
                    <div class="nav-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                    <div class="nav-user-role"><?= htmlspecialchars($_SESSION['role']) ?></div>
                </div>
                <span class="material-symbols-outlined dropdown-chevron">expand_more</span>
            </div>

            <div class="profile-menu" id="profileMenu">
                <div class="profile-menu-header">
                    Signed in as<br>
                    <strong><?= htmlspecialchars($_SESSION['username'] ?? $_SESSION['full_name']) ?></strong>
                </div>
                <div class="profile-menu-divider"></div>
                <div class="theme-section">
                    <span class="theme-label">Theme</span>
                    <div class="theme-swatches">
                        <div class="theme-swatch tooltip-trigger active" data-tip="Light Mode" data-set-theme="light" style="background: #f0f2f5; border: 1px solid #d1d5db;" title="Light"></div>
                        <div class="theme-swatch tooltip-trigger" data-tip="Dark Mode" data-set-theme="dark" style="background: #111827; border: 1px solid #374151;" title="Dark"></div>
                        <div class="theme-swatch tooltip-trigger" data-tip="Midnight Purple" data-set-theme="midnight" style="background: #0f172a; border: 1px solid #334155;" title="Midnight"></div>
                        <div class="theme-swatch tooltip-trigger" data-tip="Catppuccin Frappé" data-set-theme="catppuccin" style="background-color: #303446; background-image: url('https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/catppuccin.svg'); background-size: cover; border: 1px solid #51576d;" title="Catppuccin Frappé"></div>
                    </div>
                </div>
                <div class="profile-menu-divider"></div>

                <?php if ($_SESSION['role'] === 'lead' || $_SESSION['role'] === 'admin'): ?>
                    <a href="admin/admin_dashboard.php" class="profile-menu-item">
                        <span class="material-symbols-outlined">admin_panel_settings</span> Admin Panel
                    </a>
                    <div class="profile-menu-divider"></div>
                <?php endif; ?>

                <a href="settings.php" class="profile-menu-item">
                    <span class="material-symbols-outlined">manage_accounts</span> Account Settings
                </a>
                <div class="profile-menu-divider"></div>
                <a href="logout.php" class="profile-menu-item text-danger">
                    <span class="material-symbols-outlined">logout</span> Sign out
                </a>
            </div>
        </div>
    </nav>

    <aside class="main-sidebar">
        <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
        <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); margin: 10px 0 10px 24px; letter-spacing: 0.05em;">MAIN MENU</div>

        <a href="index.php" class="sidebar-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">dashboard</span> Dashboard
        </a>

        <?php if ($_SESSION['role'] === 'lead' || $_SESSION['role'] === 'admin'): ?>
            <a href="tasks.php" class="sidebar-item <?= $currentPage === 'tasks.php' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">task</span> Tasks
            </a>
        <?php else: ?>
            <a href="assignments.php" class="sidebar-item <?= $currentPage === 'assignments.php' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">assignment</span> Assignments
            </a>
        <?php endif; ?>

        <a href="reports.php" class="sidebar-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">assessment</span> Reports
        </a>
    </aside>
    <div id="custom-tooltip"></div>