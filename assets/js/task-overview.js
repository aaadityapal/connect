class TaskOverview {
    constructor() {
        // Task filter functionality
        this.taskFilters = document.querySelectorAll('.task-filter');
        this.dateFrom = document.getElementById('dateFrom');
        this.dateTo = document.getElementById('dateTo');
        this.applyFilterBtn = document.querySelector('.apply-filter-btn');
        this.clearFilterBtn = document.querySelector('.clear-filter-btn');
        
        this.attachEventListeners();
        this.initializeProgressBars();
    }

    attachEventListeners() {
        // Task filter clicks
        this.taskFilters.forEach(filter => {
            filter.addEventListener('click', () => this.handleFilterClick(filter));
        });

        // Date filter buttons
        this.applyFilterBtn.addEventListener('click', () => this.applyDateFilter());
        this.clearFilterBtn.addEventListener('click', () => this.clearDateFilter());
    }

    handleFilterClick(clickedFilter) {
        this.taskFilters.forEach(f => f.classList.remove('active'));
        clickedFilter.classList.add('active');
        // TODO: Implement actual filtering logic
    }

    applyDateFilter() {
        // TODO: Implement date filtering logic
        console.log('Filtering from:', this.dateFrom.value, 'to:', this.dateTo.value);
    }

    clearDateFilter() {
        this.dateFrom.value = '';
        this.dateTo.value = '';
        // TODO: Reset to default view
    }

    initializeProgressBars() {
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    }
}

// Don't initialize here - it will be initialized in the main script
