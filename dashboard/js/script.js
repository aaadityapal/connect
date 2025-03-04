// Toggle Sidebar
document.querySelector('.toggle-btn').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('collapsed');
});

// Update greeting and theme based on time
function updateGreeting() {
    const greetingElement = document.querySelector('.greeting-section');
    const greetingText = document.getElementById('greeting-time');
    const userName = document.getElementById('user-name');
    const hour = new Date().getHours();
    let greeting;
    
    // Set appropriate greeting and theme
    if (hour >= 5 && hour < 12) {
        greeting = "Good Morning";
        greetingElement.classList.add('greeting-morning');
    } else if (hour >= 12 && hour < 17) {
        greeting = "Good Afternoon";
        greetingElement.classList.add('greeting-afternoon');
    } else if (hour >= 17 && hour < 20) {
        greeting = "Good Evening";
        greetingElement.classList.add('greeting-evening');
    } else {
        greeting = "Good Night";
        greetingElement.classList.add('greeting-night');
    }

    const currentTime = new Date().toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: false 
    });

    
    if (greetingText) {
        greetingText.textContent = `${greeting}! It's ${currentTime}`;
        greetingText.style.cssText = `
            font-size: 1.1rem;
            color: #5a6c7d;
            margin: 0;
        `;
    }

    if (greetingElement) {
        greetingElement.style.cssText = `
            background-color: #fff3e0;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.1);
        `;
    }
}

// Update greeting every second
updateGreeting();
setInterval(updateGreeting, 1000);

// You can add user name dynamically
// document.getElementById('user-name').textContent = "John Doe"; // Uncomment and modify as needed 

// Handle Punch Button
document.addEventListener('DOMContentLoaded', function() {
    const punchButton = document.getElementById('punchButton');
    
    // Add notification div to body if it doesn't exist
    if (!document.getElementById('notification')) {
        const notificationDiv = document.createElement('div');
        notificationDiv.id = 'notification';
        notificationDiv.style.cssText = `
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            transition: all 0.3s ease;
        `;
        document.body.appendChild(notificationDiv);
    }

    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.style.backgroundColor = type === 'success' ? '#4CAF50' : '#f44336';
        notification.style.display = 'block';
        
        // Add animation
        notification.style.animation = 'slideIn 0.5s forwards';
        
        // Hide after 5 seconds for punch-out message (longer to read the time)
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.5s forwards';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 500);
        }, 5000);
    }

    // Function to check punch status and update button
    function checkPunchStatus() {
        fetch('punch_attendance.php?action=check_status', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Status check:', data); // Debug log
            if (data.is_punched_in && !data.is_punched_out) {
                // User is punched in but not out
                punchButton.classList.add('active');
                punchButton.innerHTML = '<i class="fas fa-fingerprint"></i> Punch Out';
            } else {
                // User either hasn't punched in or has completed punch out
                punchButton.classList.remove('active');
                punchButton.innerHTML = '<i class="fas fa-fingerprint"></i> Punch In';
            }
        })
        .catch(error => {
            console.error('Status check error:', error);
            // Default to Punch In on error
            punchButton.classList.remove('active');
            punchButton.innerHTML = '<i class="fas fa-fingerprint"></i> Punch In';
        });
    }

    // Check status immediately when page loads
    checkPunchStatus();

    punchButton.addEventListener('click', function() {
        punchButton.disabled = true;
        punchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        fetch('punch_attendance.php', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: punchButton.classList.contains('active') ? 'punch_out' : 'punch_in'
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (data.message.includes('Punched in')) {
                    punchButton.classList.add('active');
                    punchButton.innerHTML = '<i class="fas fa-fingerprint"></i> Punch Out';
                    showNotification('✅ Punched in successfully!');
                } else {
                    punchButton.classList.remove('active');
                    punchButton.innerHTML = '<i class="fas fa-fingerprint"></i> Punch In';
                    showNotification(`✅ Punched out successfully!\nYou worked for ${data.workingTime}`);
                }
                // Check status again after successful punch
                checkPunchStatus();
            } else {
                punchButton.innerHTML = '<i class="fas fa-fingerprint"></i> Punch In';
                showNotification(data.message || 'Error recording attendance', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            punchButton.innerHTML = '<i class="fas fa-fingerprint"></i> Punch In';
            showNotification('Failed to record attendance. Please try again.', 'error');
        })
        .finally(() => {
            punchButton.disabled = false;
        });
    });

    // Notification click handler
    const notificationIcon = document.querySelector('.notification-icon');
    notificationIcon.addEventListener('click', function() {
        // Add your notification panel logic here
        console.log('Notifications clicked');
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const profileAvatar = document.getElementById('profileAvatar');
    const profileDropdown = document.getElementById('profileDropdown');
    
    // Toggle dropdown
    profileAvatar.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
        
        // Position the dropdown relative to avatar
        const avatarRect = profileAvatar.getBoundingClientRect();
        profileDropdown.style.top = (avatarRect.bottom + 10) + 'px';
        profileDropdown.style.left = avatarRect.left + 'px';
    });
    
    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!profileDropdown.contains(e.target) && e.target !== profileAvatar) {
            profileDropdown.classList.remove('show');
        }
    });

    // Dark mode functionality
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;
    
    // Check for saved dark mode preference
    const darkMode = localStorage.getItem('darkMode') === 'true';
    
    // Apply dark mode if saved
    if (darkMode) {
        body.classList.add('dark-mode');
        darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>Light Mode';
    }
    
    // Dark mode toggle handler
    darkModeToggle.addEventListener('click', function(e) {
        e.preventDefault();
        body.classList.toggle('dark-mode');
        
        // Update button text and icon
        const isDarkMode = body.classList.contains('dark-mode');
        this.innerHTML = isDarkMode ? 
            '<i class="fas fa-sun"></i>Light Mode' : 
            '<i class="fas fa-moon"></i>Dark Mode';
        
        // Save preference
        localStorage.setItem('darkMode', isDarkMode);
        
        // Close dropdown after selection
        profileDropdown.classList.remove('show');
    });
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    #notification {
        white-space: pre-line;
        line-height: 1.5;
    }
`;
document.head.appendChild(style);

// Calendar functionality
let currentDate = new Date();

// Function to fetch month data
async function fetchMonthData(year, month) {
    try {
        const response = await fetch(`get_month_data.php?year=${year}&month=${month}`);
        const text = await response.text(); // Get raw response first
        console.log('Raw response:', text); // Log the raw response
        
        try {
            return JSON.parse(text); // Try to parse it
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.log('Invalid JSON received:', text);
            return {};
        }
    } catch (error) {
        console.error('Fetch error:', error);
        return {};
    }
}

function generateCalendar(date) {
    const month = date.getMonth();
    const year = date.getFullYear();
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    
    // Fetch attendance and leave data for the entire month
    fetchMonthData(year, month + 1).then(monthData => {
        if (!monthData || typeof monthData !== 'object') {
            console.error('Invalid month data received:', monthData);
            monthData = {};
        }

        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
        
        const daysInMonth = lastDay.getDate();
        const startingDay = firstDay.getDay();
        
        let calendarHTML = `
            <div class="calendar-header">
                <button class="calendar-nav prev"><i class="fas fa-chevron-left"></i></button>
                <h4>${monthNames[month]} ${year}</h4>
                <button class="calendar-nav next"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="calendar-body">
                <div class="calendar-weekdays">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>
                <div class="calendar-dates">
        `;
        
        // Add empty cells for days before the first day of the month
        for (let i = 0; i < startingDay; i++) {
            calendarHTML += '<div class="calendar-date empty"></div>';
        }
        
        // Add cells for each day of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const currentDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayData = monthData[currentDate] || { present: 0, onLeave: 0, upcomingLeaves: [] };
            
            const isToday = day === new Date().getDate() && 
                           month === new Date().getMonth() && 
                           year === new Date().getFullYear();
            
            calendarHTML += `
                <div class="calendar-date ${isToday ? 'today' : ''}" data-date="${currentDate}">
                    ${day}
                    <div class="date-tooltip">
                        <div class="tooltip-header">
                            <h4>${day} ${monthNames[month]} ${year}</h4>
                        </div>
                        <div class="tooltip-body">
                            <div class="attendance-stat">
                                <i class="fas fa-user-check"></i>
                                <span>Present: ${dayData.present || 0}</span>
                            </div>
                            <div class="leave-stat">
                                <i class="fas fa-user-clock"></i>
                                <span>On Leave: ${dayData.onLeave || 0}</span>
                            </div>
                            ${dayData.upcomingLeaves && dayData.upcomingLeaves.length > 0 ? `
                                <div class="upcoming-leaves">
                                    <h5>Employees on Leave:</h5>
                                    ${dayData.upcomingLeaves.map(leave => `
                                        <div class="leave-user">
                                            <span class="username">${leave.username}</span>
                                            <span class="leave-type">${leave.leaveType}</span>
                                            <span class="leave-duration">
                                                ${formatDateRange(leave.startDate, leave.endDate)}
                                            </span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>`;
        }
        
        calendarHTML += `
                </div>
            </div>
        `;
        
        const calendarElement = document.getElementById('mini-calendar');
        if (calendarElement) {
            calendarElement.innerHTML = calendarHTML;
            setupDateTooltips();
            setupCalendarNavigation();
        }
    }).catch(error => {
        console.error('Error generating calendar:', error);
    });
}

// Function to setup date tooltips
function setupDateTooltips() {
    const dates = document.querySelectorAll('.calendar-date:not(.empty)');
    
    dates.forEach(date => {
        const tooltip = date.querySelector('.date-tooltip');
        
        if (tooltip) {
            date.addEventListener('mouseenter', function(e) {
                // Hide all other tooltips
                document.querySelectorAll('.date-tooltip').forEach(t => {
                    t.style.display = 'none';
                });
                
                // Position and show this tooltip
                tooltip.style.display = 'block';
                
                // Position tooltip above the date
                const dateRect = date.getBoundingClientRect();
                const tooltipRect = tooltip.getBoundingClientRect();
                
                tooltip.style.top = `${-tooltipRect.height - 10}px`;
                tooltip.style.left = '50%';
                tooltip.style.transform = 'translateX(-50%)';
            });
            
            date.addEventListener('mouseleave', function(e) {
                tooltip.style.display = 'none';
            });
        }
    });
}

// Initialize calendar when page loads
document.addEventListener('DOMContentLoaded', function() {
    generateCalendar(currentDate);
});

// Update the tooltip positioning
document.addEventListener('DOMContentLoaded', function() {
    // Function to handle tooltip display
    function setupTooltip(cardSelector, tooltipId) {
        const card = document.querySelector(cardSelector);
        const tooltip = document.getElementById(tooltipId);
        
        if (card && tooltip) {
            card.addEventListener('mouseenter', function(e) {
                // Hide all other tooltips first
                document.querySelectorAll('.tooltip-content').forEach(t => {
                    t.style.display = 'none';
                    t.style.opacity = '0';
                });

                // Show this tooltip
                tooltip.style.display = 'block';
                
                // Add animation
                requestAnimationFrame(() => {
                    tooltip.style.opacity = '1';
                    tooltip.style.transform = 'translateX(-50%) translateY(0)';
                });
            });

            card.addEventListener('mouseleave', function(e) {
                const tooltipRect = tooltip.getBoundingClientRect();
                const mouseY = e.clientY;
                const mouseX = e.clientX;

                if (mouseY < tooltipRect.top || mouseY > tooltipRect.bottom ||
                    mouseX < tooltipRect.left || mouseX > tooltipRect.right) {
                    hideTooltip(tooltip);
                }
            });

            tooltip.addEventListener('mouseleave', () => hideTooltip(tooltip));
        }
    }

    function hideTooltip(tooltip) {
        tooltip.style.opacity = '0';
        tooltip.style.transform = 'translateX(-50%) translateY(-10px)';
        setTimeout(() => {
            tooltip.style.display = 'none';
        }, 200);
    }

    // Setup tooltips for both cards
    setupTooltip('.overview-card.present', 'presentTooltip');
    setupTooltip('.overview-card.pending', 'pendingLeavesTooltip');
    setupTooltip('.overview-card.leave', 'onLeaveTooltip');
    setupTooltip('.overview-card.short', 'shortLeavesTooltip');
});

// Add leave action handling
let currentLeaveAction = {
    leaveId: null,
    action: null,
    element: null
};

function handleLeaveAction(leaveId, action, element) {
    const leaveItem = element.closest('.leave-request-item');
    const username = leaveItem.querySelector('.tooltip-username').textContent;
    const duration = leaveItem.querySelector('.leave-date').textContent;

    currentLeaveAction = {
        leaveId,
        action,
        element
    };

    // Update dialog content
    document.getElementById('dialogEmployeeName').textContent = username;
    document.getElementById('dialogLeaveDuration').textContent = duration;
    document.getElementById('actionType').textContent = action;
    document.getElementById('actionReason').value = '';

    // Show dialog
    const dialog = document.getElementById('leaveActionDialog');
    dialog.style.display = 'flex';
    setTimeout(() => dialog.classList.add('active'), 50);
}

function submitLeaveAction(reason) {
    const { leaveId, action, element } = currentLeaveAction;
    const leaveItem = element.closest('.leave-request-item');
    
    // Disable buttons while processing
    const buttons = leaveItem.querySelectorAll('button');
    buttons.forEach(btn => btn.disabled = true);
    
    // Get manager's ID from meta tag
    const managerId = document.querySelector('meta[name="user-id"]').content;
    
    // Prepare data for submission
    const data = {
        leave_id: leaveId,
        action: action,
        manager_action_reason: reason,
        manager_action_by: managerId,
        manager_action_at: new Date().toISOString()
    };
    
    // Send request to server
    fetch('handle_leave_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the leave item with animation
            leaveItem.style.opacity = '0';
            setTimeout(() => {
                leaveItem.remove();
                // Update the counter
                const counter = document.querySelector('.overview-card.pending .number');
                const currentCount = parseInt(counter.textContent);
                counter.textContent = currentCount - 1;
            }, 300);
            
            showNotification(`Leave request ${action}ed successfully`, 'success');
        } else {
            throw new Error(data.message || 'Failed to process leave request');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(error.message, 'error');
        // Re-enable buttons on error
        buttons.forEach(btn => btn.disabled = false);
    });
}

// Add event listeners for dialog
document.addEventListener('DOMContentLoaded', function() {
    const dialog = document.getElementById('leaveActionDialog');
    
    // Close dialog on cancel or X button
    document.querySelector('.close-dialog').addEventListener('click', () => {
        dialog.classList.remove('active');
        setTimeout(() => dialog.style.display = 'none', 300);
    });
    
    document.querySelector('.cancel-btn').addEventListener('click', () => {
        dialog.classList.remove('active');
        setTimeout(() => dialog.style.display = 'none', 300);
    });
    
    // Handle confirm action
    document.getElementById('confirmActionBtn').addEventListener('click', () => {
        const reason = document.getElementById('actionReason').value.trim();
        if (!reason) {
            showNotification('Please provide a reason for your action', 'error');
            return;
        }
        
        submitLeaveAction(reason);
        dialog.classList.remove('active');
        setTimeout(() => dialog.style.display = 'none', 300);
    });
});

// Add this notification function at the top of your script file
function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Create notification content
    notification.innerHTML = `
        <div class="notification-content">
            ${type === 'success' 
                ? '<i class="fas fa-check-circle"></i>' 
                : '<i class="fas fa-exclamation-circle"></i>'
            }
            <span>${message}</span>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove after delay
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add this helper function for date formatting
function formatDateRange(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (startDate === endDate) {
        return start.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    return `${start.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric'
    })} - ${end.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric'
    })}`;
}

// Add this to your existing JavaScript
document.addEventListener('DOMContentLoaded', async function() {
    const viewToggle = document.querySelector('.view-toggle');
    const taskOverviewCards = document.querySelector('.task-overview-cards');
    const taskCalendarView = document.querySelector('.task-calendar-view');

    // Show overview by default
    if (taskOverviewCards && taskCalendarView) {
        taskOverviewCards.style.display = 'grid';
        taskCalendarView.style.display = 'none';
        // Set overview radio button as checked
        const overviewRadio = document.getElementById('overviewView');
        if (overviewRadio) {
            overviewRadio.checked = true;
        }
    }

    // Rest of your existing toggle logic
    if (viewToggle) {
        viewToggle.addEventListener('change', async function(e) {
            const selectedView = e.target.value;
            
            if (selectedView === 'overview') {
                taskOverviewCards.style.display = 'grid';
                taskCalendarView.style.display = 'none';
            } else {
                taskOverviewCards.style.display = 'none';
                taskCalendarView.style.display = 'block';
                await generateTaskCalendar();
            }
        });
    }
});

// Function to fetch tasks for the current month
async function fetchMonthTasks(year, month) {
    try {
        const response = await fetch(`dashboard/handlers/get_month_tasks.php?year=${year}&month=${month + 1}`);
        const data = await response.json();
        return data.tasks || [];
    } catch (error) {
        console.error('Error fetching tasks:', error);
        return [];
    }
}

// Add this to your existing JavaScript
async function generateTaskCalendar(date = new Date()) {
    const year = date.getFullYear();
    const month = date.getMonth();
    
    // Fetch tasks before generating calendar
    const monthTasks = await fetchMonthTasks(year, month);
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDay = firstDay.getDay();
    
    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    
    // Update calendar title
    const calendarTitle = document.querySelector('.task-calendar-title');
    if (calendarTitle) {
        calendarTitle.textContent = `${monthNames[month]} ${year}`;
    }
    
    let calendarHTML = '';
    
    // Empty boxes for days before the first day of the month
    for (let i = 0; i < startingDay; i++) {
        calendarHTML += '<div class="task-calendar-date empty"></div>';
    }
    
    // Generate calendar dates
    for (let day = 1; day <= daysInMonth; day++) {
        const currentDate = new Date(year, month, day);
        const isToday = new Date().toDateString() === currentDate.toDateString();
        const dateClass = isToday ? 'task-calendar-date today' : 'task-calendar-date';
        const position = (startingDay + day - 1) % 7;
        
        // Filter tasks for this day
        const dayTasks = monthTasks.filter(task => {
            const taskDate = new Date(task.start_date);
            return taskDate.getDate() === day && 
                   taskDate.getMonth() === month && 
                   taskDate.getFullYear() === year;
        });
        
        // Count tasks by status
        const taskCounts = {
            pending: dayTasks.filter(t => t.status === 'pending').length,
            in_progress: dayTasks.filter(t => t.status === 'in_progress').length,
            completed: dayTasks.filter(t => t.status === 'completed').length,
            on_hold: dayTasks.filter(t => t.status === 'on_hold').length
        };
        
        // Generate tasks HTML
        const tasksHTML = dayTasks.map(task => `
            <div class="calendar-task task-${task.status} project-type-${(task.project_type || 'default').toLowerCase()}" data-task-id="${task.id}">
                <div class="project-type-indicator"></div>
                <span class="task-title">${task.title}</span>
                <div class="task-meta">
                    <span class="task-time">${new Date(task.start_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                    <span class="task-assignee">${task.assignee_name || 'Unassigned'}</span>
                </div>
            </div>
        `).join('');
        
        // Create a preview of tasks (limited to 2-3 tasks)
        const taskPreviewHTML = dayTasks.slice(0, 2).map(task => `
            <div class="calendar-task-preview task-${task.status} project-type-${(task.project_type || 'default').toLowerCase()}" 
                 data-task-id="${task.id}" 
                 onclick="handleTaskClick(event)">
                <div class="project-type-indicator"></div>
                <span class="task-title">${task.title}</span>
            </div>
        `).join('');
        
        calendarHTML += `
            <div class="${dateClass}" data-position="${position}" data-date="${year}-${month + 1}-${day}">
                <div class="date-header">
                    <span class="date-number">${day}</span>
                    <div class="task-indicator">
                        ${taskCounts.pending > 0 ? `<span class="indicator-dot pending" title="${taskCounts.pending} pending"></span>` : ''}
                        ${taskCounts.in_progress > 0 ? `<span class="indicator-dot in-progress" title="${taskCounts.in_progress} in progress"></span>` : ''}
                        ${taskCounts.completed > 0 ? `<span class="indicator-dot completed" title="${taskCounts.completed} completed"></span>` : ''}
                        ${taskCounts.on_hold > 0 ? `<span class="indicator-dot on-hold" title="${taskCounts.on_hold} on hold"></span>` : ''}
                    </div>
                </div>
                <div class="task-preview-container">
                    ${taskPreviewHTML}
                </div>
                <div class="tasks-container">
                    ${tasksHTML}
                </div>
                <div class="add-task-icon">
                    <i class="fas fa-plus"></i>
                </div>
            </div>
        `;
    }
    
    const calendarDates = document.getElementById('taskCalendarDates');
    if (calendarDates) {
        calendarDates.innerHTML = calendarHTML;
    }
    
    // Add these function calls
    setupCalendarInteractions();
    setupCalendarNavigation();
}

// Function to setup calendar interactions
function setupCalendarInteractions() {
    // Add double click handlers for date boxes
    const dateBoxes = document.querySelectorAll('.task-calendar-date');
    dateBoxes.forEach(box => {
        box.addEventListener('dblclick', function(e) {
            // Remove expanded class from any previously expanded box
            document.querySelectorAll('.task-calendar-date.expanded')
                .forEach(expandedBox => {
                    if (expandedBox !== this) {
                        expandedBox.classList.remove('expanded');
                    }
                });
            
            // Toggle expanded class on current box
            this.classList.toggle('expanded');
        });
    });

    // Add click handlers for the add task icons
    const addTaskIcons = document.querySelectorAll('.add-task-icon');
    addTaskIcons.forEach(icon => {
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
            const dateCell = this.closest('.task-calendar-date');
            const selectedDate = dateCell.dataset.date;
            
            // Get the task dialog
            const taskDialog = document.getElementById('addTaskDialog');
            if (taskDialog) {
                // Set the date in the form
                document.getElementById('taskStartDate').value = selectedDate;
                document.getElementById('taskDueDate').value = selectedDate;
                
                // Show the dialog
                taskDialog.style.display = 'flex';
                setTimeout(() => taskDialog.classList.add('active'), 50);
            }
        });
    });

    // Add navigation event listeners
    const prevButton = document.querySelector('.task-calendar-nav.prev');
    const nextButton = document.querySelector('.task-calendar-nav.next');

    if (prevButton) {
        prevButton.onclick = async () => {
            const currentDate = new Date(document.querySelector('.task-calendar-title').textContent);
            const newDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
            await generateTaskCalendar(newDate);
        };
    }

    if (nextButton) {
        nextButton.onclick = async () => {
            const currentDate = new Date(document.querySelector('.task-calendar-title').textContent);
            const newDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
            await generateTaskCalendar(newDate);
        };
    }

    // Add click handlers for tasks
    const taskPreviews = document.querySelectorAll('.calendar-task-preview');
    console.log('Found task previews:', taskPreviews.length);
    taskPreviews.forEach(preview => {
        preview.addEventListener('click', handleTaskClick);
    });
}

