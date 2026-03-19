
let educationList = [];
let experienceList = [];
let documentsList = [];

document.addEventListener("DOMContentLoaded", async () => {
    
    // 1. First, load all the HTML fragments from the tabs/ folder
    const tabsToLoad = [
        { id: 'personal-info', src: 'tabs/personal-info.html' },
        { id: 'security', src: 'tabs/security.html' },
        { id: 'notifications', src: 'tabs/notifications.html' },
        { id: 'activity-log', src: 'tabs/activity-log.html' },
        { id: 'hr-documents', src: 'tabs/hr-documents.html' }
    ];
    
    // 1. Load HTML fragments (Tabs)
    try {
        await Promise.all(tabsToLoad.map(async tab => {
            const container = document.getElementById(tab.id);
            if (container) {
                const response = await fetch(tab.src + '?v=' + Date.now()); // CACHE BUST
                if (response.ok) {
                    container.innerHTML = await response.text();
                } else {
                    console.error(`Failed to fetch tab: ${tab.src}`);
                }
            }
        }));
    } catch(err) {
        console.error('Error loading tab components:', err);
    }

    // 1.1 Load Modals (Independent block)
    try {
        const modalMount = document.getElementById('modal-mount');
        if (modalMount) {
            const modalFiles = [
                '../../studio_users/components/modals/profile-add-education.html',
                '../../studio_users/components/modals/profile-add-experience.html'
            ];
            
            const modalHTMList = [];
            for (const file of modalFiles) {
                const response = await fetch(file + '?v=' + Date.now()); // CACHE BUST
                if (response.ok) {
                    const text = await response.text();
                    modalHTMList.push(text);
                } else {
                    console.error(`Failed to fetch modal [v2]: ${file}`);
                }
            }
            modalMount.innerHTML = modalHTMList.join('');
        }
    } catch(err) {
        console.error('Error loading modal components:', err);
    }

    // 2. Initialize all dynamic components AFTER HTML is safely injected
    try {
        initCustomSelects();
        initTabs();
        initSubTabs();
        initFileInputs();
        initButtons();
        initModals();
        initEmergencyContacts();
        initTagSystem();
        initSecurityTab();
        initActivityLog();
        initNotificationsTab();
        // initAgModalSystem(); // Moved to top-level for better reliability
        
        // 2.1 Refresh icons for dynamic content
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    } catch (err) {
        console.error('Error during component initialization:', err);
    }

    // 3. Load user data from API
    loadUserInfo();
});

