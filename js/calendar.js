document.addEventListener('DOMContentLoaded', function() {
    console.log('Calendar JS loaded');
    
    // Get task section and buttons
    const taskSection = document.getElementById('taskSection');
    const boardBtn = document.getElementById('boardViewBtn');
    const calendarBtn = document.getElementById('calendarViewBtn');
    
    console.log('Task Section:', taskSection); // Debug
    console.log('Board Button:', boardBtn); // Debug
    console.log('Calendar Button:', calendarBtn); // Debug
    
    if (!taskSection) {
        console.error('Task section not found!');
        return;
    }
    
    if (!boardBtn || !calendarBtn) {
        console.error('Toggle buttons not found!');
        return;
    }
    
    // Add click listeners
    calendarBtn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Calendar button clicked'); // Debug
        
        // Hide task stats
        const taskStats = document.querySelector('.task-stats');
        if (taskStats) {
            taskStats.style.display = 'none';
        }
        
        // Update button states
        boardBtn.classList.remove('active');
        calendarBtn.classList.add('active');
        
        // Clear task section and add calendar
        taskSection.innerHTML = `
            <div class="calendar-view">
                <div class="calendar-header">
                    <div class="month-selector">
                        <button class="calendar-nav" id="prevMonthBtn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="currentMonth"></span>
                        <button class="calendar-nav" id="nextMonthBtn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="weekday-header">
                        <div class="weekday">Sun</div>
                        <div class="weekday">Mon</div>
                        <div class="weekday">Tue</div>
                        <div class="weekday">Wed</div>
                        <div class="weekday">Thu</div>
                        <div class="weekday">Fri</div>
                        <div class="weekday">Sat</div>
                    </div>
                    <div id="calendarDates" class="calendar-dates"></div>
                </div>
            </div>
        `;
        
        initializeCalendar();
    });
    
    boardBtn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Board button clicked'); // Debug
        location.reload();
    });
});

let currentDate = new Date();

function initializeCalendar() {
    console.log('Initializing calendar'); // Debug
    
    // Set up month navigation
    document.getElementById('prevMonthBtn').addEventListener('click', prevMonth);
    document.getElementById('nextMonthBtn').addEventListener('click', nextMonth);
    
    updateCalendarDisplay();
}

function updateCalendarDisplay() {
    console.log('Updating calendar display'); // Debug
    
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    
    const monthYear = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
    document.getElementById('currentMonth').textContent = monthYear;
    
    const calendarDates = document.getElementById('calendarDates');
    if (calendarDates) {
        calendarDates.innerHTML = generateCalendarDays();
    }
}

function generateCalendarDays() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDay = firstDay.getDay();
    
    let calendarHTML = '';
    let dayCount = 1;
    
    // Create 6 weeks of days
    for (let i = 0; i < 6; i++) {
        for (let j = 0; j < 7; j++) {
            if (i === 0 && j < startingDay) {
                calendarHTML += '<div class="calendar-cell empty"></div>';
            } else if (dayCount > daysInMonth) {
                calendarHTML += '<div class="calendar-cell empty"></div>';
            } else {
                const isToday = isCurrentDay(year, month, dayCount);
                calendarHTML += `
                    <div class="calendar-cell ${isToday ? 'today' : ''}">
                        <div class="date-number">${dayCount}</div>
                        <div class="task-list"></div>
                    </div>
                `;
                dayCount++;
            }
        }
    }
    
    return calendarHTML;
}

function isCurrentDay(year, month, day) {
    const today = new Date();
    return today.getFullYear() === year &&
           today.getMonth() === month &&
           today.getDate() === day;
}

function prevMonth() {
    console.log('Previous month clicked'); // Debug
    currentDate.setMonth(currentDate.getMonth() - 1);
    updateCalendarDisplay();
}

function nextMonth() {
    console.log('Next month clicked'); // Debug
    currentDate.setMonth(currentDate.getMonth() + 1);
    updateCalendarDisplay();
} 