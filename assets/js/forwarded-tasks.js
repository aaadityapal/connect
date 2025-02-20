class ForwardedTasks {
    static instance = null;

    constructor() {
        if (ForwardedTasks.instance) {
            return ForwardedTasks.instance;
        }
        ForwardedTasks.instance = this;
        
        this.container = document.querySelector('.forwarded-tasks-container');
        this.initialize();
    }

    initialize() {
        if (!this.container) {
            console.error('Forwarded tasks container not found');
            return;
        }
        this.fetchTasks();
        // Refresh every 5 minutes
        setInterval(() => this.fetchTasks(), 300000);
    }

    async fetchTasks() {
        try {
            this.showLoading();
            
            const response = await fetch('/hr/api/tasks/forwarded.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new TypeError("Received non-JSON response from server");
            }

            const result = await response.json();
            
            if (result.success) {
                this.renderTasks(result.data);
            } else {
                throw new Error(result.error || 'Failed to fetch tasks');
            }
        } catch (error) {
            console.error('Error fetching forwarded tasks:', error);
            this.showError(error.message);
        }
    }

    showLoading() {
        if (!this.container) return;
        this.container.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-circle-notch fa-spin"></i>
                <p>Loading tasks...</p>
            </div>
        `;
    }

    renderTasks(tasks) {
        if (!this.container) return;

        if (!Array.isArray(tasks) || tasks.length === 0) {
            this.container.innerHTML = this.getEmptyState();
            return;
        }

        this.container.innerHTML = tasks.map(task => this.createTaskCard(task)).join('');
    }

    createTaskCard(task) {
        return `
            <div class="forwarded-task-card" data-task-id="${task.id}">
                <div class="task-header">
                    <div class="task-title">
                        <h3>${this.escapeHtml(task.project_title)}</h3>
                        <span class="task-type ${task.type.toLowerCase()}">${this.escapeHtml(task.type)}</span>
                    </div>
                    <span class="task-status ${task.status.toLowerCase()}">${this.escapeHtml(task.status)}</span>
                </div>
                <div class="task-details">
                    <p class="task-description">${this.escapeHtml(task.details)}</p>
                    <div class="task-progress">
                        <span class="progress-label">Status:</span>
                        <span class="progress-status ${task.task_status.toLowerCase()}">
                            ${this.escapeHtml(task.task_status)}
                        </span>
                    </div>
                    <div class="task-meta">
                        <span class="forwarded-by">
                            <i class="fas fa-user"></i> 
                            Forwarded by: ${this.escapeHtml(task.forwarded_by)}
                        </span>
                        <span class="forwarded-time">
                            <i class="far fa-clock"></i>
                            ${this.escapeHtml(task.forwarded_at)}
                        </span>
                    </div>
                </div>
            </div>
        `;
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    getEmptyState() {
        return `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No forwarded tasks</p>
            </div>
        `;
    }

    showError(message) {
        if (!this.container) return;
        
        this.container.innerHTML = `
            <div class="error-state">
                <i class="fas fa-exclamation-circle"></i>
                <p>Failed to load forwarded tasks</p>
                <p class="error-message">${this.escapeHtml(message)}</p>
                <button onclick="ForwardedTasks.refresh()">Try Again</button>
            </div>
        `;
    }

    static refresh() {
        const instance = new ForwardedTasks();
        instance.fetchTasks();
    }
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    new ForwardedTasks();
}); 