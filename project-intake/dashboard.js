// Dashboard Logic

let globalProjects = []; // Store projects globally for filtering

document.addEventListener('DOMContentLoaded', () => {
    initDateFilters();
    loadDashboardData();
    setupReminderFilters();
    setupDateFilterListeners();
});

function initDateFilters() {
    const today = new Date();
    const month = today.getMonth(); // 0-11
    const year = today.getFullYear();

    const monthSelect = document.getElementById('monthFilter');
    const yearSelect = document.getElementById('yearFilter');

    if (monthSelect) monthSelect.value = month;
    if (yearSelect) yearSelect.value = year;
}

function setupDateFilterListeners() {
    document.getElementById('monthFilter').addEventListener('change', () => filterDashboardData());
    document.getElementById('yearFilter').addEventListener('change', () => filterDashboardData());
}

function filterDashboardData() {
    const month = document.getElementById('monthFilter').value;
    const year = document.getElementById('yearFilter').value;

    const filtered = globalProjects.filter(p => {
        // If "All/All", show everything including TBD
        if (month === 'all' && year === 'all') return true;

        if (!p.nextDueDate || p.nextDueDate === 'TBD') {
            // If filtering by specific date, hide TBD unless we want to show everything
            return false;
        }

        const d = new Date(p.nextDueDate);
        const pMonth = d.getMonth();
        const pYear = d.getFullYear();

        const monthMatch = (month === 'all') || (pMonth == month);
        const yearMatch = (year === 'all') || (pYear == year);

        return monthMatch && yearMatch;
    });

    updateStats(filtered);
    populateTable(filtered);

    // Update reminders based on current filter state
    const activeBtn = document.querySelector('.reminder-filters .active');
    populateReminders(filtered, activeBtn ? activeBtn.dataset.filter : 'all');
}

function loadDashboardData() {
    // 1. Get data from localStorage (Project Intake Form)
    const localData = localStorage.getItem('projectIntakeFormData');

    // Add dummy data for demonstration
    const dummyProjects = [
        {
            projectName: "Luxury Villa - Gurgaon",
            clientEmail: "rajesh@example.com",
            clientName: "Rajesh Kumar",
            totalValue: 5000000,
            received: 1500000,
            currentStage: "Foundation",
            nextDueDate: "2026-01-28", // Matches Jan 2026
            nextDueAmount: 500000,
            id: "PRJ-2026-001"
        },
        {
            projectName: "Commercial Complex - Noida",
            clientEmail: "priya@example.com",
            clientName: "Priya Sharma",
            totalValue: 12500000,
            received: 5000000,
            currentStage: "Structure",
            nextDueDate: "2026-02-10", // Matches Feb 2026
            nextDueAmount: 1250000,
            id: "PRJ-2026-015"
        },
        {
            projectName: "Interior Design - Delhi",
            clientEmail: "amit@example.com",
            clientName: "Amit Verma",
            totalValue: 800000,
            received: 425000,
            currentStage: "Finishing",
            nextDueDate: "2026-02-15", // Matches Feb 2026
            nextDueAmount: 375000,
            id: "PRJ-2026-008"
        }
    ];

    globalProjects = [...dummyProjects];

    // If we have a locally saved project (from the intake form), add it to the top
    if (localData) {
        try {
            const newProject = JSON.parse(localData);

            // Calculate totals from payment stages
            let totalVal = 0;
            let paidVal = 0;
            let nextDue = null;
            let nextDueAmount = 0;
            let stageName = "Intake";

            if (newProject.paymentStages && Array.isArray(newProject.paymentStages)) {
                newProject.paymentStages.forEach(stage => {
                    const amount = parseFloat(stage.amount) || 0;
                    totalVal += amount;
                    if (stage.status === 'paid') {
                        paidVal += amount;
                    }

                    // Logic to find next due date (first unpaid)
                    if (stage.status !== 'paid' && !nextDue) {
                        nextDue = stage.dueDate;
                        stageName = stage.name;
                        nextDueAmount = amount;
                    }
                });
            }

            // Client Name derivation
            let cName = "New Client";
            if (newProject.clientEmail) {
                cName = newProject.clientEmail.split('@')[0];
                cName = cName.charAt(0).toUpperCase() + cName.slice(1);
            }

            globalProjects.unshift({
                projectName: newProject.projectName,
                clientEmail: newProject.clientEmail || "client@example.com",
                clientName: cName,
                totalValue: totalVal,
                received: paidVal,
                currentStage: stageName,
                nextDueDate: nextDue || "TBD",
                nextDueAmount: nextDueAmount,
                id: newProject.referenceNumber || "NEW-001",
                paymentStages: newProject.paymentStages // Pass actual stages for timeline
            });
        } catch (e) {
            console.error("Error loading local project:", e);
        }
    }

    // Apply Filter logic immediately (which defaults to Current Month/Year)
    filterDashboardData();
}

