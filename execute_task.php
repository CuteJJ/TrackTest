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

    /* --- UPDATED PAGE LAYOUT (Full Width for Regression) --- */
    .page-shell { flex: 1; display: flex; overflow: hidden; min-height: 0; }
    .page-shell.smoke-layout { display: grid; grid-template-columns: 1fr 380px; }
    .page-shell.reg-layout { display: flex; flex-direction: column; }
    
    .left-panel { overflow-y: auto; padding: 32px 36px 64px; display: block; flex: 1; }
    
    .lp-heading { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; }
    .lp-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main); line-height: 1.2; margin-bottom: 6px; }
    .lp-sub { font-size: 0.82rem; color: var(--text-muted); }
    
    .role-badge { font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
    .role-main { background: var(--primary); color: white; border: 1px solid var(--primary); }
    .role-support { background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border); }
    .role-lead { background: #f59e0b; color: white; border: 1px solid #f59e0b; }
    
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

    /* --- Test Case Cards --- */
    .case-card { 
        background: var(--bg-surface); border: 1px solid var(--border); border-radius: 10px; 
        margin-bottom: 10px; display: flex; flex-direction: column; overflow: visible !important;
        position: relative; transition: border-color 0.3s ease, opacity 0.3s ease, transform 0.3s ease, max-height 0.3s ease, margin 0.3s ease, padding 0.3s ease; 
    }

    .status-bar {
        display: flex;
        align-items: center;
        padding: 14px 18px;
        gap: 14px;
    }

    .case-identity {
        flex: 1;
        min-width: 0;
    }

    .case-title {
        font-weight: 700;
        color: var(--text-main);
        font-size: 0.9rem;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .case-code {
        font-size: 0.72rem;
        color: var(--text-muted);
        font-family: var(--font-mono);
        margin-top: 2px;
    }

    .status-dropdown {
        width: 130px;
        padding: 8px 28px 8px 12px;
        border: 1px solid var(--border);
        background-color: var(--bg-body);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-muted);
        transition: all 0.2s;
        font-family: var(--font-body);
        outline: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        flex-shrink: 0;
        
        /* Fix alignment: Center the placeholder, left align the list items */
        text-align: center;
        text-align-last: center;
        
        height: 34px;
        box-sizing: border-box;
    }
    
    /* Force dropdown option list to be left aligned */
    .status-dropdown option {
        text-align: left;
    }
    
    .status-dropdown:hover { border-color: var(--primary); }
    
    /* --- Multi-Select Mode --- */
    .select-checkbox {
        display: none;
        width: 18px; height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
        flex-shrink: 0;
    }
    .case-card.select-mode .select-checkbox { display: block; }
    .case-card.select-mode.checked-card {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(2, 136, 209, 0.2);
    }

    /* Floating remove bar */
    .bulk-action-bar {
        position: fixed;
        bottom: -70px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        z-index: 200;
        transition: bottom 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .bulk-action-bar.visible { bottom: 28px; }
    .bulk-action-bar .count-badge {
        background: var(--primary);
        color: white;
        font-size: 0.75rem;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }
    .bulk-action-bar .btn-bulk-remove {
        background: var(--error);
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s;
        font-family: var(--font-body);
    }
    .bulk-action-bar .btn-bulk-remove:hover { filter: brightness(1.1); transform: scale(1.03); }
    .bulk-action-bar .btn-bulk-remove .material-symbols-outlined { font-size: 16px; }
    .bulk-action-bar .btn-cancel-select {
        background: transparent;
        color: var(--text-muted);
        border: 1px solid var(--border);
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        font-family: var(--font-body);
    }
    .bulk-action-bar .btn-cancel-select:hover { border-color: var(--text-muted); color: var(--text-main); }

    .btn-select-mode {
        border: 1px solid var(--border) !important;
        color: var(--text-muted) !important;
        transition: all 0.15s;
    }
    .btn-select-mode:hover { border-color: var(--primary) !important; color: var(--primary) !important; }
    .btn-select-mode.active { 
        border-color: var(--primary) !important; 
        color: var(--primary) !important; 
        background: rgba(2, 136, 209, 0.08) !important; 
    }
    
    .btn-remove-case {
        width: 28px; height: 28px; border: none; background: transparent;
        border-radius: 6px; cursor: pointer; display: flex; align-items: center;
        justify-content: center; flex-shrink: 0; transition: all 0.15s;
        color: var(--text-muted); opacity: 0.4;
    }
    .btn-remove-case:hover {
        background: var(--error-bg); color: var(--error); opacity: 1;
        transform: scale(1.1);
    }
    .btn-remove-case .material-symbols-outlined { font-size: 18px; }
    .status-dropdown option { color: var(--text-main); background-color: var(--bg-surface); }

    /* Card Border States */
    .case-card.status-Pass { border-left: 4px solid var(--success); }
    .case-card.status-Fail { border-left: 4px solid var(--error); }
    .case-card.status-Blocked { border-left: 4px solid var(--blocked); }
    .case-card.status-NA { border-left: 4px solid var(--na); }
    .case-card.status-Pending { border-left: 4px solid var(--text-muted); }

    .case-card.status-Pass .status-dropdown {
        background-color: var(--success); color: white; border-color: var(--success);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    }
    .case-card.status-Fail .status-dropdown {
        background-color: var(--error); color: white; border-color: var(--error);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    }
    .case-card.status-Blocked .status-dropdown {
        background-color: var(--blocked); color: white; border-color: var(--blocked);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    }
    .case-card.status-NA .status-dropdown {
        background-color: var(--na); color: white; border-color: var(--na);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    }
    .case-card.status-Pending .status-dropdown {
        background-color: var(--bg-body); color: var(--text-muted); border-color: var(--border);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    }

    /* Safety net */
    .case-card .case-card .status-dropdown {
        background-color: var(--bg-body) !important; color: var(--text-muted) !important;
        border-color: var(--border) !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
    }
    .case-card .case-card { border-left: none !important; }

    /* --- JIRA Section --- */
    .jira-section {
        padding: 0 18px;
        max-height: 0;
        overflow: visible !important;
        opacity: 0;
        transition: max-height 0.3s ease, padding 0.3s ease, opacity 0.25s ease;
        position: relative;
    }

    /* Show for all statuses */
    .case-card.status-Pass .jira-section,
    .case-card.status-Fail .jira-section,
    .case-card.status-Blocked .jira-section,
    .case-card.status-NA .jira-section,
    .case-card.status-Pending .jira-section {
        max-height: 120px !important;
        padding: 0 18px 14px;
        opacity: 1;
        overflow: visible !important;
    }

    /* --- CLEAN INPUT BOX STYLES --- */
    .jira-input-wrap { 
        position: relative; 
        overflow: visible !important;
        margin-bottom: 0;
    }

    .jira-input { 
        width: 100%; height: 34px; padding: 0 12px;
        border: 1px solid var(--border); border-radius: 6px; 
        font-size: 0.78rem; background: var(--bg-body); color: var(--text-main); 
        font-family: var(--font-body); outline: none; 
        transition: border-color 0.2s, box-shadow 0.2s; 
    }
    .jira-input::placeholder { color: var(--text-muted); font-weight: 500; }
    .jira-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(2, 136, 209, 0.12); }

    /* --- HINT TEXT BELOW INPUT (LEFT ALIGNED) --- */
    .jira-hint-container {
        display: flex;
        justify-content: flex-start;
        margin-top: 4px;
        padding-left: 2px;
    }
    .jira-hint-text {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.65rem;
        color: var(--text-muted);
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .jira-hint-text kbd {
        background: var(--bg-body);
        border: 1px solid var(--border);
        border-radius: 3px;
        padding: 0 6px;
        font-size: 0.6rem;
        font-weight: 700;
        color: var(--text-muted);
        font-family: var(--font-mono);
    }
    .jira-hint-text .material-symbols-outlined {
        font-size: 14px;
        color: var(--text-muted);
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-4px); }
        40%, 80% { transform: translateX(4px); }
    }
    
    .jira-input.input-error {
        animation: shake 0.4s ease; border-color: var(--error) !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15) !important; background: var(--error-bg) !important;
    }

    /* Persistent red state for empty Fail JIRA */
    .jira-input.fail-missing {
        border-color: var(--error) !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
        background: var(--error-bg) !important;
        animation: pulse-red 1.5s ease-in-out infinite;
    }
    .jira-input.fail-missing::placeholder {
        color: var(--error) !important;
        font-weight: 700 !important;
    }

    @keyframes pulse-red {
        0%, 100% { box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2); }
        50% { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.35); }
    }

    .jira-locked { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        background: var(--bg-body); 
        border: 1px solid var(--border); 
        border-radius: 6px; 
        padding: 7px 10px; 
        height: 34px;
        margin-bottom: 4px; 
    }
    .jira-link-text { 
        font-size: 0.78rem; color: var(--primary); text-decoration: none; white-space: nowrap; 
        overflow: hidden; text-overflow: ellipsis; max-width: 90%; font-weight: 500; font-family: var(--font-body); 
    }
    .jira-link-text:hover { text-decoration: underline; }

    /* --- Delete Panel --- */
    .card-content-wrapper { background: var(--bg-surface); width: 100%; height: 100%; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2; position: relative; }
    
    
    /* --- Right Sidebar Overview --- */
    .right-panel { background: var(--bg-surface); border-left: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
    .rp-head { flex-shrink: 0; padding: 18px 24px; border-bottom: 1px solid var(--border); background: var(--bg-body); }
    .rp-head-title { font-size: 0.73rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-main); display: block; margin-bottom: 4px; }
    .rp-body { padding: 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 32px; }

    /* --- REASSIGN MODAL --- */
    .reassign-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .reassign-modal-overlay.active {
        display: flex;
    }
    .reassign-modal {
        background: var(--bg-surface);
        border-radius: 16px;
        padding: 32px;
        max-width: 480px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border: 1px solid var(--border);
    }
    .reassign-modal h2 {
        margin: 0 0 8px 0;
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .reassign-modal p {
        color: var(--text-muted);
        margin-bottom: 20px;
        font-size: 0.9rem;
        line-height: 1.5;
    }
    .reassign-modal .case-info {
        background: var(--bg-body);
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid var(--border);
    }
    .reassign-modal .case-info .label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .reassign-modal .case-info .value {
        font-weight: 700;
        color: var(--text-main);
        margin-top: 4px;
    }
    .reassign-modal .tester-select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.9rem;
        background: var(--bg-body);
        color: var(--text-main);
        font-family: var(--font-body);
        outline: none;
        margin-bottom: 20px;
        cursor: pointer;
    }
    .reassign-modal .tester-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
    }
    .reassign-modal .tester-select option {
        padding: 8px;
    }
    .reassign-modal .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }
    .reassign-modal .modal-actions .btn {
        width: auto;
        padding: 10px 24px;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .reassign-modal .modal-actions .btn-cancel {
        background: var(--bg-body);
        color: var(--text-main);
        border: 1px solid var(--border);
    }
    .reassign-modal .modal-actions .btn-cancel:hover {
        background: var(--border);
    }
    .reassign-modal .modal-actions .btn-reassign {
        background: var(--primary);
        color: white;
    }
    .reassign-modal .modal-actions .btn-reassign:hover {
        background: var(--primary-hover);
    }

    .btn-reassign-case {
        width: 28px; height: 28px; border: none; background: transparent;
        border-radius: 6px; cursor: pointer; display: flex; align-items: center;
        justify-content: center; flex-shrink: 0; transition: all 0.15s;
        color: var(--text-muted); opacity: 0.4;
    }
    .btn-reassign-case:hover {
        background: rgba(59, 130, 246, 0.1); color: var(--primary); opacity: 1;
        transform: scale(1.1);
    }
    .btn-reassign-case .material-symbols-outlined { font-size: 18px; }

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

    /* --- CUSTOM TOOLTIP --- */
    #custom-tooltip {
        position: fixed;
        background: #1e293b;
        color: #f1f5f9;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        max-width: 280px;
        width: max-content;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
        z-index: 999999 !important;
        border: 1px solid rgba(255,255,255,0.08);
        line-height: 1.5;
        font-family: var(--font-body);
    }
    #custom-tooltip.visible {
        opacity: 1;
    }
    #custom-tooltip .tooltip-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }
    #custom-tooltip .tooltip-row:last-child {
        margin-bottom: 0;
    }
    #custom-tooltip .tooltip-label {
        color: #94a3b8;
        font-size: 0.65rem;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.05em;
        margin-right: 16px;
    }
    #custom-tooltip .tooltip-value {
        font-weight: 600;
        text-align: right;
    }

    /* Extra safety for all tooltip containers */
    .jira-enter-hint,
    .jira-enter-hint *,
    .jira-input-wrap,
    .jira-input-wrap * {
        overflow: visible !important;
    }
    
    /* --- REGRESSION EXECUTE ONLY STYLES --- */
    .regression-only-panel {
        background: var(--bg-surface); 
        border: 1px solid var(--border); 
        border-radius: 12px; 
        padding: 30px; 
        text-align: center; 
        margin-top: 20px;
    }
    .regression-only-panel h2 {
        font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 12px;
    }
    .regression-only-panel p {
        color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px;
    }
    .regression-only-panel .btn-testrail {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--bg-body); border: 1px solid var(--primary);
        color: var(--primary); padding: 12px 24px;
        border-radius: 8px; font-weight: 700; font-size: 0.95rem;
        text-decoration: none; transition: all 0.2s;
    }
    .regression-only-panel .btn-testrail:hover {
        background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(2,136,209,0.3);
    }
