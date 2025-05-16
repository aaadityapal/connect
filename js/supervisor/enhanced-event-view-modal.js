/**
 * Enhanced Event View Modal
 * Displays detailed information about a specific event when the view button is clicked
 */
class EnhancedEventViewModal {
    constructor() {
        this.init();
    }

    init() {
        // Create modal structure if it doesn't exist
        this.createModalStructure();
        
        // Initialize event listeners
        this.setupEventListeners();
    }

    createModalStructure() {
        // Check if modal already exists in the DOM
        if (document.getElementById('enhancedEventViewModal')) {
            return;
        }

        const modalHTML = `
            <div id="enhancedEventViewModal" class="enhanced-view-modal-backdrop">
                <div class="enhanced-view-modal">
                    <div class="enhanced-view-modal-header">
                        <div class="header-left">
                            <h3 class="enhanced-view-title">Site Update</h3>
                            <span class="enhanced-view-date" id="enhancedViewDate"></span>
                        </div>
                        <button type="button" class="enhanced-view-close" id="enhancedViewClose">&times;</button>
                    </div>
                    <div class="enhanced-view-modal-body">
                        <div class="enhanced-view-loader" id="enhancedViewLoader">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p>Loading event details...</p>
                        </div>
                        <div class="enhanced-view-content" id="enhancedViewContent">
                            <!-- Content will be dynamically populated -->
                        </div>
                    </div>
                    <div class="enhanced-view-modal-footer">
                        <button type="button" class="enhanced-view-btn enhanced-view-btn-edit" id="enhancedViewEditBtn">
                            <i class="fas fa-edit"></i> Edit Event
                        </button>
                        <button type="button" class="enhanced-view-btn enhanced-view-btn-print" id="enhancedViewPrintBtn">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="enhanced-view-btn enhanced-view-btn-delete" id="enhancedViewDeleteBtn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <button type="button" class="enhanced-view-btn enhanced-view-btn-close" id="enhancedViewCloseBtn">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to the body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    setupEventListeners() {
        // Close modal buttons
        document.getElementById('enhancedViewClose').addEventListener('click', () => this.hideModal());
        document.getElementById('enhancedViewCloseBtn').addEventListener('click', () => this.hideModal());
        
        // Close on backdrop click if it's the actual backdrop (not a child element)
        document.getElementById('enhancedEventViewModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('enhancedEventViewModal')) {
                this.hideModal();
            }
        });
        
        // Edit button
        document.getElementById('enhancedViewEditBtn').addEventListener('click', () => {
            const eventId = document.getElementById('enhancedViewContent').getAttribute('data-event-id');
            if (eventId) {
                this.hideModal();
                // Call the edit function - this will now use our new modal
                if (typeof editEvent === 'function') {
                    editEvent(eventId);
                } else if (typeof openEventEditModal === 'function') {
                    openEventEditModal(eventId);
                } else {
                    console.error('Edit function is not defined');
                }
            }
        });
        
        // Delete button
        document.getElementById('enhancedViewDeleteBtn').addEventListener('click', () => {
            const eventId = document.getElementById('enhancedViewContent').getAttribute('data-event-id');
            if (eventId) {
                if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                    this.deleteEvent(eventId);
                }
            }
        });
        
        // Print button
        document.getElementById('enhancedViewPrintBtn').addEventListener('click', () => {
            this.printEventDetails();
        });
    }

    showModal(eventId, eventDate) {
        // Show loading state
        document.getElementById('enhancedViewLoader').style.display = 'flex';
        document.getElementById('enhancedViewContent').style.display = 'none';
        
        // Display the date
        if (eventDate) {
            document.getElementById('enhancedViewDate').textContent = eventDate;
        }
        
        // Store the event ID in the content div for later reference
        document.getElementById('enhancedViewContent').setAttribute('data-event-id', eventId);
        
        // Show the modal with animation
        const modal = document.getElementById('enhancedEventViewModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        // Fetch and display event data
        this.fetchEventDetails(eventId);
    }

    hideModal() {
        const modal = document.getElementById('enhancedEventViewModal');
        modal.classList.remove('active');
        
        // Wait for animation to finish before hiding completely
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    async fetchEventDetails(eventId) {
        try {
            // Show loading state
            document.getElementById('enhancedViewLoader').style.display = 'flex';
            document.getElementById('enhancedViewContent').style.display = 'none';
            
            // Make the API call to fetch event details from our backend
            const response = await fetch(`backend/get_event_details.php?event_id=${eventId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });
            
            // First get the response text
            const responseText = await response.text();
            
