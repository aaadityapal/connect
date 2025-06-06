/**
 * Event Details Modal JavaScript
 * Handles fetching and displaying detailed event information
 */

document.addEventListener('DOMContentLoaded', function() {
  // Initialize event details modal
  initEventDetailsModal();
  
  // Add event listeners for closing the modal
  const closeEventDetailsBtn = document.getElementById('closeEventDetailsModal');
  if (closeEventDetailsBtn) {
    closeEventDetailsBtn.addEventListener('click', closeEventDetailsModal);
  }
  
  const closeEventDetailsModalBtn = document.getElementById('closeEventDetailsModalBtn');
  if (closeEventDetailsModalBtn) {
    closeEventDetailsModalBtn.addEventListener('click', closeEventDetailsModal);
  }
  
  // Close modal when clicking outside
  const eventDetailsModal = document.getElementById('eventDetailsModal');
  if (eventDetailsModal) {
    eventDetailsModal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeEventDetailsModal();
      }
    });
  }
  
  // Initialize image viewer
  initImageViewer();
});

/**
 * Initialize the event details modal
 */
function initEventDetailsModal() {
  // Since the modal HTML is already in the page, we just need to add event delegation
  // for event items to show details when clicked
  
  // Add event delegation for event items
  document.addEventListener('click', function(e) {
    const eventItem = e.target.closest('.event-item');
    if (eventItem) {
      const eventId = eventItem.dataset.eventId;
      if (eventId) {
        showEventDetails(eventId);
      }
    }
  });
}

/**
 * Show event details modal with data from the API
 */
function showEventDetails(eventId) {
  const modal = document.getElementById('eventDetailsModal');
  const loader = document.querySelector('.event-details-loader');
  const content = document.getElementById('eventDetailsContent');
  
  // Show modal with loader
  modal.classList.add('show');
  loader.style.display = 'flex';
  content.innerHTML = '';
  
  // Close any other modals that might be open
  const otherModals = document.querySelectorAll('.modal.show, .modal.fade.show');
  otherModals.forEach(otherModal => {
    if (otherModal !== modal && typeof $(otherModal).modal === 'function') {
      $(otherModal).modal('hide');
    }
  });
  
  // Fetch event details from API
  fetch(`backend/get_event_details.php?event_id=${eventId}`)
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success') {
        // Render event details
        renderEventDetails(data.event);
      } else {
        // Show error message
        content.innerHTML = `
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> 
            Failed to load event details: ${data.message}
          </div>
        `;
      }
    })
    .catch(error => {
      console.error('Error fetching event details:', error);
      content.innerHTML = `
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle"></i> 
          Error loading event details. Please try again.
        </div>
      `;
    })
    .finally(() => {
      // Hide loader
      loader.style.display = 'none';
    });
}

/**
 * Render event details in the modal
 */