async function loadUserInfo() {
    try {
        const response = await fetch(`../api/get_user_info.php?v=${Date.now()}`);
        const result = await response.json();

        if (result.status === 'success') {
            const d = result.data;

            // ── Helper: set input/textarea value safely ──────────────────
            function setField(id, value) {
                const el = document.getElementById(id);
                if (el) el.value = value || '';
            }

            // ── Helper: set <select> value + update custom select UI ─────
            function setSelect(id, value) {
                const el = document.getElementById(id);
                if (el && value) {
                    el.value = value;
                    updateCustomSelect(el);
                }
            }

            // ── SECTION 2: Basic Information ──────────────────────────────
            setField('pi-username',    d.username);
            setField('pi-email',       d.email);
            setField('pi-phone',       d.phone_number || d.phone);
            setField('pi-employee-id', d.unique_id);
            setField('pi-designation', d.designation);
            setField('pi-department',  d.department);
            setField('pi-position',    d.position);
            setField('pi-role',        d.role);
            setField('pi-unique-id',   d.unique_id);
            setField('pi-dob',         d.dob);
            setField('pi-joining-date',d.joining_date);
            setSelect('pi-gender',     d.gender);

            // ── SECTION 3: About Me ───────────────────────────────────────
            setField('pi-bio', d.bio);

            // ── SECTION 4: Personal Details ───────────────────────────────
            setSelect('pi-marital-status', d.marital_status);
            setSelect('pi-blood-group',    d.blood_group);
            setField('pi-nationality',     d.nationality);
            setField('pi-languages',       d.languages);

            // ── SECTION 5: Contact & Address ──────────────────────────────
            setField('pi-address',     d.address);
            setField('pi-city',        d.city);
            setField('pi-state',       d.state);
            setField('pi-country',     d.country);
            setField('pi-postal-code', d.postal_code);

            // ── SECTION 6: Emergency Contacts (dynamic list) ──────────────
            loadEmergencyContacts(d);


            // ── SECTION 7: Skills & Interests ─────────────────────────────
            loadTags('skills-tag-list', d.skills, 'skills');
            loadTags('interests-tag-list', d.interests, 'interests');

            // ── SECTION 8: Social Media (parsed JSON object) ──────────────
            if (d.social_media && typeof d.social_media === 'object') {
                const sm = d.social_media;
                setField('pi-linkedin',  sm.linkedin);
                setField('pi-twitter',   sm.twitter);
                setField('pi-facebook',  sm.facebook);
                setField('pi-instagram', sm.instagram);
                setField('pi-github',    sm.github);
                setField('pi-youtube',   sm.youtube);
            }

            // ── SECTION 9: Education Background ──────────────────────────
            educationList = Array.isArray(d.education_background) ? d.education_background : [];
            renderEducationTable();

            // ── SECTION 10: Work Experience ────────────────────────────
            experienceList = Array.isArray(d.work_experiences) ? d.work_experiences : [];
            renderExperienceTable();

            // ── SECTION 11: Documents ───────────────────────────────────
            documentsList = Array.isArray(d.documents) ? d.documents : [];
            renderDocumentsGrid();

            // ── SECTION 11: Bank Details (parsed JSON object) ─────────────
            if (d.bank_details && typeof d.bank_details === 'object') {
                const bank = d.bank_details;
                setField('pi-bank-name',       bank.bank_name);
                setField('pi-account-holder',  bank.account_holder);
                setField('pi-account-number',  bank.account_number);
                setField('pi-ifsc',            bank.ifsc_code);
            }

            // ── SECTION 12: Notification Preferences ─────────────────────
            if (d.notification_preferences && typeof d.notification_preferences === 'object') {
                const np = d.notification_preferences;
                const emailToggle = document.getElementById('toggle-email');
                const pushToggle = document.getElementById('toggle-push');
                if (emailToggle) emailToggle.checked = np.email === true;
                if (pushToggle)  pushToggle.checked  = np.push === true;
            }

            // ── Profile Picture ───────────────────────────────────────────
            const avatarPreview = document.querySelector('.avatar-preview');
            if (avatarPreview) {
                if (d.profile_picture) {
                    avatarPreview.innerHTML = `
                        <img src="../../${d.profile_picture}" 
                             alt="Profile" 
                             style="width:100%;height:100%;object-fit:cover;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="avatar-fallback" style="display:none; width:100%; height:100%; align-items:center; justify-content:center;">
                            <svg class="avatar-icon" viewBox="0 0 24 24" fill="#9ca3af" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
                                <path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm0 2c-3.33 0-10 1.67-10 5v2h20v-2c0-3.33-6.67-5-10-5z" />
                            </svg>
                        </div>
                    `;
                } else {
                    avatarPreview.innerHTML = `
                        <svg class="avatar-icon" viewBox="0 0 24 24" fill="#9ca3af" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
                            <path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm0 2c-3.33 0-10 1.67-10 5v2h20v-2c0-3.33-6.67-5-10-5z" />
                        </svg>
                    `;
                }
            }

            // ── Profile Completion % ──────────────────────────────────────
            const completionFields = [
                { val: d.profile_picture, weight: 1 },
                { val: d.username, weight: 1 },
                { val: d.email, weight: 1 },
                { val: d.phone_number || d.phone, weight: 1 },
                { val: d.designation, weight: 1 },
                { val: d.department, weight: 1 },
                { val: d.dob, weight: 1 },
                { val: d.gender, weight: 1 },
                { val: d.bio, weight: 1 },
                { val: d.address, weight: 1 },
                { val: d.nationality, weight: 1 },
                { val: d.blood_group, weight: 1 },
                { val: d.marital_status, weight: 1 },
                { val: d.languages, weight: 1 },
                { val: d.skills, weight: 1 },
                { val: d.interests, weight: 1 },
                { val: d.social_media, isJson: true, weight: 1 },
                { val: d.bank_details, isJson: true, weight: 1 },
                { val: d.education_background, isArray: true, weight: 1 },
                { val: d.work_experiences, isArray: true, weight: 1 },
                { val: d.emergency_contact, isJson: true, weight: 1 }
            ];

            let filledWeight = 0;
            let totalWeight = 0;

            completionFields.forEach(f => {
                totalWeight += f.weight;
                let isFilled = false;
                if (f.isArray) {
                    isFilled = Array.isArray(f.val) && f.val.length > 0;
                } else if (f.isJson) {
                    if (typeof f.val === 'object' && f.val !== null) {
                        // Check if any value inside the object is set
                        isFilled = Object.values(f.val).some(v => v && String(v).trim() !== '');
                    } else if (typeof f.val === 'string') {
                        isFilled = f.val.trim() !== '' && f.val !== '[]' && f.val !== '{}';
                    }
                } else {
                    isFilled = f.val && String(f.val).trim() !== '';
                }

                if (isFilled) filledWeight += f.weight;
            });

            const pct = Math.round((filledWeight / totalWeight) * 100);

            const badge = document.getElementById('profile-completion-badge');
            if (badge) badge.textContent = `Profile ${pct}% Complete`;

            // Update progress ring
            const ring = document.querySelector('.ring-fill');
            if (ring) {
                const circumference = 2 * Math.PI * 48;
                ring.style.strokeDasharray  = circumference;
                ring.style.strokeDashoffset = circumference - (pct / 100) * circumference;
            }

        } else {
            console.error('API Error:', result.message);
        }
    } catch (err) {
        console.error('Fetch Error:', err);
    }
}


// ── Emergency Contact: Multi-Row System ───────────────────────────────────────

function initEmergencyContacts() {
    // Wire up the "Add Contact" button (delegated — works after tab HTML loads)
    document.addEventListener('click', function(e) {
        if (e.target.closest('#btn-add-emergency-contact')) {
            addEmergencyContactRow();
        }
        // Remove a row
        if (e.target.closest('.ec-remove-btn')) {
            const row = e.target.closest('.ec-contact-row');
            if (row) {
                row.remove();
                updateEmergencyEmptyState();
            }
        }
    });
}

function loadEmergencyContacts(d) {
    const list = document.getElementById('emergency-contacts-list');
    if (!list) return;

    list.innerHTML = ''; // clear

    // Build contacts array from DB fields
    let contacts = [];

    // Try to parse emergency_contact as JSON array (multiple contacts)
    if (d.emergency_contact) {
        try {
            const parsed = JSON.parse(d.emergency_contact);
            if (Array.isArray(parsed)) {
                contacts = parsed;
            }
        } catch (e) {
            // Not JSON — treat as plain note for first contact
        }
    }

    // If emergency_contact_name exists and contacts array is still empty,
    // build the first contact from the flat fields
    if (contacts.length === 0 && (d.emergency_contact_name || d.emergency_contact_phone)) {
        contacts.push({
            name:  d.emergency_contact_name  || '',
            phone: d.emergency_contact_phone || '',
            note:  (!d.emergency_contact || d.emergency_contact.startsWith('[')) ? '' : d.emergency_contact
        });
    }

    // Render each contact as a row
    if (contacts.length > 0) {
        contacts.forEach(c => addEmergencyContactRow(c));
    }

    updateEmergencyEmptyState();
}

