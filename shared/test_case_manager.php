<?php
// shared/test_case_manager.php

require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/helper.php';

Helper::requireManagementRole();

// --- HELPER: Remove empty spaces and parse inputs ---
function parseTcInputs($codes, $titles, $combined) {
    if (!empty(array_filter(array_map('trim', $codes)))) {
        return [$codes, $titles];
    }
    $parsedCodes = [];
    $parsedTitles = [];
    foreach ($combined as $c) {
        $c = trim($c);
        if (empty($c)) {
            $parsedCodes[] = '';
            $parsedTitles[] = '';
            continue;
        }
        if (strpos($c, ' - ') !== false) {
            $parts = explode(' - ', $c, 2);
            $parsedCodes[] = trim($parts[0]);
            $parsedTitles[] = trim($parts[1]);
        } else {
            $parsedCodes[] = $c;
            $parsedTitles[] = $c;
        }
    }
    return [$parsedCodes, $parsedTitles];
}

// --- Handle POST Requests using JS redirects to avoid Headers error ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. ADD TEST CASES TO POOL
    if (isset($_POST['add_test_cases'])) {
        try {
            $codes = $_POST['case_code'] ?? [];
            $titles = $_POST['case_title'] ?? [];
            $combined = $_POST['_atc_combined'] ?? [];

            list($codes, $titles) = parseTcInputs($codes, $titles, $combined);
            
            // REFINED: Check against ALL test cases (Pool and Printer assigned) to prevent duplicates
            $ex_stmt = $pdo->prepare("SELECT case_code FROM test_cases");
            $ex_stmt->execute([]);
            $processed_codes = $ex_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt_tc = $pdo->prepare("INSERT INTO test_cases (printer_model, case_code, title) VALUES (?, ?, ?)");
            $added = 0;
            $attempted = 0;

            for($i=0; $i<count($codes); $i++) {
                $code = trim($codes[$i]);
                if(!empty($code)) {
                    $attempted++;
                    if(!in_array($code, $processed_codes)) {
                        $stmt_tc->execute([NULL, $code, trim($titles[$i])]);
                        $processed_codes[] = $code;
                        $added++;
                    }
                }
            }

            if ($added > 0) {
                Helper::setFlash("$added test case(s) added to the pool.", "success");
            } elseif ($attempted > 0) {
                Helper::setFlash("The selected test case(s) already exist in the pool.", "error");
            } else {
                Helper::setFlash("No valid test cases were entered.", "error");
            }
        } catch (Exception $e) {
            Helper::setFlash("Error adding test cases: " . $e->getMessage(), "error");
        }
        echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
        exit();
    }

    // 2. DELETE TEST CASE (UPDATED MESSAGE LOGIC)
    elseif (isset($_POST['delete_testcase'])) {
        try {
            $tc_id = $_POST['tc_id'];

            // Check if this test case is currently assigned to any printer
            $checkStmt = $pdo->prepare("SELECT printer_model FROM test_cases WHERE id = ?");
            $checkStmt->execute([$tc_id]);
            $printer_model = $checkStmt->fetchColumn();

            if (!empty($printer_model)) {
                // BLOCK: It belongs to a printer
                Helper::setFlash("Error delete test case. This test case is currently assigned to a printer.", "error");
            } else {
                // ALLOW: It is just in the pool
                $pdo->prepare("DELETE FROM test_cases WHERE id = ?")->execute([$tc_id]);
                Helper::setFlash("Test case removed.", "success");
            }
        } catch(Exception $e) {
            Helper::setFlash("Error removing test case. It may be linked to active tests.", "error");
        }
        echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
        exit();
    }

    // 3. EDIT TEST CASE
    elseif (isset($_POST['edit_testcase'])) {
        try {
            $code = trim($_POST['case_code'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $tc_id = $_POST['tc_id'];

            if (empty($code) || empty($title)) {
                Helper::setFlash("Case Code and Title cannot be empty.", "error");
                echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
                exit();
            }

            // REFINED: Check for duplicates against ALL cases (including printer assigned) except itself
            $chk = $pdo->prepare("SELECT id FROM test_cases WHERE case_code = ? AND title = ? AND id != ?");
            $chk->execute([$code, $title, $tc_id]);
            
            if ($chk->fetch()) {
                Helper::setFlash("A test case with ID '#{$code}' and the same title already exists.", "error");
            } else {
                $stmt = $pdo->prepare("UPDATE test_cases SET case_code = ?, title = ? WHERE id = ?");
                $stmt->execute([$code, $title, $tc_id]);
                Helper::setFlash("Test case updated successfully.", "success");
            }
        } catch (Exception $e) {
            Helper::setFlash("Error updating test case.", "error");
        }
        echo '<script>window.location.href = "' . $_SERVER['PHP_SELF'] . '";</script>';
        exit();
    }
}

// --- Fetch Data for the View ---
// REFINED: Default sorted by Case Code Ascending
$all_cases = $pdo->query("SELECT * FROM test_cases ORDER BY case_code ASC")->fetchAll(PDO::FETCH_ASSOC);

$pool_cases = []; $pool_seen = [];
foreach ($all_cases as $tc) {
    $key = trim($tc['case_code']) . '|||' . trim($tc['title']);
    if (!isset($pool_seen[$key])) {
        $pool_seen[$key] = true;
        $pool_cases[] = $tc;
    }
}

$combined_options = []; $seen_pairs = [];
foreach ($all_cases as $tc) {
    $code = trim($tc['case_code']);
    $title = trim($tc['title']);
    $pair_key = $code . '|||' . $title;
    if (!isset($seen_pairs[$pair_key]) && ($code !== '' || $title !== '')) {
        $seen_pairs[$pair_key] = true;
        $display = trim($code . ' - ' . $title, ' -');
        if ($display !== '') $combined_options[$display] = $display;
    }
}
ksort($combined_options);
?>

<style>
    .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { margin:0; font-size: 1.6rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
    .unified-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: visible !important; margin-bottom: 30px; padding-bottom: 8px;}
    .pool-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 12px; }
    .pool-search { position: relative; width: 220px; flex: none; }
    .pool-search input { width: 100%; height: 34px; padding: 0 10px 0 34px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-body); color: var(--text-main); font-size: 0.82rem; font-family: 'Inter', sans-serif; transition: border-color 0.15s; }
    .pool-search input:focus { outline: none; border-color: var(--primary); }
    .pool-search .material-symbols-outlined { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); font-size: 16px; color: var(--text-muted); pointer-events: none; }
    .pool-table { width: 100%; border-collapse: collapse; }
    .pool-table thead th { text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; padding: 10px 12px; border-bottom: 1px solid var(--border); white-space: nowrap; background: var(--bg-body); cursor: pointer; user-select: none; transition: background 0.15s; }
    .pool-table thead th:hover { background: var(--bg-surface); color: var(--primary); }
    .pool-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
    .pool-table tbody tr:hover { background: var(--bg-surface); }
    .pool-table tbody tr:last-child { border-bottom: none; }
    .pool-table td { padding: 10px 12px; font-size: 0.85rem; color: var(--text-main); vertical-align: middle; }
    .pool-table .code-col { font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; font-weight: 600; color: var(--primary); white-space: nowrap; }
    .pool-table .actions-col { display: flex; gap: 6px; justify-content: flex-end; white-space: nowrap; }
    .pool-empty { text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 0.9rem; font-style: italic; }
    .dynamic-tc-row { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; padding: 8px; background: var(--bg-body); border-radius: 8px; border: 1px dashed var(--border); }
    .dynamic-tc-row input { flex: 1; margin: 0 !important; }
    .dynamic-tc-row .btn-remove { background: var(--error-bg); color: var(--error); border: none; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .dynamic-tc-row .btn-remove:hover { background: var(--error); color: white; }
    .tc-combined-wrapper { flex: 1; min-width: 0; }
    .tc-combined-wrapper .enh-trigger { height: 42px !important; min-height: 42px !important; border-radius: 6px !important; }
    .tc-combined-wrapper .enh-dropdown { width: 100%; }
    
    /* --- REFINED MODAL LAYOUT FOR ADD TEST CASES --- */
    .refined-modal-body { display: flex; flex-direction: column; max-height: 75vh; padding: 0 !important; overflow: hidden !important; }
    .refined-modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--bg-surface); border-radius: 16px 16px 0 0; }
    .refined-modal-content { flex: 1; overflow: hidden; display: flex; flex-direction: column; padding: 20px 24px; background: var(--bg-surface); }
    .refined-scroll-area { flex: 1; overflow-y: auto; padding-right: 8px; margin-bottom: 12px; }
    .refined-scroll-area::-webkit-scrollbar { width: 6px; }
    .refined-scroll-area::-webkit-scrollbar-track { background: var(--bg-surface); border-radius: 3px; }
    .refined-scroll-area::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 3px; }
    .refined-scroll-area::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    .refined-modal-footer { flex-shrink: 0; padding: 16px 24px 24px 24px; border-top: 1px solid var(--border); background: var(--bg-surface); border-radius: 0 0 16px 16px; display: flex; gap: 10px; align-items: center; }
    .refined-modal-footer .btn { flex: 1; }

    #dynamicAtcList { max-height: 310px; overflow-y: auto !important; border: 1px solid var(--border); border-radius: 8px; padding: 8px !important; }
    #dynamicAtcList::-webkit-scrollbar { width: 6px; }
    #dynamicAtcList::-webkit-scrollbar-track { background: var(--bg-surface); border-radius: 3px; }
    #dynamicAtcList::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 3px; }