function renderEventDetails(event) {
  const content = document.getElementById('eventDetailsContent');
  
  // Format date for display
  const eventDate = new Date(event.event_date);
  const formattedDate = eventDate.toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
  
  // Get badge class based on event type
  const badgeClass = `event-details-badge-${event.event_type}`;
  
  // Build HTML for event details header
  let html = `
    <div class="event-details-header">
      <h3>${event.title}</h3>
      <div class="event-details-meta">
        <span class="event-details-meta-item">
          <i class="fas fa-calendar-alt"></i> ${formattedDate}
        </span>
        <span class="event-details-meta-item">
          <i class="fas fa-tag"></i> 
          <span class="event-details-badge ${badgeClass}">${event.event_type.charAt(0).toUpperCase() + event.event_type.slice(1)}</span>
        </span>
        <span class="event-details-meta-item">
          <i class="fas fa-map-marker-alt"></i> ${event.site_name}
        </span>
        <span class="event-details-meta-item">
          <i class="fas fa-user"></i> Created by: ${event.created_by_name}
        </span>
      </div>
    </div>
  `;
  
  // Create tabs based on available data
  const tabs = [];
  const tabContents = [];
  
  // Overview tab (always present)
  tabs.push(`<li class="nav-item">
    <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab">
      <i class="fas fa-info-circle"></i> Overview
    </a>
  </li>`);
  
  // Overview tab content with export button and counts
  let overviewContent = `
    <div class="tab-pane fade show active" id="overview" role="tabpanel">
      <button class="export-excel-btn" id="exportEventExcel">
        <i class="fas fa-file-excel"></i> Export to Excel
      </button>
      <div class="row mt-3">
  `;
  
  // Add count cards to overview
  if (event.counts) {
    if (event.counts.vendors > 0) {
      overviewContent += `
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card">
            <div class="card-body text-center">
              <i class="fas fa-truck fa-2x text-primary mb-2"></i>
              <h5 class="card-title">${event.counts.vendors}</h5>
              <p class="card-text">Vendors</p>
            </div>
          </div>
        </div>
      `;
    }
    
    if (event.counts.company_labours > 0) {
      overviewContent += `
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card">
            <div class="card-body text-center">
              <i class="fas fa-hard-hat fa-2x text-success mb-2"></i>
              <h5 class="card-title">${event.counts.company_labours}</h5>
              <p class="card-text">Company Laborers</p>
            </div>
          </div>
        </div>
      `;
    }
    
    if (event.counts.beverages > 0) {
      overviewContent += `
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card">
            <div class="card-body text-center">
              <i class="fas fa-coffee fa-2x text-warning mb-2"></i>
              <h5 class="card-title">${event.counts.beverages}</h5>
              <p class="card-text">Beverages</p>
            </div>
          </div>
        </div>
      `;
    }
    
    if (event.counts.work_progress > 0) {
      overviewContent += `
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card">
            <div class="card-body text-center">
              <i class="fas fa-tasks fa-2x text-info mb-2"></i>
              <h5 class="card-title">${event.counts.work_progress}</h5>
              <p class="card-text">Work Progress</p>
            </div>
          </div>
        </div>
      `;
    }
    
    if (event.counts.inventory > 0) {
      overviewContent += `
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card">
            <div class="card-body text-center">
              <i class="fas fa-boxes fa-2x text-secondary mb-2"></i>
              <h5 class="card-title">${event.counts.inventory}</h5>
              <p class="card-text">Inventory Items</p>
            </div>
          </div>
        </div>
      `;
    }
  }
  
  overviewContent += `
      </div>
    </div>
  `;
  
  tabContents.push(overviewContent);
  
  // Vendors tab
  if (event.vendors && event.vendors.length > 0) {
    tabs.push(`<li class="nav-item">
      <a class="nav-link" id="vendors-tab" data-toggle="tab" href="#vendors" role="tab">
        <i class="fas fa-truck"></i> Vendors (${event.vendors.length})
      </a>
    </li>`);
    
    let vendorsContent = `
      <div class="tab-pane fade" id="vendors" role="tabpanel">
        <table class="event-details-table mt-3">
          <thead>
            <tr>
              <th>#</th>
              <th>Vendor Name</th>
              <th>Type</th>
              <th>Contact</th>
              <th>Laborers</th>
            </tr>
          </thead>
          <tbody>
    `;
    
    event.vendors.forEach((vendor, index) => {
      vendorsContent += `
        <tr>
          <td>${index + 1}</td>
          <td>${vendor.vendor_name}</td>
          <td>${vendor.vendor_type}</td>
          <td>${vendor.contact_number}</td>
          <td>${vendor.labourers ? vendor.labourers.length : 0}</td>
        </tr>
      `;
    });
    
    vendorsContent += `
          </tbody>
        </table>
    `;
    
    // Add laborers for each vendor if available
    event.vendors.forEach((vendor, index) => {
      if (vendor.labourers && vendor.labourers.length > 0) {
        vendorsContent += `
          <div class="mt-4">
            <h5>${vendor.vendor_name}'s Laborers</h5>
            <table class="event-details-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Contact</th>
                  <th>Morning</th>
                  <th>Evening</th>
                  <th>Wages</th>
                </tr>
              </thead>
              <tbody>
        `;
        
        vendor.labourers.forEach((labour, labourIndex) => {
          vendorsContent += `
            <tr>
              <td>${labourIndex + 1}</td>
              <td>${labour.labour_name}</td>
              <td>${labour.contact_number}</td>
              <td>${labour.morning_attendance ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
              <td>${labour.evening_attendance ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
              <td>${labour.wages ? '₹' + labour.wages.totalDay : 'N/A'}</td>
            </tr>
          `;
        });
        
        vendorsContent += `
              </tbody>
            </table>
          </div>
        `;
      }
    });
    
    vendorsContent += `</div>`;
    tabContents.push(vendorsContent);
  }
  
  // Company Laborers tab
  if (event.company_labours && event.company_labours.length > 0) {
    tabs.push(`<li class="nav-item">
      <a class="nav-link" id="labours-tab" data-toggle="tab" href="#labours" role="tab">
        <i class="fas fa-hard-hat"></i> Company Laborers (${event.company_labours.length})
      </a>
    </li>`);
    
    let laboursContent = `
      <div class="tab-pane fade" id="labours" role="tabpanel">
        <table class="event-details-table mt-3">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Contact</th>
              <th>Morning</th>
              <th>Evening</th>
              <th>Wages</th>
            </tr>
          </thead>
          <tbody>
    `;
    
    event.company_labours.forEach((labour, index) => {
      laboursContent += `
        <tr>
          <td>${index + 1}</td>
          <td>${labour.labour_name}</td>
          <td>${labour.contact_number}</td>
          <td>${labour.morning_attendance ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
          <td>${labour.evening_attendance ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
          <td>${labour.wages ? '₹' + labour.wages.grand_total : 'N/A'}</td>
        </tr>
      `;
    });
    
    laboursContent += `
          </tbody>
        </table>
      </div>
    `;
    tabContents.push(laboursContent);
  }
  
  // Beverages tab
  if (event.beverages && event.beverages.length > 0) {
    tabs.push(`<li class="nav-item">
      <a class="nav-link" id="beverages-tab" data-toggle="tab" href="#beverages" role="tab">
        <i class="fas fa-coffee"></i> Beverages (${event.beverages.length})
      </a>
    </li>`);
    
    let beveragesContent = `
      <div class="tab-pane fade" id="beverages" role="tabpanel">
        <table class="event-details-table mt-3">
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
      beveragesContent += `
        <tr>
          <td>${index + 1}</td>
          <td>${beverage.beverage_type}</td>
          <td>${beverage.beverage_name}</td>
          <td>₹${beverage.amount}</td>
        </tr>
      `;
    });
    
    beveragesContent += `
          </tbody>
        </table>
      </div>
    `;
    tabContents.push(beveragesContent);
  }
  
  // Work Progress tab
  if (event.work_progress && event.work_progress.length > 0) {
    tabs.push(`<li class="nav-item">
      <a class="nav-link" id="progress-tab" data-toggle="tab" href="#progress" role="tab">
        <i class="fas fa-tasks"></i> Work Progress (${event.work_progress.length})
      </a>
    </li>`);
    
    let progressContent = `
      <div class="tab-pane fade" id="progress" role="tabpanel">
    `;
    
    event.work_progress.forEach((work, index) => {
      progressContent += `
        <div class="card mt-3">
          <div class="card-header">
            <strong>${work.work_category} - ${work.work_type}</strong>
          </div>
          <div class="card-body">
            <p><strong>Work Done:</strong> ${work.work_done}</p>
            <p><strong>Remarks:</strong> ${work.remarks || 'None'}</p>
            
            ${work.media && work.media.length > 0 ? `
              <div class="event-details-media">
                ${work.media.map(media => {
                  const fileExt = media.file_name.split('.').pop().toLowerCase();
                  const mediaUrl = `fetch_media.php?id=${media.media_id}&type=work_progress`;
                  
                  if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                // Image file - use our custom image viewer instead of opening in new tab
                return `
                  <div class="event-details-media-item">
                    <img src="${mediaUrl}" alt="${media.file_name}" 
                      onclick="openImageViewer('${mediaUrl}', '${media.file_name}')" title="${media.file_name}">
                  </div>
                `;
                  } else if (['mp4', 'webm', 'ogg'].includes(fileExt)) {
                    // Video file
                    return `
                      <div class="event-details-media-item video-item">
                        <video controls width="100%" height="auto">
                          <source src="${mediaUrl}" type="video/${fileExt}">
                          Your browser does not support the video tag.
                        </video>
                        <div class="media-caption">${media.file_name}</div>
                      </div>
                    `;
                  } else if (fileExt === 'pdf') {
                    // PDF file - add more robust fallback handling
                    return `
                      <div class="event-details-media-item pdf-item">
                        <div class="pdf-preview" onclick="handlePdfClick('${mediaUrl}', '${media.file_name}', ${media.media_id}, 'work_progress')">
                          <i class="fas fa-file-pdf"></i>
                          <span>PDF Document</span>
                        </div>
                        <div class="media-caption">${media.file_name}</div>
                      </div>
                    `;
                  } else {
                    // Other file types
                    return `
                      <div class="event-details-media-item file-item">
                        <div class="file-preview" onclick="window.open('${mediaUrl}', '_blank')">
                          <i class="fas fa-file"></i>
                          <span>File</span>
                        </div>
                        <div class="media-caption">${media.file_name}</div>
                      </div>
                    `;
                  }
                }).join('')}
              </div>
            ` : ''}
          </div>
        </div>
      `;
    });
    
    progressContent += `</div>`;
    tabContents.push(progressContent);
  }
  
  // Inventory tab
  if (event.inventory && event.inventory.length > 0) {
    tabs.push(`<li class="nav-item">
      <a class="nav-link" id="inventory-tab" data-toggle="tab" href="#inventory" role="tab">
        <i class="fas fa-boxes"></i> Inventory (${event.inventory.length})
      </a>
    </li>`);
    
    let inventoryContent = `
      <div class="tab-pane fade" id="inventory" role="tabpanel">
        <table class="event-details-table mt-3">
          <thead>
            <tr>
              <th>#</th>
              <th>Type</th>
              <th>Material</th>
              <th>Quantity</th>
              <th>Unit</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
    `;
    
    event.inventory.forEach((item, index) => {
      inventoryContent += `
        <tr>
          <td>${index + 1}</td>
          <td>${item.inventory_type}</td>
          <td>${item.material_type}</td>
          <td>${item.quantity}</td>
          <td>${item.unit}</td>
          <td>${item.remarks || 'None'}</td>
        </tr>
      `;
    });
    
    inventoryContent += `
          </tbody>
        </table>
        
        ${event.inventory.some(item => item.media && item.media.length > 0) ? `
          <div class="inventory-media-section">
          <h5 class="mt-3">Inventory Media</h5>
            
            <!-- Bills Section -->
            ${event.inventory.some(item => item.media && item.media.some(media => media.media_type === 'bill')) ? `
              <div class="media-category">
                <h6 class="media-category-title"><i class="fas fa-file-invoice"></i> Bills</h6>
          <div class="event-details-media">
            ${event.inventory.flatMap(item => 
                    item.media ? item.media.filter(media => media.media_type === 'bill').map(media => {
                      const fileExt = media.file_name.split('.').pop().toLowerCase();
                      const mediaUrl = `fetch_media.php?id=${media.media_id}&type=inventory`;
                      
                      if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                        // Image file - use our custom image viewer instead of opening in new tab
                        return `
                <div class="event-details-media-item">
                            <div class="media-type-tag">Bill</div>
                            <img src="${mediaUrl}" alt="${media.file_name}" 
                              onclick="openImageViewer('${mediaUrl}', '${media.file_name}')" title="${media.file_name}">
                            <div class="media-caption">${media.file_name}</div>
                </div>
                        `;
                      } else if (['mp4', 'webm', 'ogg'].includes(fileExt)) {
                        // Video file
                        return `
                          <div class="event-details-media-item video-item">
                            <div class="media-type-tag">Bill</div>
                            <video controls width="100%" height="auto">
                              <source src="${mediaUrl}" type="video/${fileExt}">
                              Your browser does not support the video tag.
                            </video>
                            <div class="media-caption">${media.file_name}</div>
                          </div>
                        `;
                      } else if (fileExt === 'pdf') {
                        // PDF file - add more robust fallback handling
                        return `
                          <div class="event-details-media-item pdf-item">
                            <div class="media-type-tag">Bill</div>
                            <div class="pdf-preview" onclick="handlePdfClick('${mediaUrl}', '${media.file_name}', ${media.media_id}, 'inventory')">
                              <i class="fas fa-file-pdf"></i>
                              <span>PDF Document</span>
                            </div>
                            <div class="media-caption">${media.file_name}</div>
                          </div>
                        `;
                      } else {
                        // Other file types
                        return `
                          <div class="event-details-media-item file-item">
                            <div class="media-type-tag">Bill</div>
                            <div class="file-preview" onclick="window.open('${mediaUrl}', '_blank')">
                              <i class="fas fa-file"></i>
                              <span>File</span>
                            </div>
                            <div class="media-caption">${media.file_name}</div>
                          </div>
                        `;
                      }
                    }) : []
            ).join('')}
                </div>
              </div>
            ` : ''}
            
            <!-- Other Media Section -->
            ${event.inventory.some(item => item.media && item.media.some(media => media.media_type !== 'bill')) ? `
              <div class="media-category">
                <h6 class="media-category-title"><i class="fas fa-images"></i> Photos & Videos</h6>
                <div class="event-details-media">
                  ${event.inventory.flatMap(item => 
                    item.media ? item.media.filter(media => media.media_type !== 'bill').map(media => {
                      const fileExt = media.file_name.split('.').pop().toLowerCase();
                      const mediaUrl = `fetch_media.php?id=${media.media_id}&type=inventory`;
                      
                      if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                        // Image file - use our custom image viewer instead of opening in new tab
                        return `
                          <div class="event-details-media-item">
                            <div class="media-type-tag">${media.media_type === 'photo' ? 'Photo' : 'Media'}</div>
                            <img src="${mediaUrl}" alt="${media.file_name}" 
                              onclick="openImageViewer('${mediaUrl}', '${media.file_name}')" title="${media.file_name}">
                            <div class="media-caption">${media.file_name}</div>
                          </div>
                        `;
                      } else if (['mp4', 'webm', 'ogg'].includes(fileExt)) {
                        // Video file
                        return `
                          <div class="event-details-media-item video-item">
                            <div class="media-type-tag">Video</div>
                            <video controls width="100%" height="auto">
                              <source src="${mediaUrl}" type="video/${fileExt}">
                              Your browser does not support the video tag.
                            </video>
                            <div class="media-caption">${media.file_name}</div>
                          </div>
                        `;
                      } else if (fileExt === 'pdf') {
                        // PDF file - add more robust fallback handling
                        return `
                          <div class="event-details-media-item pdf-item">
                            <div class="media-type-tag">Document</div>
                            <div class="pdf-preview" onclick="handlePdfClick('${mediaUrl}', '${media.file_name}', ${media.media_id}, 'inventory')">
                              <i class="fas fa-file-pdf"></i>
                              <span>PDF Document</span>
                            </div>
                            <div class="media-caption">${media.file_name}</div>
                          </div>
                        `;
                      } else {
                        // Other file types
                        return `
                          <div class="event-details-media-item file-item">
                            <div class="media-type-tag">File</div>
                            <div class="file-preview" onclick="window.open('${mediaUrl}', '_blank')">
                              <i class="fas fa-file"></i>
                              <span>File</span>
                            </div>
                            <div class="media-caption">${media.file_name}</div>
                          </div>
                        `;
                      }
                    }) : []
                  ).join('')}
                </div>
              </div>
            ` : ''}
          </div>
        ` : ''}
      </div>
    `;
    tabContents.push(inventoryContent);
  }
  
  // Add tabs navigation
  html += `
    <ul class="nav nav-tabs event-details-tabs" id="eventDetailsTabs" role="tablist">
      ${tabs.join('')}
    </ul>
    <div class="tab-content event-details-tab-content">
      ${tabContents.join('')}
    </div>
  `;
  
  // Set content
  content.innerHTML = html;
  
  // Initialize Bootstrap tabs
  $('#eventDetailsTabs a').on('click', function(e) {
    e.preventDefault();
    $(this).tab('show');
  });
  
  // Add event listener for Excel export button
  const exportButton = document.getElementById('exportEventExcel');
  if (exportButton) {
    exportButton.addEventListener('click', function() {
      exportEventToExcel(event);
    });
  }
  
  // Handle any nested modals
  handleNestedModals();
}

/**
 * Export event data to Excel
 */
function exportEventToExcel(event) {
  // Create workbook
  const wb = XLSX.utils.book_new();
  wb.Props = {
    Title: `Event Details - ${event.title}`,
    Subject: "Event Export",
    Author: "HR System",
    CreatedDate: new Date()
  };
  
  // Add Overview worksheet with more detailed summary
  const overviewData = [
    ["Event Details"],
    ["Title", event.title],
    ["Date", event.event_date],
    ["Type", event.event_type],
    ["Site", event.site_name],
    ["Created By", event.created_by_name],
    ["Created At", event.created_at],
    [""],
    ["Summary Counts"],
    ["Vendors", event.counts.vendors],
    ["Company Laborers", event.counts.company_labours],
    ["Beverages", event.counts.beverages],
    ["Work Progress Items", event.counts.work_progress],
    ["Inventory Items", event.counts.inventory],
    [""]
  ];
  
  // Add vendor summary details
  if (event.vendors && event.vendors.length > 0) {
    overviewData.push(["Vendor Summary"]);
    overviewData.push(["Vendor Name", "Type", "Contact", "Laborers"]);
    
    event.vendors.forEach(vendor => {
      overviewData.push([
        vendor.vendor_name,
        vendor.vendor_type,
        vendor.contact_number,
        vendor.labourers ? vendor.labourers.length : 0
      ]);
    });
    overviewData.push([""]);
  }
  
  // Add labor summary details
  const allLabors = [];
  const vendorLaborRows = [];
  const companyLaborRows = [];
  
  // Add vendor laborers to summary
  if (event.vendors) {
    event.vendors.forEach(vendor => {
      if (vendor.labourers && vendor.labourers.length > 0) {
        vendor.labourers.forEach(labour => {
          allLabors.push({
            type: "Vendor Labor",
            vendor: vendor.vendor_name,
            name: labour.labour_name,
            contact: labour.contact_number || "N/A",
            morning: labour.morning_attendance ? "Present" : "Absent",
            evening: labour.evening_attendance ? "Present" : "Absent",
            wage: labour.wages ? labour.wages.totalDay || "N/A" : "N/A",
            isVendorLabor: true
          });
        });
      }
    });
  }
  
  // Add company laborers to summary
  if (event.company_labours) {
    event.company_labours.forEach(labour => {
      allLabors.push({
        type: "Company Labor",
        vendor: "Company",
        name: labour.labour_name,
        contact: labour.contact_number || "N/A",
        morning: labour.morning_attendance ? "Present" : "Absent",
        evening: labour.evening_attendance ? "Present" : "Absent",
        wage: labour.wages ? labour.wages.grand_total || "N/A" : "N/A",
        isVendorLabor: false
      });
    });
  }
  
  // Add labor summary to overview
  const laborSummaryStartRow = overviewData.length;
  if (allLabors.length > 0) {
    overviewData.push(["Labor Summary"]);
    overviewData.push(["Type", "Name", "Vendor/Company", "Contact", "Morning Attendance", "Evening Attendance", "Wage"]);
    
    allLabors.forEach((labor, index) => {
      overviewData.push([
        labor.type,
        labor.name,
        labor.vendor,
        labor.contact,
        labor.morning,
        labor.evening,
        labor.wage
      ]);
      
      // Track row for styling (add 2 for header rows)
      const rowIndex = laborSummaryStartRow + 2 + index;
      if (labor.isVendorLabor) {
        vendorLaborRows.push(rowIndex);
      } else {
        companyLaborRows.push(rowIndex);
      }
    });
  }
  
  const overviewWS = XLSX.utils.aoa_to_sheet(overviewData);
  
  // Apply styling to the overview sheet
  const range = XLSX.utils.decode_range(overviewWS['!ref']);
  
  // Make headers bold
  for (let C = range.s.c; C <= range.e.c; ++C) {
    const address = XLSX.utils.encode_col(C) + "1";
    if (!overviewWS[address]) continue;
    overviewWS[address].s = { font: { bold: true } };
  }
  
  // Apply section header styling
  const sectionHeaders = [0, 8, 16]; // Row indices for section headers
  if (event.vendors && event.vendors.length > 0) {
    sectionHeaders.push(16 + event.vendors.length + 2);
  }
  if (allLabors.length > 0) {
    sectionHeaders.push(laborSummaryStartRow);
  }
  
  sectionHeaders.forEach(row => {
    for (let C = range.s.c; C <= range.e.c; ++C) {
      const address = XLSX.utils.encode_cell({r: row, c: C});
      if (!overviewWS[address]) continue;
      overviewWS[address].s = { 
        font: { bold: true, sz: 14 },
        fill: { fgColor: { rgb: "EEEEEE" } }
      };
    }
  });
  
  // Apply labor summary header styling
  if (allLabors.length > 0) {
    const laborHeaderRow = laborSummaryStartRow + 1;
    for (let C = 0; C <= range.e.c; ++C) {
      const address = XLSX.utils.encode_cell({r: laborHeaderRow, c: C});
      if (!overviewWS[address]) continue;
      overviewWS[address].s = { 
        font: { bold: true, color: { rgb: "FFFFFF" } },
        fill: { fgColor: { rgb: "2F75B5" } }
      };
    }
  }
  
  // Apply color to vendor labor rows
  vendorLaborRows.forEach(row => {
    for (let C = 0; C <= range.e.c; ++C) {
      const address = XLSX.utils.encode_cell({r: row, c: C});
      if (!overviewWS[address]) continue;
      overviewWS[address].s = overviewWS[address].s || {};
      overviewWS[address].s.fill = { fgColor: { rgb: "D9E1F2" } }; // Light blue background
    }
  });
  
  // Apply color to company labor rows
  companyLaborRows.forEach(row => {
    for (let C = 0; C <= range.e.c; ++C) {
      const address = XLSX.utils.encode_cell({r: row, c: C});
      if (!overviewWS[address]) continue;
      overviewWS[address].s = overviewWS[address].s || {};
      overviewWS[address].s.fill = { fgColor: { rgb: "F8CCCC" } }; // Light red background
    }
  });
  
  // Set column widths for better readability
  const cols = [];
  for (let i = 0; i <= range.e.c; ++i) cols.push({ wch: 20 });
  overviewWS['!cols'] = cols;
  
  XLSX.utils.book_append_sheet(wb, overviewWS, "Overview");
  
  // Add Vendors worksheet with detailed information
  if (event.vendors && event.vendors.length > 0) {
    const vendorHeaders = ["#", "Vendor Name", "Type", "Contact Number", "Email", "Address", "Laborers Count"];
    const vendorData = [vendorHeaders];
    
    event.vendors.forEach((vendor, index) => {
      vendorData.push([
        index + 1,
        vendor.vendor_name,
        vendor.vendor_type,
        vendor.contact_number,
        vendor.email || "N/A",
        vendor.address || "N/A",
        vendor.labourers ? vendor.labourers.length : 0
      ]);
    });
    
    const vendorWS = XLSX.utils.aoa_to_sheet(vendorData);
    
    // Style vendor headers
    for (let C = 0; C < vendorHeaders.length; ++C) {
      const address = XLSX.utils.encode_col(C) + "1";
      if (!vendorWS[address]) continue;
      vendorWS[address].s = { 
        font: { bold: true, color: { rgb: "FFFFFF" } },
        fill: { fgColor: { rgb: "4472C4" } } // Blue header
      };
    }
    
    XLSX.utils.book_append_sheet(wb, vendorWS, "Vendors");
    
    // Add vendor laborers with detailed attendance and payment info
    if (event.vendors.some(v => v.labourers && v.labourers.length > 0)) {
      const vendorLabourHeaders = [
        "Vendor Name", 
        "Labour Name", 
        "Contact Number", 
        "ID/Aadhar", 
        "Morning Attendance", 
        "Evening Attendance", 
        "Basic Wage", 
        "Overtime", 
        "Total Day Wage"
      ];
      const vendorLabourData = [vendorLabourHeaders];
      
      event.vendors.forEach(vendor => {
        if (vendor.labourers && vendor.labourers.length > 0) {
          vendor.labourers.forEach(labour => {
            vendorLabourData.push([
              vendor.vendor_name,
              labour.labour_name,
              labour.contact_number || "N/A",
              labour.id_number || "N/A",
              labour.morning_attendance ? "Present" : "Absent",
              labour.evening_attendance ? "Present" : "Absent",
              labour.wages ? labour.wages.basic || "N/A" : "N/A",
              labour.wages ? labour.wages.overtime || "0" : "0",
              labour.wages ? labour.wages.totalDay || "N/A" : "N/A"
            ]);
          });
        }
      });
      
      const vendorLabourWS = XLSX.utils.aoa_to_sheet(vendorLabourData);
      
      // Style vendor labor headers
      for (let C = 0; C < vendorLabourHeaders.length; ++C) {
        const address = XLSX.utils.encode_col(C) + "1";
        if (!vendorLabourWS[address]) continue;
        vendorLabourWS[address].s = { 
          font: { bold: true, color: { rgb: "FFFFFF" } },
          fill: { fgColor: { rgb: "4472C4" } } // Blue header
        };
      }
      
      // Apply blue background to all vendor labor rows
      const vendorLabourRange = XLSX.utils.decode_range(vendorLabourWS['!ref']);
      for (let R = 1; R <= vendorLabourRange.e.r; ++R) { // Start from row 1 (skip header)
        for (let C = 0; C <= vendorLabourRange.e.c; ++C) {
          const address = XLSX.utils.encode_cell({r: R, c: C});
          if (!vendorLabourWS[address]) continue;
          
          vendorLabourWS[address].s = vendorLabourWS[address].s || {};
          vendorLabourWS[address].s.fill = { fgColor: { rgb: "D9E1F2" } }; // Light blue background
        }
      }
      
      XLSX.utils.book_append_sheet(wb, vendorLabourWS, "Vendor Laborers");
    }
  }
  
  // Add Company Laborers worksheet with detailed information
  if (event.company_labours && event.company_labours.length > 0) {
    const labourHeaders = [
      "#", 
      "Name", 
      "Employee ID", 
      "Contact Number", 
      "ID/Aadhar", 
      "Morning Attendance", 
      "Evening Attendance", 
      "Basic Wage", 
      "Overtime Hours", 
      "Overtime Pay", 
      "Allowances", 
      "Total Wage"
    ];
    const labourData = [labourHeaders];
    
    event.company_labours.forEach((labour, index) => {
      labourData.push([
        index + 1,
        labour.labour_name,
        labour.employee_id || "N/A",
        labour.contact_number || "N/A",
        labour.id_number || "N/A",
        labour.morning_attendance ? "Present" : "Absent",
        labour.evening_attendance ? "Present" : "Absent",
        labour.wages ? labour.wages.basic || "N/A" : "N/A",
        labour.wages ? labour.wages.overtime_hours || "0" : "0",
        labour.wages ? labour.wages.overtime_pay || "0" : "0",
        labour.wages ? labour.wages.allowances || "0" : "0",
        labour.wages ? labour.wages.grand_total || "N/A" : "N/A"
      ]);
    });
    
    const labourWS = XLSX.utils.aoa_to_sheet(labourData);
    
    // Style company labor headers
    for (let C = 0; C < labourHeaders.length; ++C) {
      const address = XLSX.utils.encode_col(C) + "1";
      if (!labourWS[address]) continue;
      labourWS[address].s = { 
        font: { bold: true, color: { rgb: "FFFFFF" } },
        fill: { fgColor: { rgb: "C00000" } } // Red header
      };
    }
    
    // Apply red background to all company labor rows
    const labourRange = XLSX.utils.decode_range(labourWS['!ref']);
    for (let R = 1; R <= labourRange.e.r; ++R) { // Start from row 1 (skip header)
      for (let C = 0; C <= labourRange.e.c; ++C) {
        const address = XLSX.utils.encode_cell({r: R, c: C});
        if (!labourWS[address]) continue;
        
        labourWS[address].s = labourWS[address].s || {};
        labourWS[address].s.fill = { fgColor: { rgb: "F8CCCC" } }; // Light red background
      }
    }
    
    XLSX.utils.book_append_sheet(wb, labourWS, "Company Laborers");
  }
  
  // Add All Attendance Summary worksheet with colored rows
  const allAttendanceHeaders = ["Type", "Name", "Vendor/Company", "Contact", "Morning Attendance", "Evening Attendance", "Full Day", "Wage"];
  const allAttendanceData = [allAttendanceHeaders];
  
  // Add vendor laborers to attendance summary
  if (event.vendors) {
    event.vendors.forEach(vendor => {
      if (vendor.labourers && vendor.labourers.length > 0) {
        vendor.labourers.forEach(labour => {
          allAttendanceData.push([
            "Vendor Labour",
            labour.labour_name,
            vendor.vendor_name,
            labour.contact_number || "N/A",
            labour.morning_attendance ? "Present" : "Absent",
            labour.evening_attendance ? "Present" : "Absent",
            (labour.morning_attendance && labour.evening_attendance) ? "Present" : "Partial/Absent",
            labour.wages ? labour.wages.totalDay || "N/A" : "N/A"
          ]);
        });
      }
    });
  }
  
  // Add company laborers to attendance summary
  if (event.company_labours) {
    event.company_labours.forEach(labour => {
      allAttendanceData.push([
        "Company Labour",
        labour.labour_name,
        "Company",
        labour.contact_number || "N/A",
        labour.morning_attendance ? "Present" : "Absent",
        labour.evening_attendance ? "Present" : "Absent",
        (labour.morning_attendance && labour.evening_attendance) ? "Present" : "Partial/Absent",
        labour.wages ? labour.wages.grand_total || "N/A" : "N/A"
      ]);
    });
  }
  
  // Add attendance summary worksheet if we have data
  if (allAttendanceData.length > 1) {
    const attendanceWS = XLSX.utils.aoa_to_sheet(allAttendanceData);
    
    // Style attendance summary headers
    for (let C = 0; C < allAttendanceHeaders.length; ++C) {
      const address = XLSX.utils.encode_col(C) + "1";
      if (!attendanceWS[address]) continue;
      attendanceWS[address].s = { 
        font: { bold: true, color: { rgb: "FFFFFF" } },
        fill: { fgColor: { rgb: "2F75B5" } } // Dark blue header
      };
    }
    
    // Color rows based on labor type
    const attendanceRange = XLSX.utils.decode_range(attendanceWS['!ref']);
    for (let R = 1; R <= attendanceRange.e.r; ++R) { // Start from row 1 (skip header)
      // Check if this is vendor or company labor
      const typeCell = XLSX.utils.encode_cell({r: R, c: 0});
      if (attendanceWS[typeCell] && attendanceWS[typeCell].v === "Vendor Labour") {
        // Color vendor labor rows blue
        for (let C = 0; C <= attendanceRange.e.c; ++C) {
          const address = XLSX.utils.encode_cell({r: R, c: C});
          if (!attendanceWS[address]) continue;
          
          attendanceWS[address].s = attendanceWS[address].s || {};
          attendanceWS[address].s.fill = { fgColor: { rgb: "D9E1F2" } }; // Light blue background
        }
      } else if (attendanceWS[typeCell] && attendanceWS[typeCell].v === "Company Labour") {
        // Color company labor rows red
        for (let C = 0; C <= attendanceRange.e.c; ++C) {
          const address = XLSX.utils.encode_cell({r: R, c: C});
          if (!attendanceWS[address]) continue;
          
          attendanceWS[address].s = attendanceWS[address].s || {};
          attendanceWS[address].s.fill = { fgColor: { rgb: "F8CCCC" } }; // Light red background
        }
      }
    }
    
    XLSX.utils.book_append_sheet(wb, attendanceWS, "Attendance Summary");
  }
  
  // Add Beverages worksheet if available
  if (event.beverages && event.beverages.length > 0) {
    const beverageHeaders = ["#", "Type", "Name", "Quantity", "Unit Price", "Amount", "Supplier"];
    const beverageData = [beverageHeaders];
    
    event.beverages.forEach((beverage, index) => {
      beverageData.push([
        index + 1,
        beverage.beverage_type,
        beverage.beverage_name,
        beverage.quantity || "1",
        beverage.unit_price || beverage.amount,
        beverage.amount,
        beverage.supplier || "N/A"
      ]);
    });
    
    const beverageWS = XLSX.utils.aoa_to_sheet(beverageData);
    XLSX.utils.book_append_sheet(wb, beverageWS, "Beverages");
  }
  
  // Add Work Progress worksheet if available
  if (event.work_progress && event.work_progress.length > 0) {
    const workProgressHeaders = ["#", "Category", "Type", "Work Done", "Completion %", "Supervisor", "Remarks"];
    const workProgressData = [workProgressHeaders];
    
    event.work_progress.forEach((work, index) => {
      workProgressData.push([
        index + 1,
        work.work_category,
        work.work_type,
        work.work_done,
        work.completion_percentage || "N/A",
        work.supervisor || "N/A",
        work.remarks || "None"
      ]);
    });
    
    const workProgressWS = XLSX.utils.aoa_to_sheet(workProgressData);
    XLSX.utils.book_append_sheet(wb, workProgressWS, "Work Progress");
  }
  
  // Add Inventory worksheet if available
  if (event.inventory && event.inventory.length > 0) {
    const inventoryHeaders = ["#", "Type", "Material", "Quantity", "Unit", "Unit Price", "Total Value", "Supplier", "Remarks"];
    const inventoryData = [inventoryHeaders];
    
    event.inventory.forEach((item, index) => {
      inventoryData.push([
        index + 1,
        item.inventory_type,
        item.material_type,
        item.quantity,
        item.unit,
        item.unit_price || "N/A",
        item.total_value || "N/A",
        item.supplier || "N/A",
        item.remarks || "None"
      ]);
    });
    
    const inventoryWS = XLSX.utils.aoa_to_sheet(inventoryData);
    XLSX.utils.book_append_sheet(wb, inventoryWS, "Inventory");
  }
  
  // Generate Excel file name
  const fileName = `Event_${event.title.replace(/[^a-z0-9]/gi, '_')}_${event.event_date}.xlsx`;
  
  // Export to Excel file
  XLSX.writeFile(wb, fileName);
  
  // Show success notification
  showNotification('Excel file exported successfully!', 'success');
}

/**
 * Close the event details modal
 */
function closeEventDetailsModal() {
  const modal = document.getElementById('eventDetailsModal');
  if (modal) {
    modal.classList.remove('show');
  }
  
  // Stop any videos that might be playing
  const videos = document.querySelectorAll('.event-details-media-item video');
  videos.forEach(video => {
    if (!video.paused) {
      video.pause();
    }
  });
}

/**
 * Function to ensure nested modals are properly handled
 * This should be called after the modal content is rendered
 */
function handleNestedModals() {
  // Find any nested Bootstrap modals and disable them
  const nestedModals = document.querySelectorAll('#eventDetailsModal .modal');
  nestedModals.forEach(nestedModal => {
    // Add class to identify these as nested modals
    nestedModal.classList.add('nested-modal');
    
    // Add close buttons to all nested modals
    const closeButtons = nestedModal.querySelectorAll('.close, [data-dismiss="modal"]');
    closeButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        nestedModal.style.display = 'none';
      });
    });
    
    // Hide the nested modal
    nestedModal.style.display = 'none';
  });
  
  // Check specifically for the events listing modal which is causing issues
  const eventsListModal = document.querySelector('#eventDetailsModal [id*="events-for"]');
  if (eventsListModal) {
    eventsListModal.style.display = 'none';
    
    // Add a close button if not already present
    if (!eventsListModal.querySelector('.close-nested-modal')) {
      const closeBtn = document.createElement('button');
      closeBtn.className = 'close close-nested-modal';
      closeBtn.innerHTML = '&times;';
      closeBtn.style.position = 'absolute';
      closeBtn.style.right = '10px';
      closeBtn.style.top = '10px';
      closeBtn.style.zIndex = '1000';
      closeBtn.addEventListener('click', function() {
        eventsListModal.style.display = 'none';
      });
      
      if (eventsListModal.firstChild) {
        eventsListModal.insertBefore(closeBtn, eventsListModal.firstChild);
      } else {
        eventsListModal.appendChild(closeBtn);
      }
    }
  }
}

/**
 * Initialize the image viewer functionality
 */
function initImageViewer() {
  const overlay = document.getElementById('imageViewerOverlay');
  const closeBtn = document.getElementById('imageViewerClose');
  const image = document.getElementById('imageViewerImage');
  
  // Close on X button click
  if (closeBtn) {
    closeBtn.addEventListener('click', function() {
      closeImageViewer();
    });
  }
  
  // Close on overlay click (but not on image)
  if (overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) {
        closeImageViewer();
      }
    });
  }
  
  // Close on ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && overlay && overlay.classList.contains('show')) {
      closeImageViewer();
    }
  });
}

/**
 * Open the image viewer with the specified image
 */
function openImageViewer(imageUrl, caption) {
  const overlay = document.getElementById('imageViewerOverlay');
  const image = document.getElementById('imageViewerImage');
  const captionEl = document.getElementById('imageViewerCaption');
  
  if (overlay && image) {
    // Set image source
    image.src = imageUrl;
    
    // Set caption if provided
    if (captionEl) {
      captionEl.textContent = caption || '';
      captionEl.style.display = caption ? 'block' : 'none';
    }
    
    // Show overlay
    overlay.classList.add('show');
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
  }
}

/**
 * Close the image viewer
 */
function closeImageViewer() {
  const overlay = document.getElementById('imageViewerOverlay');
  
  if (overlay) {
    overlay.classList.remove('show');
    
    // Restore body scrolling
    document.body.style.overflow = '';
    
    // Clear image source after animation completes
    setTimeout(() => {
      const image = document.getElementById('imageViewerImage');
      if (image) {
        image.src = '';
      }
    }, 300);
  }
}

/**
 * Handle PDF click with fallbacks
 * This function tries multiple approaches to open a PDF file
 */
function handlePdfClick(mediaUrl, fileName, mediaId, mediaType) {
  // First try: Open in new tab
  const pdfWindow = window.open(mediaUrl, '_blank');
  
  // Check if window was blocked or failed to open
  setTimeout(() => {
    if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed === 'undefined') {
      console.log('PDF window was blocked or failed to open. Trying alternative methods...');
      
      // Second try: Create a temporary anchor and trigger click
      const link = document.createElement('a');
      link.href = mediaUrl;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      // Show a fallback message to the user if needed
      const fallbackMessage = `
        <div class="pdf-fallback-message">
          <p>If the PDF doesn't open automatically, you can:</p>
          <ul>
            <li><a href="${mediaUrl}" target="_blank">Click here to open in a new tab</a></li>
            <li><a href="${mediaUrl}" download="${fileName}">Click here to download the file</a></li>
            <li><a href="download_pdf.php?id=${mediaId}&type=${mediaType}" target="_blank">Try alternative download method</a></li>
          </ul>
        </div>
      `;
      
      // Create a modal or toast to display the fallback message
      showPdfFallbackMessage(fallbackMessage);
    }
  }, 1000);
}

/**
 * Show PDF fallback message to the user
 */
function showPdfFallbackMessage(message) {
  // Check if a fallback message already exists
  let fallbackEl = document.getElementById('pdfFallbackMessage');
  
  if (!fallbackEl) {
    // Create fallback element
    fallbackEl = document.createElement('div');
    fallbackEl.id = 'pdfFallbackMessage';
    fallbackEl.className = 'pdf-fallback-container';
    fallbackEl.innerHTML = `
      <div class="pdf-fallback-content">
        <div class="pdf-fallback-header">
          <h4>PDF Viewer</h4>
          <button type="button" class="close" onclick="document.getElementById('pdfFallbackMessage').remove();">&times;</button>
        </div>
        <div class="pdf-fallback-body"></div>
      </div>
    `;
    document.body.appendChild(fallbackEl);
  }
  
  // Update message content
  const bodyEl = fallbackEl.querySelector('.pdf-fallback-body');
  if (bodyEl) {
    bodyEl.innerHTML = message;
  }
  
  // Show the fallback message
  fallbackEl.style.display = 'flex';
  
  // Auto-hide after 10 seconds
  setTimeout(() => {
    if (document.body.contains(fallbackEl)) {
      fallbackEl.style.opacity = '0';
      setTimeout(() => {
        if (document.body.contains(fallbackEl)) {
          fallbackEl.remove();
        }
      }, 500);
    }
  }, 10000);
} 