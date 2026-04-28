(function () {
    const modal = document.getElementById('addEmployeeModal');
    const openBtn = document.getElementById('addEmployeeBtn');
    const roleSelect = document.getElementById('aeRoleSelect');
    const managerSelect = document.getElementById('aeManagerSelect');
    const customRoleField = document.getElementById('aeCustomRoleField');
    const customRoleInput = document.getElementById('aeCustomRoleInput');
    const customRoleValue = '__custom__';

    // ── Username availability check ──────────────────────────
    const usernameInput  = document.getElementById('aeUsernameInput');
    const usernameStatus = document.getElementById('aeUsernameStatus');
    const usernameField  = document.getElementById('aeUsernameField');
    let usernameDebounceTimer = null;
    let usernameAvailable = true; // assume ok until proven otherwise

    function setUsernameState(state, message) {
        // state: 'idle' | 'checking' | 'taken' | 'available'
        if (!usernameStatus || !usernameField) return;

        usernameStatus.className = 'ae-field__status';
        usernameField.classList.remove('is-taken', 'is-available');

        if (state === 'checking') {
            usernameStatus.classList.add('ae-field__status--checking');
            usernameStatus.textContent = 'Checking availability';
            usernameAvailable = true; // allow submit while checking (server will catch it)
        } else if (state === 'taken') {
            usernameStatus.classList.add('ae-field__status--taken');
            usernameStatus.textContent = message || 'Username is already taken, use another.';
            usernameField.classList.add('is-taken');
            usernameAvailable = false;
        } else if (state === 'available') {
            usernameStatus.classList.add('ae-field__status--available');
            usernameStatus.textContent = message || 'Username is available.';
            usernameField.classList.add('is-available');
            usernameAvailable = true;
        } else {
            // idle / reset
            usernameStatus.textContent = '';
            usernameAvailable = true;
        }
    }

    function checkUsername(value) {
        const trimmed = value.trim();

        if (trimmed.length < 3) {
            setUsernameState('idle');
            return;
        }

        setUsernameState('checking');

        fetch((window.AE_CHECK_USERNAME_URL || 'check_username.php') + '?username=' + encodeURIComponent(trimmed))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.available === true) {
                    setUsernameState('available', data.message);
                } else if (data.available === false) {
                    setUsernameState('taken', data.message);
                } else {
                    // null = too short or no value; just clear
                    setUsernameState('idle');
                }
            })
            .catch(function () {
                setUsernameState('idle'); // silently fail; server-side will validate
            });
    }

    if (usernameInput) {
        usernameInput.addEventListener('input', function () {
            clearTimeout(usernameDebounceTimer);
            setUsernameState('idle'); // clear immediately on new keystroke
            usernameDebounceTimer = setTimeout(function () {
                checkUsername(usernameInput.value);
            }, 500); // 500 ms debounce
        });
    }
    // ─────────────────────────────────────────────────────────

    if (!modal || !openBtn) {
        return;
    }

    const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (roleSelect && roleSelect.options.length <= 1) {
            populateRoleSelect(roleSelect, window.EMPLOYEE_ROLE_OPTIONS || []);
        }
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            firstInput.focus();
        }
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        openBtn.focus();
    }

    function trapFocus(event) {
        if (!modal.classList.contains('is-open') || event.key !== 'Tab') {
            return;
        }

        const focusables = Array.from(modal.querySelectorAll(focusableSelector));
        if (focusables.length === 0) {
            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function normalizeOptions(options) {
        if (Array.isArray(options)) {
            return options.filter((option) => typeof option === 'string' && option.trim() !== '');
        }
        if (options && typeof options === 'object') {
            return Object.values(options).filter((option) => typeof option === 'string' && option.trim() !== '');
        }
        return [];
    }

    function shouldBlockAdmin() {
        const role = String(window.CURRENT_USER_ROLE || '').toLowerCase();
        return role === 'hr' || role === 'human resources';
    }

    function populateSelect(selectEl, options) {
        const normalized = normalizeOptions(options);
        if (!selectEl || normalized.length === 0) {
            return;
        }
        normalized.forEach((option) => {
            const opt = document.createElement('option');
            opt.value = option;
            opt.textContent = option;
            selectEl.appendChild(opt);
        });
    }

    function getRoleCategory(role) {
        const value = role.toLowerCase();
        if (value.includes('admin') || value.includes('manager') || value.includes('hr')) {
            return 'Leadership & HR';
        }
        if (value.includes('design') || value.includes('interior') || value.includes('draughtsman') || value.includes('ff&e') || value.includes('stylist') || value.includes('graphic')) {
            return 'Design & Creative';
        }
        if (value.includes('studio') || value.includes('3d')) {
            return 'Studio & Visualization';
        }
        if (value.includes('site')) {
            return 'Site & Execution';
        }
        if (value.includes('sales') || value.includes('relationship') || value.includes('business') || value.includes('marketing') || value.includes('social media')) {
            return 'Sales & Marketing';
        }
        if (value.includes('purchase')) {
            return 'Purchase';
        }
        if (value.includes('account') || value.includes('finance') || value.includes('payroll')) {
            return 'Accounts & Finance';
        }
        if (value.includes('it') || value.includes('system') || value.includes('network') || value.includes('software') || value.includes('helpdesk')) {
            return 'IT & Systems';
        }
        if (value.includes('working') || value.includes('trainee') || value.includes('back office')) {
            return 'Operations & Support';
        }
        return 'Other';
    }

    function populateRoleSelect(selectEl, options) {
        const normalized = normalizeOptions(options);
        if (!selectEl) {
            return;
        }

        const placeholder = selectEl.querySelector('option[value=""]');
        selectEl.innerHTML = '';
        if (placeholder) {
            selectEl.appendChild(placeholder);
        }

        const groups = new Map();
        const blockAdmin = shouldBlockAdmin();
        normalized.forEach((option) => {
            if (blockAdmin && option.toLowerCase() === 'admin') {
                return;
            }
            const label = getRoleCategory(option);
            if (!groups.has(label)) {
                groups.set(label, []);
            }
            groups.get(label).push(option);
        });

        const groupOrder = [
            'Leadership & HR',
            'Design & Creative',
            'Studio & Visualization',
            'Site & Execution',
            'Sales & Marketing',
            'Purchase',
            'Accounts & Finance',
            'IT & Systems',
            'Operations & Support',
            'Other'
        ];

        groupOrder.forEach((label) => {
            const items = groups.get(label);
            if (!items || items.length === 0) {
                return;
            }
            items.sort((a, b) => a.localeCompare(b));
            const group = document.createElement('optgroup');
            group.label = label;
            items.forEach((option) => {
                const opt = document.createElement('option');
                opt.value = option;
                opt.textContent = option;
                group.appendChild(opt);
            });
            selectEl.appendChild(group);
        });

        const customGroup = document.createElement('optgroup');
        customGroup.label = 'Custom';
        const customOption = document.createElement('option');
        customOption.value = customRoleValue;
        customOption.textContent = 'Other (type custom role)';
        customGroup.appendChild(customOption);
        selectEl.appendChild(customGroup);
    }

    function toggleCustomRoleField() {
        if (!customRoleField || !customRoleInput || !roleSelect) {
            return;
        }
        const useCustom = roleSelect.value === customRoleValue;
        customRoleField.classList.toggle('is-hidden', !useCustom);
        customRoleInput.required = useCustom;
        if (useCustom) {
            customRoleInput.focus();
        } else {
            customRoleInput.value = '';
        }
    }

    populateRoleSelect(roleSelect, window.EMPLOYEE_ROLE_OPTIONS || []);
    populateSelect(managerSelect, window.REPORTING_MANAGER_OPTIONS || []);

    openBtn.addEventListener('click', openModal);
    if (roleSelect) {
        roleSelect.addEventListener('change', toggleCustomRoleField);
    }
    modal.addEventListener('click', (event) => {
        const target = event.target;
        if (target && target.closest && target.closest('[data-ae-close="true"]')) {
            closeModal();
        }
    });

    // Block form submit when username is taken
    const aeForm = modal ? modal.querySelector('.ae-form') : null;
    if (aeForm) {
        aeForm.addEventListener('submit', function (event) {
            if (!usernameAvailable) {
                event.preventDefault();
                // Briefly shake the status message to draw attention
                if (usernameStatus) {
                    usernameStatus.style.animation = 'none';
                    // force reflow
                    void usernameStatus.offsetWidth;
                    usernameStatus.style.animation = 'ae-shake 0.35s ease';
                    usernameStatus.addEventListener('animationend', function () {
                        usernameStatus.style.animation = '';
                    }, { once: true });
                }
                if (usernameInput) {
                    usernameInput.focus();
                }
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
        trapFocus(event);
    });
})();

