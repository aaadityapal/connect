document.addEventListener('DOMContentLoaded', () => {
    loadRemindersData();
});

let globalRemindersData = [];

function loadRemindersData() {
    const container = document.getElementById('remindersContainer');

    const localData = localStorage.getItem('projectIntakeFormData');

    // Dummy Data with full paymentStages for expansion
    const dummyProjects = [
        {
            projectName: "Luxury Villa - Gurgaon",
            clientEmail: "rajesh@example.com",
            currentStage: "Foundation",
            nextDueDate: "2026-01-28",
            nextDueAmount: 500000,
            id: "PRJ-2026-001",
            reminderCount: 2,
            lastReminderSent: "2026-01-25",
            paymentStages: [
                { name: "Booking Amount", amount: 500000, dueDate: "2026-01-10", status: "paid" },
                { name: "Foundation", amount: 500000, dueDate: "2026-01-28", status: "pending" },
                { name: "Structure", amount: 1500000, dueDate: "2026-03-15", status: "pending" },
                { name: "Finishing", amount: 1000000, dueDate: "2026-05-20", status: "pending" }
            ]
        },
        {
            projectName: "Commercial Complex - Noida",
            clientEmail: "priya@example.com",
            currentStage: "Structure",
            nextDueDate: "2026-02-10",
            nextDueAmount: 1250000,
            id: "PRJ-2026-015",
            reminderCount: 1,
            lastReminderSent: "2026-02-01",
            paymentStages: [
                { name: "Booking Amount", amount: 5000000, dueDate: "2025-12-01", status: "paid" },
                { name: "Structure", amount: 1250000, dueDate: "2026-02-10", status: "pending" },
                { name: "Interiors", amount: 2000000, dueDate: "2026-06-15", status: "pending" }
            ]
        },
        {
            projectName: "Interior Design - Delhi",
            clientEmail: "amit@example.com",
            currentStage: "Finishing",
            nextDueDate: "2026-02-15",
            nextDueAmount: 375000,
            id: "PRJ-2026-008",
            reminderCount: 0,
            lastReminderSent: null,
            paymentStages: [
                { name: "Design Phase", amount: 200000, dueDate: "2026-01-05", status: "paid" },
                { name: "Material Procurement", amount: 400000, dueDate: "2026-01-20", status: "paid" },
                { name: "Finishing", amount: 375000, dueDate: "2026-02-15", status: "pending" }
            ]
        }
    ];

    globalRemindersData = [...dummyProjects];

    // Local data merge logic
    if (localData) {
        try {
            const p = JSON.parse(localData);

            // Re-construct logic
            let activeStage = "Intake";
            let amount = 0;
            let due = "TBD";

            if (p.paymentStages) {
                const firstUnpaid = p.paymentStages.find(s => s.status !== 'paid');
                if (firstUnpaid) {
                    activeStage = firstUnpaid.name;
                    amount = firstUnpaid.amount;
                    due = firstUnpaid.dueDate;
                }
            }

            globalRemindersData.unshift({
                projectName: p.projectName,
                clientEmail: p.clientEmail,
                currentStage: activeStage,
                nextDueDate: due,
                nextDueAmount: amount,
                id: p.referenceNumber || "NEW-001",
                reminderCount: 0,
                lastReminderSent: null,
                paymentStages: p.paymentStages || []
            });
        } catch (e) { console.error(e); }
    }

    renderReminders(globalRemindersData);
}

