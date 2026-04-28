document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('uploadDocModal');
    const closeBtn = document.getElementById('closeUploadDocModal');
    const cancelBtn = document.getElementById('cancelUploadDocModal');
    const form = document.getElementById('uploadDocumentForm');
    const typeSelect = document.getElementById('documentType');
    const customTypeField = document.getElementById('customDocumentTypeField');
    const customTypeInput = document.getElementById('customDocumentType');
    const documentNameInput = document.getElementById('documentName');
    const documentDateInput = document.getElementById('documentDate');
    const documentFileInput = document.getElementById('documentFile');
    const expiryDateInput = document.getElementById('expiryDate');
    const visibilityModeSelect = document.getElementById('visibilityMode');
    const visibilityUserIdsField = document.getElementById('visibilityUserIdsField');
    const visibilityUserIdsInput = document.getElementById('visibilityUserIds');
    const notesInput = document.getElementById('documentNotes');
    const titleText = document.querySelector('#uploadDocTitle span');
    const uploadProgressOverlay = document.getElementById('uploadProgressOverlay');
    const uiNoticeModal = document.getElementById('uiNoticeModal');
    const uiNoticeTitle = document.getElementById('uiNoticeTitle');
    const uiNoticeMessage = document.getElementById('uiNoticeMessage');
    const uiNoticeOkBtn = document.getElementById('uiNoticeOkBtn');
    const uiConfirmModal = document.getElementById('uiConfirmModal');
    const uiConfirmTitle = document.getElementById('uiConfirmTitle');
    const uiConfirmMessage = document.getElementById('uiConfirmMessage');
    const uiConfirmDetails = document.getElementById('uiConfirmDetails');
    const uiConfirmCancelBtn = document.getElementById('uiConfirmCancelBtn');
    const uiConfirmOkBtn = document.getElementById('uiConfirmOkBtn');

    if (!modal) {
        return;
    }

    function setUploadProgress(isOpen) {
        if (!uploadProgressOverlay) {
            return;
        }
        uploadProgressOverlay.hidden = !isOpen;
        uploadProgressOverlay.classList.toggle('is-open', isOpen);
        uploadProgressOverlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }

    function showUiNotice(message, title = 'Notification') {
        if (!uiNoticeModal || !uiNoticeTitle || !uiNoticeMessage || !uiNoticeOkBtn) {
            alert(message);
            return Promise.resolve();
        }

        uiNoticeTitle.textContent = title;
        uiNoticeMessage.textContent = message;
        uiNoticeModal.hidden = false;
        uiNoticeModal.classList.add('is-open');
        uiNoticeModal.setAttribute('aria-hidden', 'false');

        return new Promise((resolve) => {
            const close = () => {
                uiNoticeModal.classList.remove('is-open');
                uiNoticeModal.setAttribute('aria-hidden', 'true');
                uiNoticeModal.hidden = true;
                uiNoticeOkBtn.removeEventListener('click', onOk);
                uiNoticeModal.removeEventListener('click', onOverlayClick);
                resolve();
            };

            const onOk = () => close();
            const onOverlayClick = (event) => {
                if (event.target === uiNoticeModal) {
                    close();
                }
            };

            uiNoticeOkBtn.addEventListener('click', onOk);
            uiNoticeModal.addEventListener('click', onOverlayClick);
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showUiConfirm(options = {}) {
        const title = String(options.title || 'Confirm Action');
        const message = String(options.message || 'Are you sure?');
        const details = Array.isArray(options.details) ? options.details : [];

        if (!uiConfirmModal || !uiConfirmTitle || !uiConfirmMessage || !uiConfirmDetails || !uiConfirmCancelBtn || !uiConfirmOkBtn) {
            return Promise.resolve(confirm(message));
        }

        uiConfirmTitle.textContent = title;
        uiConfirmMessage.textContent = message;
        uiConfirmDetails.style.display = details.length > 0 ? '' : 'none';
        uiConfirmDetails.innerHTML = details.map((item) => {
            const label = escapeHtml(item?.label ?? 'Detail');
            const value = escapeHtml(item?.value ?? '-');
            return `<div class="ui-confirm-detail-row"><strong>${label}</strong><span>${value}</span></div>`;
        }).join('');

        uiConfirmModal.hidden = false;
        uiConfirmModal.classList.add('is-open');
        uiConfirmModal.setAttribute('aria-hidden', 'false');

        return new Promise((resolve) => {
            const close = (result) => {
                uiConfirmModal.classList.remove('is-open');
                uiConfirmModal.setAttribute('aria-hidden', 'true');
                uiConfirmModal.hidden = true;
                uiConfirmCancelBtn.removeEventListener('click', onCancel);
                uiConfirmOkBtn.removeEventListener('click', onConfirm);
                uiConfirmModal.removeEventListener('click', onOverlayClick);
                resolve(result);
            };

            const onCancel = () => close(false);
            const onConfirm = () => close(true);
            const onOverlayClick = (event) => {
                if (event.target === uiConfirmModal) {
                    close(false);
                }
            };

            uiConfirmCancelBtn.addEventListener('click', onCancel);
            uiConfirmOkBtn.addEventListener('click', onConfirm);
            uiConfirmModal.addEventListener('click', onOverlayClick);
        });
    }

    window.showUiNotice = showUiNotice;
    window.showUiConfirm = showUiConfirm;

    function normalizeTypeKey(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function syncCustomDocumentTypeField() {
        if (!typeSelect || !customTypeField || !customTypeInput) {
            return;
        }

        const isCustom = typeSelect.value === 'custom';
        customTypeField.hidden = !isCustom;
        customTypeField.style.display = isCustom ? 'flex' : 'none';
        customTypeInput.required = isCustom;
        if (!isCustom) {
            customTypeInput.value = '';
        }
    }

    function syncVisibilityField() {
        if (!visibilityModeSelect || !visibilityUserIdsField || !visibilityUserIdsInput) {
            return;
        }

        const isSpecificUsers = visibilityModeSelect.value === 'specific_users';
        const employeeId = String(modal.dataset.employeeId || '').trim();
        visibilityUserIdsField.hidden = !isSpecificUsers;
        visibilityUserIdsField.style.display = isSpecificUsers ? 'flex' : 'none';
        visibilityUserIdsInput.required = isSpecificUsers;
        if (isSpecificUsers) {
            if (employeeId !== '') {
                visibilityUserIdsInput.value = employeeId;
            }
        } else {
            visibilityUserIdsInput.value = '';
        }
    }

    function openUploadModal(options = {}) {
        const resolved = typeof options === 'string'
            ? { prefillType: options }
            : (options || {});

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const employeeId = String(resolved.employeeId || '').trim();
        const employeeName = String(resolved.employeeName || '').trim();
        const prefillType = String(resolved.prefillType || '').trim();

        modal.dataset.employeeId = employeeId;
        modal.dataset.employeeName = employeeName;

        if (titleText) {
            titleText.textContent = employeeName
                ? `Upload Document - ${employeeName}`
                : 'Upload Employee Document';
        }

        if (typeSelect) {
            typeSelect.value = prefillType || '';
        }

        syncCustomDocumentTypeField();
        syncVisibilityField();
    }

    function closeUploadModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        modal.dataset.employeeId = '';
        modal.dataset.employeeName = '';
        if (titleText) {
            titleText.textContent = 'Upload Employee Document';
        }
    }

    window.openUploadModal = openUploadModal;

    typeSelect?.addEventListener('change', syncCustomDocumentTypeField);
    visibilityModeSelect?.addEventListener('change', syncVisibilityField);
    syncCustomDocumentTypeField();
    syncVisibilityField();

    closeBtn?.addEventListener('click', closeUploadModal);
    cancelBtn?.addEventListener('click', closeUploadModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeUploadModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeUploadModal();
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const employeeId = String(modal.dataset.employeeId || '').trim();
        if (!employeeId) {
            await showUiNotice('Please use an employee-specific Upload button inside the employee section.', 'Upload Error');
            return;
        }

        const selectedType = String(typeSelect?.value || '').trim();
        const customType = String(customTypeInput?.value || '').trim();
        const resolvedTypeKey = selectedType === 'custom' ? normalizeTypeKey(customType) : selectedType;
        const resolvedTypeLabel = selectedType === 'custom'
            ? customType
            : String(typeSelect?.selectedOptions?.[0]?.textContent || '').trim();

        if (!resolvedTypeKey || !resolvedTypeLabel) {
            await showUiNotice('Please select or enter a valid document type.', 'Validation');
            return;
        }

        const submitBtn = form.querySelector('.upload-btn-submit');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Uploading...</span>';
        }
        setUploadProgress(true);

        try {
            const payload = new FormData(form);
            payload.set('employee_id', employeeId);
            payload.set('document_type', selectedType);
            payload.set('custom_document_type', customType);

            const response = await fetch('api/upload_employee_document.php', {
                method: 'POST',
                body: payload
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Upload failed');
            }

            const doc = data.document || {};
            const detail = {
                id: String(doc.id || `doc_${Date.now()}`),
                employeeId: String(doc.employee_id || employeeId),
                employeeName: String(modal.dataset.employeeName || '').trim(),
                documentTypeKey: String(doc.document_type_key || resolvedTypeKey),
                documentTypeLabel: String(doc.document_type_label || resolvedTypeLabel),
                documentName: String(doc.document_name || documentNameInput?.value || '').trim(),
                documentDate: String(doc.document_date || documentDateInput?.value || '').trim(),
                expiryDate: String(doc.expiry_date || expiryDateInput?.value || '').trim(),
                visibilityMode: String(doc.visibility_mode || visibilityModeSelect?.value || 'all').trim(),
                visibilityUserIds: Array.isArray(doc.visibility_user_ids)
                    ? doc.visibility_user_ids.join(',')
                    : String(doc.visibility_user_ids || visibilityUserIdsInput?.value || '').trim(),
                notes: String(doc.notes || notesInput?.value || '').trim(),
                fileName: String(doc.file_name || documentFileInput?.files?.[0]?.name || '').trim(),
                uploadedAt: String(doc.uploaded_at || new Date().toISOString())
            };

            window.dispatchEvent(new CustomEvent('employee-document-added', { detail }));
            await showUiNotice('Document uploaded successfully. New tab is created automatically if type is new.', 'Upload Success');
            closeUploadModal();
            form.reset();
            syncCustomDocumentTypeField();
            syncVisibilityField();
        } catch (err) {
            await showUiNotice(err instanceof Error ? err.message : 'Failed to upload document', 'Upload Failed');
        } finally {
            setUploadProgress(false);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    });
});