function updateStats(projects) {
    const totalProjects = projects.length;

    let totalRevenue = 0;
    let pendingCount = 0;
    let dates = [];

    projects.forEach(p => {
        totalRevenue += p.totalValue;
        if (p.nextDueDate && p.nextDueDate !== 'TBD') {
            pendingCount++;
            dates.push(new Date(p.nextDueDate));
        }
    });

    // Find nearest date
    let nearestDate = "-";
    if (dates.length > 0) {
        dates.sort((a, b) => a - b); // Ascending
        const nearest = dates[0];
        nearestDate = nearest.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    const formattedRevenue = "₹" + (totalRevenue / 100000).toFixed(1) + "L";

    document.getElementById('totalProjects').textContent = totalProjects;
    document.getElementById('totalRevenue').textContent = formattedRevenue;
    document.getElementById('pendingPayments').textContent = pendingCount;
    document.getElementById('nextDueDate').textContent = nearestDate;
}

function populateTable(projects) {
    const tbody = document.getElementById('projectsTableBody');
    tbody.innerHTML = '';

    if (projects.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--text-muted);">No projects found for this period.</td></tr>';
        return;
    }

    projects.forEach((project, index) => {
        // Main Row
        const tr = document.createElement('tr');
        const rowId = `project-details-${index}`;

        const total = formatCurrency(project.totalValue);
        const received = formatCurrency(project.received);
        
        // Get last payment date - find the last paid stage
        let lastPaymentDateStr = 'N/A';
        if (project.paymentStages && Array.isArray(project.paymentStages)) {
            const paidStages = project.paymentStages.filter(s => s.status === 'paid');
            if (paidStages.length > 0) {
                const lastPaidStage = paidStages[paidStages.length - 1];
                lastPaymentDateStr = formatDate(lastPaidStage.dueDate);
            }
        } else {
            // Fallback for dummy data - use nextDueDate
            lastPaymentDateStr = formatDate(project.nextDueDate);
        }

        // Calculate Stage Number ("Stage 1", "Stage 2" etc.)
        let stageLabel = project.currentStage; // Default fallback

        if (project.paymentStages && Array.isArray(project.paymentStages)) {
            // Find index of current stage
            const stageIdx = project.paymentStages.findIndex(s => s.name === project.currentStage);
            if (stageIdx !== -1) {
                stageLabel = `Stage ${stageIdx + 1}`;
            }
        } else {
            // Fallback for dummy data mapping
            const dummyMap = {
                "Intake": "Stage 1",
                "Foundation": "Stage 1",
                "Structure": "Stage 2",
                "Finishing": "Stage 3",
                "Completion": "Stage 4"
            };
            if (dummyMap[project.currentStage]) {
                stageLabel = dummyMap[project.currentStage];
            }
        }

        tr.innerHTML = `
            <td>
                <div style="display: flex; align-items: start; gap: 0.75rem;">
                     <button class="btn-toggle-details" onclick="toggleDetails('${rowId}', this)">
                        <i class="fas fa-chevron-down"></i>
                     </button>
                     <div>
                        <div style="font-weight: 500; color: var(--gray-900);">${project.projectName}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">${project.id}</div>
                     </div>
                </div>
            </td>
            <td>${project.clientEmail}</td>
            <td>
                <div style="display:flex; flex-direction:column; gap:0.25rem;">
                    <span class="status-badge status-active" style="width:fit-content;">${stageLabel}</span>
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">
                        ${formatCurrency(project.nextDueAmount || 0)}
                    </span>
                </div>
            </td>
            <td>${total}</td>
            <td>${received}</td>
            <td>${lastPaymentDateStr}</td>
            <td>
                <button class="action-btn" title="View Details"><i class="fas fa-eye"></i></button>
                <button class="action-btn" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn" title="Export to Excel"><i class="fas fa-file-excel"></i></button>
            </td>
        `;

        // Details Row
        const detailsTr = document.createElement('tr');
        detailsTr.id = rowId;
        detailsTr.className = 'project-details-row';

        const timelineHTML = renderTimeline(project);

        detailsTr.innerHTML = `
            <td colspan="7" style="padding: 0;">
                <div class="timeline-container">
                    <h3 style="margin: 0 0 1.5rem 1rem; font-size: 0.9rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Payment Timeline & Reminders</h3>
                    <div class="timeline">
                        ${timelineHTML}
                    </div>
                </div>
            </td>
        `;

        tbody.appendChild(tr);
        tbody.appendChild(detailsTr);
    });
}