</style>

<header class="topbar">
    <a href="index.php" class="tb-brand">
        <span class="tb-dot"></span>
        Track Manager
    </a>
    <nav class="tb-crumb">
        <!-- FIX: Changed breadcrumb to point to Assignments -->
        <a href="assignments.php">Assignments</a>
        <span class="tb-crumb-sep">›</span>
        <span class="tb-crumb-cur">Execute Test</span>
    </nav>
</header>

<div class="page-shell <?= ($task_info['testing_type'] == 'Regression') ? 'reg-layout' : 'smoke-layout' ?>">
    <main class="left-panel">
        <div class="lp-heading">
            <div>
                <h1 class="lp-title">
                    <?= htmlspecialchars($task_info['testing_type']) ?> Test:
                    <span style="color:var(--primary);"><?= htmlspecialchars($task_info['model_name']) ?></span>
                </h1>
                <p class="lp-sub">Task ID: #<?= $task_info['id'] ?> &bull; Created: <?= date('M d, Y', strtotime($task_info['created_at'])) ?></p>
            </div>
            <!-- Role badge removed for Regression tasks -->
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

        <?php if ($task_info['testing_type'] == 'Regression'): ?>
            <!-- REGRESSION EXECUTE VIEW: SIMPLE & CLEAN -->
            <div class="regression-only-panel">
                <h2>Execute Regression Test via TestRail</h2>
                <p>This regression test is managed directly in TestRail. Click the button below to open the TestRail run and begin execution.</p>
                
                <div style="margin-top: 10px;">
                    <?php 
                    // Fetch the regression URL for this specific task and printer
                    $stmt_url = $pdo->prepare("SELECT regression_url FROM task_assignments WHERE task_id = ? AND printer_id = ? LIMIT 1");
                    $stmt_url->execute([$task_id, $printer_id]);
                    $reg_url = $stmt_url->fetchColumn();
                    ?>
                    
                    <?php if (!empty($reg_url)): ?>
                        <a href="<?= htmlspecialchars($reg_url) ?>" target="_blank" class="btn-testrail">
                            <span class="material-symbols-outlined">open_in_new</span>
                            Open TestRail Run
                        </a>
                    <?php else: ?>
                        <div style="background: var(--error-bg); border: 1px solid var(--error); color: var(--error); padding: 12px 16px; border-radius: 8px; display: inline-block;">
                            <span class="material-symbols-outlined" style="vertical-align: middle;">warning</span>
                            No TestRail URL assigned to this task.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- SMOKE TEST VIEW: FULL EXECUTION WORKFLOW -->
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
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" id="btnSelectMode" class="btn-mini ghost btn-select-mode" onclick="toggleSelectMode()" style="display: flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">checklist</span> Select
                        </button>
                        <button type="button" class="btn-mini ghost" onclick="unclaimAllCases()" style="border: 1px solid var(--error); color: var(--error); display: flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">remove_circle_outline</span> Remove All
                        </button>
                        <button type="button" class="btn-mini ghost" onclick="passAllCases()" style="border: 1px solid var(--success); color: var(--success); display: flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">done_all</span> Pass All
                        </button>
                    </div>   
                <?php endif; ?>
            </div>

            <?php if (empty($my_cases)): ?>
                <div style="text-align:center; padding:50px 20px; border:2px dashed var(--border); border-radius:12px; color:var(--text-muted); font-weight:500;">
                    No cases selected yet.<br>Select cases from Step 1 above.
                </div>
            <?php else: ?>
                <?php foreach ($my_cases as $case): ?>
                    <?php 
                    $safeStatus = str_replace('/', '', $case['status'] ?? 'Pending');
                    // Ensure empty status becomes 'Pending' for CSS class
                    $statusClass = empty($safeStatus) ? 'Pending' : $safeStatus;
                    $isLead = ($_SESSION['role'] === 'lead' || $_SESSION['role'] === 'admin');
                    ?>
                    <div class="case-card status-<?= $statusClass ?>" id="card_<?= $case['case_id'] ?>">

                        <div class="card-content-wrapper">

                            <div class="status-bar">
                                <input type="checkbox" class="select-checkbox" id="sel_<?= $case['case_id'] ?>" onchange="toggleCardSelect('card_<?= $case['case_id'] ?>', this.checked)">
                                <div class="case-identity">
                                    <div class="case-title"><?= htmlspecialchars($case['title']) ?></div>
                                    <div class="case-code">ID: #<?= htmlspecialchars($case['case_code']) ?></div>
                                </div>
                                <?php if ($isLead): ?>
                                    <button type="button" class="btn-reassign-case tooltip-trigger" data-tip="Reassign to another tester" onclick="openReassignModal(<?= $case['case_id'] ?>, '<?= htmlspecialchars($case['title']) ?>', <?= $case['assigned_to'] ?? 'null' ?>)">
                                        <span class="material-symbols-outlined">swap_horiz</span>
                                    </button>
                                <?php endif; ?>
                                <select class="status-dropdown" onchange="updateStatus(<?= $case['case_id'] ?>, this.value)">
                                    <option value="" <?= ($safeStatus == 'Pending' || empty($safeStatus)) ? 'selected' : '' ?>>Untested</option>
                                    <option value="Pass" <?= ($safeStatus == 'Pass') ? 'selected' : '' ?>>Pass</option>
                                    <option value="Fail" <?= ($safeStatus == 'Fail') ? 'selected' : '' ?>>Fail</option>
                                    <option value="Blocked" <?= ($safeStatus == 'Blocked') ? 'selected' : '' ?>>Blocked</option>
                                    <option value="N/A" <?= ($safeStatus == 'NA') ? 'selected' : '' ?>>N/A</option>
                                </select>
                                <button type="button" class="btn-remove-case" onclick="unclaimCase(event, <?= $case['case_id'] ?>)" title="Remove from list">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>

                            <div class="jira-section">
                                <div id="jira_edit_wrap_<?= $case['case_id'] ?>" class="jira-input-wrap <?= !empty($case['jira_url']) ? 'hidden' : '' ?>">
                                    <input type="text" id="jira_<?= $case['case_id'] ?>" class="jira-input"
                                           placeholder="Attach JIRA URL or Remarks or Comments"
                                           value="<?= htmlspecialchars($case['jira_url'] ?? '') ?>"
                                           data-saved-url="<?= htmlspecialchars($case['jira_url'] ?? '') ?>"
                                           onkeydown="handleJiraKey(event, <?= $case['case_id'] ?>)"
                                           onblur="handleJiraBlur(<?= $case['case_id'] ?>)">
                                </div>
                                
                                <!-- CLEAN HINT TEXT BELOW THE INPUT (LEFT ALIGNED) -->
                                <div class="jira-hint-container">
                                    <span class="jira-hint-text">
                                        <span class="material-symbols-outlined">info</span>
                                        Press <kbd>Enter</kbd> to save
                                    </span>
                                </div>

                                <div id="jira_locked_wrap_<?= $case['case_id'] ?>" class="jira-locked <?= empty($case['jira_url']) ? 'hidden' : '' ?>">
                                    <div id="jira_links_container_<?= $case['case_id'] ?>" style="display:flex; align-items:center; gap:10px; overflow:hidden; flex-wrap:wrap;">
                                        <?php
                                        $urls = array_filter(array_map('trim', explode(',', $case['jira_url'] ?? '')));
                                        foreach ($urls as $url):
                                        ?>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                <span class="material-symbols-outlined" style="font-size:14px; color:var(--primary);">link</span>
                                                <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="jira-link-text"><?= htmlspecialchars($url) ?></a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="icon-btn tooltip-trigger" data-tip="Edit" onclick="unlockJira(<?= $case['case_id'] ?>)" style="width:22px; height:22px; border:none; flex-shrink:0;">
                                        <span class="material-symbols-outlined" style="font-size:13px;">edit</span>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?> 
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php if ($task_info['testing_type'] == 'Smoke'): ?>
    <!-- Right Sidebar is ONLY rendered for Smoke Tests -->
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
    <?php endif; ?>
