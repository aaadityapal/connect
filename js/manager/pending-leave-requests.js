/**
 * Pending Leave Requests JavaScript
 * Handles functionality for the pending leave requests card
 */
/**
 * Populate month/year dropdown with options
 * @param {string} targetId - The ID of the dropdown element to populate (default: 'leaveMonthYearFilter')
 */
function populateMonthYearDropdown(targetId = 'leaveMonthYearFilter') {
    const dropdown = document.getElementById(targetId);
    if (!dropdown) return;
    
    // Clear existing options except the first one
    while (dropdown.options.length > 1) {
        dropdown.remove(1);
    }
    
    // Get current date
    const currentDate = new Date();
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth();
    
    // Add options for the last 12 months
    for (let i = 0; i < 12; i++) {
        const date = new Date(currentYear, currentMonth - i, 1);
        const month = date.getMonth() + 1; // 1-12
        const year = date.getFullYear();
        
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        const option = document.createElement('option');
        option.value = `${month}-${year}`;
        option.textContent = `${monthNames[month - 1]} ${year}`;
        dropdown.appendChild(option);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Check if user has the Senior Manager (Site) role
    checkUserRole();
    
    // Populate month/year dropdown
    populateMonthYearDropdown();
    
    // Add click event listener to the leave requests card
    const leaveCard = document.querySelector('.site-card[data-card-type="pending-leave"]');
    if (leaveCard) {
        leaveCard.addEventListener('click', function(e) {
            // Stop event propagation to prevent other handlers
            e.stopPropagation();
            e.preventDefault();
            
            // Don't navigate if clicking on the status filter dropdown or other interactive elements
            if (!e.target.closest('#leaveStatusFilter') && 
                !e.target.closest('#leaveMonthYearFilter') && 
                !e.target.closest('.leave-item-action')) {
                openPendingLeaveModal(e);
            }
            
            // Return false to prevent default behavior
            return false;
        }, true); // Use capture phase to ensure this handler runs first
    }
    
    // Add event listener to the status filter dropdown
    const statusFilter = document.getElementById('leaveStatusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            // Fetch leave requests with the selected status
            fetchLeaveRequests(this.value);
        });
    }
    
    // Add event listener to the month/year filter dropdown
    const monthYearFilter = document.getElementById('leaveMonthYearFilter');
    if (monthYearFilter) {
        monthYearFilter.addEventListener('change', function() {
            const statusFilter = document.getElementById('leaveStatusFilter');
            const status = statusFilter ? statusFilter.value : 'pending';
            fetchLeaveRequests(status);
        });
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
            
            // Also refresh leave requests
            fetchLeaveRequests();
        };
    }
});

/**
 * Checks if the user has the Senior Manager (Site) role
 * If yes, shows the pending leave requests card and hides travel expenses card
 */
function checkUserRole() {
    const pendingLeaveCardContainer = document.getElementById('pendingLeaveCardContainer');
    const travelExpensesCardContainer = document.getElementById('travelExpensesCardContainer');
    
    if (pendingLeaveCardContainer) {
        // Fetch user role from server
        fetch('ajax_handlers/fetch_pending_leave_requests.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response was not ok: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // User is Senior Manager (Site)
                    // Show the pending leave card
                    pendingLeaveCardContainer.style.display = 'block';
                    fetchLeaveRequests('pending');
                    
                    // Hide the travel expenses card
                    if (travelExpensesCardContainer) {
                        travelExpensesCardContainer.style.display = 'none';
                    }
                } else {
                    // Hide the card if user doesn't have permission
                    pendingLeaveCardContainer.style.display = 'none';
                    
                    // Make sure travel expenses card is visible for other roles
                    if (travelExpensesCardContainer) {
                        travelExpensesCardContainer.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking user role:', error);
                // Hide the card on error
                pendingLeaveCardContainer.style.display = 'none';
                
                // Make sure travel expenses card is visible on error
                if (travelExpensesCardContainer) {
                    travelExpensesCardContainer.style.display = 'block';
                }
            });
    }
}

/**
 * Fetches leave requests from the server based on status and date filters
 * @param {string} status - The status to filter by ('pending', 'approved', 'rejected', or 'all')
 */