function renderReminders(projects, filter = 'all') {
    const container = document.getElementById('remindersContainer');
    container.innerHTML = '';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let count = 0;

    projects.forEach((p, index) => {
        if (p.nextDueDate === 'TBD') return;

        const dueDate = new Date(p.nextDueDate);
        const isOverdue = dueDate < today;

        if (filter === 'overdue' && !isOverdue) return;

        count++;

        const cardId = `reminder-card-${index}`;
        const card = document.createElement('div');
        card.className = 'reminder-project-card';

        const stageClass = isOverdue ? 'overdue' : '';
        const statusText = isOverdue ? 'Overdue' : 'Upcoming';
        const badgeStyle = isOverdue
            ? 'background:#fee2e2; color:#991b1b;'
            : 'background:#dbeafe; color:#1e40af;';

        // Generate Stage List HTML
        let stagesHTML = '';
        if (p.paymentStages && p.paymentStages.length > 0) {
            stagesHTML = p.paymentStages.map((stage, sIdx) => {
                const isPaid = stage.status === 'paid';
                const stageStatusClass = isPaid ? 'text-success' : 'text-pending';
                const icon = isPaid ? '<i class="fas fa-check-circle" style="color:#10b981;"></i>' : '<i class="far fa-circle"></i>';

                // Button logic: Show only if NOT paid
                const actionBtn = isPaid
                    ? `<span style="font-size:0.75rem; color:#10b981; font-weight:600;">Paid</span>`
                    : `<button class="btn-micro-reminder" onclick="sendStageReminder('${p.id}', '${stage.name}')">
                         <i class="fas fa-paper-plane"></i> Send
                       </button>`;

                return `
                    <div class="reminder-stage-item" style="display:flex; justify-content:space-between; align-items:center; padding: 0.75rem 0; border-bottom:1px solid var(--border-light);">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            ${icon}
                            <div>
                                <div style="font-weight:500; font-size:0.9rem;">Stage ${sIdx + 1}: ${stage.name}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${formatDate(stage.dueDate)} • ${formatCurrency(stage.amount)}</div>
                            </div>
                        </div>
                        <div>${actionBtn}</div>
                    </div>
                `;
            }).join('');
        } else {
            stagesHTML = '<div style="padding:1rem; color:var(--text-muted); font-size:0.875rem;">No stage details available.</div>';
        }

        // Card HTML
        card.innerHTML = `
            <div class="reminder-project-header" style="cursor:pointer;" onclick="toggleProjectStages('${cardId}', this)">
                <div style="flex:1;">
                    <div style="display:flex; justify-content:space-between; align-items:start;">
                        <h3>${p.projectName}</h3>
                         <div style="padding:0.25rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:600; ${badgeStyle}">${statusText}</div> 
                    </div>
                    <div class="reminder-client-ref">${p.id} • ${p.clientEmail}</div>
                </div>
                <div style="margin-left:1rem; padding-top:0.25rem;">
                    <i class="fas fa-chevron-down toggle-icon" style="color:var(--gray-400); transition:transform 0.2s;"></i>
                </div>
            </div>
            
            <!-- Primary Active Stage Info (Always Visible) -->
            <div class="reminder-stage-block ${stageClass}">
                <div class="reminder-stage-header">
                    <span>${p.currentStage}</span>
                    <span>${formatCurrency(p.nextDueAmount)}</span>
                </div>
                <div class="reminder-stat-row">
                    <span>Due Date:</span>
                    <span style="font-weight:500;">${formatDate(p.nextDueDate)}</span>
                </div>
                 <div class="reminder-stat-row">
                    <span>Reminders Sent:</span>
                    <span>${p.reminderCount || 0} times</span>
                </div>

                <div class="reminder-actions">
                    <button class="btn-reminder-action" onclick="sendReminder('${p.id}', this)">
                        <i class="fas fa-paper-plane"></i>
                         Send Reminder Again
                    </button>
                </div>
            </div>

            <!-- Expanded Stage List (Hidden by default) -->
            <div id="${cardId}" class="project-stages-expandable" style="display:none; margin-top:1rem; border-top:1px dashed var(--border-color); padding-top:0.5rem;">
                <h4 style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem; letter-spacing:0.05em;">All Project Stages</h4>
                ${stagesHTML}
            </div>
        `;
        container.appendChild(card);
    });

    if (count === 0) {
        container.innerHTML = '<div style="text-align:center; grid-column:1/-1; padding:3rem; color:var(--text-muted);">No reminders found for this filter.</div>';
    }
}

function toggleProjectStages(cardId, header) {
    const list = document.getElementById(cardId);
    const icon = header.querySelector('.toggle-icon');

    if (list.style.display === 'none') {
        list.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        list.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

function filterReminders(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderReminders(globalRemindersData, type);
}

function sendReminder(id, btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Sent!';
    btn.style.background = '#10b981';
    btn.disabled = true;
    setTimeout(() => {
        alert(`Main Stage Reminder sent to client for ${id}`);
        btn.innerHTML = originalText;
        btn.style.background = '';
        btn.disabled = false;
    }, 500);
}

function sendStageReminder(id, stageName) {
    alert(`Specific reminder for "${stageName}" sent to client for project ${id}`);
}

function formatCurrency(amount) {
    if (!amount) return '₹0';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumSignificantDigits: 3 }).format(amount);
}
function formatDate(dateString) {
    if (!dateString || dateString === 'TBD') return '';
    try {
        const d = new Date(dateString);
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (e) { return dateString; }
}
