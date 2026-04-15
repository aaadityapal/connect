/* ============================================
   form.js — Policy Form Logic (with Quill editor)
   ============================================ */

(function () {
    const form       = document.getElementById('hrPolicyForm');
    const btnPublish = document.getElementById('hrBtnPublish');
    const btnClear   = document.getElementById('hrBtnClear');

    if (!form) return;

    // ── Init Quill ───────────────────────────────
    const quill = new Quill('#hrPolicyQuillEditor', {
        theme: 'snow',
        placeholder: 'Enter the full detailed description of the policy here...',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ indent: '-1' }, { indent: '+1' }],
                ['blockquote', 'code-block'],
                ['link'],
                ['clean']
            ]
        }
    });

    const hiddenTextarea = document.getElementById('hrInputLongDesc');

    // Sync Quill → hidden textarea on every change
    quill.on('text-change', () => {
        hiddenTextarea.value = quill.root.innerHTML === '<p><br></p>'
            ? ''
            : quill.root.innerHTML;
    });

    // ── Submit Handler ──────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const heading   = document.getElementById('hrInputHeading').value.trim();
        const shortDesc = document.getElementById('hrInputShortDesc').value.trim();
        const longDesc  = hiddenTextarea.value.trim();

        if (!longDesc) {
            if (window.showToast) window.showToast('Detailed Description cannot be empty.', 'error');
            quill.focus();
            return;
        }

        const originalHTML = btnPublish.innerHTML;
        btnPublish.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Publishing...';
        btnPublish.disabled = true;

        try {
            // ── Real API call ──────────────────────────────
            const res  = await fetch('api/save_policy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ heading, shortDesc, longDesc })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            if (window.showToast) window.showToast('Policy published successfully!');
            quill.setText('');
            hiddenTextarea.value = '';

        } catch (err) {
            console.error('[form.js] Publish error:', err);
            if (window.showToast) window.showToast('Failed to publish. Please try again.', 'error');
        } finally {
            btnPublish.innerHTML = originalHTML;
            btnPublish.disabled = false;
        }
    });

    // ── Clear Handler ───────────────────────────
    btnClear.addEventListener('click', () => {
        if (confirm('Clear the form? Unsaved changes will be lost.')) {
            form.reset();
            quill.setText('');
            hiddenTextarea.value = '';
        }
    });

})();
