// Site Update Modal JavaScript

// Show the site update modal when a site card is clicked
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to all site card arrows
    const siteCardArrows = document.querySelectorAll('.site-card-arrow');
    
    siteCardArrows.forEach(arrow => {
        arrow.addEventListener('click', function(e) {
            e.preventDefault();
            const updateId = this.getAttribute('href').split('=')[1];
            openSiteUpdateModal(updateId);
        });
    });
});

// Function to open the site update modal and load data
function openSiteUpdateModal(updateId) {
    // Show modal
    const modal = document.getElementById('siteUpdateModal');
    if (!modal) return;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent scrolling of background
    
    // Reset sections to loading state
    document.getElementById('modal-vendors').innerHTML = '<div class="loading">Loading vendors...</div>';
    document.getElementById('modal-company-laborers').innerHTML = '<div class="loading">Loading company laborers...</div>';
    document.getElementById('modal-travel-expenses').innerHTML = '<div class="loading">Loading travel expenses...</div>';
    document.getElementById('modal-beverages').innerHTML = '<div class="loading">Loading refreshments...</div>';
    document.getElementById('modal-work-progress').innerHTML = '<div class="loading">Loading work progress...</div>';
    document.getElementById('modal-inventory').innerHTML = '<div class="loading">Loading inventory...</div>';
    
    // Set the full details link
    document.getElementById('view-full-details-link').href = 'view_site_update.php?id=' + updateId;
    
    // Fetch site update details using AJAX
    fetchSiteUpdateDetails(updateId);
}

// Function to close the site update modal
function closeSiteUpdateModal() {
    const modal = document.getElementById('siteUpdateModal');
    if (!modal) return;
    
    modal.style.display = 'none';
    document.body.style.overflow = ''; // Restore scrolling
}

// Function to fetch site update details
function fetchSiteUpdateDetails(updateId) {
    // Create AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'site_updates/fetch_update_details.php?id=' + updateId, true);
    
    xhr.onload = function() {
        if (this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                
                if (response.success) {
                    // Update modal with received data
                    updateModalContent(response.data);
                } else {
                    showModalError(response.message || 'Error loading update details');
                }
            } catch (e) {
                showModalError('Error parsing response');
                console.error('Error parsing JSON response:', e);
            }
        } else {
            showModalError('Server returned error: ' + this.status);
        }
    };
    
    xhr.onerror = function() {
        showModalError('Network error occurred');
    };
    
    xhr.send();
}

// Function to update modal content with fetched data
function updateModalContent(data) {
    // Update basic info
    document.getElementById('modal-site-name').textContent = data.site_name || 'Site Update';
    document.getElementById('modal-update-date').textContent = data.update_date || '';
    document.getElementById('modal-created-by').textContent = data.created_by_name || '';
    
    // Update vendor and laborers section
    updateVendorsSection(data.vendors || []);
    
    // Update company laborers section
    updateCompanyLaborersSection(data.company_labours || []);
    
    // Update travel expenses section
    updateTravelExpensesSection(data.travel_expenses || []);
    
    // Update beverages section
    updateBeveragesSection(data.beverages || []);
    
    // Update grand total
    updateGrandTotal(data.grand_total || 0);
    
    // Update work progress section
    updateWorkProgressSection(data.work_progress || []);
    
    // Update inventory section
    updateInventorySection(data.inventory || []);
}

