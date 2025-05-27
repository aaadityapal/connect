/**
 * Site Overview Dashboard JavaScript
 * Handles functionality for the site overview cards
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize site overview cards
    initSiteOverviewCards();
    
    // Initialize tooltips
    initTooltips();
    
    // Fetch supervisors present today
    fetchSupervisorsPresent();
    
    // Set up refresh button functionality
    const refreshButton = document.getElementById('refreshSiteOverview');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;
            
            // Simulate data refresh (would be an API call in production)
            setTimeout(() => {
                updateSiteOverviewCards();
                fetchSupervisorsPresent(); // Also refresh supervisors data
                
                // Restore button state
                this.innerHTML = '<i class="fas fa-sync-alt"></i>';
                this.disabled = false;
                
                // Show notification
                showNotification('Site overview data refreshed', 'success');
            }, 1000);
        });
    }
    
    // Add click event listeners to cards
    document.querySelectorAll('.site-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Only navigate if the click is not on a supervisor avatar
            if (!e.target.closest('.supervisor-avatar')) {
                const cardType = this.getAttribute('data-card-type');
                
                // Special handling for supervisors card
                if (cardType === 'supervisors') {
                    openSupervisorModal();
                } else {
                    navigateToDetailPage(cardType);
                }
            }
        });
    });
    
    // Add click event listeners to supervisor avatars
    document.querySelectorAll('.supervisor-avatar').forEach(avatar => {
        avatar.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click
            
            // Don't navigate for the "more" button
            if (this.classList.contains('more-supervisors')) {
                openSupervisorModal();
                return;
            }
            
            const supervisorId = this.getAttribute('data-supervisor-id');
            if (supervisorId) {
                openSupervisorDetailModal(supervisorId);
            }
        });
    });
    
    // Set up "View All Supervisors" button in modal
    const viewAllSupervisorsBtn = document.getElementById('viewAllSupervisorsBtn');
    if (viewAllSupervisorsBtn) {
        viewAllSupervisorsBtn.addEventListener('click', function() {
            // This would navigate to the supervisors page
            // For now, just close the modal and show a notification
            $('#supervisorModal').modal('hide');
            showNotification('Navigating to all supervisors page is not available in demo mode', 'info');
        });
    }
});

/**
 * Initializes the site overview cards with animations
 */
function initSiteOverviewCards() {
    // Animate the values in cards
    document.querySelectorAll('.site-card-value').forEach(valueElement => {
        animateValue(valueElement);
    });
    
    // Animate progress bars
    document.querySelectorAll('.site-card-progress-bar').forEach(progressBar => {
        const targetWidth = progressBar.getAttribute('data-width');
        setTimeout(() => {
            progressBar.style.width = targetWidth + '%';
        }, 300);
    });
}

/**
 * Animates a numeric value with counting effect
 */
function animateValue(element) {
    const targetValue = parseInt(element.getAttribute('data-value'));
    const duration = 1500; // Animation duration in ms
    const startTime = performance.now();
    const startValue = 0;
    
    function updateValue(currentTime) {
        const elapsedTime = currentTime - startTime;
        
        if (elapsedTime < duration) {
            const progress = elapsedTime / duration;
            const currentValue = Math.floor(startValue + (targetValue - startValue) * progress);
            element.textContent = currentValue.toLocaleString();
            requestAnimationFrame(updateValue);
        } else {
            element.textContent = targetValue.toLocaleString();
        }
    }
    
    requestAnimationFrame(updateValue);
}

/**
 * Updates the site overview cards with new data
 * In a real application, this would fetch data from an API
 */
