<?php
require_once 'controllers/TestRunController.php';
$TITLE = "Execute Test | Track Manager";
require_once 'configs/header.php';
?>
<style>
    /* --- Page Specific Layout (No Global Nav) --- */
    .topbar {
        flex-shrink: 0; height: var(--nav-height); background: var(--bg-surface);
        border-bottom: 1px solid var(--border); padding: 0 24px; display: flex;
        align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); z-index: 100;
    }
    .tb-brand { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 700; color: var(--text-main); text-decoration: none; }
    .tb-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }
    .tb-crumb { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted); }
    .tb-crumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
    .tb-crumb a:hover { color: var(--primary); }
    .tb-crumb-sep { color: var(--border); }
    .tb-crumb-cur { color: var(--text-main); font-weight: 600; }

    .page-shell { flex: 1; display: grid; grid-template-columns: 1fr 380px; overflow: hidden; min-height: 0; }
    .left-panel { overflow-y: auto; padding: 32px 36px 64px; display: block; }
    
    .lp-heading { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; }
    .lp-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main); line-height: 1.2; margin-bottom: 6px; }
    .lp-sub { font-size: 0.82rem; color: var(--text-muted); }
    
    .role-badge { font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
    .role-main { background: var(--primary); color: white; border: 1px solid var(--primary); }
    .role-support { background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border); }
    
    /* --- Task Info Cards --- */
    .task-info-grid { display: grid; grid-template-columns: 1.8fr 1.2fr 1.2fr; gap: 16px; margin-bottom: 30px; }
    .info-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02); }
    .info-card.highlight { background: var(--bg-body); border-color: var(--border); }
    .info-label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 8px; letter-spacing: 0.05em; }
    .fw-transition { display: flex; align-items: center; gap: 16px; }
    .fw-ver { display: flex; flex-direction: column; }
    .fw-ver span { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
    .fw-ver strong { font-family: var(--font-mono); font-size: 1.1rem; color: var(--text-main); }
    .fw-ver.new strong { color: var(--primary); font-weight: 700; }
    .fw-ver.old strong { color: var(--text-muted); text-decoration: line-through; opacity: 0.8; font-size: 0.95rem; }
    .mini-row { display: flex; gap: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mini-row strong { font-size: 1rem; color: var(--text-main); font-weight: 600; font-family: var(--font-body); }

    /* --- Step 1: Selection Grid --- */
    .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0 0 12px; }
    .section-sub { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px; display: block; }
    .selection-box { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 30px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02); }
    .chip-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
    .case-chip { padding: 8px 14px; border-radius: 20px; background: var(--bg-body); border: 1px solid var(--border); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 8px; max-width: 100%; color: var(--text-main); font-family: var(--font-body); }
    .case-chip:hover { border-color: var(--primary); background: var(--bg-surface); color: var(--primary); transform: translateY(-1px); box-shadow: 0 2px 5px rgba(2, 136, 209, 0.1); }

    /* --- Step 2: Test Case Cards --- */
    .case-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 12px; display: flex; flex-direction: column; overflow: hidden; position: relative; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); }
    .case-row { display: flex; align-items: center; padding: 16px 20px; gap: 16px; }
    .status-icon { display: flex; align-items: center; justify-content: center; }
    .case-info { flex-grow: 1; font-family: var(--font-body); }
    .case-title { font-weight: 600; color: var(--text-main); font-size: 0.95rem; line-height: 1.3; }
    .case-code { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; font-family: var(--font-mono); }
    .status-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .status-btn { padding: 8px 16px; border: 1.5px solid var(--border); background: transparent; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); transition: all 0.15s; font-family: var(--font-body); }
    .status-btn:hover { background: var(--bg-body); color: var(--text-main); }
    
    .case-card.status-Pass { border-left: 5px solid var(--success); }
    .case-card.status-Pass .btn-pass { background: var(--success); color: white; border-color: var(--success); }
    .case-card.status-Fail { border-left: 5px solid var(--error); }
    .case-card.status-Fail .btn-fail { background: var(--error); color: white; border-color: var(--error); }
    .case-card.status-Blocked { border-left: 5px solid var(--blocked); }
    .case-card.status-Blocked .btn-blocked { background: var(--blocked); color: white; border-color: var(--blocked); }
    .case-card.status-NA { border-left: 5px solid var(--na); }
    .case-card.status-NA .btn-na { background: var(--na); color: white; border-color: var(--na); }

    /* --- CLEAN JIRA UI STATES --- */
    .jira-box { background: var(--bg-surface); padding: 12px 20px; border-top: 1px dashed var(--border); display: none; border-radius: 0 0 12px 12px; }
    .case-card.status-Fail .jira-box, .case-card.status-Pass .jira-box { display: block; }
    
    .jira-input-wrap { position: relative; }
    .jira-input { 
        width: 98%; height: 36px; padding: 0 0 0 2%; border: 1px solid var(--border); 
        border-radius: 6px; font-size: 0.8rem; background: var(--bg-body); color: var(--text-main); 
        font-family: var(--font-body); outline: none; transition: border-color 0.15s, box-shadow 0.15s; 
    }
    .jira-input:focus { border-color: var(--primary); background: var(--bg-surface); box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1); }
    .jira-enter-hint { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 16px; pointer-events: none; opacity: 0.8; }
    
    /* Input Error State Animation */
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-4px); }
        40%, 80% { transform: translateX(4px); }
    }
    .jira-input.input-error {
        animation: shake 0.4s ease; border-color: var(--error) !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important; background: var(--error-bg) !important;
    }

    .jira-locked { 
        display: flex; align-items: center; justify-content: space-between; 
        background: var(--bg-body); border: 1px solid var(--border); border-radius: 6px; padding: 6px 12px; 
    }
    .jira-link-text { 
        font-size: 0.8rem; color: var(--primary); text-decoration: none; white-space: nowrap; 
        overflow: hidden; text-overflow: ellipsis; max-width: 90%; font-weight: 600; font-family: var(--font-body); 
    }
    .jira-link-text:hover { text-decoration: underline; }

    /* --- Delete Panel --- */
    .card-content-wrapper { background: var(--bg-surface); width: 100%; height: 100%; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2; position: relative; }
    .remove-trigger { position: absolute; top: 0; right: 0; width: 6px; height: 100%; background-color: transparent; cursor: pointer; transition: all 0.2s; z-index: 10; }
    .remove-trigger:hover { background-color: var(--error-bg); width: 10px; }
    .delete-panel { position: absolute; top: 0; right: 0; bottom: 0; width: 80px; background-color: var(--error-bg); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 1; }
    .delete-btn { color: var(--error); background: var(--bg-surface); border-radius: 50%; padding: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); transition: transform 0.15s; }
    .delete-btn:hover { transform: scale(1.15) rotate(10deg); color: white; background: var(--error); }
    .case-card.delete-mode .card-content-wrapper { transform: translateX(-80px); }
    
    /* --- Right Sidebar Overview --- */
    .right-panel { background: var(--bg-surface); border-left: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
    .rp-head { flex-shrink: 0; padding: 18px 24px; border-bottom: 1px solid var(--border); background: var(--bg-body); }
    .rp-head-title { font-size: 0.73rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); display: block; margin-bottom: 4px; }
    .rp-body { padding: 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 32px; }

    <?php
    $colors = ['#0288d1', '#16a34a', '#7c3aed', '#e11d48', '#d97706', '#0d9488'];
    $i = 0;
    $tester_colors = [];
    foreach ($testers as $tid => $info) {
        $col = $colors[$i % count($colors)];
        $tester_colors[$tid] = $col;
        echo ".tester-bg-$tid { background-color: $col !important; color: white; }\n";
        echo ".tester-text-$tid { color: $col; }\n";
        $i++;
    }
    ?>
    .tester-legend-item { display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; padding: 10px 14px; border-radius: 8px; background: var(--bg-body); border: 1px solid var(--border); margin-bottom: 8px; font-weight: 600; font-family: var(--font-body); }
    .color-dot { width: 12px; height: 12px; border-radius: 50%; }
    .mini-badge-main { font-size: 0.65rem; font-weight: 800; color: var(--primary); background: var(--bg-surface); border: 1px solid var(--primary); padding: 2px 8px; border-radius: 12px; font-family: var(--font-body); }
    .calendar-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
    .grid-cell { aspect-ratio: 1; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: help; transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: 1px solid transparent; position: relative; }
    .cell-unassigned { background: var(--bg-body); border: 1px dashed var(--border); color: var(--text-muted); }
    .grid-cell:hover { z-index: 100; transform: scale(1.15); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12); }
    
    .tooltip-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .tooltip-label { color: #94a3b8; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
    .tooltip-value { font-weight: 600; text-align: right; }
    .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
</style>

<header class="topbar">
    <a href="index.php" class="tb-brand">
        <span class="tb-dot"></span>
        Track Manager
    </a>
    <nav class="tb-crumb">
        <a href="index.php">Dashboard</a>
        <span class="tb-crumb-sep">›</span>
        <span class="tb-crumb-cur">Execute Test</span>
    </nav>
</header>

<div class="page-shell">
    <main class="left-panel">
        <div class="lp-heading">
            <div>
                <h1 class="lp-title">
                    <?= htmlspecialchars($task_info['testing_type']) ?> Test:
                    <span style="color:var(--primary);"><?= htmlspecialchars($task_info['model_name']) ?></span>
                </h1>
                <p class="lp-sub">Task ID: #<?= $task_info['id'] ?> &bull; Created: <?= date('M d, Y', strtotime($task_info['created_at'])) ?></p>
            </div>
            <span class="role-badge <?= ($user_role_label == 'Main') ? 'role-main' : 'role-support' ?>">
                Role: <?= htmlspecialchars($user_role_label) ?>
            </span>
        </div>

        <div class="task-info-grid">
            <div class="info-card highlight">
                <span class="info-label">Firmware Upgrade Path</span>
                <div class="fw-transition">
                    <div class="fw-ver old">
                        <span>From</span>
                        <strong><?= htmlspecialchars($task_info['fw_version_prev']) ?></strong>
                    </div>
                    <div class="fw-arrow"><span class="material-symbols-outlined" style="color:var(--text-muted);">arrow_right_alt</span></div>
                    <div class="fw-ver new">
                        <span>To (Target)</span>
                        <strong><?= htmlspecialchars($task_info['fw_version_current']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="mini-row">
                    <div>
                        <span class="info-label">Recovery FW</span><br>
                        <strong style="font-family: var(--font-mono); color: var(--error);"><?= htmlspecialchars($task_info['fw_version_rec']) ?></strong>
                    </div>
                    <div>
                        <span class="info-label">FW Type</span><br>
                        <strong><?= htmlspecialchars($task_info['fw_type']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="mini-row">
                    <div>
                        <span class="info-label">Task Date</span><br>
                        <strong><?= date('M d', strtotime($task_info['task_date'])) ?></strong>
                    </div>
                    <div>
                        <span class="info-label">Due Date</span><br>
                        <strong style="color: var(--primary);"><?= date('M d', strtotime($task_info['due_date'])) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($_SESSION['role'] !== 'lead' && $_SESSION['role'] !== 'admin'): ?>
            <div class="selection-box">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                    <div>
                        <h3 class="section-title" style="margin-bottom: 4px;">Step 1: Select Cases to Execute</h3>
                        <span class="section-sub" style="margin-bottom: 0;">Click a case below to add it to your execution list.</span>
                    </div>
                    <?php if (!empty($available_cases)): ?>
                        <button type="button" class="btn-mini ghost" onclick="claimAllCases()" style="border: 1px solid var(--primary); color: var(--primary); display: flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">library_add</span> Claim All
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($available_cases)): ?>
                    <div style="font-size:0.9rem; padding:15px; color:var(--text-muted); font-style:italic; text-align:center; background:var(--bg-body); border-radius:8px;">
                        All cases have been assigned. Good job!
                    </div>
                <?php else: ?>
                    <div class="chip-grid">
                        <?php foreach ($available_cases as $case): ?>
                            <button class="case-chip" data-id="<?= $case['case_id'] ?>" onclick="claimCase(<?= $case['case_id'] ?>, this)" title="ID: <?= $case['case_code'] ?>">
                                <span class="material-symbols-outlined" style="font-size:18px; color:var(--primary); flex-shrink:0;">add_circle</span>
                                <span><?= htmlspecialchars($case['title']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px;">
                <h3 class="section-title" style="margin: 0;">Step 2: My Execution List</h3>
                <?php if (!empty($my_cases)): ?>
                    <button type="button" class="btn-mini ghost" onclick="passAllCases()" style="border: 1px solid var(--success); color: var(--success); display: flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">done_all</span> Pass Pending
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 20px;">
                <h3 class="section-title">Master Evaluation Control</h3>
                <span class="section-sub">You have authority to view, evaluate, or override any case execution across the entire test suite.</span>
            </div>
        <?php endif; ?>

        <?php if (empty($my_cases)): ?>
            <div style="text-align:center; padding:50px 20px; border:2px dashed var(--border); border-radius:12px; color:var(--text-muted); font-weight:500;">
                No cases selected yet.<br>Select cases from Step 1 above.
            </div>
        <?php else: ?>
            <?php foreach ($my_cases as $case): ?>
                <?php $safeStatus = str_replace('/', '', $case['status'] ?? 'Pending'); ?>
                <div class="case-card status-<?= $safeStatus ?>" id="card_<?= $case['case_id'] ?>">
                    <div class="delete-panel" onclick="toggleDeleteMode('card_<?= $case['case_id'] ?>')">
                        <div class="delete-btn" onclick="unclaimCase(event, <?= $case['case_id'] ?>)" title="Reset to Pending">
                            <span class="material-symbols-outlined">delete</span>
                        </div>
                    </div>

                    <div class="card-content-wrapper">
                        <div class="remove-trigger" onclick="toggleDeleteMode('card_<?= $case['case_id'] ?>')"></div>
                        <div class="case-row">
                            <div class="status-icon">
                                <?php if ($safeStatus == 'Pass'): ?>
                                    <span class="material-symbols-outlined" style="color:var(--success); font-size: 28px;">check_circle</span>
                                <?php elseif ($safeStatus == 'Fail'): ?>
                                    <span class="material-symbols-outlined" style="color:var(--error); font-size: 28px;">cancel</span>
                                <?php elseif ($safeStatus == 'Blocked'): ?>
                                    <span class="material-symbols-outlined" style="color:var(--blocked); font-size: 28px;">block</span>
                                <?php elseif ($safeStatus == 'NA'): ?>
                                    <span class="material-symbols-outlined" style="color:var(--na); font-size: 28px;">do_not_disturb_on</span>
                                <?php else: ?>
                                    <span class="material-symbols-outlined" style="color:var(--text-muted); font-size: 28px;">radio_button_unchecked</span>
                                <?php endif; ?>
                            </div>

                            <div class="case-info">
                                <div class="case-title"><?= htmlspecialchars($case['title']) ?></div>
                                <div class="case-code" style="display:flex; gap:10px; align-items:center;">
                                    <span>ID: #<?= htmlspecialchars($case['case_code']) ?></span>

                                    <?php if ($_SESSION['role'] === 'lead' || $_SESSION['role'] === 'admin'): ?>
                                        <span style="display:inline-block; width:4px; height:4px; background:var(--border); border-radius:50%;"></span>
                                        <span style="color:var(--primary); font-family:var(--font-body); font-weight:600; display:flex; align-items:center; gap:4px;">
                                            <span class="material-symbols-outlined" style="font-size:12px;">person</span>
                                            <?= htmlspecialchars($case['assigned_name'] ?? 'Unassigned') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="status-actions">
                                <button type="button" class="status-btn btn-pass" onclick="updateStatus(<?= $case['case_id'] ?>, 'Pass')">Pass</button>
                                <button type="button" class="status-btn btn-fail" onclick="updateStatus(<?= $case['case_id'] ?>, 'Fail')">Fail</button>
                                <button type="button" class="status-btn btn-blocked" onclick="updateStatus(<?= $case['case_id'] ?>, 'Blocked')">Blocked</button>
                                <button type="button" class="status-btn btn-na" onclick="updateStatus(<?= $case['case_id'] ?>, 'N/A')">N/A</button>
                            </div>
                        </div>

                        <div class="jira-box">
                            <div id="jira_edit_wrap_<?= $case['case_id'] ?>" class="jira-input-wrap <?= !empty($case['jira_url']) ? 'hidden' : '' ?>">
                                <input type="text" id="jira_<?= $case['case_id'] ?>" class="jira-input" 
                                       placeholder="Attach JIRA Bug URL... (Add ',' to separate multiple URLs, Enter to save)" 
                                       value="<?= htmlspecialchars($case['jira_url'] ?? '') ?>" 
                                       data-saved-url="<?= htmlspecialchars($case['jira_url'] ?? '') ?>"
                                       onkeydown="handleJiraKey(event, <?= $case['case_id'] ?>)"
                                       onblur="handleJiraBlur(<?= $case['case_id'] ?>)">
                                <span class="material-symbols-outlined jira-enter-hint">keyboard_return</span>
                            </div>
                            <div id="jira_locked_wrap_<?= $case['case_id'] ?>" class="jira-locked <?= empty($case['jira_url']) ? 'hidden' : '' ?>">
                                
                                <div id="jira_links_container_<?= $case['case_id'] ?>" style="display:flex; align-items:center; gap:12px; overflow:hidden; flex-wrap:wrap;">
                                    <?php 
                                    $urls = array_filter(array_map('trim', explode(',', $case['jira_url'] ?? '')));
                                    foreach ($urls as $url): 
                                    ?>
                                        <div style="display:flex; align-items:center; gap:4px;">
                                            <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">link</span>
                                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="jira-link-text"><?= htmlspecialchars($url) ?></a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" class="icon-btn tooltip-trigger" data-tip="Edit URL" onclick="unlockJira(<?= $case['case_id'] ?>)" style="width:24px; height:24px; border:none; flex-shrink:0;">
                                    <span class="material-symbols-outlined" style="font-size: 14px;">edit</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <aside class="right-panel">
        <div class="rp-head">
            <span class="rp-head-title">Testing Overview</span>
        </div>

        <div class="rp-body">
            <div>
                <h4 class="rp-head-title" style="margin-bottom: 12px; color: var(--text-muted);">Team Roster</h4>
                <?php foreach ($testers as $tid => $t): ?>
                    <div class="tester-legend-item">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img src="<?= htmlspecialchars($t['pfp'] ?? 'imgs/default_pfp.svg') ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border);">
                            <div class="color-dot tester-bg-<?= $tid ?>"></div>
                            <span style="color: var(--text-main);"><?= htmlspecialchars($t['name']) ?></span>
                        </div>
                        <?php if ($t['role'] === 'Main'): ?>
                            <span class="mini-badge-main">MAIN</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <h4 class="rp-head-title" style="margin-bottom: 12px; color: var(--text-muted);">Overall Progress</h4>
                <div class="calendar-grid">
                    <?php foreach ($all_cases as $c): ?>
                        <?php
                        $bgClass = $c['assigned_to'] ? "tester-bg-{$c['assigned_to']}" : "cell-unassigned";
                        $icon = match ($c['status']) {
                            'Pass' => 'check',
                            'Fail' => 'close',
                            'Blocked' => 'block',
                            'N/A' => 'do_not_disturb_on',
                            default => 'more_horiz'
                        };
                        $testerName = htmlspecialchars($c['assigned_name'] ?? 'Unassigned');

                        $statusColor = match ($c['status']) {
                            'Pass' => 'var(--success)',
                            'Fail' => 'var(--error)',
                            'Blocked' => 'var(--blocked)',
                            'N/A' => 'var(--na)',
                            default => 'var(--text-muted)'
                        };
                        ?>

                        <div id="grid_cell_<?= $c['case_id'] ?>"
                            class="grid-cell <?= $bgClass ?>"
                            data-code="<?= htmlspecialchars($c['case_code']) ?>"
                            data-title="<?= htmlspecialchars($c['title']) ?>"
                            data-tester="<?= $testerName ?>"
                            data-status="<?= $c['status'] ?>"
                            data-color="<?= $statusColor ?>">
                            <span class="material-symbols-outlined" style="font-size:20px; color: inherit; filter: brightness(2);">
                                <?= $icon ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </aside>
</div>

<script>
    // --- 1. TOOLTIP LOGIC ---
    document.addEventListener('DOMContentLoaded', () => {
        const tooltip = document.getElementById('custom-tooltip');
        if (!tooltip) return;

        // A. Standard Text Tooltips (Team Roster, Edit Buttons)
        document.body.addEventListener('mouseenter', (e) => {
            if (e.target.classList && e.target.classList.contains('tooltip-trigger')) {
                tooltip.textContent = e.target.getAttribute('data-tip');
                tooltip.classList.add('visible');
            }
        }, true);

        document.body.addEventListener('mouseleave', (e) => {
            if (e.target.classList && e.target.classList.contains('tooltip-trigger')) {
                tooltip.classList.remove('visible');
            }
        }, true);

        // B. Rich HTML Tooltips (Calendar Grid)
        const gridCells = document.querySelectorAll('.grid-cell');
        gridCells.forEach(cell => {
            cell.addEventListener('mouseenter', (e) => {
                const code = cell.getAttribute('data-code');
                const title = cell.getAttribute('data-title');
                const tester = cell.getAttribute('data-tester');
                const status = cell.getAttribute('data-status');
                const color = cell.getAttribute('data-color');

                let displayColor = color;
                if (status === 'Pass') displayColor = '#34d399'; // Emerald 400
                else if (status === 'Fail') displayColor = '#fb7185'; // Rose 400
                else if (status === 'Blocked') displayColor = '#fbbf24'; // Amber 400
                else if (status === 'N/A') displayColor = '#a78bfa'; // Violet 400
                else displayColor = '#cbd5e1'; // Slate 300

                tooltip.innerHTML = `
                    <div class="tooltip-row">
                        <span class="tooltip-label">Case ID</span>
                        <span class="tooltip-value" style="font-family: var(--font-mono);">#${code}</span>
                    </div>
                    <div style="margin-bottom:10px; font-size:0.95rem; font-weight:700; line-height:1.4;">${title}</div>
                    <div style="border-top:1px solid rgba(255,255,255,0.1); margin:10px 0;"></div>
                    <div class="tooltip-row">
                        <span class="tooltip-label" style="margin-right: 22px;">Assigned</span>
                        <span class="tooltip-value">${tester}</span>
                    </div>
                    <div class="tooltip-row" style="margin-bottom:0;">
                        <span class="tooltip-label">Status</span>
                        <div class="tooltip-value" style="display:flex; align-items:center; justify-content:flex-end; color:${displayColor}; font-weight:800;">
                            <span class="status-dot" style="background:${color}; border: 1px solid rgba(255,255,255,0.2);"></span>
                            ${status}
                        </div>
                    </div>
                `;
                tooltip.classList.add('visible');
            });

            cell.addEventListener('mouseleave', () => {
                tooltip.classList.remove('visible');
            });
        });

        // C. Global Mouse Move & Out-of-Bounds Flipping
        document.body.addEventListener('mousemove', (e) => {
            if (!tooltip.classList.contains('visible')) return;

            let leftPos = e.clientX + 14;
            let topPos = e.clientY + 14;

            if (leftPos + tooltip.offsetWidth > window.innerWidth) {
                leftPos = e.clientX - tooltip.offsetWidth - 14;
            }
            if (topPos + tooltip.offsetHeight > window.innerHeight) {
                topPos = e.clientY - tooltip.offsetHeight - 14;
            }

            tooltip.style.left = `${leftPos}px`;
            tooltip.style.top = `${topPos}px`;
        }, true);
    });

    // --- 2. BUSINESS LOGIC (AJAX & UI) ---

    // Validates and formats multiple URLs (Auto prepends https:// if missing, allows comma separation)
    function formatAndValidateUrl(inputStr) {
        let rawStr = (inputStr || '').trim();
        if (!rawStr) return { valid: true, url: '' }; 

        let urls = rawStr.split(',').map(s => s.trim()).filter(s => s !== '');
        let validUrls = [];
        let allValid = true;

        for (let u of urls) {
            if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
            try {
                new URL(u);
                validUrls.push(u);
            } catch (e) {
                allValid = false;
                break;
            }
        }
        
        return { valid: allValid, url: validUrls.join(', ') };
    }

    function triggerInputError(inputEl, message) {
        inputEl.classList.remove('input-error');
        void inputEl.offsetWidth; 
        inputEl.classList.add('input-error');
        if(typeof showDynamicToast === 'function') showDynamicToast(message, "error");
        inputEl.focus();
    }

    function handleJiraKey(event, caseId) {
        if (event.key === 'Enter') {
            event.preventDefault();
            attemptSaveJira(caseId);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            revertJiraEdit(caseId);
        }
    }

    function handleJiraBlur(caseId) {
        setTimeout(() => revertJiraEdit(caseId), 200);
    }

    function revertJiraEdit(caseId) {
        const input = document.getElementById(`jira_${caseId}`);
        if (!input) return;

        const savedUrl = input.getAttribute('data-saved-url') || '';
        input.value = savedUrl;
        input.classList.remove('input-error');
        
        if (savedUrl !== '') {
            document.getElementById(`jira_edit_wrap_${caseId}`).classList.add('hidden');
            document.getElementById(`jira_locked_wrap_${caseId}`).classList.remove('hidden');
        }
    }

    function attemptSaveJira(caseId) {
        const input = document.getElementById(`jira_${caseId}`);
        const card = document.getElementById(`card_${caseId}`);
        
        let currentStatus = 'Pending';
        if (card.classList.contains('status-Pass')) currentStatus = 'Pass';
        if (card.classList.contains('status-Fail')) currentStatus = 'Fail';
        if (card.classList.contains('status-Blocked')) currentStatus = 'Blocked';
        if (card.classList.contains('status-NA')) currentStatus = 'N/A';

        const validation = formatAndValidateUrl(input.value);

        if (currentStatus === 'Fail' && validation.url === '') {
            triggerInputError(input, "A JIRA URL is required to save a Failed test.");
            return;
        }

        if (validation.url !== '' && !validation.valid) {
            triggerInputError(input, "Please enter a valid URL.");
            return;
        }

        const finalUrl = validation.url;
        input.value = finalUrl; 
        input.classList.remove('input-error');

        window.showLoader();
        const formData = new FormData();
        formData.append('update_status', '1');
        formData.append('case_id', caseId);
        formData.append('status', currentStatus);
        formData.append('jira_url', finalUrl);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                window.hideLoader();
                if (data.success) {
                    input.setAttribute('data-saved-url', finalUrl); 
                    
                    if (finalUrl !== '') {
                        document.getElementById(`jira_edit_wrap_${caseId}`).classList.add('hidden');
                        document.getElementById(`jira_locked_wrap_${caseId}`).classList.remove('hidden');
                        
                        const linksContainer = document.getElementById(`jira_links_container_${caseId}`);
                        linksContainer.innerHTML = '';
                        
                        const urlArray = finalUrl.split(',').map(s => s.trim()).filter(s => s !== '');
                        urlArray.forEach(url => {
                            const linkWrap = document.createElement('div');
                            linkWrap.style.display = "flex";
                            linkWrap.style.alignItems = "center";
                            linkWrap.style.gap = "4px";
                            linkWrap.innerHTML = `
                                <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">link</span>
                                <a href="${url}" target="_blank" class="jira-link-text">${url}</a>
                            `;
                            linksContainer.appendChild(linkWrap);
                        });
                    }
                    if (typeof showDynamicToast === 'function') showDynamicToast("JIRA URL saved securely.", "success");
                } else {
                    if (typeof showDynamicToast === 'function') showDynamicToast(data.error || "Failed to link JIRA URL.", "error");
                }
            }).catch(() => {
                window.hideLoader();
                if (typeof showDynamicToast === 'function') showDynamicToast("Network error.", "error");
            });
    }

    function unlockJira(caseId) {
        document.getElementById(`jira_locked_wrap_${caseId}`).classList.add('hidden');
        const editWrap = document.getElementById(`jira_edit_wrap_${caseId}`);
        editWrap.classList.remove('hidden');

        const input = document.getElementById(`jira_${caseId}`);
        input.focus();
        const val = input.value;
        input.value = '';
        input.value = val;
    }

    function updateStatus(caseId, status) {
        const card = document.getElementById(`card_${caseId}`);
        const jiraInput = document.getElementById(`jira_${caseId}`);
        
        const validation = formatAndValidateUrl(jiraInput ? jiraInput.value : '');
        
        if (validation.url !== '' && !validation.valid) {
            unlockJira(caseId);
            triggerInputError(jiraInput, "Please fix the invalid URL before updating the status.");
            return;
        }
        
        const finalUrl = validation.url;
        if (jiraInput) {
            jiraInput.value = finalUrl;
            jiraInput.classList.remove('input-error');
        }
        
        const safeStatus = status.replace('/', '');

        card.classList.remove('status-Pass', 'status-Fail', 'status-Blocked', 'status-NA', 'status-Pending');
        card.classList.add(`status-${safeStatus}`);

        const iconDiv = card.querySelector('.status-icon');
        if (status === 'Pass') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--success); font-size: 28px;">check_circle</span>';
        else if (status === 'Fail') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--error); font-size: 28px;">cancel</span>';
        else if (status === 'Blocked') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--blocked); font-size: 28px;">block</span>';
        else if (status === 'N/A') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--na); font-size: 28px;">do_not_disturb_on</span>';

        const gridCell = document.getElementById(`grid_cell_${caseId}`);
        if (gridCell) {
            let color = 'var(--text-muted)'; let icon = 'more_horiz';
            if (status === 'Pass') { color = 'var(--success)'; icon = 'check'; }
            else if (status === 'Fail') { color = 'var(--error)'; icon = 'close'; }
            else if (status === 'Blocked') { color = 'var(--blocked)'; icon = 'block'; }
            else if (status === 'N/A') { color = 'var(--na)'; icon = 'do_not_disturb_on'; }

            gridCell.setAttribute('data-status', status);
            gridCell.setAttribute('data-color', color);
            const iconSpan = gridCell.querySelector('.material-symbols-outlined');
            if (iconSpan) iconSpan.textContent = icon;
        }

        if (status === 'Fail' && finalUrl === '') {
            unlockJira(caseId);
            jiraInput.focus();
            return;
        }

        window.showLoader();
        const formData = new FormData();
        formData.append('update_status', '1');
        formData.append('case_id', caseId);
        formData.append('status', status);
        formData.append('jira_url', finalUrl);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                window.hideLoader();
                if (data.success) {
                    if (jiraInput) jiraInput.setAttribute('data-saved-url', finalUrl);
                } else {
                    if (typeof showDynamicToast === 'function') showDynamicToast("Error saving: " + (data.error || "Unknown error"), 'error');
                }
            })
            .catch(err => {
                window.hideLoader();
                console.error('Fetch error:', err);
            });
    }

    // --- CLAIM ALL CASES ---
    function claimAllCases() {
        if (!confirm("Are you sure you want to claim all available cases?")) return;
        
        const chips = document.querySelectorAll('.case-chip');
        if (chips.length === 0) return;
        
        window.showLoader();
        let promises = [];
        
        chips.forEach(chip => {
            const caseId = chip.getAttribute('data-id');
            if(!caseId) return;

            const formData = new FormData();
            formData.append('claim_case', '1');
            formData.append('case_id', caseId);
            
            promises.push(fetch(window.location.href, { method: 'POST', body: formData }));
        });
        
        // Wait for all cases to sync with server, then reload page
        Promise.all(promises).then(() => {
            location.reload(); 
        }).catch(() => {
            window.hideLoader();
            alert("Some claims failed to sync to the server.");
            location.reload();
        });
    }

    // --- PASS ALL CASES (AUTOFILL) ---
    // --- PASS PENDING CASES (AUTOFILL) ---
    function passAllCases() {
        // Target ONLY cases that currently have the "Pending" status
        const pendingCases = document.querySelectorAll('.case-card.status-Pending');
        
        if (pendingCases.length === 0) {
            alert("You have no pending cases to pass.");
            return;
        }

        if (!confirm(`Are you sure you want to mark ${pendingCases.length} pending case(s) as 'Pass'?`)) return;
        
        window.showLoader();
        let promises = [];
        
        pendingCases.forEach(card => {
            const caseId = card.id.replace('card_', '');
            
            const formData = new FormData();
            formData.append('update_status', '1');
            formData.append('case_id', caseId);
            formData.append('status', 'Pass');
            
            const jiraInput = document.getElementById(`jira_${caseId}`);
            if (jiraInput) formData.append('jira_url', jiraInput.getAttribute('data-saved-url') || '');
            
            promises.push(fetch(window.location.href, { method: 'POST', body: formData }));
        });
        
        Promise.all(promises).then(() => {
            location.reload(); 
        }).catch(() => {
            window.hideLoader();
            alert("Some updates failed to sync to the server.");
            location.reload();
        });
    }

    function toggleDeleteMode(cardId) {
        const card = document.getElementById(cardId);
        card.classList.toggle('delete-mode');
    }

    // --- CLAIM CASE AJAX ---
    function claimCase(caseId, btnElement) {
        window.showLoader();
        const formData = new FormData();
        formData.append('claim_case', '1');
        formData.append('case_id', caseId);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    window.hideLoader();
                    if (typeof showDynamicToast === 'function') showDynamicToast(data.error || "Could not claim task.", 'error');

                    if (btnElement) {
                        btnElement.style.transition = 'all 0.3s ease';
                        btnElement.style.transform = 'scale(0)';
                        btnElement.style.opacity = '0';
                        setTimeout(() => btnElement.remove(), 300);
                    }
                    setTimeout(() => location.reload(), 2500);
                }
            })
            .catch(() => window.hideLoader());
    }

    function unclaimCase(event, caseId) {
        event.stopPropagation();
        if (!confirm("Are you sure you want to remove this case from your list?")) return;

        window.showLoader();
        const formData = new FormData();
        formData.append('unclaim_case', '1');
        formData.append('case_id', caseId);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById(`card_${caseId}`);
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-100%)';
                    setTimeout(() => location.reload(), 300);
                } else {
                    alert("Error: " + (data.error || "Could not remove case"));
                    window.hideLoader();
                }
            })
            .catch(() => window.hideLoader());
    }
</script>
<div id="custom-tooltip" ></div>
</body>
</html>