// Combined task click handler
function handleTaskClick(event) {
    event.preventDefault();
    event.stopPropagation();
    
    console.log('Task clicked:', event.target);
    
    const taskElement = event.target.closest('.calendar-task-preview, .calendar-task');
    if (!taskElement) {
        console.log('No task element found');
        return;
    }
    
    console.log('Task element found:', taskElement);
    
    // Get the project/task ID
    const taskId = taskElement.getAttribute('data-task-id');
    if (!taskId) {
        console.log('No task ID found');
        return;
    }
    
    console.log('Task ID:', taskId);
    
    // Fetch and show project details
    fetchProjectDetails(taskId);
}

// Function to fetch project details
async function fetchProjectDetails(projectId) {
    try {
        console.log('Fetching project details for ID:', projectId);
        
        const response = await fetch(`dashboard/handlers/get_project_details.php?project_id=${projectId}`);
        const data = await response.json();
        
        console.log('Received data:', data);
        
        if (data.success) {
            showProjectDetailsModal(data.project);
        } else {
            console.error('Error fetching project details:', data.message);
            // You can show an error notification here
        }
    } catch (error) {
        console.error('Error:', error);
        // You can show an error notification here
    }
}

// Update the project details modal display function
function showProjectDetailsModal(project) {
    console.log('Showing modal for project:', project);
    
    const modal = document.getElementById('projectDetailsModal');
    if (!modal) {
        console.error('Project details modal not found');
        return;
    }

    try {
        const modalHeader = modal.querySelector('.modal-header');
        
        // Remove any existing project type classes
        modalHeader.classList.remove(
            'project-type-architecture',
            'project-type-interior',
            'project-type-construction'
        );
        
        // Add the appropriate project type class
        const projectType = (project.project_type || '').toLowerCase();
        if (['architecture', 'interior', 'construction'].includes(projectType)) {
            modalHeader.classList.add(`project-type-${projectType}`);
        }

        // Populate modal content
        modal.querySelector('.project-title').textContent = project.title || 'Untitled Project';
        modal.querySelector('.project-manager').textContent = project.created_by_username || 'N/A';
        modal.querySelector('.project-timeline').textContent = 
            `${formatDate(project.start_date)} - ${formatDate(project.end_date)}`;
        modal.querySelector('.project-type').textContent = project.project_type || 'N/A';
        modal.querySelector('.project-status').textContent = project.status || 'Pending';
        modal.querySelector('.project-description').textContent = project.description || 'No description available';

        // Populate stages
        const stagesList = modal.querySelector('.stages-list');
        stagesList.innerHTML = '';

        if (project.stages && project.stages.length > 0) {
            project.stages.forEach(stage => {
                const stageStatus = (stage.status || 'pending').toLowerCase();
                const stageElement = document.createElement('div');
                stageElement.className = `stage-item status-${stageStatus}`;
                stageElement.innerHTML = `
                    <div class="stage-header">
                        <h5>Stage ${stage.stage_number}</h5>
                        <select class="stage-status-dropdown" onchange="updateStageStatus(${stage.id}, this.value, this)">
                            <option value="not_started" ${stageStatus === 'not_started' ? 'selected' : ''}>Not Started</option>
                            <option value="pending" ${stageStatus === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="in_progress" ${stageStatus === 'in_progress' ? 'selected' : ''}>In Progress</option>
                            <option value="in_review" ${stageStatus === 'in_review' ? 'selected' : ''}>In Review</option>
                            <option value="completed" ${stageStatus === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="on_hold" ${stageStatus === 'on_hold' ? 'selected' : ''}>On Hold</option>
                            <option value="cancelled" ${stageStatus === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            <option value="blocked" ${stageStatus === 'blocked' ? 'selected' : ''}>Blocked</option>
                            <option value="freezed" ${stageStatus === 'freezed' ? 'selected' : ''}>Freezed</option>
                            <option value="sent_to_client" ${stageStatus === 'sent_to_client' ? 'selected' : ''}>Sent to Client</option>
                        </select>
                        <span class="status-badge status-${stageStatus}">
                            ${stage.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                        </span>
                    </div>
                    <div class="stage-meta">
                        <span><i class="fas fa-user"></i> ${stage.assignee_name || 'Unassigned'}</span>
                        <span><i class="fas fa-calendar"></i> Due: ${formatDate(stage.end_date)}</span>
                    </div>
                    ${stage.substages && stage.substages.length > 0 ? `
                        <div class="substages-list">
                            ${stage.substages.map(substage => `
                                <div class="substage-item status-${substage.status}" data-substage-id="${substage.id}">
                                    <div class="substage-header">
                                        <h6>${substage.title}</h6>
                                        <span class="status-badge status-${substage.status}">
                                            ${substage.status || 'Pending'}
                                        </span>
                                    </div>
                                    <div class="stage-meta">
                                        <span><i class="fas fa-user"></i> ${substage.assignee_name || 'Unassigned'}</span>
                                        <span><i class="fas fa-calendar"></i> Due: ${formatDate(substage.end_date)}</span>
                                    </div>
                                    <div class="toggle-files" data-substage-id="${substage.id}">
                                        <i class="fas fa-chevron-down"></i> 
                                    </div>
                                    <table class="substage-files-table" id="files-table-${substage.id}" style="display: none;">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>File Name</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Files will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                `;
                stagesList.appendChild(stageElement);
            });

            // Update the toggle click handler
            document.querySelectorAll('.toggle-files').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const substageId = this.dataset.substageId;
                    const table = document.getElementById(`files-table-${substageId}`);
                    const icon = this.querySelector('i');
                    
                    // Toggle the active class on the button
                    this.classList.toggle('active');
                    
                    // Toggle table visibility using display property
                    if (table.style.display === 'none' || !table.style.display) {
                        table.style.display = 'table';
                    } else {
                        table.style.display = 'none';
                    }
                    
                    // Rotate the icon
                    if (this.classList.contains('active')) {
                        icon.style.transform = 'rotate(180deg)';
                    } else {
                        icon.style.transform = 'rotate(0deg)';
                    }
                });
            });
        } else {
            stagesList.innerHTML = '<p class="no-stages">No stages defined for this project</p>';
        }

        // Show modal
        modal.style.display = 'block';
        
        // Add close button functionality
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) {
            closeBtn.onclick = () => modal.style.display = 'none';
        }
        
        // Close on outside click
        window.onclick = (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
        
    } catch (error) {
        console.error('Error populating modal:', error);
    }
}

// Helper function to format dates
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...');
    
    // Add click handlers to all calendar tasks
    const calendar = document.querySelector('.task-calendar-dates');
    if (calendar) {
        console.log('Calendar found, adding click handlers');
        calendar.addEventListener('click', handleTaskClick);
    } else {
        console.log('Calendar not found');
    }
});

// Keep only one global stageCount variable
let stageCount = 0;
let substageCounters = {};

// Keep only one global createStageHTML function
window.createStageHTML = function(stageNumber, dates = null) {
    substageCounters[stageNumber] = 0;
    return `
        <div class="stage-block" data-stage="${stageNumber}">
            <div class="stage-header">
                <h4 class="stage-title">Stage ${stageNumber}</h4>
                <button class="remove-stage-btn" onclick="removeStage(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="task-form-group">
                <label>Assign To</label>
                <select class="stage-assignee">
                    <option value="">Select Employee</option>
                </select>
            </div>
            <div class="task-form-row">
                <div class="task-form-group">
                    <label>Start Date & Time</label>
                    <div class="task-datetime-input">
                        <input type="datetime-local" class="stage-start-date" 
                            value="${dates ? dates.startDate : ''}" required>
                    </div>
                </div>
                <div class="task-form-group">
                    <label>Due By</label>
                    <div class="task-datetime-input">
                        <input type="datetime-local" class="stage-due-date" 
                            value="${dates ? dates.endDate : ''}" required>
                    </div>
                </div>
            </div>
            <div class="file-upload-container">
                <input type="file" id="stageFile${stageNumber}" class="file-upload-input">
                <label for="stageFile${stageNumber}" class="file-upload-label">
                    <i class="fas fa-paperclip"></i>
                    <span>Attach File</span>
                </label>
                <div class="selected-file"></div>
            </div>
            
            <!-- Substages Section -->
            <div class="substages-container">
                <div class="substages-wrapper" id="substagesWrapper${stageNumber}">
                    <!-- Substages will be added here -->
                </div>
                <button class="add-substage-btn" onclick="addSubstage(${stageNumber})">
                    <i class="fas fa-plus"></i>
                    <span>Add Substage</span>
                </button>
            </div>
        </div>
    `;
};