function updateSiteOverviewCards() {
    // This is a simulation - in a real app, you would fetch data from an API
    const newData = {
        safety: {
            value: Math.floor(Math.random() * 30) + 90, // 90-120 range
            trend: Math.floor(Math.random() * 10) - 5, // -5 to +5
            incidents: Math.floor(Math.random() * 3),
            lastUpdate: 'Just now'
        },
        productivity: {
            value: Math.floor(Math.random() * 20) + 80, // 80-100 range
            trend: Math.floor(Math.random() * 10) - 2, // -2 to +8
            tasks: Math.floor(Math.random() * 50) + 150,
            lastUpdate: 'Just now'
        },
        equipment: {
            value: Math.floor(Math.random() * 30) + 70, // 70-100 range
            trend: Math.floor(Math.random() * 10) - 3, // -3 to +7
            active: Math.floor(Math.random() * 10) + 20,
            lastUpdate: 'Just now'
        },
        workforce: {
            value: Math.floor(Math.random() * 20) + 80, // 80-100 range
            trend: Math.floor(Math.random() * 10) - 4, // -4 to +6
            present: Math.floor(Math.random() * 50) + 150,
            lastUpdate: 'Just now'
        }
    };
    
    // Update safety card
    updateCardData('safety', newData.safety);
    
    // Update productivity card
    updateCardData('productivity', newData.productivity);
    
    // Update equipment card
    updateCardData('equipment', newData.equipment);
    
    // Update workforce card
    updateCardData('workforce', newData.workforce);
}

/**
 * Updates a specific card with new data
 */
function updateCardData(cardType, data) {
    const card = document.querySelector(`.site-card[data-card-type="${cardType}"]`);
    if (!card) return;
    
    // Update value
    const valueElement = card.querySelector('.site-card-value');
    if (valueElement) {
        valueElement.setAttribute('data-value', data.value);
        animateValue(valueElement);
    }
    
    // Update trend
    const trendElement = card.querySelector('.site-card-trend');
    if (trendElement) {
        const trendIcon = trendElement.querySelector('i');
        const trendValue = trendElement.querySelector('span');
        
        if (data.trend > 0) {
            trendElement.className = 'site-card-trend trend-up';
            trendIcon.className = 'fas fa-arrow-up';
            trendValue.textContent = `+${data.trend}% from last week`;
        } else if (data.trend < 0) {
            trendElement.className = 'site-card-trend trend-down';
            trendIcon.className = 'fas fa-arrow-down';
            trendValue.textContent = `${data.trend}% from last week`;
        } else {
            trendElement.className = 'site-card-trend trend-neutral';
            trendIcon.className = 'fas fa-minus';
            trendValue.textContent = `No change from last week`;
        }
    }
    
    // Update progress bar
    const progressBar = card.querySelector('.site-card-progress-bar');
    if (progressBar) {
        progressBar.setAttribute('data-width', data.value);
        progressBar.style.width = data.value + '%';
    }
    
    // Update specific stats based on card type
    if (cardType === 'safety') {
        const incidentsElement = card.querySelector('[data-stat="incidents"] .site-card-stat-value');
        if (incidentsElement) incidentsElement.textContent = data.incidents;
    } else if (cardType === 'productivity') {
        const tasksElement = card.querySelector('[data-stat="tasks"] .site-card-stat-value');
        if (tasksElement) tasksElement.textContent = data.tasks;
    } else if (cardType === 'equipment') {
        const activeElement = card.querySelector('[data-stat="active"] .site-card-stat-value');
        if (activeElement) activeElement.textContent = data.active;
    } else if (cardType === 'workforce') {
        const presentElement = card.querySelector('[data-stat="present"] .site-card-stat-value');
        if (presentElement) presentElement.textContent = data.present;
    }
    
    // Update last update time
    const lastUpdateElement = card.querySelector('.site-card-footer');
    if (lastUpdateElement) {
        lastUpdateElement.textContent = `Last updated: ${data.lastUpdate}`;
    }
}

/**
 * Navigate to a detail page based on card type
 */
