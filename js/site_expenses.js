// Add this function to safely get DOM elements
function safeQuerySelector(selector) {
    const element = document.querySelector(selector);
    return element;
}

// Function to toggle sidebar panel
function togglePanel() {
    const leftPanel = document.getElementById('leftPanel');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (leftPanel) {
        leftPanel.classList.toggle('collapsed');
        if (leftPanel.classList.contains('collapsed')) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        } else {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize totals
    if (typeof updateTotalWages === 'function') {
        updateTotalWages();
        updateTravellingAllowancesTotal();
        updateBeveragesTotal();
        updateMiscExpensesTotal();
        updateGrandTotal();
    }

    // Initialize counters
    window.vendorCounter = 0;
    window.labourCounter = 0;
    window.companyLabourCounter = 0;
    window.travelAllowanceCounter = 0;
    window.beverageCounter = 0;
    window.workProgressCounter = 0;
    window.inventoryCounter = 0;
    
    // Initialize custom scroll function
    window.smoothScrollToElement = function(element, offset = 50) {
        if (!element) return;
        
        // Get element position
        const rect = element.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const targetTop = rect.top + scrollTop - offset;
        
        // Scroll to element
        window.scrollTo({
            top: targetTop,
            behavior: 'smooth'
        });
    };

    // Toggle sidebar on small screens
    const menuItems = document.querySelectorAll('.menu-item');
    if (menuItems) {
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    const leftPanel = document.querySelector('.left-panel');
                    if (leftPanel) {
                        leftPanel.classList.toggle('collapsed');
                    }
                }
            });
        });
    }
    
    // Highlight active menu item
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.menu-item');
    
    if (menuLinks) {
        menuLinks.forEach(link => {
            if (link.getAttribute('onclick') && 
                link.getAttribute('onclick').includes(currentPath.split('/').pop())) {
                link.classList.add('active');
            }
        });
    }
    
    // Modal functionality
    const updateDetailsModal = safeQuerySelector('#updateDetailsModal');
    const closeBtn = safeQuerySelector('.close-modal');
    const siteUpdateModal = safeQuerySelector('#siteUpdateModal');
    
    if (closeBtn) {
        closeBtn.onclick = function() {
            if (updateDetailsModal) {
                updateDetailsModal.style.display = 'none';
            }
        }
    }
    
    // Event delegation for window clicks
    window.onclick = function(event) {
        if (updateDetailsModal && event.target === updateDetailsModal) {
            updateDetailsModal.style.display = 'none';
        } else if (siteUpdateModal && event.target === siteUpdateModal) {
            if (typeof hideSiteUpdateModal === 'function') {
                hideSiteUpdateModal();
            }
        }
    }

    // Function to handle Civil Work selection and auto-scroll to buttons
    function handleCivilWorkSelection() {
        // Update selector to match the actual Civil Work type dropdown ID or selector
        const civilWorkTypeSelect = document.querySelector('select[name="civil-work-type"]');
        
        if (civilWorkTypeSelect) {
            civilWorkTypeSelect.addEventListener('change', function() {
                // Get the work progress buttons container
                const workProgressButtons = document.querySelector('.work-progress-buttons');
                
                if (workProgressButtons) {
                    // Scroll to the buttons section with smooth animation
                    workProgressButtons.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
        
        // Also add click event for the Civil Work header/title
        const civilWorkHeader = document.querySelector('.work-progress-container .item-header h4');
        if (civilWorkHeader) {
            civilWorkHeader.addEventListener('click', function() {
                const workProgressButtons = document.querySelector('.work-progress-buttons');
                if (workProgressButtons) {
                    workProgressButtons.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    }

    // Call the function when document is ready
    handleCivilWorkSelection();

    // Remove this duplicate click handler for the Add Civil Work button
    // The btn-add-civil click handler is now properly managed in setupCivilWorkHandlers
    /*
    const addCivilWorkBtn = document.querySelector('.btn-add-civil');
    if (addCivilWorkBtn) {
        addCivilWorkBtn.addEventListener('click', function() {
            // Set a small timeout to let the modal open first
            setTimeout(setupWorkProgressItemEvents, 100);
        });
    }
    */
});

// Add vendor
function addVendor() {
    window.vendorCounter++;
    const vendorsContainer = document.getElementById('vendors-container');
    
    const vendorDiv = document.createElement('div');
    vendorDiv.className = 'vendor-container';
    vendorDiv.id = `vendor-${window.vendorCounter}`;
    
    vendorDiv.innerHTML = `
        <div class="vendor-header">
            <div class="vendor-type-select">
                <label for="vendor-type-${window.vendorCounter}">Vendor Service</label>
                <select class="form-control vendor-type-select" id="vendor-type-${window.vendorCounter}" name="vendors[${window.vendorCounter}][type]" required>
                    <option value="">Select Vendor Type</option>
                    <option value="POP">POP</option>
                    <option value="Tile">Tile</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Plumbing">Plumbing</option>
                    <option value="Carpentry">Carpentry</option>
                    <option value="Painting">Painting</option>
                    <option value="HVAC">HVAC</option>
                    <option value="Flooring">Flooring</option>
                    <option value="Roofing">Roofing</option>
                    <option value="Masonry">Masonry</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <button type="button" class="remove-btn" onclick="removeVendor(${window.vendorCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="form-group">
            <label for="vendor-name-${window.vendorCounter}">Vendor Name</label>
            <input type="text" class="form-control vendor-name-input" id="vendor-name-${window.vendorCounter}" name="vendors[${window.vendorCounter}][name]" required autocomplete="off">
            <div class="search-results" id="vendor-search-results-${window.vendorCounter}"></div>
        </div>
        <div class="form-group">
            <label for="vendor-contact-${window.vendorCounter}">Contact Number</label>
            <input type="text" class="form-control" id="vendor-contact-${window.vendorCounter}" name="vendors[${window.vendorCounter}][contact]">
        </div>
        
        <div class="vendor-labours" id="vendor-labours-${window.vendorCounter}">
            <!-- Labours will be added here -->
        </div>
        <button type="button" class="btn-add-item" onclick="addLabour(${window.vendorCounter})">
            <i class="fas fa-plus"></i> Add Labour
        </button>
    `;
    
    vendorsContainer.appendChild(vendorDiv);
    
    // Initialize autocomplete for vendor name input
    initVendorAutocomplete(window.vendorCounter);
}

// Initialize autocomplete for vendor name field
function initVendorAutocomplete(vendorId) {
    const vendorNameInput = document.getElementById(`vendor-name-${vendorId}`);
    const vendorTypeSelect = document.getElementById(`vendor-type-${vendorId}`);
    const vendorContactInput = document.getElementById(`vendor-contact-${vendorId}`);
    
    // Setup autocomplete for vendor name field
    $(vendorNameInput).autocomplete({
        source: function(request, response) {
            // Make AJAX call to get vendor data
            $.ajax({
                url: 'site_expenses.php',
                dataType: 'json',
                data: {
                    action: 'get_vendor_labour_data',
                    type: 'vendor',
                    term: request.term
                },
                success: function(data) {
                    if (data.success) {
                        response($.map(data.data, function(item) {
                            return {
                                label: item.name + ' (' + item.type + ')',
                                value: item.name,
                                vendor: item
                            };
                        }));
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            // Auto-fill vendor details
            const vendor = ui.item.vendor;
            
            // Set vendor type
            if (vendor.type) {
                vendorTypeSelect.value = vendor.type;
            }
            
            // Set contact
            if (vendor.contact) {
                vendorContactInput.value = vendor.contact;
            }
            
            return true;
        }
    });
    
    // Also trigger search when vendor type changes
    vendorTypeSelect.addEventListener('change', function() {
        if (vendorNameInput.value.length > 0) {
            $(vendorNameInput).autocomplete('search', vendorNameInput.value);
        }
    });
}

// Remove vendor
function removeVendor(id) {
    const vendorDiv = document.getElementById(`vendor-${id}`);
    vendorDiv.remove();
    // Update totals
    updateVendorTotals();
    updateGrandTotal();
}

// Function to update vendor totals
function updateVendorTotals() {
    // This function triggers the calculation of total wages
    updateTotalWages();
}

// Add labour to vendor
function addLabour(vendorId) {
    window.labourCounter++;
    const labourContainer = document.getElementById(`vendor-labours-${vendorId}`);
    
    // Count existing labor items for this vendor to determine number
    const existingLabors = labourContainer.querySelectorAll('.labour-container').length;
    const laborNumber = existingLabors + 1;
    
    const labourDiv = document.createElement('div');
    labourDiv.className = 'labour-container';
    labourDiv.id = `labour-${window.labourCounter}`;
    
    labourDiv.innerHTML = `
        <div class="labour-header">
            <strong>Labour #${laborNumber}</strong>
            <button type="button" class="remove-btn" onclick="removeLabour(${window.labourCounter}, ${vendorId})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="labour-name-${window.labourCounter}">Labour Name</label>
                    <input type="text" class="form-control labour-name-input" id="labour-name-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][name]" required autocomplete="off">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="labour-mobile-${window.labourCounter}">Mobile Number</label>
                    <input type="text" class="form-control" id="labour-mobile-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][mobile]">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-3">
                <div class="form-group">
                    <label for="labour-morning-attendance-${window.labourCounter}">Morning Att.</label>
                    <select class="form-control" id="labour-morning-attendance-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][morning_attendance]" required onchange="calculateLabourTotal(${window.labourCounter})">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half-day">Half-day</option>
                    </select>
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="labour-afternoon-attendance-${window.labourCounter}">Afternoon Att.</label>
                    <select class="form-control" id="labour-afternoon-attendance-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][afternoon_attendance]" required onchange="calculateLabourTotal(${window.labourCounter})">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half-day">Half-day</option>
                    </select>
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="labour-ot-hours-${window.labourCounter}">OT Hours</label>
                    <input type="number" class="form-control" id="labour-ot-hours-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateLabourTotal(${window.labourCounter})">
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="labour-ot-wages-${window.labourCounter}">OT Wages (₹/hr)</label>
                    <input type="number" class="form-control" id="labour-ot-wages-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][ot_wages]" value="0" min="0" onchange="calculateLabourTotal(${window.labourCounter})">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-4">
                <div class="form-group">
                    <label for="labour-wage-${window.labourCounter}">Wage (₹)</label>
                    <input type="number" class="form-control" id="labour-wage-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][wage]" value="0" min="0" required onchange="calculateLabourTotal(${window.labourCounter})">
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="labour-ot-amount-${window.labourCounter}">OT Amount (₹)</label>
                    <input type="number" class="form-control" id="labour-ot-amount-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][ot_amount]" value="0" min="0" onchange="calculateLabourTotal(${window.labourCounter})">
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="labour-total-${window.labourCounter}">Total Amount (₹)</label>
                    <input type="number" class="form-control vendor-labour-total" id="labour-total-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][total]" value="0" min="0" readonly>
                </div>
            </div>
        </div>
    `;
    
    labourContainer.appendChild(labourDiv);
    
    // Initialize autocomplete for labour name
    initLabourAutocomplete(window.labourCounter, vendorId);
}

// Initialize autocomplete for labour name field
function initLabourAutocomplete(labourId, vendorId) {
    const labourNameInput = document.getElementById(`labour-name-${labourId}`);
    const labourMobileInput = document.getElementById(`labour-mobile-${labourId}`);
    const labourWageInput = document.getElementById(`labour-wage-${labourId}`);
    const vendorTypeSelect = document.getElementById(`vendor-type-${vendorId}`);
    const vendorNameInput = document.getElementById(`vendor-name-${vendorId}`);
    
    // Get vendor type and name for better search results
    const vendorType = vendorTypeSelect ? vendorTypeSelect.value : '';
    const vendorName = vendorNameInput ? vendorNameInput.value : '';
    
    // Setup autocomplete for labour name field
    $(labourNameInput).autocomplete({
        source: function(request, response) {
            // Make AJAX call to get labour data
            $.ajax({
                url: 'site_expenses.php',
                dataType: 'json',
                data: {
                    action: 'get_vendor_labour_data',
                    type: 'vendor_labour',
                    term: request.term,
                    vendor_type: vendorType,
                    vendor_name: vendorName
                },
                success: function(data) {
                    if (data.success) {
                        response($.map(data.data, function(item) {
                            return {
                                label: item.name,
                                value: item.name,
                                labour: item
                            };
                        }));
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            // Auto-fill labour details
            const labour = ui.item.labour;
            
            // Set mobile number
            if (labour.mobile) {
                labourMobileInput.value = labour.mobile;
            }
            
            // Set wage
            if (labour.wage) {
                labourWageInput.value = labour.wage;
                // Recalculate total
                calculateLabourTotal(labourId);
            }
            
            return true;
        }
    });
}

// Remove labour
function removeLabour(id, vendorId) {
    const labourDiv = document.getElementById(`labour-${id}`);
    labourDiv.remove();
    
    // Update numbers for remaining labor items
    if (vendorId) {
        const labourContainer = document.getElementById(`vendor-labours-${vendorId}`);
        if (labourContainer) {
            const laborItems = labourContainer.querySelectorAll('.labour-container');
            laborItems.forEach((item, index) => {
                const headerEl = item.querySelector('.labour-header strong');
                if (headerEl) {
                    headerEl.textContent = `Labour #${index + 1}`;
                }
            });
        }
    }
    
    // Update totals
    updateTotalWages();
    updateGrandTotal();
}

// Calculate labour totals
function calculateLabourTotal(labourId) {
    const morningAttendanceSelect = document.getElementById(`labour-morning-attendance-${labourId}`);
    const afternoonAttendanceSelect = document.getElementById(`labour-afternoon-attendance-${labourId}`);
    const otHoursInput = document.getElementById(`labour-ot-hours-${labourId}`);
    const otWagesInput = document.getElementById(`labour-ot-wages-${labourId}`);
    const wageInput = document.getElementById(`labour-wage-${labourId}`);
    const otAmountInput = document.getElementById(`labour-ot-amount-${labourId}`);
    const totalInput = document.getElementById(`labour-total-${labourId}`);
    
    const morningAttendance = morningAttendanceSelect.value;
    const afternoonAttendance = afternoonAttendanceSelect.value;
    const otHours = parseFloat(otHoursInput.value) || 0;
    const otWages = parseFloat(otWagesInput.value) || 0;
    const wage = parseFloat(wageInput.value) || 0;
    const otAmount = parseFloat(otAmountInput.value) || 0;
    
    // Calculate attendance factor for morning
    let morningFactor = 1;
    if (morningAttendance === 'Absent') {
        morningFactor = 0;
    } else if (morningAttendance === 'Half-day') {
        morningFactor = 0.5;
    }
    
    // Calculate attendance factor for afternoon
    let afternoonFactor = 1;
    if (afternoonAttendance === 'Absent') {
        afternoonFactor = 0;
    } else if (afternoonAttendance === 'Half-day') {
        afternoonFactor = 0.5;
    }
    
    // Combined attendance factor (morning counts for 0.5 of day, afternoon counts for 0.5)
    const combinedFactor = (morningFactor * 0.5) + (afternoonFactor * 0.5);
    
    // Calculate total
    const total = (wage * combinedFactor) + otAmount;
    
    // Update total field
    totalInput.value = total.toFixed(2);
    
    // Update total wages and grand total
    updateTotalWages();
}

// Modal functionality
const modal = document.getElementById('updateDetailsModal');
const closeBtn = document.querySelector('.close-modal');

function viewUpdateDetails(siteName, date, details) {
    const modalSiteName = document.getElementById('modalSiteName');
    const modalDate = document.getElementById('modalDate');
    const modalDetails = document.getElementById('modalDetails');
    const modal = document.getElementById('updateDetailsModal');
    
    if (modalSiteName) modalSiteName.textContent = siteName;
    if (modalDate) modalDate.textContent = date;
    if (modalDetails) modalDetails.textContent = details;
    if (modal) modal.style.display = 'block';
    
    // Fetch and populate the additional site update details
    fetchSiteUpdateDetails(siteName, date);
}

function fetchSiteUpdateDetails(siteName, date) {
    // Make an AJAX call to get the site update details
    fetch(`site_expenses.php?action=get_site_update_details&site_name=${encodeURIComponent(siteName)}&date=${encodeURIComponent(date)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(responseData => {
            if (responseData.success) {
                populateSiteUpdateDetails(responseData.data);
            } else {
                console.error('Error fetching details:', responseData.message);
                // Show a message in the modal
                const vendorsList = document.getElementById('modalVendorsList');
                const companyLaboursList = document.getElementById('modalCompanyLaboursList');
                
                if (vendorsList) vendorsList.innerHTML = '<div class="site-detail-empty-message">No vendor details available.</div>';
                if (companyLaboursList) companyLaboursList.innerHTML = '<div class="site-detail-empty-message">No company labour details available.</div>';
                
                // Hide expenses section
                const expensesSection = document.getElementById('modalExpensesSection');
                if (expensesSection) expensesSection.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching site update details:', error);
            // Show a fallback message using demo data
            console.log('Using demo data for display...');
            
            // Fallback to demo data for display purposes
            const demoData = {
                vendors: [
                    {
                        type: "POP",
                        name: "Mahaveer Enterprises",
                        contact: "9876543210",
                        labours: [
                            {
                                name: "Ramesh Kumar",
                                mobile: "8765432109",
                                attendance: "Present",
                                ot_hours: 2,
                                wage: 500,
                                ot_amount: 125,
                                total: 625
                            },
                            {
                                name: "Suresh Singh",
                                mobile: "7654321098",
                                attendance: "Half-day",
                                ot_hours: 0,
                                wage: 450,
                                ot_amount: 0,
                                total: 225
                            }
                        ]
                    },
                    {
                        type: "Electrical",
                        name: "Power Solutions",
                        contact: "9876543211",
                        labours: [
                            {
                                name: "Vijay Kapoor",
                                mobile: "8765432108",
                                attendance: "Present",
                                ot_hours: 1.5,
                                wage: 600,
                                ot_amount: 112.5,
                                total: 712.5
                            }
                        ]
                    }
                ],
                company_labours: [
                    {
                        name: "Rahul Sharma",
                        mobile: "9876543212",
                        attendance: "Present",
                        ot_hours: 2,
                        wage: 700,
                        ot_amount: 175,
                        total: 875
                    },
                    {
                        name: "Amit Patel",
                        mobile: "9876543213",
                        attendance: "Present",
                        ot_hours: 0,
                        wage: 650,
                        ot_amount: 0,
                        total: 650
                    }
                ],
                expenses: {
                    total_wages: 3087.5,
                    total_misc_expenses: 1250,
                    grand_total: 4337.5
                }
            };
            
            populateSiteUpdateDetails(demoData);
        });
}

function populateSiteUpdateDetails(data) {
    // Populate vendors section
    const vendorsList = document.getElementById('modalVendorsList');
    const vendorsSection = document.getElementById('modalVendorsSection');
    
    if (vendorsList && data.vendors && data.vendors.length > 0) {
        vendorsList.innerHTML = '';
        
        data.vendors.forEach(vendor => {
            const vendorCard = document.createElement('div');
            vendorCard.className = 'site-detail-vendor-card';
            
            let vendorHTML = `
                <div class="site-detail-vendor-header">
                    <div style="display: flex; align-items: center;">
                        <div style="margin-right: 10px; font-weight: bold; min-width: 25px; height: 25px; background-color: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            ${data.vendors.indexOf(vendor) + 1}
                        </div>
                        <div>
                            <div class="site-detail-vendor-type">${vendor.type}</div>
                            <div class="site-detail-vendor-name">${vendor.name}</div>
                        </div>
                    </div>
                </div>
            `;
            
            if (vendor.contact) {
                vendorHTML += `
                    <div class="site-detail-vendor-contact">
                        <i class="fas fa-phone"></i> ${vendor.contact}
                    </div>
                `;
            }
            
            // Add vendor labours if any
            if (vendor.labours && vendor.labours.length > 0) {
                vendorHTML += `<div class="site-detail-labour-list">`;
                
                // Header row
                vendorHTML += `
                    <div class="site-detail-labour-row" style="background-color: #f0f0f0; font-weight: 600;">
                        <div class="site-detail-labour-col" style="width: 5%;">
                            <span class="site-detail-labour-col-label">#</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Name</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Mobile</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Attendance</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">OT Hours</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Wage (₹)</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Total (₹)</span>
                        </div>
                    </div>
                `;
                
                // Data rows
                vendor.labours.forEach((labour, laborIndex) => {
                    vendorHTML += `
                        <div class="site-detail-labour-row">
                            <div class="site-detail-labour-col" style="width: 5%;">
                                <span class="site-detail-labour-col-value">${laborIndex + 1}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.name}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.mobile ? labour.mobile : '-'}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.attendance}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.ot_hours}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.wage}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.total}</span>
                            </div>
                        </div>
                    `;
                });
                
                vendorHTML += `</div>`;
            }
            
            vendorCard.innerHTML = vendorHTML;
            vendorsList.appendChild(vendorCard);
        });
        
        vendorsSection.style.display = 'block';
    } else if (vendorsSection) {
        vendorsList.innerHTML = '<div class="site-detail-empty-message">No vendors found for this update.</div>';
        vendorsSection.style.display = 'block';
    }
    
    // Populate company labours section
    const companyLaboursList = document.getElementById('modalCompanyLaboursList');
    const companyLaboursSection = document.getElementById('modalCompanyLaboursSection');
    
    if (companyLaboursList && data.company_labours && data.company_labours.length > 0) {
        companyLaboursList.innerHTML = '';
        
        let companyLaboursHTML = `
            <div class="site-detail-labour-card">
                <div class="site-detail-labour-list">
                    <div class="site-detail-labour-row" style="background-color: #f0f0f0; font-weight: 600;">
                        <div class="site-detail-labour-col" style="width: 5%;">
                            <span class="site-detail-labour-col-label">#</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Name</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Mobile</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Attendance</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">OT Hours</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Wage (₹)</span>
                        </div>
                        <div class="site-detail-labour-col">
                            <span class="site-detail-labour-col-label">Total (₹)</span>
                        </div>
                    </div>
        `;
        
        data.company_labours.forEach((labour, labourIndex) => {
            companyLaboursHTML += `
                <div class="site-detail-labour-row">
                    <div class="site-detail-labour-col" style="width: 5%;">
                        <span class="site-detail-labour-col-value">${labourIndex + 1}</span>
                    </div>
                    <div class="site-detail-labour-col">
                        <span class="site-detail-labour-col-value">${labour.name}</span>
                    </div>
                    <div class="site-detail-labour-col">
                        <span class="site-detail-labour-col-value">${labour.mobile ? labour.mobile : '-'}</span>
                    </div>
                    <div class="site-detail-labour-col">
                        <span class="site-detail-labour-col-value">${labour.attendance}</span>
                    </div>
                    <div class="site-detail-labour-col">
                        <span class="site-detail-labour-col-value">${labour.ot_hours}</span>
                    </div>
                    <div class="site-detail-labour-col">
                        <span class="site-detail-labour-col-value">${labour.wage}</span>
                    </div>
                    <div class="site-detail-labour-col">
                        <span class="site-detail-labour-col-value">${labour.total}</span>
                    </div>
                </div>
            `;
        });
        
        companyLaboursHTML += `</div></div>`;
        companyLaboursList.innerHTML = companyLaboursHTML;
        companyLaboursSection.style.display = 'block';
    } else if (companyLaboursSection) {
        companyLaboursList.innerHTML = '<div class="site-detail-empty-message">No company labours found for this update.</div>';
        companyLaboursSection.style.display = 'block';
    }
    
    // Populate work progress section
    const workProgressList = document.getElementById('modalWorkProgressList');
    const workProgressSection = document.getElementById('modalWorkProgressSection');
    
    if (workProgressList && data.work_progress && data.work_progress.length > 0) {
        workProgressList.innerHTML = '';
        
        data.work_progress.forEach((item, index) => {
            const workItem = document.createElement('div');
            workItem.className = 'site-detail-work-item';
            
            // Determine status class
            let statusClass = 'not-started';
            if (item.status === 'Yes') {
                statusClass = 'completed';
            } else if (item.status === 'In Progress') {
                statusClass = 'in-progress';
            }
            
            let workHTML = `
                <div class="site-detail-work-header">
                    <div style="display: flex; align-items: center;">
                        <div style="margin-right: 10px; font-weight: bold; min-width: 25px; height: 25px; background-color: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            ${index + 1}
                        </div>
                        <div>
                            <div class="site-detail-work-type">${item.work_type}</div>
                            <div class="site-detail-work-category">${item.category}</div>
                        </div>
                    </div>
                    <div class="site-detail-work-status ${statusClass}">
                        ${item.status}
                    </div>
                </div>
            `;
            
            if (item.remarks && item.remarks.trim() !== '') {
                workHTML += `
                    <div class="site-detail-work-remarks">
                        <strong>Remarks:</strong> ${item.remarks}
                    </div>
                `;
            }
            
            // Add media files if any
            if (item.files && item.files.length > 0) {
                workHTML += `
                    <div class="site-detail-media-gallery">
                        <div class="site-detail-media-title">Photos/Videos</div>
                        <div class="site-detail-media-grid">
                `;
                
                item.files.forEach((file, fileIndex) => {
                    const isVideo = file.type === 'video';
                    const mediaClass = isVideo ? 'video' : 'image';
                    const mediaId = `work-media-${index}-${fileIndex}`;
                    
                    workHTML += `
                        <div class="site-detail-media-item ${mediaClass}" id="${mediaId}" onclick="openMediaModal('${file.path}', '${file.type}')">
                            ${isVideo ? 
                                `<img src="images/video-thumbnail.jpg" alt="Video thumbnail">` : 
                                `<img src="${file.path}" alt="Work progress image">`}
                        </div>
                    `;
                });
                
                workHTML += `
                        </div>
                    </div>
                `;
            }
            
            workItem.innerHTML = workHTML;
            workProgressList.appendChild(workItem);
        });
        
        workProgressSection.style.display = 'block';
    } else if (workProgressSection) {
        workProgressList.innerHTML = '<div class="site-detail-empty-message">No work progress data found for this update.</div>';
        workProgressSection.style.display = 'block';
    }
    
    // Populate inventory section
    const inventoryList = document.getElementById('modalInventoryList');
    const inventorySection = document.getElementById('modalInventorySection');
    
    if (inventoryList && data.inventory && data.inventory.length > 0) {
        inventoryList.innerHTML = '';
        
        data.inventory.forEach((item, index) => {
            const inventoryItem = document.createElement('div');
            inventoryItem.className = 'site-detail-inventory-item';
            
            let inventoryHTML = `
                <div class="site-detail-inventory-header">
                    <div style="display: flex; align-items: center;">
                        <div style="margin-right: 10px; font-weight: bold; min-width: 25px; height: 25px; background-color: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            ${index + 1}
                        </div>
                        <div>
                            <div class="site-detail-material-type">${item.material}</div>
                            <div class="site-detail-inventory-quantity">${item.quantity} ${item.unit}</div>
                        </div>
                    </div>
                </div>
            `;
            
            if (item.standard_values && item.standard_values.trim() !== '') {
                inventoryHTML += `
                    <div class="site-detail-inventory-notes">
                        <strong>Notes:</strong> ${item.standard_values}
                    </div>
                `;
            }
            
            // Add media files if any
            if (item.files && item.files.length > 0) {
                inventoryHTML += `
                    <div class="site-detail-media-gallery">
                        <div class="site-detail-media-title">Photos/Videos</div>
                        <div class="site-detail-media-grid">
                `;
                
                item.files.forEach((file, fileIndex) => {
                    const isVideo = file.type === 'video';
                    const mediaClass = isVideo ? 'video' : 'image';
                    const mediaId = `inventory-media-${index}-${fileIndex}`;
                    
                    inventoryHTML += `
                        <div class="site-detail-media-item ${mediaClass}" id="${mediaId}" onclick="openMediaModal('${file.path}', '${file.type}')">
                            ${isVideo ? 
                                `<img src="images/video-thumbnail.jpg" alt="Video thumbnail">` : 
                                `<img src="${file.path}" alt="Inventory image">`}
                        </div>
                    `;
                });
                
                inventoryHTML += `
                        </div>
                    </div>
                `;
            }
            
            inventoryItem.innerHTML = inventoryHTML;
            inventoryList.appendChild(inventoryItem);
        });
        
        inventorySection.style.display = 'block';
    } else if (inventorySection) {
        inventoryList.innerHTML = '<div class="site-detail-empty-message">No inventory data found for this update.</div>';
        inventorySection.style.display = 'block';
    }
    
    // Populate expenses summary
    if (data.expenses) {
        const totalWages = document.getElementById('modalTotalWages');
        const totalMiscExpenses = document.getElementById('modalTotalMiscExpenses');
        const grandTotal = document.getElementById('modalGrandTotal');
        
        if (totalWages) totalWages.textContent = '₹' + data.expenses.total_wages.toFixed(2);
        if (totalMiscExpenses) totalMiscExpenses.textContent = '₹' + data.expenses.total_misc_expenses.toFixed(2);
        if (grandTotal) grandTotal.textContent = '₹' + data.expenses.grand_total.toFixed(2);
        
        document.getElementById('modalExpensesSection').style.display = 'block';
    } else {
        document.getElementById('modalExpensesSection').style.display = 'none';
    }
}

closeBtn.onclick = function() {
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target === modal) {
        modal.style.display = 'none';
    } else if (event.target === document.getElementById('siteUpdateModal')) {
        hideSiteUpdateModal();
    }
}

// Company Labour counter
// Remove duplicate declarations

// Add company labour
function addCompanyLabour() {
    window.companyLabourCounter++;
    const container = document.getElementById('company-labours-container');
    
    // Count existing labor items to determine number
    const existingLabors = container.querySelectorAll('.company-labour-container').length;
    const laborNumber = existingLabors + 1;
    
    const labourDiv = document.createElement('div');
    labourDiv.className = 'company-labour-container';
    labourDiv.id = `company-labour-${window.companyLabourCounter}`;
    
    labourDiv.innerHTML = `
        <button type="button" class="remove-btn-corner" onclick="removeCompanyLabour(${window.companyLabourCounter})">
            <i class="fas fa-times"></i>
        </button>
        <h4 style="margin-bottom: 15px; font-size: 16px; color: #333;">Company Labour #${laborNumber}</h4>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="company-labour-name-${window.companyLabourCounter}">Labour Name</label>
                    <input type="text" class="form-control company-labour-name-input" id="company-labour-name-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][name]" required autocomplete="off">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="company-labour-mobile-${window.companyLabourCounter}">Mobile Number</label>
                    <input type="text" class="form-control" id="company-labour-mobile-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][mobile]">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-3">
                <div class="form-group">
                    <label for="company-labour-morning-attendance-${window.companyLabourCounter}">Morning Att.</label>
                    <select class="form-control" id="company-labour-morning-attendance-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][morning_attendance]" required onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half-day">Half-day</option>
                    </select>
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="company-labour-afternoon-attendance-${window.companyLabourCounter}">Afternoon Att</label>
                    <select class="form-control" id="company-labour-afternoon-attendance-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][afternoon_attendance]" required onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half-day">Half-day</option>
                    </select>
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="company-labour-ot-hours-${window.companyLabourCounter}">OT Hours</label>
                    <input type="number" class="form-control" id="company-labour-ot-hours-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="company-labour-ot-wages-${window.companyLabourCounter}">OT Wages (₹/hr)</label>
                    <input type="number" class="form-control" id="company-labour-ot-wages-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][ot_wages]" value="0" min="0" onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-4">
                <div class="form-group">
                    <label for="company-labour-wage-${window.companyLabourCounter}">Wage (₹)</label>
                    <input type="number" class="form-control" id="company-labour-wage-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][wage]" value="0" min="0" required onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="company-labour-ot-amount-${window.companyLabourCounter}">OT Amount (₹)</label>
                    <input type="number" class="form-control" id="company-labour-ot-amount-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][ot_amount]" value="0" min="0" onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="company-labour-total-${window.companyLabourCounter}">Total Amount (₹)</label>
                    <input type="number" class="form-control company-labour-total" id="company-labour-total-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][total]" value="0" min="0" readonly data-id="${window.companyLabourCounter}">
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(labourDiv);
    
    // Initialize autocomplete for company labour name
    initCompanyLabourAutocomplete(window.companyLabourCounter);
}

// Initialize autocomplete for company labour name field
function initCompanyLabourAutocomplete(labourId) {
    const labourNameInput = document.getElementById(`company-labour-name-${labourId}`);
    const labourMobileInput = document.getElementById(`company-labour-mobile-${labourId}`);
    const labourWageInput = document.getElementById(`company-labour-wage-${labourId}`);
    
    // Setup autocomplete for company labour name field
    $(labourNameInput).autocomplete({
        source: function(request, response) {
            // Make AJAX call to get company labour data
            $.ajax({
                url: 'site_expenses.php',
                dataType: 'json',
                data: {
                    action: 'get_vendor_labour_data',
                    type: 'company_labour',
                    term: request.term
                },
                success: function(data) {
                    if (data.success) {
                        response($.map(data.data, function(item) {
                            // Add source info to label
                            let labelText = item.name;
                            if (item.source) {
                                // Capitalize first letter of source
                                const source = item.source.charAt(0).toUpperCase() + item.source.slice(1);
                                labelText += ` (${source})`;
                            }
                            
                            return {
                                label: labelText,
                                value: item.name,
                                labour: item
                            };
                        }));
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            // Auto-fill company labour details
            const labour = ui.item.labour;
            
            // Set mobile number
            if (labour.mobile) {
                labourMobileInput.value = labour.mobile;
            }
            
            // Set wage
            if (labour.wage) {
                labourWageInput.value = labour.wage;
                // Recalculate total
                calculateCompanyLabourTotal(labourId);
            }
            
            return true;
        }
    });
}

// Remove company labour
function removeCompanyLabour(id) {
    const labourDiv = document.getElementById(`company-labour-${id}`);
    labourDiv.remove();
    
    // Update numbers for remaining labor items
    const container = document.getElementById('company-labours-container');
    if (container) {
        const laborItems = container.querySelectorAll('.company-labour-container');
        laborItems.forEach((item, index) => {
            const headerEl = item.querySelector('h4');
            if (headerEl) {
                headerEl.textContent = `Company Labour #${index + 1}`;
            }
        });
    }
    
    updateTotalWages();
    updateGrandTotal();
}

// Calculate company labour totals
function calculateCompanyLabourTotal(labourId) {
    const morningAttendanceSelect = document.getElementById(`company-labour-morning-attendance-${labourId}`);
    const afternoonAttendanceSelect = document.getElementById(`company-labour-afternoon-attendance-${labourId}`);
    const otHoursInput = document.getElementById(`company-labour-ot-hours-${labourId}`);
    const otWagesInput = document.getElementById(`company-labour-ot-wages-${labourId}`);
    const wageInput = document.getElementById(`company-labour-wage-${labourId}`);
    const otAmountInput = document.getElementById(`company-labour-ot-amount-${labourId}`);
    const totalInput = document.getElementById(`company-labour-total-${labourId}`);
    
    const morningAttendance = morningAttendanceSelect.value;
    const afternoonAttendance = afternoonAttendanceSelect.value;
    const otHours = parseFloat(otHoursInput.value) || 0;
    const wage = parseFloat(wageInput.value) || 0;
    let otWages = parseFloat(otWagesInput.value);
    let otAmount = parseFloat(otAmountInput.value);
    
    // Calculate attendance factor for morning
    let morningFactor = 1;
    if (morningAttendance === 'Absent') {
        morningFactor = 0;
    } else if (morningAttendance === 'Half-day') {
        morningFactor = 0.5;
    }
    
    // Calculate attendance factor for afternoon
    let afternoonFactor = 1;
    if (afternoonAttendance === 'Absent') {
        afternoonFactor = 0;
    } else if (afternoonAttendance === 'Half-day') {
        afternoonFactor = 0.5;
    }
    
    // Combined attendance factor (morning counts for 0.5 of day, afternoon counts for 0.5)
    const combinedFactor = (morningFactor * 0.5) + (afternoonFactor * 0.5);
    
    // If user hasn't entered OT Wages, calculate default
    if (!otWagesInput.value || isNaN(otWages)) {
        otWages = wage / 8 * 1.5;
        otWagesInput.value = otWages.toFixed(2);
    }
    
    // If user hasn't entered OT Amount, calculate
    if (!otAmountInput.value || isNaN(otAmount)) {
        otAmount = otHours * otWages;
        otAmountInput.value = otAmount.toFixed(2);
    }
    
    // Calculate total
    const total = (wage * combinedFactor) + otAmount;
    totalInput.value = total.toFixed(2);
    
    // Update total wages and grand total
    updateTotalWages();
    updateGrandTotal();
}

// Add travelling allowance
function addTravellingAllowance() {
    window.travelAllowanceCounter++;
    const container = document.getElementById('travel-allowances-list');
    
    const allowanceDiv = document.createElement('div');
    allowanceDiv.className = 'travel-allowance-container';
    allowanceDiv.id = `travel-allowance-${window.travelAllowanceCounter}`;
    
    allowanceDiv.innerHTML = `
        <button type="button" class="remove-btn-corner" onclick="removeTravellingAllowance(${window.travelAllowanceCounter})">
            <i class="fas fa-times"></i>
        </button>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="travel-from-${window.travelAllowanceCounter}">From</label>
                    <input type="text" class="form-control" id="travel-from-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][from]" required>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="travel-to-${window.travelAllowanceCounter}">To</label>
                    <input type="text" class="form-control" id="travel-to-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][to]" required>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-4">
                <div class="form-group">
                    <label for="travel-mode-${window.travelAllowanceCounter}">Mode of Transport</label>
                    <select class="form-control" id="travel-mode-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][mode]" required>
                        <option value="">Select Mode</option>
                        <option value="Car">Car</option>
                        <option value="Bike">Bike</option>
                        <option value="Bus">Bus</option>
                        <option value="Train">Train</option>
                        <option value="Auto">Auto</option>
                        <option value="Taxi">Taxi</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="travel-kilometers-${window.travelAllowanceCounter}">Total Kilometers</label>
                    <input type="number" class="form-control" id="travel-kilometers-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][kilometers]" value="0" min="0" step="0.1">
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="travel-amount-${window.travelAllowanceCounter}">Amount (₹)</label>
                    <input type="number" class="form-control travel-amount" id="travel-amount-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][amount]" value="0" min="0" required onchange="updateTravellingAllowancesTotal()">
                </div>
            </div>
                    </div>
                `;
    
    container.appendChild(allowanceDiv);
}

// Remove travelling allowance
function removeTravellingAllowance(id) {
    const allowanceDiv = document.getElementById(`travel-allowance-${id}`);
    allowanceDiv.remove();
    updateTravellingAllowancesTotal();
    updateMiscExpensesTotal();
    updateGrandTotal();
}

// Update travelling allowances total
function updateTravellingAllowancesTotal() {
    const amountInputs = document.querySelectorAll('.travel-amount');
    let total = 0;
    
    amountInputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    
    const totalSpan = document.getElementById('total-travel-allowances');
    const totalInput = document.getElementById('total-travel-allowances-input');
    
    if (totalSpan) totalSpan.textContent = total.toFixed(2);
    if (totalInput) totalInput.value = total.toFixed(2);
    
    updateMiscExpensesTotal();
    updateGrandTotal();
}

// Add beverage
function addBeverage() {
    window.beverageCounter++;
    const container = document.getElementById('beverages-list');
    
    const beverageDiv = document.createElement('div');
    beverageDiv.className = 'beverage-container';
    beverageDiv.id = `beverage-${window.beverageCounter}`;
    
    beverageDiv.innerHTML = `
        <button type="button" class="remove-btn-corner" onclick="removeBeverage(${window.beverageCounter})">
            <i class="fas fa-times"></i>
        </button>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="beverage-name-${window.beverageCounter}">Beverage/Food Item</label>
                    <input type="text" class="form-control" id="beverage-name-${window.beverageCounter}" name="beverages[${window.beverageCounter}][name]" required>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="beverage-amount-${window.beverageCounter}">Amount (₹)</label>
                    <input type="number" class="form-control beverage-amount" id="beverage-amount-${window.beverageCounter}" name="beverages[${window.beverageCounter}][amount]" value="0" min="0" required onchange="updateBeveragesTotal()">
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(beverageDiv);
}

// Remove beverage
function removeBeverage(id) {
    const beverageDiv = document.getElementById(`beverage-${id}`);
    beverageDiv.remove();
    updateBeveragesTotal();
    updateMiscExpensesTotal();
    updateGrandTotal();
}

// Update beverages total
function updateBeveragesTotal() {
    const amountInputs = document.querySelectorAll('.beverage-amount');
    let total = 0;
    
    amountInputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    
    const totalSpan = document.getElementById('total-beverages');
    const totalInput = document.getElementById('total-beverages-input');
    
    if (totalSpan) totalSpan.textContent = total.toFixed(2);
    if (totalInput) totalInput.value = total.toFixed(2);
    
    updateMiscExpensesTotal();
    updateGrandTotal();
}

// Update total wages (vendor labours + company labours)
function updateTotalWages() {
    // Sum vendor labour totals
    const vendorLabourTotals = document.querySelectorAll('.vendor-labour-total');
    let vendorLaboursTotal = 0;
    
    vendorLabourTotals.forEach(input => {
        vendorLaboursTotal += parseFloat(input.value) || 0;
    });
    
    // Sum company labour totals
    const companyLabourTotals = document.querySelectorAll('.company-labour-total');
    let companyLaboursTotal = 0;
    
    companyLabourTotals.forEach(input => {
        companyLaboursTotal += parseFloat(input.value) || 0;
    });
    
    // Combined total
    const totalWages = vendorLaboursTotal + companyLaboursTotal;
    
    const totalWagesSpan = document.getElementById('total-wages');
    const totalWagesInput = document.getElementById('total-wages-input');
    
    if (totalWagesSpan) totalWagesSpan.textContent = totalWages.toFixed(2);
    if (totalWagesInput) totalWagesInput.value = totalWages.toFixed(2);
    
    updateGrandTotal();
}

// Update miscellaneous expenses total (travel allowances + beverages)
function updateMiscExpensesTotal() {
    const travelInput = document.getElementById('total-travel-allowances-input');
    const beveragesInput = document.getElementById('total-beverages-input');
    
    const travelAllowances = travelInput ? (parseFloat(travelInput.value) || 0) : 0;
    const beverages = beveragesInput ? (parseFloat(beveragesInput.value) || 0) : 0;
    
    const totalMiscExpenses = travelAllowances + beverages;
    
    const totalSpan = document.getElementById('total-misc-expenses');
    const totalInput = document.getElementById('total-misc-expenses-input');
    
    if (totalSpan) totalSpan.textContent = totalMiscExpenses.toFixed(2);
    if (totalInput) totalInput.value = totalMiscExpenses.toFixed(2);
    
    updateGrandTotal();
}

// Update grand total (wages + misc expenses)
function updateGrandTotal() {
    const wagesInput = document.getElementById('total-wages-input');
    const miscExpensesInput = document.getElementById('total-misc-expenses-input');
    
    const wages = wagesInput ? (parseFloat(wagesInput.value) || 0) : 0;
    const miscExpenses = miscExpensesInput ? (parseFloat(miscExpensesInput.value) || 0) : 0;
    
    const grandTotal = wages + miscExpenses;
    
    const grandTotalSpan = document.getElementById('grand-total');
    const grandTotalInput = document.getElementById('grand-total-input');
    
    if (grandTotalSpan) grandTotalSpan.textContent = grandTotal.toFixed(2);
    if (grandTotalInput) grandTotalInput.value = grandTotal.toFixed(2);
}

// Work progress counter
let workProgressCounter = 0;

// Add work progress item
function addWorkProgress(type) {
    window.workProgressCounter++;
    const container = document.getElementById('work-progress-list');
    
    const workProgressDiv = document.createElement('div');
    workProgressDiv.className = 'work-progress-container';
    workProgressDiv.id = `work-progress-${window.workProgressCounter}`;
    
    let workOptions = '';
    
    if (type === 'civil') {
        workOptions = `
            <option value="">Select Civil Work</option>
            <option value="Foundation">Foundation</option>
            <option value="Excavation">Excavation</option>
            <option value="RCC">RCC Work</option>
            <option value="Brickwork">Brickwork</option>
            <option value="Plastering">Plastering</option>
            <option value="Flooring Base">Flooring Base Preparation</option>
            <option value="Waterproofing">Waterproofing</option>
            <option value="External Plastering">External Plastering</option>
            <option value="Concrete Work">Concrete Work</option>
            <option value="Drainage">Drainage System</option>
            <option value="Other Civil Work">Other Civil Work</option>
        `;
    } else if (type === 'interior') {
        workOptions = `
            <option value="">Select Interior Work</option>
            <option value="Painting">Painting</option>
            <option value="Flooring">Flooring</option>
            <option value="Wall Cladding">Wall Cladding</option>
            <option value="Ceiling Work">Ceiling Work</option>
            <option value="Furniture">Furniture Installation</option>
            <option value="Electrical">Electrical Fittings</option>
            <option value="Plumbing">Plumbing Fixtures</option>
            <option value="Tiling">Tiling</option>
            <option value="Carpentry">Carpentry</option>
            <option value="Lighting">Lighting Installation</option>
            <option value="HVAC">HVAC Installation</option>
            <option value="Other Interior Work">Other Interior Work</option>
        `;
    }
    
    workProgressDiv.innerHTML = `
        <div class="item-header">
            <h4>${type === 'civil' ? '<i class="fas fa-hammer"></i> Civil Work' : '<i class="fas fa-couch"></i> Interior Work'}</h4>
            <button type="button" class="remove-btn" onclick="removeWorkProgress(${window.workProgressCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="work-type-${window.workProgressCounter}"><i class="fas fa-clipboard-check"></i> Type of Work</label>
                    <select class="form-control" id="work-type-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][work_type]" required>
                        ${workOptions}
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="work-status-${window.workProgressCounter}"><i class="fas fa-check-circle"></i> Is Work Completed?</label>
                    <select class="form-control" id="work-status-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][status]" required>
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                        <option value="In Progress">In Progress</option>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="work-category-${window.workProgressCounter}"><i class="fas fa-tag"></i> Category</label>
                    <input type="hidden" name="work_progress[${window.workProgressCounter}][category]" value="${type}">
                    <input type="text" class="form-control" value="${type === 'civil' ? 'Civil Work' : 'Interior Work'}" readonly>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="work-remarks-${window.workProgressCounter}"><i class="fas fa-comment-alt"></i> Remarks</label>
                    <textarea class="form-control" id="work-remarks-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][remarks]" placeholder="Add any remarks about the work progress..."></textarea>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="work-files-${window.workProgressCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                    <input type="file" class="form-control" id="work-files-${window.workProgressCounter}" name="work_progress_files_${window.workProgressCounter}[]" multiple accept="image/*,video/*">
                    <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(workProgressDiv);
    
    // Improved scrolling to the newly created work progress container
    setTimeout(function() {
        // Use our custom scroll function
        window.smoothScrollToElement(workProgressDiv, 80);
        
        // Add focus to the first input in the new container
        const firstSelect = document.getElementById(`work-type-${window.workProgressCounter}`);
        if (firstSelect) {
            firstSelect.focus();
        }
    }, 300);
}

// Remove work progress item
function removeWorkProgress(id) {
    const workProgressDiv = document.getElementById(`work-progress-${id}`);
    workProgressDiv.remove();
}

// Open/close site update modal
function openSiteUpdateModal() {
    const modal = document.getElementById('siteUpdateModal');
    if (modal) {
        modal.style.display = 'block';
        // Initialize totals when modal opens
        setTimeout(() => {
            if (typeof updateTotalWages === 'function') {
                updateTotalWages();
                updateTravellingAllowancesTotal();
                updateBeveragesTotal();
                updateMiscExpensesTotal();
                updateGrandTotal();
            }
        }, 100);
    }
}

function hideSiteUpdateModal() {
    const modal = document.getElementById('siteUpdateModal');
    if (modal) modal.style.display = 'none';
}

// Inventory counter
window.inventoryCounter = 0;

// Add inventory item
function addInventoryItem() {
    window.inventoryCounter++;
    const container = document.getElementById('inventory-list');
    
    // Count existing inventory items to determine number
    const existingItems = container.querySelectorAll('.inventory-container').length;
    const itemNumber = existingItems + 1;
    
    const inventoryDiv = document.createElement('div');
    inventoryDiv.className = 'inventory-container';
    inventoryDiv.id = `inventory-${window.inventoryCounter}`;
    
    inventoryDiv.innerHTML = `
        <div class="item-header">
            <h4>
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #3498db; color: white; border-radius: 50%; margin-right: 10px; font-size: 14px;">${itemNumber}</span>
                <i class="fas fa-box"></i> Inventory Item
            </h4>
            <button type="button" class="remove-btn" onclick="removeInventoryItem(${window.inventoryCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="material-type-${window.inventoryCounter}"><i class="fas fa-cubes"></i> Material</label>
                    <select class="form-control" id="material-type-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][material]" required>
                        <option value="">Select Material</option>
                        <option value="Cement">Cement</option>
                        <option value="Sand">Sand</option>
                        <option value="Aggregate">Aggregate</option>
                        <option value="Bricks">Bricks</option>
                        <option value="Steel">Steel</option>
                        <option value="Timber">Timber</option>
                        <option value="Paint">Paint</option>
                        <option value="Tiles">Tiles</option>
                        <option value="Glass">Glass</option>
                        <option value="Electrical Wires">Electrical Wires</option>
                        <option value="Pipes">Pipes</option>
                        <option value="Sanitary Fixtures">Sanitary Fixtures</option>
                        <option value="Concrete">Concrete</option>
                        <option value="Plaster">Plaster</option>
                        <option value="Gravel">Gravel</option>
                        <option value="Stone Dust">Stone Dust</option>
                        <option value="Water Proofing Materials">Water Proofing Materials</option>
                        <option value="Plywood">Plywood</option>
                        <option value="Adhesives">Adhesives</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="quantity-${window.inventoryCounter}"><i class="fas fa-balance-scale"></i> Quantity</label>
                    <input type="number" class="form-control" id="quantity-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][quantity]" min="0" step="any" required>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="unit-${window.inventoryCounter}"><i class="fas fa-ruler"></i> Unit</label>
                    <select class="form-control" id="unit-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][unit]" required>
                        <option value="">Select Unit</option>
                        <option value="Kg">Kg</option>
                        <option value="Bag">Bag</option>
                        <option value="Ton">Ton</option>
                        <option value="Cubic Meter">Cubic Meter</option>
                        <option value="Square Meter">Square Meter</option>
                        <option value="Meter">Meter</option>
                        <option value="Piece">Piece</option>
                        <option value="Number">Number</option>
                        <option value="Litre">Litre</option>
                        <option value="Quintal">Quintal</option>
                        <option value="Bundle">Bundle</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="standard-values-${window.inventoryCounter}"><i class="fas fa-clipboard"></i> Standard Values/Notes</label>
                    <textarea class="form-control" id="standard-values-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][standard_values]" placeholder="Add any standard values or notes about the material..."></textarea>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="inventory-files-${window.inventoryCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                    <input type="file" class="form-control" id="inventory-files-${window.inventoryCounter}" name="inventory_files_${window.inventoryCounter}[]" multiple accept="image/*,video/*">
                    <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(inventoryDiv);
    
    // Improved scrolling to the newly created inventory container
    setTimeout(function() {
        // Use our custom scroll function
        window.smoothScrollToElement(inventoryDiv, 80);
        
        // Add focus to the first input in the new container
        const firstSelect = document.getElementById(`material-type-${window.inventoryCounter}`);
        if (firstSelect) {
            try {
                firstSelect.focus();
            } catch (e) {
                console.error('Focus failed:', e);
            }
        }
    }, 300); // Increased timeout for better reliability
}

// Remove inventory item
function removeInventoryItem(id) {
    const inventoryDiv = document.getElementById(`inventory-${id}`);
    inventoryDiv.remove();
    
    // Update numbers for remaining inventory items
    const container = document.getElementById('inventory-list');
    if (container) {
        const items = container.querySelectorAll('.inventory-container');
        items.forEach((item, index) => {
            const numberEl = item.querySelector('.item-header h4 span');
            if (numberEl) {
                numberEl.textContent = index + 1;
            }
        });
    }
}

// Function to open media modal
function openMediaModal(path, type) {
    const modal = document.getElementById('siteDetailMediaModal');
    const modalContent = document.getElementById('siteDetailMediaContent');
    
    if (!modal || !modalContent) return;
    
    // Clear previous content
    modalContent.innerHTML = '';
    
    // Create new content based on type
    if (type === 'image') {
        const img = document.createElement('img');
        img.src = path;
        img.alt = 'Site image';
        modalContent.appendChild(img);
    } else if (type === 'video') {
        const video = document.createElement('video');
        video.controls = true;
        video.autoplay = true;
        
        const source = document.createElement('source');
        source.src = path;
        
        // Determine video type from path
        const extension = path.split('.').pop().toLowerCase();
        let mimeType = 'video/mp4'; // Default
        
        if (extension === 'mov') {
            mimeType = 'video/quicktime';
        } else if (extension === 'avi') {
            mimeType = 'video/x-msvideo';
        } else if (extension === 'wmv') {
            mimeType = 'video/x-ms-wmv';
        }
        
        source.type = mimeType;
        video.appendChild(source);
        modalContent.appendChild(video);
    }
    
    // Show the modal
    modal.style.display = 'block';
}

// Set up media modal close handlers
document.addEventListener('DOMContentLoaded', function() {
    // Close the media modal when clicking the close button
    const mediaCloseBtn = document.querySelector('.site-detail-media-close');
    if (mediaCloseBtn) {
        mediaCloseBtn.addEventListener('click', function() {
            const mediaModal = document.getElementById('siteDetailMediaModal');
            if (mediaModal) {
                mediaModal.style.display = 'none';
                
                // Pause any playing videos
                const videos = document.querySelectorAll('#siteDetailMediaContent video');
                if (videos.length > 0) {
                    videos.forEach(video => {
                        video.pause();
                    });
                }
            }
        });
    }
    
    // Close media modal when clicking outside the content
    window.addEventListener('click', function(event) {
        const mediaModal = document.getElementById('siteDetailMediaModal');
        if (event.target === mediaModal) {
            mediaModal.style.display = 'none';
            
            // Pause any playing videos
            const videos = document.querySelectorAll('#siteDetailMediaContent video');
            if (videos.length > 0) {
                videos.forEach(video => {
                    video.pause();
                });
            }
        }
    });
});

// Function to open the edit site details modal
function editSiteDetails() {
    // Get the site name and date from the view modal
    const siteName = document.getElementById('modalSiteName').textContent;
    const siteDate = document.getElementById('modalDate').textContent;
    
    // Show loading state
    document.getElementById('siteDetailEditBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    
    // Fetch the site update details for editing
    fetch(`site_expenses.php?action=get_site_update_for_edit&site_name=${encodeURIComponent(siteName)}&date=${encodeURIComponent(siteDate)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(responseData => {
            if (responseData.success) {
                // Populate the edit form with the data
                populateEditForm(responseData.data);
                
                // Hide the view modal and show the edit modal
                document.getElementById('updateDetailsModal').style.display = 'none';
                document.getElementById('editSiteUpdateModal').style.display = 'block';
            } else {
                console.error('Error fetching site update for edit:', responseData.message);
                alert('Error loading site update data for editing: ' + responseData.message);
            }
        })
        .catch(error => {
            console.error('Error fetching site update for edit:', error);
            alert('Error loading site update data for editing. Please try again later.');
        })
        .finally(() => {
            // Reset button state
            document.getElementById('siteDetailEditBtn').innerHTML = '<i class="fas fa-edit"></i> Edit Details';
        });
}

// Function to hide the edit site update modal
function hideEditSiteUpdateModal() {
    document.getElementById('editSiteUpdateModal').style.display = 'none';
}

// Function to populate the edit form with data
function populateEditForm(data) {
    // Set site update ID
    document.getElementById('edit_site_update_id').value = data.id;
    
    // Set basic details
    document.getElementById('edit_site_name').value = data.site_name;
    
    // Convert date to YYYY-MM-DD format for the date input
    if (data.update_date) {
        const dateObj = new Date(data.update_date);
        const formattedDate = dateObj.toISOString().split('T')[0];
        document.getElementById('edit_update_date').value = formattedDate;
    }
    
    // Clear existing data containers
    document.getElementById('edit-vendors-container').innerHTML = '';
    document.getElementById('edit-company-labours-container').innerHTML = '';
    document.getElementById('edit-work-progress-list').innerHTML = '';
    document.getElementById('edit-inventory-list').innerHTML = '';
    
    // Populate vendors and their labours
    if (data.vendors && data.vendors.length > 0) {
        data.vendors.forEach(vendor => {
            // Add vendor and populate it with data
            const vendorId = addVendorToEdit(true);
            populateVendorEdit(vendorId, vendor);
        });
    }
    
    // Populate company labours
    if (data.company_labours && data.company_labours.length > 0) {
        data.company_labours.forEach(labour => {
            // Add company labour and populate it with data
            const labourId = addCompanyLabourToEdit(true);
            populateCompanyLabourEdit(labourId, labour);
        });
    }
    
    // Populate work progress items
    if (data.work_progress && data.work_progress.length > 0) {
        data.work_progress.forEach(item => {
            // Add work progress item and populate it with data
            const itemId = addWorkProgressToEdit(item.category.toLowerCase(), true);
            populateWorkProgressEdit(itemId, item);
        });
    }
    
    // Populate inventory items
    if (data.inventory && data.inventory.length > 0) {
        data.inventory.forEach(item => {
            // Add inventory item and populate it with data
            const itemId = addInventoryItemToEdit(true);
            populateInventoryEdit(itemId, item);
        });
    }
    
    // Update totals
    if (data.expenses) {
        document.getElementById('edit-total-wages').textContent = parseFloat(data.expenses.total_wages).toFixed(2);
        document.getElementById('edit-total-wages-input').value = data.expenses.total_wages;
        
        document.getElementById('edit-total-misc-expenses').textContent = parseFloat(data.expenses.total_misc_expenses).toFixed(2);
        document.getElementById('edit-total-misc-expenses-input').value = data.expenses.total_misc_expenses;
        
        document.getElementById('edit-grand-total').textContent = parseFloat(data.expenses.grand_total).toFixed(2);
        document.getElementById('edit-grand-total-input').value = data.expenses.grand_total;
    }
}

// Add vendor to the edit form
function addVendorToEdit(silent = false) {
    window.vendorEditCounter = window.vendorEditCounter || 0;
    window.vendorEditCounter++;
    
    const vendorsContainer = document.getElementById('edit-vendors-container');
    
    const vendorDiv = document.createElement('div');
    vendorDiv.className = 'vendor-container';
    vendorDiv.id = `edit-vendor-${window.vendorEditCounter}`;
    
    vendorDiv.innerHTML = `
        <div class="vendor-header">
            <div class="vendor-type-select">
                <label for="edit-vendor-type-${window.vendorEditCounter}">Vendor Service</label>
                <select class="form-control" id="edit-vendor-type-${window.vendorEditCounter}" name="edit_vendors[${window.vendorEditCounter}][type]" required>
                    <option value="">Select Vendor Type</option>
                    <option value="POP">POP</option>
                    <option value="Tile">Tile</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Plumbing">Plumbing</option>
                    <option value="Carpentry">Carpentry</option>
                    <option value="Painting">Painting</option>
                    <option value="HVAC">HVAC</option>
                    <option value="Flooring">Flooring</option>
                    <option value="Roofing">Roofing</option>
                    <option value="Masonry">Masonry</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <button type="button" class="remove-btn" onclick="removeVendorFromEdit(${window.vendorEditCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <input type="hidden" name="edit_vendors[${window.vendorEditCounter}][db_id]" id="edit-vendor-db-id-${window.vendorEditCounter}" value="">
        <div class="form-group">
            <label for="edit-vendor-name-${window.vendorEditCounter}">Vendor Name</label>
            <input type="text" class="form-control" id="edit-vendor-name-${window.vendorEditCounter}" name="edit_vendors[${window.vendorEditCounter}][name]" required>
        </div>
        <div class="form-group">
            <label for="edit-vendor-contact-${window.vendorEditCounter}">Contact Number</label>
            <input type="text" class="form-control" id="edit-vendor-contact-${window.vendorEditCounter}" name="edit_vendors[${window.vendorEditCounter}][contact]">
        </div>
        
        <div class="vendor-labours" id="edit-vendor-labours-${window.vendorEditCounter}">
            <!-- Labours will be added here -->
        </div>
        <button type="button" class="btn-add-item" onclick="addLabourToEdit(${window.vendorEditCounter})">
            <i class="fas fa-plus"></i> Add Labour
        </button>
    `;
    
    vendorsContainer.appendChild(vendorDiv);
    
    if (!silent) {
        // Scroll to the newly added vendor
        setTimeout(() => {
            vendorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
    
    return window.vendorEditCounter;
}

// Remove vendor from the edit form
function removeVendorFromEdit(id) {
    const vendorDiv = document.getElementById(`edit-vendor-${id}`);
    vendorDiv.remove();
    // Update totals
    updateEditTotals();
}

// Populate vendor in the edit form
function populateVendorEdit(vendorId, vendorData) {
    if (vendorData.id) {
        document.getElementById(`edit-vendor-db-id-${vendorId}`).value = vendorData.id;
    }
    
    document.getElementById(`edit-vendor-type-${vendorId}`).value = vendorData.type;
    document.getElementById(`edit-vendor-name-${vendorId}`).value = vendorData.name;
    
    if (vendorData.contact) {
        document.getElementById(`edit-vendor-contact-${vendorId}`).value = vendorData.contact;
    }
    
    // Add labours if any
    if (vendorData.labours && vendorData.labours.length > 0) {
        vendorData.labours.forEach(labour => {
            const labourId = addLabourToEdit(vendorId, true);
            populateLabourEdit(vendorId, labourId, labour);
        });
    }
}

// Add labour to a vendor in the edit form
function addLabourToEdit(vendorId, silent = false) {
    window.labourEditCounter = window.labourEditCounter || 0;
    window.labourEditCounter++;
    
    const labourContainer = document.getElementById(`edit-vendor-labours-${vendorId}`);
    
    // Count existing labor items for this vendor to determine number
    const existingLabors = labourContainer.querySelectorAll('.labour-container').length;
    const laborNumber = existingLabors + 1;
    
    const labourDiv = document.createElement('div');
    labourDiv.className = 'labour-container';
    labourDiv.id = `edit-labour-${window.labourEditCounter}`;
    
    labourDiv.innerHTML = `
        <input type="hidden" id="edit-labour-db-id-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][db_id]" value="">
        <div class="labour-header">
            <strong>Labour #${laborNumber}</strong>
            <button type="button" class="remove-btn" onclick="removeLabourFromEdit(${window.labourEditCounter}, ${vendorId})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="edit-labour-name-${window.labourEditCounter}">Labour Name</label>
                    <input type="text" class="form-control" id="edit-labour-name-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][name]" required>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="edit-labour-mobile-${window.labourEditCounter}">Mobile Number</label>
                    <input type="text" class="form-control" id="edit-labour-mobile-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][mobile]">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-labour-attendance-${window.labourEditCounter}">Attendance</label>
                    <select class="form-control" id="edit-labour-attendance-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][attendance]" required onchange="calculateEditLabourTotal(${window.labourEditCounter})">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half-day">Half-day</option>
                    </select>
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-labour-ot-hours-${window.labourEditCounter}">OT Hours</label>
                    <input type="number" class="form-control" id="edit-labour-ot-hours-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateEditLabourTotal(${window.labourEditCounter})">
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-labour-wage-${window.labourEditCounter}">Wage (₹)</label>
                    <input type="number" class="form-control" id="edit-labour-wage-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][wage]" value="0" min="0" required onchange="calculateEditLabourTotal(${window.labourEditCounter})">
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-labour-ot-amount-${window.labourEditCounter}">OT Amount (₹)</label>
                    <input type="number" class="form-control" id="edit-labour-ot-amount-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][ot_amount]" value="0" min="0" readonly>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="edit-labour-total-${window.labourEditCounter}">Total Amount (₹)</label>
                    <input type="number" class="form-control edit-vendor-labour-total" id="edit-labour-total-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][total]" value="0" min="0" readonly>
                </div>
            </div>
        </div>
    `;
    
    labourContainer.appendChild(labourDiv);
    
    if (!silent) {
        // Scroll to the newly added labour
        setTimeout(() => {
            labourDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
    
    return window.labourEditCounter;
}

// Remove labour from the edit form
function removeLabourFromEdit(id, vendorId) {
    const labourDiv = document.getElementById(`edit-labour-${id}`);
    labourDiv.remove();
    
    // Update numbers for remaining labor items
    if (vendorId) {
        const labourContainer = document.getElementById(`edit-vendor-labours-${vendorId}`);
        if (labourContainer) {
            const laborItems = labourContainer.querySelectorAll('.labour-container');
            laborItems.forEach((item, index) => {
                const headerEl = item.querySelector('.labour-header strong');
                if (headerEl) {
                    headerEl.textContent = `Labour #${index + 1}`;
                }
            });
        }
    }
    
    // Update totals
    updateEditTotals();
}

// Populate labour in the edit form
function populateLabourEdit(vendorId, labourId, labourData) {
    if (labourData.id) {
        document.getElementById(`edit-labour-db-id-${labourId}`).value = labourData.id;
    }
    
    document.getElementById(`edit-labour-name-${labourId}`).value = labourData.name;
    
    if (labourData.mobile) {
        document.getElementById(`edit-labour-mobile-${labourId}`).value = labourData.mobile;
    }
    
    document.getElementById(`edit-labour-attendance-${labourId}`).value = labourData.attendance;
    document.getElementById(`edit-labour-ot-hours-${labourId}`).value = labourData.ot_hours;
    document.getElementById(`edit-labour-wage-${labourId}`).value = labourData.wage;
    document.getElementById(`edit-labour-ot-amount-${labourId}`).value = labourData.ot_amount;
    document.getElementById(`edit-labour-total-${labourId}`).value = labourData.total;
}

// Calculate labour totals in the edit form
function calculateEditLabourTotal(labourId) {
    const attendanceSelect = document.getElementById(`edit-labour-attendance-${labourId}`);
    const otHoursInput = document.getElementById(`edit-labour-ot-hours-${labourId}`);
    const wageInput = document.getElementById(`edit-labour-wage-${labourId}`);
    const otAmountInput = document.getElementById(`edit-labour-ot-amount-${labourId}`);
    const totalInput = document.getElementById(`edit-labour-total-${labourId}`);
    
    const attendance = attendanceSelect.value;
    const otHours = parseFloat(otHoursInput.value) || 0;
    const wage = parseFloat(wageInput.value) || 0;
    
    // Calculate attendance factor
    let attendanceFactor = 1;
    if (attendance === 'Absent') {
        attendanceFactor = 0;
    } else if (attendance === 'Half-day') {
        attendanceFactor = 0.5;
    }
    
    // Calculate OT amount (1.5x regular wage)
    const otRate = wage / 8 * 1.5; // Assuming 8-hour workday
    const otAmount = otHours * otRate;
    
    // Calculate total
    const total = (wage * attendanceFactor) + otAmount;
    
    // Update fields
    otAmountInput.value = otAmount.toFixed(2);
    totalInput.value = total.toFixed(2);
    
    // Update total wages and grand total
    updateEditTotals();
}

// Add company labour to the edit form
function addCompanyLabourToEdit(silent = false) {
    window.companyLabourEditCounter = window.companyLabourEditCounter || 0;
    window.companyLabourEditCounter++;
    
    const container = document.getElementById('edit-company-labours-container');
    
    // Count existing labor items to determine number
    const existingLabors = container.querySelectorAll('.company-labour-container').length;
    const laborNumber = existingLabors + 1;
    
    const labourDiv = document.createElement('div');
    labourDiv.className = 'company-labour-container';
    labourDiv.id = `edit-company-labour-${window.companyLabourEditCounter}`;
    
    labourDiv.innerHTML = `
        <button type="button" class="remove-btn-corner" onclick="removeCompanyLabourFromEdit(${window.companyLabourEditCounter})">
            <i class="fas fa-times"></i>
        </button>
        <h4 style="margin-bottom: 15px; font-size: 16px; color: #333;">Company Labour #${laborNumber}</h4>
        <input type="hidden" id="edit-company-labour-db-id-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][db_id]" value="">
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="edit-company-labour-name-${window.companyLabourEditCounter}">Labour Name</label>
                    <input type="text" class="form-control" id="edit-company-labour-name-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][name]" required>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="edit-company-labour-mobile-${window.companyLabourEditCounter}">Mobile Number</label>
                    <input type="text" class="form-control" id="edit-company-labour-mobile-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][mobile]">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-company-labour-morning-attendance-${window.companyLabourEditCounter}">Morning Att.</label>
                    <select class="form-control" id="edit-company-labour-morning-attendance-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][morning_attendance]" required onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half-day">Half-day</option>
                    </select>
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-company-labour-afternoon-attendance-${window.companyLabourEditCounter}">Afternoon Att</label>
                    <select class="form-control" id="edit-company-labour-afternoon-attendance-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][afternoon_attendance]" required onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half-day">Half-day</option>
                    </select>
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-company-labour-ot-hours-${window.companyLabourEditCounter}">OT Hours</label>
                    <input type="number" class="form-control" id="edit-company-labour-ot-hours-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <label for="edit-company-labour-ot-wages-${window.companyLabourEditCounter}">OT Wages (₹/hr)</label>
                    <input type="number" class="form-control" id="edit-company-labour-ot-wages-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][ot_wages]" value="0" min="0" onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-4">
                <div class="form-group">
                    <label for="edit-company-labour-wage-${window.companyLabourEditCounter}">Wage (₹)</label>
                    <input type="number" class="form-control" id="edit-company-labour-wage-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][wage]" value="0" min="0" required onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="edit-company-labour-ot-amount-${window.companyLabourEditCounter}">OT Amount (₹)</label>
                    <input type="number" class="form-control" id="edit-company-labour-ot-amount-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][ot_amount]" value="0" min="0" onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                </div>
            </div>
            <div class="col-4">
                <div class="form-group">
                    <label for="edit-company-labour-total-${window.companyLabourEditCounter}">Total Amount (₹)</label>
                    <input type="number" class="form-control edit-company-labour-total" id="edit-company-labour-total-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][total]" value="0" min="0" readonly>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(labourDiv);
    
    if (!silent) {
        // Scroll to the newly added labour
        setTimeout(() => {
            labourDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
    
    return window.companyLabourEditCounter;
}

// Remove company labour from the edit form
function removeCompanyLabourFromEdit(id) {
    const labourDiv = document.getElementById(`edit-company-labour-${id}`);
    labourDiv.remove();
    
    // Update numbers for remaining labor items
    const container = document.getElementById('edit-company-labours-container');
    if (container) {
        const laborItems = container.querySelectorAll('.company-labour-container');
        laborItems.forEach((item, index) => {
            const headerEl = item.querySelector('h4');
            if (headerEl) {
                headerEl.textContent = `Company Labour #${index + 1}`;
            }
        });
    }
    
    // Update totals
    updateEditTotals();
}

// Populate company labour in the edit form
function populateCompanyLabourEdit(labourId, labourData) {
    if (labourData.id) {
        document.getElementById(`edit-company-labour-db-id-${labourId}`).value = labourData.id;
    }
    
    document.getElementById(`edit-company-labour-name-${labourId}`).value = labourData.name;
    
    if (labourData.mobile) {
        document.getElementById(`edit-company-labour-mobile-${labourId}`).value = labourData.mobile;
    }
    
    if (labourData.morning_attendance) {
        document.getElementById(`edit-company-labour-morning-attendance-${labourId}`).value = labourData.morning_attendance;
    }
    
    if (labourData.afternoon_attendance) {
        document.getElementById(`edit-company-labour-afternoon-attendance-${labourId}`).value = labourData.afternoon_attendance;
    } else if (labourData.attendance) {
        // Fallback for backward compatibility
        document.getElementById(`edit-company-labour-morning-attendance-${labourId}`).value = labourData.attendance;
        document.getElementById(`edit-company-labour-afternoon-attendance-${labourId}`).value = labourData.attendance;
    }
    
    document.getElementById(`edit-company-labour-ot-hours-${labourId}`).value = labourData.ot_hours;
    document.getElementById(`edit-company-labour-wage-${labourId}`).value = labourData.wage;
    
    if (labourData.ot_wages) {
        document.getElementById(`edit-company-labour-ot-wages-${labourId}`).value = labourData.ot_wages;
    } else {
        // Calculate OT wages if not provided
        const wage = parseFloat(labourData.wage) || 0;
        const otWages = wage / 8 * 1.5; // Assuming 8-hour workday
        document.getElementById(`edit-company-labour-ot-wages-${labourId}`).value = otWages.toFixed(2);
    }
    
    document.getElementById(`edit-company-labour-ot-amount-${labourId}`).value = labourData.ot_amount;
    document.getElementById(`edit-company-labour-total-${labourId}`).value = labourData.total;
}

// Calculate company labour totals in the edit form
function calculateEditCompanyLabourTotal(labourId) {
    const morningAttendanceSelect = document.getElementById(`edit-company-labour-morning-attendance-${labourId}`);
    const afternoonAttendanceSelect = document.getElementById(`edit-company-labour-afternoon-attendance-${labourId}`);
    const otHoursInput = document.getElementById(`edit-company-labour-ot-hours-${labourId}`);
    const otWagesInput = document.getElementById(`edit-company-labour-ot-wages-${labourId}`);
    const wageInput = document.getElementById(`edit-company-labour-wage-${labourId}`);
    const otAmountInput = document.getElementById(`edit-company-labour-ot-amount-${labourId}`);
    const totalInput = document.getElementById(`edit-company-labour-total-${labourId}`);
    
    const morningAttendance = morningAttendanceSelect.value;
    const afternoonAttendance = afternoonAttendanceSelect.value;
    const otHours = parseFloat(otHoursInput.value) || 0;
    const wage = parseFloat(wageInput.value) || 0;
    let otWages = parseFloat(otWagesInput.value);
    let otAmount = parseFloat(otAmountInput.value);
    
    // Calculate attendance factor for morning
    let morningFactor = 1;
    if (morningAttendance === 'Absent') {
        morningFactor = 0;
    } else if (morningAttendance === 'Half-day') {
        morningFactor = 0.5;
    }
    
    // Calculate attendance factor for afternoon
    let afternoonFactor = 1;
    if (afternoonAttendance === 'Absent') {
        afternoonFactor = 0;
    } else if (afternoonAttendance === 'Half-day') {
        afternoonFactor = 0.5;
    }
    
    // Combined attendance factor (morning counts for 0.5 of day, afternoon counts for 0.5)
    const combinedFactor = (morningFactor * 0.5) + (afternoonFactor * 0.5);
    
    // If user hasn't entered OT Wages, calculate default
    if (!otWagesInput.value || isNaN(otWages)) {
        otWages = wage / 8 * 1.5;
        otWagesInput.value = otWages.toFixed(2);
    }
    
    // If user hasn't entered OT Amount, calculate
    if (!otAmountInput.value || isNaN(otAmount)) {
        otAmount = otHours * otWages;
        otAmountInput.value = otAmount.toFixed(2);
    }
    
    // Calculate total
    const total = (wage * combinedFactor) + otAmount;
    totalInput.value = total.toFixed(2);
    
    // Update totals
    updateEditTotals();
}

// Update totals in the edit form
function updateEditTotals() {
    // Sum vendor labour totals
    const vendorLabourTotals = document.querySelectorAll('.edit-vendor-labour-total');
    let vendorLaboursTotal = 0;
    
    vendorLabourTotals.forEach(input => {
        vendorLaboursTotal += parseFloat(input.value) || 0;
    });
    
    // Sum company labour totals
    const companyLabourTotals = document.querySelectorAll('.edit-company-labour-total');
    let companyLaboursTotal = 0;
    
    companyLabourTotals.forEach(input => {
        companyLaboursTotal += parseFloat(input.value) || 0;
    });
    
    // Combined total
    const totalWages = vendorLaboursTotal + companyLaboursTotal;
    
    const totalWagesSpan = document.getElementById('edit-total-wages');
    const totalWagesInput = document.getElementById('edit-total-wages-input');
    
    if (totalWagesSpan) totalWagesSpan.textContent = totalWages.toFixed(2);
    if (totalWagesInput) totalWagesInput.value = totalWages.toFixed(2);
    
    // Update grand total
    updateEditGrandTotal();
}

// Update grand total in the edit form
function updateEditGrandTotal() {
    const wagesInput = document.getElementById('edit-total-wages-input');
    const miscExpensesInput = document.getElementById('edit-total-misc-expenses-input');
    
    const wages = wagesInput ? (parseFloat(wagesInput.value) || 0) : 0;
    const miscExpenses = miscExpensesInput ? (parseFloat(miscExpensesInput.value) || 0) : 0;
    
    const grandTotal = wages + miscExpenses;
    
    const grandTotalSpan = document.getElementById('edit-grand-total');
    const grandTotalInput = document.getElementById('edit-grand-total-input');
    
    if (grandTotalSpan) grandTotalSpan.textContent = grandTotal.toFixed(2);
    if (grandTotalInput) grandTotalInput.value = grandTotal.toFixed(2);
}

// Add work progress item to the edit form
function addWorkProgressToEdit(category, silent = false) {
    window.workProgressEditCounter = window.workProgressEditCounter || 0;
    window.workProgressEditCounter++;
    
    const container = document.getElementById('edit-work-progress-list');
    
    const workItem = document.createElement('div');
    workItem.className = 'work-progress-item';
    workItem.id = `edit-work-progress-${window.workProgressEditCounter}`;
    
    // Convert category to title case for display
    const categoryDisplay = category.charAt(0).toUpperCase() + category.slice(1);
    
    // Create options based on category
    let workTypeOptions = '';
    
    if (category === 'civil') {
        workTypeOptions = `
            <option value="">Select Civil Work</option>
            <option value="Foundation">Foundation</option>
            <option value="Excavation">Excavation</option>
            <option value="RCC Work">RCC Work</option>
            <option value="Brickwork">Brickwork</option>
            <option value="Plastering">Plastering</option>
            <option value="Flooring Base Preparation">Flooring Base Preparation</option>
            <option value="Waterproofing">Waterproofing</option>
            <option value="External Plastering">External Plastering</option>
            <option value="Concrete Work">Concrete Work</option>
            <option value="Drainage">Drainage System</option>
            <option value="Other Civil Work">Other Civil Work</option>
        `;
    } else if (category === 'interior') {
        workTypeOptions = `
            <option value="">Select Interior Work</option>
            <option value="Painting">Painting</option>
            <option value="Flooring">Flooring</option>
            <option value="Wall Cladding">Wall Cladding</option>
            <option value="Ceiling Work">Ceiling Work</option>
            <option value="Furniture Installation">Furniture Installation</option>
            <option value="Electrical">Electrical Fittings</option>
            <option value="Plumbing">Plumbing Fixtures</option>
            <option value="Tiling">Tiling</option>
            <option value="Carpentry">Carpentry</option>
            <option value="Lighting">Lighting Installation</option>
            <option value="HVAC">HVAC Installation</option>
            <option value="Other Interior Work">Other Interior Work</option>
        `;
    }
    
    workItem.innerHTML = `
        <button type="button" class="remove-btn-corner" onclick="removeWorkProgressFromEdit(${window.workProgressEditCounter})">
            <i class="fas fa-times"></i>
        </button>
        <input type="hidden" name="edit_work_progress[${window.workProgressEditCounter}][db_id]" id="edit-work-progress-db-id-${window.workProgressEditCounter}" value="">
        <input type="hidden" name="edit_work_progress[${window.workProgressEditCounter}][category]" value="${category}">
        <div class="work-progress-header">
            <div class="header-left">
                <i class="${category === 'civil' ? 'fas fa-hammer' : 'fas fa-couch'}"></i>
                <span class="header-title">${categoryDisplay} Work</span>
            </div>
        </div>
        <div class="form-group">
            <label for="edit-work-type-${window.workProgressEditCounter}">${categoryDisplay} Work Type</label>
            <select class="form-control" id="edit-work-type-${window.workProgressEditCounter}" name="edit_work_progress[${window.workProgressEditCounter}][work_type]" required>
                ${workTypeOptions}
            </select>
        </div>
        <div class="form-group">
            <label for="edit-work-status-${window.workProgressEditCounter}">Is Work Completed?</label>
            <select class="form-control" id="edit-work-status-${window.workProgressEditCounter}" name="edit_work_progress[${window.workProgressEditCounter}][status]" required>
                <option value="Yes">Yes</option>
                <option value="In Progress">In Progress</option>
                <option value="No">Not Started</option>
            </select>
        </div>
        <div class="form-group">
            <label for="edit-work-remarks-${window.workProgressEditCounter}">Remarks</label>
            <textarea class="form-control" id="edit-work-remarks-${window.workProgressEditCounter}" name="edit_work_progress[${window.workProgressEditCounter}][remarks]" placeholder="Add any remarks or notes about the work..."></textarea>
        </div>
        <div class="form-group">
            <label for="edit-work-files-${window.workProgressEditCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
            <input type="file" class="form-control" id="edit-work-files-${window.workProgressEditCounter}" name="edit_work_progress_files_${window.workProgressEditCounter}[]" multiple accept="image/*,video/*">
            <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
        </div>
        <div id="edit-existing-files-work-${window.workProgressEditCounter}" class="existing-files-container">
            <!-- Existing files will be loaded here dynamically -->
        </div>
    `;
    
    container.appendChild(workItem);
    
    if (!silent) {
        // Scroll to the newly added work item
        setTimeout(() => {
            workItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
    
    return window.workProgressEditCounter;
}

// Remove work progress from edit form
function removeWorkProgressFromEdit(id) {
    const workItem = document.getElementById(`edit-work-progress-${id}`);
    workItem.remove();
}

// Populate work progress in edit form
function populateWorkProgressEdit(itemId, itemData) {
    if (itemData.id) {
        document.getElementById(`edit-work-progress-db-id-${itemId}`).value = itemData.id;
    }
    
    document.getElementById(`edit-work-type-${itemId}`).value = itemData.work_type;
    document.getElementById(`edit-work-status-${itemId}`).value = itemData.status;
    
    if (itemData.remarks) {
        document.getElementById(`edit-work-remarks-${itemId}`).value = itemData.remarks;
    }
    
    // Add existing files if any
    if (itemData.files && itemData.files.length > 0) {
        const filesContainer = document.getElementById(`edit-existing-files-work-${itemId}`);
        filesContainer.innerHTML = '<div class="existing-files-header"><i class="fas fa-paperclip"></i> Existing Files</div>';
        
        const filesGrid = document.createElement('div');
        filesGrid.className = 'existing-files-grid';
        
        itemData.files.forEach(file => {
            const fileItem = document.createElement('div');
            fileItem.className = 'existing-file-item';
            if (file.type === 'video') {
                fileItem.classList.add('video');
            }
            fileItem.onclick = function(e) {
                // Prevent triggering if clicking on delete button
                if (e.target.closest('.file-delete')) return;
                openMediaModal(file.path, file.type);
            };
            fileItem.style.cursor = 'pointer';
            
            if (file.type === 'image') {
                fileItem.innerHTML = `
                    <img src="${file.path}" alt="Work progress image">
                    <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                        <i class="fas fa-times"></i>
                    </div>
                    <input type="hidden" name="edit_work_progress[${itemId}][existing_files][]" value="${file.id}">
                `;
            } else {
                fileItem.innerHTML = `
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: #343a40; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-film" style="font-size: 24px; color: white;"></i>
                    </div>
                    <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                        <i class="fas fa-times"></i>
                    </div>
                    <input type="hidden" name="edit_work_progress[${itemId}][existing_files][]" value="${file.id}">
                `;
            }
            
            filesGrid.appendChild(fileItem);
        });
        
        filesContainer.appendChild(filesGrid);
    }
}

// Add inventory item to edit form
function addInventoryItemToEdit(silent = false) {
    window.inventoryEditCounter = window.inventoryEditCounter || 0;
    window.inventoryEditCounter++;
    
    const container = document.getElementById('edit-inventory-list');
    
    // Count existing inventory items to determine number
    const existingItems = container.querySelectorAll('.inventory-container').length;
    const itemNumber = existingItems + 1;
    
    const inventoryDiv = document.createElement('div');
    inventoryDiv.className = 'inventory-container';
    inventoryDiv.id = `edit-inventory-${window.inventoryEditCounter}`;
    
    inventoryDiv.innerHTML = `
        <div class="item-header">
            <h4>
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #3498db; color: white; border-radius: 50%; margin-right: 10px; font-size: 14px;">${itemNumber}</span>
                <i class="fas fa-box"></i> Inventory Item
            </h4>
            <button type="button" class="remove-btn" onclick="removeInventoryItemFromEdit(${window.inventoryEditCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <input type="hidden" name="edit_inventory[${window.inventoryEditCounter}][db_id]" id="edit-inventory-db-id-${window.inventoryEditCounter}" value="">
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="edit-material-type-${window.inventoryEditCounter}"><i class="fas fa-cubes"></i> Material</label>
                    <select class="form-control" id="edit-material-type-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][material]" required>
                        <option value="">Select Material</option>
                        <option value="Cement">Cement</option>
                        <option value="Sand">Sand</option>
                        <option value="Aggregate">Aggregate</option>
                        <option value="Bricks">Bricks</option>
                        <option value="Steel">Steel</option>
                        <option value="Timber">Timber</option>
                        <option value="Paint">Paint</option>
                        <option value="Tiles">Tiles</option>
                        <option value="Glass">Glass</option>
                        <option value="Electrical Wires">Electrical Wires</option>
                        <option value="Pipes">Pipes</option>
                        <option value="Sanitary Fixtures">Sanitary Fixtures</option>
                        <option value="Concrete">Concrete</option>
                        <option value="Plaster">Plaster</option>
                        <option value="Gravel">Gravel</option>
                        <option value="Stone Dust">Stone Dust</option>
                        <option value="Water Proofing Materials">Water Proofing Materials</option>
                        <option value="Plywood">Plywood</option>
                        <option value="Adhesives">Adhesives</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="edit-quantity-${window.inventoryEditCounter}"><i class="fas fa-balance-scale"></i> Quantity</label>
                    <input type="number" class="form-control" id="edit-quantity-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][quantity]" min="0" step="any" required>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label for="edit-unit-${window.inventoryEditCounter}"><i class="fas fa-ruler"></i> Unit</label>
                    <select class="form-control" id="edit-unit-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][unit]" required>
                        <option value="">Select Unit</option>
                        <option value="Kg">Kg</option>
                        <option value="Bag">Bag</option>
                        <option value="Ton">Ton</option>
                        <option value="Cubic Meter">Cubic Meter</option>
                        <option value="Square Meter">Square Meter</option>
                        <option value="Meter">Meter</option>
                        <option value="Piece">Piece</option>
                        <option value="Number">Number</option>
                        <option value="Litre">Litre</option>
                        <option value="Quintal">Quintal</option>
                        <option value="Bundle">Bundle</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="edit-standard-values-${window.inventoryEditCounter}"><i class="fas fa-clipboard"></i> Standard Values/Notes</label>
                    <textarea class="form-control" id="edit-standard-values-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][standard_values]" placeholder="Add any standard values or notes about the material..."></textarea>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <label for="edit-inventory-files-${window.inventoryEditCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                    <input type="file" class="form-control" id="edit-inventory-files-${window.inventoryEditCounter}" name="edit_inventory_files_${window.inventoryEditCounter}[]" multiple accept="image/*,video/*">
                    <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                </div>
            </div>
        </div>
        <div id="edit-existing-files-inventory-${window.inventoryEditCounter}" class="existing-files-container">
            <!-- Existing files will be loaded here dynamically -->
        </div>
    `;
    
    container.appendChild(inventoryDiv);
    
    if (!silent) {
        // Scroll to the newly added inventory
        setTimeout(() => {
            inventoryDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
    
    return window.inventoryEditCounter;
}

// Remove inventory item from edit form
function removeInventoryItemFromEdit(id) {
    const inventoryDiv = document.getElementById(`edit-inventory-${id}`);
    inventoryDiv.remove();
    
    // Update numbers for remaining inventory items
    const container = document.getElementById('edit-inventory-list');
    if (container) {
        const items = container.querySelectorAll('.inventory-container');
        items.forEach((item, index) => {
            const numberEl = item.querySelector('.item-header h4 span');
            if (numberEl) {
                numberEl.textContent = index + 1;
            }
        });
    }
}

// Populate inventory item in edit form
function populateInventoryEdit(itemId, itemData) {
    if (itemData.id) {
        document.getElementById(`edit-inventory-db-id-${itemId}`).value = itemData.id;
    }
    
    document.getElementById(`edit-material-type-${itemId}`).value = itemData.material;
    document.getElementById(`edit-quantity-${itemId}`).value = itemData.quantity;
    document.getElementById(`edit-unit-${itemId}`).value = itemData.unit;
    
    if (itemData.standard_values) {
        document.getElementById(`edit-standard-values-${itemId}`).value = itemData.standard_values;
    }
    
    // Add existing files if any
    if (itemData.files && itemData.files.length > 0) {
        const filesContainer = document.getElementById(`edit-existing-files-inventory-${itemId}`);
        filesContainer.innerHTML = '<div class="existing-files-header"><i class="fas fa-paperclip"></i> Existing Files</div>';
        
        const filesGrid = document.createElement('div');
        filesGrid.className = 'existing-files-grid';
        
        itemData.files.forEach(file => {
            const fileItem = document.createElement('div');
            fileItem.className = 'existing-file-item';
            if (file.type === 'video') {
                fileItem.classList.add('video');
            }
            fileItem.onclick = function(e) {
                // Prevent triggering if clicking on delete button
                if (e.target.closest('.file-delete')) return;
                openMediaModal(file.path, file.type);
            };
            fileItem.style.cursor = 'pointer';
            
            if (file.type === 'image') {
                fileItem.innerHTML = `
                    <img src="${file.path}" alt="Inventory image">
                    <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                        <i class="fas fa-times"></i>
                    </div>
                    <input type="hidden" name="edit_inventory[${itemId}][existing_files][]" value="${file.id}">
                `;
            } else {
                fileItem.innerHTML = `
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: #343a40; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-film" style="font-size: 24px; color: white;"></i>
                    </div>
                    <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                        <i class="fas fa-times"></i>
                    </div>
                    <input type="hidden" name="edit_inventory[${itemId}][existing_files][]" value="${file.id}">
                `;
            }
            
            filesGrid.appendChild(fileItem);
        });
        
        filesContainer.appendChild(filesGrid);
    }
}

// Function to update all totals in the edit form
function updateEditTotals() {
    // Calculate total wages
    let totalWages = 0;
    
    // Calculate vendor labour wages
    const vendorLabourTotals = document.querySelectorAll('.edit-vendor-labour-total');
    vendorLabourTotals.forEach(totalInput => {
        totalWages += parseFloat(totalInput.value) || 0;
    });
    
    // Calculate company labour wages
    const companyLabourTotals = document.querySelectorAll('.edit-company-labour-total');
    companyLabourTotals.forEach(totalInput => {
        totalWages += parseFloat(totalInput.value) || 0;
    });
    
    // Update total wages display and input
    document.getElementById('edit-total-wages').textContent = totalWages.toFixed(2);
    document.getElementById('edit-total-wages-input').value = totalWages.toFixed(2);
    
    // Calculate grand total (just total wages for now, can add more components later)
    const grandTotal = totalWages;
    
    // Update grand total display and input
    document.getElementById('edit-grand-total').textContent = grandTotal.toFixed(2);
    document.getElementById('edit-grand-total-input').value = grandTotal.toFixed(2);
}

// Handle removing existing files
function removeExistingFile(element, fileId) {
    // Remove the file item from the UI
    const fileItem = element.closest('.existing-file-item');
    if (fileItem) {
        // Add a hidden input to track deleted files
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'deleted_files[]';
        hiddenInput.value = fileId;
        
        // Add to the form
        const form = document.getElementById('edit-site-update-form');
        if (form) {
            form.appendChild(hiddenInput);
        }
        
        // Remove the element with animation
        fileItem.style.transition = 'all 0.3s ease';
        fileItem.style.transform = 'scale(0.8)';
        fileItem.style.opacity = '0';
        
        setTimeout(() => {
            fileItem.remove();
        }, 300);
    }
}

// Function to open media in modal for preview
function openMediaModal(filePath, fileType) {
    const modal = document.getElementById('siteDetailMediaModal');
    const modalContent = document.getElementById('siteDetailMediaContent');
    
    if (fileType === 'image') {
        modalContent.innerHTML = `<img src="${filePath}" alt="Media">`;
    } else if (fileType === 'video') {
        modalContent.innerHTML = `<video controls><source src="${filePath}" type="video/mp4">Your browser does not support the video tag.</video>`;
    }
    
    modal.style.display = 'block';
}

// Close media modal
function closeMediaModal() {
    const modal = document.getElementById('siteDetailMediaModal');
    modal.style.display = 'none';
}

// Function to set up Civil Work click handlers when adding new work items
function setupWorkProgressItemEvents() {
    // Handle work type select in the modal
    const workTypeSelect = document.querySelector('#workTypeSelect');
    if (workTypeSelect && workTypeSelect.value === 'Civil Work') {
        // Get the category dropdown and scroll to it for easy access
        const categorySelect = document.querySelector('#categorySelect');
        if (categorySelect) {
            categorySelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    // Add change event to the work type select
    if (workTypeSelect) {
        workTypeSelect.addEventListener('change', function() {
            if (this.value === 'Civil Work') {
                // Update the UI for Civil Work selection
                updateCategoryOptionsForWorkType(this.value);
                
                // Scroll to the category select dropdown
                const categorySelect = document.querySelector('#categorySelect');
                if (categorySelect) {
                    categorySelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                updateCategoryOptionsForWorkType(this.value);
            }
        });
    }
}

// Function to update category options based on work type
function updateCategoryOptionsForWorkType(workType) {
    const categorySelect = document.querySelector('#categorySelect');
    if (!categorySelect) return;
    
    // Clear current options
    categorySelect.innerHTML = '';
    
    if (workType === 'Civil Work') {
        // Add civil work categories
        categorySelect.innerHTML = `
            <option value="">Select Category</option>
            <option value="Foundation Work">Foundation Work</option>
            <option value="Brickwork">Brickwork</option>
            <option value="Plastering">Plastering</option>
            <option value="External Plastering">External Plastering</option>
            <option value="Concrete Work">Concrete Work</option>
            <option value="Drainage System">Drainage System</option>
            <option value="Other Civil Work">Other Civil Work</option>
        `;
    } else if (workType === 'Interior Work') {
        // Add interior work categories
        categorySelect.innerHTML = `
            <option value="">Select Category</option>
            <option value="Painting">Painting</option>
            <option value="Flooring">Flooring</option>
            <option value="Wall Paneling">Wall Paneling</option>
            <option value="Ceiling Work">Ceiling Work</option>
            <option value="Furniture Installation">Furniture Installation</option>
            <option value="Electrical Fittings">Electrical Fittings</option>
            <option value="Plumbing Fixtures">Plumbing Fixtures</option>
            <option value="Tiling">Tiling</option>
            <option value="Carpentry">Carpentry</option>
            <option value="Lighting Installation">Lighting Installation</option>
            <option value="HVAC Installation">HVAC Installation</option>
            <option value="Other Interior Work">Other Interior Work</option>
        `;
    }
}