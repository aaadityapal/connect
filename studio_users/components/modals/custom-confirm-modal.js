// ================================================
// CUSTOM CONFIRM MODAL — Logic
// File: components/modals/custom-confirm-modal.js
// ================================================

(function () {
    let overlay = null;
    let titleEl = null;
    let msgEl = null;
    let confirmBtn = null;
    let cancelBtn = null;
    let resolver = null;
    let isLoaded = false;
    let queue = [];

    function setupElements() {
        overlay = document.getElementById('customConfirmModal');
        titleEl = document.getElementById('ccmTitle');
        msgEl = document.getElementById('ccmMessage');
        confirmBtn = document.getElementById('ccmConfirmBtn');
        cancelBtn = document.getElementById('ccmCancelBtn');

        if (!overlay || !titleEl || !msgEl || !confirmBtn || !cancelBtn) return;

        confirmBtn.addEventListener('click', () => close(true));
        cancelBtn.addEventListener('click', () => close(false));

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close(false);
        });

        document.addEventListener('keydown', (e) => {
            if (!overlay.classList.contains('ccm-open')) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                close(false);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                close(true);
            }
        });

        isLoaded = true;
        while (queue.length) {
            const args = queue.shift();
            window.showCustomConfirm(args).then(args._resolve);
        }
    }

    async function initConfirm() {
        if (document.getElementById('customConfirmModal')) {
            setupElements();
            return;
        }

        try {
            const res = await fetch('components/modals/custom-confirm-modal.html');
            const html = await res.text();
            document.body.insertAdjacentHTML('beforeend', html);
            setupElements();
        } catch (err) {
            console.error('[CustomConfirm] Failed to load modal HTML:', err);
        }
    }

    function close(result) {
        if (overlay) overlay.classList.remove('ccm-open');
        if (resolver) {
            const done = resolver;
            resolver = null;
            done(!!result);
        }
    }

    window.showCustomConfirm = function (opts = {}) {
        const options = {
            title: opts.title || 'Confirm Action',
            message: opts.message || 'Are you sure you want to continue?',
            confirmText: opts.confirmText || 'Confirm',
            cancelText: opts.cancelText || 'Cancel'
        };

        return new Promise((resolve) => {
            if (!isLoaded || !overlay) {
                queue.push({ ...options, _resolve: resolve });
                return;
            }

            titleEl.textContent = options.title;
            msgEl.textContent = options.message;
            confirmBtn.textContent = options.confirmText;
            cancelBtn.textContent = options.cancelText;

            resolver = resolve;
            overlay.classList.add('ccm-open');
            setTimeout(() => confirmBtn.focus(), 30);
        });
    };

    initConfirm();
})();
