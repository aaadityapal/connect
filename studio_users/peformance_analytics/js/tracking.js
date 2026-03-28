// ─── Tracking: Filter pills ────────────────────────────
window.setFilter = function(el, type) {
    // 1. Toggle active pill
    document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');

    // 2. Show / hide the mini tracking cards based on data-filter
    document.querySelectorAll('.track-card').forEach(card => {
        const filters = card.dataset.filter || '';
        card.style.display = filters.includes(type) ? 'flex' : 'none';
    });

    // 3. Show / hide bottom panels
    const projectsPanel    = document.getElementById('projects-panel');
    const completionsPanel = document.getElementById('completions-panel');
    const bottomSections   = document.getElementById('bottom-sections');

    if (!projectsPanel || !completionsPanel) return;

    if (type === 'all') {
        projectsPanel.style.display    = '';
        completionsPanel.style.display = '';
        if (bottomSections) bottomSections.style.gridTemplateColumns = '';
    } else if (type === 'stages' || type === 'substages') {
        projectsPanel.style.display    = '';
        completionsPanel.style.display = 'none';
        if (bottomSections) bottomSections.style.gridTemplateColumns = '1fr';
    } else if (type === 'late') {
        projectsPanel.style.display    = 'none';
        completionsPanel.style.display = '';
        if (bottomSections) bottomSections.style.gridTemplateColumns = '1fr';
    }
};