function fetchLeaveRequests(status = 'pending') {
    // Get current status from dropdown if not provided
    if (!status) {
        const statusFilter = document.getElementById('leaveStatusFilter');
        status = statusFilter ? statusFilter.value : 'pending';
    }
    
    // Get month/year filter value
    const monthYearFilter = document.getElementById('leaveMonthYearFilter');
    const monthYearValue = monthYearFilter ? monthYearFilter.value : '';
    
    // Parse month and year if a value is selected
    let month = '';
    let year = '';
    if (monthYearValue) {
        const parts = monthYearValue.split('-');
        if (parts.length === 2) {
            month = parts[0];
            year = parts[1];
        }
    }
    
    // Show loading state in the card
    const leaveCard = document.querySelector('.site-card[data-card-type="pending-leave"]');
    if (leaveCard) {
        const valueElement = leaveCard.querySelector('#pendingLeaveCount');
        if (valueElement) {
            valueElement.textContent = '...';
        }
        
        const leaveListContainer = document.getElementById('pendingLeaveList');
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

    // Update trend text based on status
    updateTrendText(status, monthYearValue);
    
    // Update card color based on status
    updateCardColor(status);

    // Build URL with all filters
    let url = `ajax_handlers/fetch_pending_leave_requests.php?status=${status}`;
    if (month && year) {
        url += `&month=${month}&year=${year}`;
    }

    // Fetch data from API with all parameters
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update the leave count in the card
                updateLeaveCountInCard(data.total_requests);
                
                // Update the UI with leave requests
                updateLeaveRequestsUI(data.leave_requests);
                
                // Cache the data for modal use
                window.leaveRequestsData = data;
            } else {
                console.error('Error fetching leave requests:', data.error);
                showNotification('Failed to load leave requests data', 'error');
                
                // If error, show empty data
                updateLeaveRequestsUI([]);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to connect to the server', 'error');
            
            // If error, show empty data
            updateLeaveRequestsUI([]);
        });
}

/**
 * Updates the trend text based on status and date filter
 * @param {string} status - The current status filter
 * @param {string} monthYear - The month-year filter value (e.g., "5-2023")
 */
function updateTrendText(status, monthYear = '') {
    const trendSpan = document.querySelector('.site-card[data-card-type="pending-leave"] .site-card-trend span');
    if (!trendSpan) return;
    
    // Base text based on status
    let text = '';
    switch (status) {
        case 'pending':
            text = 'Awaiting approval';
            break;
        case 'approved':
            text = 'Already approved';
            break;
        case 'rejected':
            text = 'Previously rejected';
            break;
        case 'all':
            text = 'All leave requests';
            break;
        default:
            text = 'Leave requests';
    }
    
    // Add month/year information if provided
    if (monthYear) {
        const parts = monthYear.split('-');
        if (parts.length === 2) {
            const month = parseInt(parts[0]);
            const year = parts[1];
            
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            if (month >= 1 && month <= 12) {
                text += ` (${monthNames[month - 1]} ${year})`;
            }
        }
    }
    
    trendSpan.textContent = text;
}

/**
 * Updates the card color based on status
 * @param {string} status - The current status filter
 */
function updateCardColor(status) {
    const progressBar = document.getElementById('pendingLeaveProgressBar');
    const icon = document.querySelector('.site-card[data-card-type="pending-leave"] .site-card-icon');
    
    if (progressBar && icon) {
        let colorClass = '';
        switch (status) {
            case 'pending':
                colorClass = 'bg-warning';
                break;
            case 'approved':
                colorClass = 'bg-success';
                break;
            case 'rejected':
                colorClass = 'bg-danger';
                break;
            case 'all':
                colorClass = 'bg-info';
                break;
            default:
                colorClass = 'bg-primary';
        }
        
        // Remove all bg-* classes
        icon.classList.remove('bg-primary', 'bg-success', 'bg-danger', 'bg-warning', 'bg-info');
        progressBar.classList.remove('bg-primary', 'bg-success', 'bg-danger', 'bg-warning', 'bg-info');
        
        // Add the new color class
        icon.classList.add(colorClass);
        progressBar.classList.add(colorClass);
    }
}

