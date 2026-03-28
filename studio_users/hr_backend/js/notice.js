/* ============================================
   notice.js — Notice Section Logic
   ============================================ */

(function () {
    const form       = document.getElementById('hrNoticeForm');
    const btnPublish = document.getElementById('hrNoticeBtnPublish');
    const btnClear   = document.getElementById('hrNoticeBtnClear');
    const fileInput  = document.getElementById('hrNoticeFile');
    const uploadZone = document.getElementById('hrNoticeUploadZone');
    const uploadText = document.getElementById('hrNoticeUploadText');
    const uploadIcon = document.getElementById('hrNoticeUploadIconMain');

    if (!form) return;

    // ── File Upload Handling ────────────────────

    // Show selected filename in the upload zone
    function handleFileSelected(file) {
        if (!file) return;

        const maxMB = 5;
        if (file.size > maxMB * 1024 * 1024) {
            if (window.showToast) window.showToast(`File too large. Max size is ${maxMB}MB.`, 'error');
            fileInput.value = '';
            return;
        }

        uploadZone.classList.add('hr-notice-file-selected');
        uploadIcon.className = 'fa-solid fa-circle-check hr-notice-upload-icon';
        uploadText.innerHTML = `<strong style="color:#10b981;">${file.name}</strong>`;
    }

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            handleFileSelected(fileInput.files[0]);
        });
    }

    // Drag & Drop support on upload zone
    if (uploadZone) {
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('hr-notice-drag-over');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('hr-notice-drag-over');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('hr-notice-drag-over');
            const file = e.dataTransfer.files[0];
            if (file && fileInput) {
                // Assign dropped file to native input via DataTransfer
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                handleFileSelected(file);
            }
        });
    }

    // Reset upload zone to default state
    function resetUploadZone() {
        if (!uploadZone) return;
        uploadZone.classList.remove('hr-notice-file-selected', 'hr-notice-drag-over');
        uploadIcon.className = 'fa-solid fa-cloud-arrow-up hr-notice-upload-icon';
        uploadText.innerHTML = `<span class="hr-notice-upload-highlight">Click to upload</span> or drag & drop`;
    }

    // ── Submit Handler ──────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const title     = document.getElementById('hrNoticeTitle').value.trim();
        const shortDesc = document.getElementById('hrNoticeShortDesc').value.trim();
        const longDesc  = document.getElementById('hrNoticeLongDesc').value.trim();
        const file      = fileInput ? fileInput.files[0] : null;

        const originalHTML = btnPublish.innerHTML;
        btnPublish.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
        btnPublish.disabled = true;

        try {
            // ── Real API call ──────────────────────────────
            const formData = new FormData();
            formData.append('title',     title);
            formData.append('shortDesc', shortDesc);
            formData.append('longDesc',  longDesc);
            if (file) {
                console.log("[Notice JS] Attaching file:", file.name);
                formData.append('attachment', file);
            } else {
                console.log("[Notice JS] No file selected.");
            }

            const res  = await fetch('api/save_notice.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            if (window.showToast) window.showToast('Notice sent successfully!');

        } catch (err) {
            console.error('[notice.js] Send error:', err);
            if (window.showToast) window.showToast('Failed to send notice. Please try again.', 'error');
        } finally {
            btnPublish.innerHTML = originalHTML;
            btnPublish.disabled  = false;
        }
    });

    // ── Clear Handler ───────────────────────────
    btnClear.addEventListener('click', () => {
        if (confirm('Clear the form? Unsaved content will be lost.')) {
            form.reset();
            resetUploadZone();
        }
    });

})();