</style>

<!-- ================== HTML ================== -->
<div class="page-content-scroll">
    <div class="dash-wrapper" style="padding-top: 20px;">
        <div class="page-title-row">
            <h1 class="page-title">
                <span class="material-symbols-outlined" style="font-size: 28px; color: var(--primary);">list_alt</span>
                Test Cases Pool
            </h1>
            <button class="btn" style="width:auto; display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 42px; border-radius: 8px;" onclick="openModal('addTestCasesModal')">
                <span class="material-symbols-outlined" style="font-size: 20px;">add</span> Add Case
            </button>
        </div>

        <div class="unified-card">
            <div class="pool-header">
                <div class="pool-search">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="tcPoolSearch" placeholder="Search by code or title">
                </div>
            </div>
            <div style="max-height: 600px; overflow-y: auto; border-top: 1px solid var(--border);">
                <table class="pool-table" id="tcPoolTable">
                    <thead style="position: sticky; top: 0; background: var(--bg-surface); z-index: 2;">
                        <tr>
                            <th style="width: 140px;" onclick="sortTable(0)">Case Code <span id="sortIcon0">↕</span></th>
                            <th onclick="sortTable(1)">Title <span id="sortIcon1">↕</span></th>
                            <th style="width: 100px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tcTableBody">
                        <?php if(empty($pool_cases)): ?>
                        <tr><td colspan="3"><div class="pool-empty">No test cases yet. Click "Add Case" to create one.</div></td></tr>
                        <?php else: ?>
                            <?php foreach($pool_cases as $tc): ?>
                            <tr data-search="<?= htmlspecialchars(strtolower($tc['case_code'] . ' ' . $tc['title'])) ?>">
                                <!-- REMOVED # PREFIX -->
                                <td class="code-col"><?= htmlspecialchars($tc['case_code']) ?></td>
                                <td><?= htmlspecialchars($tc['title']) ?: '<span style="color:var(--text-muted);font-style:italic;">—</span>' ?></td>
                                <td>
                                    <div class="actions-col">
                                        <button type="button" class="btn-mini ghost" style="padding: 6px;" onclick="openEditTcModal(<?= $tc['id'] ?>, '<?= htmlspecialchars($tc['case_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tc['title'], ENT_QUOTES) ?>')" title="Edit">
                                            <span class="material-symbols-outlined" style="font-size:16px;">edit</span>
                                        </button>
                                        <form method="POST" id="delPoolTc_<?= $tc['id'] ?>" style="display:inline;">
                                            <input type="hidden" name="delete_testcase" value="1">
                                            <input type="hidden" name="tc_id" value="<?= $tc['id'] ?>">
                                            <button type="button" class="btn-mini ghost" style="padding: 6px; color: var(--error);" title="Delete"
                                                    onclick="if(confirm('Delete test case #<?= htmlspecialchars(addslashes($tc['case_code']), ENT_QUOTES) ?> permanently?')){document.getElementById('delPoolTc_<?= $tc['id'] ?>').submit();}">
                                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ======================== ADD TEST CASES TO POOL MODAL (REFINED) ======================== -->