function toggleDetails(rowId, btn) {
    const row = document.getElementById(rowId);
    if (row) {
        row.classList.toggle('details-visible');
        btn.classList.toggle('expanded');
    }
}

function renderTimeline(project) {
    // 1. Real Data (from local storage)
    if (project.paymentStages && Array.isArray(project.paymentStages)) {
        return project.paymentStages.map((stage, idx) => {
            const isPaid = stage.status === 'paid';
            let statusClass = '';
            let icon = '<i class="fas fa-circle" style="font-size: 8px;"></i>';
            let meta = `<span><i class="fas fa-clock"></i> Pending</span>`;

            if (isPaid) {
                statusClass = 'completed';
                icon = '<i class="fas fa-check"></i>';
                meta = `<span><i class="fas fa-check-circle"></i> Paid on ${formatDate(stage.dueDate)}</span>`;
            } else if (project.currentStage === stage.name) {
                statusClass = 'active';
                meta = `
                    <span><i class="fas fa-calendar"></i> Due: ${formatDate(stage.dueDate)}</span>
                    <span style="margin-left: 1rem;"><i class="fab fa-whatsapp"></i> 1 Reminder sent</span>
                `;
            } else {
                meta = `<span><i class="fas fa-calendar"></i> Due: ${formatDate(stage.dueDate)}</span>`;
            }

            const stageId = `stage-${idx + 1}`;
            const currentStatus = stage.status || 'pending';
            
            return `
                <div class="timeline-item ${statusClass}">
                    <div class="timeline-dot">${icon}</div>
                    <div class="timeline-content">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div style="flex: 1;">
                                <h4>Stage ${idx + 1}</h4>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <div class="stage-status-dropdown-wrapper">
                                    <span class="stage-status-label">Status:</span>
                                    <select class="stage-status-dropdown" onchange="changeStageStatus('${stageId}', this.value)">
                                        <option value="pending" ${currentStatus === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="partially-paid" ${currentStatus === 'partially-paid' ? 'selected' : ''}>Partially Paid</option>
                                        <option value="fully-paid" ${currentStatus === 'fully-paid' ? 'selected' : ''}>Fully Paid</option>
                                        <option value="overdue" ${currentStatus === 'overdue' ? 'selected' : ''}>Overdue</option>
                                        <option value="completed" ${currentStatus === 'completed' ? 'selected' : ''}>Completed</option>
                                        <option value="not-started" ${currentStatus === 'not-started' ? 'selected' : ''}>Not Started</option>
                                    </select>
                                </div>
                                <button class="stage-timeline-icon" onclick="openReminderModal('${stageId}')" title="View reminder history">
                                    <i class="fas fa-history"></i>
                                </button>
                            </div>
                        </div>
                        <p style="color: var(--gray-900); font-weight: 500; margin-bottom: 0.1rem;">${stage.name}</p>
                        <p>${formatCurrency(parseFloat(stage.amount))} <span style="color:var(--text-muted); font-size:0.75rem;">- ${stage.notes || ''}</span></p>
                        <div class="timeline-meta">
                            ${meta}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // 2. Dummy Data Fallback
    return `
        <div class="timeline-item completed">
            <div class="timeline-dot"><i class="fas fa-check"></i></div>
            <div class="timeline-content">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="flex: 1;">
                        <h4>Stage 1</h4>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <div class="stage-status-dropdown-wrapper">
                            <span class="stage-status-label">Status:</span>
                            <select class="stage-status-dropdown" onchange="changeStageStatus('stage-1', this.value)">
                                <option value="pending">Pending</option>
                                <option value="partially-paid">Partially Paid</option>
                                <option value="fully-paid" selected>Fully Paid</option>
                                <option value="overdue">Overdue</option>
                                <option value="completed">Completed</option>
                                <option value="not-started">Not Started</option>
                            </select>
                        </div>
                        <button class="stage-timeline-icon" onclick="openReminderModal('stage-1')" title="View reminder history">
                            <i class="fas fa-history"></i>
                        </button>
                    </div>
                </div>
                <p style="color: var(--gray-900); font-weight: 500; margin-bottom: 0.1rem;">Booking Amount</p>
                <p>₹5,00,000 received via Bank Transfer</p>
                <div class="timeline-meta">
                    <span><i class="fas fa-calendar"></i> Paid on Jan 10, 2026</span>
                    <span><i class="fas fa-envelope"></i> Invoice #INV-001 sent</span>
                </div>
            </div>
        </div>
        <div class="timeline-item active">
            <div class="timeline-dot"><i class="fas fa-circle" style="font-size: 8px;"></i></div>
            <div class="timeline-content">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="flex: 1;">
                        <h4>Stage 2</h4>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <div class="stage-status-dropdown-wrapper">
                            <span class="stage-status-label">Status:</span>
                            <select class="stage-status-dropdown" onchange="changeStageStatus('stage-2', this.value)">
                                <option value="pending" selected>Pending</option>
                                <option value="partially-paid">Partially Paid</option>
                                <option value="fully-paid">Fully Paid</option>
                                <option value="overdue">Overdue</option>
                                <option value="completed">Completed</option>
                                <option value="not-started">Not Started</option>
                            </select>
                        </div>
                        <button class="stage-timeline-icon" onclick="openReminderModal('stage-2')" title="View reminder history">
                            <i class="fas fa-history"></i>
                        </button>
                    </div>
                </div>
                <p style="color: var(--gray-900); font-weight: 500; margin-bottom: 0.1rem;">${project.currentStage}</p>
                <p>Payment pending for stage completion</p>
                <div class="timeline-meta">
                    <span><i class="fas fa-calendar"></i> Due: ${formatDate(project.nextDueDate)}</span>
                    <span><i class="fab fa-whatsapp"></i> 2 Reminders sent</span>
                </div>
            </div>
        </div>
        <div class="timeline-item">
            <div class="timeline-dot"><i class="fas fa-circle" style="font-size: 8px;"></i></div>
            <div class="timeline-content">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="flex: 1;">
                        <h4>Stage 3</h4>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <div class="stage-status-dropdown-wrapper">
                            <span class="stage-status-label">Status:</span>
                            <select class="stage-status-dropdown" onchange="changeStageStatus('stage-3', this.value)">
                                <option value="pending" selected>Pending</option>
                                <option value="partially-paid">Partially Paid</option>
                                <option value="fully-paid">Fully Paid</option>
                                <option value="overdue">Overdue</option>
                                <option value="completed">Completed</option>
                                <option value="not-started">Not Started</option>
                            </select>
                        </div>
                        <button class="stage-timeline-icon" onclick="openReminderModal('stage-3')" title="View reminder history">
                            <i class="fas fa-history"></i>
                        </button>
                    </div>
                </div>
                <p style="color: var(--gray-900); font-weight: 500; margin-bottom: 0.1rem;">Completion</p>
                <p>Final handover payment</p>
                <div class="timeline-meta">
                    <span><i class="fas fa-calendar"></i> Est: Mar 15, 2026</span>
                </div>
            </div>
        </div>
    `;
}

function populateReminders(projects, filter = 'all') {
    const list = document.getElementById('remindersList');
    list.innerHTML = '';

    let overdueCount = 0;
    let upcomingCount = 0;
    let totalDue = 0;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (projects.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:1rem; color:var(--text-muted); font-size:0.875rem;">No reminders for this period</div>';
    }

    projects.forEach(p => {
        if (!p.nextDueDate || p.nextDueDate === 'TBD') return;

        const dueDate = new Date(p.nextDueDate);
        const isOverdue = dueDate < today;
        const status = isOverdue ? 'overdue' : 'upcoming';

        if (status === 'overdue') overdueCount++;
        else upcomingCount++;

        totalDue += (p.nextDueAmount || 0);

        if (filter !== 'all' && filter !== status) return;

        const item = document.createElement('div');
        item.className = `reminder-item ${status}`;

        const badgeText = isOverdue ? 'Overdue' : 'Due Soon';
        const formattedAmount = formatCurrency(p.nextDueAmount || 0);
        const formattedDate = formatDate(p.nextDueDate);

        item.innerHTML = `
            <div class="reminder-header">
                <span class="project-ref">${p.id}</span>
                <span class="reminder-badge ${status}">${badgeText}</span>
            </div>
            <h4 class="project-name">${p.projectName}</h4>
            <div class="reminder-details">
                <div class="detail-row">
                    <i class="fas fa-layer-group"></i>
                    <span>${p.currentStage}</span>
                </div>
                <div class="detail-row">
                    <i class="fas fa-rupee-sign"></i>
                    <span>${formattedAmount}</span>
                </div>
                <div class="detail-row">
                    <i class="fas fa-calendar"></i>
                    <span>Due: ${formattedDate}</span>
                </div>
                <div class="detail-row">
                    <i class="fas fa-user"></i>
                    <span>${p.clientName || 'Client'}</span>
                </div>
            </div>
            <button class="btn-send-reminder" onclick="alert('Reminder sent to ${p.clientEmail} via WhatsApp!')">
                <i class="fab fa-whatsapp"></i>
                Send Reminder
            </button>
        `;
        list.appendChild(item);
    });

    const totalDueFormatted = "₹" + (totalDue / 100000).toFixed(2) + "L";
    const statItems = document.querySelectorAll('.reminder-stats .stat-item');
    if (statItems.length >= 3) {
        statItems[0].querySelector('.stat-value').textContent = overdueCount;
        statItems[1].querySelector('.stat-value').textContent = upcomingCount;
        statItems[2].querySelector('.stat-value').textContent = totalDueFormatted;
    }
}

function setupReminderFilters() {
    const filters = document.querySelector('.reminder-filters');
    if (!filters) return;

    // Use event delegation for better performance
    filters.addEventListener('click', (e) => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;

        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Re-run population using GLOBAL filtered state? 
        // We need to re-fetch the filtered set.
        filterDashboardData();
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumSignificantDigits: 3 }).format(amount);
}

function formatDate(dateString) {
    if (!dateString || dateString === 'TBD') return 'TBD';
    try {
        const d = new Date(dateString);
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (e) { return dateString; }
}
