(function (global) {
    let modalsInjected = false;

    function injectPunchModals() {
        if (modalsInjected) return;
        modalsInjected = true;

        fetch(`punch-modals/punch-modals.html?v=${Date.now()}`, { cache: 'no-store' })
            .then(res => {
                if (!res.ok) throw new Error("Failed to fetch punch modals HTML");
                return res.text();
            })
            .then(html => {
                const div = document.createElement('div');
                div.innerHTML = html;

                // Append all generated modal nodes directly to the document body
                while (div.firstChild) {
                    document.body.appendChild(div.firstChild);
                }

                // Initialize the logic previously bound locally in script.js
                // It's wrapped under initPunchModalEvents() directly there.
                if (typeof window.initPunchModalEvents === 'function') {
                    window.initPunchModalEvents();
                } else {
                    console.warn("window.initPunchModalEvents is not defined globally.");
                }
            })
            .catch(err => console.error("Error loading punch modals:", err));
    }

    global.PunchModalLoader = {
        init: injectPunchModals
    };

    // Auto-init rapidly once DOM is ready
    window.addEventListener('DOMContentLoaded', () => {
        global.PunchModalLoader.init();
    });
})(window);