function addEmergencyContactRow(data = {}) {
    const list = document.getElementById('emergency-contacts-list');
    if (!list) return;

    const index = list.querySelectorAll('.ec-contact-row').length + 1;

    const row = document.createElement('div');
    row.className = 'ec-contact-row';
    row.style.cssText = `
        display: grid;
        grid-template-columns: 1fr 1fr 1fr auto;
        gap: 0.75rem;
        align-items: end;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        margin-bottom: 0.65rem;
        transition: box-shadow 0.2s;
    `;

    row.innerHTML = `
        <div class="form-group" style="margin:0;">
            <label style="font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; margin-bottom:4px; display:block;">
                Contact Name
            </label>
            <input type="text" class="ec-name" placeholder="e.g. Ramesh Kumar"
                   value="${data.name || ''}"
                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; font-size:0.875rem; outline:none; background:#fff;">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; margin-bottom:4px; display:block;">
                Phone Number
            </label>
            <input type="text" class="ec-phone" placeholder="e.g. +91 98765 43210"
                   value="${data.phone || ''}"
                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; font-size:0.875rem; outline:none; background:#fff;">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; margin-bottom:4px; display:block;">
                Relationship / Notes
            </label>
            <input type="text" class="ec-note" placeholder="e.g. Father, Spouse"
                   value="${data.note || ''}"
                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; font-size:0.875rem; outline:none; background:#fff;">
        </div>
        <button type="button" class="ec-remove-btn" title="Remove contact"
                style="height:38px; width:38px; border:1px solid #fecaca; border-radius:8px; background:#fff5f5;
                       color:#ef4444; cursor:pointer; display:flex; align-items:center; justify-content:center;
                       flex-shrink:0; transition:all 0.15s; margin-bottom:0;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                <path d="M10 11v6M14 11v6"></path>
                <path d="M9 6V4h6v2"></path>
            </svg>
        </button>
    `;

    list.appendChild(row);
    updateEmergencyEmptyState();
}

function updateEmergencyEmptyState() {
    const list  = document.getElementById('emergency-contacts-list');
    const empty = document.getElementById('emergency-contacts-empty');
    if (!list || !empty) return;
    const hasRows = list.querySelectorAll('.ec-contact-row').length > 0;
    empty.style.display = hasRows ? 'none' : 'block';
}

// ── End Emergency Contact System ──────────────────────────────────────────────

// ── Tag System for Skills & Interests ─────────────────────────────────────────

function initTagSystem() {
    setupTagInput('skills', 'skills-tag-input', 'skills-tag-list');
    setupTagInput('interests', 'interests-tag-input', 'interests-tag-list');
}

function setupTagInput(groupName, inputId, listId) {
    // Delegated event listener for tag inputs
    document.addEventListener('keydown', function(e) {
        if (e.target.id === inputId) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const val = e.target.value.trim().replace(/,$/, '');
                if (val) {
                    addTag(listId, val, groupName);
                    e.target.value = '';
                }
            }
        }
    });

    document.addEventListener('blur', function(e) {
        if (e.target.id === inputId) {
            const val = e.target.value.trim().replace(/,$/, '');
            if (val) {
                addTag(listId, val, groupName);
                e.target.value = '';
            }
        }
    }, true);

    // Remove buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.tag-remove-btn')) {
            const btn = e.target.closest('.tag-remove-btn');
            // ensure it's in the correct list
            if (btn.closest('#' + listId)) {
                btn.closest('.tag-item').remove();
            }
        }
    });
}

function loadTags(listId, csvString, groupName) {
    const list = document.getElementById(listId);
    if (!list) return;
    list.innerHTML = ''; // clear

    if (csvString && typeof csvString === 'string') {
        const items = csvString.split(',').map(s => s.trim()).filter(s => s);
        items.forEach(item => addTag(listId, item, groupName));
    }
}

function addTag(listId, label, groupName) {
    const list = document.getElementById(listId);
    if (!list) return;

    // Check for duplicates
    const exist = Array.from(list.querySelectorAll('.tag-text')).find(el => el.textContent.toLowerCase() === label.toLowerCase());
    if (exist) return;

    const div = document.createElement('div');
    div.className = 'tag-item';
    div.style.cssText = `
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        color: #334155;
        font-size: 0.8rem;
        padding: 4px 10px;
        border-radius: 6px;
        margin: 0 4px 4px 0;
        border: 1px solid #e2e8f0;
    `;
    div.innerHTML = `
        <span class="tag-text">${label}</span>
        <button type="button" class="tag-remove-btn" style="background:none; border:none; color:#94a3b8; font-size:1rem; margin-left:6px; cursor:pointer; padding:0; display:flex; align-items:center; line-height:1;">
            &times;
        </button>
        <input type="hidden" name="${groupName}[]" value="${label}">
    `;
    list.appendChild(div);
}

// ── End Tag System ────────────────────────────────────────────────────────────


// ── Security Tab: Change Password ─────────────────────────────────────────────

function initSecurityTab() {
    document.addEventListener('submit', async function(e) {
        if (e.target.id === 'changePasswordForm') {
            e.preventDefault();
            
            const currentPass = document.getElementById('currentPassword').value;
            const newPass     = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;
            const alertBox    = document.getElementById('changePasswordAlert');
            const submitBtn   = document.getElementById('changePasswordBtn');

            if (newPass !== confirmPass) {
                alert("New passwords do not match!");
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
            }

            try {
                const formData = new FormData();
                formData.append('current_password', currentPass);
                formData.append('new_password',     newPass);
                formData.append('confirm_password', confirmPass);

                const response = await fetch('../api/change_password.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    showToast("Success! Your password has been changed.");
                    e.target.reset();
                    initActivityLog(); // Refresh log
                    if (alertBox) {
                        alertBox.style.display = 'block';
                        alertBox.className = 'mt-3 p-3 rounded bg-green-50 text-green-700 border border-green-200';
                        alertBox.style.background = '#f0fdf4';
                        alertBox.style.color = '#15803d';
                        alertBox.style.padding = '12px';
                        alertBox.style.borderRadius = '8px';
                        alertBox.textContent = result.message;
                    }
                } else {
                    if (alertBox) {
                        alertBox.style.display = 'block';
                        alertBox.style.background = '#fef2f2';
                        alertBox.style.color = '#b91c1c';
                        alertBox.style.padding = '12px';
                        alertBox.style.borderRadius = '8px';
                        alertBox.textContent = result.message;
                    } else {
                        alert(result.message);
                    }
                }
            } catch (err) {
                console.error("Security update error:", err);
                alert("An error occurred. Please try again.");
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update Password';
                }
            }
        }
    });
}

// ── Activity Log: Fetch and Render ───────────────────────────────────────────

