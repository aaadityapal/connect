// ─── Header: Inject today's date ─────────────────────
(function() {
    const el = document.getElementById('header-date');
    if (el) {
        const d    = new Date();
        const opts = { day: '2-digit', month: 'short', year: 'numeric' };
        el.textContent = d.toLocaleDateString('en-GB', opts);
    }
})();
