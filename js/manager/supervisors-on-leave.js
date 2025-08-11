/**
 * Supervisors on Leave JavaScript
 * Handles functionality for the supervisors on leave card
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the supervisors on leave card
    fetchSupervisorsOnLeave();
    
    // Add click event listener to the supervisors on leave card
    const supervisorsLeaveCard = document.querySelector('.site-card[data-card-type="supervisors-leave"]');
    if (supervisorsLeaveCard) {
        supervisorsLeaveCard.addEventListener('click', function(e) {
            // Stop event propagation to prevent other handlers
            e.stopPropagation();
            e.preventDefault();
            
            // Don't navigate if clicking on a specific element
            if (!e.target.closest('.supervisor-avatar')) {
                openSupervisorsOnLeaveModal(e);
            }
            
            // Return false to prevent default behavior
            return false;
        }, true); // Use capture phase to ensure this handler runs first
    }
    
    // Add this card to the refresh functionality
    const refreshButton = document.getElementById('refreshSiteOverview');
    if (refreshButton) {
        const originalClickHandler = refreshButton.onclick;
        refreshButton.onclick = function(e) {
            // Call the original handler if it exists
            if (typeof originalClickHandler === 'function') {
                originalClickHandler.call(this, e);
            }
            
            // Also refresh supervisors on leave
            fetchSupervisorsOnLeave();
        };
    }
});

/**
 * Fetches supervisors who are currently on leave
 */
function fetchSupervisorsOnLeave() {
    // Show loading state in the card
    const supervisorLeaveCard = document.querySelector('.site-card[data-card-type="supervisors-leave"]');
    if (supervisorLeaveCard) {
        const valueElement = supervisorLeaveCard.querySelector('#supervisorLeaveCount');
        if (valueElement) {
            valueElement.textContent = '...';
        }
        
        const leaveListContainer = document.getElementById('supervisorLeaveList');
        if (leaveListContainer) {
            leaveListContainer.innerHTML = `
                <div class="text-center py-2">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <span class="ml-2">Loading data...</span>
                </div>
            `;
        }
    }

    // Fetch data from API
    fetch('ajax_handlers/fetch_supervisors_on_leave.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update the supervisors count in the card
                updateSupervisorLeaveCountInCard(data.count_on_leave, data.total_supervisors);
                
                // Update the UI with supervisors on leave
                updateSupervisorsOnLeaveUI(data.supervisors_on_leave);
                
                // Cache the data for modal use
                window.supervisorsOnLeaveData = data;
            } else {
                console.error('Error fetching supervisors on leave:', data.error);
                showNotification('Failed to load supervisors on leave data', 'error');
                
                // If error, show empty data
                updateSupervisorsOnLeaveUI([]);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to connect to the server', 'error');
            
            // If error, show empty data
            updateSupervisorsOnLeaveUI([]);
        });
}

/**
 * Updates the supervisor on leave count in the card
 */
function updateSupervisorLeaveCountInCard(onLeaveCount, totalCount) {
    const supervisorLeaveCard = document.querySelector('.site-card[data-card-type="supervisors-leave"]');
    if (!supervisorLeaveCard) return;
    
    // Update the main value
    const valueElement = document.getElementById('supervisorLeaveCount');
    if (valueElement) {
        valueElement.setAttribute('data-value', onLeaveCount);
        if (typeof animateValue === 'function') {
            animateValue(valueElement);
        } else {
            valueElement.textContent = onLeaveCount;
        }
    }
    
    // Update progress bar
    const progressBar = document.getElementById('supervisorLeaveProgressBar');
    if (progressBar) {
        const percentage = totalCount > 0 ? Math.round((onLeaveCount / totalCount) * 100) : 0;
        progressBar.setAttribute('data-width', percentage);
        progressBar.style.width = percentage + '%';
    }
}

/**
 * Updates the UI with supervisors on leave data
 */