async function initActivityLog() {
    const list = document.querySelector('.activity-list');
    if (!list) return;

    try {
        const response = await fetch(`../api/fetch_activity_logs.php?v=${Date.now()}`);
        const result = await response.json();

        if (result.status === 'success' && result.data.length > 0) {
            list.innerHTML = result.data.map(log => {
                let icon = '📝';
                if (log.action_type.includes('login')) icon = '🔐';
                if (log.action_type.includes('password')) icon = '🔑';
                if (log.action_type.includes('profile')) icon = '👤';
                if (log.action_type.includes('task')) icon = '📋';

                const date = new Date(log.created_at);
                const timeStr = date.toLocaleString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    hour: 'numeric', 
                    minute: '2-digit',
                    hour12: true 
                });

                return `
                    <div class="activity-item">
                        <div class="activity-icon">${icon}</div>
                        <div class="activity-content">
                            <div class="activity-title">${log.description}</div>
                            <div class="activity-meta">${timeStr}</div>
                        </div>
                    </div>
                `;
            }).join('');
        } else if (result.data.length === 0) {
            list.innerHTML = `
                <div class="empty-state">
                    <p class="text-muted">No recent activity found.</p>
                </div>
            `;
        }
    } catch (err) {
        console.error("Activity log load error:", err);
    }
}

// ── Notifications Tab: Save Toggles ──────────────────────────────────────────

function initNotificationsTab() {
    document.addEventListener('change', function(e) {
        if (e.target.id === 'toggle-email' || e.target.id === 'toggle-push') {
            saveNotifications();
        }
    });
}

async function saveNotifications() {
    const emailToggle = document.getElementById('toggle-email');
    const pushToggle = document.getElementById('toggle-push');
    
    const prefs = {
        email: emailToggle ? emailToggle.checked : false,
        push:  pushToggle  ? pushToggle.checked  : false
    };

    try {
        const formData = new FormData();
        formData.append('notification_preferences', JSON.stringify(prefs));

        const response = await fetch('../api/update_user_info.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            showToast("Preferences updated.");
        }
    } catch (err) {
        console.error("Notifications save error:", err);
    }
}

// ── Education & Experience Table Managers ─────────────────────────────────────

function renderEducationTable() {
    const body = document.getElementById('education-table-body');
    if (!body) return;

    if (educationList.length === 0) {
        body.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <div class="empty-icon">🎓</div>
                        <p>No education history added yet.</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    body.innerHTML = educationList.map((edu, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${edu.degree || '—'}</td>
            <td>${edu.institution || '—'}</td>
            <td>${edu.field || '—'}</td>
            <td>${edu.year || '—'}</td>
            <td class="actions">
                <button class="icon-btn action-delete edu-delete-btn" data-index="${i}" title="Delete">🗑️</button>
            </td>
        </tr>
    `).join('');
}

function deleteEducation(index) {
    educationList.splice(index, 1);
    renderEducationTable();
}

function renderExperienceTable() {
    const body = document.getElementById('experience-table-body');
    if (!body) return;

    if (experienceList.length === 0) {
        body.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <div class="empty-icon">💼</div>
                        <p>No work experience added yet.</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    body.innerHTML = experienceList.map((exp, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${exp.company || '—'}</td>
            <td>${exp.title || '—'}</td>
            <td>${exp.years || '—'}</td>
            <td>${exp.responsibilities || '—'}</td>
            <td class="actions">
                <button class="icon-btn action-delete exp-delete-btn" data-index="${i}" title="Delete">🗑️</button>
            </td>
        </tr>
    `).join('');
}

function deleteExperience(index) {
    experienceList.splice(index, 1);
    renderExperienceTable();
}

function updateCustomSelect(selElmnt) {
    const wrapper = selElmnt.closest('.custom-select');
    if (!wrapper) return;
    
    const selectedDiv = wrapper.querySelector('.select-selected');
    const itemsDiv = wrapper.querySelector('.select-items');
    
    if (selectedDiv && itemsDiv && selElmnt.options[selElmnt.selectedIndex]) {
        selectedDiv.innerHTML = selElmnt.options[selElmnt.selectedIndex].innerHTML;
        
        // Update selection in list
        const items = itemsDiv.querySelectorAll('div');
        items.forEach((item, index) => {
            if (index === selElmnt.selectedIndex) {
                item.setAttribute('class', 'same-as-selected');
            } else {
                item.removeAttribute('class');
            }
        });
    }
}

function initTabs() {
    const tabs = document.querySelectorAll('.tab-btn');
    const pages = document.querySelectorAll('.tab-page');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const target = tab.getAttribute('data-target');
            pages.forEach(page => {
                page.classList.toggle('active', page.id === target);
            });
        });
    });
}

function initSubTabs() {
    const subTabs = document.querySelectorAll('.sub-tab-btn');
    const subPages = document.querySelectorAll('.sub-tab-page');

    subTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            subTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const target = tab.getAttribute('data-subtarget');
            subPages.forEach(page => {
                page.classList.toggle('active', page.id === target);
            });
        });
    });
}

function initFileInputs() {
    // Delegated click for file areas
    document.addEventListener('click', (e) => {
        const area = e.target.closest('.file-drop-area, .avatar-upload-zone');
        if (area) {
            const fileInput = area.querySelector('input[type="file"]');
            // Prevent recursion if clicking the file input itself
            if (fileInput && e.target !== fileInput) {
                fileInput.click();
            }
        }
    });

    // Delegated change for file inputs
    document.addEventListener('change', (e) => {
        if (e.target.matches('.file-input, .avatar-file-selector')) {
            const fileInput = e.target;
            const area = fileInput.closest('.file-drop-area, .avatar-upload-zone');
            const fileMsg = area?.querySelector('.file-msg, .avatar-selection-status');
            
            const fileName = fileInput.files[0]?.name;
            if (fileName && fileMsg) {
                fileMsg.textContent = fileName;
                fileMsg.style.color = "var(--accent-color)";
            } else if (fileMsg) {
                const defaultMsg = area.classList.contains('avatar-upload-zone') ? "Choose file or drag & drop" : "Choose file or drag & drop";
                fileMsg.textContent = defaultMsg;
                fileMsg.style.color = "var(--text-muted)";
            }
        }
    });
}

