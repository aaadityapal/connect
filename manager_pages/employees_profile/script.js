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
    const actionNoticeModal = document.getElementById('actionNoticeModal');
    const noticeTitle = document.getElementById('noticeTitle');
    const noticeMessage = document.getElementById('noticeMessage');
    const noticeOkBtn = document.getElementById('noticeOkBtn');
    let currentEmployeeId = null;
    let currentEmployeeData = null;
    const ROLE_OPTIONS = Array.isArray(window.EMPLOYEE_ROLE_OPTIONS) ? window.EMPLOYEE_ROLE_OPTIONS : [];
    const REPORTING_MANAGER_OPTIONS = Array.isArray(window.REPORTING_MANAGER_OPTIONS) ? window.REPORTING_MANAGER_OPTIONS : [];

    const SENIOR_ROLES = [
        'admin', 'HR', 'Senior Manager (Studio)', 'Senior Manager (Site)',
        'Senior Manager (Marketing)', 'Senior Manager (Sales)', 'Senior Manager (Purchase)'
    ];

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

    function showNoticeModal(message, title = 'Notification') {
        if (!actionNoticeModal || !noticeMessage || !noticeTitle) {
            return;
        }
        noticeTitle.textContent = title;
        noticeMessage.textContent = message;
        actionNoticeModal.classList.add('show');
        actionNoticeModal.setAttribute('aria-hidden', 'false');
        setTimeout(() => noticeOkBtn?.focus(), 0);
    }

    function closeNoticeModal() {
        if (!actionNoticeModal) return;
        actionNoticeModal.classList.remove('show');
        actionNoticeModal.setAttribute('aria-hidden', 'true');
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

    function formatDateTimeDetailed(value) {
        if (!hasDisplayValue(value)) return 'N/A';
        const raw = String(value).trim();
        const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)
            ? raw.replace(' ', 'T')
            : raw;
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) {
            return raw;
        }

        return date.toLocaleString('en-IN', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
    }

    function tryParseJsonLike(value) {
        if (typeof value !== 'string') return null;
        const raw = value.trim();
        if (!raw) return null;
        if (!((raw.startsWith('{') && raw.endsWith('}')) || (raw.startsWith('[') && raw.endsWith(']')))) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    }

    function normalizeReadableText(v) {
        return String(v)
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function buildKeyValueRows(obj, preferredKeys = []) {
        const entries = Object.entries(obj || {}).filter(([, val]) => hasDisplayValue(val));
        if (!entries.length) return '<div class="json-empty">No details available.</div>';

        const sorted = sortByPreferredOrder(entries, preferredKeys);
        return sorted.map(([k, v]) => `
            <div class="json-row">
                <span class="json-key">${escapeHtml(toLabel(k))}</span>
                <span class="json-value">${escapeHtml(formatValue(k, v))}</span>
            </div>
        `).join('');
    }

    function buildJsonDisplay(key, parsed) {
        const normalizedKey = normalizeKey(key);

        if (Array.isArray(parsed)) {
            if (!parsed.length) {
                return '<div class="json-empty">No details available.</div>';
            }

            const allObjects = parsed.every(item => item && typeof item === 'object' && !Array.isArray(item));
            if (allObjects) {
                const preferredByField = {
                    education: ['degree', 'qualification', 'field', 'specialization', 'institution', 'university', 'college', 'year'],
                    educationbackground: ['degree', 'qualification', 'field', 'specialization', 'institution', 'university', 'college', 'year'],
                    workexperience: ['company', 'organization', 'title', 'role', 'years', 'duration', 'from', 'to', 'responsibilities'],
                    workexperiences: ['company', 'organization', 'title', 'role', 'years', 'duration', 'from', 'to', 'responsibilities']
                };

                const preferred = preferredByField[normalizedKey] || [];

                return `
                    <div class="json-list">
                        ${parsed.map((item, idx) => `
                            <div class="json-card">
                                <div class="json-card-title">${escapeHtml(toLabel(key))} ${idx + 1}</div>
                                ${buildKeyValueRows(item, preferred)}
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            return `
                <ul class="json-bullets">
                    ${parsed.map(item => `<li>${escapeHtml(normalizeReadableText(formatValue(key, item)))}</li>`).join('')}
                </ul>
            `;
        }

        if (parsed && typeof parsed === 'object') {
            return `<div class="json-object">${buildKeyValueRows(parsed)}</div>`;
        }

        return `<span>${escapeHtml(formatValue(key, parsed))}</span>`;
    }

    function formatValueHtml(key, value) {
        if (value === null || value === undefined || value === '') {
            return '<span>—</span>';
        }

        if (Array.isArray(value) || (value && typeof value === 'object')) {
            return buildJsonDisplay(key, value);
        }

        const parsed = tryParseJsonLike(value);
        if (parsed !== null) {
            return buildJsonDisplay(key, parsed);
        }

        return `<span>${escapeHtml(formatValue(key, value))}</span>`;
    }

    function hasDisplayValue(value) {
        return !(value === null || value === undefined || String(value).trim() === '');
    }

    function isFilledForCompletion(value, { isJson = false, isArray = false } = {}) {
        if (value === null || value === undefined) return false;

        if (isArray) {
            if (Array.isArray(value)) return value.length > 0;
            const parsed = tryParseJsonLike(value);
            return Array.isArray(parsed) && parsed.length > 0;
        }

        if (isJson) {
            if (typeof value === 'object' && value !== null) {
                return Object.values(value).some(v => {
                    if (Array.isArray(v)) return v.length > 0;
                    if (v && typeof v === 'object') return Object.values(v).some(n => String(n ?? '').trim() !== '');
                    return String(v ?? '').trim() !== '';
                });
            }

            const parsed = tryParseJsonLike(value);
            if (parsed && typeof parsed === 'object') {
                return Object.values(parsed).some(v => {
                    if (Array.isArray(v)) return v.length > 0;
                    if (v && typeof v === 'object') return Object.values(v).some(n => String(n ?? '').trim() !== '');
                    return String(v ?? '').trim() !== '';
                });
            }

            const raw = String(value).trim();
            return raw !== '' && raw !== '{}' && raw !== '[]' && raw.toLowerCase() !== 'null';
        }

        return String(value).trim() !== '';
    }

    function calculateProfileCompletionPercent(employee = {}) {
        const stored = Number(employee?.profile_completion_percent);
        if (Number.isFinite(stored) && stored >= 0 && stored <= 100) {
            return Math.round(stored);
        }

        const fields = [
            { key: 'profile_picture' },
            { key: 'username' },
            { key: 'email' },
            { key: 'phone_number_fallback_phone' },
            { key: 'designation' },
            { key: 'department' },
            { key: 'dob' },
            { key: 'gender' },
            { key: 'bio' },
            { key: 'address' },
            { key: 'nationality' },
            { key: 'blood_group' },
            { key: 'marital_status' },
            { key: 'languages' },
            { key: 'skills' },
            { key: 'interests' },
            { key: 'social_media', isJson: true },
            { key: 'bank_details', isJson: true },
            { key: 'education_background', isArray: true },
            { key: 'work_experiences', isArray: true },
            { key: 'emergency_contact', isJson: true }
        ];

        const total = fields.length;
        const filled = fields.reduce((sum, f) => {
            const value = (f.key === 'phone_number_fallback_phone')
                ? (employee.phone_number || employee.phone)
                : employee[f.key];
            return sum + (isFilledForCompletion(value, { isJson: !!f.isJson, isArray: !!f.isArray }) ? 1 : 0);
        }, 0);

        return Math.round((filled / total) * 100);
    }

    function normalizeKey(key) {
        return String(key).toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function shouldHideField(key) {
        const normalized = normalizeKey(key);
        const hiddenFields = new Set([
            'salary',
            'currentsalary',
            'currentctc',
            'expectedsalary',
            'expectedctc',
            'overtime',
            'overtimerate',
            'attendancestatus',
            'project',
            'noticeperiod'
        ]);

        return hiddenFields.has(normalized);
    }

    function isBankField(key) {
        const normalized = normalizeKey(key);
        return /(bank|accountnumber|accnumber|accno|holder|ifsc|swift|iban|branch|upi|payment|beneficiary|micr|routing)/.test(normalized);
    }

    function parseJsonObject(value) {
        if (!value) return null;
        if (typeof value === 'object' && !Array.isArray(value)) return value;
        if (typeof value !== 'string') return null;

        const parsed = tryParseJsonLike(value);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            return parsed;
        }

        return null;
    }

    function resolveFileUrl(filePath) {
        if (!hasDisplayValue(filePath)) return '';
        const raw = String(filePath).trim();
        if (!raw) return '';

        if (/^https?:\/\//i.test(raw) || raw.startsWith('/')) {
            return raw;
        }

        const cleaned = raw.replace(/^\.\//, '').replace(/^\/+/, '');
        return `../../${cleaned}`;
    }

    function resolveProfilePictureUrl(filePath) {
        if (!hasDisplayValue(filePath)) return '';
        const raw = String(filePath).trim();
        if (!raw) return '';
        if (/^https?:\/\//i.test(raw) || raw.startsWith('/')) return raw;
        const cleaned = raw
            .replace(/^(\.\/)+/, '')
            .replace(/^(\.\.\/)+/, '')
            .replace(/^\/+/, '');
        return `../../${cleaned}`;
    }

    function parseJsonArray(value) {
        const parsed = (typeof value === 'string') ? tryParseJsonLike(value) : value;
        if (Array.isArray(parsed)) return parsed;
        if (parsed && typeof parsed === 'object') return [parsed];
        return [];
    }

    function normalizeEducationList(employee) {
        const source = parseJsonArray(employee?.education_background).length
            ? parseJsonArray(employee?.education_background)
            : parseJsonArray(employee?.education);

        return source
            .map(item => ({
                degree: item?.degree || item?.highest_degree || item?.qualification || '',
                institution: item?.institution || item?.university || item?.college || '',
                field: item?.field || item?.field_of_study || item?.specialization || '',
                year: item?.year || item?.graduation_year || item?.passing_year || ''
            }))
            .filter(item => hasDisplayValue(item.degree) || hasDisplayValue(item.institution) || hasDisplayValue(item.field) || hasDisplayValue(item.year));
    }

    function normalizeExperienceList(employee) {
        const source = parseJsonArray(employee?.work_experiences).length
            ? parseJsonArray(employee?.work_experiences)
            : parseJsonArray(employee?.work_experience);

        return source
            .map(item => ({
                company: item?.company || item?.current_company || item?.organization || '',
                title: item?.title || item?.job_title || item?.role || '',
                years: item?.years || item?.experience_years || item?.duration || '',
                responsibilities: item?.responsibilities || item?.responsibility || ''
            }))
            .filter(item => hasDisplayValue(item.company) || hasDisplayValue(item.title) || hasDisplayValue(item.years) || hasDisplayValue(item.responsibilities));
    }

    function normalizeBankEditData(employee) {
        const bankObj = parseJsonObject(employee?.bank_details) || {};
        return {
            bank_name: bankObj.bank_name || employee?.bank_name || '',
            account_holder: bankObj.account_holder || bankObj.account_holder_name || employee?.account_holder_name || '',
            account_number: bankObj.account_number || employee?.account_number || employee?.bank_account_number || '',
            ifsc_code: bankObj.ifsc_code || employee?.ifsc_code || '',
            branch_name: bankObj.branch_name || employee?.branch_name || '',
            account_type: bankObj.account_type || employee?.account_type || ''
        };
    }

    function renderEditField(fieldKey, employee) {
        const value = employee?.[fieldKey] ?? '';
        const label = toLabel(fieldKey);
        const inputType = fieldKey === 'dob' || fieldKey === 'joining_date' ? 'date' : 'text';

        if (fieldKey === 'role') {
            const options = ROLE_OPTIONS.length ? ROLE_OPTIONS : [String(value || '')].filter(Boolean);
            return `
                <div class="edit-item">
                    <label>${escapeHtml(label)}</label>
                    <select class="edit-input" data-field="role" id="edit-role">
                        <option value="">Select Role</option>
                        ${options.map(role => `<option value="${escapeHtml(role)}" ${String(value) === String(role) ? 'selected' : ''}>${escapeHtml(role)}</option>`).join('')}
                    </select>
                </div>
            `;
        }

        if (fieldKey === 'reporting_manager') {
            const options = REPORTING_MANAGER_OPTIONS.length ? REPORTING_MANAGER_OPTIONS : [String(value || '')].filter(Boolean);
            return `
                <div class="edit-item">
                    <label>Reporting Manager</label>
                    <select class="edit-input" data-field="reporting_manager" id="edit-reporting-manager">
                        <option value="">Select Manager</option>
                        ${options.map(manager => `<option value="${escapeHtml(manager)}" ${String(value) === String(manager) ? 'selected' : ''}>${escapeHtml(manager)}</option>`).join('')}
                    </select>
                </div>
            `;
        }

        if (fieldKey === 'designation' || fieldKey === 'status') {
            return `
                <div class="edit-item">
                    <label>${escapeHtml(label)}</label>
                    <input type="text" class="edit-input" data-field="${escapeHtml(fieldKey)}" value="${escapeHtml(String(value ?? ''))}" readonly />
                </div>
            `;
        }

        return `
            <div class="edit-item">
                <label>${escapeHtml(label)}</label>
                <input type="${inputType}" class="edit-input" data-field="${escapeHtml(fieldKey)}" value="${escapeHtml(String(value ?? ''))}" placeholder="Enter ${escapeHtml(label)}" />
            </div>
        `;
    }

    function getAutoReportingManager(role) {
        if (!role) return '';

        if ([
            'Design Team', 'Working Team', '3D Designing Team', 'Studio Trainees',
            'Interior Designer', 'Senior Interior Designer', 'Junior Interior Designer',
            'Lead Interior Designer', 'Associate Interior Designer', 'Interior Design Coordinator',
            'Interior Design Assistant', 'FF&E Designer', 'Interior Stylist', 'Interior Design Intern',
            'Graphic Designer', 'Draughtsman'
        ].includes(role)) {
            return 'Sr. Manager (Studio)';
        }

        if (role === 'Business Developer') return 'Sr. Manager (Business Developer)';

        if (['Relationship Manager', 'Sales Manager', 'Sales Consultant', 'Field Sales Representative', 'Sales'].includes(role)) {
            return 'Sr. Manager (Relationship Manager)';
        }

        if (['Site Manager', 'Site Coordinator', 'Site Supervisor', 'Site Trainee'].includes(role)) {
            return 'Sr. Manager (Operations)';
        }

        if (['Social Media Manager', 'Social Media Marketing', 'Maid Back Office'].includes(role)) {
            return 'Sr. Manager (HR)';
        }

        if (['Purchase Manager', 'Purchase Executive', 'Purchase'].includes(role)) {
            return 'Sr. Manager (Purchase)';
        }

        return '';
    }

    function applyReportingManagerRule() {
        const roleSelect = document.getElementById('edit-role');
        const managerSelect = document.getElementById('edit-reporting-manager');
        if (!roleSelect || !managerSelect) return;

        const selectedRole = roleSelect.value;
        const isSenior = SENIOR_ROLES.includes(selectedRole);

        if (isSenior) {
            managerSelect.value = '';
            managerSelect.disabled = true;
            return;
        }

        managerSelect.disabled = false;
        const suggested = getAutoReportingManager(selectedRole);
        if (suggested) {
            managerSelect.value = suggested;
        }
    }

    function renderEditTabPanel(fields, employee) {
        return `
            <div class="edit-grid">
                ${fields.map(f => renderEditField(f, employee)).join('')}
            </div>
        `;
    }

    function renderEducationRow(item = {}, index = 1) {
        return `
            <div class="repeater-row" data-type="education">
                <div class="repeater-head">
                    <strong>Education ${index}</strong>
                    <button type="button" class="btn-secondary repeater-remove" data-remove-row>
                        <i data-lucide="trash-2"></i>
                        <span>Remove</span>
                    </button>
                </div>
                <div class="edit-grid">
                    <div class="edit-item">
                        <label>Degree</label>
                        <input type="text" class="edit-input" data-edu="degree" value="${escapeHtml(item.degree || '')}" placeholder="e.g. Bachelor's" />
                    </div>
                    <div class="edit-item">
                        <label>Institution</label>
                        <input type="text" class="edit-input" data-edu="institution" value="${escapeHtml(item.institution || '')}" placeholder="e.g. ABC University" />
                    </div>
                    <div class="edit-item">
                        <label>Field of Study</label>
                        <input type="text" class="edit-input" data-edu="field" value="${escapeHtml(item.field || '')}" placeholder="e.g. Commerce" />
                    </div>
                    <div class="edit-item">
                        <label>Year</label>
                        <input type="text" class="edit-input" data-edu="year" value="${escapeHtml(item.year || '')}" placeholder="e.g. 2022" />
                    </div>
                </div>
            </div>
        `;
    }

    function renderEducationRows(items = []) {
        if (!items.length) items = [{ degree: '', institution: '', field: '', year: '' }];
        return items.map((item, idx) => renderEducationRow(item, idx + 1)).join('');
    }

    function renderExperienceRow(item = {}, index = 1) {
        return `
            <div class="repeater-row" data-type="experience">
                <div class="repeater-head">
                    <strong>Experience ${index}</strong>
                    <button type="button" class="btn-secondary repeater-remove" data-remove-row>
                        <i data-lucide="trash-2"></i>
                        <span>Remove</span>
                    </button>
                </div>
                <div class="edit-grid">
                    <div class="edit-item">
                        <label>Company</label>
                        <input type="text" class="edit-input" data-exp="company" value="${escapeHtml(item.company || '')}" placeholder="e.g. ArchitectsHive" />
                    </div>
                    <div class="edit-item">
                        <label>Job Title</label>
                        <input type="text" class="edit-input" data-exp="title" value="${escapeHtml(item.title || '')}" placeholder="e.g. Sr. Manager" />
                    </div>
                    <div class="edit-item">
                        <label>Experience (Years)</label>
                        <input type="text" class="edit-input" data-exp="years" value="${escapeHtml(item.years || '')}" placeholder="e.g. 3.5" />
                    </div>
                    <div class="edit-item">
                        <label>Responsibilities</label>
                        <input type="text" class="edit-input" data-exp="responsibilities" value="${escapeHtml(item.responsibilities || '')}" placeholder="Short role summary" />
                    </div>
                </div>
            </div>
        `;
    }

    function renderExperienceRows(items = []) {
        if (!items.length) items = [{ company: '', title: '', years: '', responsibilities: '' }];
        return items.map((item, idx) => renderExperienceRow(item, idx + 1)).join('');
    }

    function renumberRepeaterRows(container, type) {
        if (!container) return;
        const prefix = type === 'education' ? 'Education' : 'Experience';
        const rows = container.querySelectorAll(`.repeater-row[data-type="${type}"]`);
        rows.forEach((row, idx) => {
            const title = row.querySelector('.repeater-head strong');
            if (title) title.textContent = `${prefix} ${idx + 1}`;
        });
    }

    function openEditProfileModal(employeeId, employee) {
        const infoFields = ['username', 'email', 'phone', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact', 'gender', 'dob', 'address', 'city', 'state', 'country', 'postal_code', 'bio', 'languages'];
        const workFields = ['role', 'reporting_manager', 'designation', 'status', 'joining_date', 'skills'];
        const bank = normalizeBankEditData(employee || {});
        const educationRows = normalizeEducationList(employee || {});
        const experienceRows = normalizeExperienceList(employee || {});

        document.getElementById('modalEmployeeName').textContent = `Edit ${employee?.username || 'Employee'} Profile`;

        profileBody.innerHTML = `
            <form id="employeeEditForm" class="employee-edit-form" novalidate>
                <div class="profile-tabs edit-tabs">
                    <button type="button" class="profile-tab active" data-tab="edit-info"><i data-lucide="user-circle-2"></i><span>Information</span></button>
                    <button type="button" class="profile-tab" data-tab="edit-work"><i data-lucide="briefcase-business"></i><span>Work</span></button>
                    <button type="button" class="profile-tab" data-tab="edit-account"><i data-lucide="landmark"></i><span>Account</span></button>
                </div>

                <div class="profile-tab-panel active" data-panel="edit-info">
                    ${renderEditTabPanel(infoFields, employee)}
                </div>

                <div class="profile-tab-panel" data-panel="edit-work">
                    ${renderEditTabPanel(workFields, employee)}
                    <section class="edit-section-block">
                        <div class="repeater-head main">
                            <h4>Education Background</h4>
                            <button type="button" class="btn-secondary" id="addEducationRowBtn">
                                <i data-lucide="plus"></i>
                                <span>Add Education</span>
                            </button>
                        </div>
                        <div id="educationRepeater" class="repeater-wrap">
                            ${renderEducationRows(educationRows)}
                        </div>
                    </section>

                    <section class="edit-section-block">
                        <div class="repeater-head main">
                            <h4>Work Experience</h4>
                            <button type="button" class="btn-secondary" id="addExperienceRowBtn">
                                <i data-lucide="plus"></i>
                                <span>Add Experience</span>
                            </button>
                        </div>
                        <div id="experienceRepeater" class="repeater-wrap">
                            ${renderExperienceRows(experienceRows)}
                        </div>
                    </section>
                </div>

                <div class="profile-tab-panel" data-panel="edit-account">
                    <div class="edit-grid">
                        <div class="edit-item">
                            <label>Bank Name</label>
                            <input type="text" class="edit-input" id="edit-bank-name" value="${escapeHtml(bank.bank_name)}" placeholder="Enter bank name" />
                        </div>
                        <div class="edit-item">
                            <label>Account Holder</label>
                            <input type="text" class="edit-input" id="edit-account-holder" value="${escapeHtml(bank.account_holder)}" placeholder="Enter account holder" />
                        </div>
                        <div class="edit-item">
                            <label>Account Number</label>
                            <input type="text" class="edit-input" id="edit-account-number" value="${escapeHtml(bank.account_number)}" placeholder="Enter account number" />
                        </div>
                        <div class="edit-item">
                            <label>IFSC Code</label>
                            <input type="text" class="edit-input" id="edit-ifsc-code" value="${escapeHtml(bank.ifsc_code)}" placeholder="Enter IFSC code" />
                        </div>
                        <div class="edit-item">
                            <label>Branch Name</label>
                            <input type="text" class="edit-input" id="edit-branch-name" value="${escapeHtml(bank.branch_name)}" placeholder="Enter branch name" />
                        </div>
                        <div class="edit-item">
                            <label>Account Type</label>
                            <input type="text" class="edit-input" id="edit-account-type" value="${escapeHtml(bank.account_type)}" placeholder="e.g. savings" />
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="btn-primary" id="saveProfileBtn">
                        <i data-lucide="save"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        `;

        bindProfileTabs();
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        const cancelBtn = document.getElementById('cancelEditBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => renderProfile(employee, document.querySelector(`.employee-card [onclick*="${employeeId}"]`)?.closest('.employee-card')));
        }

        const educationRepeater = document.getElementById('educationRepeater');
        const experienceRepeater = document.getElementById('experienceRepeater');
        const roleSelect = document.getElementById('edit-role');

        if (roleSelect) {
            roleSelect.addEventListener('change', applyReportingManagerRule);
            applyReportingManagerRule();
        }

        document.getElementById('addEducationRowBtn')?.addEventListener('click', () => {
            if (educationRepeater) {
                const nextIndex = educationRepeater.querySelectorAll('.repeater-row[data-type="education"]').length + 1;
                educationRepeater.insertAdjacentHTML('beforeend', renderEducationRow({ degree: '', institution: '', field: '', year: '' }, nextIndex));
                renumberRepeaterRows(educationRepeater, 'education');
                if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
            }
        });

        document.getElementById('addExperienceRowBtn')?.addEventListener('click', () => {
            if (experienceRepeater) {
                const nextIndex = experienceRepeater.querySelectorAll('.repeater-row[data-type="experience"]').length + 1;
                experienceRepeater.insertAdjacentHTML('beforeend', renderExperienceRow({ company: '', title: '', years: '', responsibilities: '' }, nextIndex));
                renumberRepeaterRows(experienceRepeater, 'experience');
                if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
            }
        });

        profileBody.addEventListener('click', (event) => {
            const removeBtn = event.target.closest('[data-remove-row]');
            if (!removeBtn) return;
            const row = removeBtn.closest('.repeater-row');
            const parent = row?.parentElement;
            if (!row || !parent) return;
            if (parent.children.length <= 1) return;
            const type = row.getAttribute('data-type');
            row.remove();
            if (type) {
                renumberRepeaterRows(parent, type);
            }
        });

        const form = document.getElementById('employeeEditForm');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();

            const payload = { id: employeeId };
            const fields = form.querySelectorAll('[data-field]');

            for (const el of fields) {
                const field = el.dataset.field;
                const raw = (el.value ?? '').trim();
                payload[field] = raw;
            }

            const education = Array.from(form.querySelectorAll('.repeater-row[data-type="education"]')).map(row => ({
                degree: row.querySelector('[data-edu="degree"]')?.value?.trim() || '',
                institution: row.querySelector('[data-edu="institution"]')?.value?.trim() || '',
                field: row.querySelector('[data-edu="field"]')?.value?.trim() || '',
                year: row.querySelector('[data-edu="year"]')?.value?.trim() || ''
            })).filter(item => hasDisplayValue(item.degree) || hasDisplayValue(item.institution) || hasDisplayValue(item.field) || hasDisplayValue(item.year));

            const experience = Array.from(form.querySelectorAll('.repeater-row[data-type="experience"]')).map(row => ({
                company: row.querySelector('[data-exp="company"]')?.value?.trim() || '',
                title: row.querySelector('[data-exp="title"]')?.value?.trim() || '',
                years: row.querySelector('[data-exp="years"]')?.value?.trim() || '',
                responsibilities: row.querySelector('[data-exp="responsibilities"]')?.value?.trim() || ''
            })).filter(item => hasDisplayValue(item.company) || hasDisplayValue(item.title) || hasDisplayValue(item.years) || hasDisplayValue(item.responsibilities));

            const bankDetails = {
                bank_name: document.getElementById('edit-bank-name')?.value?.trim() || '',
                account_holder: document.getElementById('edit-account-holder')?.value?.trim() || '',
                account_number: document.getElementById('edit-account-number')?.value?.trim() || '',
                ifsc_code: document.getElementById('edit-ifsc-code')?.value?.trim() || '',
                branch_name: document.getElementById('edit-branch-name')?.value?.trim() || '',
                account_type: document.getElementById('edit-account-type')?.value?.trim() || ''
            };

            payload.education_background = JSON.stringify(education);
            payload.education = JSON.stringify(education);
            payload.work_experiences = JSON.stringify(experience);
            payload.work_experience = JSON.stringify(experience);
            payload.bank_details = JSON.stringify(bankDetails);

            const saveBtn = document.getElementById('saveProfileBtn');
            if (saveBtn) saveBtn.disabled = true;

            try {
                const response = await fetch('api/update_employee_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to update profile.');
                }

                const refreshRes = await fetch(`api/get_employee_profile.php?id=${encodeURIComponent(employeeId)}`, {
                    credentials: 'same-origin'
                });
                const refreshData = await refreshRes.json();
                if (!refreshRes.ok || !refreshData.success) {
                    throw new Error(refreshData.message || 'Updated, but failed to refresh profile.');
                }

                currentEmployeeData = refreshData.employee || {};
                const fallbackCard = document.querySelector(`.employee-card [onclick*="${employeeId}"]`)?.closest('.employee-card');
                renderProfile(currentEmployeeData, fallbackCard);
            } catch (error) {
                alert(error.message || 'Failed to update profile.');
                if (saveBtn) saveBtn.disabled = false;
            }
        });
    }

    function extractDocuments(employee) {
        const raw = employee?.documents;
        if (!hasDisplayValue(raw)) return [];

        const parsed = (typeof raw === 'string') ? tryParseJsonLike(raw) : raw;
        const source = parsed ?? raw;
        const docs = [];

        function pushDoc(entry, fallbackLabel = 'Document') {
            if (!entry) return;

            if (typeof entry === 'string') {
                const url = resolveFileUrl(entry);
                if (!url) return;
                const fileName = entry.split('/').pop() || fallbackLabel;
                docs.push({
                    label: fallbackLabel,
                    name: fileName,
                    url
                });
                return;
            }

            if (typeof entry === 'object') {
                const rawPath = entry.file_path || entry.path || entry.url || entry.document_url || entry.file || entry.filepath;
                const url = resolveFileUrl(rawPath);
                if (!url) return;

                const name = entry.original_name || entry.name || entry.filename || entry.title || String(rawPath).split('/').pop() || fallbackLabel;
                const label = entry.type || fallbackLabel;

                docs.push({
                    label,
                    name,
                    url
                });
            }
        }

        if (Array.isArray(source)) {
            source.forEach((item, idx) => pushDoc(item, `Document ${idx + 1}`));
        } else if (source && typeof source === 'object') {
            Object.entries(source).forEach(([key, value]) => {
                if (value && typeof value === 'object') {
                    pushDoc(value, toLabel(key));
                } else {
                    pushDoc(value, toLabel(key));
                }
            });
        }

        return docs;
    }

    function renderDocumentsPanel(documents) {
        if (!documents.length) {
            return '<div class="profile-empty">No documents available.</div>';
        }

        return `
            <section class="tab-section">
                <h4 class="tab-section-title">
                    <i data-lucide="file-text" class="section-icon"></i>
                    <span>User Documents</span>
                </h4>
                <div class="documents-grid">
                    ${documents.map((doc, idx) => `
                        <article class="document-item">
                            <div class="document-meta">
                                <div class="document-icon-wrap">
                                    <i data-lucide="file" class="document-icon"></i>
                                </div>
                                <div>
                                    <div class="document-label">${escapeHtml(toLabel(doc.label || `Document ${idx + 1}`))}</div>
                                    <div class="document-name">${escapeHtml(doc.name || `File ${idx + 1}`)}</div>
                                </div>
                            </div>
                            <div class="document-actions">
                                <a class="btn-secondary btn-doc" href="${escapeHtml(doc.url)}" target="_blank" rel="noopener noreferrer">
                                    <i data-lucide="eye"></i>
                                    <span>View</span>
                                </a>
                                <a class="btn-primary btn-doc" href="${escapeHtml(doc.url)}" download>
                                    <i data-lucide="download"></i>
                                    <span>Download</span>
                                </a>
                            </div>
                        </article>
                    `).join('')}
                </div>
            </section>
        `;
    }

    function extractBankFields(employee) {
        const fieldMap = new Map();

        function setField(key, value) {
            if (!hasDisplayValue(value)) return;
            const normalized = normalizeKey(key);
            if (!fieldMap.has(normalized)) {
                fieldMap.set(normalized, [key, value]);
            }
        }

        // 1) Direct bank columns from users table (if available)
        Object.entries(employee || {}).forEach(([key, value]) => {
            if (isBankField(key)) {
                setField(key, value);
            }
        });

        // 2) JSON field users.bank_details (common in this project)
        const bankObj = parseJsonObject(employee?.bank_details);
        if (bankObj) {
            const aliases = [
                ['bank_name', ['bank_name', 'bank'] ],
                ['account_holder', ['account_holder', 'account_holder_name', 'holder_name', 'beneficiary_name'] ],
                ['account_number', ['account_number', 'bank_account_number', 'acc_no', 'account_no'] ],
                ['ifsc_code', ['ifsc_code', 'ifsc'] ],
                ['branch_name', ['branch_name', 'branch'] ],
                ['account_type', ['account_type', 'type'] ],
                ['upi_id', ['upi_id', 'upi'] ],
                ['swift_code', ['swift_code', 'swift'] ],
                ['iban', ['iban'] ],
                ['routing_number', ['routing_number', 'routing'] ],
                ['micr_code', ['micr_code', 'micr'] ]
            ];

            aliases.forEach(([targetKey, possibleKeys]) => {
                for (const candidate of possibleKeys) {
                    if (hasDisplayValue(bankObj[candidate])) {
                        setField(targetKey, bankObj[candidate]);
                        break;
                    }
                }
            });
        }

        return Array.from(fieldMap.values());
    }

    function iconForField(key) {
        const normalized = normalizeKey(key);

        if (/(id|employeeid|uniqueid)/.test(normalized)) return 'badge-check';
        if (/(username|name)/.test(normalized)) return 'user';
        if (/(email)/.test(normalized)) return 'mail';
        if (/(phone|mobile)/.test(normalized)) return 'phone';
        if (/(emergency|contact)/.test(normalized)) return 'phone-call';
        if (/(gender)/.test(normalized)) return 'users';
        if (/(dob|birth|date)/.test(normalized)) return 'calendar';
        if (/(address|city|state|country|zip|pincode|postal)/.test(normalized)) return 'map-pin';
        if (/(role|designation|department|team|manager)/.test(normalized)) return 'briefcase';
        if (/(education|qualification|degree|university|college|institute|course|specialization)/.test(normalized)) return 'graduation-cap';
        if (/(experience|workexperience|company|organization)/.test(normalized)) return 'building-2';
        if (/(skills|skill)/.test(normalized)) return 'sparkles';
        if (/(salary|ctc|compensation|overtime)/.test(normalized)) return 'wallet';
        if (/(status|active|verified)/.test(normalized)) return 'shield-check';
        if (/(createdat|updatedat|lastlogin)/.test(normalized)) return 'clock-3';

        return 'circle-dot';
    }

    function iconForSection(title) {
        const normalized = normalizeKey(title);
        if (normalized.includes('primary')) return 'user-circle';
        if (normalized.includes('personal')) return 'id-card';
        if (normalized.includes('emergency')) return 'phone-call';
        if (normalized.includes('location')) return 'map-pinned';
        if (normalized.includes('employment')) return 'briefcase-business';
        if (normalized.includes('education')) return 'graduation-cap';
        if (normalized.includes('experience')) return 'badge-check';
        if (normalized.includes('bank')) return 'landmark';
        if (normalized.includes('compensation')) return 'wallet-cards';
        if (normalized.includes('system')) return 'shield';
        if (normalized.includes('additional')) return 'folder-open';
        if (normalized.includes('other')) return 'list';
        return 'layout-list';
    }

    function categorizeField(fieldName) {
        const key = fieldName.toLowerCase();
        const normalized = key.replace(/[^a-z0-9]/g, '');

        if (isBankField(fieldName)) {
            return 'account';
        }

        // Force requested fields into Information tab
        if (['id', 'employeeid', 'uniqueid', 'bio', 'languages', 'bloodgroup', 'role', 'emergencycontact', 'emergencycontactname', 'emergencycontactphone'].includes(normalized)) {
            return 'info';
        }

        if (/(username|name|email|phone|mobile|gender|dob|birth|address|city|state|country|pincode|zip|emergency|contact)/.test(key)) {
            return 'info';
        }

        if (/(role|department|designation|manager|team|joining|employee|salary|shift|attendance|overtime|project|status|education|qualification|degree|university|college|institute|specialization|course|passout|passing|work_experience|experience|exp|company|organization|notice|ctc|skills|certification)/.test(key)) {
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
                <label>
                    <span class="label-with-icon">
                        <i data-lucide="${iconForField(key)}" class="label-icon"></i>
                        <span>${escapeHtml(toLabel(key))}</span>
                    </span>
                </label>
                <div class="info-value">${formatValueHtml(key, value)}</div>
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
                    title: 'Emergency Contacts',
                    keys: ['emergency_contact_name', 'emergency_contact_phone', 'emergency_phone', 'emergency_mobile', 'emergency_contact', 'emergency_relation', 'emergency_relationship']
                },
                {
                    title: 'Location',
                    keys: ['address', 'city', 'state', 'country', 'postal_code', 'zip', 'pincode']
                }
            ],
            work: [
                {
                    title: 'Employment',
                    keys: [
                        'department',
                        'designation',
                        'role',
                        'reporting_manager',
                        'manager_id',
                        'team',
                        'joining_date',
                        'employee_type',
                        'employment_type',
                        'shift',
                        'shift_id',
                        'status'
                    ]
                },
                {
                    title: 'Education & Qualification',
                    keys: [
                        'education',
                        'highest_education',
                        'highest_qualification',
                        'qualification',
                        'degree',
                        'course',
                        'specialization',
                        'stream',
                        'university',
                        'college',
                        'institute',
                        'passing_year',
                        'passout_year',
                        'graduation_year',
                        'cgpa',
                        'percentage'
                    ]
                },
                {
                    title: 'Experience & Skills',
                    keys: [
                        'work_experience',
                        'experience',
                        'total_experience',
                        'relevant_experience',
                        'current_company',
                        'previous_company',
                        'last_company',
                        'previous_organization',
                        'skills',
                        'primary_skills',
                        'secondary_skills',
                        'certification',
                        'certifications'
                    ]
                }
            ],
            account: [
                {
                    title: 'Bank Account Details',
                    keys: [
                        'bank_details',
                        'bank_name',
                        'account_holder',
                        'bank_account_holder_name',
                        'account_holder_name',
                        'beneficiary_name',
                        'bank_account_number',
                        'account_number',
                        'acc_no',
                        'ifsc_code',
                        'swift_code',
                        'iban',
                        'branch_name',
                        'branch',
                        'account_type',
                        'routing_number',
                        'micr_code',
                        'upi_id',
                        'payment_mode'
                    ]
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
                    <h4 class="tab-section-title">
                        <i data-lucide="${iconForSection(section.title)}" class="section-icon"></i>
                        <span>${escapeHtml(section.title)}</span>
                    </h4>
                    <div class="profile-info-grid">
                        ${buildInfoItems(sectionFields)}
                    </div>
                </section>
            `);
        });

        const remaining = normalizedEntries
            .filter(item => !usedKeys.has(item.normalized))
            .map(item => [item.key, item.value]);

        if (tabName !== 'account' && remaining.length) {
            sectionsHtml.push(`
                <section class="tab-section">
                    <h4 class="tab-section-title">
                        <i data-lucide="list" class="section-icon"></i>
                        <span>Other Details</span>
                    </h4>
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

    function getEmployeeCardById(employeeId) {
        if (!employeeId) return null;
        return document.querySelector(`.employee-card[data-employee-id="${String(employeeId)}"]`);
    }

    function applyStatusToCard(card, nextStatus, statusChangedAt = '') {
        if (!card) return;
        const normalized = String(nextStatus).toLowerCase() === 'active' ? 'active' : 'inactive';
        card.dataset.status = normalized;
        card.dataset.statusChangedAt = statusChangedAt || card.dataset.statusChangedAt || '';
        card.classList.toggle('status-active', normalized === 'active');
        card.classList.toggle('status-inactive', normalized !== 'active');

        const statusBadge = card.querySelector('.js-status-badge') || card.querySelector('.status-badge');
        if (statusBadge) {
            statusBadge.textContent = normalized === 'active' ? 'Active' : 'Inactive';
            statusBadge.classList.toggle('active', normalized === 'active');
            statusBadge.classList.toggle('inactive', normalized !== 'active');
        }

        const toggleBtn = card.querySelector('.btn-status-toggle');
        if (toggleBtn) {
            const makeInactive = normalized === 'active';
            const label = makeInactive ? 'Set Inactive' : 'Set Active';
            toggleBtn.title = label;
            toggleBtn.classList.toggle('is-active', normalized === 'active');
            toggleBtn.classList.toggle('is-inactive', normalized !== 'active');
            const span = toggleBtn.querySelector('span');
            if (span) span.textContent = label;
            toggleBtn.setAttribute('onclick', `toggleEmployeeStatus(${Number(card.dataset.employeeId || 0)}, '${normalized}', this, 'card')`);
        }

        const statusTimeEl = card.querySelector('.js-status-time');
        if (statusTimeEl) {
            statusTimeEl.textContent = `Last Active At: ${formatDateTimeDetailed(card.dataset.statusChangedAt || '')}`;
        }

        const reminderBtn = card.querySelector('.btn-reminder');
        if (reminderBtn) {
            if (normalized !== 'active') {
                reminderBtn.remove();
            }
        }
    }

    function renderProfile(employee, fallbackCard) {
        const fullName = employee.username || employee.name || fallbackCard?.querySelector('.employee-name')?.textContent?.trim() || 'Employee';
        const role = employee.role || fallbackCard?.querySelector('.employee-role')?.textContent?.trim() || 'Not Assigned';
        const status = (employee.status || fallbackCard?.dataset?.status || 'inactive').toLowerCase();
        const avatarColor = fallbackCard?.querySelector('.avatar-large')?.style.background || '#6366f1';
        const initial = fullName.charAt(0).toUpperCase();
        const profilePicture = employee.profile_picture || employee.profile_image || '';
        const cardImgSrc = fallbackCard?.querySelector('.employee-avatar-image')?.getAttribute('src') || '';
        const profilePictureUrl = resolveProfilePictureUrl(profilePicture) || cardImgSrc;
        const statusChangedAt = employee.status_changed_date || fallbackCard?.dataset?.statusChangedAt || '';
        const completionPct = calculateProfileCompletionPercent(employee || {});
        const reminderButtonHtml = (completionPct < 90 && status === 'active')
            ? `<button class="btn-secondary btn-reminder-modal" id="modalReminderBtn" title="Send Profile Reminder"><i data-lucide="bell-ring"></i><span>Send Reminder</span></button>`
            : '';
        const statusToggleLabel = status === 'active' ? 'Set Inactive' : 'Set Active';
        const statusToggleClass = status === 'active' ? 'is-active' : 'is-inactive';
        const avatarHtml = profilePictureUrl
            ? `<img src="${escapeHtml(profilePictureUrl)}" alt="${escapeHtml(fullName)}" class="profile-avatar-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"><span class="avatar-fallback-initial" style="display:none;">${escapeHtml(initial)}</span>`
            : `<span class="avatar-fallback-initial">${escapeHtml(initial)}</span>`;

        const entries = Object.entries(employee || {}).filter(([key, value]) => {
            const lowerKey = String(key).toLowerCase();
            if (shouldHideField(key)) return false;
            if (lowerKey === 'id') return true;
            if (lowerKey === 'username') return true;
            if (lowerKey === 'email') return true;
            if (lowerKey === 'role') return true;
            if (lowerKey === 'status') return true;
            return hasDisplayValue(value);
        });
        const infoFields = entries.filter(([key]) => categorizeField(key) === 'info');
        const workFields = entries.filter(([key]) => categorizeField(key) === 'work');
        const accountFields = extractBankFields(employee || {});
        const documents = extractDocuments(employee || {});

        currentEmployeeData = employee || {};
        document.getElementById('modalEmployeeName').textContent = `${fullName}'s Profile`;

        profileBody.innerHTML = `
            <div class="profile-detail-header">
                <div class="avatar-extra-large" style="background: ${avatarColor};">
                    ${avatarHtml}
                </div>
                <div>
                    <h2 class="profile-name">${escapeHtml(fullName)}</h2>
                    <div class="profile-role-row">
                        <span class="role-chip">${escapeHtml(role)}</span>
                        <span class="status-badge js-modal-status-badge ${status === 'active' ? 'active' : 'inactive'}">${status === 'active' ? 'Active' : 'Inactive'}</span>
                    </div>
                    <div class="status-time js-modal-status-time">Last Active At: ${escapeHtml(formatDateTimeDetailed(statusChangedAt))}</div>
                    <div class="profile-completion-modal">
                        <div class="profile-completion-top">
                            <span>Profile Completion</span>
                            <strong>${completionPct}%</strong>
                        </div>
                        <div class="profile-completion-track">
                            <div class="profile-completion-fill" style="width:${completionPct}%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-tabs">
                <button class="profile-tab active" data-tab="info"><i data-lucide="user-circle-2"></i><span>Information</span></button>
                <button class="profile-tab" data-tab="work"><i data-lucide="briefcase-business"></i><span>Work</span></button>
                <button class="profile-tab" data-tab="account"><i data-lucide="shield-check"></i><span>Account</span></button>
                <button class="profile-tab" data-tab="documents"><i data-lucide="file-text"></i><span>Documents</span></button>
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

            <div class="profile-tab-panel" data-panel="documents">
                ${renderDocumentsPanel(documents)}
            </div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="document.getElementById('profileModal').style.display='none'">Close</button>
                ${reminderButtonHtml}
                <button class="btn-status-toggle ${statusToggleClass}" id="modalStatusToggleBtn" title="${statusToggleLabel}"><i data-lucide="power"></i><span>${statusToggleLabel}</span></button>
                <button class="btn-primary" id="editProfileBtn">Edit Profile</button>
            </div>
        `;

        bindProfileTabs();
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        const editBtn = document.getElementById('editProfileBtn');
        if (editBtn && currentEmployeeId) {
            editBtn.addEventListener('click', () => openEditProfileModal(currentEmployeeId, currentEmployeeData || employee || {}));
        }

        const reminderBtn = document.getElementById('modalReminderBtn');
        if (reminderBtn && currentEmployeeId) {
            reminderBtn.addEventListener('click', () => {
                const usernameForReminder = employee?.username || fullName || 'User';
                window.sendProfileReminder(currentEmployeeId, usernameForReminder, reminderBtn, 'modal', completionPct);
            });
        }

        const modalStatusToggleBtn = document.getElementById('modalStatusToggleBtn');
        if (modalStatusToggleBtn && currentEmployeeId) {
            modalStatusToggleBtn.addEventListener('click', () => {
                const currentStatus = ((currentEmployeeData?.status || status || 'inactive') + '').toLowerCase();
                window.toggleEmployeeStatus(currentEmployeeId, currentStatus, modalStatusToggleBtn, 'modal');
            });
        }
    }

    window.viewProfile = async function(employeeId) {
        currentEmployeeId = employeeId;
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

    window.sendProfileReminder = async function(employeeId, username = null, triggerBtn = null, source = 'unknown', completionPercent = null) {
        if (!employeeId) return;

        const btn = triggerBtn || null;
        const card = btn?.closest('.employee-card') || null;
        const cardUsername = btn?.closest('.employee-card')?.querySelector('.employee-name')?.textContent?.trim() || null;
        const displayName = username || cardUsername || 'User';
        const cardCompletion = card ? Number(card.dataset.completion || 0) : null;
        const completion = Number.isFinite(Number(completionPercent)) ? Number(completionPercent) : cardCompletion;
        if (btn) btn.disabled = true;

        try {
            const response = await fetch('api/send_profile_completion_reminder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    employee_id: employeeId,
                    trigger_source: source,
                    completion_percent: completion
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to send reminder.');
            }

            const infoMessage = String(data.message || '').toLowerCase().includes('already')
                ? `Reminder already exists for ${displayName} today.`
                : `Reminder task created for ${displayName}.`;

            showNoticeModal(infoMessage, 'Reminder Status');

            if (btn) {
                btn.disabled = true;
                btn.classList.add('reminder-sent');
                btn.title = 'Reminder already sent today';
            }
        } catch (error) {
            showNoticeModal(error.message || 'Failed to send reminder.', 'Reminder Error');
        } finally {
            if (btn && !btn.classList.contains('reminder-sent')) btn.disabled = false;
        }
    };

    window.toggleEmployeeStatus = async function(employeeId, currentStatus = 'inactive', triggerBtn = null, source = 'unknown') {
        if (!employeeId) return;
        const normalizedCurrent = String(currentStatus).toLowerCase() === 'active' ? 'active' : 'inactive';
        const nextStatus = normalizedCurrent === 'active' ? 'inactive' : 'active';

        const btn = triggerBtn || null;
        if (btn) btn.disabled = true;

        try {
            const response = await fetch('api/toggle_employee_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    employee_id: Number(employeeId),
                    status: nextStatus,
                    trigger_source: source
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to update status.');
            }

            const card = getEmployeeCardById(employeeId);
            const changedAt = data.status_changed_at || '';
            applyStatusToCard(card, nextStatus, changedAt);

            if (currentEmployeeData && Number(currentEmployeeData.id || employeeId) === Number(employeeId)) {
                currentEmployeeData.status = nextStatus;
                currentEmployeeData.status_changed_date = changedAt || currentEmployeeData.status_changed_date || '';
            }

            if (source === 'modal' && currentEmployeeData) {
                renderProfile(currentEmployeeData, card);
            }

            showNoticeModal(`User marked as ${nextStatus === 'active' ? 'Active' : 'Inactive'}.`, 'Status Updated');
        } catch (error) {
            showNoticeModal(error.message || 'Failed to update user status.', 'Status Error');
        } finally {
            if (btn) btn.disabled = false;
        }
    };

    noticeOkBtn?.addEventListener('click', closeNoticeModal);
    actionNoticeModal?.addEventListener('click', (event) => {
        if (event.target === actionNoticeModal) {
            closeNoticeModal();
        }
    });

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
