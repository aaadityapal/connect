(function() {
    let alertedIds = JSON.parse(localStorage.getItem('alertedDeadlineTasks') || '[]');

    document.addEventListener('DOMContentLoaded', () => {
        // Initial check after 3 seconds
        setTimeout(checkUpcomingDeadlines, 3000);
        
        // Check every 60 seconds
        setInterval(checkUpcomingDeadlines, 60000);

        const closeBtn = document.getElementById('closeUpcomingDeadlineModal');
        if (closeBtn) {
            closeBtn.onclick = closeUpcomingModal;
        }
    });

    function checkUpcomingDeadlines() {
        // Check if user has snoozed alerts
        const snoozeUntil = localStorage.getItem('udmAlertSnoozeUntil');
        if (snoozeUntil && Date.now() < parseInt(snoozeUntil)) {
            return; 
        }

        fetch('api/check_upcoming_deadlines.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.tasks && data.tasks.length > 0) {
                    // Show modal with all current tasks
                    showUpcomingWarning(data.tasks);
                }
            })
            .catch(err => console.warn('[Upcoming Deadline Check] failed:', err));
    }

    function showUpcomingWarning(tasks) {
        const overlay = document.getElementById('upcomingDeadlineModalOverlay');
        const listContainer = document.getElementById('udmTaskList');
        if (!overlay || !listContainer) return;
        
        // Count overdue vs upcoming
        const overdueCount = tasks.filter(t => t.titlePrefix.includes("Missed")).length;
        const total = tasks.length;
        
        document.querySelector('.udm-title').innerHTML = overdueCount > 0 
            ? `Action Required! ⚠️ <span style="font-size:0.8rem; font-weight:500; opacity:0.7; margin-left:8px;">(${total} items)</span>`
            : `Deadlines Approaching ⏱️ <span style="font-size:0.8rem; font-weight:500; opacity:0.7; margin-left:8px;">(${total} items)</span>`;
            
        listContainer.innerHTML = '';
        
        tasks.forEach(task => {
            const isOverdue = task.titlePrefix.includes("Missed");
            const item = document.createElement('div');
            item.className = 'udm-task-item';
            item.style.borderLeft = `4px solid ${task.dotColor}`;
            
            item.innerHTML = `
                <div class="udm-task-info">
                    <div class="udm-task-name">${task.projectStage || task.title}</div>
                    <div class="udm-task-badge" style="background:${task.bgColor}; color:${task.dotColor};">
                        <i class="fa-regular fa-clock" style="margin-right:4px;"></i>${task.time_remaining_label}
                    </div>
                </div>
                <div class="udm-task-actions">
                    <button class="udm-action-btn udm-secondary-btn" data-id="${task.id}" data-action="extend">
                        <i class="fa-solid fa-clock-rotate-left"></i> Extend
                    </button>
                    <button class="udm-action-btn udm-primary-btn" data-id="${task.id}" data-action="finish">
                        <i class="fa-solid fa-check"></i> Mark as Done
                    </button>
                </div>
            `;
            
            // Wire clicks
            item.querySelector('[data-action="extend"]').onclick = () => {
                closeUpcomingModal();
                if (typeof window.openExtendDeadlineModal === 'function') {
                    window.openExtendDeadlineModal(task, 'schedule');
                }
            };
            
            item.querySelector('[data-action="finish"]').onclick = () => {
                closeUpcomingModal();
                if (window.TaskModal && typeof window.TaskModal.open === 'function') {
                    window.TaskModal.open(task);
                }
            };

            listContainer.appendChild(item);
        });

        // Snooze footer 
        const snoozeBtn = document.getElementById('udmSnoozeBtn');
        if (snoozeBtn) {
            snoozeBtn.onclick = () => {
                const twoHours = 2 * 60 * 60 * 1000;
                const snoozeUntil = Date.now() + twoHours;
                
                // Save locally
                localStorage.setItem('udmAlertSnoozeUntil', snoozeUntil.toString());
                
                // Log to backend
                fetch('api/log_deadline_snooze.php', { method: 'POST' })
                    .catch(err => console.warn('[Log Snooze] failed:', err));

                closeUpcomingModal();
            };
        }
        
        overlay.style.display = 'flex';
        
        // Play notification sound with interaction check
        playNotificationSound();
    }

    function playNotificationSound() {
        const audio = new Audio('tones/task_popup.wav');
        audio.play().catch(e => {
            // Silently catch autoplay restrictions to avoid console errors or 
            // unexpected playback later on a random click.
        });
    }

    function closeUpcomingModal() {
        const overlay = document.getElementById('upcomingDeadlineModalOverlay');
        if(overlay) overlay.style.display = 'none';
    }

    // Export close to global just in case
    window.closeUpcomingDeadlineModal = closeUpcomingModal;
})();
