document.addEventListener('DOMContentLoaded', () => {



    /* =====================================================
       ICON BUTTON RIPPLE / SPIN ANIMATION
       ===================================================== */
    const setupIconButtons = () => {
        const refreshBtn = document.querySelector('.btn-icon[title="Refresh"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                const icon = refreshBtn.querySelector('i');
                icon.style.transition = 'transform 0.6s ease';
                icon.style.transform  = 'rotate(360deg)';
                setTimeout(() => {
                    icon.style.transition = 'none';
                    icon.style.transform  = 'rotate(0deg)';
                }, 620);
            });
        }
    };

    /* =====================================================
       ANIMATE STAT VALUES (COUNT-UP EFFECT)
       ===================================================== */
    window.animateCards = () => {
        const values = document.querySelectorAll('.stat-value[data-value]');
        values.forEach(el => {
            const target = parseFloat(el.dataset.value) || 0;
            if (target === 0) return; // skip zeros for now
            let start = 0;
            const duration = 900;
            const step = (timestamp) => {
                if (!start) start = timestamp;
                const progress = Math.min((timestamp - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                el.textContent = (target % 1 === 0)
                    ? Math.floor(eased * target)
                    : (eased * target).toFixed(1);
                if (progress < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        });
    };

    /* Keyframe for spinner (injected inline) */
    const style = document.createElement('style');
    style.textContent = `@keyframes spin { to { transform: rotate(360deg); } }`;
    document.head.appendChild(style);

    // Initialize remaining elements

    setupIconButtons();
    window.animateCards();
});
