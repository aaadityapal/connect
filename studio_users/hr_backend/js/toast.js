/* ============================================
   toast.js — Toast Notification Logic
   ============================================ */

(function () {
    // Expose globally so form.js can call it
    window.showToast = function (message, type = 'success') {
        const toast  = document.getElementById('hrToast');
        const text   = document.getElementById('hrToastText');
        const icon   = toast ? toast.querySelector('.hr-toast-icon') : null;

        if (!toast || !text) return;

        text.textContent = message;

        if (type === 'error') {
            toast.style.borderLeftColor = '#ef4444';
            if (icon) { icon.className = 'fa-solid fa-circle-xmark hr-toast-icon'; icon.style.color = '#ef4444'; }
        } else {
            toast.style.borderLeftColor = 'var(--hr-success)';
            if (icon) { icon.className = 'fa-solid fa-circle-check hr-toast-icon'; icon.style.color = 'var(--hr-success)'; }
        }

        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    };
})();