function navigateToDetailPage(cardType) {
    // This would navigate to different pages based on the card type
    // For now, we'll just log it
    console.log(`Navigating to ${cardType} details page`);
    
    // In a real application, you would use:
    // window.location.href = `${cardType}_details.php`;
    
    // For demo, show a notification
    showNotification(`Viewing ${cardType} details is not available in demo mode`, 'info');
}

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    // Check if Bootstrap's tooltip function exists
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    } else {
        console.warn('Bootstrap tooltip function not found. Custom tooltip implementation activated.');
        
        // Custom tooltip implementation if Bootstrap is not available
        document.querySelectorAll('[data-toggle="tooltip"]').forEach(element => {
            const title = element.getAttribute('title');
            if (!title) return;
            
            // Store the title and remove it to prevent default tooltip
            element.dataset.tooltipText = title;
            element.removeAttribute('title');
            
            // Mouse enter event - show tooltip
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip show';
                tooltip.innerHTML = `
                    <div class="tooltip-arrow"></div>
                    <div class="tooltip-inner">${this.dataset.tooltipText}</div>
                `;
                document.body.appendChild(tooltip);
                
                // Position the tooltip
                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
                tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
                
                // Store reference to the tooltip
                this.tooltip = tooltip;
            });
            
            // Mouse leave event - hide tooltip
            element.addEventListener('mouseleave', function() {
                if (this.tooltip) {
                    this.tooltip.remove();
                    this.tooltip = null;
                }
            });
        });
    }
}

/**
 * Fetches supervisors who are present today
 */
function fetchSupervisorsPresent() {
    // Show loading state in the card
    const supervisorCard = document.querySelector('.site-card[data-card-type="supervisors"]');
    if (supervisorCard) {
        const valueElement = supervisorCard.querySelector('.site-card-value');
        if (valueElement) {
            valueElement.textContent = '...';
        }
        
        const avatarsContainer = document.getElementById('supervisorAvatars');
        if (avatarsContainer) {
            avatarsContainer.innerHTML = `
                <div class="text-center w-100 py-2">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            `;
        }
    }

    // Fetch data from API
    fetch('api/get_supervisors_present.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                // Update the supervisors count in the card
                updateSupervisorCountInCard(data.present_supervisors, data.total_supervisors);
                
                // Update the UI with supervisors
                updateSupervisorsUI(data.supervisors);
            } else {
                console.error('Error fetching supervisors:', data.message);
                showNotification('Failed to load supervisors data', 'error');
                
                // If error, show dummy data
                updateSupervisorsUI([]);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to connect to the server', 'error');
            
            // If error, show dummy data
            updateSupervisorsUI([]);
        });
}

/**
 * Updates the supervisor count in the card
 */
function updateSupervisorCountInCard(presentCount, totalCount) {
    const supervisorCard = document.querySelector('.site-card[data-card-type="supervisors"]');
    if (!supervisorCard) return;
    
    // Update the main value
    const valueElement = supervisorCard.querySelector('.site-card-value');
    if (valueElement) {
        valueElement.setAttribute('data-value', presentCount);
        animateValue(valueElement);
    }
    
    // Update the stats
    const presentStat = supervisorCard.querySelector('[data-stat="present"] .site-card-stat-value');
    if (presentStat) {
        presentStat.textContent = presentCount;
    }
    
    const totalStat = supervisorCard.querySelector('.site-card-stat:not([data-stat="present"]) .site-card-stat-value');
    if (totalStat) {
        totalStat.textContent = totalCount;
    }
    
    // Update progress bar
    const progressBar = supervisorCard.querySelector('.site-card-progress-bar');
    if (progressBar) {
        const percentage = totalCount > 0 ? Math.round((presentCount / totalCount) * 100) : 0;
        progressBar.setAttribute('data-width', percentage);
        progressBar.style.width = percentage + '%';
    }
    
    // Update trend
    const trendElement = supervisorCard.querySelector('.site-card-trend');
    if (trendElement) {
        // This would normally come from the API comparing to previous day
        // For now, we'll use a random value
        const trendValue = Math.floor(Math.random() * 5) - 2; // -2 to +2
        
        const trendIcon = trendElement.querySelector('i');
        const trendSpan = trendElement.querySelector('span');
        
        if (trendValue > 0) {
            trendElement.className = 'site-card-trend trend-up';
            trendIcon.className = 'fas fa-arrow-up';
            trendSpan.textContent = `+${trendValue} from yesterday`;
        } else if (trendValue < 0) {
            trendElement.className = 'site-card-trend trend-down';
            trendIcon.className = 'fas fa-arrow-down';
            trendSpan.textContent = `${trendValue} from yesterday`;
        } else {
            trendElement.className = 'site-card-trend trend-neutral';
            trendIcon.className = 'fas fa-minus';
            trendSpan.textContent = `No change from yesterday`;
        }
    }
}

