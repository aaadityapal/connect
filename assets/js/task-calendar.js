class TaskCalendar {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.currentDate = new Date();
        this.init();
    }

    init() {
        this.render();
        this.attachEventListeners();
        this.loadTasks();
    }

    render() {
        this.container.innerHTML = `
            <div class="calendar-header">
                <button class="calendar-nav prev"><i class="fas fa-chevron-left"></i></button>
                <h2 class="calendar-title"></h2>
                <button class="calendar-nav next"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="calendar-body">
                <div class="calendar-weekdays"></div>
                <div class="calendar-dates"></div>
            </div>
        `;
        this.renderWeekdays();
        this.updateCalendar();
    }

    renderWeekdays() {
        const weekdaysContainer = this.container.querySelector('.calendar-weekdays');
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        weekdays.forEach(day => {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-weekday';
            dayElement.textContent = day;
            weekdaysContainer.appendChild(dayElement);
        });
    }

    updateCalendar() {
        // Update calendar title
        const title = this.container.querySelector('.calendar-title');
        title.textContent = this.currentDate.toLocaleDateString('default', { 
            month: 'long', 
            year: 'numeric' 
        });

        // Update calendar grid
        this.renderDates();
    }

    renderDates() {
        const datesContainer = this.container.querySelector('.calendar-dates');
        datesContainer.innerHTML = '';

        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        // Add empty cells for days before the first of the month
        for (let i = 0; i < firstDay.getDay(); i++) {
            datesContainer.appendChild(this.createDateCell());
        }

        // Add the days of the month
        for (let day = 1; day <= lastDay.getDate(); day++) {
            datesContainer.appendChild(this.createDateCell(day));
        }
    }

    createDateCell(day = '') {
        const cell = document.createElement('div');
        cell.className = 'calendar-date';
        cell.setAttribute('data-date', `${this.currentDate.getFullYear()}-${String(this.currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`);
        
        if (day) {
            // Create date container
            const dateContainer = document.createElement('div');
            dateContainer.className = 'date-container';
            dateContainer.textContent = day;
            
            // Check if this date is today
            const today = new Date();
            if (today.getDate() === day && 
                today.getMonth() === this.currentDate.getMonth() && 
                today.getFullYear() === this.currentDate.getFullYear()) {
                cell.classList.add('today');
            }
            
            // Create add button
            const addButton = document.createElement('button');
            addButton.className = 'add-task-btn';
            addButton.innerHTML = '+';
            addButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const selectedDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), day);
                const formattedDate = selectedDate.toISOString().split('T')[0];
                
                const modal = document.getElementById('studioTaskCreationModal');
                if (modal) {
                    const dateInput = modal.querySelector('[name="due_date"]');
                    if (dateInput) {
                        dateInput.value = formattedDate;
                    }
                    
                    // Force remove any existing modal backdrops and classes
                    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    
                    try {
                        if (typeof bootstrap !== 'undefined') {
                            // Destroy existing modal instance if it exists
                            const existingModal = bootstrap.Modal.getInstance(modal);
                            if (existingModal) {
                                existingModal.dispose();
                            }
                            
                            const taskModal = new bootstrap.Modal(modal, {
                                backdrop: 'static', // Prevents closing on backdrop click
                                keyboard: true
                            });
                            
                            // Handle modal events
                            modal.addEventListener('shown.bs.modal', function () {
                                document.body.style.overflow = 'auto';
                                document.body.style.paddingRight = '0';
                            });
                            
                            modal.addEventListener('hidden.bs.modal', function () {
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = 'auto';
                                document.body.style.paddingRight = '0';
                                document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                            });
                            
                            taskModal.show();
                        } else {
                            $(modal).modal('show');
                        }
                    } catch (error) {
                        console.warn('Modal initialization error:', error);
                    }
                } else {
                    console.error('Studio Task Creation Modal not found');
                }
            });
            
            cell.appendChild(dateContainer);
            cell.appendChild(addButton);
        }
        
        return cell;
    }

    loadTasks() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth() + 1;

        fetch(`get_calendar_tasks.php?year=${year}&month=${month}`)
            .then(response => response.json())
            .then(tasks => this.renderTasks(tasks))
            .catch(error => console.error('Error loading tasks:', error));
    }

    renderTasks(tasks) {
        console.log('Received tasks:', tasks); // Debug log

        // Convert tasks object to array if it's not already
        Object.entries(tasks).forEach(([date, dayTasks]) => {
            console.log('Processing date:', date, 'tasks:', dayTasks); // Debug log
            
            dayTasks.forEach(task => {
                const taskDate = new Date(task.due_date);
                const day = taskDate.getDate();
                console.log('Processing task for day:', day, task); // Debug log
                
                // Find the corresponding date cell
                const dateCell = this.container.querySelector(`.calendar-date[data-date="${date}"]`);
                if (dateCell) {
                    // Create or get tasks container
                    let tasksContainer = dateCell.querySelector('.tasks-container');
                    if (!tasksContainer) {
                        tasksContainer = document.createElement('div');
                        tasksContainer.className = 'tasks-container';
                        dateCell.appendChild(tasksContainer);
                    }

                    // Create task element
                    const taskElement = document.createElement('div');
                    taskElement.className = 'calendar-task';
                    
                    // Add stage info
                    if (task.stage_name) {
                        const stageSpan = document.createElement('span');
                        stageSpan.className = 'task-stage';
                        stageSpan.textContent = task.stage_name;
                        stageSpan.style.backgroundColor = task.stage_color || '#ddd';
                        taskElement.appendChild(stageSpan);
                    }
                    
                    // Add substage info if exists
                    if (task.substage_name) {
                        const substageSpan = document.createElement('span');
                        substageSpan.className = 'task-substage';
                        substageSpan.textContent = task.substage_name;
                        substageSpan.style.backgroundColor = task.substage_color || '#eee';
                        taskElement.appendChild(substageSpan);
                    }
                    
                    // Add title
                    const titleSpan = document.createElement('span');
                    titleSpan.className = 'task-title';
                    titleSpan.textContent = task.title;
                    taskElement.appendChild(titleSpan);
                    
                    tasksContainer.appendChild(taskElement);
                } else {
                    console.log('Date cell not found for:', date); // Debug log
                }
            });
        });
    }

    attachEventListeners() {
        const prev = this.container.querySelector('.calendar-nav.prev');
        const next = this.container.querySelector('.calendar-nav.next');

        prev.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.updateCalendar();
            this.loadTasks();
        });

        next.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.updateCalendar();
            this.loadTasks();
        });
    }
} 