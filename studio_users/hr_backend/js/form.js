/* ============================================
   form.js — Policy Form Logic
   ============================================ */

(function () {
    const form       = document.getElementById('hrPolicyForm');
    const btnPublish = document.getElementById('hrBtnPublish');
    const btnClear   = document.getElementById('hrBtnClear');

    if (!form) return;

    // ── Submit Handler ──────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const heading   = document.getElementById('hrInputHeading').value.trim();
        const shortDesc = document.getElementById('hrInputShortDesc').value.trim();
        const longDesc  = document.getElementById('hrInputLongDesc').value.trim();

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
        }
    });

})();