/**
 * Updates the UI with supervisors data
 */
function updateSupervisorsUI(supervisors) {
    const container = document.getElementById('supervisorAvatars');
    if (!container) return;
    
    // Clear existing content
    container.innerHTML = '';
    
    // If no supervisors, show a message
    if (!supervisors || supervisors.length === 0) {
        container.innerHTML = `
            <div class="text-center w-100 py-2">
                <span class="text-muted">No supervisors present</span>
            </div>
        `;
        return;
    }
    
    // Determine how many to show directly (max 4)
    const visibleCount = Math.min(4, supervisors.length);
    const hasMore = supervisors.length > 4;
    
    // Add visible supervisors
    for (let i = 0; i < visibleCount; i++) {
        // If this is the 4th item and we have more, show the +X button instead
        if (i === 3 && hasMore) {
            const moreCount = supervisors.length - 3;
            const moreElement = document.createElement('div');
            moreElement.className = 'supervisor-avatar more-supervisors';
            moreElement.setAttribute('data-toggle', 'tooltip');
            moreElement.setAttribute('title', `${moreCount} more supervisors`);
            moreElement.innerHTML = `<span>+${moreCount}</span>`;
            
            moreElement.addEventListener('click', function(e) {
                e.stopPropagation();
                openSupervisorModal();
            });
            
            container.appendChild(moreElement);
        } else {
            const supervisor = supervisors[i];
            const element = document.createElement('div');
            element.className = 'supervisor-avatar';
            element.setAttribute('data-supervisor-id', supervisor.id);
            element.setAttribute('data-toggle', 'tooltip');
            element.setAttribute('title', `${supervisor.name} - ${supervisor.role}`);
            
            element.innerHTML = `
                <img src="https://ui-avatars.com/api/?name=${supervisor.avatar}&background=${supervisor.color}&color=fff" alt="${supervisor.avatar}">
            `;
            
            element.addEventListener('click', function(e) {
                e.stopPropagation();
                openSupervisorDetailModal(supervisor.id);
            });
            
            container.appendChild(element);
        }
    }
    
    // Re-initialize tooltips for the new elements
    initTooltips();
}

/**
 * Shows a modal with all supervisors
 */
function showAllSupervisors(supervisors) {
    // This would show a modal with all supervisors
    // For now, we'll just show a notification
    showNotification('Viewing all supervisors is not available in demo mode', 'info');
}

/**
 * Navigates to a supervisor's profile
 */
function navigateToSupervisorProfile(supervisorId) {
    // This would navigate to the supervisor's profile
    // For now, we'll just log it and show a notification
    console.log(`Navigating to supervisor profile: ${supervisorId}`);
    showNotification(`Viewing supervisor profile is not available in demo mode`, 'info');
}

/**
 * Shows a notification message if the showNotification function exists
 * This assumes the main dashboard already has a notification function
 */