function updateSupervisorsOnLeaveUI(supervisorsOnLeave) {
    const container = document.getElementById('supervisorLeaveList');
    if (!container) return;
    
    // Clear existing content
    container.innerHTML = '';
    
    // If no supervisors on leave, show a message
    if (!supervisorsOnLeave || supervisorsOnLeave.length === 0) {
        container.innerHTML = `
            <div class="text-center w-100 py-2">
                <span class="text-muted">No supervisors currently on leave</span>
            </div>
        `;
        return;
    }
    
    // Create a list to display supervisors on leave
    const list = document.createElement('ul');
    list.className = 'list-group list-group-flush';
    
    // Add each supervisor to the list
    supervisorsOnLeave.forEach(supervisor => {
        const listItem = document.createElement('li');
        listItem.className = 'list-group-item d-flex align-items-center px-0 py-2';
        
        // Create avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'supervisor-avatar mr-3';
        avatarDiv.style.width = '40px';
        avatarDiv.style.height = '40px';
        avatarDiv.style.backgroundColor = getRandomColor(supervisor.name);
        avatarDiv.style.color = 'white';
        avatarDiv.style.display = 'flex';
        avatarDiv.style.alignItems = 'center';
        avatarDiv.style.justifyContent = 'center';
        avatarDiv.style.fontWeight = 'bold';
        avatarDiv.style.borderRadius = '50%';
        
        const initials = getInitials(supervisor.name);
        avatarDiv.textContent = initials;
        
        // Create info div
        const infoDiv = document.createElement('div');
        infoDiv.className = 'flex-grow-1';
        
        // Add name
        const nameDiv = document.createElement('div');
        nameDiv.className = 'font-weight-bold';
        nameDiv.textContent = supervisor.name;
        infoDiv.appendChild(nameDiv);
        
        // Add Site Supervisor designation
        const designationDiv = document.createElement('div');
        designationDiv.className = 'small text-muted';
        designationDiv.textContent = 'Site Supervisor';
        infoDiv.appendChild(designationDiv);
        
        // Add leave details
        const detailsDiv = document.createElement('div');
        detailsDiv.className = 'small text-muted';
        
        // Format leave type and duration
        const leaveType = supervisor.leave_type.charAt(0).toUpperCase() + supervisor.leave_type.slice(1);
        detailsDiv.textContent = `${leaveType} Leave - ${supervisor.duration}`;
        
        infoDiv.appendChild(detailsDiv);
        
        // Assemble the list item
        listItem.appendChild(avatarDiv);
        listItem.appendChild(infoDiv);
        
        // Add to list
        list.appendChild(listItem);
    });
    
    // Add the list to the container
    container.appendChild(list);
}

/**
 * Opens the supervisors on leave modal
 */