// Toasts function block
function showToast(message) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<span>✓</span> ${message}`;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Auto dismiss
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

function initButtons() {
    //Specific handlers for Education/Experience Save Buttons in Modals
    document.addEventListener('click', (e) => {
        if (e.target.id === 'btn-save-education-modal') {
            const degree = document.getElementById('modal-edu-level').value;
            const inst = document.getElementById('modal-edu-institution').value;
            const field = document.getElementById('modal-edu-field').value;
            const year = document.getElementById('modal-edu-year').value;

            if (!degree || !inst || !field || !year) {
                alert("Please fill in all required fields.");
                return;
            }

            educationList.push({ degree, institution: inst, field, year });
            renderEducationTable();
            
            // Close modal
            e.target.closest('.modal-overlay').classList.remove('active');
            // Reset fields
            document.getElementById('modal-edu-level').value = '';
            document.getElementById('modal-edu-institution').value = '';
            document.getElementById('modal-edu-field').value = '';
            document.getElementById('modal-edu-year').value = '';
            updateCustomSelect(document.getElementById('modal-edu-level'));
        }

        if (e.target.id === 'btn-save-experience-modal') {
            const company = document.getElementById('modal-exp-company').value;
            const title = document.getElementById('modal-exp-title').value;
            const years = document.getElementById('modal-exp-years').value;
            const responsibilities = document.getElementById('modal-exp-desc').value;

            if (!company || !title || !years) {
                alert("Please fill in company, title, and years.");
                return;
            }

            experienceList.push({ company, title, years, responsibilities });
            renderExperienceTable();

            // Close modal
            e.target.closest('.modal-overlay').classList.remove('active');
            // Reset fields
            document.getElementById('modal-exp-company').value = '';
            document.getElementById('modal-exp-title').value = '';
            document.getElementById('modal-exp-years').value = '';
            document.getElementById('modal-exp-desc').value = '';
        }
    });

    const actionButtons = document.querySelectorAll('.btn-primary');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Skip the main profile save button and modal-specific save buttons as they have their own handlers
            if (btn.id === 'btn-save-profile' || btn.id === 'btn-commit-avatar-upload' || btn.id.includes('modal')) return;

            if(!btn.hasAttribute('data-modal')) {
               e.preventDefault(); 
               if(btn.textContent.includes('Save') || btn.textContent.includes('Add')) {
                   showToast("Success! Item added.");
                   
                   const innerModal = btn.closest('.modal-overlay');
                   if(innerModal) {
                       innerModal.classList.remove('active');
                   }
               }
            }
        });
    });

    // Handle HR Document actions
    document.addEventListener('click', (e) => {
        const docBtn = e.target.closest('.icon-btn');
        if (docBtn && docBtn.closest('.section-hr-documents')) {
            const title = docBtn.closest('.doc-card')?.querySelector('.doc-title')?.textContent || 'Document';
            const action = docBtn.getAttribute('title');
            showToast(`${action}ing ${title}...`);
        }
    });
    // Save Changes and Reset button listeners
    document.addEventListener('click', (e) => {
        if (e.target.id === 'btn-save-profile') {
            saveChanges();
        }
        if (e.target.id === 'btn-reset-profile') {
            if (confirm("Are you sure you want to reset all changes?")) {
                loadUserInfo();
            }
        }
        // Delegated Avatar Upload
        const uploadBtn = e.target.closest('#btn-commit-avatar-upload');
        if (uploadBtn) {
            handleAvatarUpload(uploadBtn);
        }
    });
}

async function handleAvatarUpload(btn) {
    const fileInput = document.getElementById('avatar-file-selector');
    const file = fileInput?.files[0];
    
    if (!file) {
        alert("Please select a file first.");
        return;
    }

    const formData = new FormData();
    formData.append('profile_pic', file);

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = "Uploading...";

    try {
        const response = await fetch('../api/upload_profile_pic.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            showToast("Profile picture updated successfully!");
            initActivityLog(); // Refresh log
            // Update preview
            const avatarPreview = document.querySelector('.avatar-preview');
            if (avatarPreview) {
                avatarPreview.innerHTML = `
                    <img src="../../${result.filename}" 
                         alt="Profile" 
                         style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="avatar-fallback" style="display:none; width:100%; height:100%;">
                        <svg class="avatar-icon" viewBox="0 0 24 24" fill="#9ca3af" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
                            <path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm0 2c-3.33 0-10 1.67-10 5v2h20v-2c0-3.33-6.67-5-10-5z" />
                        </svg>
                    </div>
                `;
            }
            // Close modal
            const modal = btn.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
            
            // Reset input
            fileInput.value = '';
            const fileMsg = document.querySelector('.avatar-selection-status');
            if (fileMsg) fileMsg.textContent = "Choose file or drag & drop";

        } else {
            alert("Upload failed: " + result.message);
        }
    } catch (err) {
        console.error("Upload error:", err);
        alert("An error occurred during upload.");
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function saveChanges() {
    const saveBtn = document.getElementById('btn-save-profile');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
    }

    try {
        const formData = new FormData();

        // ── Helper: get field value safely ───────────────────────────
        function getVal(id) {
            const el = document.getElementById(id);
            return el ? el.value : '';
        }

        // ── SECTION 2 & 4: Fields ────────────────────────────────────
        formData.append('phone_number', getVal('pi-phone'));
        formData.append('dob',          getVal('pi-dob'));
        formData.append('gender',       getVal('pi-gender'));
        formData.append('bio',          getVal('pi-bio'));
        formData.append('marital_status', getVal('pi-marital-status'));
        formData.append('blood_group',    getVal('pi-blood-group'));
        formData.append('nationality',    getVal('pi-nationality'));
        formData.append('languages',      getVal('pi-languages'));

        // ── SECTION 5: Contact ───────────────────────────────────────
        formData.append('address',      getVal('pi-address'));
        formData.append('city',         getVal('pi-city'));
        formData.append('state',        getVal('pi-state'));
        formData.append('country',      getVal('pi-country'));
        formData.append('postal_code',  getVal('pi-postal-code'));

        // ── SECTION 6: Emergency Contacts ────────────────────────────
        const ecRows = document.querySelectorAll('.ec-contact-row');
        const contacts = Array.from(ecRows).map(row => ({
            name:  row.querySelector('.ec-name').value,
            phone: row.querySelector('.ec-phone').value,
            note:  row.querySelector('.ec-note').value
        }));
        formData.append('emergency_contact', JSON.stringify(contacts));
        
        // Also sync first contact to legacy fields if available
        if (contacts.length > 0) {
            formData.append('emergency_contact_name',  contacts[0].name);
            formData.append('emergency_contact_phone', contacts[0].phone);
        }

        // ── SECTION 7: Skills & Interests ────────────────────────────
        function getTags(listId) {
            const tags = Array.from(document.querySelectorAll(`#${listId} .tag-text`));
            return tags.map(t => t.textContent).join(', ');
        }
        formData.append('skills',    getTags('skills-tag-list'));
        formData.append('interests', getTags('interests-tag-list'));

        const bankDetails = {
            bank_name:      getVal('pi-bank-name'),
            account_holder: getVal('pi-account-holder'),
            account_number: getVal('pi-account-number'),
            ifsc_code:      getVal('pi-ifsc')
        };
        formData.append('bank_details', JSON.stringify(bankDetails));

        // ── SECTION 8: Social Media ──────────────────────────────────
        const socialMedia = {
            linkedin:  getVal('pi-linkedin'),
            twitter:   getVal('pi-twitter'),
            facebook:  getVal('pi-facebook'),
            instagram: getVal('pi-instagram'),
            github:    getVal('pi-github'),
            youtube:   getVal('pi-youtube')
        };
        formData.append('social_media', JSON.stringify(socialMedia));

        // ── SECTION 9 & 10: Tables ───────────────────────────────────
        console.log("Saving Education:", educationList);
        console.log("Saving Work Experience:", experienceList);
        formData.append('education_background', JSON.stringify(educationList));
        formData.append('work_experiences',     JSON.stringify(experienceList));

        const response = await fetch('../api/update_user_info.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            showToast("Success! Your profile has been updated.");
            loadUserInfo(); // refresh to ensure data consistency
            initActivityLog(); // Refresh log
        } else {
            alert("Save failed: " + result.message);
        }

    } catch (err) {
        console.error("Save error:", err);
        alert("An error occurred while saving.");
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        }
    }
}