// Function to update work progress section
function updateWorkProgressSection(workProgressItems) {
    const container = document.getElementById('modal-work-progress');
    
    if (workProgressItems.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>No work progress items available</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    workProgressItems.forEach(item => {
        // Debug: log work progress item data
        console.log('Work progress item:', item);
        
        // Create HTML for work progress media if available
        let mediaHtml = '';
        if (item.media && item.media.length > 0) {
            console.log('Media found for work item:', item.media);
            mediaHtml = '<div class="media-container">';
            item.media.forEach(media => {
                // Check if file_path exists
                if (!media.file_path) {
                    console.error('Missing file_path in media item:', media);
                    return;
                }
                
                // Make sure file_path is properly formatted
                let filePath = media.file_path;
                
                // If file_path doesn't start with a slash or http, make it relative to root
                if (!filePath.startsWith('/') && !filePath.startsWith('http')) {
                    // If already starts with 'uploads/', use as is, otherwise prepend '../'
                    if (!filePath.startsWith('uploads/')) {
                        filePath = '../' + filePath;
                    }
                }
                
                console.log('Using media path:', filePath);
                
                // Check if this is a video file
                const isVideo = filePath.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i);
                
                if (isVideo) {
                    // For video files, show a thumbnail with play button overlay
                    mediaHtml += `
                        <div class="media-item video-item" onclick="openMediaViewer('${filePath}')">
                            <div class="video-thumbnail">
                                <i class="fas fa-play-circle"></i>
                                <span>Video</span>
                            </div>
                        </div>
                    `;
                } else {
                    // For image files, show the image thumbnail
                    mediaHtml += `
                        <div class="media-item" onclick="openMediaViewer('${filePath}')">
                            <img src="${filePath}" alt="Work progress media" onerror="this.onerror=null; this.src='../images/image-not-found.svg'; console.error('Failed to load image: ${filePath}');">
                        </div>
                    `;
                }
            });
            mediaHtml += '</div>';
        }
        
        // Choose appropriate icon
        let categoryIcon = 'fas fa-hammer';
        
        html += `
            <div class="work-item">
                <div class="work-item-header">
                    <div class="work-item-title"><i class="${categoryIcon}"></i> ${item.work_category || ''}: ${item.work_type || ''}</div>
                    <div class="work-status status-${item.work_done || 'No'}">
                        <i class="fas ${item.work_done === 'Yes' ? 'fa-check' : 'fa-times'}"></i> 
                        ${item.work_done || 'No'}
                    </div>
                </div>
                ${item.remarks ? `<div class="work-remarks"><i class="fas fa-comment"></i> ${item.remarks}</div>` : ''}
                ${mediaHtml}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Function to update inventory section
function updateInventorySection(inventoryItems) {
    const container = document.getElementById('modal-inventory');
    
    if (inventoryItems.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>No inventory items available</p>
            </div>
        `;
        return;
    }
    
    // Group inventory items by type
    const groupedItems = {
        'Received': [],
        'Available': [],
        'Consumed': []
    };
    
    inventoryItems.forEach(item => {
        const type = item.inventory_type || 'Available';
        if (!groupedItems[type]) groupedItems[type] = [];
        groupedItems[type].push(item);
    });
    
    let html = '';
    
    // Process each inventory type
    for (const [type, items] of Object.entries(groupedItems)) {
        if (items.length === 0) continue;
        
        html += `<div class="inventory-group inventory-group-${type.toLowerCase()}">`;
        
        items.forEach(item => {
            // Debug: log inventory item data
            console.log('Inventory item:', item);
            
            // Create HTML for inventory media if available
            let mediaHtml = '';
            if (item.media && item.media.length > 0) {
                console.log('Media found for inventory item:', item.media);
                mediaHtml = '<div class="media-container">';
                item.media.forEach(media => {
                    // Check if file_path exists
                    if (!media.file_path) {
                        console.error('Missing file_path in media item:', media);
                        return;
                    }
                    
                    // Make sure file_path is properly formatted
                    let filePath = media.file_path;
                    
                    // If file_path doesn't start with a slash or http, make it relative to root
                    if (!filePath.startsWith('/') && !filePath.startsWith('http')) {
                        // If already starts with 'uploads/', use as is, otherwise prepend '../'
                        if (!filePath.startsWith('uploads/')) {
                            filePath = '../' + filePath;
                        }
                    }
                    
                    console.log('Using media path:', filePath);
                    
                    // Check if this is a video file
                    const isVideo = filePath.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i);
                    
                    if (isVideo) {
                        // For video files, show a thumbnail with play button overlay
                        mediaHtml += `
                            <div class="media-item video-item" onclick="openMediaViewer('${filePath}')">
                                <div class="video-thumbnail">
                                    <i class="fas fa-play-circle"></i>
                                    <span>Video</span>
                                </div>
                            </div>
                        `;
                    } else {
                        // For image files, show the image thumbnail
                        mediaHtml += `
                            <div class="media-item" onclick="openMediaViewer('${filePath}')">
                                <img src="${filePath}" alt="Inventory media" onerror="this.onerror=null; this.src='../images/image-not-found.svg'; console.error('Failed to load image: ${filePath}');">
                            </div>
                        `;
                    }
                });
                mediaHtml += '</div>';
            }
            
            html += `
                <div class="inventory-item">
                    <div class="inventory-item-header">
                        <div class="inventory-item-title">
                            <span class="inventory-type">
                                <i class="fas fa-box"></i>
                                ${item.inventory_type || 'Available'}
                            </span>
                            <i class="fas fa-cube"></i> ${item.material || 'Material'}
                        </div>
                        <div class="inventory-quantity">
                            <i class="fas fa-balance-scale"></i> ${item.quantity || '0'} ${item.unit || 'pcs'}
                        </div>
                    </div>
                    ${item.notes ? `<div class="inventory-notes"><i class="fas fa-sticky-note"></i> ${item.notes}</div>` : ''}
                    ${mediaHtml}
                </div>
            `;
        });
        
        html += `</div>`;
    }
    
    container.innerHTML = html;
}