/**
 * Updates the leave count in the card
 */
function updateLeaveCountInCard(count) {
    const leaveCard = document.querySelector('.site-card[data-card-type="pending-leave"]');
    if (!leaveCard) return;
    
    // Update the main value
    const valueElement = document.getElementById('pendingLeaveCount');
    if (valueElement) {
        valueElement.setAttribute('data-value', count);
        if (typeof animateValue === 'function') {
            animateValue(valueElement);
        } else {
            valueElement.textContent = count;
        }
    }
    
    // Update progress bar
    const progressBar = document.getElementById('pendingLeaveProgressBar');
    if (progressBar) {
        // Use a percentage based on some reasonable maximum (e.g., 20 requests would be 100%)
        const maxRequests = 20;
        const percentage = Math.min(100, Math.round((count / maxRequests) * 100));
        progressBar.setAttribute('data-width', percentage);
        progressBar.style.width = percentage + '%';
    }
}

/**
 * Updates the UI with leave requests
 */
function updateLeaveRequestsUI(leaveRequests) {
    const container = document.getElementById('pendingLeaveList');
    if (!container) return;
    
    // Clear existing content
    container.innerHTML = '';
    
    // Get current status
    const statusFilter = document.getElementById('leaveStatusFilter');
    const currentStatus = statusFilter ? statusFilter.value : 'pending';
    
    // If no leave requests, show a message
    if (!leaveRequests || leaveRequests.length === 0) {
        container.innerHTML = `
            <div class="text-center w-100 py-2">
                <span class="text-muted">No ${currentStatus === 'all' ? '' : currentStatus + ' '}leave requests</span>
            </div>
        `;
        return;
    }
    
    // Create a list to display leave requests
    const list = document.createElement('ul');
    list.className = 'list-group list-group-flush pending-leave-list';
    list.style.marginBottom = '0';
    
    // Add each leave request to the list
    leaveRequests.forEach(request => {
                            const listItem = document.createElement('li');
                    listItem.className = 'list-group-item d-flex flex-wrap align-items-center px-0 py-1';
        listItem.setAttribute('data-request-id', request.id);
        
        // Create avatar with initials
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'leave-avatar mr-2';
                            avatarDiv.style.width = '28px';
                    avatarDiv.style.height = '28px';
                    avatarDiv.style.minWidth = '28px';
        avatarDiv.style.backgroundColor = getRandomColor(request.name);
        avatarDiv.style.color = 'white';
        avatarDiv.style.display = 'flex';
        avatarDiv.style.alignItems = 'center';
        avatarDiv.style.justifyContent = 'center';
        avatarDiv.style.fontWeight = 'bold';
        avatarDiv.style.borderRadius = '50%';
        avatarDiv.style.fontSize = '0.7rem';
        
        const initials = getInitials(request.name);
        avatarDiv.textContent = initials;
        
        // Create info div
        const infoDiv = document.createElement('div');
        infoDiv.className = 'flex-grow-1';
        
        // Add name
        const nameDiv = document.createElement('div');
        nameDiv.className = 'font-weight-bold small';
        nameDiv.style.fontSize = '0.8rem';
        nameDiv.style.lineHeight = '1.2';
        nameDiv.style.textOverflow = 'ellipsis';
        nameDiv.style.overflow = 'hidden';
        nameDiv.style.whiteSpace = 'nowrap';
        nameDiv.style.maxWidth = '100%';
        nameDiv.textContent = request.name;
        infoDiv.appendChild(nameDiv);
        
        // Add leave details
        const detailsDiv = document.createElement('div');
        detailsDiv.className = 'small text-muted';
        detailsDiv.style.fontSize = '0.7rem';
        detailsDiv.style.lineHeight = '1.1';
        detailsDiv.style.textOverflow = 'ellipsis';
        detailsDiv.style.overflow = 'hidden';
        detailsDiv.style.whiteSpace = 'nowrap';
        detailsDiv.style.maxWidth = '100%';
        
        // Format leave type and duration
        let leaveTypeDisplay = request.leave_type_name || (request.leave_type.charAt(0).toUpperCase() + request.leave_type.slice(1));
        
        // Add half day type if applicable
        if (request.has_half_day_info) {
            leaveTypeDisplay += ` (${request.half_day_type === 'first_half' ? 'Morning' : 'Afternoon'})`;
        }
        
        // Add compensation info if applicable
        if (request.is_compensate_leave && request.comp_off_source_date) {
            leaveTypeDisplay += ` (Comp: ${request.comp_off_source_date})`;
        }
        
        detailsDiv.textContent = `${leaveTypeDisplay} - ${request.duration}`;
        
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
 * Opens the pending leave requests modal
 */
function openPendingLeaveModal(e) {
    // Stop event propagation to prevent other handlers
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    
    // Check if modal exists, create it if it doesn't
    let modal = $('#pendingLeaveModal');
    
    if (modal.length === 0) {
        // Create modal HTML and append to body
        const modalHTML = `
            <div class="modal fade" id="pendingLeaveModal" tabindex="-1" role="dialog" aria-labelledby="pendingLeaveModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light border-bottom-0">
                            <h5 class="modal-title font-weight-bold" id="pendingLeaveModalLabel">
                                <i class="fas fa-calendar-alt mr-2 text-primary"></i> <span id="modalTitleStatus">Pending</span> Leave Requests
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body px-4">
                            <div id="pendingLeaveModalContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-3 text-muted">Loading pending leave requests...</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 flex-column flex-md-row">
                            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center mb-3 mb-md-0 w-100">
                                <div class="d-flex flex-column flex-sm-row mb-3 mb-md-0 w-100 w-md-auto">
                                    <div class="mr-0 mr-sm-3 mb-2 mb-sm-0">
                                        <label for="modalMonthYearFilter" class="mb-1 d-block d-md-inline">Date:</label>
                                        <select id="modalMonthYearFilter" class="form-control form-control-sm" style="min-width: 140px;">
                                            <option value="">All Dates</option>
                                            <!-- Month options will be added by JavaScript -->
                                        </select>
                                    </div>
                                    <div>
                                        <label for="modalLeaveStatusFilter" class="mb-1 d-block d-md-inline">Status:</label>
                                        <select id="modalLeaveStatusFilter" class="form-control form-control-sm" style="min-width: 120px;">
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="all">All</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex mt-3 mt-md-0 ml-md-auto">
                                    <button type="button" class="btn btn-light mr-2" data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i> Close
                                    </button>
                                    <button type="button" class="btn btn-primary" id="viewAllPendingLeavesBtn">
                                        <i class="fas fa-list-ul mr-1"></i> View All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHTML);
        modal = $('#pendingLeaveModal');
        
        // Add event listener for view all leaves button
        $('#viewAllPendingLeavesBtn').on('click', function() {
            // Navigate to leave requests page
            window.location.href = 'leave_requests.php';
        });
        
        // Add event listener for modal status filter
        $('#modalLeaveStatusFilter').on('change', function() {
            const selectedStatus = this.value;
            
            // Reload data with selected status
            loadPendingLeaveForModal(selectedStatus);
            
            // Update card filter to match
            const cardStatusFilter = document.getElementById('leaveStatusFilter');
            if (cardStatusFilter) {
                cardStatusFilter.value = selectedStatus;
                fetchLeaveRequests(selectedStatus);
            }
        });
        
        // Add event listener for modal month/year filter
        $('#modalMonthYearFilter').on('change', function() {
            const selectedMonthYear = this.value;
            const modalStatusFilter = document.getElementById('modalLeaveStatusFilter');
            const selectedStatus = modalStatusFilter ? modalStatusFilter.value : 'pending';
            
            // Reload data with selected filters
            loadPendingLeaveForModal(selectedStatus);
            
            // Update card filter to match
            const cardMonthYearFilter = document.getElementById('leaveMonthYearFilter');
            if (cardMonthYearFilter) {
                cardMonthYearFilter.value = selectedMonthYear;
                fetchLeaveRequests(selectedStatus);
            }
        });
        
        // Add custom styles for the leave requests container
        const style = document.createElement('style');
        style.textContent = `
            /* Utility */
            .min-w-0 { min-width: 0; }
            .status-badge-container {
                position: absolute;
                top: 12px;
                right: 12px;
            }
            @media (max-width: 767.98px) {
                .status-badge-container { display: none !important; }
            }
            .leave-requests-container {
                max-height: 60vh;
                overflow-y: auto;
                padding-right: 5px;
            }
            .leave-requests-container::-webkit-scrollbar {
                width: 6px;
            }
            .leave-requests-container::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            .leave-requests-container::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 10px;
            }
            .leave-requests-container::-webkit-scrollbar-thumb:hover {
                background: #a8a8a8;
            }
            .leave-request-card {
                transition: all 0.2s ease;
            }
            .leave-request-card:hover {
                transform: translateY(-3px);
            }
            .leave-request-card .card {
                border-radius: 8px;
                transition: all 0.3s ease;
            }
            .leave-request-card:hover .card {
                box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
            }
            
            /* Responsive styles */
            @media (max-width: 767.98px) {
                .leave-requests-container {
                    max-height: 70vh;
                }
                .modal-body {
                    padding: 1rem;
                }
                .card-body {
                    padding: 0.75rem;
                }
                .badge {
                    font-size: 0.7rem;
                }
                h6 {
                    font-size: 0.9rem;
                }
                .modal-footer {
                    padding: 0.75rem 1rem;
                }
            }
            
            /* iPhone SE specific styles */
            @media (max-width: 375px) {
                .leave-requests-container {
                    max-height: 65vh;
                }
                .modal-body {
                    padding: 0.75rem;
                }
                .card-body {
                    padding: 0.5rem;
                }
                .badge {
                    font-size: 0.65rem;
                    padding: 0.25rem 0.5rem !important;
                }
                h6 {
                    font-size: 0.85rem;
                }
                small {
                    font-size: 0.7rem;
                }
                .leave-avatar {
                    width: 35px !important;
                    height: 35px !important;
                    min-width: 35px !important;
                }
                .btn-sm {
                    padding: 0.2rem 0.4rem;
                    font-size: 0.7rem;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Show the modal
    modal.modal('show');
    
    // Populate modal month/year dropdown
    populateMonthYearDropdown('modalMonthYearFilter');
    
    // Sync modal filters with card filters
    const cardStatusFilter = document.getElementById('leaveStatusFilter');
    const modalStatusFilter = document.getElementById('modalLeaveStatusFilter');
    const cardMonthYearFilter = document.getElementById('leaveMonthYearFilter');
    const modalMonthYearFilter = document.getElementById('modalMonthYearFilter');
    
    // Sync status filter
    if (cardStatusFilter && modalStatusFilter) {
        modalStatusFilter.value = cardStatusFilter.value;
    }
    
    // Sync month/year filter
    if (cardMonthYearFilter && modalMonthYearFilter) {
        modalMonthYearFilter.value = cardMonthYearFilter.value;
    }
    
    // Get current filters
    const currentStatus = cardStatusFilter ? cardStatusFilter.value : 'pending';
    
    // Load leave requests data with current filters
    loadPendingLeaveForModal(currentStatus);
    
    // Return false to prevent default behavior
    return false;
}

/**
 * Loads leave requests details for the modal
 * @param {string} status - Optional status filter, if not provided will use the modal dropdown value
 */
function loadPendingLeaveForModal(status) {
    const modalContent = document.getElementById('pendingLeaveModalContent');
    if (!modalContent) return;
    
    // Get status from parameter or modal dropdown
    if (!status) {
        const modalStatusFilter = document.getElementById('modalLeaveStatusFilter');
        status = modalStatusFilter ? modalStatusFilter.value : 'pending';
    }
    
    // Get month/year filter
    const monthYearFilter = document.getElementById('modalMonthYearFilter');
    const monthYearValue = monthYearFilter ? monthYearFilter.value : '';
    
    // Parse month and year if a value is selected
    let month = '';
    let year = '';
    let dateText = '';
    if (monthYearValue) {
        const parts = monthYearValue.split('-');
        if (parts.length === 2) {
            month = parts[0];
            year = parts[1];
            
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            if (month >= 1 && month <= 12) {
                dateText = ` for ${monthNames[month - 1]} ${year}`;
            }
        }
    }
    
    // Update modal title based on status
    const modalTitleStatus = document.getElementById('modalTitleStatus');
    if (modalTitleStatus) {
        if (status === 'all') {
            modalTitleStatus.textContent = 'All';
        } else {
            modalTitleStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        }
    }
    
    // Show loading state with animation
    modalContent.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading ${status === 'all' ? 'all' : status + ' '}leave requests${dateText}...</p>
        </div>
    `;
    
    // Clear cached data to ensure we get fresh data with the current status
    window.leaveRequestsData = null;
    
    // Build URL with all filters
    let url = `ajax_handlers/fetch_pending_leave_requests.php?status=${status}`;
    if (month && year) {
        url += `&month=${month}&year=${year}`;
    }
    
    // Fetch from server with the selected filters
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Cache the data
                window.leaveRequestsData = data;
                
                // Display the data in the modal
                displayPendingLeaveData(data);
            } else {
                // Show error message
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> ${data.error || 'Failed to load pending leave requests data'}
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary" onclick="retryLoadPendingLeave()">
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
                    <button class="btn btn-primary" onclick="retryLoadPendingLeave()">
                        <i class="fas fa-sync-alt mr-2"></i> Retry
                    </button>
                </div>
            `;
        });
}

/**
 * Retries loading leave requests data after an error
 */
function retryLoadPendingLeave() {
    // Clear cached data
    window.leaveRequestsData = null;
    
    // Reload data
    loadPendingLeaveForModal();
}

/**
 * Displays pending leave requests data in the modal
 */
function displayPendingLeaveData(data) {
    const modalContent = document.getElementById('pendingLeaveModalContent');
    if (!modalContent) return;
    
    const leaveRequests = data.leave_requests;
    const status = data.status || 'pending';
    
    // Update modal title with count and status
    const modalTitle = document.getElementById('pendingLeaveModalLabel');
    if (modalTitle) {
        let statusText = '';
        switch (status) {
            case 'pending':
                statusText = 'Pending';
                break;
            case 'approved':
                statusText = 'Approved';
                break;
            case 'rejected':
                statusText = 'Rejected';
                break;
            case 'all':
                statusText = 'All';
                break;
            default:
                statusText = 'Pending';
        }
        modalTitle.innerHTML = `<i class="fas fa-clock mr-2"></i> ${statusText} Leave Requests (${data.total_requests})`;
    }
    
    // If no leave requests, show a message
    if (!leaveRequests || leaveRequests.length === 0) {
        modalContent.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i> No ${status === 'all' ? '' : status + ' '}leave requests found.
            </div>
        `;
        return;
    }
    
    // Generate HTML for the modal
    let html = `
        <div class="row">
            <div class="col-12 mb-3">
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" class="form-control" id="pendingLeaveSearch" placeholder="Search requests...">
                    <div class="input-group-append">
                        <span class="input-group-text bg-white border-left-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="leave-requests-container">
    `;
    
    // Add cards for each leave request
    leaveRequests.forEach(request => {
        // Use the color code from the database or fall back to a default
        const typeColor = request.color_code || '#607D8B';
        
        html += `
            <div class="leave-request-card mb-3" data-request-id="${request.id}">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3 position-relative">
                        <div class="d-flex align-items-start mb-2 pr-5 pr-md-0">
                            <div class="leave-avatar mr-3" style="min-width: 40px; width: 40px; height: 40px; background-color: ${getRandomColor(request.name)}; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 50%;">
                                ${getInitials(request.name)}
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="mb-0 font-weight-bold text-truncate">${request.name}</h6>
                                <small class="text-muted">${request.created_at}</small>
                                <div class="d-block d-md-none mt-2">
                                    ${getStatusBadge(request.status)}
                                    <span class="badge ml-2" style="background-color: ${typeColor}; color: white; padding: 5px 10px; border-radius: 30px;">${request.leave_type_name}${request.has_half_day_info ? ' (' + (request.half_day_type === 'first_half' ? 'Morning' : 'Afternoon') + ')' : ''}${request.is_compensate_leave && request.comp_off_source_date ? ' (Comp)' : ''}</span>
                                </div>
                            </div>
                            <div class="status-badge-container d-none d-md-flex align-items-center ml-2">
                                ${getStatusBadge(request.status)}
                                <span class="badge ml-2" style="background-color: ${typeColor}; color: white; padding: 5px 10px; border-radius: 30px;">${request.leave_type_name}${request.has_half_day_info ? ' (' + (request.half_day_type === 'first_half' ? 'Morning' : 'Afternoon') + ')' : ''}${request.is_compensate_leave && request.comp_off_source_date ? ' (Comp)' : ''}</span>
                            </div>
                        </div>
                        
                        <div class="leave-details mb-2 pl-2" style="border-left: 3px solid ${typeColor}; padding-left: 10px; overflow-wrap: break-word; word-wrap: break-word;">
                            <div class="row">
                                <div class="col-12 col-sm-6 mb-1">
                                    <strong>Duration:</strong> ${request.duration}
                                </div>
                                <div class="col-12 col-sm-6 mb-1">
                                    <strong>Date Range:</strong> ${request.formatted_start_date} ${request.start_date !== request.end_date ? ' to ' + request.formatted_end_date : ''}
                                </div>
                                ${request.is_short_leave ? `
                                <div class="col-12 col-sm-6 mb-1">
                                    <strong>Time:</strong> ${request.time_from || 'N/A'} - ${request.time_to || 'N/A'}
                                </div>` : ''}
                                ${request.has_half_day_info ? `
                                <div class="col-12 col-sm-6 mb-1">
                                    <strong>Half Day Type:</strong> ${request.half_day_type === 'first_half' ? 'First Half (Morning)' : 'Second Half (Afternoon)'}
                                </div>` : ''}
                                ${request.is_compensate_leave && request.comp_off_source_date ? `
                                <div class="col-12 col-sm-6 mb-1">
                                    <strong>Compensating for:</strong> ${request.comp_off_source_date}
                                </div>` : ''}
                                <div class="col-12 mb-1">
                                    <strong>Reason:</strong> <span title="${request.reason || 'No reason provided'}">${request.reason || 'No reason provided'}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${request.status === 'pending' ? `
                        <div class="d-flex flex-wrap justify-content-end mt-3">
                            <button type="button" class="btn btn-outline-success btn-sm mr-2 mb-2 mb-sm-0 approve-leave" data-request-id="${request.id}">
                                <i class="fas fa-check mr-1"></i> <span class="d-none d-sm-inline">Approve</span><span class="d-inline d-sm-none">OK</span>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm reject-leave" data-request-id="${request.id}">
                                <i class="fas fa-times mr-1"></i> <span class="d-none d-sm-inline">Reject</span><span class="d-inline d-sm-none">No</span>
                            </button>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += `
                </div>
            </div>
        </div>
    `;
    
    // Update modal content
    modalContent.innerHTML = html;
    
    // Add event listeners for search
    const searchInput = document.getElementById('pendingLeaveSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterPendingLeave(this.value);
        });
    }
    
    // Add event listeners for approve/reject buttons
    document.querySelectorAll('.approve-leave').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const requestId = this.getAttribute('data-request-id');
            approveLeaveRequest(requestId);
        });
    });
    
    document.querySelectorAll('.reject-leave').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const requestId = this.getAttribute('data-request-id');
            rejectLeaveRequest(requestId);
        });
    });
}

/**
 * Filter pending leave requests by search term
 */
function filterPendingLeave(searchTerm) {
    const cards = document.querySelectorAll('.leave-request-card');
    const term = searchTerm.toLowerCase();
    
    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(term)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

/**
 * Approves a leave request
 */
function approveLeaveRequest(requestId) {
    openLeaveActionReasonModal('approve', requestId);
}

/**
 * Rejects a leave request
 */
function rejectLeaveRequest(requestId) {
    openLeaveActionReasonModal('reject', requestId);
}

/**
 * Opens a small modal asking for approval/rejection reason and submits to server
 */
function openLeaveActionReasonModal(action, requestId) {
    // Ensure valid action
    const normalizedAction = action === 'approve' ? 'approve' : 'reject';
    const actionTitle = normalizedAction === 'approve' ? 'Approve' : 'Reject';
    const placeholder = normalizedAction === 'approve' ? 'Reason for approval (optional, but recommended)...' : 'Reason for rejection (required)...';

    // Create modal if not exists
    let modal = document.getElementById('leaveActionReasonModal');
    if (!modal) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="leaveActionReasonModal" tabindex="-1" role="dialog" aria-labelledby="leaveActionReasonLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h6 class="modal-title" id="leaveActionReasonLabel"></h6>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body pt-3 pb-2">
                            <div class="form-group mb-2">
                                <label for="leaveActionReasonInput" class="small mb-1">Reason</label>
                                <textarea id="leaveActionReasonInput" class="form-control" rows="3" placeholder=""></textarea>
                                <small class="form-text text-muted">This note will be saved with the action.</small>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" id="leaveActionReasonSubmitBtn">
                                <i class="fas fa-paper-plane mr-1"></i> Submit
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(wrapper.firstElementChild);
        modal = document.getElementById('leaveActionReasonModal');
    }

    // Set dynamic content
    document.getElementById('leaveActionReasonLabel').textContent = `${actionTitle} Leave Request`;
    const input = document.getElementById('leaveActionReasonInput');
    input.value = '';
    input.placeholder = placeholder;

    // Bind submit
    const submitBtn = document.getElementById('leaveActionReasonSubmitBtn');
    const handler = function() {
        const reason = (input.value || '').trim();
        if (normalizedAction === 'reject' && reason.length === 0) {
            showNotification('Please provide a reason for rejection.', 'error');
            return;
        }
        submitLeaveActionWithReason(normalizedAction, requestId, reason)
            .then(success => {
                if (success) {
                    // Close modal
                    $('#leaveActionReasonModal').modal('hide');
                    // Refresh current filters
                    const statusFilter = document.getElementById('leaveStatusFilter');
                    const currentStatus = statusFilter ? statusFilter.value : 'pending';
                    fetchLeaveRequests(currentStatus);
                    // Refresh modal list preserving modal filters
                    const modalStatusFilter = document.getElementById('modalLeaveStatusFilter');
                    const modalStatus = modalStatusFilter ? modalStatusFilter.value : currentStatus;
                    loadPendingLeaveForModal(modalStatus);
                }
            });
    };
    // Remove previous listener then add
    submitBtn.replaceWith(submitBtn.cloneNode(true));
    const freshSubmitBtn = document.getElementById('leaveActionReasonSubmitBtn');
    freshSubmitBtn.addEventListener('click', handler, { once: true });

    // Show modal
    $('#leaveActionReasonModal').modal('show');
}

/**
 * Sends approval/rejection with reason to server
 */
function submitLeaveActionWithReason(action, requestId, reason) {
    const formData = new URLSearchParams();
    formData.append('id', requestId);
    formData.append('action', action);
    formData.append('reason', reason);

    return fetch('ajax_handlers/update_leave_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                showNotification(data.message || 'Leave updated successfully', 'success');
                return true;
            } else {
                showNotification(data.error || 'Failed to update leave request', 'error');
                return false;
            }
        })
        .catch(err => {
            console.error('Update error:', err);
            showNotification('Network error while updating leave request', 'error');
            return false;
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
 * Get a status badge based on leave status
 * @param {string} status - The leave status (pending, approved, rejected)
 * @returns {string} HTML for the status badge
 */
function getStatusBadge(status) {
    let badgeClass = '';
    let icon = '';
    let text = '';
    
    switch (status) {
        case 'pending':
            badgeClass = 'badge-warning';
            icon = 'clock';
            text = 'Pending';
            break;
        case 'approved':
            badgeClass = 'badge-success';
            icon = 'check';
            text = 'Approved';
            break;
        case 'rejected':
            badgeClass = 'badge-danger';
            icon = 'times';
            text = 'Rejected';
            break;
        default:
            badgeClass = 'badge-secondary';
            icon = 'question';
            text = status.charAt(0).toUpperCase() + status.slice(1);
    }
    
    return `<span class="badge ${badgeClass}" style="padding: 5px 8px; border-radius: 30px; font-size: 0.75rem;">
                <i class="fas fa-${icon} mr-1"></i> <span class="d-none d-sm-inline">${text}</span><span class="d-inline d-sm-none">${text.charAt(0)}</span>
            </span>`;
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