// ── Delegated Deletion Handlers ──
document.addEventListener('click', function(e) {
    const eduDeleteBtn = e.target.closest('.edu-delete-btn');
    if (eduDeleteBtn) {
        const index = parseInt(eduDeleteBtn.getAttribute('data-index'));
        if (!isNaN(index) && confirm("Remove this educational background?")) {
            educationList.splice(index, 1);
            renderEducationTable();
            showToast("Educational entry removed. Click 'Save Changes' to finalize.");
        }
    }

    const expDeleteBtn = e.target.closest('.exp-delete-btn');
    if (expDeleteBtn) {
        const index = parseInt(expDeleteBtn.getAttribute('data-index'));
        if (!isNaN(index) && confirm("Remove this work experience entry?")) {
            experienceList.splice(index, 1);
            renderExperienceTable();
            showToast("Experience entry removed. Click 'Save Changes' to finalize.");
        }
    }
});

function initModals() {
    // Delegated Modal Opening
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-modal]');
        if (trigger) {
            e.preventDefault();
            const modalId = trigger.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        }
    });

    // Delegated Modal Closing
    document.addEventListener('click', (e) => {
        // Close via close button
        const closeBtn = e.target.closest('.modal-close');
        if (closeBtn) {
            const modal = closeBtn.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        }

        // Close via overlay click
        if (e.target.matches('.modal-overlay')) {
            e.target.classList.remove('active');
        }
    });
}

// --- Custom Select Dropdown Implementation ---
function initCustomSelects() {
    var x = document.querySelectorAll("select:not(.custom-select-initialized)");
    for (var i = 0; i < x.length; i++) {
        var selElmnt = x[i];
        if (!selElmnt || selElmnt.options.length === 0) continue; // Skip empty selects or nulls

        selElmnt.classList.add("custom-select-initialized");
        
        var wrapper = document.createElement("div");
        wrapper.setAttribute("class", "custom-select");
        if (selElmnt.parentNode) {
            selElmnt.parentNode.insertBefore(wrapper, selElmnt);
            wrapper.appendChild(selElmnt);
        } else {
            continue;
        }

        var a = document.createElement("div");
        a.setAttribute("class", "select-selected");
        
        // Safely set selection text
        const selectedIndex = selElmnt.selectedIndex >= 0 ? selElmnt.selectedIndex : 0;
        a.innerHTML = selElmnt.options[selectedIndex] ? selElmnt.options[selectedIndex].innerHTML : 'Select...';
        wrapper.appendChild(a);
        
        var b = document.createElement("div");
        b.setAttribute("class", "select-items select-hide");
        for (var j = 0; j < selElmnt.length; j++) {
            var c = document.createElement("div");
            c.innerHTML = selElmnt.options[j].innerHTML;
            if (j === selElmnt.selectedIndex) {
                c.setAttribute("class", "same-as-selected");
            }
            c.addEventListener("click", function(e) {
                const s = this.parentNode.parentNode.getElementsByTagName("select")[0];
                const h = this.parentNode.previousSibling;
                for (let i = 0; i < s.length; i++) {
                    if (s.options[i].innerHTML == this.innerHTML) {
                        s.selectedIndex = i;
                        h.innerHTML = this.innerHTML;
                        const y = this.parentNode.getElementsByClassName("same-as-selected");
                        for (let k = 0; k < y.length; k++) {
                            y[k].removeAttribute("class");
                        }
                        this.setAttribute("class", "same-as-selected");
                        break;
                    }
                }
                h.click();
                const event = new Event("change", { bubbles: true });
                s.dispatchEvent(event);
            });
            b.appendChild(c);
        }
        wrapper.appendChild(b);
        a.addEventListener("click", function(e) {
            e.stopPropagation();
            closeAllSelect(this);
            this.nextSibling.classList.toggle("select-hide");
            this.classList.toggle("select-arrow-active");
        });
    }
}

