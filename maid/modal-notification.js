// Modal Notification System
// Replaces default alert() and confirm() with custom modals

class ModalNotification {
    constructor() {
        this.createModalHTML();
        this.attachEventListeners();
    }

    createModalHTML() {
        // Create modal overlay and container
        const modalHTML = `
            <!-- Alert Modal -->
            <div id="customAlertModal" class="custom-modal">
                <div class="custom-modal-content">
                    <div class="custom-modal-header">
                        <div class="custom-modal-icon" id="alertIcon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <h3 id="alertTitle">Notification</h3>
                    </div>
                    <div class="custom-modal-body">
                        <p id="alertMessage"></p>
                    </div>
                    <div class="custom-modal-footer">
                        <button class="custom-modal-btn custom-modal-btn-primary" id="alertOkBtn">OK</button>
                    </div>
                </div>
            </div>

            <!-- Confirm Modal -->
            <div id="customConfirmModal" class="custom-modal">
                <div class="custom-modal-content">
                    <div class="custom-modal-header">
                        <div class="custom-modal-icon confirm-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <h3 id="confirmTitle">Confirm</h3>
                    </div>
                    <div class="custom-modal-body">
                        <p id="confirmMessage"></p>
                    </div>
                    <div class="custom-modal-footer">
                        <button class="custom-modal-btn custom-modal-btn-secondary" id="confirmCancelBtn">Cancel</button>
                        <button class="custom-modal-btn custom-modal-btn-primary" id="confirmOkBtn">Confirm</button>
                    </div>
                </div>
            </div>
        `;

        // Append to body
        const div = document.createElement('div');
        div.innerHTML = modalHTML;
        document.body.appendChild(div);

        // Add styles
        this.addStyles();
    }

    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .custom-modal {
                display: none;
                position: fixed;
                z-index: 10000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                animation: fadeIn 0.2s ease;
            }

            .custom-modal.show {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .custom-modal-content {
                background: white;
                border-radius: 12px;
                width: 90%;
                max-width: 400px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                animation: slideUp 0.3s ease;
                overflow: hidden;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .custom-modal-header {
                padding: 1.5rem;
                text-align: center;
                border-bottom: 1px solid #e5e7eb;
            }

            .custom-modal-icon {
                width: 60px;
                height: 60px;
                margin: 0 auto 1rem;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .custom-modal-icon.success-icon {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            }

            .custom-modal-icon.error-icon {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            }

            .custom-modal-icon.warning-icon {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            }

            .custom-modal-icon.confirm-icon {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            }

            .custom-modal-icon svg {
                color: white;
            }

            .custom-modal-header h3 {
                margin: 0;
                font-size: 1.25rem;
                font-weight: 600;
                color: #1f2937;
            }

            .custom-modal-body {
                padding: 1.5rem;
                text-align: center;
            }

            .custom-modal-body p {
                margin: 0;
                font-size: 1rem;
                color: #6b7280;
                line-height: 1.6;
            }

            .custom-modal-footer {
                padding: 1rem 1.5rem;
                display: flex;
                gap: 0.75rem;
                justify-content: flex-end;
                background: #f9fafb;
            }

            .custom-modal-btn {
                padding: 0.625rem 1.25rem;
                border: none;
                border-radius: 6px;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .custom-modal-btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .custom-modal-btn-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            }

            .custom-modal-btn-secondary {
                background: #e5e7eb;
                color: #374151;
            }

            .custom-modal-btn-secondary:hover {
                background: #d1d5db;
            }

            .custom-modal-btn:active {
                transform: translateY(0);
            }

            @media (max-width: 480px) {
                .custom-modal-content {
                    width: 95%;
                    max-width: none;
                }

                .custom-modal-header {
                    padding: 1.25rem;
                }

                .custom-modal-icon {
                    width: 50px;
                    height: 50px;
                }

                .custom-modal-body {
                    padding: 1.25rem;
                }

                .custom-modal-footer {
                    flex-direction: column-reverse;
                }

                .custom-modal-btn {
                    width: 100%;
                }
            }
        `;
        document.head.appendChild(style);
    }

    attachEventListeners() {
        // Alert modal close
        document.getElementById('alertOkBtn').addEventListener('click', () => {
            this.closeAlert();
        });

        // Confirm modal buttons
        document.getElementById('confirmOkBtn').addEventListener('click', () => {
            this.confirmCallback(true);
            this.closeConfirm();
        });

        document.getElementById('confirmCancelBtn').addEventListener('click', () => {
            this.confirmCallback(false);
            this.closeConfirm();
        });

        // Close on overlay click
        document.getElementById('customAlertModal').addEventListener('click', (e) => {
            if (e.target.id === 'customAlertModal') {
                this.closeAlert();
            }
        });

        document.getElementById('customConfirmModal').addEventListener('click', (e) => {
            if (e.target.id === 'customConfirmModal') {
                this.confirmCallback(false);
                this.closeConfirm();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (document.getElementById('customAlertModal').classList.contains('show')) {
                    this.closeAlert();
                }
                if (document.getElementById('customConfirmModal').classList.contains('show')) {
                    this.confirmCallback(false);
                    this.closeConfirm();
                }
            }
        });
    }

    showAlert(message, type = 'info', title = null) {
        const modal = document.getElementById('customAlertModal');
        const messageEl = document.getElementById('alertMessage');
        const titleEl = document.getElementById('alertTitle');
        const iconEl = document.getElementById('alertIcon');

        messageEl.textContent = message;

        // Set title
        if (title) {
            titleEl.textContent = title;
        } else {
            titleEl.textContent = type === 'success' ? 'Success' :
                type === 'error' ? 'Error' :
                    type === 'warning' ? 'Warning' : 'Notification';
        }

        // Set icon class
        iconEl.className = 'custom-modal-icon';
        if (type === 'success') iconEl.classList.add('success-icon');
        else if (type === 'error') iconEl.classList.add('error-icon');
        else if (type === 'warning') iconEl.classList.add('warning-icon');

        modal.classList.add('show');
    }

    closeAlert() {
        document.getElementById('customAlertModal').classList.remove('show');
    }

    showConfirm(message, callback, title = 'Confirm') {
        const modal = document.getElementById('customConfirmModal');
        const messageEl = document.getElementById('confirmMessage');
        const titleEl = document.getElementById('confirmTitle');

        messageEl.textContent = message;
        titleEl.textContent = title;
        this.confirmCallback = callback;

        modal.classList.add('show');
    }

    closeConfirm() {
        document.getElementById('customConfirmModal').classList.remove('show');
    }
}

// Initialize modal notification system
const modalNotification = new ModalNotification();

// Override default alert and confirm
window.customAlert = function (message, type = 'info', title = null) {
    modalNotification.showAlert(message, type, title);
};

window.customConfirm = function (message, callback, title = 'Confirm') {
    modalNotification.showConfirm(message, callback, title);
};
