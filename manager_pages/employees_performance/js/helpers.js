/**
 * helpers.js — Helper Functions for Employees Performance
 */

function getGrade(score) {
    if (score >= 90) return { label: 'Excellent', cls: 'badge-excellent' };
    if (score >= 75) return { label: 'Good',      cls: 'badge-good' };
    if (score >= 60) return { label: 'Average',   cls: 'badge-average' };
    return               { label: 'Poor',      cls: 'badge-poor' };
}

function initials(name) {
    return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
}

function filteredSorted() {
    let data = [...MOCK_EMPLOYEES];
    
    // Filter by Search
    if (state.search) {
        const q = state.search.toLowerCase();
        data = data.filter(e => e.name.toLowerCase().includes(q) || e.role.toLowerCase().includes(q) || e.dept.toLowerCase().includes(q));
    }
    
    // Filter by Role
    if (state.role !== 'All') {
        data = data.filter(e => e.role === state.role);
    }
    
    // Filter by Projects
    const hasProjectFilter = state.projects && !state.projects.includes('All Projects');
    if (hasProjectFilter) {
        data = data.filter(e => e.projects.some(p => state.projects.includes(p.name)));
    }
    
    // Sort
    data.sort((a, b) => {
        let valA = a[state.sortBy];
        let valB = b[state.sortBy];
        
        if (hasProjectFilter && ['tasks', 'score'].includes(state.sortBy)) {
            // Aggregate score/tasks across selected projects
            const subA = a.projects.filter(p => state.projects.includes(p.name));
            const subB = b.projects.filter(p => state.projects.includes(p.name));
            
            if (subA.length) valA = Math.round(subA.reduce((acc, curr) => acc + curr[state.sortBy], 0) / subA.length);
            else valA = 0;

            if (subB.length) valB = Math.round(subB.reduce((acc, curr) => acc + curr[state.sortBy], 0) / subB.length);
            else valB = 0;
        }

        const v = state.sortDir === 'asc' ? 1 : -1;
        if (valA < valB) return -v;
        if (valA > valB) return v;
        return 0;
    });
    
    return data;
}

function avgMetric(key) {
    let sum = 0;
    let count = 0;
    
    const hasProjectFilter = state.projects && !state.projects.includes('All Projects');
    if (hasProjectFilter && ['tasks', 'score'].includes(key)) {
        MOCK_EMPLOYEES.forEach(e => {
            const sub = e.projects.filter(pr => state.projects.includes(pr.name));
            if (sub.length) {
                const avg = sub.reduce((acc, curr) => acc + curr[key], 0) / sub.length;
                sum += avg;
                count++;
            }
        });
    } else {
        MOCK_EMPLOYEES.forEach(e => {
            sum += e[key];
            count++;
        });
    }
    
    if (count === 0) return 0;
    return Math.round(sum / count);
}

function progressColor(pct) {
    if (pct >= 90) return '#10b981';
    if (pct >= 75) return '#0ea5e9';
    if (pct >= 60) return '#f59e0b';
    return '#ef4444';
}

function showToast(msg, type = 'success') {
    const t = document.getElementById('perf-toast');
    t.innerHTML = `<i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}" style="width:16px;height:16px;"></i> ${msg}`;
    t.style.background = type === 'success' ? '#1e293b' : '#ef4444';
    t.classList.add('show');
    if (window.lucide) lucide.createIcons();
    setTimeout(() => t.classList.remove('show'), 3200);
}
