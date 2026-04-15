/* ============================================
   custom.js — Manage Policies & Notices Logic
   ============================================ */

(function () {

    /* ── URL resolver: local XAMPP vs production ── */
    function resolveUrl(storedUrl) {
        if (!storedUrl) return storedUrl;
        if (/^https?:\/\//i.test(storedUrl)) return storedUrl;
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        // On XAMPP: pathname has >=4 parts → first is project sub-dir ('connect')
        const subDir = (pathParts.length >= 4) ? ('/' + pathParts[0]) : '';
        if (subDir && storedUrl.startsWith(subDir + '/')) return storedUrl;
        if (subDir && storedUrl.startsWith('/uploads/'))  return subDir + storedUrl;
        return storedUrl;
    }


    /* ── DOM refs ─────────────────────────────── */
    const policyListEl     = document.getElementById('hrPolicyList');
    const noticeListEl     = document.getElementById('hrNoticeList');
    const manageListView   = document.getElementById('manageListView');

    // Policy edit view
    const manageEditView   = document.getElementById('manageEditView');
    const editForm         = document.getElementById('hrEditPolicyForm');
    const inputId          = document.getElementById('editPolicyId');
    const inputHeading     = document.getElementById('editPolicyHeading');
    const inputShortDesc   = document.getElementById('editPolicyShortDesc');
    const inputLongDesc    = document.getElementById('editPolicyLongDesc');   // hidden textarea
    const btnBack          = document.getElementById('btnBackToList');
    const btnSave          = document.getElementById('hrBtnSaveChanges');

    // Notice edit view
    const manageEditNoticeView  = document.getElementById('manageEditNoticeView');
    const editNoticeForm        = document.getElementById('hrEditNoticeForm');
    const inputNoticeId         = document.getElementById('editNoticeId');
    const inputNoticeTitle      = document.getElementById('editNoticeTitle');
    const inputNoticeShortDesc  = document.getElementById('editNoticeShortDesc');
    const inputNoticeLongDesc   = document.getElementById('editNoticeLongDesc'); // hidden textarea
    const btnBackNotice         = document.getElementById('btnBackToListFromNotice');
    const btnSaveNotice         = document.getElementById('hrBtnSaveNoticeChanges');
    const noticeAttachmentRow   = document.getElementById('noticeAttachmentRow');
    const noticeAttachmentInfo  = document.getElementById('noticeAttachmentInfo');

    if (!policyListEl || !noticeListEl) return;

    /* ── Quill toolbar config (shared) ────────── */
    const QUILL_TOOLBAR = [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ indent: '-1' }, { indent: '+1' }],
        ['blockquote', 'code-block'],
        ['link'],
        ['clean']
    ];

    /* ── Init: Edit Policy Quill ──────────────── */
    const editPolicyQuill = new Quill('#editPolicyQuillEditor', {
        theme: 'snow',
        placeholder: 'Enter the full detailed description of the policy here...',
        modules: { toolbar: QUILL_TOOLBAR }
    });
    editPolicyQuill.on('text-change', () => {
        inputLongDesc.value = editPolicyQuill.root.innerHTML === '<p><br></p>'
            ? '' : editPolicyQuill.root.innerHTML;
    });

    /* ── Init: Edit Notice Quill ──────────────── */
    const editNoticeQuill = new Quill('#editNoticeQuillEditor', {
        theme: 'snow',
        placeholder: 'Enter the full detailed description of the notice here...',
        modules: { toolbar: QUILL_TOOLBAR }
    });
    editNoticeQuill.on('text-change', () => {
        inputNoticeLongDesc.value = editNoticeQuill.root.innerHTML === '<p><br></p>'
            ? '' : editNoticeQuill.root.innerHTML;
    });

    let currentPolicies = [];
    let currentNotices  = [];

    /* ── Helper: hide all edit views ────────── */
    function showListView() {
        if (manageListView)      manageListView.style.display      = 'block';
        if (manageEditView)      manageEditView.style.display      = 'none';
        if (manageEditNoticeView) manageEditNoticeView.style.display = 'none';
    }

    /* ════════════════════════════════════════
       FETCH (both policies + notices)
    ════════════════════════════════════════ */
    async function fetchAll() {
        policyListEl.innerHTML = '<div class="hr-policy-list-loader"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading policies...</div>';
        noticeListEl.innerHTML = '<div class="hr-policy-list-loader"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading notices...</div>';

        try {
            const res  = await fetch('api/get_policies.php?v=' + Date.now());
            const data = await res.json();

            if (data.success) {
                currentPolicies = data.policies || data.data || [];
                currentNotices  = data.notices  || [];
                renderPolicies();
                renderNotices();
            } else {
                const err = '<div class="hr-policy-list-loader" style="color:#ef4444;">Failed to load.</div>';
                policyListEl.innerHTML = err;
                noticeListEl.innerHTML = err;
            }
        } catch (e) {
            console.error(e);
            const err = '<div class="hr-policy-list-loader" style="color:#ef4444;">Connection error.</div>';
            policyListEl.innerHTML = err;
            noticeListEl.innerHTML = err;
        }
    }

    /* ════════════════════════════════════════
       RENDER POLICIES
    ════════════════════════════════════════ */
    function renderPolicies() {
        policyListEl.innerHTML = '';

        if (!currentPolicies.length) {
            policyListEl.innerHTML = '<div class="hr-policy-list-loader">No policies found. Create one in the Policy tab.</div>';
            return;
        }

        currentPolicies.forEach(policy => {
            const dateStr   = new Date(policy.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const mandatory = policy.is_mandatory == 1;

            const item = document.createElement('div');
            item.className = 'hr-policy-item';
            item.innerHTML = `
                <div class="hr-policy-item-content">
                    <div class="hr-policy-item-title">${policy.heading}</div>
                    <div class="hr-policy-item-desc">${policy.short_desc}</div>
                    <div class="hr-policy-item-meta">
                        <i class="fa-regular fa-calendar" style="margin-right:3px;"></i> Last updated: ${dateStr}
                        ${policy.is_active == 1 ? '<span class="hr-policy-item-badge">Active</span>' : '<span class="hr-policy-item-badge" style="background:#fee2e2;color:#dc2626;">Inactive</span>'}
                        ${mandatory ? '<span class="hr-policy-item-badge" style="background:#eef2ff;color:#4338ca;border-color:#c7d2fe;">Mandatory</span>' : ''}
                    </div>
                </div>
                <div class="hr-policy-item-actions">
                    <button class="hr-btn-edit" data-id="${policy.id}">
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                    <button class="hr-btn-delete" data-id="${policy.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>`;

            policyListEl.appendChild(item);
        });

        /* Edit handlers */
        policyListEl.querySelectorAll('.hr-btn-edit').forEach(btn => {
            btn.addEventListener('click', e => openPolicyEdit(e.currentTarget.dataset.id));
        });

        /* Delete handlers */
        policyListEl.querySelectorAll('.hr-btn-delete').forEach(btn => {
            btn.addEventListener('click', async e => {
                const id  = e.currentTarget.dataset.id;
                const btn = e.currentTarget;
                if (!confirm('Permanently delete this policy? This cannot be undone.')) return;
                await deleteItem(btn, 'api/delete_policy.php', id, () => {
                    if (window.showToast) window.showToast('Policy deleted.');
                    fetchAll();
                });
            });
        });
    }

    /* ════════════════════════════════════════
       RENDER NOTICES
    ════════════════════════════════════════ */
    function renderNotices() {
        noticeListEl.innerHTML = '';

        if (!currentNotices.length) {
            noticeListEl.innerHTML = '<div class="hr-policy-list-loader">No notices found. Broadcast one in the Notice tab.</div>';
            return;
        }

        currentNotices.forEach(notice => {
            const dateStr   = new Date(notice.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const mandatory = notice.is_mandatory == 1;
            const hasFile   = !!notice.attachment;

            const item = document.createElement('div');
            item.className = 'hr-policy-item';
            item.innerHTML = `
                <div class="hr-policy-item-content">
                    <div class="hr-policy-item-title">
                        <i class="fa-solid fa-bullhorn" style="color:#0891b2;font-size:0.8rem;margin-right:5px;"></i>
                        ${notice.title}
                    </div>
                    <div class="hr-policy-item-desc">${notice.short_desc || ''}</div>
                    <div class="hr-policy-item-meta">
                        <i class="fa-regular fa-calendar" style="margin-right:3px;"></i> Broadcast: ${dateStr}
                        ${notice.is_active == 1 ? '<span class="hr-policy-item-badge">Active</span>' : '<span class="hr-policy-item-badge" style="background:#fee2e2;color:#dc2626;">Inactive</span>'}
                        ${mandatory ? '<span class="hr-policy-item-badge" style="background:#fffbeb;color:#b45309;border-color:#fde68a;">Mandatory</span>' : ''}
                        ${hasFile   ? '<span class="hr-policy-item-badge" style="background:#f0f9ff;color:#0369a1;border-color:#bae6fd;"><i class="fa-solid fa-paperclip"></i> Attachment</span>' : ''}
                    </div>
                </div>
                <div class="hr-policy-item-actions">
                    <button class="hr-btn-edit" data-id="${notice.id}">
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                    <button class="hr-btn-delete" data-id="${notice.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>`;

            noticeListEl.appendChild(item);
        });

        /* Edit handlers */
        noticeListEl.querySelectorAll('.hr-btn-edit').forEach(btn => {
            btn.addEventListener('click', e => openNoticeEdit(e.currentTarget.dataset.id));
        });

        /* Delete handlers */
        noticeListEl.querySelectorAll('.hr-btn-delete').forEach(btn => {
            btn.addEventListener('click', async e => {
                const id  = e.currentTarget.dataset.id;
                const btn = e.currentTarget;
                if (!confirm('Permanently delete this notice? This cannot be undone.')) return;
                await deleteItem(btn, 'api/delete_notice.php', id, () => {
                    if (window.showToast) window.showToast('Notice deleted.');
                    fetchAll();
                });
            });
        });
    }

    /* ════════════════════════════════════════
       POLICY EDIT OPEN / SAVE
    ════════════════════════════════════════ */
    function openPolicyEdit(id) {
        const policy = currentPolicies.find(p => p.id == id);
        if (!policy) return;

        inputId.value        = policy.id;
        inputHeading.value   = policy.heading;
        inputShortDesc.value = policy.short_desc;

        // Load existing content into Quill (supports both plain text & HTML)
        editPolicyQuill.root.innerHTML = policy.long_desc || '';
        inputLongDesc.value = policy.long_desc || '';

        manageListView.style.display      = 'none';
        manageEditView.style.display      = 'block';
        if (manageEditNoticeView) manageEditNoticeView.style.display = 'none';
    }

    if (btnBack) btnBack.addEventListener('click', () => {
        editPolicyQuill.setText('');
        inputLongDesc.value = '';
        showListView();
    });

    if (editForm) {
        editForm.addEventListener('submit', async e => {
            e.preventDefault();

            const longDesc = inputLongDesc.value.trim();
            if (!longDesc) {
                if (window.showToast) window.showToast('Detailed Description cannot be empty.', 'error');
                editPolicyQuill.focus();
                return;
            }

            const payload = {
                id:        inputId.value,
                heading:   inputHeading.value.trim(),
                shortDesc: inputShortDesc.value.trim(),
                longDesc:  longDesc,
            };
            const orig = btnSave.innerHTML;
            btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            btnSave.disabled  = true;

            try {
                const res  = await fetch('api/update_policy.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    if (window.showToast) window.showToast('Policy updated successfully!');
                    editPolicyQuill.setText('');
                    inputLongDesc.value = '';
                    showListView();
                    fetchAll();
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                console.error(err);
                if (window.showToast) window.showToast('Failed to update. Please try again.', 'error');
            } finally {
                btnSave.innerHTML = orig;
                btnSave.disabled  = false;
            }
        });
    }

    /* ════════════════════════════════════════
       NOTICE EDIT OPEN / SAVE
    ════════════════════════════════════════ */
    function openNoticeEdit(id) {
        const notice = currentNotices.find(n => n.id == id);
        if (!notice) return;

        inputNoticeId.value        = notice.id;
        inputNoticeTitle.value     = notice.title;
        inputNoticeShortDesc.value = notice.short_desc || '';

        // Load existing content into Quill
        editNoticeQuill.root.innerHTML = notice.long_desc || '';
        inputNoticeLongDesc.value = notice.long_desc || '';

        if (notice.attachment) {
            noticeAttachmentRow.style.display = 'block';
            const filename = notice.attachment.split('/').pop();
            noticeAttachmentInfo.innerHTML = `
                <a href="${resolveUrl(notice.attachment)}" target="_blank" rel="noopener"
                   style="color:#0891b2; text-decoration:underline; font-weight:600;">
                    <i class="fa-solid fa-paperclip"></i> ${filename}
                </a>
                <span style="color:#a8a29e; margin-left:0.5rem; font-size:0.75rem;">
                    (attachment cannot be changed — delete and re-upload if needed)
                </span>`;
        } else {
            noticeAttachmentRow.style.display = 'none';
        }

        manageListView.style.display         = 'none';
        manageEditView.style.display         = 'none';
        manageEditNoticeView.style.display   = 'block';
    }

    if (btnBackNotice) btnBackNotice.addEventListener('click', () => {
        editNoticeQuill.setText('');
        inputNoticeLongDesc.value = '';
        showListView();
    });

    if (editNoticeForm) {
        editNoticeForm.addEventListener('submit', async e => {
            e.preventDefault();

            const longDesc = inputNoticeLongDesc.value.trim();
            if (!longDesc) {
                if (window.showToast) window.showToast('Detailed Content cannot be empty.', 'error');
                editNoticeQuill.focus();
                return;
            }

            const payload = {
                id:        inputNoticeId.value,
                title:     inputNoticeTitle.value.trim(),
                shortDesc: inputNoticeShortDesc.value.trim(),
                longDesc:  longDesc,
            };
            const orig = btnSaveNotice.innerHTML;
            btnSaveNotice.innerHTML  = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            btnSaveNotice.disabled   = true;

            try {
                const res  = await fetch('api/update_notice.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    if (window.showToast) window.showToast('Notice updated successfully!');
                    editNoticeQuill.setText('');
                    inputNoticeLongDesc.value = '';
                    showListView();
                    fetchAll();
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                console.error(err);
                if (window.showToast) window.showToast('Failed to update. Please try again.', 'error');
            } finally {
                btnSaveNotice.innerHTML = orig;
                btnSaveNotice.disabled  = false;
            }
        });
    }

    /* ════════════════════════════════════════
       SHARED DELETE HELPER
    ════════════════════════════════════════ */
    async function deleteItem(btn, endpoint, id, onSuccess) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btn.disabled  = true;
        try {
            const res  = await fetch(endpoint, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id })
            });
            const data = await res.json();
            if (data.success) {
                onSuccess();
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            console.error(err);
            if (window.showToast) window.showToast('Failed to delete. Please try again.', 'error');
            btn.innerHTML = orig;
            btn.disabled  = false;
        }
    }

    /* ── Refresh on tab click ────────────────── */
    const customTabBtn = document.getElementById('hrToggleCustom');
    if (customTabBtn) {
        customTabBtn.addEventListener('click', () => {
            showListView();
            fetchAll();
        });
    }

    /* ── Initial load ────────────────────────── */
    fetchAll();

})();
