class ProjectMetricsDashboard {
    constructor() {
        this.initializeCharts();
        this.setupEventListeners();
        this.loadDummyData();
    }

    initializeCharts() {
        // 1. Destroy any existing chart instances first
        if (window.projectStatusChart) {
            window.projectStatusChart.destroy();
        }
        
        // 2. Then create the new chart instance
        const ctx = document.getElementById('projectStatusChart').getContext('2d');
        window.projectStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Total Active', 'Pending', 'Due', 'Overdue'],
                datasets: [{
                    data: [12, 5, 8, 3],
                    backgroundColor: [
                        'rgba(76, 175, 80, 0.8)',  // Green
                        'rgba(255, 193, 7, 0.8)',  // Yellow
                        'rgba(33, 150, 243, 0.8)', // Blue
                        'rgba(220, 53, 69, 0.8)'   // Red
                    ],
                    borderColor: [
                        'rgba(76, 175, 80, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(33, 150, 243, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Project Status Distribution',
                        padding: {
                            top: 10,
                            bottom: 20
                        }
                    }
                }
            }
        });
    }

    loadDummyData() {
        // Dummy data for demonstration
        const dummyData = {
            totalActive: 12,
            pending: 5,
            due: 8,
            overdue: 3
        };

        // Update the metrics display with proper styling
        this.updateMetricDisplay('totalActiveProjects', dummyData.totalActive, 'total-active');
        this.updateMetricDisplay('pendingProjects', dummyData.pending, 'pending');
        this.updateMetricDisplay('dueProjects', dummyData.due, 'due');
        this.updateMetricDisplay('overdueProjects', dummyData.overdue, 'overdue');

        // Update chart
        window.projectStatusChart.data.datasets[0].data = [
            dummyData.totalActive,
            dummyData.pending,
            dummyData.due,
            dummyData.overdue
        ];
        window.projectStatusChart.update();
    }

    updateMetricDisplay(elementId, value, className) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value;
            // Add appropriate styling class
            element.className = `metric-value ${className}`;
        }
    }

    setupEventListeners() {
        const applyFilterBtn = document.querySelector('.metrics-apply-btn');
        const resetBtn = document.querySelector('.metrics-reset-btn');

        if (applyFilterBtn) {
            applyFilterBtn.addEventListener('click', () => this.applyDateFilter());
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetDateFilter());
        }
    }

    async fetchProjectMetrics() {
        try {
            const response = await fetch('/api/project-metrics');
            const data = await response.json();
            this.updateProjectMetrics(data);
        } catch (error) {
            console.error('Error fetching project metrics:', error);
        }
    }

    async fetchUpcomingStages() {
        try {
            const response = await fetch('/api/upcoming-stages');
            const stages = await response.json();
            this.renderUpcomingStages(stages);
        } catch (error) {
            console.error('Error fetching upcoming stages:', error);
        }
    }

    async fetchPendingSubstages() {
        try {
            const response = await fetch('/api/pending-substages');
            const substages = await response.json();
            this.renderPendingSubstages(substages);
        } catch (error) {
            console.error('Error fetching pending substages:', error);
        }
    }

    updateProjectMetrics(data) {
        // Update metrics values
        this.updateMetricDisplay('totalActiveProjects', data.totalActive, 'total-active');
        this.updateMetricDisplay('pendingProjects', data.pending, 'pending');
        this.updateMetricDisplay('dueProjects', data.due, 'due');
        this.updateMetricDisplay('overdueProjects', data.overdue, 'overdue');

        // Update chart
        window.projectStatusChart.data.datasets[0].data = [
            data.totalActive,
            data.pending,
            data.due,
            data.overdue
        ];
        window.projectStatusChart.update();
    }

    renderUpcomingStages(stages) {
        const container = document.getElementById('upcomingStagesList');
        container.innerHTML = stages.map(stage => `
            <div class="stage-item">
                <div class="stage-header">
                    <h4>${stage.name}</h4>
                    <span class="stage-date">${stage.startDate}</span>
                </div>
                <div class="stage-project">${stage.projectName}</div>
                <div class="stage-status ${stage.status}">${stage.status}</div>
            </div>
        `).join('');
    }

    renderPendingSubstages(substages) {
        const container = document.getElementById('pendingSubstagesList');
        container.innerHTML = substages.map(substage => `
            <div class="substage-item">
                <div class="substage-header">
                    <h4>${substage.name}</h4>
                    <span class="substage-date">${substage.dueDate}</span>
                </div>
                <div class="substage-project">${substage.projectName}</div>
                <div class="substage-stage">${substage.stageName}</div>
                <div class="substage-status ${substage.status}">${substage.status}</div>
            </div>
        `).join('');
    }

    applyDateFilter() {
        const startDate = document.getElementById('metricsStartDate')?.value;
        const endDate = document.getElementById('metricsEndDate')?.value;
        // For demo, just reload dummy data
        this.loadDummyData();
    }

    resetDateFilter() {
        const startDateInput = document.getElementById('metricsStartDate');
        const endDateInput = document.getElementById('metricsEndDate');
        
        if (startDateInput) startDateInput.value = '';
        if (endDateInput) endDateInput.value = '';
        
        this.loadDummyData();
    }
}

// Initialize dashboard when document is ready
document.addEventListener('DOMContentLoaded', () => {
    new ProjectMetricsDashboard();
}); 