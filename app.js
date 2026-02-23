// ==========================================
// 1. THEME ENGINE (LocalStorage)
// ==========================================
const initTheme = () => {
    // Check local storage. If nothing is saved, default to 'light'
    const savedTheme = localStorage.getItem('track-manager-theme') || 'light';
    
    // Apply immediately to the HTML tag
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Update the UI Swatches to show which one is active
    updateThemeUI(savedTheme);
};

const updateThemeUI = (activeTheme) => {
    document.querySelectorAll('.theme-swatch').forEach(swatch => {
        if (swatch.dataset.setTheme === activeTheme) {
            swatch.classList.add('active');
        } else {
            swatch.classList.remove('active');
        }
    });
};

// Run this the millisecond the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initTheme();

    // Listen for swatch clicks
    document.querySelectorAll('.theme-swatch').forEach(swatch => {
        swatch.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevents dropdown from closing when selecting a theme
            
            const selectedTheme = swatch.dataset.setTheme;
            
            // Set HTML attribute and save to LocalStorage
            document.documentElement.setAttribute('data-theme', selectedTheme);
            localStorage.setItem('track-manager-theme', selectedTheme);
            
            // Update the rings around the swatches
            updateThemeUI(selectedTheme);
        });
    });
});

// Immediately invoke initTheme so it runs before the body even finishes rendering
initTheme();

// 2. Flash Message Logic
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('flash-toast');
    if (toast) {
        setTimeout(() => toast.classList.add('show'), 100); // Slide in
        setTimeout(() => {
            toast.classList.remove('show'); // Slide out
            setTimeout(() => toast.remove(), 400); // Remove from DOM
        }, 3000);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. Workflow Toggle ---
    const typeSelect = document.getElementById('testingType');
    
    // Initial Run
    if(typeSelect) {
        toggleWorkflow(typeSelect.value);
        typeSelect.addEventListener('change', (e) => toggleWorkflow(e.target.value));
    }

    function toggleWorkflow(type) {
        const isReg = (type === 'Regression');
        
        // Toggle Global Visibility Classes
        document.querySelectorAll('.smoke-area').forEach(el => {
            if(isReg) el.classList.add('hidden');
            else {
                // Only show if parent card is selected
                const card = el.closest('.printer-card');
                if(card.classList.contains('selected')) el.classList.remove('hidden');
            }
        });

        document.querySelectorAll('.regression-area').forEach(el => {
            if(!isReg) el.classList.add('hidden');
            else {
                // Only show if parent card is selected
                const card = el.closest('.printer-card');
                if(card.classList.contains('selected')) el.classList.remove('hidden');
            }
        });
    }

    // --- 2. Printer Selection Toggle ---
    document.querySelectorAll('.printer-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            const card = e.target.closest('.printer-card');
            const printerId = e.target.value;
            const smokeArea = document.getElementById(`smoke_${printerId}`);
            const regArea = document.getElementById(`reg_${printerId}`);
            const isReg = document.getElementById('testingType').value === 'Regression';

            if (e.target.checked) {
                card.classList.add('selected');
                // Show relevant area
                if (isReg) regArea.classList.remove('hidden');
                else smokeArea.classList.remove('hidden');
            } else {
                card.classList.remove('selected');
                smokeArea.classList.add('hidden');
                regArea.classList.add('hidden');
            }
        });
    });

    // --- 3. Add Tester Logic ---
    window.addTester = (printerId) => {
        const select = document.getElementById(`user_select_${printerId}`);
        const userId = select.value;
        const userName = select.options[select.selectedIndex].getAttribute('data-name');
        const list = document.getElementById(`tester_list_${printerId}`);

        if (!userId) return;

        // Prevent Duplicates
        if (list.querySelector(`input[value="${userId}"]`)) {
            // Optional: Shake animation or toast here
            alert('User already assigned'); 
            return;
        }

        // Determine Role (First one is Main)
        const isFirst = list.children.length === 0;
        const roleState = isFirst ? 'checked' : '';
        const roleLabel = isFirst ? 'Main' : 'Support';
        const rowClass = isFirst ? 'is-main' : '';
        const badgeClass = isFirst ? 'role-main' : 'role-support';

        // Template Literal for new row
        const rowHtml = `
            <div class="tester-row ${rowClass}">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; flex-grow:1;">
                    <input type="radio" name="main_tester[${printerId}]" value="${userId}" ${roleState} 
                           onchange="updateRoles(${printerId})">
                    <span>${userName}</span>
                </label>
                
                <span class="role-badge ${badgeClass}">${roleLabel}</span>
                <input type="hidden" name="assignments[${printerId}][]" value="${userId}">
                
                <button type="button" class="btn" style="width:auto; padding:4px 8px; background:var(--error); color:white;" 
                        onclick="removeTester(this, ${printerId})">✕</button>
            </div>
        `;

        list.insertAdjacentHTML('beforeend', rowHtml);
        select.value = ""; // Reset dropdown
    };

    // --- 4. Remove Tester Logic ---
    window.removeTester = (btn, printerId) => {
        const row = btn.closest('.tester-row');
        const radio = row.querySelector('input[type="radio"]');
        const wasMain = radio.checked;
        
        row.remove();

        // Smart Reassignment: If Main left, promote the first Support
        if (wasMain) {
            const list = document.getElementById(`tester_list_${printerId}`);
            const firstRow = list.querySelector('.tester-row');
            if (firstRow) {
                firstRow.querySelector('input[type="radio"]').checked = true;
                updateRoles(printerId); // Update visuals
            }
        }
    };

    // --- 5. Update Visuals on Radio Change ---
    window.updateRoles = (printerId) => {
        const list = document.getElementById(`tester_list_${printerId}`);
        const rows = list.querySelectorAll('.tester-row');

        rows.forEach(row => {
            const radio = row.querySelector('input[type="radio"]');
            const badge = row.querySelector('.role-badge');
            
            if (radio.checked) {
                row.classList.add('is-main');
                badge.textContent = 'Main';
                badge.className = 'role-badge role-main';
            } else {
                row.classList.remove('is-main');
                badge.textContent = 'Support';
                badge.className = 'role-badge role-support';
            }
        });
    };

    // --- GLOBAL LOADER CONTROLS ---
    window.showLoader = () => {
        const loader = document.getElementById('global-loader-overlay');
        if (loader) loader.classList.add('active');
    };

    window.hideLoader = () => {
        const loader = document.getElementById('global-loader-overlay');
        if (loader) loader.classList.remove('active');
    };

//    Auto-Loader for all standard form submissions:
    document.addEventListener('submit', (e) => {
        if(!e.target.classList.contains('no-loader')) {
            window.showLoader();
        }
    });

// --- PROFILE DROPDOWN LOGIC ---
window.toggleProfileMenu = function(event) {
    event.stopPropagation();
    const menu = document.getElementById('profileMenu');
    const btn = document.getElementById('profileDropdownBtn');
    if (menu) menu.classList.toggle('show');
    if (btn) btn.classList.toggle('active');
};

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('profileMenu');
    const btn = document.getElementById('profileDropdownBtn');
    if (menu && menu.classList.contains('show') && !menu.contains(event.target)) {
        menu.classList.remove('show');
        if (btn) btn.classList.remove('active');
    }
});

});