function closeAllSelect(elmnt) {
    var x = document.getElementsByClassName("select-items");
    var y = document.getElementsByClassName("select-selected");
    var arrNo = [];
    for (var i = 0; i < y.length; i++) {
        if (elmnt == y[i]) {
            arrNo.push(i)
        } else {
            y[i].classList.remove("select-arrow-active");
        }
    }
    for (var i = 0; i < x.length; i++) {
        if (arrNo.indexOf(i) === -1) {
            x[i].classList.add("select-hide");
        }
    }
}

document.addEventListener("click", closeAllSelect);

/**
 * --- AG-MODAL SYSTEM (Hardened) ---
 */
document.addEventListener('click', (e) => {
    // 1. Open Trigger
    const trigger = e.target.closest('#ag-trigger-upload');
    if (trigger) {
        e.preventDefault();
        
        let modal = document.getElementById('ag-upload-modal') || document.querySelector('.ag-modal-overlay');
        
        if (modal) {
            modal.classList.add('ag-active');
        } else {
            console.error("AG CRITICAL: No modal found even with fallback class search.");
        }
    }

    // 1.1 Education Open Trigger
    const eduTrigger = e.target.closest('#ag-trigger-education');
    if (eduTrigger) {
        e.preventDefault();
        const modal = document.getElementById('ag-education-modal');
        if (modal) modal.classList.add('ag-active');
    }

    // 1.2 Experience Open Trigger
    const expTrigger = e.target.closest('#ag-trigger-experience');
    if (expTrigger) {
        e.preventDefault();
        const modal = document.getElementById('ag-experience-modal');
        if (modal) modal.classList.add('ag-active');
    }

    // 1.3 Document Open Trigger
    const docTrigger = e.target.closest('#ag-trigger-document');
    if (docTrigger) {
        e.preventDefault();
        const modal = document.getElementById('ag-document-modal');
        if (modal) modal.classList.add('ag-active');
    }

    // 2. Close Logic
    const closeBtn = e.target.closest('#ag-close-upload, #ag-close-education, #ag-close-experience, #ag-close-document');
    const isOverlay = e.target.classList.contains('ag-modal-overlay');
    const isRemoveBtn = e.target.closest('#ag-remove-preview');
    
    if (closeBtn || isOverlay) {
        const modal = document.getElementById('ag-upload-modal') 
                   || document.getElementById('ag-education-modal')
                   || document.getElementById('ag-experience-modal')
                   || document.getElementById('ag-document-modal')
                   || document.querySelector('.ag-modal-overlay.ag-active');
        if (modal) {
            modal.classList.remove('ag-active');
            // Cleanup: Reset preview/forms UI when closing
            resetAgUploadUI();
            resetAgEducationUI();
            resetAgExperienceUI();
            resetAgDocumentUI();
        }
    }

    if (isRemoveBtn) {
        resetAgUploadUI();
    }

    // 3. Submit Button
    const saveBtn = e.target.closest('#ag-save-photo');
    if (saveBtn) {
        handleAgSubmitUpload(saveBtn);
    }

    // 4. Education Save Button
    const eduSaveBtn = e.target.closest('#ag-save-education');
    if (eduSaveBtn) {
        handleAgEducationSubmit(eduSaveBtn);
    }

    // 5. Experience Save Button
    const expSaveBtn = e.target.closest('#ag-save-experience');
    if (expSaveBtn) {
        handleAgExperienceSubmit(expSaveBtn);
    }

    // 6. Document Save Button
    const docSaveBtn = e.target.closest('#ag-save-document');
    if (docSaveBtn) {
        handleAgDocumentSubmit(docSaveBtn);
    }

    // 7. Document Upload Zone Click
    const zoneTrigger = e.target.closest('#ag-doc-trigger-select');
    if (zoneTrigger) {
        document.getElementById('ag-doc-file-input')?.click();
    }
});

// File Selection Change Listener (Updated for Preview)
document.addEventListener('change', (e) => {
    if (e.target.id === 'ag-avatar-input') {
        const file = e.target.files[0];
        if (file) {
            const previewBox = document.getElementById('ag-preview-box');
            const previewImg = document.getElementById('ag-preview-img');
            const instructions = document.getElementById('ag-upload-instructions');
            
            if (previewBox && previewImg && instructions) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    previewBox.style.display = 'block';
                    instructions.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        }
    }

    // Document File Selection
    if (e.target.id === 'ag-doc-file-input') {
        const file = e.target.files[0];
        const preview = document.getElementById('ag-doc-preview-name');
        const instructions = document.getElementById('ag-doc-instructions');
        if (file) {
            if (preview) {
                preview.textContent = "Selected: " + file.name;
                preview.style.display = 'block';
            }
            if (instructions) instructions.style.display = 'none';
        }
    }
});

/**
 * Reset the Upload Modal UI
 */
function resetAgUploadUI() {
    const input = document.getElementById('ag-avatar-input');
    const previewBox = document.getElementById('ag-preview-box');
    const instructions = document.getElementById('ag-upload-instructions');
    
    if (input) input.value = '';
    if (previewBox) previewBox.style.display = 'none';
    if (instructions) instructions.style.display = 'flex';
}