function openSupervisorsOnLeaveModal(e) {
    // Stop event propagation to prevent other handlers
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    
    // Check if modal exists, create it if it doesn't
    let modal = $('#supervisorsOnLeaveModal');
    
    if (modal.length === 0) {
        // Create modal HTML and append to body
        const modalHTML = `
            <div class="modal fade" id="supervisorsOnLeaveModal" tabindex="-1" role="dialog" aria-labelledby="supervisorsOnLeaveModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="supervisorsOnLeaveModalLabel">
                                <i class="fas fa-user-clock mr-2"></i> Site Supervisors On Leave
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div id="supervisorsOnLeaveModalContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-3 text-muted">Loading supervisors on leave details...</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="viewAllLeavesBtn">View All Leave Requests</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHTML);
        modal = $('#supervisorsOnLeaveModal');
        
        // Add event listener for view all leaves button
        $('#viewAllLeavesBtn').on('click', function() {
            // Navigate to leave requests page
            window.location.href = 'leave_requests.php';
        });
    }
    
    // Show the modal
    modal.modal('show');
    
    // Load supervisors on leave data
    loadSupervisorsOnLeaveForModal();
    
    // Return false to prevent default behavior
    return false;
}

/**
 * Loads supervisors on leave details for the modal
 */
function loadSupervisorsOnLeaveForModal() {
    const modalContent = document.getElementById('supervisorsOnLeaveModalContent');
    if (!modalContent) return;
    
    // Show loading state with animation
    modalContent.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading supervisors on leave details...</p>
        </div>
    `;
    
    // Check if we have cached data
    if (window.supervisorsOnLeaveData) {
        displaySupervisorsOnLeaveData(window.supervisorsOnLeaveData);
        return;
    }
    
    // If no cached data, fetch from server
    fetch('ajax_handlers/fetch_supervisors_on_leave.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Cache the data
                window.supervisorsOnLeaveData = data;
                
                // Display the data in the modal
                displaySupervisorsOnLeaveData(data);
            } else {
                // Show error message
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> ${data.error || 'Failed to load supervisors on leave data'}
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary" onclick="retryLoadSupervisorsOnLeave()">
                            <i class="fas fa-sync-alt mr-2"></i> Retry
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Show error message
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i> Failed to connect to the server: ${error.message}
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary" onclick="retryLoadSupervisorsOnLeave()">
                        <i class="fas fa-sync-alt mr-2"></i> Retry
                    </button>
                </div>
            `;
        });
}

/**
 * Retries loading supervisors on leave data after an error
 */
function retryLoadSupervisorsOnLeave() {
    // Clear cached data
    window.supervisorsOnLeaveData = null;
    
    // Reload data
    loadSupervisorsOnLeaveForModal();
}

/**
 * Displays supervisors on leave data in the modal
 */
function displaySupervisorsOnLeaveData(data) {
    const modalContent = document.getElementById('supervisorsOnLeaveModalContent');
    if (!modalContent) return;
    
    const supervisorsOnLeave = data.supervisors_on_leave;
    
    // Update modal title with count
    const modalTitle = document.getElementById('supervisorsOnLeaveModalLabel');
    if (modalTitle) {
        modalTitle.innerHTML = `<i class="fas fa-user-clock mr-2"></i> Site Supervisors On Leave (${supervisorsOnLeave.length})`;
    }
    
    // If no supervisors on leave, show a message
    if (!supervisorsOnLeave || supervisorsOnLeave.length === 0) {
        modalContent.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i> No supervisors are currently on leave.
            </div>
        `;
        return;
    }
    
    // Generate HTML for the modal
    let html = `
        <div class="row">
            <div class="col-12 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div class="input-group mb-2 mr-sm-2" style="max-width: 300px;">
                        <div class="input-group-prepend">
                            <div class="input-group-text"><i class="fas fa-search"></i></div>
                        </div>
                        <input type="text" class="form-control" id="supervisorLeaveSearch" placeholder="Search supervisors...">
                    </div>
                </div>
            </div>
    `;
    
    // Create cards for each supervisor on leave
    supervisorsOnLeave.forEach(supervisor => {
        html += `
            <div class="col-md-6 col-lg-4 mb-4 supervisor-leave-item">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="supervisor-avatar mr-3" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden;">
                                <div style="width: 50px; height: 50px; background-color: ${getRandomColor(supervisor.name)}; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem;">
                                    ${getInitials(supervisor.name)}
                                </div>
                            </div>
                            <div>
                                <h5 class="card-title mb-0">${supervisor.name}</h5>
                                <p class="text-muted small mb-1">Site Supervisor</p>
                                <div class="badge badge-warning text-white mt-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> On Leave
                                </div>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0">
                                <i class="fas fa-tag text-primary mr-2"></i> ${supervisor.leave_type.charAt(0).toUpperCase() + supervisor.leave_type.slice(1)} Leave
                            </li>
                            <li class="list-group-item px-0">
                                <i class="fas fa-calendar-day text-primary mr-2"></i> From: ${formatDate(supervisor.start_date)}
                            </li>
                            <li class="list-group-item px-0">
                                <i class="fas fa-calendar-check text-primary mr-2"></i> To: ${formatDate(supervisor.end_date)}
                            </li>
                            <li class="list-group-item px-0">
                                <i class="fas fa-clock text-primary mr-2"></i> Duration: ${supervisor.duration}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += `</div>`;
    
    // Update modal content
    modalContent.innerHTML = html;
    
    // Add event listener for search
    const searchInput = document.getElementById('supervisorLeaveSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterSupervisorsOnLeave(this.value);
        });
    }
}

/**
 * Filter supervisors on leave by search term
 */
function filterSupervisorsOnLeave(searchTerm) {
    const supervisorItems = document.querySelectorAll('.supervisor-leave-item');
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
 * Get initials from a name
 */
function getInitials(name) {
    if (!name) return '??';
    
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    
    return parts[0].substring(0, 2).toUpperCase();
}

/**
 * Get a random color based on a string
 */
function getRandomColor(str) {
    if (!str) return '#6c757d'; // Default gray
    
    // Generate a hash from the string
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    
    // Convert hash to color
    const colors = [
        '#007bff', // primary
        '#28a745', // success
        '#17a2b8', // info
        '#6f42c1', // purple
        '#e83e8c', // pink
        '#fd7e14', // orange
        '#20c997', // teal
        '#6610f2'  // indigo
    ];
    
    return colors[Math.abs(hash) % colors.length];
}

/**
 * Format a date string
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    const date = new Date(dateString);
    
    return date.toLocaleDateString('en-US', options);
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
    }
}
