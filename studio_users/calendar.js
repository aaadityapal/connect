document.addEventListener("DOMContentLoaded", () => {









    // Calendar Variables
    const currentMonthDisplay = document.getElementById('currentMonthDisplay');
    const calendarGrid = document.getElementById('calendarGrid');
    const prevMonthBtn = document.getElementById('prevMonthBtn');
    const nextMonthBtn = document.getElementById('nextMonthBtn');

    let currentDate = new Date();

    // Fixed 2026 Holidays Data
    const holidays2026 = [
        { month: 0, day: 1, name: "New Year" },
        { month: 0, day: 26, name: "Republic Day" },
        { month: 1, day: 15, name: "Maha Shivaratri" },
        { month: 2, day: 4, name: "Holi" },
        { month: 2, day: 26, name: "Ram Navmi" },
        { month: 7, day: 15, name: "Independence Day" },
        { month: 9, day: 2, name: "Gandhi Jayanti" },
        { month: 10, day: 8, name: "Diwali" },
        { month: 10, day: 9, name: "Govardhan Puja" },
        { month: 10, day: 11, name: "Bhai Dooj" }
    ];

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        if (currentMonthDisplay) {
            currentMonthDisplay.textContent = new Date(year, month).toLocaleString('default', { month: 'long', year: 'numeric' });
        }

        if (calendarGrid) {
            calendarGrid.innerHTML = '';

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Empty cells
            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day empty';
                calendarGrid.appendChild(emptyCell);
            }

            const today = new Date();
            let saturdayCount = 0;

            for (let i = 1; i <= daysInMonth; i++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.textContent = i;

                // Check Today
                if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                    dayCell.classList.add('today');
                }

                // Helper Date Object for this cell
                const cellDate = new Date(year, month, i);

                // 1. Holiday Logic
                const holiday = holidays2026.find(h => h.month === month && h.day === i);
                if (holiday) {
                    dayCell.classList.add('has-holiday');
                    dayCell.setAttribute('title', holiday.name);

                    // Add Holiday Indicator dot
                    const dot = document.createElement('div');
                    dot.className = 'holiday-dot';
                    dayCell.appendChild(dot);
                }

                // 2. 4th Saturday Logic (Monthly Meeting)
                let isMeetingDay = false;
                if (cellDate.getDay() === 6) { // 6 is Saturday
                    saturdayCount++;
                    if (saturdayCount === 4) {
                        isMeetingDay = true;
                        dayCell.classList.add('has-meeting');
                        const existingTitle = dayCell.getAttribute('title') || "";
                        dayCell.setAttribute('title', existingTitle ? existingTitle + " | Monthly Office Meeting" : "Monthly Office Meeting");
                    }
                }

                // 3. Task Logic
                let hasTask = false;
                Object.keys(window.tasksData).forEach(type => {
                    window.tasksData[type].forEach(task => {
                        if (task.rawDate) {
                            const taskDate = new Date(task.rawDate);
                            if (taskDate.getDate() === i && taskDate.getMonth() === month && taskDate.getFullYear() === year) {
                                hasTask = true;
                            }
                        } else {
                            const timeLower = task.time.toLowerCase();
                            const monthNameShort = cellDate.toLocaleDateString('en-US', { month: 'short' }).toLowerCase();
                            if (timeLower.includes(`${monthNameShort} ${i}`)) {
                                hasTask = true;
                            } else {
                                const td = new Date();
                                if (td.getDate() === i && td.getMonth() === month && td.getFullYear() === year) {
                                    if (type === 'daily' || timeLower.includes('today')) hasTask = true;
                                }
                            }
                        }
                    });
                });

                if (hasTask) {
                    dayCell.classList.add('has-task');
                }

                dayCell.addEventListener('click', () => {
                    document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
                    dayCell.classList.add('selected');
                    openCalendarDayModal(cellDate, holiday ? holiday.name : null, isMeetingDay);
                });

                calendarGrid.appendChild(dayCell);
            }
        }
    }

    if (prevMonthBtn && nextMonthBtn) {
        prevMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        nextMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });
    }

    // Initial Render
    renderCalendar();

    // Calendar Day Modal Logic
    function openCalendarDayModal(cellDate, holidayText, hasMeeting) {
        const modal = document.getElementById('calendarDayModal');
        if (!modal) return;

        const title = document.getElementById('calModalTitle');
        const holidayDiv = document.getElementById('calModalHoliday');
        const holidayTextEl = document.getElementById('calModalHolidayText');
        const meetingDiv = document.getElementById('calModalMeeting');
        const tasksContainer = document.getElementById('calModalTasks');

        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        title.textContent = cellDate.toLocaleDateString('en-US', options);

        if (holidayText) {
            holidayDiv.style.display = 'block';
            holidayTextEl.textContent = holidayText;
        } else {
            holidayDiv.style.display = 'none';
        }

        meetingDiv.style.display = hasMeeting ? 'block' : 'none';

        tasksContainer.innerHTML = '';
        let dayTasks = [];

        Object.keys(window.tasksData).forEach(type => {
            window.tasksData[type].forEach(task => {
                let matches = false;
                if (task.rawDate) {
                    const taskDate = new Date(task.rawDate);
                    if (taskDate.getDate() === cellDate.getDate() && taskDate.getMonth() === cellDate.getMonth() && taskDate.getFullYear() === cellDate.getFullYear()) {
                        matches = true;
                    }
                } else {
                    const timeLower = task.time.toLowerCase();
                    const monthNameShort = cellDate.toLocaleDateString('en-US', { month: 'short' }).toLowerCase();
                    const dayNum = cellDate.getDate();

                    if (timeLower.includes(`${monthNameShort} ${dayNum}`)) {
                        matches = true;
                    } else {
                        const today = new Date();
                        if (today.getDate() === cellDate.getDate() && today.getMonth() === cellDate.getMonth()) {
                            if (type === 'daily' || timeLower.includes('today')) matches = true;
                        }
                    }
                }
                if (matches) {
                    if (!dayTasks.find(t => t.id === task.id)) {
                        dayTasks.push(task);
                    }
                }
            });
        });

        if (dayTasks.length === 0) {
            tasksContainer.innerHTML = '<div style="padding: 15px; text-align: center; color: #6b7280; font-style: italic;">No tasks scheduled for this day.</div>';
        } else {
            dayTasks.forEach((task, index) => {
                const el = document.createElement('div');
                const badgeClass = task.badge.toLowerCase() === 'high' ? 'high' : task.badge.toLowerCase() === 'med' ? 'medium' : 'low';
                const doneClass = task.checked ? "text-decoration: line-through; color: #94a3b8;" : "color: #0f172a;";
                const doneDescClass = task.checked ? "text-decoration: line-through; opacity: 0.7;" : "";
                const opacityClass = task.checked ? "opacity: 0.7;" : "opacity: 1;";

                let badgeStyle = '';
                let borderLeftColor = '';
                if (badgeClass === 'high') {
                    badgeStyle = 'background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);';
                    borderLeftColor = '#ef4444';
                } else if (badgeClass === 'medium') {
                    badgeStyle = 'background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);';
                    borderLeftColor = '#f59e0b';
                } else {
                    badgeStyle = 'background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);';
                    borderLeftColor = '#10b981';
                }

                if (task.checked) borderLeftColor = '#cbd5e1'; // Grey for completed

                // Simple entrance animation
                const animationDelay = (index * 0.05) + 's';

                el.className = 'task-item';
                el.style.cssText = `
                    background: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-left: 4px solid ${borderLeftColor};
                    padding: 1rem;
                    border-radius: 12px;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                    transition: transform 0.2s, box-shadow 0.2s;
                    animation: tlBlockIn 0.3s ease-out ${animationDelay} both;
                    ${opacityClass}
                `;

                // Add hover effect via event listeners since inline hover is not possible
                el.addEventListener('mouseenter', () => {
                    if (!task.checked) {
                        el.style.transform = 'translateY(-2px)';
                        el.style.boxShadow = '0 6px 12px rgba(0,0,0,0.05)';
                    }
                });
                el.addEventListener('mouseleave', () => {
                    el.style.transform = '';
                    el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.02)';
                });

                el.innerHTML = `
                    <div style="flex: 1; padding-right: 1rem;">
                        <h4 style="margin: 0 0 0.35rem 0; font-size: 0.95rem; font-weight: 600; line-height: 1.3; ${doneClass}">${task.title}</h4>
                        <p style="margin: 0; font-size: 0.8rem; color: #64748b; line-height: 1.4; display:flex; align-items:center; gap: 0.4rem; ${doneDescClass}"><i class="fa-regular fa-clock" style="font-size: 0.75rem;"></i> ${task.time || 'All Day'}</p>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem; flex-shrink: 0;">
                        <span style="font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.05em; ${badgeStyle}">${task.badge}</span>
                        ${task.checked ? '<div style="background: #e2e8f0; color: #64748b; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><i class="fa-solid fa-check"></i> DONE</div>' : ''}
                    </div>
                `;
                tasksContainer.appendChild(el);
            });
        }

        modal.classList.add('visible', 'open');
        document.body.style.overflow = 'hidden';
    }

    const closeCalDayModal = document.getElementById('closeCalDayModal');
    if (closeCalDayModal) {
        closeCalDayModal.addEventListener('click', () => {
            const modal = document.getElementById('calendarDayModal');
            if (modal) {
                modal.classList.remove('visible', 'open');
                document.body.style.overflow = '';
            }
        });
    }

    // Close on outsiade click
    const calDayModalOverlay = document.getElementById('calendarDayModal');
    if (calDayModalOverlay) {
        calDayModalOverlay.addEventListener('click', (e) => {
            if (e.target === calDayModalOverlay) {
                calDayModalOverlay.classList.remove('visible', 'open');
                document.body.style.overflow = '';
            }
        });
    }

});