async function handleAgSubmitUpload(btn) {
    const input = document.getElementById('ag-avatar-input');
    const file = input?.files[0];
    
    if (!file) {
        alert("Please select a photo first.");
        return;
    }

    const formData = new FormData();
    formData.append('profile_pic', file);

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block; font-size:1.1rem; transform-origin:center; animation:ag-spin 1s linear infinite;">⌛</span> Uploading...';
    
    try {
        const response = await fetch('../api/upload_profile_pic.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            showToast("Profile Picture Updated!");
            
            // Close modal and reset UI
            const modal = document.getElementById('ag-upload-modal') || document.querySelector('.ag-active');
            if (modal) modal.classList.remove('ag-active');
            resetAgUploadUI();
            
            // Update Preview
            const avatars = document.querySelectorAll('.avatar-preview');
            avatars.forEach(av => {
                av.innerHTML = `<img src="../../${result.filename}" style="width:100%;height:100%;object-fit:cover;">`;
            });
            
            initActivityLog();
        } else {
            alert("Upload Failed: " + result.message);
        }
    } catch (err) {
        console.error("AG Upload Critical Error:", err);
        alert("Network error: Check your connection.");
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

/**
 * Reset the Education Modal UI
 */
function resetAgEducationUI() {
    const fields = ['ag-edu-degree', 'ag-edu-institution', 'ag-edu-field', 'ag-edu-year'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

/**
 * Handle Education Submit
 */
async function handleAgEducationSubmit(btn) {
    const degree = document.getElementById('ag-edu-degree')?.value;
    const inst   = document.getElementById('ag-edu-institution')?.value;
    const field  = document.getElementById('ag-edu-field')?.value;
    const year   = document.getElementById('ag-edu-year')?.value;

    if (!degree || !inst || !field || !year) {
        alert("Please fill in all mandatory education fields (*).");
        return;
    }

    // Add to global list
    educationList.push({ 
        degree: degree, 
        institution: inst, 
        field: field, 
        year: year 
    });

    // Re-render table instantly
    renderEducationTable();

    // Show toast
    showToast("Education history updated locally. Remember to click 'Save Changes' at the bottom to sync with server!");

    // Close and Reset
    const modal = document.getElementById('ag-education-modal');
    if (modal) modal.classList.remove('ag-active');
    resetAgEducationUI();
}

/**
 * Reset the Experience Modal UI
 */
function resetAgExperienceUI() {
    const fields = ['ag-exp-company', 'ag-exp-title', 'ag-exp-years', 'ag-exp-desc'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

/**
 * Handle Experience Submit
 */
async function handleAgExperienceSubmit(btn) {
    const company = document.getElementById('ag-exp-company')?.value;
    const title   = document.getElementById('ag-exp-title')?.value;
    const years   = document.getElementById('ag-exp-years')?.value;
    const desc    = document.getElementById('ag-exp-desc')?.value;

    if (!company || !title || !years) {
        alert("Please fill in Company, Job Title, and Years (*).");
        return;
    }

    // Add to global list
    experienceList.push({ 
        company: company, 
        title: title, 
        years: years, 
        responsibilities: desc || ''
    });

    // Re-render table instantly
    renderExperienceTable();

    // Show toast
    showToast("Work experience updated locally. Remember to click 'Save Changes' at the bottom to sync with server!");

    // Close and Reset
    const modal = document.getElementById('ag-experience-modal');
    if (modal) modal.classList.remove('ag-active');
    resetAgExperienceUI();
}

/**
 * Render Documents Grid
 */
function renderDocumentsGrid() {
    const grid = document.getElementById('ag-documents-grid');
    const empty = document.getElementById('ag-documents-empty');
    if (!grid || !empty) return;

    if (documentsList.length === 0) {
        grid.style.display = 'none';
        empty.style.display = 'block';
        return;
    }

    grid.style.display = 'grid';
    empty.style.display = 'none';

    grid.innerHTML = documentsList.map(doc => `
        <div class="ag-doc-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; display:flex; flex-direction:column; gap:0.75rem; transition:all 0.2s;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <div style="width:40px; height:40px; border-radius:8px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                    ${doc.extension === 'pdf' ? '📕' : '📄'}
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:0.7rem; text-transform:uppercase; font-weight:700; color:#64748b; letter-spacing:0.02em;">${doc.type}</div>
                    <div style="font-size:0.85rem; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${doc.name}</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:0.5rem; border-top:1px solid #f1f5f9; pt:0.75rem; margin-top:0.25rem; padding-top:0.75rem;">
                <a href="../../${doc.path}" target="_blank" class="btn" style="flex:1; padding:6px; font-size:0.75rem; background:#f8fafc; border:1px solid #e2e8f0; color:#64748b; text-align:center; text-decoration:none; border-radius:6px; font-weight:600;">View File</a>
                <button onclick="deleteDocument('${doc.id}')" class="btn" style="padding:6px 10px; font-size:0.75rem; background:#fff1f2; border:1px solid #fecaca; color:#e11d48; border-radius:6px; cursor:pointer;" title="Delete">🗑️</button>
            </div>
        </div>
    `).join('');
}

/**
 * Handle Document Submit
 */
async function handleAgDocumentSubmit(btn) {
    const type = document.getElementById('ag-doc-type')?.value;
    const name = document.getElementById('ag-doc-name')?.value;
    const fileInput = document.getElementById('ag-doc-file-input');
    const file = fileInput?.files[0];

    if (!type || !name || !file) {
        alert("Please fill all mandatory fields and select a file.");
        return;
    }

    const formData = new FormData();
    formData.append('document', file);
    formData.append('doc_type', type);
    formData.append('doc_name', name);

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = 'Uploading...';

    try {
        const response = await fetch('../api/upload_user_document.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            showToast("Document uploaded successfully!");
            documentsList = result.documents;
            renderDocumentsGrid();
            
            // Close and Reset
            const modal = document.getElementById('ag-document-modal');
            if (modal) modal.classList.remove('ag-active');
            resetAgDocumentUI();
        } else {
            alert("Upload failed: " + result.message);
        }
    } catch (err) {
        console.error("Doc Upload Error:", err);
        alert("A network error occurred.");
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

/**
 * Delete Document
 */
async function deleteDocument(docId) {
    if (!confirm("Are you sure you want to delete this document?")) return;

    try {
        const response = await fetch('../api/delete_user_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ doc_id: docId })
        });
        const result = await response.json();

        if (result.status === 'success') {
            showToast("Document deleted.");
            documentsList = result.documents;
            renderDocumentsGrid();
        } else {
            alert("Delete failed: " + result.message);
        }
    } catch (err) {
        console.error("Doc Delete Error:", err);
    }
}

/**
 * Reset Document UI
 */
function resetAgDocumentUI() {
    const type = document.getElementById('ag-doc-type');
    const name = document.getElementById('ag-doc-name');
    const fileInput = document.getElementById('ag-doc-file-input');
    const preview = document.getElementById('ag-doc-preview-name');
    const instructions = document.getElementById('ag-doc-instructions');

    if (type) type.value = '';
    if (name) name.value = '';
    if (fileInput) fileInput.value = '';
    if (preview) {
        preview.textContent = '';
        preview.style.display = 'none';
    }
    if (instructions) instructions.style.display = 'block';
}
