// ─── Summary: Set month label ──────────────────────────
(function() {
    const el = document.getElementById('summary-month-label');
    if (el) {
        const months = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
        const now = new Date();
        el.textContent = months[now.getMonth()] + ' ' + now.getFullYear();
    }
    
    // Populate stats from APIs
    if (window.apiData && window.apiData.summary) {
        const statVals = document.querySelectorAll('.stat-value');
        if (statVals.length >= 4) {
            statVals[0].textContent = window.apiData.summary.efficiency + '%';
            statVals[1].textContent = window.apiData.summary.active_tasks;
            statVals[2].textContent = window.apiData.summary.this_month;
            statVals[3].textContent = window.apiData.summary.upcoming;
        }
    }
})();