</div>

<!-- REASSIGN MODAL -->
<div class="reassign-modal-overlay" id="reassignModal">
    <div class="reassign-modal">
        <h2>
            <span class="material-symbols-outlined">swap_horiz</span>
            Reassign Test Case
        </h2>
        <p>Reassign this test case to another tester. The current tester will be unassigned.</p>
        
        <div class="case-info">
            <div class="label">Test Case</div>
            <div class="value" id="reassignCaseTitle">Loading...</div>
            <div class="label" style="margin-top: 8px;">Current Tester</div>
            <div class="value" id="reassignCurrentTester">Unassigned</div>
        </div>
        
        <select class="tester-select" id="reassignTesterSelect">
            <option value="">Select a tester...</option>
            <?php foreach ($testers as $tid => $t): ?>
                <option value="<?= $tid ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <div class="modal-actions">
            <button type="button" class="btn btn-cancel" onclick="closeReassignModal()">
                Cancel
            </button>
            <button type="button" class="btn btn-reassign" onclick="confirmReassign()">
                <span class="material-symbols-outlined">check</span>
                Reassign
            </button>
        </div>
    </div>
</div>

<!-- BULK ACTION BAR -->
<div class="bulk-action-bar" id="bulkActionBar">
    <span class="count-badge" id="selectedCount">0 selected</span>
    <button type="button" class="btn-bulk-remove" onclick="removeSelectedCases()">
        <span class="material-symbols-outlined">delete_sweep</span>
        Remove Selected
    </button>
    <button type="button" class="btn-cancel-select" onclick="toggleSelectMode()">Cancel</button>