// Function to update vendors section
function updateVendorsSection(vendors) {
    const container = document.getElementById('modal-vendors');
    
    if (vendors.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>No vendors available</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    // Add vendors and their laborers
    vendors.forEach(vendor => {
        let laborersHtml = '';
        
        if (vendor.laborers && vendor.laborers.length > 0) {
            laborersHtml = '<div class="laborers-list">';
            laborersHtml += `
                <div class="laborer-header">
                    <div class="laborer-name-title"><i class="fas fa-user"></i> Name</div>
                    <div class="laborer-attendance-title"><i class="fas fa-calendar-check"></i> Attendance</div>
                    <div class="laborer-wages-title"><i class="fas fa-money-bill-wave"></i> Wages</div>
                    <div class="laborer-overtime-title"><i class="fas fa-clock"></i> Overtime</div>
                    <div class="laborer-amount-title"><i class="fas fa-rupee-sign"></i> Total</div>
                </div>
                <div class="laborer-subheader">
                    <div class="laborer-name-col"></div>
                    <div class="laborer-attendance-col">
                        <span>Morning</span>
                        <span>Evening</span>
                    </div>
                    <div class="laborer-wages-col">
                        <span>Per Day</span>
                        <span>Day Total</span>
                    </div>
                    <div class="laborer-overtime-col">
                        <span>Hours</span>
                        <span>Rate</span>
                        <span>Amount</span>
                    </div>
                    <div class="laborer-amount-col"></div>
                </div>
            `;
            
            vendor.laborers.forEach(laborer => {
                // Debug: log all fields to see what's available
                console.log('Laborer data:', laborer);
                
                // Calculate or extract values
                const wagesPerDay = laborer.wages_per_day || 0;
                const dayTotal = laborer.day_total || wagesPerDay; // Fallback to wages_per_day if not specified
                
                // Check all possible field names for overtime hours - add more field names from database
                let otHours = 'N/A';
                const possibleHoursFields = ['overtime', 'overtime_hours', 'ot_hours', 'ot'];
                for (const field of possibleHoursFields) {
                    if (laborer[field] !== undefined && laborer[field] !== null && laborer[field] !== '') {
                        otHours = laborer[field];
                        break;
                    }
                }
                
                // Format overtime hours with hours and minutes
                let formattedOtHours = 'N/A';
                if (otHours !== 'N/A') {
                    // Parse the overtime value (could be decimal or time format)
                    let hours = 0;
                    let minutes = 0;
                    
                    // Check for minutes in ot_minutes column first
                    if (laborer.ot_minutes !== undefined && laborer.ot_minutes !== null && laborer.ot_minutes !== '') {
                        minutes = parseInt(laborer.ot_minutes, 10);
                        
                        // Add minutes to existing hours calculation
                        if (typeof otHours === 'string' && otHours.includes(':')) {
                            // Time format "HH:MM"
                            const parts = otHours.split(':');
                            hours = parseInt(parts[0], 10);
                        } else {
                            // Decimal format (e.g., 1.5 hours)
                            hours = Math.floor(parseFloat(otHours));
                        }
                    } else if (typeof otHours === 'string' && otHours.includes(':')) {
                        // Time format "HH:MM"
                        const parts = otHours.split(':');
                        hours = parseInt(parts[0], 10);
                        minutes = parseInt(parts[1], 10);
                    } else {
                        // Decimal format (e.g., 1.5 hours)
                        const decimalHours = parseFloat(otHours);
                        hours = Math.floor(decimalHours);
                        minutes = Math.round((decimalHours - hours) * 60);
                    }
                    
                    formattedOtHours = `${hours}h ${minutes}m`;
                }
                
                // Check all possible field names for overtime rate
                let otRate = 'N/A';
                const possibleRateFields = ['overtime_rate', 'ot_rate', 'rate'];
                for (const field of possibleRateFields) {
                    if (laborer[field] !== undefined && laborer[field] !== null && laborer[field] !== '') {
                        otRate = laborer[field];
                        break;
                    }
                }
                
                // Format overtime rate
                if (otRate !== 'N/A') {
                    otRate = '₹' + parseFloat(otRate).toFixed(2);
                }
                
                // Check all possible field names for overtime amount
                let otAmount = 0;
                const possibleAmountFields = ['overtime_amount', 'ot_amount', 'amount'];
                for (const field of possibleAmountFields) {
                    if (laborer[field] !== undefined && laborer[field] !== null && laborer[field] !== '') {
                        otAmount = laborer[field];
                        break;
                    }
                }
                
                laborersHtml += `
                    <div class="laborer-item">
                        <div class="laborer-name"><i class="fas fa-user"></i> ${laborer.name || 'Laborer'}</div>
                        <div class="laborer-attendance">
                            <span class="attendance-badge attendance-${laborer.morning_attendance || 'P'}">
                                <i class="fas fa-sun"></i> ${laborer.morning_attendance || 'P'}
                            </span>
                            <span class="attendance-badge attendance-${laborer.evening_attendance || 'P'}">
                                <i class="fas fa-moon"></i> ${laborer.evening_attendance || 'P'}
                            </span>
                        </div>
                        <div class="laborer-wages">
                            <span class="wages-per-day">₹${parseFloat(wagesPerDay).toFixed(2)}</span>
                            <span class="day-total">₹${parseFloat(dayTotal).toFixed(2)}</span>
                        </div>
                        <div class="laborer-overtime">
                            <span class="ot-hours">${formattedOtHours}</span>
                            <span class="ot-rate">${otRate}</span>
                            <span class="ot-amount">₹${parseFloat(otAmount).toFixed(2)}</span>
                        </div>
                        <div class="laborer-amount">₹${parseFloat(laborer.total_amount || 0).toFixed(2)}</div>
                    </div>
                `;
            });
            
            // Add total for this vendor's laborers
            const vendorTotal = vendor.laborers.reduce((total, laborer) => total + parseFloat(laborer.total_amount || 0), 0);
            laborersHtml += `
                <div class="vendor-total">
                    <span>Total Amount</span>
                    <span class="vendor-total-amount">₹${vendorTotal.toFixed(2)}</span>
                </div>
            `;
            
            laborersHtml += '</div>';
        }
        
        html += `
            <div class="vendor-item">
                <div class="vendor-header">
                    <div class="vendor-name"><i class="fas fa-building"></i> ${vendor.name || 'Vendor'}</div>
                    <div class="vendor-type"><i class="fas fa-tag"></i> ${vendor.vendor_type || 'General'}</div>
                </div>
                ${vendor.contact ? `<div class="vendor-contact"><i class="fas fa-phone-alt"></i> ${vendor.contact}</div>` : ''}
                ${laborersHtml}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Function to update company laborers section
function updateCompanyLaborersSection(companyLabours) {
    const container = document.getElementById('modal-company-laborers');
    
    if (companyLabours.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>No company laborers available</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="company-labor-container">
    `;
    
    // Create table header
    html += `
        <div class="laborer-header">
            <div class="laborer-name-title"><i class="fas fa-user"></i> Name</div>
            <div class="laborer-attendance-title"><i class="fas fa-calendar-check"></i> Attendance</div>
            <div class="laborer-wages-title"><i class="fas fa-money-bill-wave"></i> Wages</div>
            <div class="laborer-overtime-title"><i class="fas fa-clock"></i> Overtime</div>
            <div class="laborer-amount-title"><i class="fas fa-rupee-sign"></i> Total</div>
        </div>
        <div class="laborer-subheader">
            <div class="laborer-name-col"></div>
            <div class="laborer-attendance-col">
                <span>Morning</span>
                <span>Evening</span>
            </div>
            <div class="laborer-wages-col">
                <span>Per Day</span>
                <span>Day Total</span>
            </div>
            <div class="laborer-overtime-col">
                <span>Hours</span>
                <span>Rate</span>
                <span>Amount</span>
            </div>
            <div class="laborer-amount-col"></div>
        </div>
    `;
    
    // Add company laborers
    companyLabours.forEach(laborer => {
        // Debug: log all fields to see what's available
        console.log('Company laborer data:', laborer);
        
        // Calculate or extract values
        const wagesPerDay = laborer.wages_per_day || 0;
        const dayTotal = laborer.day_total || wagesPerDay; // Fallback to wages_per_day if not specified
        
        // Check all possible field names for overtime hours - add more field names from database
        let otHours = 'N/A';
        const possibleHoursFields = ['overtime', 'overtime_hours', 'ot_hours', 'ot'];
        for (const field of possibleHoursFields) {
            if (laborer[field] !== undefined && laborer[field] !== null && laborer[field] !== '') {
                otHours = laborer[field];
                break;
            }
        }
        
        // Format overtime hours with hours and minutes
        let formattedOtHours = 'N/A';
        if (otHours !== 'N/A') {
            // Parse the overtime value (could be decimal or time format)
            let hours = 0;
            let minutes = 0;
            
            // Check for minutes in ot_minutes column first
            if (laborer.ot_minutes !== undefined && laborer.ot_minutes !== null && laborer.ot_minutes !== '') {
                minutes = parseInt(laborer.ot_minutes, 10);
                
                // Add minutes to existing hours calculation
                if (typeof otHours === 'string' && otHours.includes(':')) {
                    // Time format "HH:MM"
                    const parts = otHours.split(':');
                    hours = parseInt(parts[0], 10);
                } else {
                    // Decimal format (e.g., 1.5 hours)
                    hours = Math.floor(parseFloat(otHours));
                }
            } else if (typeof otHours === 'string' && otHours.includes(':')) {
                // Time format "HH:MM"
                const parts = otHours.split(':');
                hours = parseInt(parts[0], 10);
                minutes = parseInt(parts[1], 10);
            } else {
                // Decimal format (e.g., 1.5 hours)
                const decimalHours = parseFloat(otHours);
                hours = Math.floor(decimalHours);
                minutes = Math.round((decimalHours - hours) * 60);
            }
            
            formattedOtHours = `${hours}h ${minutes}m`;
        }
        
        // Check all possible field names for overtime rate
        let otRate = 'N/A';
        const possibleRateFields = ['overtime_rate', 'ot_rate', 'rate'];
        for (const field of possibleRateFields) {
            if (laborer[field] !== undefined && laborer[field] !== null && laborer[field] !== '') {
                otRate = laborer[field];
                break;
            }
        }
        
        // Format overtime rate
        if (otRate !== 'N/A') {
            otRate = '₹' + parseFloat(otRate).toFixed(2);
        }
        
        // Check all possible field names for overtime amount
        let otAmount = 0;
        const possibleAmountFields = ['overtime_amount', 'ot_amount', 'amount'];
        for (const field of possibleAmountFields) {
            if (laborer[field] !== undefined && laborer[field] !== null && laborer[field] !== '') {
                otAmount = laborer[field];
                break;
            }
        }
        
        html += `
            <div class="laborer-item">
                <div class="laborer-name"><i class="fas fa-user"></i> ${laborer.name || 'Laborer'}</div>
                <div class="laborer-attendance">
                    <span class="attendance-badge attendance-${laborer.morning_attendance || 'P'}">
                        <i class="fas fa-sun"></i> ${laborer.morning_attendance || 'P'}
                    </span>
                    <span class="attendance-badge attendance-${laborer.evening_attendance || 'P'}">
                        <i class="fas fa-moon"></i> ${laborer.evening_attendance || 'P'}
                    </span>
                </div>
                <div class="laborer-wages">
                    <span class="wages-per-day">₹${parseFloat(wagesPerDay).toFixed(2)}</span>
                    <span class="day-total">₹${parseFloat(dayTotal).toFixed(2)}</span>
                </div>
                <div class="laborer-overtime">
                    <span class="ot-hours">${formattedOtHours}</span>
                    <span class="ot-rate">${otRate}</span>
                    <span class="ot-amount">₹${parseFloat(otAmount).toFixed(2)}</span>
                </div>
                <div class="laborer-amount">₹${parseFloat(laborer.total_amount || 0).toFixed(2)}</div>
            </div>
        `;
    });
    
    // Add total for company laborers
    const companyTotal = companyLabours.reduce((total, laborer) => total + parseFloat(laborer.total_amount || 0), 0);
    html += `
        <div class="company-total">
            <span>Total Labor Cost</span>
            <span class="company-total-amount">₹${companyTotal.toFixed(2)}</span>
        </div>
    </div>
    `;
    
    container.innerHTML = html;
}

// Function to update travel expenses section
function updateTravelExpensesSection(travelExpenses) {
    const container = document.getElementById('modal-travel-expenses');
    
    if (travelExpenses.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>No travel expenses available</p>
            </div>
        `;
        return;
    }
    
    // Debug: log travel expense data
    console.log('Travel expenses data:', travelExpenses);
    
    let html = `
        <div class="travel-expenses-table">
            <div class="travel-header">
                <div class="travel-mode-title"><i class="fas fa-car"></i> Transport Mode</div>
                <div class="travel-from-title"><i class="fas fa-map-marker-alt"></i> From</div>
                <div class="travel-to-title"><i class="fas fa-map-pin"></i> To</div>
                <div class="travel-distance-title"><i class="fas fa-road"></i> Distance</div>
                <div class="travel-amount-title"><i class="fas fa-rupee-sign"></i> Amount</div>
            </div>
    `;
    
    // Add travel expenses
    travelExpenses.forEach(expense => {
        html += `
            <div class="travel-item">
                <div class="travel-mode">
                    <i class="${getTransportIcon(expense.transport_mode)}"></i> 
                    ${expense.transport_mode || 'Transport'}
                </div>
                <div class="travel-from">${expense.travel_from || '–'}</div>
                <div class="travel-to">${expense.travel_to || '–'}</div>
                <div class="travel-distance">${expense.km_travelled ? `${expense.km_travelled} km` : 'N/A'}</div>
                <div class="travel-amount">₹${parseFloat(expense.amount || 0).toFixed(2)}</div>
            </div>
        `;
    });
    
    // Add total for travel expenses
    const travelTotal = travelExpenses.reduce((total, expense) => total + parseFloat(expense.amount || 0), 0);
    html += `
        <div class="travel-total">
            <span>Travel Total</span>
            <span class="travel-total-amount">₹${travelTotal.toFixed(2)}</span>
        </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// Function to get appropriate icon for transportation mode
function getTransportIcon(transportMode) {
    if (!transportMode) return 'fas fa-car';
    
    const mode = transportMode.toLowerCase();
    
    if (mode.includes('bike') || mode.includes('motorcycle')) {
        return 'fas fa-motorcycle';
    } else if (mode.includes('bus')) {
        return 'fas fa-bus';
    } else if (mode.includes('train')) {
        return 'fas fa-train';
    } else if (mode.includes('plane') || mode.includes('flight') || mode.includes('air')) {
        return 'fas fa-plane';
    } else if (mode.includes('taxi') || mode.includes('cab')) {
        return 'fas fa-taxi';
    } else if (mode.includes('truck')) {
        return 'fas fa-truck';
    } else if (mode.includes('walk') || mode.includes('foot')) {
        return 'fas fa-walking';
    } else if (mode.includes('bicycle') || mode.includes('cycle')) {
        return 'fas fa-bicycle';
    } else if (mode.includes('ship') || mode.includes('boat') || mode.includes('ferry')) {
        return 'fas fa-ship';
    } else {
        return 'fas fa-car'; // Default icon
    }
}

// Function to update beverages section
function updateBeveragesSection(beverages) {
    const container = document.getElementById('modal-beverages');
    
    if (beverages.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <p>No refreshments available</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    // Add beverage expenses
    beverages.forEach(beverage => {
        html += `
            <div class="expense-item">
                <div class="expense-details">
                    <div class="expense-type"><i class="fas fa-coffee"></i> ${beverage.beverage_type || 'Refreshment'}</div>
                    <div class="expense-description">${beverage.name || 'Beverage'}</div>
                </div>
                <div class="expense-amount"><i class="fas fa-rupee-sign"></i> ${parseFloat(beverage.amount || 0).toFixed(2)}</div>
            </div>
        `;
    });
    
    // Add total for beverages
    const beverageTotal = beverages.reduce((total, beverage) => total + parseFloat(beverage.amount || 0), 0);
    html += `
        <div class="expense-total">
            <span>Refreshments Total</span>
            <span class="expense-total-amount">₹${beverageTotal.toFixed(2)}</span>
        </div>
    `;
    
    container.innerHTML = html;
}

// Function to update grand total
function updateGrandTotal(grandTotal) {
    const totalElement = document.getElementById('modal-grand-total');
    totalElement.textContent = `₹${parseFloat(grandTotal).toFixed(2)}`;
}

// Function to show error in modal
function showModalError(message) {
    const sections = [
        'modal-vendors',
        'modal-company-laborers',
        'modal-travel-expenses',
        'modal-beverages',
        'modal-work-progress',
        'modal-inventory'
    ];
    
    sections.forEach(section => {
        document.getElementById(section).innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Error: ${message}</p>
            </div>
        `;
    });
}

// Media viewer functionality
function openMediaViewer(mediaPath) {
    console.log('Opening media viewer for:', mediaPath);
    
    // Determine if the media is a video or image based on file extension
    const isVideo = mediaPath.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i);
    
    if (isVideo) {
        // Handle video files
        Swal.fire({
            html: `
                <video controls style="max-width: 100%; max-height: 80vh;">
                    <source src="${mediaPath}" type="video/${isVideo[1]}">
                    Your browser does not support the video tag.
                </video>
            `,
            showCloseButton: true,
            showConfirmButton: false,
            width: 'auto',
            customClass: {
                container: 'media-viewer-modal',
                popup: 'media-viewer-popup'
            }
        });
    } else {
        // Handle image files (existing functionality)
        // Check if the image exists first before showing it with SweetAlert
        const img = new Image();
        img.onload = function() {
            // Image loaded successfully, show it in SweetAlert
            Swal.fire({
                imageUrl: mediaPath,
                imageAlt: 'Media Image',
                showCloseButton: true,
                showConfirmButton: false,
                width: 'auto',
                imageWidth: '700px', // Increased image width
                imageHeight: 'auto',
                customClass: {
                    container: 'media-viewer-modal',
                    popup: 'media-viewer-popup',
                    image: 'media-viewer-image'
                }
            });
        };
        
        img.onerror = function() {
            // Image failed to load, show fallback
            console.error('Failed to load image in viewer:', mediaPath);
            Swal.fire({
                imageUrl: '../images/image-not-found.svg',
                imageAlt: 'Image Not Found',
                showCloseButton: true,
                showConfirmButton: false,
                title: 'Image Not Found',
                text: 'The image could not be loaded.',
                width: 'auto',
                imageWidth: '200px',
                imageHeight: '200px',
                customClass: {
                    container: 'media-viewer-modal'
                }
            });
        };
        
        // Start loading the image
        img.src = mediaPath;
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('siteUpdateModal');
    if (event.target === modal) {
        closeSiteUpdateModal();
    }
}); 