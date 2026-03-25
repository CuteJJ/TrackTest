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

// ==========================================================================
// ENHANCED DROPDOWN CONTROLLER
// ==========================================================================
class EnhancedDropdown {
    constructor(element) {
        this.el = element;
        this.config = JSON.parse(this.el.dataset.config);

        // DOM Elements
        this.trigger = this.el.querySelector('.enh-trigger');
        this.content = this.el.querySelector('.enh-trigger-content');
        this.menu = this.el.querySelector('.enh-menu');
        this.searchInput = this.el.querySelector('.enh-search');
        this.optionsContainer = this.el.querySelector('.enh-options');
        this.options = Array.from(this.el.querySelectorAll('.enh-option'));
        this.hiddenContainer = this.el.querySelector('.enh-hidden-inputs');

        this.createOpt = this.el.querySelector('.enh-create-opt');
        this.createText = this.el.querySelector('.enh-create-text');

        // State
        this.isOpen = false;
        this.selectedValues = this.getInitialSelected();

        this.init();
    }

    init() {
        this.renderTrigger();

        // Events
        this.trigger.addEventListener('click', (e) => {
            // Ignore click if clicking a chip close button
            if (e.target.closest('.enh-chip-close')) return;
            this.toggle();
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.el.contains(e.target)) this.close();
        });

        // Search filtering
        this.searchInput.addEventListener('input', () => this.filterOptions());

        // Option clicks
        this.optionsContainer.addEventListener('click', (e) => {
            const opt = e.target.closest('.enh-option');
            if (opt) this.handleSelect(opt.dataset.value, opt.querySelector('.enh-opt-label').textContent);

            const createOpt = e.target.closest('.enh-create-opt');
            if (createOpt) this.handleCreate();
        });

        // Keyboard support (Enter in search)
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                // If create is visible and active
                if (this.config.creatable && this.createOpt && !this.createOpt.classList.contains('hidden')) {
                    this.handleCreate();
                } else {
                    // Select first visible option
                    const firstVisible = this.options.find(o => !o.classList.contains('hidden'));
                    if (firstVisible) this.handleSelect(firstVisible.dataset.value, firstVisible.querySelector('.enh-opt-label').textContent);
                }
            }
        });
    }

    getInitialSelected() {
        return Array.from(this.hiddenContainer.querySelectorAll('input')).map(inp => ({
            value: inp.value,
            label: this.getLabelForValue(inp.value)
        }));
    }

    getLabelForValue(val) {
        const opt = this.options.find(o => o.dataset.value === val);
        return opt ? opt.querySelector('.enh-opt-label').textContent : val;
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        // Close other open dropdowns first
        document.querySelectorAll('.enh-dropdown').forEach(el => {
            if (el !== this.el) el.classList.remove('open');
            const menu = el.querySelector('.enh-menu');
            if (menu && el !== this.el) {
                menu.classList.add('hidden');
                menu.classList.remove('drop-up'); // Reset position
            }
        });

        this.isOpen = true;
        this.el.classList.add('open');

        // --- SMART POSITIONING LOGIC ---
        // Temporarily remove hidden to calculate actual height
        this.menu.style.visibility = 'hidden';
        this.menu.classList.remove('hidden');

        const triggerRect = this.trigger.getBoundingClientRect();
        const menuHeight = this.menu.offsetHeight;
        const spaceBelow = window.innerHeight - triggerRect.bottom;
        const spaceAbove = triggerRect.top;

        // If it doesn't fit below, AND there is more space above, drop it UP.
        if (spaceBelow < (menuHeight + 20) && spaceAbove > spaceBelow) {
            this.menu.classList.add('drop-up');
        } else {
            this.menu.classList.remove('drop-up');
        }

        // Restore visibility and animate in
        this.menu.style.visibility = '';

        this.searchInput.value = '';
        this.filterOptions();
        setTimeout(() => this.searchInput.focus(), 50);
    }

    close() {
        this.isOpen = false;
        this.el.classList.remove('open');
        this.menu.classList.add('hidden');
    }

    filterOptions() {
        const query = this.searchInput.value.toLowerCase().trim();
        let hasExactMatch = false;
        let visibleCount = 0;

        this.options.forEach(opt => {
            const label = opt.querySelector('.enh-opt-label').textContent.toLowerCase();
            const val = opt.dataset.value.toLowerCase();

            if (label.includes(query) || val.includes(query)) {
                opt.classList.remove('hidden');
                visibleCount++;
                if (label === query || val === query) hasExactMatch = true;
            } else {
                opt.classList.add('hidden');
            }
        });

        // Handle Group Labels visibility
        this.el.querySelectorAll('.enh-optgroup-label').forEach(label => {
            let nextElement = label.nextElementSibling;
            let hasVisibleSiblings = false;
            while (nextElement && nextElement.classList.contains('enh-option')) {
                if (!nextElement.classList.contains('hidden')) { hasVisibleSiblings = true; break; }
                nextElement = nextElement.nextElementSibling;
            }
            label.style.display = hasVisibleSiblings ? 'block' : 'none';
        });

        // Handle Create Option Logic
        if (this.config.creatable && this.createOpt) {
            if (query.length > 0 && !hasExactMatch) {
                this.createOpt.classList.remove('hidden');
                this.createText.textContent = this.searchInput.value;
            } else {
                this.createOpt.classList.add('hidden');
            }
        }
    }

    handleSelect(value, label) {
        const existingIdx = this.selectedValues.findIndex(item => item.value === value);

        if (this.config.multiple) {
            if (existingIdx > -1) {
                this.selectedValues.splice(existingIdx, 1); // Remove
            } else {
                this.selectedValues.push({ value, label }); // Add
            }
            // Keep menu open for multiple, just refocus search
            this.searchInput.focus();
        } else {
            this.selectedValues = [{ value, label }];
            this.close();
        }

        this.updateDOM();
    }

    handleCreate() {
        const newVal = this.searchInput.value.trim();
        if (!newVal) return;

        // Add to DOM as a new option so it persists
        const optHtml = `
            <div class='enh-option' data-value='${newVal}'>
                ${this.config.multiple ? "<div class='enh-checkbox'><span class='material-symbols-outlined'>check</span></div>" : ""}
                <span class='enh-opt-label'>${newVal}</span>
            </div>`;
        this.optionsContainer.insertAdjacentHTML('beforeend', optHtml);
        this.options = Array.from(this.el.querySelectorAll('.enh-option')); // Refresh cache

        this.handleSelect(newVal, newVal);
    }

    removeValue(value) {
        this.selectedValues = this.selectedValues.filter(item => item.value !== value);
        this.updateDOM();
    }

    updateDOM() {
        // 1. Update Hidden Inputs for Form Submission
        this.hiddenContainer.innerHTML = '';
        this.selectedValues.forEach(item => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = this.config.name;
            inp.value = item.value;
            this.hiddenContainer.appendChild(inp);
        });

        // 2. Update Options UI (checkmarks/bolding)
        this.options.forEach(opt => {
            const isSelected = this.selectedValues.some(item => item.value === opt.dataset.value);
            opt.classList.toggle('selected', isSelected);
        });

        // 3. Update Trigger UI (Chips or Text)
        this.renderTrigger();

        this.el.dispatchEvent(new CustomEvent('change', { bubbles: true }));
    }

    renderTrigger() {
        this.content.innerHTML = '';
        if (this.selectedValues.length === 0) {
            this.content.innerHTML = `<span class="enh-placeholder">${this.config.placeholder}</span>`;
            return;
        }

        if (this.config.multiple) {
            this.selectedValues.forEach(item => {
                const chip = document.createElement('div');
                chip.className = 'enh-chip';
                chip.innerHTML = `
                    ${item.label}
                    <span class="material-symbols-outlined enh-chip-close" data-val="${item.value}">close</span>
                `;
                // Add remove listener to the close button specifically
                chip.querySelector('.enh-chip-close').addEventListener('click', (e) => {
                    e.stopPropagation(); // Stop dropdown from opening
                    this.removeValue(e.target.dataset.val);
                });
                this.content.appendChild(chip);
            });
        } else {
            this.content.innerHTML = `<span class="enh-single-val">${this.selectedValues[0].label}</span>`;
        }
    }
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.enh-dropdown').forEach(el => new EnhancedDropdown(el));
});

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
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

