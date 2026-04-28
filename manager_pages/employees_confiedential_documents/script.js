document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('employeeSearch');
    const statusFilter = document.getElementById('statusFilter');
    const uploadBtn = document.getElementById('uploadDocumentBtn');
    const reminderBtn = document.getElementById('bulkReminderBtn');
    const rows = Array.from(document.querySelectorAll('#employeeRows tr[data-status]'));

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function humanizeType(typeKey) {
        return String(typeKey || '')
            .replaceAll('-', ' ')
            .replace(/\b\w/g, (ch) => ch.toUpperCase()) || 'Document';
    }

    function formatDateForUi(raw) {
        const value = String(raw || '').trim();
        if (!value) {
            return 'N/A';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function notify(message, title = 'Notification') {
        if (typeof window.showUiNotice === 'function') {
            return window.showUiNotice(message, title);
        }
        alert(message);
        return Promise.resolve();
    }

    const documentsStore = {};
    const docsFetchInFlight = {};

    function getEmployeeDocuments(employeeId) {
        const bucket = documentsStore[String(employeeId)];
        if (!Array.isArray(bucket)) {
            return [];
        }
        return bucket;
    }

    function normalizeDocumentFromApi(doc) {
        return {
            id: String(doc.id || ''),
            documentTypeKey: String(doc.document_type_key || 'other'),
            documentTypeLabel: String(doc.document_type_label || humanizeType(doc.document_type_key || 'other')),
            documentName: String(doc.document_name || 'Untitled Document'),
            documentDate: String(doc.document_date || ''),
            expiryDate: String(doc.expiry_date || ''),
            visibilityMode: String(doc.visibility_mode || 'all'),
            visibilityUserIds: String(doc.visibility_user_ids || ''),
            notes: String(doc.notes || ''),
            fileName: String(doc.file_original_name || ''),
            uploadedAt: String(doc.created_at || '')
        };
    }

    async function loadEmployeeDocuments(employeeId, forceReload = false) {
        const key = String(employeeId || '').trim();
        if (!key) {
            return [];
        }

        if (!forceReload && Array.isArray(documentsStore[key])) {
            return documentsStore[key];
        }

        if (docsFetchInFlight[key]) {
            return docsFetchInFlight[key];
        }

        const request = fetch('api/get_employee_documents.php?employee_id=' + encodeURIComponent(key))
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to load documents');
                }

                const docs = Array.isArray(data.documents) ? data.documents.map(normalizeDocumentFromApi) : [];
                documentsStore[key] = docs;
                return docs;
            })
            .catch(async (err) => {
                documentsStore[key] = [];
                await notify(err instanceof Error ? err.message : 'Failed to load documents', 'Load Failed');
                return [];
            })
            .finally(() => {
                delete docsFetchInFlight[key];
            });

        docsFetchInFlight[key] = request;
        return request;
    }

    function renderEmployeeDocuments(employeeId) {
        const expandRow = document.querySelector(`[data-expand-content="${employeeId}"]`);
        if (!expandRow) {
            return;
        }

        const tabsWrap = expandRow.querySelector('[data-doc-tabs]');
        const panelsWrap = expandRow.querySelector('[data-doc-panels]');
        const emptyWrap = expandRow.querySelector('[data-doc-empty]');
        const employeeRow = document.querySelector(`[data-employee-row="${employeeId}"]`);
        const employeeName = employeeRow?.dataset.employeeName || 'Employee';

        if (!tabsWrap || !panelsWrap || !emptyWrap) {
            return;
        }

        const documents = [...getEmployeeDocuments(employeeId)].sort((a, b) => {
            const at = String(a.uploadedAt || '');
            const bt = String(b.uploadedAt || '');
            return bt.localeCompare(at);
        });

        if (documents.length === 0) {
            tabsWrap.innerHTML = '';
            panelsWrap.innerHTML = '';
            emptyWrap.style.display = 'block';
            return;
        }

        const grouped = [];
        const byType = {};

        documents.forEach((doc) => {
            const typeKey = String(doc.documentTypeKey || 'other');
            if (!byType[typeKey]) {
                byType[typeKey] = {
                    key: typeKey,
                    label: String(doc.documentTypeLabel || humanizeType(typeKey)),
                    documents: []
                };
                grouped.push(byType[typeKey]);
            }
            byType[typeKey].documents.push(doc);
        });

        tabsWrap.innerHTML = grouped.map((group, index) => {
            return `<button type="button" class="doc-tab ${index === 0 ? 'is-active' : ''}" role="tab" data-doc-tab="${escapeHtml(group.key)}">${escapeHtml(group.label)}</button>`;
        }).join('');

        panelsWrap.innerHTML = grouped.map((group, index) => {
            const docsHtml = group.documents.map((doc) => {
                const meta = `Doc Date: ${escapeHtml(formatDateForUi(doc.documentDate))} | Uploaded: ${escapeHtml(formatDateForUi(doc.uploadedAt))}`;
                const fileMeta = doc.fileName ? ` | File: ${escapeHtml(doc.fileName)}` : '';
                return `
                    <div class="doc-item">
                        <div>
                            <strong>${escapeHtml(doc.documentName || 'Untitled Document')}</strong>
                            <small>${meta}${fileMeta}</small>
                        </div>
                        <div class="doc-actions">
                            <button type="button" class="doc-icon-btn" title="View" aria-label="View" data-doc-action="view" data-doc-id="${escapeHtml(doc.id)}"><i data-lucide="eye" style="width:14px;height:14px;"></i></button>
                            <button type="button" class="doc-icon-btn" title="Download" aria-label="Download" data-doc-action="download" data-doc-id="${escapeHtml(doc.id)}"><i data-lucide="download" style="width:14px;height:14px;"></i></button>
                            <button type="button" class="doc-icon-btn danger" title="Hide" aria-label="Hide" data-doc-action="delete" data-doc-id="${escapeHtml(doc.id)}" data-employee-id="${escapeHtml(employeeId)}"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <section class="doc-panel ${index === 0 ? 'is-active' : ''}" data-doc-panel="${escapeHtml(group.key)}">
                    <div class="doc-panel-header">
                        <h4>${escapeHtml(group.label)}</h4>
                        <button class="tab-upload-btn js-upload-doc" type="button" data-employee-id="${escapeHtml(employeeId)}" data-employee-name="${escapeHtml(employeeName)}" data-prefill-type="${escapeHtml(group.key)}">
                            <i data-lucide="upload" style="width:14px;height:14px;"></i>
                            <span>Upload</span>
                        </button>
                    </div>
                    <div class="doc-scroll-area">${docsHtml}</div>
                </section>
            `;
        }).join('');

        emptyWrap.style.display = 'none';

        const tabs = Array.from(tabsWrap.querySelectorAll('.doc-tab'));
        const panels = Array.from(panelsWrap.querySelectorAll('.doc-panel'));

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const key = tab.dataset.docTab || '';
                tabs.forEach((item) => item.classList.remove('is-active'));
                panels.forEach((panel) => panel.classList.remove('is-active'));
                tab.classList.add('is-active');
                const target = panelsWrap.querySelector(`[data-doc-panel="${key}"]`);
                if (target) {
                    target.classList.add('is-active');
                }
            });
        });

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function ensureExpandRowOpen(employeeId) {
        const expandRow = document.querySelector(`[data-expand-content="${employeeId}"]`);
        const toggleBtn = document.querySelector(`[data-toggle-row="${employeeId}"]`);
        if (!expandRow || !toggleBtn) {
            return;
        }
        expandRow.hidden = false;
        expandRow.style.display = '';
        toggleBtn.classList.add('is-open');
        toggleBtn.setAttribute('aria-expanded', 'true');
    }

    function applyFilters() {
        const term = (searchInput?.value || '').trim().toLowerCase();
        const selectedStatus = statusFilter?.value || 'all';

        rows.forEach((row) => {
            const searchable = row.textContent.toLowerCase();
            const rowStatus = row.dataset.status || 'inactive';
            const rowId = row.dataset.employeeRow || '';
            const expandRow = rowId ? document.querySelector(`[data-expand-content="${rowId}"]`) : null;

            const byText = term === '' || searchable.includes(term);
            const byStatus = selectedStatus === 'all' || rowStatus === selectedStatus;

            const isVisible = byText && byStatus;
            row.style.display = isVisible ? '' : 'none';

            if (expandRow) {
                if (isVisible) {
                    expandRow.style.display = expandRow.hidden ? 'none' : '';
                } else {
                    expandRow.hidden = true;
                    expandRow.style.display = 'none';
                    const toggleBtn = document.querySelector(`[data-toggle-row="${rowId}"]`);
                    if (toggleBtn) {
                        toggleBtn.classList.remove('is-open');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    }
                }
            }
        });
    }

    searchInput?.addEventListener('input', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);

    document.querySelectorAll('[data-toggle-row]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.toggleRow || '';
            const expandRow = document.querySelector(`[data-expand-content="${id}"]`);
            if (!expandRow) {
                return;
            }

            const shouldOpen = expandRow.hidden;
            expandRow.hidden = !shouldOpen;
            expandRow.style.display = shouldOpen ? '' : 'none';
            btn.classList.toggle('is-open', shouldOpen);
            btn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

            if (shouldOpen) {
                await loadEmployeeDocuments(id, true);
                renderEmployeeDocuments(id);
            }
        });
    });

    document.addEventListener('click', async (event) => {
        const uploadTrigger = event.target.closest('.js-upload-doc');
        if (uploadTrigger) {
            const employeeId = uploadTrigger.dataset.employeeId || '';
            const employeeName = uploadTrigger.dataset.employeeName || '';
            const prefillType = uploadTrigger.dataset.prefillType || '';
            if (typeof window.openUploadModal === 'function') {
                window.openUploadModal({ employeeId, employeeName, prefillType });
            }
            return;
        }

        const rowAction = event.target.closest('[data-action]');
        if (rowAction) {
            const action = rowAction.dataset.action || 'action';
            notify(`UI placeholder: ${action.replace('-', ' ')}. Backend will be added next.`, 'Info');
            return;
        }

        const documentAction = event.target.closest('[data-doc-action]');
        if (documentAction) {
            const action = documentAction.dataset.docAction || 'action';
            const docId = String(documentAction.dataset.docId || '').trim();
            if (!/^\d+$/.test(docId)) {
                notify('This document is not synced with server yet.', 'Info');
                return;
            }

            const base = 'api/serve_employee_document.php?id=' + encodeURIComponent(docId);
            if (action === 'view') {
                window.open(base + '&mode=view', '_blank', 'noopener');
                return;
            }
            if (action === 'download') {
                window.location.href = base + '&mode=download';
                return;
            }

            if (action === 'delete') {
                const employeeId = String(documentAction.dataset.employeeId || '').trim();
                let shouldProceed = true;
                if (typeof window.showUiConfirm === 'function') {
                    shouldProceed = await window.showUiConfirm({
                        title: 'Delete Document',
                        message: 'Are you sure you want to delete this document?'
                    });
                }

                if (!shouldProceed) {
                    return;
                }

                fetch('api/delete_employee_document.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ document_id: Number(docId) })
                })
                    .then(async (response) => {
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Failed to delete document');
                        }

                        if (employeeId && Array.isArray(documentsStore[employeeId])) {
                            documentsStore[employeeId] = documentsStore[employeeId].filter((doc) => String(doc.id) !== docId);
                            renderEmployeeDocuments(employeeId);
                        }

                        notify('Document deleted successfully.', 'Success');
                    })
                    .catch((err) => {
                        notify(err instanceof Error ? err.message : 'Failed to delete document', 'Delete Failed');
                    });

                return;
            }

            notify('Unsupported document action.', 'Info');
        }
    });

    uploadBtn?.addEventListener('click', () => {
        const firstVisibleRow = rows.find((row) => row.style.display !== 'none');
        const employeeId = firstVisibleRow?.dataset.employeeRow || '';
        const employeeName = firstVisibleRow?.dataset.employeeName || '';

        if (typeof window.openUploadModal === 'function') {
            window.openUploadModal({ employeeId, employeeName });
            return;
        }
        notify('UI placeholder: open upload dialog for confidential documents.', 'Info');
    });

    reminderBtn?.addEventListener('click', () => {
        notify('UI placeholder: send reminder to employees with pending or missing documents.', 'Info');
    });

    window.addEventListener('employee-document-added', (event) => {
        const payload = event.detail || {};
        const employeeId = String(payload.employeeId || '').trim();
        if (!employeeId) {
            return;
        }

        if (!Array.isArray(documentsStore[employeeId])) {
            documentsStore[employeeId] = [];
        }

        documentsStore[employeeId].unshift({
            id: payload.id || `doc_${Date.now()}_${Math.floor(Math.random() * 1000)}`,
            documentTypeKey: payload.documentTypeKey || 'other',
            documentTypeLabel: payload.documentTypeLabel || humanizeType(payload.documentTypeKey || 'other'),
            documentName: payload.documentName || 'Untitled Document',
            documentDate: payload.documentDate || '',
            expiryDate: payload.expiryDate || '',
            visibilityMode: payload.visibilityMode || 'all',
            visibilityUserIds: payload.visibilityUserIds || '',
            notes: payload.notes || '',
            fileName: payload.fileName || '',
            uploadedAt: payload.uploadedAt || new Date().toISOString()
        });

        ensureExpandRowOpen(employeeId);
        renderEmployeeDocuments(employeeId);
    });

    rows.forEach((row) => {
        const employeeId = row.dataset.employeeRow || '';
        if (employeeId) {
            renderEmployeeDocuments(employeeId);
        }
    });

    if (window.lucide) {
        lucide.createIcons();
    }
});