// Update addStage function to ensure sequential numbering
window.addStage = function() {
    // Get the stages wrapper
    const stagesWrapper = document.getElementById('stagesWrapper');
    
    // Get all existing stages and calculate next stage number
    const existingStages = stagesWrapper.querySelectorAll('.stage-block');
    const nextStageNumber = existingStages.length + 1;
    
    // Get project dates
    const projectStartDate = document.getElementById('projectStartDate').value;
    const projectEndDate = document.getElementById('projectDueDate').value;
    
    // Calculate stage dates
    const stageDates = calculateDistributedDates(projectStartDate, projectEndDate, nextStageNumber);
    const currentStageDates = stageDates[nextStageNumber - 1];
    
    // Create new stage with the correct number
    const newStage = createStageHTML(nextStageNumber, currentStageDates);
    stagesWrapper.insertAdjacentHTML('beforeend', newStage);

    // Setup the new stage
    const stageElement = stagesWrapper.lastElementChild;
    populateStageAssignee(stageElement);

    // Set the dates
    const startDateInput = stageElement.querySelector('.stage-start-date');
    const dueDateInput = stageElement.querySelector('.stage-due-date');
    
    if (startDateInput && dueDateInput) {
        startDateInput.value = currentStageDates.startDate;
        dueDateInput.value = currentStageDates.endDate;
    }

    // Setup file input
    const fileInput = document.getElementById(`stageFile${nextStageNumber}`);
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }
    
    // Update global stageCount
    stageCount = nextStageNumber;
};

// Update removeStage function to ensure proper renumbering
window.removeStage = function(button) {
    const stageBlock = button.closest('.stage-block');
    const stagesWrapper = document.getElementById('stagesWrapper');
    
    // Remove the stage
    stageBlock.remove();
    
    // Get all remaining stages
    const remainingStages = stagesWrapper.querySelectorAll('.stage-block');
    
    // Renumber all remaining stages sequentially
    remainingStages.forEach((stage, index) => {
        const newStageNumber = index + 1;
        
        // Update stage title
        const stageTitle = stage.querySelector('.stage-title');
        if (stageTitle) {
            stageTitle.textContent = `Stage ${newStageNumber}`;
        }
        
        // Update data attribute
        stage.setAttribute('data-stage', newStageNumber);
        
        // Update file input ID and label
        const fileInput = stage.querySelector('.file-upload-input');
        const fileLabel = stage.querySelector('.file-upload-label');
        if (fileInput && fileLabel) {
            const newFileId = `stageFile${newStageNumber}`;
            fileInput.id = newFileId;
            fileLabel.setAttribute('for', newFileId);
        }
        
        // Update substages wrapper ID
        const substagesWrapper = stage.querySelector('.substages-wrapper');
        if (substagesWrapper) {
            substagesWrapper.id = `substagesWrapper${newStageNumber}`;
        }
    });
    
    // Update global stageCount
    stageCount = remainingStages.length;
};