<div class="modal-overlay" id="addTestCasesModal">
    <div class="modal-box" style="max-width: 620px;">
        <div class="modal-header refined-modal-header">
            <h3>Add Test Cases to Pool</h3>
            <button type="button" class="modal-close-btn" onclick="resetAndCloseModal('addTestCasesModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="refined-modal-body" style="padding: 0 !important; overflow: hidden !important;">
            <input type="hidden" name="add_test_cases" value="1">
            
            <div class="refined-modal-content">
                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom: 20px; flex-shrink: 0;">Add test cases to the pool. They will be available to select when creating a test.</p>
                
                <div style="border-top: 1px solid var(--border); padding-top: 16px; flex-shrink: 0;">
                    <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                        <span style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; flex: 0 0 35%;">Case ID</span>
                        <span style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; flex: 1;">Case Name</span>
                        <span style="width: 32px;"></span>
                    </div>
                </div>

                <!-- SCROLLABLE AREA -->
                <div class="refined-scroll-area" id="dynamicAtcList">
                    <?php for ($i = 0; $i < 30; $i++): ?>
                        <div class="dynamic-tc-row" id="atc_row_<?= $i ?>" style="<?= $i === 0 ? '' : 'display:none;' ?>; margin-bottom: 10px; padding: 8px; background: var(--bg-surface); border-radius: 8px; border: 1px solid var(--border);">
                            <input type="text" name="case_code[]" class="form-control" placeholder="e.g. TC01" style="flex: 0 0 35%; margin:0 !important; height: 42px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
                            <input type="text" name="case_title[]" class="form-control" placeholder="e.g. Print Test Page" style="flex: 1; margin:0 !important; height: 42px;">
                            <button type="button" class="btn-remove" onclick="hideAtcRow(<?= $i ?>)" style="flex-shrink: 0;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                            </button>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- ADD ANOTHER BUTTON -->
                <button type="button" class="btn ghost" onclick="addAtcRowDynamic()" style="border: 1px dashed var(--border); color: var(--text-main); background-color: var(--bg-body); width: 100%; margin-top: 12px; flex-shrink: 0;">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span> Add Another Test Case
                </button>
            </div>

            <!-- FIXED FOOTER -->
            <div class="refined-modal-footer">
                <button type="submit" class="btn">Save to Pool</button>
                <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="resetAndCloseModal('addTestCasesModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================== EDIT TEST CASE MODAL ======================== -->