</div>
<div id="custom-tooltip"></div>

<script>
    // --- Reassign Variables ---
    let reassignCaseId = null;
    let reassignCurrentTester = null;

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
                if (status === 'Pass') displayColor = '#34d399';
                else if (status === 'Fail') displayColor = '#fb7185';
                else if (status === 'Blocked') displayColor = '#fbbf24';
                else if (status === 'N/A') displayColor = '#a78bfa';
                else displayColor = '#cbd5e1';

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

    // --- 2. REASSIGN MODAL FUNCTIONS ---
    function openReassignModal(caseId, caseTitle, currentTesterId) {
        reassignCaseId = caseId;
        reassignCurrentTester = currentTesterId;
        
        document.getElementById('reassignCaseTitle').textContent = caseTitle;
        
        // Show current tester
        const currentTesterEl = document.getElementById('reassignCurrentTester');
        if (currentTesterId) {
            // Find tester name from the legend items
            const legendItems = document.querySelectorAll('.tester-legend-item');
            let found = false;
            legendItems.forEach(item => {
                const nameSpan = item.querySelector('span:last-child');
                if (nameSpan) {
                    // Check if this is the current tester by comparing with the tester id from data
                    const testerName = nameSpan.textContent.trim();
                    // We need to match by ID - use the color-dot class to identify
                    const dot = item.querySelector('.color-dot');
                    if (dot && dot.className.includes(`tester-bg-${currentTesterId}`)) {
                        currentTesterEl.textContent = testerName;
                        found = true;
                    }
                }
            });
            if (!found) {
                currentTesterEl.textContent = 'Unknown Tester';
            }
        } else {
            currentTesterEl.textContent = 'Unassigned';
        }
        
        // Reset select dropdown
        const select = document.getElementById('reassignTesterSelect');
        select.value = '';
        
        // Show modal
        document.getElementById('reassignModal').classList.add('active');
    }

    function closeReassignModal() {
        document.getElementById('reassignModal').classList.remove('active');
        reassignCaseId = null;
        reassignCurrentTester = null;
    }

    function confirmReassign() {
        const select = document.getElementById('reassignTesterSelect');
        const newTesterId = select.value;
        
        if (!newTesterId) {
            alert('Please select a tester to reassign this case to.');
            return;
        }
        
        if (newTesterId == reassignCurrentTester) {
            alert('This case is already assigned to the selected tester.');
            return;
        }
        
        if (!confirm('Are you sure you want to reassign this case to the selected tester?')) {
            return;
        }
        
        window.showLoader();
        
        // First, unclaim the case from current tester
        const unclaimFormData = new FormData();
        unclaimFormData.append('unclaim_case', '1');
        unclaimFormData.append('case_id', reassignCaseId);
        
        fetch(window.location.href, { method: 'POST', body: unclaimFormData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Now claim for new tester
                    const claimFormData = new FormData();
                    claimFormData.append('claim_case', '1');
                    claimFormData.append('case_id', reassignCaseId);
                    claimFormData.append('force_assign', '1'); // Flag to bypass ownership check
                    
                    return fetch(window.location.href, { method: 'POST', body: claimFormData });
                } else {
                    throw new Error(data.error || 'Failed to unassign current tester');
                }
            })
            .then(res => res.json())
            .then(data => {
                window.hideLoader();
                if (data.success) {
                    if (typeof showDynamicToast === 'function') {
                        showDynamicToast('Case reassigned successfully!', 'success');
                    }
                    closeReassignModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.error || 'Failed to assign new tester');
                }
            })
            .catch(err => {
                window.hideLoader();
                if (typeof showDynamicToast === 'function') {
                    showDynamicToast(err.message || 'Error reassigning case', 'error');
                }
            });
    }

    // Close modal on overlay click
    document.getElementById('reassignModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReassignModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeReassignModal();
        }
    });

    // --- 3. BUSINESS LOGIC (AJAX & UI) ---

    /* --- REFINED FUNCTION: Distinguishes between URL and Plain Text --- */
    function formatAndValidateUrl(inputStr) {
        let rawStr = (inputStr || '').trim();
        if (!rawStr) return { valid: true, url: '' }; 

        // Check if the input looks like plain text (contains a space or is short without dots)
        // If it's a multi-word remark, treat it strictly as plain text, NOT a URL.
        if (rawStr.includes(' ') || (rawStr.length < 15 && !rawStr.includes('.'))) {
            return { valid: true, url: rawStr };
        }

        // If we get here, it might be a URL or a single-word term like "JIRA-123"
        // Prepare URL validation
        let urls = rawStr.split(',').map(s => s.trim()).filter(s => s !== '');
        let validUrls = [];
        let allValid = true;

        for (let u of urls) {
            // If it looks like "JIRA-123", don't force http, just save as-is
            if (preg_match('/^[A-Za-z]+-\d+$/', u)) {
                validUrls.push(u);
                continue;
            }

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

    // Simple regex helper for JS
    function preg_match(regex, str) {
        return new RegExp(regex).test(str);
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
            const input = document.getElementById(`jira_${caseId}`);
            const card = document.getElementById(`card_${caseId}`);

            if (card.classList.contains('status-Fail') && input.value.trim() === '') {
                alert("Please enter a JIRA ID or Remark before saving.");
                triggerInputError(input, "JIRA ID or Remark is required.");
                return;
            }

            if (card.classList.contains('status-Fail') && input.value.trim() !== '') {
                input.classList.remove('fail-missing');
                input.placeholder = "Attach JIRA URL or Remarks or Comments";
                card.removeAttribute('data-incomplete');
            }

            attemptSaveJira(caseId);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            revertJiraEdit(caseId);
        }
    }

    function handleJiraBlur(caseId) {
        const card = document.getElementById(`card_${caseId}`);
        const input = document.getElementById(`jira_${caseId}`);
        if (!input || !card) return;

        if (card.classList.contains('status-Fail') && card.hasAttribute('data-incomplete') && input.value.trim() === '') {
            input.classList.add('fail-missing');
            return;
        }

        if (card.classList.contains('status-Fail') && input.value.trim() !== '') {
            input.classList.remove('fail-missing');
            input.placeholder = "Attach JIRA URL or Remarks or Comments";
            card.removeAttribute('data-incomplete');
        }

        if (input.value.trim() !== '') {
            setTimeout(() => attemptSaveJira(caseId), 200);
            return;
        }

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
            triggerInputError(input, "A JIRA URL or Remark is required to save a Failed test.");
            return;
        }

        // If validation failed because it was a bad URL, show error
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
                            
                            // Only show link icon if it's a URL, plain text shows no icon
                            const isUrl = url.startsWith('http://') || url.startsWith('https://');
                            
                            if (isUrl) {
                                linkWrap.innerHTML = `
                                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">link</span>
                                    <a href="${url}" target="_blank" class="jira-link-text">${url}</a>
                                `;
                            } else {
                                linkWrap.innerHTML = `
                                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--text-muted);">chat_bubble</span>
                                    <span class="jira-link-text" style="color:var(--text-main); cursor:default;">${url}</span>
                                `;
                            }
                            
                            linksContainer.appendChild(linkWrap);
                        });
                    }
                    if (typeof showDynamicToast === 'function') showDynamicToast("Saved successfully.", "success");
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
        const dropdown = card.querySelector('.status-dropdown');
        
        if (status === '') {
            card.classList.remove('status-Pass', 'status-Fail', 'status-Blocked', 'status-NA', 'status-Pending');
            card.classList.add('status-Pending');
            card.removeAttribute('data-incomplete');
            
            if (jiraInput) {
                jiraInput.classList.remove('fail-missing', 'input-error');
                const savedUrl = jiraInput.getAttribute('data-saved-url') || '';
                jiraInput.value = savedUrl;
                jiraInput.placeholder = "Attach JIRA URL or Remarks or Comments";
            }
            
            updateGridCell(caseId, 'Pending');
            saveStatusUpdate(caseId, 'Pending', jiraInput ? jiraInput.getAttribute('data-saved-url') || '' : '');
            return;
        }
        
        const validation = formatAndValidateUrl(jiraInput ? jiraInput.value : '');
        const finalUrl = validation.url;

        if (finalUrl !== '' && !validation.valid) {
            alert("Please enter a valid URL format.");
            const prev = card.className.match(/status-(\w+)/);
            if (prev) { let v = prev[1]; if (v === 'NA') v = 'N/A'; if (v === 'Pending') v = ''; dropdown.value = v; }
            else { dropdown.value = ''; }
            return;
        }

        if (card.classList.contains('status-Fail') && status !== 'Fail') {
            card.removeAttribute('data-incomplete');
            if (jiraInput) {
                jiraInput.classList.remove('fail-missing', 'input-error');
                jiraInput.value = jiraInput.getAttribute('data-saved-url') || '';
                jiraInput.placeholder = "Attach JIRA URL or Remarks or Comments";
            }
        }

        const safeStatus = status.replace('/', '');
        card.classList.remove('status-Pass', 'status-Fail', 'status-Blocked', 'status-NA', 'status-Pending');
        card.classList.add(`status-${safeStatus}`);

        updateGridCell(caseId, status);

        if (status === 'Fail' && finalUrl === '') {
            card.setAttribute('data-incomplete', 'true');
            unlockJira(caseId);
            if (jiraInput) {
                jiraInput.classList.add('fail-missing');
                jiraInput.placeholder = "⚠ JIRA ID is required for Fail status";
            }
            return;
        }

        card.removeAttribute('data-incomplete');
        if (jiraInput) jiraInput.classList.remove('fail-missing', 'input-error');

        saveStatusUpdate(caseId, status, finalUrl);
    }

    function updateGridCell(caseId, status) {
        const gridCell = document.getElementById(`grid_cell_${caseId}`);
        if (!gridCell) return;
        
        let color = 'var(--text-muted)';
        let icon = 'more_horiz';
        
        if (status === 'Pass') { 
            color = 'var(--success)'; 
            icon = 'check'; 
        } else if (status === 'Fail') { 
            color = 'var(--error)'; 
            icon = 'close'; 
        } else if (status === 'Blocked') { 
            color = 'var(--blocked)'; 
            icon = 'block'; 
        } else if (status === 'N/A') { 
            color = 'var(--na)'; 
            icon = 'do_not_disturb_on'; 
        } else if (status === 'Pending' || status === '') {
            color = 'var(--text-muted)';
            icon = 'more_horiz';
        }

        gridCell.setAttribute('data-status', status || 'Pending');
        gridCell.setAttribute('data-color', color);
        const iconSpan = gridCell.querySelector('.material-symbols-outlined');
        if (iconSpan) iconSpan.textContent = icon;
    }

    function saveStatusUpdate(caseId, status, finalUrl) {
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
                const jiraInput = document.getElementById(`jira_${caseId}`);
                if (data.success) {
                    if (jiraInput) jiraInput.setAttribute('data-saved-url', finalUrl);
                    if (typeof showDynamicToast === 'function') {
                        showDynamicToast(`Status updated to ${status || 'Untested'}`, "success");
                    }
                } else {
                    if (typeof showDynamicToast === 'function') {
                        showDynamicToast(data.error || "Error updating status", "error");
                    }
                }
            })
            .catch(err => {
                window.hideLoader();
                if (typeof showDynamicToast === 'function') {
                    showDynamicToast("Network error. Please try again.", "error");
                }
            });
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
                window.hideLoader();
                if (data.success) {
                    const card = document.getElementById(`card_${caseId}`);
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-100%)';
                    card.style.maxHeight = '0';
                    card.style.marginBottom = '0';
                    card.style.padding = '0';
                    card.style.overflow = 'hidden';
                    
                    setTimeout(() => {
                        card.remove();
                        const gridCell = document.getElementById(`grid_cell_${caseId}`);
                        if (gridCell) {
                            gridCell.className = 'grid-cell cell-unassigned';
                            const iconSpan = gridCell.querySelector('.material-symbols-outlined');
                            if (iconSpan) iconSpan.textContent = 'more_horiz';
                            gridCell.setAttribute('data-status', 'Pending');
                            gridCell.setAttribute('data-tester', 'Unassigned');
                        }
                        
                        const remainingCases = document.querySelectorAll('.case-card');
                        if (remainingCases.length === 0) {
                            const leftPanel = document.querySelector('.left-panel');
                            const emptyMsg = document.createElement('div');
                            emptyMsg.style.cssText = 'text-align:center; padding:50px 20px; border:2px dashed var(--border); border-radius:12px; color:var(--text-muted); font-weight:500;';
                            emptyMsg.innerHTML = 'No cases selected yet.<br>Select cases from Step 1 above.';
                            
                            const step2Heading = document.querySelector('.left-panel .section-title:last-of-type');
                            if (step2Heading) {
                                const parent = step2Heading.parentElement;
                                const nextEl = parent.nextElementSibling;
                                if (nextEl && nextEl.querySelector('.btn-select-mode')) {
                                    nextEl.remove();
                                }
                                parent.remove();
                            }
                            
                            const selectionBox = document.querySelector('.selection-box');
                            if (selectionBox) {
                                selectionBox.insertAdjacentElement('afterend', emptyMsg);
                            }
                        }
                        
                        if (typeof showDynamicToast === 'function') {
                            showDynamicToast("Case removed successfully", "success");
                        }
                    }, 400);
                } else {
                    if (typeof showDynamicToast === 'function') {
                        showDynamicToast(data.error || "Could not remove case", "error");
                    }
                }
            })
            .catch(() => {
                window.hideLoader();
                if (typeof showDynamicToast === 'function') {
                    showDynamicToast("Network error", "error");
                }
            });
    }

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
        
        Promise.all(promises).then(() => {
            location.reload(); 
        }).catch(() => {
            window.hideLoader();
            alert("Some claims failed to sync to the server.");
            location.reload();
        });
    }

    let selectModeActive = false;

    function toggleSelectMode() {
        selectModeActive = !selectModeActive;
        const cards = document.querySelectorAll('.case-card');
        const bar = document.getElementById('bulkActionBar');
        const btn = document.getElementById('btnSelectMode');

        cards.forEach(card => {
            card.classList.toggle('select-mode', selectModeActive);
            const cb = card.querySelector('.select-checkbox');
            if (cb) cb.checked = false;
            card.classList.remove('checked-card');
        });

        btn.classList.toggle('active', selectModeActive);
        bar.classList.toggle('visible', false);
        updateSelectedCount();
    }

    function toggleCardSelect(cardId, isChecked) {
        const card = document.getElementById(cardId);
        card.classList.toggle('checked-card', isChecked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.case-card.checked-card');
        const bar = document.getElementById('bulkActionBar');
        const countEl = document.getElementById('selectedCount');
        const count = checked.length;

        countEl.textContent = count === 0 ? '0 selected' : `${count} selected`;
        bar.classList.toggle('visible', selectModeActive && count > 0);
    }

    function removeSelectedCases() {
        const checked = document.querySelectorAll('.case-card.checked-card');
        
        if (checked.length === 0) return;

        if (!confirm(`Are you sure you want to remove ${checked.length} selected case(s)?`)) return;
        
        window.showLoader();
        let promises = [];
        
        checked.forEach(card => {
            const caseId = card.id.replace('card_', '');
            
            const formData = new FormData();
            formData.append('unclaim_case', '1');
            formData.append('case_id', caseId);
            
            promises.push(fetch(window.location.href, { method: 'POST', body: formData }));
        });
        
        Promise.all(promises).then(() => {
            location.reload();
        }).catch(() => {
            window.hideLoader();
            alert("Some removals failed to sync.");
            location.reload();
        });
    }
    
    function unclaimAllCases() {
        const myCards = document.querySelectorAll('.case-card');
        
        if (myCards.length === 0) {
            alert("You have no cases to remove.");
            return;
        }

        if (!confirm(`Are you sure you want to remove all ${myCards.length} case(s) from your list?`)) return;
        
        window.showLoader();
        let promises = [];
        
        myCards.forEach(card => {
            const caseId = card.id.replace('card_', '');
            
            const formData = new FormData();
            formData.append('unclaim_case', '1');
            formData.append('case_id', caseId);
            
            promises.push(fetch(window.location.href, { method: 'POST', body: formData }));
        });
        
        Promise.all(promises).then(() => {
            location.reload();
        }).catch(() => {
            window.hideLoader();
            alert("Some removals failed to sync to the server.");
            location.reload();
        });
    }
    
    function passAllCases() {
        const allCards = document.querySelectorAll('.case-card');
        const pendingCases = [];
        
        allCards.forEach(card => {
            const hasStatusClass = card.className.match(/status-(Pass|Fail|Blocked|NA)/);
            const isPending = card.classList.contains('status-Pending');
            
            if (isPending || (!hasStatusClass && !isPending)) {
                pendingCases.push(card);
            }
        });
        
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

    function claimCase(caseId, btnElement) {
        window.showLoader();
        const formData = new FormData();
        formData.append('claim_case', '1');
        formData.append('case_id', caseId);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                window.hideLoader();
                if (data.success) {
                    if (btnElement) {
                        btnElement.style.transition = 'all 0.3s ease';
                        btnElement.style.transform = 'scale(0)';
                        btnElement.style.opacity = '0';
                        setTimeout(() => btnElement.remove(), 300);
                    }
                    
                    const remainingChips = document.querySelectorAll('.case-chip');
                    if (remainingChips.length === 0) {
                        const chipGrid = document.querySelector('.chip-grid');
                        if (chipGrid) {
                            chipGrid.innerHTML = '<div style="font-size:0.9rem; padding:15px; color:var(--text-muted); font-style:italic; text-align:center; background:var(--bg-body); border-radius:8px;">All cases have been assigned. Good job!</div>';
                        }
                    }
                    
                    location.reload();
                } else {
                    if (typeof showDynamicToast === 'function') {
                        showDynamicToast(data.error || "Could not claim task.", 'error');
                    }
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

    window.addEventListener('beforeunload', function(e) {
        const incomplete = document.querySelectorAll('.case-card[data-incomplete="true"]');
        if (incomplete.length > 0) {
            const message = `You have ${incomplete.length} Failed case(s) without JIRA ID. Are you sure you want to leave?`;
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });

    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (!link) return;

        const incomplete = document.querySelectorAll('.case-card[data-incomplete="true"]');
        if (incomplete.length > 0) {
            e.preventDefault();
            e.stopPropagation();
            alert(`Please complete JIRA ID for ${incomplete.length} Failed case(s) before navigating away.`);
            
            const firstIncomplete = incomplete[0];
            const firstInput = firstIncomplete.querySelector('.jira-input');
            if (firstInput) firstInput.focus();
        }
    }, true);
</script>

</body>
</html>