// Add Task Dialog Functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Script loaded!');
    
    const addTaskBtn = document.querySelector('.add-task-btn');
    const taskDialog = document.getElementById('addTaskDialog');
    const closeTaskDialog = document.querySelector('.task-close-dialog');
    const cancelTaskBtn = document.querySelector('.task-cancel-btn');
    const saveTaskBtn = document.getElementById('saveTaskBtn');
    
    // Function to create stage HTML
    function createStageHTML(stageNumber) {
        return `
            <div class="stage-block" data-stage="${stageNumber}">
                <div class="stage-header">
                    <h4 class="stage-title">Stage ${stageNumber}</h4>
                    <button class="remove-stage-btn" onclick="removeStage(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="task-form-group">
                    <label>Assign To</label>
                    <select class="stage-assignee">
                        <option value="">Select Employee</option>
                    </select>
                </div>
                <div class="task-form-row">
                    <div class="task-form-group">
                        <label>Start Date & Time</label>
                        <div class="task-datetime-input">
                            <input type="datetime-local" class="stage-start-date">
                        </div>
                    </div>
                    <div class="task-form-group">
                        <label>Due By</label>
                        <div class="task-datetime-input">
                            <input type="datetime-local" class="stage-due-date">
                        </div>
                    </div>
                </div>
                <div class="file-upload-container">
                    <input type="file" id="stageFile${stageNumber}" class="file-upload-input">
                    <label for="stageFile${stageNumber}" class="file-upload-label">
                        <i class="fas fa-paperclip"></i>
                        <span>Attach File</span>
                    </label>
                    <div class="selected-file"></div>
                </div>
                
                <!-- Substages Section -->
                <div class="substages-container">
                    <div class="substages-wrapper" id="substagesWrapper${stageNumber}">
                        <!-- Substages will be added here -->
                    </div>
                    <button class="add-substage-btn" onclick="addSubstage(${stageNumber})">
                        <i class="fas fa-plus"></i>
                        <span>Add Substage</span>
                    </button>
                </div>
            </div>
        `;
    }

    function createSubstageHTML(stageNumber, substageNumber) {
        // Get the current project type
        const projectType = document.getElementById('projectType').value;
        
        // Get the stage assignee value
        const stageBlock = document.querySelector(`.stage-block[data-stage="${stageNumber}"]`);
        const stageAssignee = stageBlock ? stageBlock.querySelector('.stage-assignee').value : '';
        
        // Base HTML structure
        let substageOptions = '';
        
        // Set options based on project type
        if (projectType === 'architecture') {
            substageOptions = `
                <optgroup label="Concept Drawings">
                    <option value="Concept Plan">Concept Plan</option>
                    <option value="PPT">PPT</option>
                    <option value="3D Model">3D Model</option>
                </optgroup>

                <optgroup label="Structure Drawings - All Floor">
                    <option value="Excavation Layout Plan">Excavation Layout Plan</option>
                    <option value="Setting Layout Plan">Setting Layout Plan</option>
                    <option value="Foundation Plan">Foundation Plan</option>
                    <option value="Foundation Details">Foundation Details</option>
                    <option value="Column Layout Plan">Column Layout Plan</option>
                    <option value="Column Details">Column Details</option>
                    <option value="Footing Layout Plan">Footing Layout Plan</option>
                    <option value="Column & Setting Layout Plan">Column & Setting Layout Plan</option>
                    <option value="Column & Footing Details">Column & Footing Details</option>
                    <option value="Plinth Beam Layout Plan">Plinth Beam Layout Plan</option>
                    <option value="Basement Roof Slab Beam Layout Plan">Basement Roof Slab Beam Layout Plan</option>
                    <option value="Stilt Roof Slab Beam Layout Plan">Stilt Roof Slab Beam Layout Plan</option>
                    <option value="Stilt Floor Roof Slab Beam Layout Plan">Stilt Floor Roof Slab Beam Layout Plan</option>
                    <option value="Ground Floor Roof Slab Beam Layout Plan">Ground Floor Roof Slab Beam Layout Plan</option>
                    <option value="First Floor Roof Slab Beam Layout Plan">First Floor Roof Slab Beam Layout Plan</option>
                    <option value="Second Floor Roof Slab Beam Layout Plan">Second Floor Roof Slab Beam Layout Plan</option>
                    <option value="Third Floor Roof Slab Beam Layout Plan">Third Floor Roof Slab Beam Layout Plan</option>
                    <option value="Fourth Floor Roof Slab Beam Layout Plan">Fourth Floor Roof Slab Beam Layout Plan</option>
                    <option value="Fifth Floor Roof Slab Beam Layout Plan">Fifth Floor Roof Slab Beam Layout Plan</option>
                    <option value="Terrace Roof Slab Beam Layout Plan">Terrace Roof Slab Beam Layout Plan</option>
                    <option value="Basement Slab Beam Details">Basement Slab Beam Details</option>
                    <option value="Stilt Floor Slab Beam Details">Stilt Floor Slab Beam Details</option>
                    <option value="Ground Floor Slab Beam Details">Ground Floor Slab Beam Details</option>
                    <option value="First Floor Slab Beam Details">First Floor Slab Beam Details</option>
                    <option value="Second Floor Slab Beam Details">Second Floor Slab Beam Details</option>
                    <option value="Third Floor Slab Beam Details">Third Floor Slab Beam Details</option>
                    <option value="Fourth Floor Slab Beam Details">Fourth Floor Slab Beam Details</option>
                    <option value="Fifth Floor Slab Beam Details">Fifth Floor Slab Beam Details</option>
                    <option value="Terrace Slab Beam Details">Terrace Slab Beam Details</option>
                </optgroup>
                
                <optgroup label="Architecture Working Drawings - All Floor">
                    <option value="Basement Furniture Layout Plan">Basement Furniture Layout Plan</option>
                    <option value="Stilt Floor Furniture Layout Plan">Stilt Floor Furniture Layout Plan</option>
                    <option value="Ground Floor Furniture Layout Plan">Ground Floor Furniture Layout Plan</option>
                    <option value="First Floor Furniture Layout Plan">First Floor Furniture Layout Plan</option>
                    <option value="Second Floor Furniture Layout Plan">Second Floor Furniture Layout Plan</option>
                    <option value="Third Floor Furniture Layout Plan">Third Floor Furniture Layout Plan</option>
                    <option value="Fourth Floor Furniture Layout Plan">Fourth Floor Furniture Layout Plan</option>
                    <option value="Fifth Floor Furniture Layout Plan">Fifth Floor Furniture Layout Plan</option>
                    <option value="Terrace Furniture Layout Plan">Terrace Furniture Layout Plan</option>
                    <option value="Basement Working Layout Plan">Basement Working Layout Plan</option>
                    <option value="Stilt Working Layout Plan">Stilt Working Layout Plan</option>
                    <option value="Ground Floor Working Layout Plan">Ground Floor Working Layout Plan</option>
                    <option value="First Floor Working Layout Plan">First Floor Working Layout Plan</option>
                    <option value="Second Floor Working Layout Plan">Second Floor Working Layout Plan</option>
                    <option value="Third Floor Working Layout Plan">Third Floor Working Layout Plan</option>
                    <option value="Fourth Floor Working Layout Plan">Fourth Floor Working Layout Plan</option>
                    <option value="Fifth Floor Working Layout Plan">Fifth Floor Working Layout Plan</option>
                    <option value="Terrace Working Layout Plan">Terrace Working Layout Plan</option>
                    <option value="Basement Door & Window Schedule Details">Basement Door & Window Schedule Details</option>
                    <option value="Stilt Floor Door Window Schedule & Details">Stilt Floor Door Window Schedule & Details</option>
                    <option value="Ground Floor Door Window Schedule & Details">Ground Floor Door Window Schedule & Details</option>
                    <option value="First Floor Door Window Schedule & Details">First Floor Door Window Schedule & Details</option>
                    <option value="Second Floor Door Window Schedule & Details">Second Floor Door Window Schedule & Details</option>
                    <option value="Third Floor Door Window Schedule & Details">Third Floor Door Window Schedule & Details</option>
                    <option value="Fourth Floor Door Window Schedule & Details">Fourth Floor Door Window Schedule & Details</option>
                    <option value="Fifth Floor Door Window Schedule & Details">Fifth Floor Door Window Schedule & Details</option>
                    <option value="Terrace Door Window Schedule & Details">Terrace Door Window Schedule & Details</option>
                    <option value="Front Elevation Details">Front Elevation Details</option>
                    <option value="Rear Elevation Details">Rear Elevation Details</option>
                    <option value="Side 1 Elevation Details">Side 1 Elevation Details</option>
                    <option value="Side 2 Elevation Details">Side 2 Elevation Details</option>
                    <option value="Section Elevations X-X">Section Elevations X-X</option>
                    <option value="Section Elevations Y-Y">Section Elevations Y-Y</option>
                    <option value="Site Plans">Site Plans</option>
                </optgroup>
                
                <optgroup label="Electrical Drawings - All Floor">
                    <option value="Basement Wall Electrical Layout">Basement Wall Electrical Layout</option>
                    <option value="Stilt Floor Wall Electrical Layout">Stilt Floor Wall Electrical Layout</option>
                    <option value="Ground Floor Wall Electrical Layout">Ground Floor Wall Electrical Layout</option>
                    <option value="First Floor Wall Electrical Layout">First Floor Wall Electrical Layout</option>
                    <option value="Second Floor Wall Electrical Layout">Second Floor Wall Electrical Layout</option>
                    <option value="Third Floor Wall Electrical Layout">Third Floor Wall Electrical Layout</option>
                    <option value="Fourth Floor Wall Electrical Layout">Fourth Floor Wall Electrical Layout</option>
                    <option value="Fifth Floor Wall Electrical Layout">Fifth Floor Wall Electrical Layout</option>
                    <option value="Terrace Wall Electrical Layout">Terrace Wall Electrical Layout</option>
                </optgroup>
                
                <optgroup label="Ceiling Drawings - All Floor">
                    <option value="Basement Ceiling Layout Plan">Basement Ceiling Layout Plan</option>
                    <option value="Stilt Floor Ceiling Layout Plan">Stilt Floor Ceiling Layout Plan</option>
                    <option value="Ground Floor Ceiling Layout Plan">Ground Floor Ceiling Layout Plan</option>
                    <option value="First Floor Ceiling Layout Plan">First Floor Ceiling Layout Plan</option>
                    <option value="Second Floor Ceiling Layout Plan">Second Floor Ceiling Layout Plan</option>
                    <option value="Third Floor Ceiling Layout Plan">Third Floor Ceiling Layout Plan</option>
                    <option value="Fourth Floor Ceiling Layout Plan">Fourth Floor Ceiling Layout Plan</option>
                    <option value="Fifth Floor Ceiling Layout Plan">Fifth Floor Ceiling Layout Plan</option>
                    <option value="Terrace Ceiling Layout Plan">Terrace Ceiling Layout Plan</option>
                </optgroup>
                
                <optgroup label="Plumbing Drawings - All Floor">
                    <option value="Basement Plumbing Layout Plan">Basement Plumbing Layout Plan</option>
                    <option value="Stilt Floor Plumbing Layout Plan">Stilt Floor Plumbing Layout Plan</option>
                    <option value="Ground Floor Plumbing Layout Plan">Ground Floor Plumbing Layout Plan</option>
                    <option value="First Floor Plumbing Layout Plan">First Floor Plumbing Layout Plan</option>
                    <option value="Second Floor Plumbing Layout Plan">Second Floor Plumbing Layout Plan</option>
                    <option value="Third Floor Plumbing Layout Plan">Third Floor Plumbing Layout Plan</option>
                    <option value="Fourth Floor Plumbing Layout Plan">Fourth Floor Plumbing Layout Plan</option>
                    <option value="Fifth Floor Plumbing Layout Plan">Fifth Floor Plumbing Layout Plan</option>
                    <option value="Terrace Plumbing Layout Plan">Terrace Plumbing Layout Plan</option>
                </optgroup>
                
                <optgroup label="Water Supply Drawings - All Floor">
                    <option value="Basement Water Supply Layout Plan">Basement Water Supply Layout Plan</option>
                    <option value="Stilt Floor Water Supply Layout Plan">Stilt Floor Water Supply Layout Plan</option>
                    <option value="Ground Floor Water Supply Layout Plan">Ground Floor Water Supply Layout Plan</option>
                    <option value="First Floor Water Supply Layout Plan">First Floor Water Supply Layout Plan</option>
                    <option value="Second Floor Water Supply Layout Plan">Second Floor Water Supply Layout Plan</option>
                    <option value="Third Floor Water Supply Layout Plan">Third Floor Water Supply Layout Plan</option>
                    <option value="Fourth Floor Water Supply Layout Plan">Fourth Floor Water Supply Layout Plan</option>
                    <option value="Fifth Floor Water Supply Layout Plan">Fifth Floor Water Supply Layout Plan</option>
                    <option value="Terrace Water Supply Layout Plan">Terrace Water Supply Layout Plan</option>
                </optgroup>
                
                <optgroup label="Detail Drawings">
                    <option value="Staircase Details">Staircase Details</option>
                    <option value="Finishing Schedule">Finishing Schedule</option>
                    <option value="Ramp Details">Ramp Details</option>
                    <option value="Kitchen Details">Kitchen Details</option>
                    <option value="Lift Details">Lift Details</option>
                    <option value="Toilet Details">Toilet Details</option>
                    <option value="Saptic Tanks Details">Saptic Tanks Details</option>
                    <option value="Compound Wall Details">Compound Wall Details</option>
                    <option value="Landscape Details">Landscape Details</option>
                    <option value="Slab Details">Slab Details</option>
                    <option value="Slab Details">Slab Details</option>
                    <option value="Slab Details">Slab Details</option>
                </optgroup>
                
                <optgroup label="Other Drawings">
                    <option value="Site Plan">Site Plan</option>
                    <option value="Front Elevation">Front Elevation</option>
                    <option value="Rear Elevation">Rear Elevation</option>
                    <option value="Left Side Elevation">Left Side Elevation</option>
                    <option value="Right Side Elevation">Right Side Elevation</option>
                    <option value="Section A-A">Section A-A</option>
                    <option value="Section B-B">Section B-B</option>
                    <option value="Common Staircase Details">Common Staircase Details</option>
                    <option value="Toilet Detail">Toilet Detail</option>
                    <option value="Door & Window Schedule & Elevation Details">Door & Window Schedule & Elevation Details</option>
                    <option value="Compound Wall Detail">Compound Wall Detail</option>
                    <option value="Landscape Plan">Landscape Plan</option>
                </optgroup>
            `;
        } else if (projectType === 'interior') {
            substageOptions = `
                <optgroup label="Concept Design">
                     <option value="Concept Plan">Concept Plan</option>
                    <option value="PPT">PPT</option>
                    <option value="3D Views">3D Views</option>
                    <option value="Render Plan Basement">Render Plan Basement</option>
                    <option value="Stilt Plan">Stilt Plan</option>
                    <option value="Render Plan First Floor">Render Plan First Floor</option>
                    <option value="Render Plan Second Floor">Render Plan Second Floor</option>
                    <option value="Render Plan Third Floor">Render Plan Third Floor</option>
                    <option value="Render Plan Fourth Floor">Render Plan Fourth Floor</option>
                    <option value="Render Plan Fifth Floor">Render Plan Fifth Floor</option>
                    <option value="Render Plan Ground Floor">Render Plan Ground Floor</option>
                </optgroup>

                <optgroup label="3D Views">
                    <option value="Daughter's Bed Room">Daughter's Bed Room</option>
                    <option value="Son's Bed Room">Son's Bed Room</option>
                    <option value="Master Bed Room">Master Bed Room</option>
                    <option value="Guest Bed Room">Guest Bed Room</option>
                    <option value="Toilet -01">Toilet -01</option>
                    <option value="Toilet -02">Toilet -02</option>
                    <option value="Toilet -03">Toilet -03</option>
                    <option value="Toilet -04">Toilet -04</option>
                    <option value="Toilet -05">Toilet -05</option>
                    <option value="Prayer Room">Prayer Room</option>
                    <option value="Study Room">Study Room</option>  
                    <option value="Home Theater">Home Theater</option>
                    <option value="GYM / Multi-Purpose Room">GYM / Multi-Purpose Room</option>
                    <option value="Servant Room">Servant Room</option>
                    <option value="Family Lounge">Family Lounge</option>
                    <option value="Staircase">Staircase</option>
                    <option value="Landscape Area">Landscape Area</option>
                    <option value="Recreational Area">Recreational Area</option>
                    <option value="Swimming Pool">Swimming Pool</option>
                    <option value="Living & Dining Room">Living & Dining Room</option>
                    <option value="Living Room">Living Room</option>
                    <option value="Dining Room">Dining Room</option>
                    <option value="Kitchen">Kitchen</option>
                    <option value="Balcony - 01">Balcony - 01</option>
                    <option value="Balcony - 02">Balcony - 02</option>
                    <option value="Balcony - 03">Balcony - 03</option>
                    <option value="Balcony - 04">Balcony - 04</option>
                    <option value="Balcony - 05">Balcony - 05</option>
                    <option value="Utility Area">Utility Area</option>
                    <option value="Mumty False Ceiling Plan">Mumty False Ceiling Plan</option>
                    <option value="Mumty">Mumty</option>
                    <option value="Front Elevation">Front Elevation</option>
                    <option value="Rear Elevation">Rear Elevation</option>
                    <option value="Side 1 Elevation">Side 1 Elevation</option>
                    <option value="Side 2 Elevation">Side 2 Elevation</option>
                    <option value="Entrace Lobby">Entrace Lobby</option>
                    <option value="Manager's Cabin">Manager's Cabin</option>
                    <option value="Work Station Area - 01">Work Station Area - 01</option>
                    <option value="Work Station Area - 02">Work Station Area - 02</option>
                    <option value="Work Station Area - 03">Work Station Area - 03</option>
                    <option value="Work Station Area - 04">Work Station Area - 04</option>
                    <option value="Work Station Area - 05">Work Station Area - 05</option>
                    <option value="Work Station Area - 06">Work Station Area - 06</option>
                    <option value="Reception Area">Reception Area</option>
                    <option value="Conference Room">Conference Room</option>
                    <option value="Meeting Room">Meeting Room</option>
                    <option value="Waiting Area">Waiting Area</option> 
                    <option value="Lobby - 01">Lobby - 01</option>
                    <option value="Lobby - 02">Lobby - 02</option>
                    <option value="Lobby - 03">Lobby - 03</option>
                </optgroup>

                <optgroup label="Flooring Drawings">
                    <option value="Flooring Layout Plan Basement">Flooring Layout Plan Basement</option>
                    <option value="Flooring Layout Plan Stilt">Flooring Layout Plan Stilt</option>
                    <option value="Flooring Layout Plan Ground Floor">Flooring Layout Plan Ground Floor</option>
                    <option value="Flooring Layout Plan First Floor">Flooring Layout Plan First Floor</option>
                    <option value="Flooring Layout Plan Second Floor">Flooring Layout Plan Second Floor</option>
                    <option value="Flooring Layout Plan Third Floor">Flooring Layout Plan Third Floor</option>
                    <option value="Flooring Layout Plan Fourth Floor">Flooring Layout Plan Fourth Floor</option>
                    <option value="Flooring Layout Plan Fifth Floor">Flooring Layout Plan Fifth Floor</option>

                </optgroup>

                <optgroup label="False Ceiling Drawings">
                    <option value="False Ceiling Layout Plan Basement">False Ceiling Layout Plan Basement</option>
                    <option value="False Ceiling Layout Plan Stilt">False Ceiling Layout Plan Stilt</option>
                    <option value="False Ceiling Layout Plan Ground Floor">False Ceiling Layout Plan Ground Floor</option>
                    <option value="False Ceiling Layout Plan First Floor">False Ceiling Layout Plan First Floor</option>
                    <option value="False Ceiling Layout Plan Second Floor">False Ceiling Layout Plan Second Floor</option>
                    <option value="False Ceiling Layout Plan Third Floor">False Ceiling Layout Plan Third Floor</option>
                    <option value="False Ceiling Layout Plan Fourth Floor">False Ceiling Layout Plan Fourth Floor</option>
                    <option value="False Ceiling Layout Plan Fifth Floor">False Ceiling Layout Plan Fifth Floor</option>
                    <option value="Master Bed Room False Ceiling Layout Plan & Section Details">Master Bed Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Daughter's Bed Room False Ceiling Layout Plan & Section Details">Daughter's Bed Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Son's Bed Room False Ceiling Layout Plan & Section Details">Son's Bed Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Guest Bed Room False Ceiling Layout Plan & Section Details">Guest Bed Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Toilet - 01 False Ceiling Layout Plan & Section Details">Toilet - 01 False Ceiling Layout Plan & Section Details</option>
                    <option value="Toilet - 02 False Ceiling Layout Plan & Section Details">Toilet - 02 False Ceiling Layout Plan & Section Details</option>
                    <option value="Toilet - 03 False Ceiling Layout Plan & Section Details">Toilet - 03 False Ceiling Layout Plan & Section Details</option>
                    <option value="Toilet - 04 False Ceiling Layout Plan & Section Details">Toilet - 04 False Ceiling Layout Plan & Section Details</option>
                    <option value="Toilet - 05 False Ceiling Layout Plan & Section Details">Toilet - 05 False Ceiling Layout Plan & Section Details</option>
                    <option value="Prayer Room False Ceiling Layout Plan & Section Details">Prayer Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Study Room False Ceiling Layout Plan & Section Details">Study Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Home Theater False Ceiling Layout Plan & Section Details">Home Theater False Ceiling Layout Plan & Section Details</option>
                    <option value="GYM / Multi-Purpose Room False Ceiling Layout Plan & Section Details">GYM / Multi-Purpose Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Servant Room False Ceiling Layout Plan & Section Details">Servant Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Family Lounge False Ceiling Layout Plan & Section Details">Family Lounge False Ceiling Layout Plan & Section Details</option>
                    <option value="Staircase False Ceiling Layout Plan & Section Details">Staircase False Ceiling Layout Plan & Section Details</option>
                    <option value="Landscape Area False Ceiling Layout Plan & Section Details">Landscape Area False Ceiling Layout Plan & Section Details</option>
                    <option value="Recreational Area False Ceiling Layout Plan & Section Details">Recreational Area False Ceiling Layout Plan & Section Details</option>
                    <option value="Office Space False Ceiling Layout Plan & Section Details">Office Space False Ceiling Layout Plan & Section Details</option>
                    <option value="Conference Room False Ceiling Layout Plan & Section Details">Conference Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Waiting Area False Ceiling Layout Plan & Section Details">Waiting Area False Ceiling Layout Plan & Section Details</option>
                    <option value="Reception Area False Ceiling Layout Plan & Section Details">Reception Area False Ceiling Layout Plan & Section Details</option>
                    <option value="Manager's Cabin False Ceiling Layout Plan & Section Details">Manager's Cabin False Ceiling Layout Plan & Section Details</option>
                    <option value="Work Station Area 1 False Ceiling Layout Plan & Section Details">Work Station Area 1 False Ceiling Layout Plan & Section Details</option>
                    <option value="Work Station Area 2 False Ceiling Layout Plan & Section Details">Work Station Area 2 False Ceiling Layout Plan & Section Details</option>
                    <option value="Work Station Area 3 False Ceiling Layout Plan & Section Details">Work Station Area 3 False Ceiling Layout Plan & Section Details</option>
                    <option value="Meeting Room False Ceiling Layout Plan & Section Details">Meeting Room False Ceiling Layout Plan & Section Details</option>
                    <option value="Kitchen False Ceiling Layout Plan & Section Details">Kitchen False Ceiling Layout Plan & Section Details</option>
                    <option value="Utility Area False Ceiling Layout Plan & Section Details">Utility Area False Ceiling Layout Plan & Section Details</option>   
                </optgroup>

                <optgroup label="Ceiling Drawings">
                    <option value="Ceiling Layout Plan Basement">Ceiling Layout Plan Basement</option>
                    <option value="Ceiling Layout Plan Stilt">Ceiling Layout Plan Stilt</option>
                    <option value="Ceiling Layout Plan Ground Floor">Ceiling Layout Plan Ground Floor</option>
                    <option value="Ceiling Layout Plan First Floor">Ceiling Layout Plan First Floor</option>
                    <option value="Ceiling Layout Plan Second Floor">Ceiling Layout Plan Second Floor</option>
                    <option value="Ceiling Layout Plan Third Floor">Ceiling Layout Plan Third Floor</option>
                    <option value="Ceiling Layout Plan Fourth Floor">Ceiling Layout Plan Fourth Floor</option>
                    <option value="Ceiling Layout Plan Fifth Floor">Ceiling Layout Plan Fifth Floor</option>
                </optgroup>

                <optgroup label="Electrical Drawings">
                    <option value="Electrical Layout Plan Basement">Electrical Layout Plan Basement</option>
                    <option value="Electrical Layout Plan Stilt">Electrical Layout Plan Stilt</option>
                    <option value="Electrical Layout Plan Ground Floor">Electrical Layout Plan Ground Floor</option>
                    <option value="Electrical Layout Plan First Floor">Electrical Layout Plan First Floor</option>
                    <option value="Electrical Layout Plan Second Floor">Electrical Layout Plan Second Floor</option>
                    <option value="Electrical Layout Plan Third Floor">Electrical Layout Plan Third Floor</option>
                    <option value="Electrical Layout Plan Fourth Floor">Electrical Layout Plan Fourth Floor</option>
                    <option value="Electrical Layout Plan Fifth Floor">Electrical Layout Plan Fifth Floor</option>
                </optgroup>

                <optgroup label="Plumbing Drawings">
                    <option value="Plumbing Layout Plan Basement">Plumbing Layout Plan Basement</option>
                    <option value="Plumbing Layout Plan Stilt">Plumbing Layout Plan Stilt</option>
                    <option value="Plumbing Layout Plan Ground Floor">Plumbing Layout Plan Ground Floor</option>
                    <option value="Plumbing Layout Plan First Floor">Plumbing Layout Plan First Floor</option>
                    <option value="Plumbing Layout Plan Second Floor">Plumbing Layout Plan Second Floor</option>
                    <option value="Plumbing Layout Plan Third Floor">Plumbing Layout Plan Third Floor</option>
                    <option value="Plumbing Layout Plan Fourth Floor">Plumbing Layout Plan Fourth Floor</option>
                </optgroup>

                <optgroup label="Water Supply Drawings">
                    <option value="Water Supply Layout Plan Basement">Water Supply Layout Plan Basement</option>
                    <option value="Water Supply Layout Plan Stilt">Water Supply Layout Plan Stilt</option>
                    <option value="Water Supply Layout Plan Ground Floor">Water Supply Layout Plan Ground Floor</option>
                    <option value="Water Supply Layout Plan First Floor">Water Supply Layout Plan First Floor</option>
                    <option value="Water Supply Layout Plan Second Floor">Water Supply Layout Plan Second Floor</option>
                    <option value="Water Supply Layout Plan Third Floor">Water Supply Layout Plan Third Floor</option>
                    <option value="Water Supply Layout Plan Fourth Floor">Water Supply Layout Plan Fourth Floor</option>
                    <option value="Water Supply Layout Plan Fifth Floor">Water Supply Layout Plan Fifth Floor</option>
                </optgroup>

                <optgroup label="Interior Drawings">
                    <option value="Master Bed Room Wall Panelling Details">Master Bed Room Wall Panelling Details</option>
            `;
        }

        return `
            <div class="substage-block" data-substage="${substageNumber}">
                <div class="substage-header">
                    <h5 class="substage-title">Task ${String(substageNumber).padStart(2, '0')}</h5>
                    <button class="remove-substage-btn" onclick="removeSubstage(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="task-form-group">
                    <label>Substage Title</label>
                    <select class="substage-name" required>
                        <option value="">Select Title</option>
                        ${substageOptions}
                    </select>
                </div>
                <div class="task-form-group">
                    <label>Assign To</label>
                    <select class="substage-assignee" value="${stageAssignee}">
                        <option value="">Select Employee</option>
                    </select>
                </div>
                <div class="task-form-row">
                    <div class="task-form-group">
                        <label>Start Date & Time</label>
                        <div class="task-datetime-input">
                            <input type="datetime-local" class="substage-start-date">
                        </div>
                    </div>
                    <div class="task-form-group">
                        <label>Due By</label>
                        <div class="task-datetime-input">
                            <input type="datetime-local" class="substage-due-date">
                        </div>
                    </div>
                </div>
                <div class="file-upload-container">
                    <input type="file" id="substageFile${stageNumber}_${substageNumber}" class="file-upload-input">
                    <label for="substageFile${stageNumber}_${substageNumber}" class="file-upload-label">
                        <i class="fas fa-paperclip"></i>
                        <span>Attach File</span>
                    </label>
                    <div class="selected-file"></div>
                </div>
            </div>
        `;
    }

    // Function to add a new stage
    function addStage() {
        stageCount++;
        const stagesWrapper = document.getElementById('stagesWrapper');
        const newStage = createStageHTML(stageCount);
        stagesWrapper.insertAdjacentHTML('beforeend', newStage);

        // Get the newly added stage element
        const stageElement = stagesWrapper.lastElementChild;
        
        // Populate the assignee dropdown for the new stage
        populateStageAssignee(stageElement);

        // Add file change listener
        const fileInput = document.getElementById(`stageFile${stageCount}`);
        if (fileInput) {
            fileInput.addEventListener('change', handleFileSelect);
        }
    }

    // Function to remove a stage
    window.removeStage = function(button) {
        const stageBlock = button.closest('.stage-block');
        stageBlock.remove();
    };

    // Function to add a substage
    window.addSubstage = function(stageNumber) {
        const substagesWrapper = document.getElementById(`substagesWrapper${stageNumber}`);
        const stageBlock = substagesWrapper.closest('.stage-block');
        
        // Get stage dates
        const stageStartDate = stageBlock.querySelector('.stage-start-date').value;
        const stageDueDate = stageBlock.querySelector('.stage-due-date').value;
        
        // Increment substage counter
        substageCounters[stageNumber] = (substageCounters[stageNumber] || 0) + 1;
        const substageNumber = substageCounters[stageNumber];
        
        // Calculate substage dates
        const substageDates = calculateSubstageDates(stageStartDate, stageDueDate, substageNumber);
        const currentSubstageDates = substageDates[substageNumber - 1];
        
        const newSubstage = createSubstageHTML(stageNumber, substageNumber, currentSubstageDates);
        substagesWrapper.insertAdjacentHTML('beforeend', newSubstage);
        
        // Get the newly added substage element
        const lastSubstage = substagesWrapper.lastElementChild;
        
        // Set the calculated dates
        const startDateInput = lastSubstage.querySelector('.substage-start-date');
        const dueDateInput = lastSubstage.querySelector('.substage-due-date');
        
        if (startDateInput && dueDateInput) {
            startDateInput.value = currentSubstageDates.startDate;
            dueDateInput.value = currentSubstageDates.endDate;
        }

        // Populate the assignee dropdown for the new substage
        const substageAssigneeSelect = lastSubstage.querySelector('.substage-assignee');
        if (substageAssigneeSelect && window.usersData) {
            populateUserDropdown(substageAssigneeSelect, window.usersData);
        }

        // Add file input event listener
        const fileInput = document.getElementById(`substageFile${stageNumber}_${substageNumber}`);
        if (fileInput) {
            fileInput.addEventListener('change', handleFileSelect);
        }
    };

    // Function to remove a substage
    window.removeSubstage = function(button) {
        const substageBlock = button.closest('.substage-block');
        substageBlock.remove();
    };

    // Initialize Add Stage button
    const addStageBtn = document.getElementById('addStageBtn');
    if (addStageBtn) {
        addStageBtn.addEventListener('click', addStage);
    }

    // Open dialog
    if (addTaskBtn) {
        addTaskBtn.addEventListener('click', () => {
            console.log('Add Task button clicked');
            taskDialog.style.display = 'flex';
            setTimeout(() => taskDialog.classList.add('active'), 50);
        });
    }

    // Close dialog functions
    function closeDialog() {
        taskDialog.classList.remove('active');
        setTimeout(() => {
            taskDialog.style.display = 'none';
            // Reset form
            document.getElementById('taskTitle').value = '';
            document.getElementById('taskDescription').value = '';
            document.getElementById('taskStartDate').value = '';
            document.getElementById('taskDueDate').value = '';
            document.getElementById('taskAssignee').value = '';
            // Clear stages
            document.getElementById('stagesWrapper').innerHTML = '';
            stageCount = 0;
        }, 300);
    }

    // Close on X button
    if (closeTaskDialog) {
        closeTaskDialog.addEventListener('click', closeDialog);
    }

    // Close on Cancel button
    if (cancelTaskBtn) {
        cancelTaskBtn.addEventListener('click', closeDialog);
    }

    // Close on clicking outside
    if (taskDialog) {
        taskDialog.addEventListener('click', (e) => {
            if (e.target === taskDialog) {
                closeDialog();
            }
        });
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && taskDialog && taskDialog.style.display === 'flex') {
            closeDialog();
        }
    });

    // Handle form submission
    if (saveTaskBtn) {
        saveTaskBtn.addEventListener('click', async () => {
            try {
                const formData = collectFormData();
                validateFormData(formData);

                // Show loading state
                saveTaskBtn.disabled = true;
                saveTaskBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Project...';

                // Send data to backend
                const response = await fetch('dashboard/handlers/save_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Handle file uploads after project is created
                    const fileUploads = [];
                    const fileInputs = document.querySelectorAll('.file-upload-input');
                    
                    for (const input of fileInputs) {
                        if (input.files.length > 0) {
                            const file = input.files[0];
                            const stageId = input.closest('.stage-block')?.dataset?.stage || null;
                            const substageId = input.closest('.substage-block')?.dataset?.substage || null;
                            
                            fileUploads.push(uploadFile(file, result.project_id, stageId, substageId));
                        }
                    }

                    await Promise.all(fileUploads);

                    // Show success message
                    showNotification('Project created successfully!', 'success');
                    closeDialog();
                } else {
                    throw new Error(result.message || 'Failed to create project');
                }

            } catch (error) {
                console.error('Error:', error);
                showNotification(error.message, 'error');
            } finally {
                // Reset button state
                saveTaskBtn.disabled = false;
                saveTaskBtn.innerHTML = '<span>Create Project</span><i class="fas fa-check"></i>';
            }
        });
    }

    // Add this inside your DOMContentLoaded event listener
    const projectTypeSelect = document.getElementById('projectType');
    const dialogContent = document.querySelector('.task-dialog-content');

    if (projectTypeSelect) {
        projectTypeSelect.addEventListener('change', function() {
            // Remove all existing theme classes
            dialogContent.classList.remove(
                'theme-architecture',
                'theme-interior',
                'theme-construction'
            );
            
            // Add new theme class based on selection
            if (this.value) {
                dialogContent.classList.add(`theme-${this.value}`);
                
                // Smooth transition for color changes
                dialogContent.style.transition = 'all 0.3s ease';
            }
        });
    }
});