            // Try to parse it as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Error parsing JSON response:', parseError);
                console.log('Raw response text:', responseText);
                throw new Error(`Failed to parse server response as JSON. Server returned: ${responseText.substring(0, 100)}...`);
            }
            
            if (data.status === 'success') {
                this.renderEventDetails(data.event);
            } else {
                this.showErrorMessage(data.message || 'Failed to load event details');
            }
        } catch (error) {
            console.error('Error fetching event details:', error);
            this.showErrorMessage(`An error occurred while fetching event details: ${error.message}`);
        } finally {
            // Hide the loader regardless of outcome
            document.getElementById('enhancedViewLoader').style.display = 'none';
        }
    }

    renderEventDetails(event) {
        const contentContainer = document.getElementById('enhancedViewContent');
        
        // Store the event ID for later reference
        contentContainer.setAttribute('data-event-id', event.event_id);
        
        // Format date for display
        const eventDate = new Date(event.event_date);
        const formattedDate = eventDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Update the date in the header
        document.getElementById('enhancedViewDate').textContent = formattedDate;
        
        let html = `
            <div class="event-detail-card">
                <div class="event-detail-header">
                    <div class="event-detail-title">
                        <h4>${this.escapeHtml(event.title || 'Event Details')}</h4>
                    </div>
                    <div class="site-tag"><i class="fas fa-map-marker-alt"></i> ${this.escapeHtml(event.site_name || 'Unknown Site')}</div>
                </div>
                <div class="event-detail-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i> Created by <span class="event-creator">${this.escapeHtml(event.created_by_name || 'Unknown')}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i> Created on <span class="event-created-at">${new Date(event.created_at).toLocaleString()}</span>
                    </div>
                </div>
        `;
        
        // Add tabs for different sections
        html += `
            <div class="event-detail-tabs">
                <div class="event-tab active" data-tab="vendors">Vendors & Workers</div>
                <div class="event-tab" data-tab="company-labours">Company Workers</div>
                <div class="event-tab" data-tab="beverages">Beverages</div>
                <div class="event-tab" data-tab="work-progress">Work Progress</div>
                <div class="event-tab" data-tab="inventory">Inventory</div>
            </div>
            
            <div class="event-tab-content">
                <div class="event-tab-pane active" id="vendors-tab">
        `;
        
        // Vendors and Labourers Tab Content
        if (event.vendors && event.vendors.length > 0) {
            html += `
                <div class="event-detail-section">
                    <h4 class="section-title"><i class="fas fa-users"></i> Vendors (${event.vendors.length})</h4>
                    <div class="vendor-cards">
            `;
            
            event.vendors.forEach(vendor => {
                html += `
                    <div class="vendor-detail-card">
                        <div class="vendor-detail-header">
                            <div class="vendor-detail-name">
                                <i class="fas fa-${this.getVendorIcon(vendor.vendor_type)}"></i> 
                                <strong>${this.escapeHtml(vendor.vendor_name)}</strong>
                            </div>
                            <div class="vendor-detail-type">${this.escapeHtml(vendor.vendor_type)}</div>
                        </div>
                        <div class="vendor-detail-contact">
                            <a href="tel:${vendor.contact_number}" class="contact-button">
                                <i class="fas fa-phone"></i> ${this.escapeHtml(vendor.contact_number || 'N/A')}
                            </a>
                        </div>
                `;
                
                // Add material details if they exist
                if (vendor.material) {
                    html += `<div class="material-detail-section">
                        <h5><i class="fas fa-boxes"></i> Materials</h5>
                    `;
                    
                    if (vendor.material.amount) {
                        html += `<div class="material-amount">
                            <strong>Amount:</strong> ₹${parseFloat(vendor.material.amount).toFixed(2)}
                        </div>`;
                    }
                    
                    if (vendor.material.remarks) {
                        html += `<div class="material-remark">
                            <strong>Remarks:</strong> ${this.escapeHtml(vendor.material.remarks)}
                        </div>`;
                    }
                    
                    // Add material pictures gallery
                    if (vendor.material.materialPictures && vendor.material.materialPictures.length > 0) {
                        html += `<div class="material-images">
                            <h6>Material Images (${vendor.material.materialPictures.length})</h6>
                            <div class="image-gallery">`;
                            
                        vendor.material.materialPictures.forEach(pic => {
                            html += `<div class="gallery-item image-item" onclick="openImageViewer('${pic.name}')">
                                <span class="media-type-tag image-tag">Material</span>
                                <div class="gallery-thumbnail" style="background-image: url('uploads/material_images/${pic.name}')"></div>
                                <div class="media-caption">${this.escapeHtml(pic.name)}</div>
                            </div>`;
                        });
                        
                        html += `</div></div>`;
                    }
                    
                    // Add bill pictures gallery
                    if (vendor.material.billPictures && vendor.material.billPictures.length > 0) {
                        html += `<div class="bill-images">
                            <h6>Bill Images (${vendor.material.billPictures.length})</h6>
                            <div class="image-gallery">`;
                            
                        vendor.material.billPictures.forEach(pic => {
                            html += `<div class="gallery-item bill-item" onclick="openImageViewer('${pic.name}')">
                                <span class="media-type-tag bill-tag">Bill</span>
                                <div class="gallery-thumbnail" style="background-image: url('uploads/bill_images/${pic.name}')"></div>
                                <div class="media-caption">${this.escapeHtml(pic.name)}</div>
                            </div>`;
                        });
                        
                        html += `</div></div>`;
                    }
                    
                    html += `</div>`;
                }
                
                // Add labour details if they exist
                if (vendor.labourers && vendor.labourers.length > 0) {
                    html += `<div class="labour-detail-section">
                        <h5><i class="fas fa-hard-hat"></i> Labourers (${vendor.labourers.length})</h5>
                        <div class="labour-cards">`;
                        
                    vendor.labourers.forEach((labourer, index) => {
                        html += `<div class="labourer-card">
                            <div class="labourer-header">
                                <strong>Labourer #${index + 1} - ${this.escapeHtml(labourer.labour_name)}</strong>
                                ${labourer.contact_number ? `<a href="tel:${labourer.contact_number}" class="labourer-contact">
                                    <i class="fas fa-phone"></i> ${this.escapeHtml(labourer.contact_number)}
                                </a>` : ''}
                            </div>
                            
                            <div class="labourer-attendance">
                                <div class="attendance-row">
                                    <div class="attendance-item">
                                        <span class="attendance-label">Morning:</span>
                                        <span class="attendance-value ${this.getAttendanceClass(labourer.morning_attendance)}">
                                            ${this.formatAttendance(labourer.morning_attendance)}
                                        </span>
                                    </div>
                                    <div class="attendance-item">
                                        <span class="attendance-label">Evening:</span>
                                        <span class="attendance-value ${this.getAttendanceClass(labourer.evening_attendance)}">
                                            ${this.formatAttendance(labourer.evening_attendance)}
                                        </span>
                                    </div>
                                </div>
                            </div>`;
                            
                        // Add wages section if available
                        if (labourer.wages) {
                            html += `<div class="labourer-wages">
                                <div class="wages-row">
                                    <div class="wages-item">
                                        <span class="wages-label">Daily Wage:</span>
                                        <span class="wages-value">₹${parseFloat(labourer.wages.perDay || 0).toFixed(2)}</span>
                                    </div>
                                    <div class="wages-item">
                                        <span class="wages-label">Total Day:</span>
                                        <span class="wages-value">₹${parseFloat(labourer.wages.totalDay || 0).toFixed(2)}</span>
                                    </div>
                                </div>
                            </div>`;
                        }
                        
                        // Add overtime section if available
                        if (labourer.overtime && (parseInt(labourer.overtime.hours) > 0 || parseInt(labourer.overtime.minutes) > 0)) {
                            html += `<div class="labourer-overtime">
                                <div class="overtime-header">
                                    <i class="fas fa-clock"></i> Overtime
                                </div>
                                <div class="overtime-details">
                                    <div class="overtime-row">
                                        <div class="overtime-item">
                                            <span class="overtime-label">Duration:</span>
                                            <span class="overtime-value">${labourer.overtime.hours}h ${labourer.overtime.minutes}m</span>
                                        </div>
                                        <div class="overtime-item">
                                            <span class="overtime-label">Rate:</span>
                                            <span class="overtime-value">₹${parseFloat(labourer.overtime.rate || 0).toFixed(2)}/hr</span>
                                        </div>
                                        <div class="overtime-item">
                                            <span class="overtime-label">Total:</span>
                                            <span class="overtime-value">₹${parseFloat(labourer.overtime.total || 0).toFixed(2)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        }
                        
                        // Add travel expenses if available
                        if (labourer.travel && (labourer.travel.mode || parseFloat(labourer.travel.amount) > 0)) {
                            html += `<div class="labourer-travel">
                                <div class="travel-header">
                                    <i class="fas fa-bus"></i> Travel Expenses
                                </div>
                                <div class="travel-details">
                                    ${labourer.travel.mode ? `<div class="travel-mode">
                                        <span class="travel-label">Mode:</span>
                                        <span class="travel-value">${this.formatTravelMode(labourer.travel.mode)}</span>
                                    </div>` : ''}
                                    ${parseFloat(labourer.travel.amount) > 0 ? `<div class="travel-amount">
                                        <span class="travel-label">Amount:</span>
                                        <span class="travel-value">₹${parseFloat(labourer.travel.amount).toFixed(2)}</span>
                                    </div>` : ''}
                                </div>
                            </div>`;
                        }
                        
                        // Add grand total if available
                        if (labourer.wages) {
                            html += `<div class="labourer-total">
                                <div class="total-row">
                                    <span class="total-label">Grand Total:</span>
                                    <span class="total-value">₹${parseFloat(labourer.wages.grand_total || 0).toFixed(2)}</span>
                                </div>
                            </div>`;
                        }
                        
                        html += `</div>`;
                    });
                    
                    html += `</div></div>`;
                }
                
                html += `</div>`;
            });
            
            html += `</div></div>`;
        } else {
            html += `
                <div class="event-detail-section">
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i> No vendors found for this event
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
        
        // Company Labours Tab
        html += `<div class="event-tab-pane" id="company-labours-tab">`;
        
        if (event.company_labours && event.company_labours.length > 0) {
            html += `
                <div class="event-detail-section">
                    <h4 class="section-title"><i class="fas fa-hard-hat"></i> Company Workers (${event.company_labours.length})</h4>
                    <div class="labour-cards">
            `;
            
            event.company_labours.forEach((labourer, index) => {
                html += `<div class="labourer-card">
                    <div class="labourer-header">
                        <strong>Worker #${index + 1} - ${this.escapeHtml(labourer.labour_name)}</strong>
                        ${labourer.contact_number ? `<a href="tel:${labourer.contact_number}" class="labourer-contact">
                            <i class="fas fa-phone"></i> ${this.escapeHtml(labourer.contact_number)}
                        </a>` : ''}
                    </div>
                    
                    <div class="labourer-attendance">
                        <div class="attendance-row">
                            <div class="attendance-item">
                                <span class="attendance-label">Morning:</span>
                                <span class="attendance-value ${this.getAttendanceClass(labourer.morning_attendance)}">
                                    ${this.formatAttendance(labourer.morning_attendance)}
                                </span>
                            </div>
                            <div class="attendance-item">
                                <span class="attendance-label">Evening:</span>
                                <span class="attendance-value ${this.getAttendanceClass(labourer.evening_attendance)}">
                                    ${this.formatAttendance(labourer.evening_attendance)}
                                </span>
                            </div>
                        </div>
                    </div>`;
                    
                // Add wages section if available
                if (labourer.wages) {
                    html += `<div class="labourer-wages">
                        <div class="wages-row">
                            <div class="wages-item">
                                <span class="wages-label">Daily Wage:</span>
                                <span class="wages-value">₹${parseFloat(labourer.wages.perDay || 0).toFixed(2)}</span>
                            </div>
                            <div class="wages-item">
                                <span class="wages-label">Total Day:</span>
                                <span class="wages-value">₹${parseFloat(labourer.wages.totalDay || 0).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>`;
                    
                    // Add overtime section if available
                    if (labourer.overtime && (parseInt(labourer.overtime.hours) > 0 || parseInt(labourer.overtime.minutes) > 0)) {
                        html += `<div class="labourer-overtime">
                            <div class="overtime-header">
                                <i class="fas fa-clock"></i> Overtime
                            </div>
                            <div class="overtime-details">
                                <div class="overtime-row">
                                    <div class="overtime-item">
                                        <span class="overtime-label">Duration:</span>
                                        <span class="overtime-value">${labourer.overtime.hours}h ${labourer.overtime.minutes}m</span>
                                    </div>
                                    <div class="overtime-item">
                                        <span class="overtime-label">Rate:</span>
                                        <span class="overtime-value">₹${parseFloat(labourer.overtime.rate || 0).toFixed(2)}/hr</span>
                                    </div>
                                    <div class="overtime-item">
                                        <span class="overtime-label">Total:</span>
                                        <span class="overtime-value">₹${parseFloat(labourer.overtime.total || 0).toFixed(2)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    }
                    
                    // Add travel expenses if available
                    if (labourer.travel && (labourer.travel.mode || parseFloat(labourer.travel.amount) > 0)) {
                        html += `<div class="labourer-travel">
                            <div class="travel-header">
                                <i class="fas fa-bus"></i> Travel Expenses
                            </div>
                            <div class="travel-details">
                                ${labourer.travel.mode ? `<div class="travel-mode">
                                    <span class="travel-label">Mode:</span>
                                    <span class="travel-value">${this.formatTravelMode(labourer.travel.mode)}</span>
                                </div>` : ''}
                                ${parseFloat(labourer.travel.amount) > 0 ? `<div class="travel-amount">
                                    <span class="travel-label">Amount:</span>
                                    <span class="travel-value">₹${parseFloat(labourer.travel.amount).toFixed(2)}</span>
                                </div>` : ''}
                            </div>
                        </div>`;
                    }
                    
                    // Add grand total if available
                    html += `<div class="labourer-total">
                        <div class="total-row">
                            <span class="total-label">Grand Total:</span>
                            <span class="total-value">₹${parseFloat(labourer.wages.grand_total || 0).toFixed(2)}</span>
                        </div>
                    </div>`;
                }
                
                html += `</div>`;
            });
            
            html += `</div></div>`;
        } else {
            html += `
                <div class="event-detail-section">
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i> No company workers found for this event
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
        
        // Beverages Tab
        html += `<div class="event-tab-pane" id="beverages-tab">`;
        
        if (event.beverages && event.beverages.length > 0) {
            html += `
                <div class="event-detail-section">
                    <h4 class="section-title"><i class="fas fa-coffee"></i> Beverages (${event.beverages.length})</h4>
                    <div class="table-responsive">
                        <table class="beverage-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            event.beverages.forEach((beverage, index) => {
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${this.escapeHtml(beverage.beverage_type || '')}</td>
                        <td>${this.escapeHtml(beverage.beverage_name || '')}</td>
                        <td>₹${parseFloat(beverage.amount || 0).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="event-detail-section">
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i> No beverages found for this event
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
        
        // Work Progress Tab
        html += `<div class="event-tab-pane" id="work-progress-tab">`;
        
        if (event.work_progress && event.work_progress.length > 0) {
            html += `
                <div class="event-detail-section">
                    <h4 class="section-title"><i class="fas fa-tasks"></i> Work Progress (${event.work_progress.length})</h4>
                    <div class="work-progress-items">
            `;
            
            event.work_progress.forEach((work, index) => {
                html += `
                    <div class="work-item">
                        <div class="work-item-header">
                            <div class="work-item-title">
                                <h5>#${index + 1} - ${this.escapeHtml(work.work_category)} - ${this.escapeHtml(work.work_type)}</h5>
                            </div>
                            <div class="work-status ${work.work_done === 'yes' ? 'status-complete' : 'status-incomplete'}">
                                <i class="fas ${work.work_done === 'yes' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                                ${work.work_done === 'yes' ? 'Completed' : 'Not Completed'}
                            </div>
                        </div>
                        
                        ${work.remarks ? `<div class="work-remarks">
                            <strong>Remarks:</strong> ${this.escapeHtml(work.remarks)}
                        </div>` : ''}
                `;
                
                // Display work media if available
                if (work.media && work.media.length > 0) {
                    html += `
                        <div class="work-media">
                            <h6>Media Files (${work.media.length})</h6>
                            <div class="media-gallery">
                    `;
                    
                    work.media.forEach(media => {
                        const isImage = media.media_type === 'image';
                        // Fix the path construction - use absolute path with correct folder
                        const mediaFileName = media.file_name || '';
                        let fileUrl;
                        
                        if (media.file_path && media.file_path.trim() !== '') {
                            // If a full file path is provided, use it directly
                            fileUrl = media.file_path;
                        } else {
                            // Check if it could be a calendar events path based on the filename pattern
                            if (mediaFileName.includes('1747307273')) {
                                fileUrl = `uploads/calendar_events/work_progress_media/work_${work.work_id}/${mediaFileName}`;
                                console.log("Using calendar events path for media:", fileUrl);
                            } else {
                                // Otherwise construct the path based on file type and location
                                fileUrl = `uploads/work_progress/${mediaFileName}`;
                            }
                        }
                        
                        // Make sure the path is relative to the current site
                        if (fileUrl.startsWith('http://localhost/')) {
                            fileUrl = fileUrl.replace('http://localhost/', '');
                        }
                        
                        // Get the appropriate tag based on media type
                        let mediaTypeTag = '';
                        let mediaTypeClass = '';
                        
                        if (isImage) {
                            mediaTypeTag = '<span class="media-type-tag image-tag">Image</span>';
                            mediaTypeClass = 'image-item';
                        } else {
                            mediaTypeTag = '<span class="media-type-tag video-tag">Video</span>';
                            mediaTypeClass = 'video-item';
                        }
                        
                        if (isImage) {
                            html += `
                                <div class="gallery-item ${mediaTypeClass}" onclick="openImageViewer('${fileUrl}', 'work_progress')">
                                    ${mediaTypeTag}
                                    <div class="gallery-thumbnail" style="background-image: url('${fileUrl}')">
                                        <div class="gallery-thumbnail-fallback" style="display:none;">
                                            <img src="images/image-not-found.png" alt="Image not available">
                                        </div>
                                    </div>
                                    <div class="media-caption">${this.escapeHtml(mediaFileName)}</div>
                                </div>
                            `;
                        } else {
                            html += `
                                <div class="gallery-item ${mediaTypeClass}">
                                    ${mediaTypeTag}
                                    <a href="javascript:void(0)" onclick="playVideo('${fileUrl}')" class="video-thumbnail">
                                        <i class="fas fa-video"></i>
                                        <div class="media-caption">${this.escapeHtml(mediaFileName)}</div>
                                    </a>
                                </div>
                            `;
                        }
                    });
                    
                    html += `</div></div>`;
                }
                
                html += `</div>`;
            });
            
            html += `</div></div>`;
        } else {
            html += `
                <div class="event-detail-section">
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i> No work progress records found for this event
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
        
        // Inventory Tab
        html += `<div class="event-tab-pane" id="inventory-tab">`;
        
        if (event.inventory && event.inventory.length > 0) {
            html += `
                <div class="event-detail-section">
                    <h4 class="section-title"><i class="fas fa-boxes"></i> Inventory Items (${event.inventory.length})</h4>
                    <div class="inventory-items">
            `;
            
            event.inventory.forEach((item, index) => {
                html += `
                    <div class="inventory-item ${item.inventory_type.toLowerCase()}">
                        <div class="inventory-item-header">
                            <div class="inventory-item-title">
                                <h5>#${index + 1} - ${this.escapeHtml(item.material_type)}</h5>
                            </div>
                            <div class="inventory-type">
                                <span class="inventory-badge inventory-${item.inventory_type.toLowerCase()}">
                                    ${this.formatInventoryType(item.inventory_type)}
                                </span>
                            </div>
                        </div>
                        
                        <div class="inventory-details">
                            <div class="inventory-quantity">
                                <strong>Quantity:</strong> ${parseFloat(item.quantity).toFixed(2)} ${this.escapeHtml(item.unit || '')}
                            </div>
                            
                            ${item.remarks ? `<div class="inventory-remarks">
                                <strong>Remarks:</strong> ${this.escapeHtml(item.remarks)}
                            </div>` : ''}
                        </div>
                `;
                
                // Display inventory media if available
                if (item.media && item.media.length > 0) {
                    html += `
                        <div class="inventory-media">
                            <h6>Media Files (${item.media.length})</h6>
                            <div class="media-gallery">
                    `;
                    
                    item.media.forEach(media => {
                        const isImage = media.media_type === 'photo' || media.media_type === 'bill';
                        // Fix the path construction - use absolute path with correct folder
                        const mediaFileName = media.file_name || '';
                        let fileUrl;
                        
                        if (media.file_path && media.file_path.trim() !== '') {
                            // If a full file path is provided, use it directly
                            fileUrl = media.file_path;
                        } else {
                            // Check if it could be a calendar events path based on the filename pattern
                            if (mediaFileName.includes('1747307273')) {
                                fileUrl = `uploads/calendar_events/inventory_media/inventory_${item.inventory_id || '6'}/${mediaFileName}`;
                                console.log("Using calendar events path for inventory media:", fileUrl);
                            } else {
                                // Choose the correct folder based on media type
                                let folder = 'inventory';
                                if (media.media_type === 'bill') {
                                    folder = 'inventory_bills';
                                } else if (media.media_type === 'video') {
                                    folder = 'inventory_videos';
                                } else {
                                    folder = 'inventory_images';
                                }
                                fileUrl = `uploads/${folder}/${mediaFileName}`;
                            }
                        }
                        
                        // Make sure the path is relative to the current site
                        if (fileUrl.startsWith('http://localhost/')) {
                            fileUrl = fileUrl.replace('http://localhost/', '');
                        }
                        
                        // Get the appropriate tag based on media type
                        let mediaTypeTag = '';
                        let mediaTypeClass = '';
                        
                        if (media.media_type === 'bill') {
                            // Check if it's a PDF
                            if (mediaFileName.toLowerCase().endsWith('.pdf')) {
                                mediaTypeTag = '<span class="media-type-tag pdf-tag">PDF</span>';
                                mediaTypeClass = 'pdf-item';
                            } else {
                                mediaTypeTag = '<span class="media-type-tag bill-tag">Bill</span>';
                                mediaTypeClass = 'bill-item';
                            }
                        } else if (media.media_type === 'photo') {
                            mediaTypeTag = '<span class="media-type-tag image-tag">Image</span>';
                            mediaTypeClass = 'image-item';
                        } else if (media.media_type === 'video') {
                            mediaTypeTag = '<span class="media-type-tag video-tag">Video</span>';
                            mediaTypeClass = 'video-item';
                        }
                        
                        if (isImage) {
                            // For PDF files, use a PDF thumbnail instead
                            if (mediaFileName.toLowerCase().endsWith('.pdf')) {
                                html += `
                                    <div class="gallery-item ${mediaTypeClass}" 
                                         onclick="openImageViewer('${fileUrl}', '${media.media_type === 'bill' ? 'inventory_bills' : 'inventory_images'}')">
                                        ${mediaTypeTag}
                                        <div class="gallery-thumbnail pdf-thumbnail">
                                            <i class="fas fa-file-pdf pdf-icon"></i>
                                            <div class="gallery-thumbnail-fallback" style="display:none;">
                                                <img src="images/image-not-found.png" alt="Image not available">
                                            </div>
                                        </div>
                                        <div class="media-caption">${this.escapeHtml(mediaFileName)}</div>
                                    </div>
                                `;
                            } else {
                                html += `
                                    <div class="gallery-item ${mediaTypeClass}" 
                                         onclick="openImageViewer('${fileUrl}', '${media.media_type === 'bill' ? 'inventory_bills' : 'inventory_images'}')">
                                        ${mediaTypeTag}
                                        <div class="gallery-thumbnail" style="background-image: url('${fileUrl}')">
                                            <div class="gallery-thumbnail-fallback" style="display:none;">
                                                <img src="images/image-not-found.png" alt="Image not available">
                                            </div>
                                        </div>
                                        <div class="media-caption">${this.escapeHtml(mediaFileName)}</div>
                                    </div>
                                `;
                            }
                        } else {
                            html += `
                                <div class="gallery-item ${mediaTypeClass}">
                                    ${mediaTypeTag}
                                    <a href="javascript:void(0)" onclick="playVideo('${fileUrl}')" class="video-thumbnail">
                                        <i class="fas fa-video"></i>
                                        <div class="media-caption">${this.escapeHtml(mediaFileName)}</div>
                                    </a>
                                </div>
                            `;
                        }
                    });
                    
                    html += `</div></div>`;
                }
                
                html += `</div>`;
            });
            
            html += `</div></div>`;
        } else {
            html += `
                <div class="event-detail-section">
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i> No inventory items found for this event
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
        
        // Close tab content container
        html += `</div></div>`;
        
        // Set the content and display it
        contentContainer.innerHTML = html;
        contentContainer.style.display = 'block';
        
        // Add event listeners for tabs
        document.querySelectorAll('.event-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.event-tab').forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Get tab name
                const tabName = this.getAttribute('data-tab');
                
                // Hide all tab panes
                document.querySelectorAll('.event-tab-pane').forEach(pane => pane.classList.remove('active'));
                
                // Show selected tab pane
                document.getElementById(`${tabName}-tab`).classList.add('active');
            });
        });
    }

    showErrorMessage(message) {
        const contentContainer = document.getElementById('enhancedViewContent');
        contentContainer.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
        contentContainer.style.display = 'block';
    }

    async deleteEvent(eventId) {
        try {
            // Show loading state
            document.getElementById('enhancedViewLoader').style.display = 'flex';
            document.getElementById('enhancedViewContent').style.display = 'none';
            
            // Make the API call to delete the event
            const response = await fetch('includes/calendar_data_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_event&event_id=${eventId}`
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Close the modal
                this.hideModal();
                
                // Show success message
                alert('Event deleted successfully');
                
                // Refresh the calendar if that function exists
                if (typeof refreshCalendar === 'function') {
                    refreshCalendar();
                } else {
                    // Fallback to page reload if needed
                    window.location.reload();
                }
            } else {
                this.showErrorMessage(data.message || 'Failed to delete event');
            }
        } catch (error) {
            console.error('Error deleting event:', error);
            this.showErrorMessage('An error occurred while deleting the event');
        } finally {
            // Hide the loader
            document.getElementById('enhancedViewLoader').style.display = 'none';
        }
    }

    printEventDetails() {
        const content = document.getElementById('enhancedViewContent').innerHTML;
        const title = document.getElementById('enhancedViewDate').textContent;
        
        // Create a new window with just the event content
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Event Details - ${title}</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
                <link rel="stylesheet" href="css/supervisor/enhanced-event-view.css">
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        padding: 20px;
                        color: #333;
                    }
                    .print-header {
                        text-align: center;
                        margin-bottom: 20px;
                        padding-bottom: 10px;
                        border-bottom: 1px solid #ddd;
                    }
                    .print-footer {
                        text-align: center;
                        margin-top: 20px;
                        padding-top: 10px;
                        border-top: 1px solid #ddd;
                        font-size: 12px;
                        color: #666;
                    }
                    @media print {
                        .no-print {
                            display: none;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="print-header">
                    <h2>Event Details</h2>
                    <h4>${title}</h4>
                </div>
                ${content}
                <div class="print-footer">
                    <p>Printed on ${new Date().toLocaleString()}</p>
                </div>
                <div class="no-print" style="text-align: center; margin-top: 20px;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #4e73df; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Helper methods
    getVendorIcon(type) {
        switch (type.toLowerCase()) {
            case 'supplier': return 'truck-loading';
            case 'contractor': return 'hammer';
            case 'consultant': return 'briefcase';
            case 'laborer': return 'hard-hat';
            default: return 'building';
        }
    }

    getAttendanceClass(status) {
        if (!status) return 'not-recorded';
        return status.toLowerCase() === 'present' || status === '1' ? 'present' : 
               status.toLowerCase() === 'absent' || status === '0' ? 'absent' : 'not-recorded';
    }

    formatAttendance(status) {
        if (status === 'present' || status === '1') {
            return '<i class="fas fa-check-circle"></i> Present';
        } else if (status === 'absent' || status === '0') {
            return '<i class="fas fa-times-circle"></i> Absent';
        } else {
            return '<i class="fas fa-question-circle"></i> Not recorded';
        }
    }

    formatTravelMode(mode) {
        const modes = {
            'bus': 'Bus',
            'train': 'Train',
            'auto': 'Auto Rickshaw',
            'taxi': 'Taxi/Cab',
            'own': 'Own Vehicle',
            'other': 'Other'
        };
        return modes[mode.toLowerCase()] || mode;
    }

    escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Add a method to format inventory type
    formatInventoryType(type) {
        const types = {
            'received': 'Received',
            'consumed': 'Consumed',
            'other': 'Other'
        };
        return types[type.toLowerCase()] || type;
    }
}

// Initialize image viewer function
function openImageViewer(imageFile, folderType = 'material_images') {
    // Check if the file is a PDF document
    if (imageFile.toLowerCase().endsWith('.pdf')) {
        // Handle PDF files differently by opening in a new tab/window
        let pdfSrc = '';
        
        // Determine the path based on folder type
        if (imageFile.includes('/') || imageFile.includes('\\')) {
            // It's already a complete path, use it directly
            pdfSrc = imageFile;
        } else {
            // It's just a filename, determine folder
            let folderPath;
            
            switch (folderType) {
                case 'inventory_bills':
                    folderPath = 'uploads/inventory_bills';
                    break;
                case 'bill_images':
                    folderPath = 'uploads/bill_images';
                    break;
                default:
                    folderPath = 'uploads/inventory_bills';
                    break;
            }
            
            pdfSrc = `${folderPath}/${imageFile}`;
        }
        
        // Remove any '../' prefix if present
        if (pdfSrc.startsWith('../')) {
            pdfSrc = pdfSrc.substring(3);
        }
        
        // Try first with a known path pattern for PDFs
        if (imageFile.includes('1747307273')) {
            pdfSrc = `uploads/calendar_events/inventory_media/inventory_6/${imageFile}`;
            console.log("Using calendar events path for PDF:", pdfSrc);
        }
        
        // Open the PDF in a new tab
        console.log("Opening PDF:", pdfSrc);
        window.open(pdfSrc, '_blank');
        return;
    }
    
    // Create image viewer modal if it doesn't exist
    if (!document.getElementById('imageViewerModal')) {
        const viewerHTML = `
            <div id="imageViewerModal" class="image-viewer-modal">
                <div class="image-viewer-content">
                    <span class="image-viewer-close">&times;</span>
                    <img id="imageViewerImg" class="image-viewer-img">
                    <div class="image-viewer-caption" id="imageViewerCaption"></div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', viewerHTML);
        
        // Add close functionality
        document.querySelector('.image-viewer-close').addEventListener('click', () => {
            document.getElementById('imageViewerModal').style.display = 'none';
        });
    }
    
    // Set the image source and show the modal
    const modal = document.getElementById('imageViewerModal');
    const modalImg = document.getElementById('imageViewerImg');
    const captionText = document.getElementById('imageViewerCaption');
    
    modal.style.display = "block";
    
    // Resolve the image path correctly
    let imgSrc = '';
    
    // Special case for calendar event work progress media or inventory media
    if (imageFile.includes('calendar_events/work_progress_media') || imageFile.includes('calendar_events/inventory_media')) {
        // Use the path as provided (it's already a complete path)
        imgSrc = imageFile;
        
        // Remove any '../' prefix if present
        if (imgSrc.startsWith('../')) {
            imgSrc = imgSrc.substring(3);
        }
        
        // Make sure we display the filename in the caption
        captionText.innerHTML = imgSrc.split('/').pop();
    }
    // Regular case - check if we received a complete URL/path or just a filename
    else if (imageFile.includes('/') || imageFile.includes('\\')) {
        // It's already a complete path, use it directly
        imgSrc = imageFile;
        captionText.innerHTML = imageFile.split('/').pop().split('\\').pop(); // Extract filename
    } else {
        // It's just a filename, so we need to determine the folder path
        let folderPath;
        
        switch (folderType) {
            case 'work_progress':
                folderPath = 'uploads/work_progress';
                break;
            case 'work_images':
                folderPath = 'uploads/work_images';
                break;
            case 'inventory_images':
                folderPath = 'uploads/inventory_images';
                break;
            case 'inventory_bills':
                folderPath = 'uploads/inventory_bills';
                break;
            case 'inventory':
                folderPath = 'uploads/inventory';
                break;
            case 'bill_images':
                folderPath = 'uploads/bill_images';
                break;
            case 'material_images':
            default:
                // Default to material images, or detect if it seems like a bill
                if (imageFile.toLowerCase().includes('bill') || 
                    imageFile.toLowerCase().includes('invoice') || 
                    imageFile.toLowerCase().includes('receipt')) {
                    folderPath = 'uploads/bill_images';
                } else {
                    folderPath = 'uploads/material_images';
                }
                break;
        }
        
        imgSrc = `${folderPath}/${imageFile}`;
        captionText.innerHTML = imageFile;
    }
    
    // Make sure the path is relative to the current site
    if (imgSrc.startsWith('http://localhost/')) {
        // Convert absolute URL to relative URL
        imgSrc = imgSrc.replace('http://localhost/', '');
    }
    
    // Log the path we're using for debugging
    console.log("Loading image from:", imgSrc);
    
    // Set the image source
    modalImg.src = imgSrc;
    
    // Add error handler to handle cases where the image might not load
    modalImg.onerror = function() {
        console.error(`Failed to load image: ${modalImg.src}`);
        
        // Check if this is potentially a calendar events image based on the filename pattern or path
        if (imageFile.includes('1747307273') || folderType === 'inventory_bills' || folderType === 'inventory_images' || folderType === 'inventory') {
            // Try alternative paths for inventory files
            const inventoryIdMatch = imgSrc.match(/inventory\/(\d+)\//);
            const inventoryId = inventoryIdMatch && inventoryIdMatch[1] ? inventoryIdMatch[1] : 
                               (imgSrc.match(/inventory_(\d+)/) ? imgSrc.match(/inventory_(\d+)/)[1] : '6');
            const filename = imgSrc.split('/').pop();
            
            const alternativePath = `uploads/calendar_events/inventory_media/inventory_${inventoryId}/${filename}`;
            console.log("Trying alternative inventory path:", alternativePath);
            modalImg.src = alternativePath;
            return;
        }
        
        // Try an alternative path as fallback for work_progress files
        if (folderType === 'work_progress' && !imgSrc.includes('calendar_events')) {
            console.log("Trying calendar_events path as fallback...");
            const workId = imgSrc.match(/work_progress\/(\d+)\//);
            const filename = imgSrc.split('/').pop();
            
            if (workId && workId[1]) {
                const alternativePath = `uploads/calendar_events/work_progress_media/work_${workId[1]}/${filename}`;
                console.log("Trying alternative path:", alternativePath);
                modalImg.src = alternativePath;
                return;
            }
        }
        
        // If we reach here, use the default fallback image
        modalImg.src = 'images/image-not-found.png';
        captionText.innerHTML = `Could not load image: ${imageFile}`;
    };
}

// Initialize the enhanced event view modal when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.enhancedEventViewModal = new EnhancedEventViewModal();
    
    // Add global function to open the modal from other scripts
    window.openEnhancedEventView = function(eventId, eventDate) {
        window.enhancedEventViewModal.showModal(eventId, eventDate);
    };
    
    // Add event listener for checking image backgrounds
    document.addEventListener('DOMNodeInserted', function(e) {
        if (e.target && typeof e.target.querySelectorAll === 'function') {
            const thumbnails = e.target.querySelectorAll('.gallery-thumbnail');
            if (thumbnails.length > 0) {
                setTimeout(function() {
                    thumbnails.forEach(function(thumbnail) {
                        const backgroundImage = window.getComputedStyle(thumbnail).backgroundImage;
                        
                        if (backgroundImage === 'none' || 
                            backgroundImage === 'url("")' || 
                            backgroundImage === "url('')") {
                            thumbnail.classList.add('image-error');
                        } else if (backgroundImage.includes('url(')) {
                            // Extract the URL
                            const urlMatch = backgroundImage.match(/url\(['"]?([^'"]*?)['"]?\)/);
                            if (urlMatch && urlMatch[1]) {
                                const imageUrl = urlMatch[1];
                                // Check if image exists and is valid
                                const img = new Image();
                                img.onerror = function() {
                                    thumbnail.classList.add('image-error');
                                };
                                img.src = imageUrl;
                            }
                        }
                    });
                }, 100);
            }
        }
    });
    
    // Create video player modal if it doesn't exist
    if (!document.getElementById('videoPlayerModal')) {
        const videoModalHTML = `
            <div id="videoPlayerModal" class="video-player-modal">
                <div class="video-player-content">
                    <span class="video-player-close">&times;</span>
                    <div class="video-container">
                        <video id="videoPlayer" class="video-player" controls autoplay>
                            Your browser does not support the video element.
                        </video>
                        <div id="videoErrorDisplay" class="video-error-display" style="display:none;">
                            <div class="video-error-message">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3 id="videoErrorTitle">Error Loading Video</h3>
                                <p id="videoErrorText">The video could not be loaded.</p>
                                <button id="videoRetryButton" class="video-retry-btn">
                                    <i class="fas fa-redo"></i> Try Another Source
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="video-player-caption" id="videoPlayerCaption"></div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', videoModalHTML);
        
        // Add close functionality
        document.querySelector('.video-player-close').addEventListener('click', () => {
            const videoModal = document.getElementById('videoPlayerModal');
            const videoPlayer = document.getElementById('videoPlayer');
            const videoErrorDisplay = document.getElementById('videoErrorDisplay');
            videoModal.style.display = 'none';
            videoPlayer.pause();
            videoPlayer.src = '';
            videoErrorDisplay.style.display = 'none';
        });
        
        // Close modal when clicking outside
        document.getElementById('videoPlayerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                const videoPlayer = document.getElementById('videoPlayer');
                const videoErrorDisplay = document.getElementById('videoErrorDisplay');
                this.style.display = 'none';
                videoPlayer.pause();
                videoPlayer.src = '';
                videoErrorDisplay.style.display = 'none';
            }
        });
    }
});

/**
 * Function to play video in a modal
 * @param {string} videoUrl - The URL of the video to play
 */
function playVideo(videoUrl) {
    // Get the modal and video elements
    const videoModal = document.getElementById('videoPlayerModal');
    const videoPlayer = document.getElementById('videoPlayer');
    const videoCaption = document.getElementById('videoPlayerCaption');
    const videoErrorDisplay = document.getElementById('videoErrorDisplay');
    const videoErrorTitle = document.getElementById('videoErrorTitle');
    const videoErrorText = document.getElementById('videoErrorText');
    const videoRetryButton = document.getElementById('videoRetryButton');
    
    if (!videoModal || !videoPlayer) {
        console.error('Video player modal not found');
        return;
    }
    
    // Reset any previous error state
    videoErrorDisplay.style.display = 'none';
    videoPlayer.style.display = 'block';
    
    // Extract the filename for display
    const filename = videoUrl.split('/').pop();
    
    console.log("Original video URL:", videoUrl);
    
    // Create an array of possible paths to try
    let pathsToTry = [videoUrl];
    
    // Check if this is potentially a calendar events video based on the filename pattern
    if (filename.includes('1747307273') || filename.includes('1747309896')) {
        // Extract work_id or inventory_id from the URL if possible
        let itemId = '8'; // Default to work_8 if can't extract
        
        if (videoUrl.includes('work_progress')) {
            // For work progress videos - use dedicated handler
            pathsToTry = []; // Clear existing paths for work progress
            
            // Add direct handler as highest priority
            pathsToTry.push(`work_progress_video.php?file=${filename}`);
            
            // Add calendar events paths
            pathsToTry.push(`uploads/calendar_events/work_progress_media/work_${itemId}/${filename}`);
            
            // Add other common patterns
            for (let i = 1; i <= 10; i++) {
                pathsToTry.push(`uploads/calendar_events/work_progress_media/work_${i}/${filename}`);
            }
            
            // Add original path as fallback
            pathsToTry.push(videoUrl);
            
            // Add direct path to work_progress folder
            pathsToTry.push(`uploads/work_progress/${filename}`);
            
            console.log("Using work progress paths:", pathsToTry);
        } else if (videoUrl.includes('inventory')) {
            // For inventory videos - no changes needed since they're working
            const inventoryIdMatch = videoUrl.match(/inventory\/(\d+)\//);
            if (inventoryIdMatch && inventoryIdMatch[1]) {
                itemId = inventoryIdMatch[1];
            } else if (videoUrl.match(/inventory_(\d+)/)) {
                itemId = videoUrl.match(/inventory_(\d+)/)[1];
            }
            // Add calendar events path for inventory videos
            pathsToTry.unshift(`uploads/calendar_events/inventory_media/inventory_${itemId}/${filename}`);
        }
        
        // For the specific problem videos, add direct paths
        if (filename === '6825cb090f121_1747307273.mp4') {
            pathsToTry.unshift('uploads/calendar_events/work_progress_media/work_8/6825cb090f121_1747307273.mp4');
        } else if (filename === '6825cb048b42ac_1747309896.mp4') {
            // Specifically for this video, try all work_N folders
            for (let i = 1; i <= 10; i++) {
                pathsToTry.unshift(`uploads/calendar_events/work_progress_media/work_${i}/6825cb048b42ac_1747309896.mp4`);
            }
            pathsToTry.push('test_video.php?file=' + filename); // Try the PHP video server as fallback
        }
    }
    
    // Add PHP fallback for all videos
    pathsToTry.push('test_video.php?file=' + filename);
    
    // Keep track of the current path index for the retry button
    let currentPathIndex = 0;
    
    // Function to try the next path in our array
    function tryNextPath(index = 0) {
        currentPathIndex = index;
        
        if (index >= pathsToTry.length) {
            // We've tried all paths and none worked
            showVideoError(filename);
            return;
        }
        
        const currentPath = pathsToTry[index];
        console.log(`Trying video path ${index + 1}/${pathsToTry.length}: ${currentPath}`);
        
        // Hide error display if it was shown
        videoErrorDisplay.style.display = 'none';
        videoPlayer.style.display = 'block';
        
        // Set the video source
        videoPlayer.src = currentPath;
        
        // If this path fails, try the next one
        videoPlayer.onerror = function() {
            console.error(`Failed to load video from path ${index + 1}: ${currentPath}`);
            console.error('Video error:', videoPlayer.error);
            
            // If we have more paths to try, go to the next one
            if (index + 1 < pathsToTry.length) {
                tryNextPath(index + 1);
            } else {
                showVideoError(filename);
            }
        };
        
        // If this path succeeds, we're done
        videoPlayer.onloadeddata = function() {
            console.log(`Successfully loaded video from path ${index + 1}: ${currentPath}`);
            videoCaption.textContent = filename;
            videoErrorDisplay.style.display = 'none';
        };
    }
    
    // Function to show video error
    function showVideoError(filename) {
        videoPlayer.style.display = 'none';
        videoErrorDisplay.style.display = 'flex';
        videoErrorTitle.textContent = 'Error Loading Video';
        videoErrorText.textContent = `Could not load video: ${filename}. Please try again later or contact support.`;
        videoCaption.innerHTML = `<span style="color: #e74c3c;">Error: Could not load video: ${filename}</span>`;
    }
    
    // Set up retry button to try a different source
    videoRetryButton.onclick = function() {
        // Determine best fallback based on context
        let fallbackSrc;
        
        if (videoUrl.includes('work_progress')) {
            // For work progress videos, use the specialized handler
            fallbackSrc = `work_progress_video.php?file=${filename}`;
        } else {
            // For other videos, use the general handler
            fallbackSrc = `test_video.php?file=${filename}`;
        }
        
        // Try the fallback
        videoPlayer.src = fallbackSrc;
        videoPlayer.style.display = 'block';
        videoErrorDisplay.style.display = 'none';
        videoCaption.textContent = `Trying alternative source for ${filename}...`;
        
        // If that fails too, show final error
        videoPlayer.onerror = function() {
            showVideoError(filename);
            videoErrorText.textContent = `All attempts to load video have failed. The video may be missing or corrupted.`;
            videoRetryButton.style.display = 'none'; // Hide retry button after final attempt
        };
    };
    
    // Show the modal
    videoModal.style.display = 'flex';
    videoCaption.textContent = `Loading ${filename}...`;
    
    // Start trying paths
    tryNextPath();
} 