/* ============================================
   tabs.js — Pill Toggle Switcher Logic
   ============================================ */

(function () {
    const SECTIONS = ['hr-form', 'hr-notice', 'hr-custom'];

    function initToggle() {
        const options  = document.querySelectorAll('.hr-toggle-option');
        const slider   = document.getElementById('hrToggleSlider');

        if (!options.length || !slider) return;

        // ── Move slider to match a button ───────────────
        function moveSlider(btn) {
            const pill     = btn.closest('.hr-toggle-pill');
            const pillRect = pill.getBoundingClientRect();
            const btnRect  = btn.getBoundingClientRect();

            // position relative to pill, accounting for the 4px padding offset
            slider.style.left  = (btn.offsetLeft) + 'px';
            slider.style.width = btnRect.width + 'px';
        }

        // ── Show target section, hide others ────────────
        function showSection(targetId) {
            SECTIONS.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                if (id === targetId) {
                    el.style.display = '';
                    el.style.animation = 'hrToggleFadeIn 0.25s ease';
                } else {
                    el.style.display = 'none';
                }
            });
        }

        // ── Click handler ────────────────────────────────
        options.forEach(btn => {
            btn.addEventListener('click', () => {
                options.forEach(b => b.classList.remove('hr-toggle-active'));
                btn.classList.add('hr-toggle-active');
                moveSlider(btn);
                showSection(btn.dataset.target);
            });
        });

        // ── Initialise on first load ─────────────────────
        const firstActive = document.querySelector('.hr-toggle-option.hr-toggle-active');
        if (firstActive) {
            // Small timeout to let layout settle before measuring offsets
            requestAnimationFrame(() => {
                moveSlider(firstActive);
                showSection(firstActive.dataset.target);
            });
        }
    }

    // ── Inject fade-in animation keyframe ───────────────
    const style = document.createElement('style');
    style.textContent = `
        @keyframes hrToggleFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0);   }
        }
    `;
    document.head.appendChild(style);

    initToggle();

})();