function showNotification(message, type) {
    // Check if the global showNotification function exists
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        // Fallback notification implementation
        console.log(`Notification (${type}): ${message}`);
        
        // Check if the notification container exists
        let notificationContainer = document.querySelector('.notification-container');
        
        // Create notification container if it doesn't exist
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.className = 'notification-container';
            document.body.appendChild(notificationContainer);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Set icon based on notification type
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        if (type === 'warning') icon = 'fa-exclamation-triangle';
        
        notification.innerHTML = `
            <i class="fas ${icon}"></i>
            <div class="notification-message">${message}</div>
        `;
        
        // Add to container
        notificationContainer.appendChild(notification);
        
        // Show notification
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

/**
 * Opens the supervisor modal with all present supervisors
 */
function openSupervisorModal() {
    // Get the modal
    const modal = $('#supervisorModal');
    
    // Show the modal
    if (modal.length) {
        modal.modal('show');
        
        // Load supervisor details
        loadSupervisorsForModal();
    } else {
        console.error('Supervisor modal not found');
    }
}

/**
 * Opens the supervisor detail modal for a specific supervisor
 */
function openSupervisorDetailModal(supervisorId) {
    // Get the modal
    const modal = $('#supervisorModal');
    
    // Show the modal
    if (modal.length) {
        modal.modal('show');
        
        // Load supervisor detail
        loadSupervisorDetail(supervisorId);
    } else {
        console.error('Supervisor modal not found');
    }
}

/**
 * Loads supervisor details for the modal
 */
function loadSupervisorsForModal() {
    const modalContent = document.getElementById('supervisorModalContent');
    if (!modalContent) return;
    
    // Show loading state with animation
    modalContent.innerHTML = `
        <div class="col-12">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading supervisor details...</p>
            </div>
        </div>
    `;
    
    // Update modal title with count
    const modalTitle = document.getElementById('supervisorModalLabel');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-users-cog mr-2"></i> Site Supervisors Present Today';
    }
    
    // Fetch data from API
    fetch('api/get_supervisors_present.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const supervisors = data.supervisors;
                
                // Update modal title with count
                if (modalTitle) {
                    modalTitle.innerHTML = `<i class="fas fa-users-cog mr-2"></i> Site Supervisors Present Today (${supervisors.length})`;
                }
                
                // Generate HTML for supervisors
                let html = '';
                
                // Add search and filter controls
                html += `
                    <div class="col-12 mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="input-group mb-2 mr-sm-2" style="max-width: 300px;">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><i class="fas fa-search"></i></div>
                                </div>
                                <input type="text" class="form-control" id="supervisorSearch" placeholder="Search supervisors...">
                            </div>
                            <div class="btn-group mb-2" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
                                <button type="button" class="btn btn-outline-primary" data-filter="active">Active</button>
                                <button type="button" class="btn btn-outline-primary" data-filter="break">On Break</button>
                                <button type="button" class="btn btn-outline-primary" data-filter="meeting">In Meeting</button>
                            </div>
                        </div>
                    </div>
                `;
                
                if (supervisors.length === 0) {
                    html += `
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> No supervisors are present today.
                            </div>
                        </div>
                    `;
                } else {
                    supervisors.forEach(supervisor => {
                        // Determine status badge
                        let statusBadge = '';
                        switch(supervisor.status) {
                            case 'active':
                                statusBadge = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Active</span>';
                                break;
                            case 'break':
                                statusBadge = '<span class="badge badge-warning text-white"><i class="fas fa-coffee mr-1"></i> On Break</span>';
                                break;
                            case 'meeting':
                                statusBadge = '<span class="badge badge-info"><i class="fas fa-users mr-1"></i> In Meeting</span>';
                                break;
                            case 'out':
                                statusBadge = '<span class="badge badge-secondary"><i class="fas fa-sign-out-alt mr-1"></i> Checked Out</span>';
                                break;
                            default:
                                statusBadge = '<span class="badge badge-secondary"><i class="fas fa-circle mr-1"></i> Unknown</span>';
                        }
                        
                        html += `
                            <div class="col-md-6 col-lg-4 mb-4 supervisor-item" data-status="${supervisor.status}">
                                <div class="card supervisor-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="supervisor-avatar mr-3" style="width: 50px; height: 50px; background-color: #${supervisor.color}; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: bold;">
                                                ${supervisor.avatar}
                                            </div>
                                            <div>
                                                <h5 class="card-title mb-0">${supervisor.name}</h5>
                                                <p class="card-subtitle text-muted mb-1">${supervisor.role}</p>
                                                ${statusBadge}
                                            </div>
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item px-0">
                                                <i class="fas fa-phone-alt text-primary mr-2"></i> ${supervisor.phone}
                                            </li>
                                            <li class="list-group-item px-0">
                                                <i class="fas fa-envelope text-primary mr-2"></i> ${supervisor.email}
                                            </li>
                                            <li class="list-group-item px-0">
                                                <i class="fas fa-map-marker-alt text-primary mr-2"></i> ${supervisor.site}
                                            </li>
                                            <li class="list-group-item px-0">
                                                <i class="fas fa-clock text-primary mr-2"></i> Check-in: ${supervisor.checkInTime}
                                            </li>
                                            <li class="list-group-item px-0">
                                                <i class="fas fa-location-arrow text-primary mr-2"></i> ${supervisor.location || 'On Site'}
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <div class="d-flex justify-content-between">
                                            <button class="btn btn-sm btn-outline-primary" onclick="openSupervisorDetailModal(${supervisor.id})">
                                                <i class="fas fa-user"></i> View Profile
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="contactSupervisor('${supervisor.phone}')">
                                                <i class="fas fa-phone"></i> Contact
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                // Update modal content
                modalContent.innerHTML = html;
                
                // Add event listeners for search and filter
                const searchInput = document.getElementById('supervisorSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        filterSupervisors(this.value);
                    });
                }
                
                // Add filter button functionality
                document.querySelectorAll('[data-filter]').forEach(button => {
                    button.addEventListener('click', function() {
                        // Remove active class from all buttons
                        document.querySelectorAll('[data-filter]').forEach(btn => {
                            btn.classList.remove('active');
                        });
                        
                        // Add active class to clicked button
                        this.classList.add('active');
                        
                        // Filter supervisors
                        const filterValue = this.getAttribute('data-filter');
                        filterSupervisorsByStatus(filterValue);
                    });
                });
            } else {
                // Show error message
                modalContent.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i> ${data.message || 'Failed to load supervisors data'}
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Show error message
            modalContent.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> Failed to connect to the server: ${error.message}
                    </div>
                </div>
            `;
        });
}

/**
 * Filter supervisors by search term
 */
function filterSupervisors(searchTerm) {
    const supervisorItems = document.querySelectorAll('.supervisor-item');
    const term = searchTerm.toLowerCase();
    
    supervisorItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(term)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

/**
 * Filter supervisors by status
 */
function filterSupervisorsByStatus(status) {
    const supervisorItems = document.querySelectorAll('.supervisor-item');
    
    supervisorItems.forEach(item => {
        if (status === 'all' || item.getAttribute('data-status') === status) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

/**
 * Loads details for a specific supervisor
 */
function loadSupervisorDetail(supervisorId) {
    const modalContent = document.getElementById('supervisorModalContent');
    if (!modalContent) return;
    
    // Show loading state with animation
    modalContent.innerHTML = `
        <div class="col-12">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading supervisor details...</p>
            </div>
        </div>
    `;
    
    // Update modal title
    const modalTitle = document.getElementById('supervisorModalLabel');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-user-hard-hat mr-2"></i> Supervisor Details';
    }
    
    // Fetch data from API
    fetch(`api/get_supervisor_detail.php?id=${supervisorId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const supervisor = data.supervisor;
                
                // Update modal title with supervisor name
                if (modalTitle) {
                    modalTitle.innerHTML = `<i class="fas fa-user-hard-hat mr-2"></i> ${supervisor.name}'s Profile`;
                }
                
                // Generate HTML for supervisor detail
                let html = `
                    <div class="col-md-4">
                        <div class="card supervisor-detail-card mb-4">
                            <div class="card-body text-center">
                                <div class="supervisor-avatar mx-auto mb-3" style="width: 100px; height: 100px; background-color: #${supervisor.color}; color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold;">
                                    ${supervisor.avatar}
                                </div>
                                <h4 class="card-title">${supervisor.name}</h4>
                                <p class="card-subtitle mb-2 text-muted">${supervisor.designation}</p>
                                <p class="badge badge-info mb-2">${supervisor.role}</p>
                                <div class="badge badge-success mb-3 px-3 py-2"><i class="fas fa-check-circle mr-1"></i> Present</div>
                                
                                <div class="text-left mt-4">
                                    <h6 class="font-weight-bold mb-3">Contact Information</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-phone-alt text-primary mr-2"></i> ${supervisor.phone}
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-envelope text-primary mr-2"></i> ${supervisor.email}
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-map-marker-alt text-primary mr-2"></i> ${supervisor.site}
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-clock text-primary mr-2"></i> Check-in: ${supervisor.checkInTime}
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-location-arrow text-primary mr-2"></i> ${supervisor.location}
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-home text-primary mr-2"></i> ${supervisor.address}
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-briefcase text-primary mr-2"></i> Experience: ${supervisor.experience}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-sm btn-outline-success" onclick="contactSupervisor('${supervisor.phone}')">
                                        <i class="fas fa-phone"></i> Call
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="sendMessage('${supervisor.email}')">
                                        <i class="fas fa-envelope"></i> Message
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Metrics -->
                        <div class="card supervisor-detail-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i> Performance</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Attendance</span>
                                        <span>${supervisor.performance.attendance}%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: ${supervisor.performance.attendance}%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Productivity</span>
                                        <span>${supervisor.performance.productivity}%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: ${supervisor.performance.productivity}%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Quality</span>
                                        <span>${supervisor.performance.quality}%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: ${supervisor.performance.quality}%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Safety</span>
                                        <span>${supervisor.performance.safety}%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: ${supervisor.performance.safety}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <!-- Projects -->
                        <div class="card supervisor-detail-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-project-diagram mr-2"></i> Projects</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Project</th>
                                                <th>Role</th>
                                                <th>Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                `;
                
                // Add projects
                supervisor.projects.forEach(project => {
                    // Determine progress color
                    let progressColor = 'bg-success';
                    if (project.progress < 50) progressColor = 'bg-danger';
                    else if (project.progress < 75) progressColor = 'bg-warning';
                    
                    html += `
                        <tr>
                            <td><strong>${project.name}</strong></td>
                            <td>${project.role}</td>
                            <td>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar ${progressColor}" role="progressbar" style="width: ${project.progress}%;" aria-valuenow="${project.progress}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted">${project.progress}%</small>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Team Members -->
                        <div class="card supervisor-detail-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-users mr-2"></i> Team Members</h5>
                            </div>
                            <div class="card-body">
                `;
                
                if (supervisor.team.length === 0) {
                    html += `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> No team members found reporting to this supervisor.
                        </div>
                    `;
                } else {
                    html += `
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    // Add team members
                    supervisor.team.forEach(member => {
                        const statusClass = member.status === 'present' ? 'success' : 'danger';
                        const statusText = member.status === 'present' ? 'Present' : 'Absent';
                        const statusIcon = member.status === 'present' ? 'check-circle' : 'times-circle';
                        
                        html += `
                            <tr>
                                <td><strong>${member.name}</strong></td>
                                <td>${member.role}</td>
                                <td><span class="badge badge-${statusClass}"><i class="fas fa-${statusIcon} mr-1"></i> ${statusText}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                }
                
                html += `
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="card supervisor-detail-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history mr-2"></i> Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                    `;
                    
                    // Add recent activities
                    supervisor.recentActivity.forEach(activity => {
                        let activityIcon = 'info-circle';
                        let activityColor = 'primary';
                        
                        switch(activity.type) {
                            case 'report':
                                activityIcon = 'file-alt';
                                activityColor = 'primary';
                                break;
                            case 'issue':
                                activityIcon = 'exclamation-triangle';
                                activityColor = 'warning';
                                break;
                            case 'task':
                                activityIcon = 'check-circle';
                                activityColor = 'success';
                                break;
                            case 'attendance':
                                activityIcon = 'clock';
                                activityColor = 'info';
                                break;
                        }
                        
                        html += `
                            <li class="list-group-item px-0 d-flex align-items-center">
                                <div class="mr-3" style="width: 40px; height: 40px; background-color: rgba(0,0,0,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-${activityIcon} text-${activityColor}"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold">${activity.text}</div>
                                    <small class="text-muted">${activity.time}</small>
                                </div>
                            </li>
                        `;
                    });
                    
                    html += `
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
                
                // Update modal content
                modalContent.innerHTML = html;
                
            } else {
                // Show error message
                modalContent.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i> ${data.message || 'Failed to load supervisor details'}
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Show error message
            modalContent.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> Failed to connect to the server: ${error.message}
                    </div>
                </div>
            `;
        });
}

/**
 * Contact a supervisor (simulated function)
 */
function contactSupervisor(phone) {
    console.log(`Contacting supervisor at ${phone}`);
    showNotification(`Calling ${phone}...`, 'info');
}

/**
 * Send a message to a supervisor (simulated function)
 */
function sendMessage(email) {
    console.log(`Sending message to ${email}`);
    showNotification(`Opening message composer for ${email}...`, 'info');
} 