<?php
require_once 'configs/db.php';
require_once 'configs/helper.php';

// Mock session so header/nav don't crash
$_SESSION['user_id'] = 1;
$_SESSION['full_name'] = 'Test Lead';
$_SESSION['role'] = 'lead';
$_SESSION['username'] = 'test.lead';

$TITLE = "UI Sandbox | Track Manager";
require_once 'configs/header.php';
?>
<style>
    /* Restricted width to showcase clean UI limits */
    .sandbox-container { 
        max-width: 900px; 
        margin: 40px auto; 
        padding: 0 24px; 
        display: flex; 
        flex-wrap: wrap; 
        gap: 24px; 
    }
    .sandbox-card { 
        background: var(--bg-surface); 
        border: 1px solid var(--border); 
        border-radius: 16px; 
        padding: 24px; 
        width: 100%; 
        max-width: 360px; /* Forces the dropdown into a realistic sidebar/modal width */
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    .sandbox-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 6px; color: var(--text-main); }
    .sandbox-desc { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 20px; line-height: 1.4; }
    
    /* Extra padding to test scroll & drop-up behavior */
    .scroll-tester { height: 400px; width: 100%; }
</style>

<?php require_once 'configs/nav.php'; ?>

<div class="page-content-scroll">
    
    <div style="padding: 20px 40px 0;">
        <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px;">Component Sandbox</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Testing the refined JIRA/Notion style dropdown constraints.</p>
    </div>

    <div class="sandbox-container">
        
        <div class="sandbox-card">
            <div class="sandbox-title">1. Single Select</div>
            <div class="sandbox-desc">Standard searchable dropdown.</div>
            <?php 
                echo Helper::enhancedDropdown([
                    'name' => 'printer_model',
                    'placeholder' => 'Search printers...',
                    'options' => ['Pixiu MFP', 'Pixiu SFP', 'Flare', 'Ray'],
                    'selected' => 'Flare' 
                ]); 
            ?>
        </div>

        <div class="sandbox-card">
            <div class="sandbox-title">2. Grouped List</div>
            <div class="sandbox-desc">Categorized headers. Great for user assignments.</div>
            <?php 
                echo Helper::enhancedDropdown([
                    'name' => 'assign_to',
                    'placeholder' => 'Assign user...',
                    'options' => [
                        'Leads' => [1 => 'Sarah Jenkins', 2 => 'Michael Chang'],
                        'Testers' => [3 => 'David O', 4 => 'Emma W', 5 => 'Chris P']
                    ]
                ]); 
            ?>
        </div>

        <div class="sandbox-card">
            <div class="sandbox-title">3. Multi-Select (Chips)</div>
            <div class="sandbox-desc">Checkboxes inside, sleek rounded chips outside.</div>
            <?php 
                echo Helper::enhancedDropdown([
                    'name' => 'status_filter[]',
                    'placeholder' => 'Filter by status...',
                    'multiple' => true,
                    'options' => ['Pass', 'Fail', 'Blocked', 'N/A', 'Pending'],
                    'selected' => ['Pass', 'Blocked'] 
                ]); 
            ?>
        </div>

        <div class="sandbox-card">
            <div class="sandbox-title">4. Creatable (Firmware)</div>
            <div class="sandbox-desc">Type a non-existent value to trigger the [+ Add] prompt.</div>
            <?php 
                echo Helper::enhancedDropdown([
                    'name' => 'fw_curr',
                    'placeholder' => 'Select or type...',
                    'creatable' => true,
                    'options' => [
                        'Recent' => ['25.1.0', '25.0.5'],
                        'Older' => ['24.9.0', '24.8.1']
                    ]
                ]); 
            ?>
        </div>

    </div>

    <div class="scroll-tester"></div>
</div>
<script src="app.js"></script>
</body>
</html>