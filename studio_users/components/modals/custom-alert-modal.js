// ================================================
// CUSTOM ALERT MODAL — Logic
// File: components/modals/custom-alert-modal.js
// ================================================

(function() {
    let overlay, iconWrapper, icon, titleEl, msgEl, btnOk;
    let isLoaded = false;
    let _onConfirm = null;
    let queue = [];

    async function initAlert() {
        if (document.getElementById('customAlertModal')) {
            setupElements();
            return;
        }

        try {
            const response = await fetch('components/modals/custom-alert-modal.html');
            const html = await response.text();
            document.body.insertAdjacentHTML('beforeend', html);
            setupElements();
        } catch (err) {
            console.error('[CustomAlert] Failed to load modal HTML:', err);
        }
    }

    function setupElements() {
        overlay = document.getElementById('customAlertModal');
        iconWrapper = document.getElementById('camIconWrapper');
        icon = document.getElementById('camIcon');
        titleEl = document.getElementById('camTitle');
        msgEl = document.getElementById('camMessage');
        btnOk = document.getElementById('camOkBtn');

        if (btnOk) {
            btnOk.addEventListener('click', closeAlert);
        }

        isLoaded = true;
        // Process any queued alerts
        while (queue.length > 0) {
            const args = queue.shift();
            window.showCustomAlert(...args);
        }
    }

    /**
     * Shows the Custom Alert Modal
     */
    window.showCustomAlert = function(message, title = 'Alert', type = 'error', onConfirm = null) {
        if (!isLoaded) {
            queue.push([message, title, type, onConfirm]);
            return;
        }

        if (!overlay) return;

        titleEl.textContent = title;
        msgEl.innerHTML = message;
        _onConfirm = onConfirm;

        // Reset themes
        iconWrapper.className = 'cam-icon';
        btnOk.className = 'cam-btn';
        
        // Apply themes
        if (type === 'error') {
            iconWrapper.style.color = '#ef4444';
            iconWrapper.style.background = '#fee2e2';
            icon.className = 'fa-solid fa-circle-exclamation';
            btnOk.classList.add('theme-error');
        } else if (type === 'warning') {
            iconWrapper.style.color = '#eab308';
            iconWrapper.style.background = '#fef9c3';
            icon.className = 'fa-solid fa-triangle-exclamation';
            btnOk.classList.add('theme-warning');
        } else if (type === 'success') {
            iconWrapper.style.color = '#22c55e';
            iconWrapper.style.background = '#dcfce7';
            icon.className = 'fa-solid fa-circle-check';
            btnOk.classList.add('theme-success');
        } else { // info
            iconWrapper.style.color = '#3b82f6';
            iconWrapper.style.background = '#e0f2fe';
            icon.className = 'fa-solid fa-circle-info';
        }

        overlay.classList.add('cam-open');
    };

    function closeAlert() {
        if (overlay) overlay.classList.remove('cam-open');
        if (typeof _onConfirm === 'function') {
            const callback = _onConfirm;
            _onConfirm = null; // Prevent double trigger
            callback();
        }
    }

    // Close on Enter if modal is open
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === 'Escape') {
            if (overlay && overlay.classList.contains('cam-open')) {
                e.preventDefault();
                closeAlert();
            }
        }
    });

    initAlert();
})();