// Update the fetchUsers function with the correct path
function fetchUsers() {
    console.log('Fetching users...'); // Debug log
    
    fetch('dashboard/handlers/fetch_users.php')
        .then(async response => {
            const text = await response.text(); // Get raw response text
            console.log('Raw response:', text); // Log the raw response
            
            try {
                return JSON.parse(text); // Try to parse it as JSON
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.log('Invalid JSON received:', text);
                throw new Error('Invalid JSON response');
            }
        })
        .then(data => {
            console.log('Parsed data:', data);
            
            if (data.status === 'success' && data.users && data.users.length > 0) {
                window.usersData = data.users;
                
                const assigneeDropdowns = document.querySelectorAll('select.stage-assignee, select.substage-assignee, #taskAssignee');
                assigneeDropdowns.forEach(dropdown => {
                    populateUserDropdown(dropdown, data.users);
                });
            } else {
                console.error('Error or no users found:', data);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            console.error('Error details:', error.message);
        });
}

// Update the populateUserDropdown function to work with direct element
function populateUserDropdown(selectElement, users) {
    if (!selectElement) {
        console.error('Select element not found');
        return;
    }

    console.log('Populating dropdown:', selectElement.id);
    console.log('Users data:', users);

    // Clear existing options
    selectElement.innerHTML = '<option value="">Select Employee</option>';

    // Add users to dropdown
    users.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.username + (user.designation ? ` - ${user.designation}` : '');
        selectElement.appendChild(option);
    });
}

// Function to populate stage assignee dropdown
function populateStageAssignee(stageElement) {
    const select = stageElement.querySelector('.stage-assignee');
    if (select && window.usersData) {
        populateUserDropdown(select, window.usersData);
    }
}

// Function to populate substage assignee dropdown
function populateSubstageAssignee(substageElement) {
    const select = substageElement.querySelector('.substage-assignee');
    if (select && window.usersData) {
        populateUserDropdown(select, window.usersData);
    }
}

// Make sure the DOM is loaded before running scripts
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...'); // Debug log
    
    // Initialize all dropdowns
    fetchUsers();
    
    // Add event listener to any "Add Stage" button
    const addStageBtn = document.getElementById('addStageBtn');
    if (addStageBtn) {
        addStageBtn.addEventListener('click', function() {
            console.log('Add Stage button clicked'); // Debug log
            addStage();
        });
    }
});

// Add file handling function
function handleFileSelect(event) {
    const fileInput = event.target;
    const fileLabel = fileInput.nextElementSibling;
    const selectedFileDiv = fileLabel.nextElementSibling;
    
    if (fileInput.files && fileInput.files[0]) {
        const fileName = fileInput.files[0].name;
        selectedFileDiv.textContent = fileName;
        selectedFileDiv.style.display = 'block';
    } else {
        selectedFileDiv.textContent = '';
        selectedFileDiv.style.display = 'none';
    }
}

// Function to handle file uploads
async function uploadFile(file, projectId, stageId = null, substageId = null) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('project_id', projectId);
    if (stageId) formData.append('stage_id', stageId);
    if (substageId) formData.append('substage_id', substageId);

    // Debug log
    console.log('Uploading file with:', {
        substageId: substageId,
        fileName: fileNameInput.value,
        columnType: columnType,
        file: fileInput.files[0]
    });
    
    try {
        const response = await fetch('api/upload_substage_file.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update the table with the new file
            const table = document.querySelector(`#files-table-${dialog.dataset.substageId} tbody`);
            const newRow = document.createElement('tr');
            newRow.setAttribute('data-file-id', result.file.id);
            const fileCount = table.children.length + 1;
            
            newRow.innerHTML = `
                <td>${fileCount}</td>
                <td>${result.file.name}</td>
                <td>${result.file.type}</td>
                <td>Pending</td>
                <td class="actions-cell">
                    <div class="file-actions">
                        <button onclick="viewFile('${result.file.path}')" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="downloadFile('${result.file.path}')" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button onclick="sendFile('${result.file.path}')" title="Send">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        <button onclick="approveFile('${result.file.path}')" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button onclick="rejectFile('${result.file.path}')" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </td>
            `;
            
            table.appendChild(newRow);
            
            // Close the dialog
            dialog.style.display = 'none';
            fileNameInput.value = '';
            fileInput.value = '';
            
            // Show success message
            alert('File uploaded successfully');
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error uploading file: ' + error.message);
    }
}

// Function to collect form data
function collectFormData() {
    // First validate that all required elements exist
    const requiredElements = {
        taskTitle: document.getElementById('taskTitle'),
        taskDescription: document.getElementById('taskDescription'),
        projectType: document.getElementById('projectType'),
        taskCategory: document.getElementById('taskCategory'),
        taskStartDate: document.getElementById('taskStartDate'),
        taskDueDate: document.getElementById('taskDueDate'),
        taskAssignee: document.getElementById('taskAssignee')
    };

    // Check if any required element is missing
    for (const [key, element] of Object.entries(requiredElements)) {
        if (!element) {
            throw new Error(`Required element ${key} not found in the form`);
        }
    }

    const formData = {
        title: requiredElements.taskTitle.value.trim(),
        description: requiredElements.taskDescription.value.trim(),
        projectType: requiredElements.projectType.value,
        category: requiredElements.taskCategory.value,
        startDate: requiredElements.taskStartDate.value,
        dueDate: requiredElements.taskDueDate.value,
        assignee: requiredElements.taskAssignee.value,
        stages: []
    };

    // Validate main project fields
    if (!formData.title || !formData.projectType || !formData.category || 
        !formData.startDate || !formData.dueDate || !formData.assignee) {
        throw new Error('Please fill in all required project fields');
    }

    // Collect stages data
    const stageBlocks = document.querySelectorAll('.stage-block:not([style*="display: none"])');
    
    stageBlocks.forEach((stageBlock, stageIndex) => {
        const stageElements = {
            assignee: stageBlock.querySelector('.stage-assignee'),
            startDate: stageBlock.querySelector('.stage-start-date'),
            endDate: stageBlock.querySelector('.stage-due-date') || stageBlock.querySelector('.stage-end-date'),
            description: stageBlock.querySelector('.stage-description')
        };

        // Check required fields (excluding description)
        const requiredFields = {
            assignee: stageElements.assignee?.value || '',
            startDate: stageElements.startDate?.value || '',
            endDate: stageElements.endDate?.value || ''
        };

        // Count how many required fields are filled
        const filledRequiredFieldsCount = Object.values(requiredFields)
            .filter(value => value !== '').length;

        // If no required fields are filled, skip this stage
        if (filledRequiredFieldsCount === 0) {
            return;
        }

        // If some but not all required fields are filled, show which ones are missing
        if (filledRequiredFieldsCount < Object.keys(requiredFields).length) {
            const missingFields = [];
            if (!requiredFields.assignee) missingFields.push('assignee');
            if (!requiredFields.startDate) missingFields.push('start date');
            if (!requiredFields.endDate) missingFields.push('due date');

            throw new Error(
                `Stage ${stageIndex + 1} is incomplete. Missing required fields: ${missingFields.join(', ')}`
            );
        }

        const stage = {
            description: stageElements.description?.value?.trim() || '', // Description is optional
            assignee: requiredFields.assignee,
            startDate: requiredFields.startDate,
            dueDate: requiredFields.endDate,
            substages: []
        };

        // Collect substages data if they exist
        const substageBlocks = stageBlock.querySelectorAll('.substage-block:not([style*="display: none"])');
        substageBlocks.forEach((substageBlock, substageIndex) => {
            const substageElements = {
                title: substageBlock.querySelector('.substage-name'),
                assignee: substageBlock.querySelector('.substage-assignee'),
                startDate: substageBlock.querySelector('.substage-start-date'),
                endDate: substageBlock.querySelector('.substage-due-date') || substageBlock.querySelector('.substage-end-date')
            };

            // Check which substage fields have data
            const filledSubstageFields = {
                title: substageElements.title?.value?.trim() || '',
                assignee: substageElements.assignee?.value || '',
                startDate: substageElements.startDate?.value || '',
                endDate: substageElements.endDate?.value || ''
            };

            // Count filled substage fields
            const filledSubstageFieldsCount = Object.values(filledSubstageFields)
                .filter(value => value !== '').length;

            // If no substage fields are filled, skip this substage
            if (filledSubstageFieldsCount === 0) {
                return;
            }

            // If some but not all substage fields are filled, show which ones are missing
            if (filledSubstageFieldsCount < Object.keys(filledSubstageFields).length) {
                const missingSubstageFields = [];
                if (!filledSubstageFields.title) missingSubstageFields.push('title');
                if (!filledSubstageFields.assignee) missingSubstageFields.push('assignee');
                if (!filledSubstageFields.startDate) missingSubstageFields.push('start date');
                if (!filledSubstageFields.endDate) missingSubstageFields.push('due date');

                throw new Error(
                    `Substage ${substageIndex + 1} in Stage ${stageIndex + 1} is incomplete.\n` +
                    `Missing: ${missingSubstageFields.join(', ')}`
                );
            }

            const substage = {
                title: filledSubstageFields.title,
                assignee: filledSubstageFields.assignee,
                startDate: filledSubstageFields.startDate,
                dueDate: filledSubstageFields.endDate
            };
            stage.substages.push(substage);
        });

        formData.stages.push(stage);
    });

    // Log the final form data for debugging
    console.log('Form Data:', formData);

    return formData;
}

