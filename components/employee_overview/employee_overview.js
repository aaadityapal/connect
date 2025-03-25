class EmployeeOverview {
    constructor() {
        this.currentDate = new Date();
        this.initializeCalendar();
        this.attachEventListeners();
    }

    initializeCalendar() {
        this.updateCalendarHeader();
        this.renderCalendar();
    }

    updateCalendarHeader() {
        const monthYear = this.currentDate.toLocaleString('default', { 
            month: 'long', 
            year: 'numeric' 
        });
        document.querySelector('.current-month').textContent = monthYear;
    }

    renderCalendar() {
        const calendarBody = document.getElementById('calendarBody');
        calendarBody.innerHTML = '';

        // Add day headers
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        days.forEach(day => {
            const dayHeader = document.createElement('div');
            dayHeader.className = 'calendar-day header';
            dayHeader.textContent = day;
            calendarBody.appendChild(dayHeader);
        });

        // Get first day of month and total days
        const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
        const lastDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
        
        // Add empty cells for days before start of month
        for (let i = 0; i < firstDay.getDay(); i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day empty';
            calendarBody.appendChild(emptyDay);
        }

        // Add days of month
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.textContent = day;

            // Add special classes for demo
            if (day === new Date().getDate() && 
                this.currentDate.getMonth() === new Date().getMonth() &&
                this.currentDate.getFullYear() === new Date().getFullYear()) {
                dayElement.classList.add('today');
            }

            // Add some demo leave indicators
            if (day === 15) dayElement.classList.add('has-leave', 'approved');
            if (day === 20) dayElement.classList.add('has-leave', 'pending');
            if (day === 25) dayElement.classList.add('has-leave', 'holiday');

            calendarBody.appendChild(dayElement);
        }
    }

    attachEventListeners() {
        document.querySelector('.prev-month').addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.initializeCalendar();
        });

        document.querySelector('.next-month').addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.initializeCalendar();
        });
    }

    // Method to update stats
    updateStats(stats) {
        if (stats.presentCount) {
            document.getElementById('presentCount').textContent = stats.presentCount;
        }
        if (stats.pendingCount) {
            document.getElementById('pendingCount').textContent = stats.pendingCount;
        }
        if (stats.shortLeaveCount) {
            document.getElementById('shortLeaveCount').textContent = stats.shortLeaveCount;
        }
        if (stats.onLeaveCount) {
            document.getElementById('onLeaveCount').textContent = stats.onLeaveCount;
        }
    }
} 