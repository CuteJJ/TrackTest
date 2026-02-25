// ==========================================
// 1. THEME ENGINE (LocalStorage & OS Preference)
// ==========================================
const initTheme = () => {
    let savedTheme = localStorage.getItem('track-manager-theme');
    
    // If nothing is saved, detect the OS System Theme
    if (!savedTheme) {
        savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    
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

// ==========================================
//   FLASH MESSAGE (7s Timer & Close)
// ==========================================

// Reusable function to manage the lifecycle of any toast
function initToast(toast) {
    // Slight delay so the DOM paints the starting state before sliding in
    setTimeout(() => toast.classList.add('show'), 50);

    // Timer for auto-dismiss (7000ms = 7 seconds)
    const dismissTimer = setTimeout(() => {
        dismissToast(toast);
    }, 7000);

    // Handle manual close button click
    const closeBtn = toast.querySelector('.toast-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            clearTimeout(dismissTimer); // Stop the 7s timer
            dismissToast(toast); // Dismiss immediately
        });
    }
}

// Reusable function to slide out and remove a toast
function dismissToast(toast) {
    toast.classList.remove('show'); // Slide out to the right
    setTimeout(() => toast.remove(), 400); // Wait for transition, then delete from DOM
}


    function toggleAdminSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('adminOverlay');
        if (sidebar && overlay) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
    }

    function openModal(id) {
        document.getElementById(id).classList.add('show');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    
// 1. Initialize PHP-generated Toasts on Page Load
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.querySelector('.flash-toast:not(.js-dynamic-toast)');
    if (toast) {
        initToast(toast);
    }
});

// 2. AJAX Dynamic Toast Generator (For concurrency bugs, etc.)
function showDynamicToast(message, type = 'error') {
    // Remove old dynamic toast if one is already showing
    const existing = document.querySelector('.flash-toast.js-dynamic-toast');
    if(existing) dismissToast(existing);

    const toast = document.createElement('div');
    toast.className = `flash-toast js-dynamic-toast ${type}`;
    
    // Choose the SVG based on the type
    const iconHtml = type === 'error' 
        ? `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16"><path fill="currentColor" d="M8 1C4.14 1 1 4.14 1 8s3.14 7 7 7s7-3.14 7-7s-3.14-7-7-7m0 13c-3.309 0-6-2.691-6-6s2.691-6 6-6s6 2.691 6 6s-2.691 6-6 6m2.854-8.146L8.708 8l2.146 2.146a.5.5 0 0 1-.708.707L8 8.707l-2.146 2.146a.5.5 0 0 1-.708 0a.5.5 0 0 1 0-.707L7.292 8L5.146 5.854a.5.5 0 0 1 .707-.707l2.146 2.146l2.146-2.146a.5.5 0 0 1 .707.707z" stroke-width="0.2" stroke="currentColor"></path></svg>`
        : `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.3"><path d="m14.25 8.75c-.5 2.5-2.3849 4.85363-5.03069 5.37991-2.64578.5263-5.33066-.7044-6.65903-3.0523-1.32837-2.34784-1.00043-5.28307.81336-7.27989 1.81379-1.99683 4.87636-2.54771 7.37636-1.54771"/><polyline points="5.75 7.75 8.25 10.25 14.25 3.75"/></g></svg>`;

    // Inject the new SaaS HTML structure
    toast.innerHTML = `
        <div class="toast-icon">${iconHtml}</div>
        <div class="toast-content">${message}</div>
        <button class="toast-close" aria-label="Close">
            <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
        </button>
        <div class="toast-progress"></div>
    `;
    
    document.body.appendChild(toast);
    
    // Kick off the lifecycle (slide in, start 7s timer)
    initToast(toast);
}

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