// Function to validate form data
function validateFormData(formData) {
    if (!formData.title || !formData.projectType || !formData.category || 
        !formData.startDate || !formData.dueDate || !formData.assignee) {
        throw new Error('Please fill in all required fields');
    }

    // Validate stages
    if (formData.stages.length === 0) {
        throw new Error('Please add at least one stage');
    }

    formData.stages.forEach((stage, index) => {
        if (!stage.assignee || !stage.startDate || !stage.dueDate) {
            throw new Error(
                `Please fill in all required fields for Stage ${index + 1}`
            );
        }

        // Validate substages
        stage.substages.forEach((substage, subIndex) => {
            if (!substage.title || !substage.assignee || 
                !substage.startDate || !substage.dueDate) {
                throw new Error(
                    `Please fill in all required fields for Substage ${subIndex + 1} in Stage ${index + 1}`
                );
            }
        });
    });

    return true;
}

// Add these functions to handle file upload dialog
function showFileUploadDialog(substageId, columnType) {
    const dialog = document.getElementById('fileUploadDialog');
    const closeBtn = dialog.querySelector('.task-close-dialog');
    const cancelBtn = dialog.querySelector('.task-cancel-btn');
    const uploadBtn = document.getElementById('uploadFileBtn');
    
    // Store substage ID and column type for later use
    dialog.dataset.substageId = substageId;
    dialog.dataset.columnType = columnType;
    
    // Show dialog
    dialog.classList.add('active');
    
    // Handle close button
    closeBtn.onclick = () => {
        dialog.classList.remove('active');
    };
    
    // Handle cancel button
    cancelBtn.onclick = () => {
        dialog.classList.remove('active');
    };
    
    // Handle upload button
    uploadBtn.onclick = async () => {
        const fileNameInput = document.getElementById('fileName');
        const fileInput = document.getElementById('fileUpload');
        const dialog = document.getElementById('fileUploadDialog');
        
        if (!fileNameInput.value || !fileInput.files[0]) {
            alert('Please enter a file name and select a file');
            return;
        }
        
        const formData = new FormData();
        formData.append('fileName', fileNameInput.value);
        formData.append('file', fileInput.files[0]);
        formData.append('substageId', dialog.dataset.substageId);
        formData.append('columnType', dialog.dataset.columnType);
        
        try {
            const response = await fetch('api/upload_substage_file.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update the table with the new file
                const table = document.querySelector(`#files-table-${dialog.dataset.substageId} tbody`);
                const newRow = document.createElement('tr');
                newRow.setAttribute('data-file-id', result.file.id);
                const fileCount = table.children.length + 1;
                
                newRow.innerHTML = `
                    <td>${fileCount}</td>
                    <td>${result.file.name}</td>
                    <td>${result.file.type}</td>
                    <td>${result.file.status}</td>
                    <td class="actions-cell">
                        <div class="file-actions">
                            <button onclick="viewFile('${result.file.path}')" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="downloadFile('${result.file.path}')" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button onclick="sendFile('${result.file.path}')" title="Send">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            <button onclick="approveFile('${result.file.path}')" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="rejectFile('${result.file.path}')" title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                `;
                
                table.appendChild(newRow);
                
                // Close the dialog
                dialog.style.display = 'none';
                fileNameInput.value = '';
                fileInput.value = '';
                
                // Show success message
                alert('File uploaded successfully');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error uploading file: ' + error.message);
        }
    };
}

// Modify the existing table cell click handler
document.addEventListener('click', function(e) {
    const cell = e.target.closest('td');
    if (cell && !cell.classList.contains('actions-cell')) {
        const row = cell.closest('tr');
        const substageTable = row.closest('.substage-files-table');
        const substageContainer = substageTable.closest('.substage-item');
        const toggleFiles = substageContainer.querySelector('.toggle-files');
        const substageId = toggleFiles.dataset.substageId;
        
        const columnIndex = Array.from(row.children).indexOf(cell);
        
        // Map column index to type
        const columnTypes = ['sno', 'filename', 'uploads', 'status'];
        const columnType = columnTypes[columnIndex];
        
        if (columnType && columnType !== 'sno') {
            console.log('Opening upload dialog with:', {
                substageId: substageId,
                columnType: columnType
            });
            showFileUploadDialog(substageId, columnType);
        }
    }
});

// Function to refresh substage files table
async function refreshSubstageFiles(substageId) {
    try {
        const response = await fetch(`api/get_substage_files.php?substageId=${substageId}`);
        const result = await response.json();
        
        if (result.success) {
            // Update the table with new data
            const table = document.querySelector(`tr[data-substage-id="${substageId}"]`)
                .closest('table');
            // Update table content with result.files data
            // Implementation depends on your table structure
        }
    } catch (error) {
        console.error('Error refreshing files:', error);
    }
}

function viewFile(path) {
    window.open(path, '_blank');
}

function downloadFile(path) {
    const link = document.createElement('a');
    link.href = path;
    link.download = path.split('/').pop();
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function sendFile(path) {
    // Implement send file functionality
    alert('Send file functionality coming soon');
}

// Update the approveFile function
async function approveFile(event, fileId) {
    let row;
    try {
        event.preventDefault();
        
        const button = event.target.closest('.approve-btn');
        if (!button) {
            throw new Error('Button not found');
        }

        row = button.closest('tr');
        if (!row) {
            throw new Error('Row not found');
        }

        // Fetch current status from database
        const statusResponse = await fetch(`dashboard/handlers/get_file_status.php?file_id=${fileId}`);
        
        // Debug: Log the raw response text
        const rawResponse = await statusResponse.text();
        console.log('Raw response:', rawResponse);

        // Try parsing the response
        let statusResult;
        try {
            statusResult = JSON.parse(rawResponse);
        } catch (e) {
            console.error('JSON parse error:', e);
            throw new Error('Invalid response from server');
        }

        if (!statusResult.success) {
            throw new Error(statusResult.message || 'Could not verify file status');
        }

        const currentStatus = statusResult.file?.status?.toLowerCase().trim();
        const statusCell = row.querySelector('.file-status');

        if (currentStatus !== 'sent_for_approval') {
            throw new Error('Only files sent for approval can be processed');
        }

        // Disable buttons during processing
        const actionButtons = row.querySelectorAll('.approve-btn, .reject-btn');
        actionButtons.forEach(btn => btn.disabled = true);

        const response = await fetch('dashboard/handlers/update_file_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                file_id: fileId,
                action: 'approve'
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        // Update UI
        if (statusCell) {
            statusCell.textContent = 'Approved';
        }
        
        row.className = 'file-status-approved';
        
        // Keep buttons disabled
        actionButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        });

        showNotification('File approved successfully', 'success');

    } catch (error) {
        console.error('Error:', error);
        showNotification(error.message, 'error');
        
        // Re-enable buttons on error if row exists
        if (row) {
            const actionButtons = row.querySelectorAll('.approve-btn, .reject-btn');
            actionButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        }
    }
}

// Update the rejectFile function
async function rejectFile(event, fileId) {
    console.log('Reject function called:', { event, fileId }); // Debug log
    
    let row;
    try {
        event.preventDefault();
        
        const button = event.target.closest('.reject-btn');
        console.log('Found button:', button); // Debug log
        
        if (!button) {
            throw new Error('Button not found');
        }

        row = button.closest('tr');
        console.log('Found row:', row); // Debug log
        
        if (!row) {
            throw new Error('Row not found');
        }

        // Disable buttons during processing
        const actionButtons = row.querySelectorAll('.approve-btn, .reject-btn');
        actionButtons.forEach(btn => btn.disabled = true);

        // Send rejection request
        const response = await fetch('dashboard/handlers/update_file_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                file_id: fileId,
                action: 'reject'
            })
        });

        console.log('Response:', response); // Debug log

        const result = await response.json();
        console.log('Result:', result); // Debug log

        if (!result.success) {
            throw new Error(result.message || 'Failed to reject file');
        }

        // Update UI
        const statusCell = row.querySelector('.file-status');
        if (statusCell) {
            statusCell.textContent = 'Rejected';
        }
        
        // Update row class
        row.className = 'file-status-rejected';
        
        // Keep buttons disabled
        actionButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        });

        showNotification('File rejected successfully', 'success');

    } catch (error) {
        console.error('Error in rejectFile:', error);
        showNotification(error.message, 'error');
        
        // Re-enable buttons on error if row exists
        if (row) {
            const actionButtons = row.querySelectorAll('.approve-btn, .reject-btn');
            actionButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        }
    }
}

// Add notification function
function showNotification(message, type = 'success') {
    // Remove any existing notifications
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Update the loadSubstageFiles function
async function loadSubstageFiles(substageId) {
    try {
        console.log('Loading files for substageId:', substageId);
        
        const table = document.querySelector(`#files-table-${substageId}`);
        if (!table) {
            console.error('Table not found:', `#files-table-${substageId}`);
            return;
        }
        
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            console.error('Table body not found');
            return;
        }

        const response = await fetch(`api/get_substage_files.php?substageId=${substageId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('API response:', result);
        
        tbody.innerHTML = ''; // Clear existing rows
        
        if (!result.success) {
            console.error('API error:', result.message);
            return;
        }

        if (result.files.length === 0) {
            // Add empty rows
            for (let i = 1; i <= 5; i++) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${i}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="actions-cell">
                        <div class="file-actions">
                            <button disabled title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button disabled title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button disabled title="Send">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            <button disabled title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button disabled title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            }
        } else {
            result.files.forEach((file, index) => {
                const row = document.createElement('tr');
                // Convert status to lowercase and replace spaces with hyphens for CSS class
                const statusClass = file.status.toLowerCase().replace(/\s+/g, '-');
                row.className = `file-status-${statusClass}`;
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${file.name}</td>
                    <td>${file.type}</td>
                    <td>${file.status}</td>
                    <td class="actions-cell">
                        <div class="file-actions">
                            <button onclick="viewFile('${file.path}')" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="downloadFile('${file.path}')" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button onclick="sendFile('${file.path}')" title="Send">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            <button class="action-btn approve-btn" 
                                    onclick="approveFile(event, ${file.id})"
                                    title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="action-btn reject-btn" 
                                    onclick="rejectFile(event, ${file.id})"
                                    title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Make sure table is visible
        table.style.display = 'table';
        
    } catch (error) {
        console.error('Error loading files:', error);
    }
}

// Update the toggle files click handler
document.addEventListener('click', async function(e) {
    const toggleIcon = e.target.closest('.toggle-files i');
    const toggleFiles = e.target.closest('.toggle-files');
    
    if (toggleIcon || toggleFiles) {
        e.preventDefault();
        e.stopPropagation();
        
        const container = toggleFiles || toggleIcon.closest('.toggle-files');
        const substageId = container.dataset.substageId;
        console.log('1. Toggle clicked for substageId:', substageId);
        
        // Find the substage container
        const substageContainer = container.closest('.substage-item');
        
        // Get or create table
        let table = substageContainer.querySelector('.substage-files-table');
        
        if (!table) {
            const tableHtml = `
                <table class="substage-files-table" id="files-table-${substageId}" data-state="hidden" style="display: none;">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            `;
            substageContainer.insertAdjacentHTML('beforeend', tableHtml);
            table = substageContainer.querySelector('.substage-files-table');
        }
        
        const icon = container.querySelector('i');
        const currentState = table.dataset.state || 'hidden';
        
        if (currentState === 'hidden') {
            try {
                // Show table first
                table.style.display = 'table';
                table.dataset.state = 'visible';
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                
                // Then fetch data
                const response = await fetch(`api/get_substage_files.php?substageId=${substageId}`);
                const result = await response.json();
                
                const tbody = table.querySelector('tbody');
                tbody.innerHTML = ''; // Clear existing rows
                
                // Add existing files
                if (result.files && result.files.length > 0) {
                    result.files.forEach((file, index) => {
                        const row = document.createElement('tr');
                        row.className = 'file-row';
                        row.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${file.name}</td>
                            <td>${file.type}</td>
                            <td>${file.status}</td>
                            <td class="actions-cell">
                                <div class="file-actions">
                                    <button onclick="viewFile('${file.path}')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="downloadFile('${file.path}')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button onclick="sendFile('${file.path}')">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <button class="action-btn approve-btn" 
                                            onclick="approveFile(event, ${file.id})"
                                            title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="action-btn reject-btn" 
                                            onclick="rejectFile(event, ${file.id})"
                                            title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
                
                // Add one empty row at the end
                const emptyRow = document.createElement('tr');
                emptyRow.className = 'empty-row';
                emptyRow.innerHTML = `
                    <td>${(result.files?.length || 0) + 1}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="actions-cell">
                        <div class="file-actions">
                            <button disabled><i class="fas fa-eye"></i></button>
                            <button disabled><i class="fas fa-download"></i></button>
                            <button disabled><i class="fas fa-paper-plane"></i></button>
                            <button disabled><i class="fas fa-check"></i></button>
                            <button disabled><i class="fas fa-times"></i></button>
                        </div>
                    </td>
                `;
                tbody.appendChild(emptyRow);
                
            } catch (error) {
                console.error('Error:', error);
                // Revert state if there's an error
                table.style.display = 'none';
                table.dataset.state = 'hidden';
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            }
        } else {
            // Hide table
            table.style.display = 'none';
            table.dataset.state = 'hidden';
            icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
        }
    }
});

// Function to handle new file upload
function handleNewFileUpload(substageId, fileData) {
    const table = document.querySelector(`#files-table-${substageId}`);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const emptyRow = tbody.querySelector('.empty-row');
    
    // Create new file row
    const newRow = document.createElement('tr');
    newRow.className = 'file-row';
    newRow.innerHTML = `
        <td>${parseInt(emptyRow.querySelector('td').textContent)}</td>
        <td>${fileData.name}</td>
        <td>${fileData.type}</td>
        <td>${fileData.status}</td>
        <td class="actions-cell">
            <div class="file-actions">
                <button onclick="viewFile('${fileData.path}')">
                    <i class="fas fa-eye"></i>
                </button>
                <button onclick="downloadFile('${fileData.path}')">
                    <i class="fas fa-download"></i>
                </button>
                <button onclick="sendFile('${fileData.path}')">
                    <i class="fas fa-paper-plane"></i>
                </button>
                <button class="action-btn approve-btn" 
                        onclick="approveFile(event, ${fileData.id})"
                        title="Approve">
                    <i class="fas fa-check"></i>
                </button>
                <button class="action-btn reject-btn" 
                        onclick="rejectFile(event, ${fileData.id})"
                        title="Reject">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </td>
    `;
    
    // Insert new row before empty row
    tbody.insertBefore(newRow, emptyRow);
    
    // Update empty row number
    emptyRow.querySelector('td').textContent = parseInt(emptyRow.querySelector('td').textContent) + 1;
}

// Update the table row generation in loadSubstageFiles function
function createFileRow(file, index) {
    const row = document.createElement('tr');
    row.setAttribute('data-file-id', file.id);
    row.className = `file-status-${file.status.toLowerCase()}`;
    
    row.innerHTML = `
        <td>${index + 1}</td>
        <td>${file.file_name}</td>
        <td>${file.type}</td>
        <td class="file-status">${capitalizeFirstLetter(file.status)}</td>
        <td class="actions-cell">
            <div class="file-actions">
                <button class="action-btn view-btn" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn download-btn" title="Download">
                    <i class="fas fa-download"></i>
                </button>
                <button class="action-btn send-btn" title="Send">
                    <i class="fas fa-paper-plane"></i>
                </button>
                <button class="action-btn approve-btn" 
                        onclick="approveFile(event, ${file.id})"
                        title="Approve">
                    <i class="fas fa-check"></i>
                </button>
                <button class="action-btn reject-btn" 
                        onclick="rejectFile(event, ${file.id})"
                        title="Reject">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </td>
    `;
    return row;
}

// Helper function to capitalize first letter
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
}

// Handle file actions (approve/reject)
async function handleFileAction(fileId, action) {
    try {
        const row = document.querySelector(`tr[data-file-id="${fileId}"]`);
        if (!row) {
            throw new Error('Row not found');
        }

        // Disable action buttons during processing
        const actionButtons = row.querySelectorAll('.approve-btn, .reject-btn');
        actionButtons.forEach(btn => btn.disabled = true);

        const response = await fetch('dashboard/handlers/update_file_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                file_id: fileId,
                action: action
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            // Update status cell
            const statusCell = row.querySelector('.file-status');
            const newStatus = action === 'reject' ? 'Rejected' : 'Approved';
            if (statusCell) {
                statusCell.textContent = newStatus;
            }
            
            // Update row class
            row.className = `file-status-${action}ed`;
            
            // Show success message
            showNotification(`File ${newStatus.toLowerCase()} successfully`, 'success');
            
            // Disable both buttons after successful action
            actionButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            });
        } else {
            throw new Error(result.message || `Failed to ${action} file`);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification(error.message, 'error');
        
        // Re-enable buttons on error
        const row = document.querySelector(`tr[data-file-id="${fileId}"]`);
        if (row) {
            const actionButtons = row.querySelectorAll('.approve-btn, .reject-btn');
            actionButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        }
    }
}

