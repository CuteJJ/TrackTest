<?php require_once 'controllers/TestRunController.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Execute Test | Track Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    
    <style>
        .execution-header {
            background: var(--bg-surface);
            padding: 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }
        
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
            font-size: 0.9rem;
        }
        
        .meta-item label { display: block; color: var(--text-muted); font-size: 0.75rem; margin-bottom: 4px; }
        .meta-item span { font-weight: 600; color: var(--text-main); font-family: monospace; }

        /* Test Case Table Styles */
        .case-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .case-row {
            display: flex;
            align-items: center;
            padding: 16px;
            gap: 16px;
        }

        .case-info { flex-grow: 1; }
        .case-code { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
        .case-title { font-weight: 500; color: var(--text-main); }
        
        .status-actions { display: flex; gap: 8px; }
        
        .status-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        
        /* Active States */
        .case-card.status-Pass { border-left: 5px solid var(--success); }
        .case-card.status-Pass .btn-pass { background: var(--success); color: white; border-color: var(--success); }
        
        .case-card.status-Fail { border-left: 5px solid var(--error); }
        .case-card.status-Fail .btn-fail { background: var(--error); color: white; border-color: var(--error); }

        /* Jira Input Area */
        .jira-box {
            background: var(--bg-body);
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            display: none; /* Hidden by default */
        }
        .case-card.status-Fail .jira-box { display: block; }

        .updater-info {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-left: auto;
            text-align: right;
            min-width: 100px;
        }
    </style>
</head>
<body>

    <div style="max-width: 1000px; margin: 0 auto; width: 100%;">
        
        <div class="execution-header">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1 style="margin:0; font-size:1.5rem;">Execute Smoke Test</h1>
                    <p style="margin:4px 0 0; color:var(--text-muted);">
                        Printer: <strong style="color:var(--primary);"><?= htmlspecialchars($task_info['model_name']) ?></strong>
                    </p>
                </div>
                <a href="index.php" class="btn" style="width:auto; background:transparent; border:1px solid var(--border); color:var(--text-main);">
                    &larr; Back to Dashboard
                </a>
            </div>

            <div class="meta-grid">
                <div class="meta-item">
                    <label>Firmware (Current)</label>
                    <span><?= htmlspecialchars($task_info['fw_version_current']) ?></span>
                </div>
                <div class="meta-item">
                    <label>Firmware Type</label>
                    <span><?= htmlspecialchars($task_info['fw_type']) ?></span>
                </div>
                <div class="meta-item">
                    <label>Date</label>
                    <span><?= date('M d, Y', strtotime($task_info['task_date'])) ?></span>
                </div>
            </div>
        </div>

        <div style="padding: 0 24px 40px;">
            <?php foreach ($test_cases as $case): ?>
            <div class="case-card status-<?= $case['status'] ?? 'Pending' ?>" id="card_<?= $case['case_id'] ?>">
                
                <div class="case-row">
                    <div class="status-icon">
                        <?php if(($case['status']??'') == 'Pass'): ?>
                            <span class="material-symbols-outlined" style="color:var(--success);">check_circle</span>
                        <?php elseif(($case['status']??'') == 'Fail'): ?>
                            <span class="material-symbols-outlined" style="color:var(--error);">cancel</span>
                        <?php else: ?>
                            <span class="material-symbols-outlined" style="color:var(--text-muted);">radio_button_unchecked</span>
                        <?php endif; ?>
                    </div>

                    <div class="case-info">
                        <div class="case-code"><?= htmlspecialchars($case['case_code']) ?></div>
                        <div class="case-title"><?= htmlspecialchars($case['title']) ?></div>
                    </div>

                    <div class="updater-info" id="updater_<?= $case['case_id'] ?>">
                        <?php if($case['updated_by_name']): ?>
                            <div style="display:flex; align-items:center; gap:4px; justify-content:flex-end;">
                                <span class="material-symbols-outlined" style="font-size:14px;">person</span>
                                <?= htmlspecialchars($case['updated_by_name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="status-actions">
                        <button type="button" class="status-btn btn-pass" onclick="updateStatus(<?= $case['case_id'] ?>, 'Pass')">Pass</button>
                        <button type="button" class="status-btn btn-fail" onclick="updateStatus(<?= $case['case_id'] ?>, 'Fail')">Fail</button>
                    </div>
                </div>

                <div class="jira-box">
                    <div style="display:flex; gap:10px;">
                        <input type="text" class="form-control" 
                               id="jira_<?= $case['case_id'] ?>"
                               placeholder="Paste JIRA Issue URL here..." 
                               value="<?= htmlspecialchars($case['jira_url'] ?? '') ?>"
                               onblur="updateStatus(<?= $case['case_id'] ?>, 'Fail')"> </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script>
        function updateStatus(caseId, status) {
            const card = document.getElementById(`card_${caseId}`);
            const jiraInput = document.getElementById(`jira_${caseId}`);
            const jiraUrl = jiraInput.value;

            // 1. Optimistic UI Update (Make it feel instant)
            card.classList.remove('status-Pass', 'status-Fail', 'status-Pending');
            card.classList.add(`status-${status}`);
            
            // Update Icon
            const iconDiv = card.querySelector('.status-icon');
            if(status === 'Pass') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--success);">check_circle</span>';
            if(status === 'Fail') iconDiv.innerHTML = '<span class="material-symbols-outlined" style="color:var(--error);">cancel</span>';

            // 2. Prepare Data
            const formData = new FormData();
            formData.append('update_status', '1');
            formData.append('case_id', caseId);
            formData.append('status', status);
            formData.append('jira_url', jiraUrl);

            // 3. Send to Server
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Update the "Updated By" text
                    const updaterDiv = document.getElementById(`updater_${caseId}`);
                    updaterDiv.innerHTML = `
                        <div style="display:flex; align-items:center; gap:4px; justify-content:flex-end;">
                            <span class="material-symbols-outlined" style="font-size:14px;">person</span>
                            ${data.updater}
                        </div>
                    `;
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>

</body>
</html>