<div class="modal-overlay" id="editTcModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Edit Test Case</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editTcModal')"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="modal-body" style="padding: 0 !important; overflow: hidden !important;">
            <input type="hidden" name="edit_testcase" value="1">
            <input type="hidden" name="tc_id" id="editTcId">
            
            <p style="font-size:0.8rem; color:var(--text-muted); margin: 20px 24px 0 24px;">Update the Case ID and/or Name.</p>
            
            <div style="padding: 20px 24px;">
                <div class="form-group">
                    <input type="text" name="case_code" id="editTcCode" class="form-control" required placeholder=" " style="font-family: 'JetBrains Mono', monospace; font-weight: 600;">
                    <label class="form-label">Case ID</label>
                </div>
                <div class="form-group">
                    <input type="text" name="title" id="editTcTitle" class="form-control" required placeholder=" ">
                    <label class="form-label">Case Name</label>
                </div>
            </div>

            <div class="refined-modal-footer" style="margin-top: 0; border-top: 1px solid var(--border);">
                <button type="submit" class="btn">Save Changes</button>
                <button type="button" class="btn ghost" style="background:transparent; border:1px solid var(--border); color:var(--text-main);" onclick="closeModal('editTcModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ================== JAVASCRIPT ================== -->
<script>
    // --- HELPER: RESET AND CLOSE MODAL ---
    function resetAndCloseModal(modalId) {
        // 1. If it's the Add Test Cases modal
        if(modalId === 'addTestCasesModal') {
            const list = document.getElementById('dynamicAtcList');
            if(list) {
                const rows = list.querySelectorAll('.dynamic-tc-row');
                rows.forEach((row, index) => {
                    if(index === 0) {
                        row.style.display = 'flex';
                        row.querySelectorAll('input').forEach(inp => inp.value = '');
                    } else {
                        row.style.display = 'none';
                        row.querySelectorAll('input').forEach(inp => inp.value = '');
                    }
                });
                // Reset row counter
                atcRowCount = 1;
            }
        }
        // 2. Close the modal
        closeModal(modalId);
    }

    // --- MODAL HELPERS ---
    function openModal(id) { document.getElementById(id).classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); document.body.style.overflow = ''; }
    
    function openEditTcModal(tcId, code, title) {
        document.getElementById('editTcId').value = tcId;
        document.getElementById('editTcCode').value = code;
        document.getElementById('editTcTitle').value = title;
        openModal('editTcModal');
    }

    // --- DYNAMIC ROWS ---
    var atcRowCount = 1;
    function addAtcRowDynamic() { 
        if (atcRowCount < 30) { 
            document.getElementById('atc_row_' + atcRowCount).style.display = 'flex'; 
            atcRowCount++; 
            var list = document.getElementById('dynamicAtcList'); 
            list.scrollTop = list.scrollHeight; 
        } 
    }
    function hideAtcRow(i) { 
        document.getElementById('atc_row_' + i).style.display = 'none'; 
        document.getElementById('atc_row_' + i).querySelectorAll('input').forEach(function(inp) { inp.value = ''; }); 
    }

    // --- SEARCH ---
    document.getElementById('tcPoolSearch').addEventListener('input', function() {
        var search = this.value.toLowerCase();
        document.querySelectorAll('#tcTableBody tr[data-search]').forEach(function(row) {
            row.style.display = row.getAttribute('data-search').indexOf(search) !== -1 ? '' : 'none';
        });
    });

    // --- SORTING LOGIC ---
    let sortDirection = { 0: 'asc', 1: 'asc' };

    function sortTable(columnIndex) {
        const tableBody = document.getElementById('tcTableBody');
        const rows = Array.from(tableBody.querySelectorAll('tr'));
        
        // Ignore empty state row
        if (rows.length === 1 && rows[0].querySelector('.pool-empty')) return;

        // Toggle direction
        if (sortDirection[columnIndex] === 'asc') {
            sortDirection[columnIndex] = 'desc';
        } else {
            sortDirection[columnIndex] = 'asc';
        }

        // Update icons
        document.getElementById('sortIcon0').textContent = columnIndex === 0 ? (sortDirection[0] === 'asc' ? '↑' : '↓') : '↕';
        document.getElementById('sortIcon1').textContent = columnIndex === 1 ? (sortDirection[1] === 'asc' ? '↑' : '↓') : '↕';

        // Sort rows
        rows.sort((a, b) => {
            const valA = a.children[columnIndex].textContent.trim().toLowerCase();
            const valB = b.children[columnIndex].textContent.trim().toLowerCase();

            if (valA < valB) return sortDirection[columnIndex] === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection[columnIndex] === 'asc' ? 1 : -1;
            return 0;
        });

        // Re-append sorted rows
        rows.forEach(row => tableBody.appendChild(row));
    }

    // --- MODAL CLOSE ON OVERLAY / ESCAPE ---
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) { if (e.target === overlay) { overlay.classList.remove('active'); document.body.style.overflow = ''; } });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function(overlay) { overlay.classList.remove('active'); });
            document.body.style.overflow = '';
        }
    });
</script>