// Function to load files for a substage
function loadSubstageFiles(substageId) {
    const tableBody = document.querySelector(`#files-table-${substageId} tbody`);
    
    // Fetch files for this substage
    fetch(`../dashboard/handlers/get_substage_files.php?substage_id=${substageId}`)
        .then(response => response.json())
        .then(files => {
            tableBody.innerHTML = ''; // Clear existing rows
            
            if (files.length === 0) {
                // Show empty state
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="no-files">No files uploaded yet</td>
                    </tr>`;
                return;
            }

            // Create rows for each file
            files.forEach((file, index) => {
                const row = document.createElement('tr');
                row.setAttribute('data-file-id', file.id);
                row.className = `file-status-${file.status.toLowerCase()}`;
                
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${file.file_name}</td>
                    <td>${file.type}</td>
                    <td class="file-status">${capitalizeFirstLetter(file.status)}</td>
                    <td class="actions-cell">
                        <div class="file-actions">
                            <button class="action-btn view-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn download-btn" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="action-btn send-btn" 
                                    onclick="sendForApproval(event, ${file.id})"
                                    title="Send for Approval">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            <button class="action-btn approve-btn" 
                                    onclick="approveFile(event, ${file.id})"
                                    title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="action-btn reject-btn" 
                                    onclick="rejectFile(event, ${file.id})"
                                    title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                `;
                
                tableBody.appendChild(row);
            });
        })
        .catch(error => {
            console.error('Error loading files:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="error-message">Error loading files</td>
                </tr>`;
        });
}

// Helper function to capitalize first letter
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
}

// Add this to your existing toggle click handler
document.querySelectorAll('.toggle-files').forEach(toggle => {
    toggle.addEventListener('click', function() {
        const substageId = this.dataset.substageId;
        const table = document.getElementById(`files-table-${substageId}`);
        const icon = this.querySelector('i');
        
        // Toggle the active class on the button
        this.classList.toggle('active');
        
        // Toggle table visibility and load files if showing
        if (table.style.display === 'none' || !table.style.display) {
            table.style.display = 'table';
            loadSubstageFiles(substageId); // Load files when showing table
        } else {
            table.style.display = 'none';
        }
        
        // Rotate the icon
        if (this.classList.contains('active')) {
            icon.style.transform = 'rotate(180deg)';
        } else {
            icon.style.transform = 'rotate(0deg)';
        }
    });
});

// Function to send file for approval
async function sendForApproval(event, fileId) {
    let row;
    try {
        event.preventDefault();
        
        const button = event.target.closest('.send-btn');
        if (!button) {
            throw new Error('Button not found');
        }

        row = button.closest('tr');
        if (!row) {
            throw new Error('Row not found');
        }

        // Get current status
        const statusCell = row.querySelector('.file-status');
        const currentStatus = statusCell?.textContent?.toLowerCase().trim() || '';
        
        // Only allow 'pending' status files to be sent for approval
        if (currentStatus !== 'pending') {
            throw new Error('Only pending files can be sent for approval');
        }

        // Disable send button during processing
        button.disabled = true;

        const response = await fetch('dashboard/handlers/update_file_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                file_id: fileId,
                action: 'send_for_approval'
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        // Update UI
        if (statusCell) {
            statusCell.textContent = 'Sent for Approval';
        }
        
        row.className = 'file-status-sent';
        
        // Update button states
        const actionButtons = row.querySelectorAll('.action-btn');
        actionButtons.forEach(btn => {
            if (btn.classList.contains('send-btn')) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            }
            if (btn.classList.contains('approve-btn') || btn.classList.contains('reject-btn')) {
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        });

        showNotification('File sent for approval successfully', 'success');

    } catch (error) {
        console.error('Error:', error);
        showNotification(error.message, 'error');
        
        // Re-enable button on error
        if (row) {
            const sendButton = row.querySelector('.send-btn');
            if (sendButton) {
                sendButton.disabled = false;
            }
        }
    }
}

// Add this after your existing code
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to all reject buttons
    document.querySelectorAll('.reject-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            console.log('Reject button clicked');
            const fileId = this.closest('tr').dataset.fileId;
            if (fileId) {
                handleFileAction(fileId, 'reject');
            } else {
                console.error('No file ID found for this row');
            }
        });
    });
});

// Function to show project details when clicking on a calendar task
function showProjectDetails(projectId) {
    const modal = document.getElementById('projectDetailsModal');
    
    // Fetch project details from the server
    fetch(`api/get_project_details.php?project_id=${projectId}`)
        .then(response => response.json())
        .then(project => {
            // ... existing modal population code ...
            
            // Populate stages with status dropdown
            const stagesList = document.querySelector('.stages-list');
            stagesList.innerHTML = '';
            
            project.stages.forEach(stage => {
                const stageElement = document.createElement('div');
                stageElement.className = `stage-item status-${stage.status}`;
                stageElement.innerHTML = `
                    <h5>${stage.name}</h5>
                    <p>${stage.description}</p>
                    <div class="stage-meta">
                        <span><i class="fas fa-user"></i> ${stage.assignee}</span>
                        <span><i class="fas fa-calendar"></i> ${formatDate(stage.due_date)}</span>
                        <div class="stage-status-update">
                            <select class="status-dropdown" data-stage-id="${stage.id}">
                                <option value="pending" ${stage.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="in-progress" ${stage.status === 'in-progress' ? 'selected' : ''}>In Progress</option>
                                <option value="completed" ${stage.status === 'completed' ? 'selected' : ''}>Completed</option>
                                <option value="delayed" ${stage.status === 'delayed' ? 'selected' : ''}>Delayed</option>
                            </select>
                            <button class="update-status-btn" onclick="updateStageStatus(${stage.id}, this)">
                                <i class="fas fa-save"></i>
                            </button>
                        </div>
                    </div>
                `;
                stagesList.appendChild(stageElement);
            });
            
            // Add event listeners for status dropdowns
            document.querySelectorAll('.status-dropdown').forEach(dropdown => {
                dropdown.addEventListener('change', function() {
                    const updateBtn = this.nextElementSibling;
                    updateBtn.classList.add('active');
                });
            });
            
            modal.style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching project details:', error);
            showNotification('Error loading project details', 'error');
        });
}

// Function to update stage status
async function updateStageStatus(stageId, newStatus, dropdown) {
    try {
        // Store the previous value before making the request
        const previousValue = dropdown.value;
        
        const response = await fetch('api/update_stage_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                stage_id: stageId,
                status: newStatus
            })
        });

        const result = await response.json();

        if (result.success) {
            // Update the status badge next to the dropdown
            const statusBadge = dropdown.parentElement.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = `status-badge status-${newStatus}`;
                statusBadge.textContent = newStatus.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            }
            
            showNotification('Status updated successfully', 'success');
        } else {
            throw new Error(result.message || 'Failed to update status');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        // Revert the dropdown to its previous value
        dropdown.value = previousValue;
        showNotification('Failed to update status', 'error');
    }
}

// Update the dropdown HTML to pass 'this' instead of event
function generateStageHTML(stage) {
    return `
        <div class="stage-header">
            <h5>Stage ${stage.stage_number}</h5>
            <select class="stage-status-dropdown" onchange="updateStageStatus(${stage.id}, this.value, this)">
                <option value="not_started" ${stage.status === 'not_started' ? 'selected' : ''}>Not Started</option>
                <option value="pending" ${stage.status === 'pending' ? 'selected' : ''}>Pending</option>
                <option value="in_progress" ${stage.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                <option value="in_review" ${stage.status === 'in_review' ? 'selected' : ''}>In Review</option>
                <option value="completed" ${stage.status === 'completed' ? 'selected' : ''}>Completed</option>
                <option value="on_hold" ${stage.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
                <option value="cancelled" ${stage.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                <option value="blocked" ${stage.status === 'blocked' ? 'selected' : ''}>Blocked</option>
                <option value="freezed" ${stage.status === 'freezed' ? 'selected' : ''}>Freezed</option>
                <option value="sent_to_client" ${stage.status === 'sent_to_client' ? 'selected' : ''}>Sent to Client</option>
            </select>
            <span class="status-badge status-${stage.status}">
                ${stage.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
            </span>
        </div>
    `;
}

// Helper function for notifications
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            ${type === 'success' 
                ? '<i class="fas fa-check-circle"></i>' 
                : '<i class="fas fa-exclamation-circle"></i>'
            }
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove notification after delay
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add this to your existing event listeners
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('stage-status-dropdown')) {
        // Store the previous value before update
        e.target.setAttribute('data-previous-value', e.target.value);
    }
});

// Add this helper function for notifications if you haven't already
function showNotification(message, type = 'success') {
    // You can implement this based on your notification system
    console.log(`${type}: ${message}`);
}

// Initialize previous values when page loads
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.stage-status-dropdown');
    dropdowns.forEach(dropdown => {
        dropdown.setAttribute('data-previous-value', dropdown.value);
    });
});

// Add this new function
function setupCalendarNavigation() {
    const prevButton = document.querySelector('.task-calendar-nav.prev');
    const nextButton = document.querySelector('.task-calendar-nav.next');
    const titleElement = document.querySelector('.task-calendar-title');

    if (prevButton) {
        prevButton.onclick = async () => {
            try {
                // Get current date from title or fallback to current date
                let currentDate;
                if (titleElement && titleElement.textContent) {
                    currentDate = new Date(titleElement.textContent + " 1");
                } else {
                    currentDate = new Date();
                }
                
                // Calculate new date
                const newDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
                
                // Format and update title
                const monthYear = newDate.toLocaleDateString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                });
                if (titleElement) {
                    titleElement.textContent = monthYear;
                }
                
                console.log('Navigating to:', monthYear); // Debug log
                await generateTaskCalendar(newDate);
            } catch (error) {
                console.error('Error navigating to previous month:', error);
            }
        };
    }

    if (nextButton) {
        nextButton.onclick = async () => {
            try {
                // Get current date from title or fallback to current date
                let currentDate;
                if (titleElement && titleElement.textContent) {
                    currentDate = new Date(titleElement.textContent + " 1");
                } else {
                    currentDate = new Date();
                }
                
                // Calculate new date
                const newDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
                
                // Format and update title
                const monthYear = newDate.toLocaleDateString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                });
                if (titleElement) {
                    titleElement.textContent = monthYear;
                }
                
                console.log('Navigating to:', monthYear); // Debug log
                await generateTaskCalendar(newDate);
            } catch (error) {
                console.error('Error navigating to next month:', error);
            }
        };
    }
}

// Add this function to handle tooltips
function setupTooltips() {
    // Setup for all cards with tooltips
    document.querySelectorAll('.overview-card').forEach(card => {
        const tooltipId = card.getAttribute('data-tooltip-id');
        if (tooltipId) {
            const tooltip = document.getElementById(tooltipId);
            
            // Show tooltip on mouseenter
            card.addEventListener('mouseenter', () => {
                if (tooltip) {
                    tooltip.style.display = 'block';
                    // Use setTimeout to ensure opacity transition works
                    setTimeout(() => {
                        tooltip.style.opacity = '1';
                    }, 10);
                }
            });

            // Hide tooltip on mouseleave
            card.addEventListener('mouseleave', () => {
                if (tooltip) {
                    tooltip.style.opacity = '0';
                    // Wait for fade out animation before hiding
                    setTimeout(() => {
                        tooltip.style.display = 'none';
                    }, 200);
                }
            });
        }
    });
}

// Call setupTooltips when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupTooltips();
});

// Function to format the title for display
function formatSubstageTitle(value) {
    return value
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

// Add this to your existing code where you handle substage creation/display
const substageTitle = substageBlock.querySelector('.substage-name').value;
const formattedTitle = formatSubstageTitle(substageTitle);

// First, add an event listener to the project type selector
document.getElementById('projectType').addEventListener('change', function() {
    updateSubstageTitles(this.value);
});

// Function to update substage titles based on project type
function updateSubstageTitles(projectType) {
    const substageSelect = document.querySelector('.substage-name');
    if (!substageSelect) return;

    // Clear existing options
    substageSelect.innerHTML = '<option value="">Select Title</option>';

    if (projectType === 'architecture') {
        // Architecture Options
        substageSelect.innerHTML += `
            <optgroup label="Concept Drawings">
                <option value="concept_plan">Concept Plan</option>
                <option value="PPT">PPT</option>
                <option value="3D Model">3D Model</option>
            </optgroup>
            
            <optgroup label="Ground Floor">
                <option value="gf_floor_plan">Ground Floor Furniture Layout Plan</option>
                <option value="gf_ceiling_plan">Ground Floor Ceiling Plan</option>
                <option value="gf_electrical">Ground Floor Electrical Layout</option>
                <option value="gf_plumbing">Ground Floor Plumbing Layout</option>
                <option value="gf_door_window_schedule">Ground Floor Door & Window Schedule</option>
                <option value="gf_working_drawing">Ground Floor Working Drawing</option>
                <option value="gf_false_ceiling_layout">Ground Floor False Ceiling Layout Plan</option>
            </optgroup>
            
            <optgroup label="First Floor">
                <option value="1f_floor_plan">First Floor Furniture Layout Plan</option>
                <option value="1f_ceiling_plan">First Floor Ceiling Plan</option>
                <option value="1f_electrical">First Floor Electrical Layout</option>
                <option value="1f_plumbing">First Floor Plumbing Layout</option>
                <option value="1f_door_window_schedule">First Floor Door & Window Schedule</option>
                <option value="1f_working_drawing">First Floor Working Drawing</option>
                <option value="1f_false_ceiling_layout">First Floor False Ceiling Layout Plan</option>
            </optgroup>

            <!-- Add other floor options similarly -->
            
            <optgroup label="Structure Drawings">
                <option value="excavation_layout_plan">Excavation Layout Plan</option>
                <option value="footing_layout_plan">Footing Layout Plan</option>
                <option value="column_&_setting_layout_plan">Column & Setting Layout Plan</option>
                <option value="column_&_footing_details">Column & Footing Details</option>
                <option value="plinth_beam_layout_plan">Plinth Beam Layout Plan</option>
                <option value="ground_floor_roof_slab_beam_layout_plan">Ground Floor Roof Slab Beam Layout Plan</option>
                <option value="first_floor_roof_slab_beam_layout_plan">First Floor Roof Slab Beam Layout Plan</option>
                <option value="second_floor_roof_slab_beam_layout_plan">Second Floor Roof Slab Beam Layout Plan</option>
                <option value="third_floor_roof_slab_beam_layout_plan">Third Floor Roof Slab Beam Layout Plan</option>
                <option value="fourth_floor_roof_slab_beam_layout_plan">Fourth Floor Roof Slab Beam Layout Plan</option>
                <option value="fifth_floor_roof_slab_beam_layout_plan">Fifth Floor Roof Slab Beam Layout Plan</option>
                <option value="slab_details">Slab Details</option>
            </optgroup>
            
            <optgroup label="Other Drawings">
                <option value="site_plan">Site Plan</option>
                <option value="elevation_front">Front Elevation</option>
                <option value="elevation_rear">Rear Elevation</option>
                <option value="elevation_left">Left Side Elevation</option>
                <option value="elevation_right">Right Side Elevation</option>
                <option value="section_aa">Section A-A</option>
                <option value="section_bb">Section B-B</option>
                <option value="common_staircase_details">Common Staircase Details</option>
                <option value="toilet_detail">Toilet Detail</option>
                <option value="door_window_schedule_&_elevation_details">Door & Window Schedule & Elevation Details</option>
                <option value="compound_wall">Compound Wall Detail</option>
                <option value="landscape_plan">Landscape Plan</option>
            </optgroup>
        `;
    } else if (projectType === 'interior') {
        // Interior Options
        substageSelect.innerHTML += `
            <optgroup label="Concept Design">
                <option value="concept_board">Concept Board</option>
                <option value="mood_board">Mood Board</option>
                <option value="material_board">Material Board</option>
                <option value="3d_views">3D Views</option>
            </optgroup>

            <optgroup label="Layout Plans">
                <option value="furniture_layout">Furniture Layout Plan</option>
                <option value="flooring_layout">Flooring Layout Plan</option>
                <option value="ceiling_layout">Ceiling Layout Plan</option>
                <option value="electrical_layout">Electrical Layout Plan</option>
                <option value="hvac_layout">HVAC Layout Plan</option>
            </optgroup>

            <optgroup label="Detail Drawings">
                <option value="wall_elevation">Wall Elevation</option>
                <option value="wall_section">Wall Section Details</option>
                <option value="civil_work_details">Civil Work Details</option>
                <option value="carpentry_details">Carpentry Details</option>
                <option value="kitchen_details">Kitchen Details</option>
                <option value="wardrobe_details">Wardrobe Details</option>
                <option value="toilet_details">Toilet Details</option>
                <option value="door_details">Door Details</option>
            </optgroup>

            <optgroup label="Services">
                <option value="electrical_details">Electrical Details</option>
                <option value="plumbing_details">Plumbing Details</option>
                <option value="false_ceiling_details">False Ceiling Details</option>
                <option value="automation_details">Automation Details</option>
            </optgroup>

            <optgroup label="Final Deliverables">
                <option value="material_specifications">Material Specifications</option>
                <option value="furniture_specifications">Furniture Specifications</option>
                <option value="bom">Bill of Materials</option>
                <option value="tender_documents">Tender Documents</option>
                <option value="working_drawings">Working Drawings</option>
            </optgroup>
        `;
    }
}

// Call the function initially if project type is pre-selected
document.addEventListener('DOMContentLoaded', function() {
    const projectType = document.getElementById('projectType').value;
    if (projectType) {
        updateSubstageTitles(projectType);
    }
});

// Remove the validateSubstageDates function
function createSubstageHTML(stageNumber, substageNumber) {
    return `
        <div class="substage-block" data-substage="${substageNumber}">
            <div class="substage-header">
                <h5 class="substage-title">Task ${String(substageNumber).padStart(2, '0')}</h5>
                <button class="remove-substage-btn" onclick="removeSubstage(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="task-form-group">
                <label>Substage Title</label>
                <select class="substage-name" required>
                    <option value="">Select Title</option>
                    ${substageOptions}
                </select>
            </div>
            <div class="task-form-group">
                <label>Assign To</label>
                <select class="substage-assignee" value="${stageAssignee}">
                    <option value="">Select Employee</option>
                </select>
            </div>
            <div class="task-form-row">
                <div class="task-form-group">
                    <label>Start Date & Time</label>
                    <div class="task-datetime-input">
                        <input type="datetime-local" class="substage-start-date">
                    </div>
                </div>
                <div class="task-form-group">
                    <label>Due By</label>
                    <div class="task-datetime-input">
                        <input type="datetime-local" class="substage-due-date">
                    </div>
                </div>
            </div>
            <div class="file-upload-container">
                <input type="file" id="substageFile${stageNumber}_${substageNumber}" class="file-upload-input">
                <label for="substageFile${stageNumber}_${substageNumber}" class="file-upload-label">
                    <i class="fas fa-paperclip"></i>
                    <span>Attach File</span>
                </label>
                <div class="selected-file"></div>
            </div>
        </div>
    `;
}

// Remove autoPopulateDueDates function and its related event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Keep other initialization code
    const addStageBtn = document.getElementById('addStageBtn');
    if (addStageBtn) {
        addStageBtn.addEventListener('click', function() {
            console.log('Add Stage button clicked');
            addStage();
        });
    }
});

// Update stage and substage removal functions to remove date-related code
function removeStage(button) {
    const stageBlock = button.closest('.stage-block');
    stageBlock.remove();
}

function removeSubstage(button) {
    const substageBlock = button.closest('.substage-block');
    substageBlock.remove();
}

// ... rest of existing code ...

// Add this function to calculate distributed dates
function calculateDistributedDates(projectStartDate, projectEndDate, numberOfStages) {
    const start = new Date(projectStartDate);
    const end = new Date(projectEndDate);
    const totalDuration = end.getTime() - start.getTime();
    const stageDuration = totalDuration / numberOfStages;
    
    const dates = [];
    for (let i = 0; i < numberOfStages; i++) {
        const stageStart = new Date(start.getTime() + (stageDuration * i));
        const stageEnd = new Date(start.getTime() + (stageDuration * (i + 1)));
        dates.push({
            startDate: stageStart.toISOString().slice(0, 16), // Format: YYYY-MM-DDTHH:mm
            endDate: stageEnd.toISOString().slice(0, 16)
        });
    }
    return dates;
}

// Add this function to calculate substage dates
function calculateSubstageDates(stageStartDate, stageEndDate, numberOfSubstages) {
    const start = new Date(stageStartDate);
    const end = new Date(stageEndDate);
    const totalDuration = end.getTime() - start.getTime();
    const substageDuration = totalDuration / numberOfSubstages;
    
    const dates = [];
    for (let i = 0; i < numberOfSubstages; i++) {
        const substageStart = new Date(start.getTime() + (substageDuration * i));
        const substageEnd = new Date(start.getTime() + (substageDuration * (i + 1)));
        dates.push({
            startDate: substageStart.toISOString().slice(0, 16),
            endDate: substageEnd.toISOString().slice(0, 16)
        });
    }
    return dates;
}

// Modify the addStage function
window.addStage = function() {
    // Increment stage count
    stageCount++;
    
    // Get the stages wrapper
    const stagesWrapper = document.getElementById('stagesWrapper');
    
    // Get project dates
    const projectStartDate = document.getElementById('projectStartDate').value;
    const projectEndDate = document.getElementById('projectDueDate').value;
    
    // Calculate stage dates
    const stageDates = calculateDistributedDates(projectStartDate, projectEndDate, stageCount);
    const currentStageDates = stageDates[stageCount - 1];
    
    // Create new stage
    const newStage = createStageHTML(stageCount, currentStageDates);
    stagesWrapper.insertAdjacentHTML('beforeend', newStage);

    // Setup the new stage
    const stageElement = stagesWrapper.lastElementChild;
    populateStageAssignee(stageElement);

    // Set the dates
    const startDateInput = stageElement.querySelector('.stage-start-date');
    const dueDateInput = stageElement.querySelector('.stage-due-date');
    
    if (startDateInput && dueDateInput) {
        startDateInput.value = currentStageDates.startDate;
        dueDateInput.value = currentStageDates.endDate;
    }

    // Setup file input
    const fileInput = document.getElementById(`stageFile${stageCount}`);
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }
};

// Modify createStageHTML function to accept dates
window.createStageHTML = function(stageNumber, dates = null) {
    substageCounters[stageNumber] = 0;
    return `
        <div class="stage-block" data-stage="${stageNumber}">
            <!-- ... existing HTML ... -->
            <div class="task-form-row">
                <div class="task-form-group">
                    <label>Start Date & Time</label>
                    <div class="task-datetime-input">
                        <input type="datetime-local" class="stage-start-date" 
                            value="${dates ? dates.startDate : ''}" required>
                    </div>
                </div>
                <div class="task-form-group">
                    <label>Due By</label>
                    <div class="task-datetime-input">
                        <input type="datetime-local" class="stage-due-date" 
                            value="${dates ? dates.endDate : ''}" required>
                    </div>
                </div>
            </div>
            <!-- ... rest of the HTML ... -->
        </div>
    `;
};

// Modify addSubstage function
window.addSubstage = function(stageNumber) {
    const substagesWrapper = document.getElementById(`substagesWrapper${stageNumber}`);
    const stageBlock = substagesWrapper.closest('.stage-block');
    
    // Get stage assignee
    const stageAssignee = stageBlock.querySelector('.stage-assignee');
    const selectedStageAssigneeId = stageAssignee ? stageAssignee.value : '';
    const selectedStageAssigneeText = stageAssignee ? stageAssignee.options[stageAssignee.selectedIndex].text : '';
    
    // Increment substage counter
    substageCounters[stageNumber] = (substageCounters[stageNumber] || 0) + 1;
    const substageNumber = substageCounters[stageNumber];
    
    // Get stage dates
    const stageStartDate = stageBlock.querySelector('.stage-start-date').value;
    const stageDueDate = stageBlock.querySelector('.stage-due-date').value;
    
    // Calculate substage dates
    const substageDates = calculateSubstageDates(stageStartDate, stageDueDate, substageNumber);
    const currentSubstageDates = substageDates[substageNumber - 1];
    
    const newSubstage = createSubstageHTML(stageNumber, substageNumber, selectedStageAssigneeId, selectedStageAssigneeText);
    substagesWrapper.insertAdjacentHTML('beforeend', newSubstage);
    
    // Get the newly added substage element
    const lastSubstage = substagesWrapper.lastElementChild;
    
    // Set the calculated dates
    const startDateInput = lastSubstage.querySelector('.substage-start-date');
    const dueDateInput = lastSubstage.querySelector('.substage-due-date');
    
    if (startDateInput && dueDateInput) {
        startDateInput.value = currentSubstageDates.startDate;
        dueDateInput.value = currentSubstageDates.endDate;
    }

    // Add file input event listener
    const fileInput = document.getElementById(`substageFile${stageNumber}_${substageNumber}`);
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }
};

// Modify createSubstageHTML to accept the selected assignee
function createSubstageHTML(stageNumber, substageNumber, selectedAssigneeId, selectedAssigneeText) {
    const projectType = document.getElementById('projectType').value;
    
    // Your existing substageOptions code...
    
    return `
        <div class="substage-block" data-substage="${substageNumber}">
            <div class="substage-header">
                <h5 class="substage-title">Task ${String(substageNumber).padStart(2, '0')}</h5>
                <button class="remove-substage-btn" onclick="removeSubstage(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="task-form-group">
                <label>Substage Title</label>
                <select class="substage-name" required>
                    <option value="">Select Title</option>
                    ${substageOptions}
                </select>
            </div>
            <div class="task-form-group">
                <label>Assign To</label>
                <select class="substage-assignee">
                    <option value="${selectedAssigneeId}">${selectedAssigneeText}</option>
                </select>
            </div>
            <!-- Rest of your substage HTML -->
        </div>
    `;
}

// Add event listeners for project date changes
document.addEventListener('DOMContentLoaded', function() {
    const projectStartDate = document.getElementById('projectStartDate');
    const projectDueDate = document.getElementById('projectDueDate');
    
    function updateAllDates() {
        if (projectStartDate.value && projectDueDate.value) {
            const stages = document.querySelectorAll('.stage-block');
            const stageDates = calculateDistributedDates(projectStartDate.value, projectDueDate.value, stages.length);
            
            stages.forEach((stage, index) => {
                const dates = stageDates[index];
                const startDateInput = stage.querySelector('.stage-start-date');
                const dueDateInput = stage.querySelector('.stage-due-date');
                
                if (startDateInput && dueDateInput) {
                    startDateInput.value = dates.startDate;
                    dueDateInput.value = dates.endDate;
                }
                
                // Update substage dates
                const substages = stage.querySelectorAll('.substage-block');
                if (substages.length > 0) {
                    const substageDates = calculateSubstageDates(dates.startDate, dates.endDate, substages.length);
                    substages.forEach((substage, subIndex) => {
                        const subDates = substageDates[subIndex];
                        const subStartInput = substage.querySelector('.substage-start-date');
                        const subDueInput = substage.querySelector('.substage-due-date');
                        
                        if (subStartInput && subDueInput) {
                            subStartInput.value = subDates.startDate;
                            subDueInput.value = subDates.endDate;
                        }
                    });
                }
            });
        }
    }
    
    if (projectStartDate && projectDueDate) {
        projectStartDate.addEventListener('change', updateAllDates);
        projectDueDate.addEventListener('change', updateAllDates);
    }
});

// Add event listener for stage assignee changes
function setupStageAssigneeListener(stageElement) {
    const stageAssignee = stageElement.querySelector('.stage-assignee');
    
    stageAssignee.addEventListener('change', function(e) {
        const selectedUserId = e.target.value;
        
        // Find all substages within this stage and update their assignees
        const substages = stageElement.querySelectorAll('.substage-block');
        substages.forEach(substage => {
            const substageAssignee = substage.querySelector('.substage-assignee');
            if (substageAssignee) {
                substageAssignee.value = selectedUserId;
            }
        });
    });
}

// Add this after your createSubstageHTML function
function setupStageAssigneeListener(stageElement) {
    const stageAssignee = stageElement.querySelector('.stage-assignee');
    
    stageAssignee.addEventListener('change', function(e) {
        const selectedValue = e.target.value;
        const substages = stageElement.querySelectorAll('.substage-block .substage-assignee');
        
        substages.forEach(substageAssignee => {
            substageAssignee.value = selectedValue;
        });
    });
}

// Add this function to handle stage removal
window.removeStage = function(button) {
    const stageBlock = button.closest('.stage-block');
    const stagesWrapper = document.getElementById('stagesWrapper');
    
    // Remove the stage
    stageBlock.remove();
    
    // Get all remaining stages
    const remainingStages = stagesWrapper.querySelectorAll('.stage-block');
    
    // Renumber all remaining stages sequentially
    remainingStages.forEach((stage, index) => {
        const newStageNumber = index + 1;
        
        // Update stage title
        const stageTitle = stage.querySelector('.stage-title');
        if (stageTitle) {
            stageTitle.textContent = `Stage ${newStageNumber}`;
        }
        
        // Update data attribute
        stage.setAttribute('data-stage', newStageNumber);
        
        // Update file input ID and label
        const fileInput = stage.querySelector('.file-upload-input');
        const fileLabel = stage.querySelector('.file-upload-label');
        if (fileInput && fileLabel) {
            const newFileId = `stageFile${newStageNumber}`;
            fileInput.id = newFileId;
            fileLabel.setAttribute('for', newFileId);
        }
        
        // Update substages wrapper ID
        const substagesWrapper = stage.querySelector('.substages-wrapper');
        if (substagesWrapper) {
            substagesWrapper.id = `substagesWrapper${newStageNumber}`;
        }
    });
    
    // Update global stage count
    stageCount = remainingStages.length;
};