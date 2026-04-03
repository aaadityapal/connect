/**
 * =====================================================
 * EMPLOYEES PROFILE — manager_pages/employees_profile/script.js
 * =====================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    // ── Search & Filters ──────────────────────────────────
    const searchInput = document.getElementById('employeeSearch');
    const roleFilter  = document.getElementById('roleFilter');
    const employeeCards = document.querySelectorAll('.employee-card');

    function filterEmployees() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedRole = roleFilter.value.toLowerCase();

        employeeCards.forEach(card => {
            const name  = card.dataset.name;
            const email = card.dataset.email;
            const role  = card.dataset.role;

            const matchesSearch = name.includes(searchTerm) || 
                                email.includes(searchTerm) || 
                                role.includes(searchTerm);
            
            const matchesRole = selectedRole === 'all' || role === selectedRole;

            if (matchesSearch && matchesRole) {
                card.style.display = 'block';
                card.style.animation = 'fadeIn 0.4s ease forwards';
            } else {
                card.style.display = 'none';
            }
        });

        // Toggle empty state
        const visibleCards = Array.from(employeeCards).filter(c => c.style.display !== 'none');
        const emptyState = document.querySelector('.empty-state');
        if (visibleCards.length === 0) {
            if (!emptyState) {
                const grid = document.getElementById('employeesGrid');
                grid.insertAdjacentHTML('beforeend', `
                    <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 4rem;">
                        <i data-lucide="search-x" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3>No results found</h3>
                        <p>Try adjusting your search or filters.</p>
                    </div>
                `);
                lucide.createIcons();
            }
        } else if (emptyState) {
            emptyState.remove();
        }
    }

    searchInput.addEventListener('input', filterEmployees);
    roleFilter.addEventListener('change', filterEmployees);

    // ── Profile Modal ─────────────────────────────────────
    const profileModal = document.getElementById('profileModal');
    const closeModal   = document.getElementById('closeModal');
    const profileBody  = document.getElementById('profileBody');

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toLabel(key) {
        const normalized = String(key).toLowerCase().replace(/[^a-z0-9]/g, '');
        const labelMap = {
            id: 'Employee ID',
            employeeid: 'Employee ID',
            uniqueid: 'Employee ID',
            bloodgroup: 'Blood Group',
            bio: 'Bio',
            languages: 'Languages',
            role: 'Role'
        };

        if (labelMap[normalized]) {
            return labelMap[normalized];
        }

        return key
            .replace(/_/g, ' ')
            .replace(/\b\w/g, ch => ch.toUpperCase());
    }

    function formatValue(key, value) {
        if (value === null || value === undefined || value === '') return '—';

        const lowerKey = String(key).toLowerCase();
        if (lowerKey.includes('date') || lowerKey.includes('created_at') || lowerKey.includes('updated_at') || lowerKey.includes('last_login')) {
            const date = new Date(value);
            if (!Number.isNaN(date.getTime())) {
                return date.toLocaleString('en-IN', {
                    year: 'numeric',
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }

        if (value === 1 || value === '1') return 'Yes';
        if (value === 0 || value === '0') return 'No';

        return String(value);
    }

    function hasDisplayValue(value) {
        return !(value === null || value === undefined || String(value).trim() === '');
    }

    function normalizeKey(key) {
        return String(key).toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function categorizeField(fieldName) {
        const key = fieldName.toLowerCase();
        const normalized = key.replace(/[^a-z0-9]/g, '');

        // Force requested fields into Information tab
        if (['id', 'employeeid', 'uniqueid', 'bio', 'languages', 'bloodgroup', 'role'].includes(normalized)) {
            return 'info';
        }

        if (/(username|name|email|phone|mobile|gender|dob|birth|address|city|state|country|pincode|zip)/.test(key)) {
            return 'info';
        }

        if (/(role|department|designation|manager|team|joining|employee|salary|shift|attendance|overtime|project|status)/.test(key)) {
            return 'work';
        }

        return 'account';
    }

    function buildInfoItems(fields) {
        if (!fields.length) {
            return '<div class="profile-empty">No data available.</div>';
        }

        return fields.map(([key, value]) => `
            <div class="info-item">
                <label>${escapeHtml(toLabel(key))}</label>
                <p>${escapeHtml(formatValue(key, value))}</p>
            </div>
        `).join('');
    }

    function sortByPreferredOrder(fields, preferredOrder = []) {
        const orderMap = new Map(preferredOrder.map((k, i) => [normalizeKey(k), i]));

        return [...fields].sort((a, b) => {
            const keyA = normalizeKey(a[0]);
            const keyB = normalizeKey(b[0]);
            const idxA = orderMap.has(keyA) ? orderMap.get(keyA) : Number.MAX_SAFE_INTEGER;
            const idxB = orderMap.has(keyB) ? orderMap.get(keyB) : Number.MAX_SAFE_INTEGER;

            if (idxA !== idxB) return idxA - idxB;
            return toLabel(a[0]).localeCompare(toLabel(b[0]));
        });
    }

    function buildTabSections(tabName, fields) {
        if (!fields.length) {
            return '<div class="profile-empty">No data available.</div>';
        }

        const definitions = {
            info: [
                {
                    title: 'Primary Information',
                    keys: ['id', 'employee_id', 'unique_id', 'username', 'name', 'email', 'role', 'status']
                },
                {
                    title: 'Personal Details',
                    keys: ['bio', 'languages', 'blood_group', 'phone', 'mobile', 'gender', 'dob', 'birth_date']
                },
                {
                    title: 'Location',
                    keys: ['address', 'city', 'state', 'country', 'postal_code', 'zip', 'pincode']
                }
            ],
            work: [
                {
                    title: 'Employment',
                    keys: ['department', 'designation', 'manager_id', 'team', 'joining_date', 'employee_type', 'shift']
                },
                {
                    title: 'Compensation & Operations',
                    keys: ['salary', 'overtime', 'attendance_status', 'project', 'work_experience']
                }
            ],
            account: [
                {
                    title: 'System Details',
                    keys: ['created_at', 'updated_at', 'last_login', 'is_verified', 'is_active']
                },
                {
                    title: 'Additional Fields',
                    keys: []
                }
            ]
        };

        const sectionDefs = definitions[tabName] || [{ title: 'Details', keys: [] }];
        const usedKeys = new Set();
        const normalizedEntries = fields.map(([key, value]) => ({ key, value, normalized: normalizeKey(key) }));
        const sectionsHtml = [];

        sectionDefs.forEach(section => {
            const keySet = new Set(section.keys.map(normalizeKey));
            let sectionFields = normalizedEntries
                .filter(item => !usedKeys.has(item.normalized))
                .filter(item => keySet.size === 0 || keySet.has(item.normalized))
                .map(item => [item.key, item.value]);

            if (!sectionFields.length) {
                return;
            }

            sectionFields = sortByPreferredOrder(sectionFields, section.keys);
            sectionFields.forEach(([k]) => usedKeys.add(normalizeKey(k)));

            sectionsHtml.push(`
                <section class="tab-section">
                    <h4 class="tab-section-title">${escapeHtml(section.title)}</h4>
                    <div class="profile-info-grid">
                        ${buildInfoItems(sectionFields)}
                    </div>
                </section>
            `);
        });

        const remaining = normalizedEntries
            .filter(item => !usedKeys.has(item.normalized))
            .map(item => [item.key, item.value]);

        if (remaining.length) {
            sectionsHtml.push(`
                <section class="tab-section">
                    <h4 class="tab-section-title">Other Details</h4>
                    <div class="profile-info-grid">
                        ${buildInfoItems(sortByPreferredOrder(remaining))}
                    </div>
                </section>
            `);
        }

        return sectionsHtml.join('');
    }

    function bindProfileTabs() {
        const tabs = profileBody.querySelectorAll('.profile-tab');
        const panels = profileBody.querySelectorAll('.profile-tab-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;

                tabs.forEach(t => t.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));

                tab.classList.add('active');
                const panel = profileBody.querySelector(`.profile-tab-panel[data-panel="${target}"]`);
                if (panel) panel.classList.add('active');
            });
        });
    }

    function renderProfile(employee, fallbackCard) {
        const fullName = employee.username || employee.name || fallbackCard.querySelector('.employee-name')?.textContent?.trim() || 'Employee';
        const role = employee.role || fallbackCard.querySelector('.employee-role')?.textContent?.trim() || 'Not Assigned';
        const status = (employee.status || fallbackCard.dataset.status || 'inactive').toLowerCase();
        const avatarColor = fallbackCard.querySelector('.avatar-large')?.style.background || '#6366f1';
        const initial = fullName.charAt(0).toUpperCase();

        const entries = Object.entries(employee || {}).filter(([key, value]) => {
            const lowerKey = String(key).toLowerCase();
            if (lowerKey === 'id') return true;
            if (lowerKey === 'username') return true;
            if (lowerKey === 'email') return true;
            if (lowerKey === 'role') return true;
            if (lowerKey === 'status') return true;
            return hasDisplayValue(value);
        });
        const infoFields = entries.filter(([key]) => categorizeField(key) === 'info');
        const workFields = entries.filter(([key]) => categorizeField(key) === 'work');
        const accountFields = entries.filter(([key]) => categorizeField(key) === 'account');

        document.getElementById('modalEmployeeName').textContent = `${fullName}'s Profile`;

        profileBody.innerHTML = `
            <div class="profile-detail-header">
                <div class="avatar-extra-large" style="background: ${avatarColor};">
                    ${escapeHtml(initial)}
                </div>
                <div>
                    <h2 class="profile-name">${escapeHtml(fullName)}</h2>
                    <div class="profile-role-row">
                        <span class="role-chip">${escapeHtml(role)}</span>
                        <span class="status-badge ${status === 'active' ? 'active' : 'inactive'}">${status === 'active' ? 'Active' : 'Inactive'}</span>
                    </div>
                </div>
            </div>

            <div class="profile-tabs">
                <button class="profile-tab active" data-tab="info">Information</button>
                <button class="profile-tab" data-tab="work">Work</button>
                <button class="profile-tab" data-tab="account">Account</button>
            </div>

            <div class="profile-tab-panel active" data-panel="info">
                ${buildTabSections('info', infoFields)}
            </div>

            <div class="profile-tab-panel" data-panel="work">
                ${buildTabSections('work', workFields)}
            </div>

            <div class="profile-tab-panel" data-panel="account">
                ${buildTabSections('account', accountFields)}
            </div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="document.getElementById('profileModal').style.display='none'">Close</button>
                <button class="btn-primary">Edit Profile</button>
            </div>
        `;

        bindProfileTabs();
    }

    window.viewProfile = async function(employeeId) {
        profileModal.style.display = 'flex';
        profileBody.innerHTML = `
            <div class="loader-container">
                <div class="spinner"></div>
            </div>
        `;

        const card = document.querySelector(`.employee-card [onclick*="${employeeId}"]`)?.closest('.employee-card');
        if (!card) {
            profileBody.innerHTML = '<div class="profile-empty">Unable to find employee card.</div>';
            return;
        }

        try {
            const response = await fetch(`api/get_employee_profile.php?id=${encodeURIComponent(employeeId)}`, {
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to load employee profile.');
            }

            renderProfile(data.employee || {}, card);
        } catch (error) {
            profileBody.innerHTML = `
                <div class="profile-empty profile-error">
                    ${escapeHtml(error.message || 'Failed to load profile.')}
                </div>
            `;
        }
    };

    closeModal.addEventListener('click', () => {
        profileModal.style.display = 'none';
    });

    // Close on outside click
    window.addEventListener('click', (e) => {
        if (e.target === profileModal) {
            profileModal.style.display = 'none';
        }
    });

    // Initialize animations
    employeeCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(10px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });
});