// Close on outside click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Close on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(modal => {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});

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
    if (existing) dismissToast(existing);

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
    if (typeSelect) {
        toggleWorkflow(typeSelect.value);
        typeSelect.addEventListener('change', (e) => toggleWorkflow(e.target.value));
    }

    function toggleWorkflow(type) {
        const isReg = (type === 'Regression');

        // Toggle Global Visibility Classes
        document.querySelectorAll('.smoke-area').forEach(el => {
            if (isReg) el.classList.add('hidden');
            else {
                // Only show if parent card is selected
                const card = el.closest('.printer-card');
                if (card.classList.contains('selected')) el.classList.remove('hidden');
            }
        });

        document.querySelectorAll('.regression-area').forEach(el => {
            if (!isReg) el.classList.add('hidden');
            else {
                // Only show if parent card is selected
                const card = el.closest('.printer-card');
                if (card.classList.contains('selected')) el.classList.remove('hidden');
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
        if (!e.target.classList.contains('no-loader')) {
            window.showLoader();
        }
    });

    // --- PROFILE DROPDOWN LOGIC ---
    window.toggleProfileMenu = function (event) {
        event.stopPropagation();
        const menu = document.getElementById('profileMenu');
        const btn = document.getElementById('profileDropdownBtn');
        if (menu) menu.classList.toggle('show');
        if (btn) btn.classList.toggle('active');
    };

    // Close dropdown when clicking outside
    document.addEventListener('click', function (event) {
        const menu = document.getElementById('profileMenu');
        const btn = document.getElementById('profileDropdownBtn');
        if (menu && menu.classList.contains('show') && !menu.contains(event.target)) {
            menu.classList.remove('show');
            if (btn) btn.classList.remove('active');
        }
    });

// ── Tooltip ──────────────────────────────────────────
const tooltip = document.getElementById('custom-tooltip');

function attachTooltips() {
    document.querySelectorAll('[data-tip]').forEach(el => {
        el.addEventListener('mouseenter', (e) => {
            tooltip.textContent = el.dataset.tip;
            tooltip.classList.add('visible');
        });
        el.addEventListener('mousemove', (e) => {
            let leftPos = e.clientX + 14;
            let topPos = e.clientY - 32;

            // Check if tooltip goes out of bounds on the right
            if (leftPos + tooltip.offsetWidth > window.innerWidth) {
                // Flip to the left side of the cursor
                leftPos = e.clientX - tooltip.offsetWidth - 14;
            }

            tooltip.style.left = leftPos + 'px';
            tooltip.style.top = topPos + 'px';
        });
        el.addEventListener('mouseleave', () => tooltip.classList.remove('visible'));
    });
}
attachTooltips();
});