/**
 * Site Overview JavaScript
 * Handles the functionality for the Site Overview section
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sample site data - in a real application, this would come from an API or database
    const siteData = {
        all: {
            total: 0, // Will be updated with actual data from PHP
            manager: 2,
            engineer: 4,
            supervisor: 0, // Will be updated with actual data from database
            labour: 45
        },
        site1: {
            total: 0, // Will be updated with actual data from PHP
            manager: 1,
            engineer: 2,
            supervisor: 0, // Will be updated with actual data from database
            labour: 20
        },
        site2: {
            total: 0, // Will be updated with actual data from PHP
            manager: 1,
            engineer: 1,
            supervisor: 0, // Will be updated with actual data from database
            labour: 15
        },
        site3: {
            total: 0, // Will be updated with actual data from PHP
            manager: 0,
            engineer: 1,
            supervisor: 0, // Will be updated with actual data from database
            labour: 10
        }
    };
    
    // Fetch supervisor count from database
    fetchSupervisorData('all').then(supervisors => {
        const presentCount = supervisors.filter(s => s.attendance_status === 'Present').length;
        
        // Update the supervisor count in the main dashboard
        const supervisorBox = document.querySelector('[data-site-stat="supervisor"] .stat-numbers');
        if (supervisorBox) {
            supervisorBox.textContent = presentCount;
        }
        
        // Update the site data
        siteData.all.supervisor = presentCount;
        
        // Distribute proportionally to individual sites for demo purposes
        siteData.site1.supervisor = Math.round(presentCount * 0.4); // 40%
        siteData.site2.supervisor = Math.round(presentCount * 0.3); // 30%
        siteData.site3.supervisor = Math.round(presentCount * 0.3); // 30%
    });
    
    // Fetch daily events count from database
    fetchDailyEvents().then(events => {
        const totalCount = events.length;
        
        // Update the manager box (now showing daily events) in the main dashboard
        const managerBox = document.querySelector('[data-site-stat="manager"] .stat-numbers');
        if (managerBox) {
            managerBox.textContent = totalCount;
        }
        
        // Update the site data
        siteData.all.manager = totalCount;
        
        // Update the manager box title to reflect its new purpose
        const managerTitle = document.querySelector('[data-site-stat="manager"] .stat-content h4');
        if (managerTitle) {
            managerTitle.textContent = "Today's Updates";
        }
        
        // Update the manager box icon
        const managerIcon = document.querySelector('[data-site-stat="manager"] .stat-icon i');
        if (managerIcon) {
            managerIcon.className = 'bi bi-calendar-check';
        }
        
        // Update the label
        const managerLabel = document.querySelector('[data-site-stat="manager"] .stat-label');
        if (managerLabel) {
            managerLabel.textContent = 'Events Today';
        }
    });

    // Get the actual total labour count from the DOM
    const totalLabourElement = document.querySelector('.employee-stat-box[data-site-stat="total"] .stat-numbers');
    if (totalLabourElement) {
        const totalLabourCount = parseInt(totalLabourElement.textContent) || 0;
        // Update all site data with the actual count
        siteData.all.total = totalLabourCount;
        // Distribute proportionally to individual sites for demo purposes
        siteData.site1.total = Math.round(totalLabourCount * 0.45); // 45%
        siteData.site2.total = Math.round(totalLabourCount * 0.33); // 33%
        siteData.site3.total = Math.round(totalLabourCount * 0.22); // 22%
    }

    // Sample detailed data for each category
    const detailedData = {
        total: [], // Will be fetched dynamically
        manager: [
            { name: "John Doe", site: "Site 1", phone: "555-1234", email: "john.doe@example.com" },
            { name: "Jane Smith", site: "Site 2", phone: "555-5678", email: "jane.smith@example.com" }
        ],
        engineer: [
            { name: "Michael Brown", site: "Site 1", specialization: "Civil", experience: "8 years" },
            { name: "Emily Davis", site: "Site 1", specialization: "Electrical", experience: "6 years" },
            { name: "David Wilson", site: "Site 2", specialization: "Mechanical", experience: "7 years" },
            { name: "Sarah Miller", site: "Site 3", specialization: "Structural", experience: "5 years" }
        ],
        supervisor: [
            { name: "Thomas Moore", site: "Site 1", team: "Foundation", workers: 8 },
            { name: "Lisa Johnson", site: "Site 1", team: "Electrical", workers: 6 },
            { name: "James Anderson", site: "Site 2", team: "Plumbing", workers: 5 },
            { name: "Patricia White", site: "Site 2", team: "Carpentry", workers: 7 },
            { name: "Richard Taylor", site: "Site 3", team: "Masonry", workers: 5 },
            { name: "Jennifer Martin", site: "Site 3", team: "Finishing", workers: 5 }
        ],
        labour: [
            { site: "Site 1", skilled: 12, unskilled: 13, attendance: "92%" },
            { site: "Site 2", skilled: 8, unskilled: 10, attendance: "88%" },
            { site: "Site 3", skilled: 6, unskilled: 9, attendance: "90%" }
        ]
    };

    // Get the site filter dropdown
    const siteFilter = document.getElementById('siteFilter');
    if (!siteFilter) return;

    // Function to update the site overview data
    function updateSiteOverview(siteId) {
        const data = siteData[siteId] || siteData.all;
        
        // Update each stat box with the corresponding data
        Object.keys(data).forEach(key => {
            const statBox = document.querySelector(`.employee-stat-box[data-site-stat="${key}"]`);
            if (statBox) {
                const statNumber = statBox.querySelector('.stat-numbers');
                if (statNumber && key !== 'total') { // Skip animation for total labour as it's fetched from PHP
                    // Add animation effect
                    animateNumber(statNumber, parseInt(statNumber.textContent), data[key]);
                }
            }
        });
    }

    // Function to animate number changes
    function animateNumber(element, start, end) {
        const duration = 1000; // Animation duration in milliseconds
        const frameDuration = 1000 / 60; // 60fps
        const totalFrames = Math.round(duration / frameDuration);
        const increment = (end - start) / totalFrames;
        
        let currentFrame = 0;
        let currentValue = start;
        
        const animate = () => {
            currentFrame++;
            currentValue += increment;
            
            if (currentFrame === totalFrames) {
                element.textContent = end;
            } else {
                element.textContent = Math.round(currentValue);
                requestAnimationFrame(animate);
            }
        };
        
        animate();
    }

    // Add event listener to the site filter dropdown
    siteFilter.addEventListener('change', function() {
        updateSiteOverview(this.value);
    });

    // Add hover effects to stat boxes
    const statBoxes = document.querySelectorAll('.employee-stat-box');
    statBoxes.forEach(box => {
        box.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 15px rgba(0, 0, 0, 0.1)';
        });
        
        box.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
        });
    });

    // Add event listeners for View Details buttons
    const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const siteType = this.getAttribute('data-site-type');
            const selectedSite = siteFilter.value;
            
            // For total labour, fetch data first
            if (siteType === 'total') {
                fetchLabourData(selectedSite).then(data => {
                    showSiteDetailsModal(siteType, selectedSite, data);
                });
            } else {
                showSiteDetailsModal(siteType, selectedSite);
            }
        });
    });

    // Function to fetch labour data from the server
    async function fetchLabourData(selectedSite) {
        try {
            const response = await fetch('fetch_labour_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    site: selectedSite
                })
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching labour data:', error);
            return {
                company_labours: [],
                vendor_labours: []
            };
        }
    }

    // Function to fetch supervisor data from the server
    async function fetchSupervisorData(selectedSite) {
        try {
            const response = await fetch('fetch_supervisors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    site: selectedSite
                })
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            return data.supervisors || [];
        } catch (error) {
            console.error('Error fetching supervisor data:', error);
            return [];
        }
    }
    
    // Function to fetch daily events from the server
    async function fetchDailyEvents() {
        try {
            // Get today's date in YYYY-MM-DD format
            const today = new Date().toISOString().split('T')[0];
            
            const response = await fetch(`backend/get_daily_events.php?date=${today}`);
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            return data.events || [];
        } catch (error) {
            console.error('Error fetching daily events:', error);
            return [];
        }
    }

    // Add new function to fetch users who haven't added events
    async function fetchUsersWithoutEvents() {
        try {
            const response = await fetch('backend/get_users_without_events.php');
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            return data.users_without_events || [];
        } catch (error) {
            console.error('Error fetching users without events:', error);
            return [];
        }
    }

    // Fetch users without events and update the Engineer card
    fetchUsersWithoutEvents().then(usersWithoutEvents => {
        const missingEventsCount = usersWithoutEvents.length;
        
        // Update the engineer box in the main dashboard
        const engineerBox = document.querySelector('[data-site-stat="engineer"] .stat-numbers');
        if (engineerBox) {
            engineerBox.textContent = missingEventsCount;
        }
        
        // Update the site data
        siteData.all.engineer = missingEventsCount;
        
        // Update the engineer box title to reflect its new purpose
        const engineerTitle = document.querySelector('[data-site-stat="engineer"] .stat-content h4');
        if (engineerTitle) {
            engineerTitle.textContent = "Missing Supervisor Events";
        }
        
        // Update the engineer box icon
        const engineerIcon = document.querySelector('[data-site-stat="engineer"] .stat-icon i');
        if (engineerIcon) {
            engineerIcon.className = 'bi bi-calendar-x';
        }
        
        // Update the label
        const engineerLabel = document.querySelector('[data-site-stat="engineer"] .stat-label');
        if (engineerLabel) {
            engineerLabel.textContent = 'Supervisors without events';
        }
    });

    // Function to show details modal for different site types
    function showSiteDetailsModal(siteType, selectedSite, fetchedData = null) {
        // Get the appropriate data based on site type
        let data = detailedData[siteType];
        let filteredData = data;
        
        // For total labour, use fetched data
        if (siteType === 'total' && fetchedData) {
            filteredData = fetchedData;
        } else if (selectedSite !== 'all' && siteType !== 'total') {
            // Filter data for specific site if not "all" and not total labour
            filteredData = data.filter(item => item.site === selectedSite.replace('site', 'Site '));
        }
        
        // Create modal HTML
        let modalTitle = '';
        let modalContent = '';
        
        switch(siteType) {
            case 'total':
                modalTitle = 'Total Labour Present Today';
                modalContent = createLabourDetailsTable(filteredData);
                break;
            case 'manager':
                modalTitle = "Today's Updates";
                
                // Show loading indicator
                modalContent = `
                    <div class="text-center my-5">
                        <div class="spinner-border text-warning" role="status"></div>
                        <div class="mt-2">Loading today's updates...</div>
                    </div>
                `;
                
                // Create and show modal with loading indicator
                showDetailsModal(modalTitle, modalContent);
                
                // Fetch daily events
                fetchDailyEvents().then(events => {
                    // Update the modal content with the fetched data
                    const modalBody = document.querySelector('#siteDetailsModal .modal-body');
                    if (modalBody) {
                        modalBody.innerHTML = createManagerDetailsTable(events);
                    }
                    
                    // Update the event count in the badge
                    const totalCount = events.length;
                    
                    // Update the count in the modal title
                    const modalTitle = document.querySelector('#siteDetailsModalLabel');
                    if (modalTitle) {
                        modalTitle.innerHTML = `
                            <i class="bi bi-calendar-check me-2 text-warning"></i>
                            Today's Updates
                            <span class="badge bg-warning ms-2">${totalCount} Events</span>
                        `;
                    }
                    
                    // Also update the count in the main dashboard
                    const managerBox = document.querySelector('[data-site-stat="manager"] .stat-numbers');
                    if (managerBox) {
                        managerBox.textContent = totalCount;
                    }
                });
                
                // Return early to prevent the default modal creation
                return;
            case 'engineer':
                modalTitle = 'Supervisors Without Events';
                
                // Show loading indicator
                modalContent = `
                    <div class="text-center my-5">
                        <div class="spinner-border text-danger" role="status"></div>
                        <div class="mt-2">Loading supervisors without events...</div>
                    </div>
                `;
                
                // Create and show modal with loading indicator
                showDetailsModal(modalTitle, modalContent);
                
                // Fetch users without events
                fetchUsersWithoutEvents().then(usersWithoutEvents => {
                    // Update the modal content with the fetched data
                    const modalBody = document.querySelector('#siteDetailsModal .modal-body');
                    if (modalBody) {
                        modalBody.innerHTML = createEngineerDetailsTable(usersWithoutEvents);
                    }
                    
                    // Update the count in the modal title
                    const modalTitle = document.querySelector('#siteDetailsModalLabel');
                    if (modalTitle) {
                        modalTitle.innerHTML = `
                            <i class="bi bi-calendar-x me-2 text-danger"></i>
                            Supervisors Without Events
                            <span class="badge bg-danger ms-2">${usersWithoutEvents.length} Supervisors</span>
                        `;
                    }
                    
                    // Also update the count in the main dashboard
                    const engineerBox = document.querySelector('[data-site-stat="engineer"] .stat-numbers');
                    if (engineerBox) {
                        engineerBox.textContent = usersWithoutEvents.length;
                    }
                });
                
                // Return early to prevent the default modal creation
                return;
            case 'supervisor':
                modalTitle = 'Site Supervisors';
                
                // Show loading indicator first
                modalContent = `
                    <div class="text-center my-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2">Loading supervisor data...</div>
                    </div>
                `;
                
                // Create and show modal with loading indicator
                showDetailsModal(modalTitle, modalContent);
                
                // Fetch real supervisor data from the database
                fetchSupervisorData(selectedSite).then(supervisors => {
                    // Update the modal content with the fetched data
                    const modalBody = document.querySelector('#siteDetailsModal .modal-body');
                    if (modalBody) {
                        modalBody.innerHTML = createSupervisorDetailsTable(supervisors);
                    }
                    
                    // Update the supervisor count in the badge
                    const presentCount = supervisors.filter(s => s.attendance_status === 'Present').length;
                    const totalCount = supervisors.length;
                    
                    // Update the count in the modal title
                    const modalTitle = document.querySelector('#siteDetailsModalLabel');
                    if (modalTitle) {
                        modalTitle.innerHTML = `
                            <i class="bi bi-clipboard-check me-2 text-primary"></i>
                            Site Supervisors
                            <span class="badge bg-success ms-2">${presentCount}/${totalCount} Present</span>
                        `;
                    }
                    
                    // Also update the count in the main dashboard
                    const supervisorBox = document.querySelector('[data-site-stat="supervisor"] .stat-numbers');
                    if (supervisorBox) {
                        supervisorBox.textContent = presentCount;
                    }
                });
                
                // Return early to prevent the default modal creation
                return;
            case 'labour':
                modalTitle = 'Labour Details';
                modalContent = createLabourDetailsTable(filteredData);
                break;
        }
        
        // Create and show modal
        showDetailsModal(modalTitle, modalContent);
    }
    
    // Function to create labour details table (updated for total labour)
    function createLabourDetailsTable(data) {
        // Check if data is in the new format (with company_labours and vendor_labours)
        if (data && (data.company_labours || data.vendor_labours)) {
            // Handle the new data format from fetch_labour_data.php
            const companyLabours = data.company_labours || [];
            const vendorLabours = data.vendor_labours || [];
            
            if (companyLabours.length === 0 && vendorLabours.length === 0) {
                return `
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <div>No labour data available for today.</div>
                    </div>
                `;
            }
            
            let content = '';
            
            // Summary statistics
            const totalCompanyLabours = companyLabours.length;
            const totalVendorLabours = vendorLabours.length;
            const totalLabours = totalCompanyLabours + totalVendorLabours;
            
            content += `
                <div class="labour-summary mb-4">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <h3 class="text-primary mb-0">${totalLabours}</h3>
                                    <p class="text-muted mb-0">Total Present</p>
                                </div>
                                <div class="col-md-4">
                                    <h3 class="text-success mb-0">${totalCompanyLabours}</h3>
                                    <p class="text-muted mb-0">Company Labours</p>
                                </div>
                                <div class="col-md-4">
                                    <h3 class="text-info mb-0">${totalVendorLabours}</h3>
                                    <p class="text-muted mb-0">Vendor Labours</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add charts section with unique IDs to avoid conflicts
            const chartId1 = 'labourDistributionChart_' + Date.now() + Math.floor(Math.random() * 1000);
            const chartId2 = 'attendanceStatusChart_' + Date.now() + Math.floor(Math.random() * 1000);
            
            content += `
                <div class="row mb-4">
                    <!-- Labour Distribution Chart -->
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-pie-chart-fill text-primary me-2"></i>
                                    Labour Distribution
                                </h5>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <canvas id="${chartId1}" height="220" width="100%"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Status Chart -->
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-bar-chart-fill text-success me-2"></i>
                                    Attendance Status
                                </h5>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <canvas id="${chartId2}" height="220" width="100%"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Company Labours Table
            if (companyLabours.length > 0) {
                content += `
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-building text-primary me-2"></i>
                                Company Labours
                            </h5>
                            <span class="badge bg-primary rounded-pill">${companyLabours.length}</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Name</th>
                                            <th scope="col">Contact</th>
                                            <th scope="col">Event</th>
                                            <th scope="col">Morning</th>
                                            <th scope="col">Evening</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                companyLabours.forEach((labour, index) => {
                    content += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                                        <i class="bi bi-person text-primary"></i>
                                    </div>
                                    <div>${labour.labour_name}</div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <a href="tel:${labour.contact_number}" class="text-decoration-none contact-link">
                                        <i class="bi bi-telephone-fill text-success me-2"></i>
                                        ${labour.contact_number || 'N/A'}
                                    </a>
                                </div>
                            </td>
                            <td>${labour.event_title || 'N/A'}</td>
                            <td>${labour.morning_attendance ? '<span class="badge bg-success">Present</span>' : '<span class="badge bg-danger">Absent</span>'}</td>
                            <td>${labour.evening_attendance ? '<span class="badge bg-success">Present</span>' : '<span class="badge bg-danger">Absent</span>'}</td>
                        </tr>
                    `;
                });
                
                content += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Vendor Labours Table
            if (vendorLabours.length > 0) {
                content += `
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-truck text-info me-2"></i>
                                Vendor Labours
                            </h5>
                            <span class="badge bg-info rounded-pill">${vendorLabours.length}</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Name</th>
                                            <th scope="col">Contact</th>
                                            <th scope="col">Vendor</th>
                                            <th scope="col">Event</th>
                                            <th scope="col">Morning</th>
                                            <th scope="col">Evening</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                vendorLabours.forEach((labour, index) => {
                    content += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                                        <i class="bi bi-person text-info"></i>
                                    </div>
                                    <div>${labour.labour_name}</div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <a href="tel:${labour.contact_number}" class="text-decoration-none contact-link">
                                        <i class="bi bi-telephone-fill text-success me-2"></i>
                                        ${labour.contact_number || 'N/A'}
                                    </a>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark">${labour.vendor_name || 'N/A'}</span></td>
                            <td>${labour.event_title || 'N/A'}</td>
                            <td>${labour.morning_attendance ? '<span class="badge bg-success">Present</span>' : '<span class="badge bg-danger">Absent</span>'}</td>
                            <td>${labour.evening_attendance ? '<span class="badge bg-success">Present</span>' : '<span class="badge bg-danger">Absent</span>'}</td>
                        </tr>
                    `;
                });
                
                content += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Calculate attendance statistics
            let attendanceStats = calculateAttendanceStats(companyLabours, vendorLabours);
            
            // Add script to initialize charts using the external functions
            content += `
                <script>
                    // Initialize charts when the modal is shown
                    document.addEventListener('DOMContentLoaded', function() {
                        // Add event listener for modal shown event
                        const modalElement = document.getElementById('siteDetailsModal');
                        if (modalElement) {
                            modalElement.addEventListener('shown.bs.modal', function() {
                                console.log('Modal shown, initializing charts...');
                                // Initialize the labour distribution chart
                                initializeLabourDistributionChart('${chartId1}', ${totalCompanyLabours}, ${totalVendorLabours});
                                
                                // Initialize the attendance status chart
                                initializeAttendanceStatusChart('${chartId2}', ${attendanceStats.morningOnly}, ${attendanceStats.eveningOnly}, ${attendanceStats.fullDay});
                            });
                        }
                    });
                    
                    // Fallback initialization after a delay
                    setTimeout(function() {
                        console.log('Fallback chart initialization...');
                        initializeLabourDistributionChart('${chartId1}', ${totalCompanyLabours}, ${totalVendorLabours});
                        initializeAttendanceStatusChart('${chartId2}', ${attendanceStats.morningOnly}, ${attendanceStats.eveningOnly}, ${attendanceStats.fullDay});
                    }, 1000);
                </script>
            `;
            
            return content;
        } else {
            // Original format for other labour data
            if (data.length === 0) {
                return `
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <div>No labour data available for the selected site.</div>
                    </div>
                `;
            }
            
            let table = `
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Labour Distribution</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Site</th>
                                        <th scope="col">Skilled Workers</th>
                                        <th scope="col">Unskilled Workers</th>
                                        <th scope="col">Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;
            
            data.forEach(labour => {
                table += `
                    <tr>
                        <td>${labour.site}</td>
                        <td>${labour.skilled}</td>
                        <td>${labour.unskilled}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${labour.attendance.replace('%', '')}%"></div>
                                </div>
                                <span>${labour.attendance}</span>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            table += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            return table;
        }
    }
    
    // Function to create manager details table (now showing daily events)
    function createManagerDetailsTable(data) {
        if (data.length === 0) {
            return `
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    No updates available for today.</div>
                `;
        }
        
        // Group events by type for summary
        const eventTypes = {};
        data.forEach(event => {
            if (!eventTypes[event.type]) {
                eventTypes[event.type] = 0;
            }
            eventTypes[event.type]++;
        });
        
        let content = `
            <!-- Summary statistics -->
            <div class="event-summary mb-4">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h3 class="text-warning mb-0">${data.length}</h3>
                                <p class="text-muted mb-0">Total Updates</p>
                            </div>
                            ${Object.entries(eventTypes).map(([type, count]) => `
                                <div class="col-md-3">
                                    <h3 class="text-${getEventTypeColor(type)} mb-0">${count}</h3>
                                    <p class="text-muted mb-0">${capitalizeFirstLetter(type)}s</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Events Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-check text-warning me-2"></i>
                        Today's Updates
                    </h5>
                    <span class="badge bg-warning rounded-pill">${data.length}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Created By</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Details</th>
                                </tr>
                            </thead>
                            <tbody>
        `;
        
        data.forEach((event, index) => {
            const eventTypeIcon = getEventTypeIcon(event.type);
            const eventTypeColor = getEventTypeColor(event.type);
            
            content += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <div class="fw-semibold">${event.title}</div>
                    </td>
                    <td>
                        <span class="badge bg-${eventTypeColor}">
                            <i class="bi ${eventTypeIcon} me-1"></i>
                            ${capitalizeFirstLetter(event.type)}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 28px; height: 28px; line-height: 28px;">
                                <i class="bi bi-person text-primary"></i>
                            </div>
                            <div>${event.created_by.name}</div>
                        </div>
                    </td>
                    <td>
                        <div class="small text-muted">
                            <i class="bi bi-clock me-1"></i>
                            ${event.created_at}
                        </div>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            ${event.counts.vendors > 0 ? 
                                `<span class="badge bg-secondary detail-badge" title="Vendors" 
                                       data-event-id="${event.id}" data-detail-type="vendors" role="button">
                                    <i class="bi bi-shop me-1"></i>${event.counts.vendors}
                                </span>` : ''}
                            ${event.counts.company_labours > 0 ? 
                                `<span class="badge bg-primary detail-badge" title="Labours" 
                                       data-event-id="${event.id}" data-detail-type="labours" role="button">
                                    <i class="bi bi-people me-1"></i>${event.counts.company_labours}
                                </span>` : ''}
                            ${event.counts.beverages > 0 ? 
                                `<span class="badge bg-success detail-badge" title="Beverages"
                                       data-event-id="${event.id}" data-detail-type="beverages" role="button">
                                    <i class="bi bi-cup-hot me-1"></i>${event.counts.beverages}
                                </span>` : ''}
                            ${event.counts.work_progress_count > 0 ? 
                                `<span class="badge bg-info detail-badge" title="Work Progress"
                                       data-event-id="${event.id}" data-detail-type="work_progress" role="button">
                                    <i class="bi bi-graph-up me-1"></i>${event.counts.work_progress_count}
                                </span>` : ''}
                            ${event.counts.inventory_count > 0 ? 
                                `<span class="badge bg-warning detail-badge" title="Inventory"
                                       data-event-id="${event.id}" data-detail-type="inventory" role="button">
                                    <i class="bi bi-box-seam me-1"></i>${event.counts.inventory_count}
                                </span>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        
        content += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        return content;
    }
    
    // Helper function to get icon for event type
    function getEventTypeIcon(type) {
        switch (type) {
            case 'meeting': return 'bi-people-fill';
            case 'delivery': return 'bi-truck';
            case 'inspection': return 'bi-clipboard-check';
            case 'report': return 'bi-file-earmark-text';
            case 'issue': return 'bi-exclamation-triangle';
            default: return 'bi-calendar-event';
        }
    }
    
    // Helper function to get color for event type
    function getEventTypeColor(type) {
        switch (type) {
            case 'meeting': return 'primary';
            case 'delivery': return 'success';
            case 'inspection': return 'warning';
            case 'report': return 'info';
            case 'issue': return 'danger';
            default: return 'secondary';
        }
    }
    
    // Helper function to capitalize first letter
    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    
    // Function to create engineer details table
    function createEngineerDetailsTable(data) {
        // Check if data is in the new format with users without events
        const isUsersWithoutEvents = Array.isArray(data) && data.length > 0 && 'user_id' in data[0];
        
        if (isUsersWithoutEvents) {
            if (data.length === 0) {
                return `
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div>All supervisors have added their events. Great job!</div>
                    </div>
                `;
            }
            
            let table = `
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-x text-danger me-2"></i>
                            Supervisors Without Events
                        </h5>
                        <span class="badge bg-danger rounded-pill">${data.length}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Supervisor Name</th>
                                        <th scope="col">ID</th>
                                        <th scope="col">Last Activity</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;
            
            data.forEach((user, index) => {
                const lastActivity = user.updated_at ? new Date(user.updated_at).toLocaleString() : 'Never';
                
                table += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                                    <i class="bi bi-person text-danger"></i>
                                </div>
                                <div>${user.username || 'Unknown Supervisor'}</div>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark">${user.user_id}</span></td>
                        <td>${lastActivity}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="sendEventReminder(${user.user_id})">
                                <i class="bi bi-bell"></i> Send Reminder
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            table += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        return table;
    } else {
        // Original engineer table code for backward compatibility
        if (data.length === 0) {
            return `
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div>No engineers available for the selected site.</div>
                </div>
            `;
        }
        
        let table = `
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-gear-wide-connected text-success me-2"></i>
                        Site Engineers
                    </h5>
                    <span class="badge bg-success rounded-pill">${data.length}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Site</th>
                                    <th scope="col">Specialization</th>
                                    <th scope="col">Experience</th>
                                </tr>
                            </thead>
                            <tbody>
        `;
        
        data.forEach((engineer, index) => {
            table += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                                <i class="bi bi-person text-success"></i>
                            </div>
                            <div>${engineer.name}</div>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark">${engineer.site}</span></td>
                    <td>${engineer.specialization}</td>
                    <td>${engineer.experience}</td>
                </tr>
            `;
        });
        
        table += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        return table;
    }
}
    
    // Function to create supervisor details table with real data
    function createSupervisorDetailsTable(data) {
        if (data.length === 0) {
            return `
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    No supervisors available for the selected site.</div>
                `;
        }
        
        // Check if data is in the new format from the database
        const isRealData = data[0] && typeof data[0].username !== 'undefined';
        
        let table = `
            <div class="supervisor-details">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-check text-primary me-2"></i>
                        Site Supervisors
                    </h5>
                    <span class="badge bg-primary rounded-pill">${data.length}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                        <th scope="col">Designation</th>
                                        <th scope="col">Department</th>
                                        <th scope="col">Phone</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
        `;
        
        data.forEach((supervisor, index) => {
            if (isRealData) {
                // Format for real data from database
                const profilePic = supervisor.profile_picture ? 
                    `<img src="${supervisor.profile_picture}" class="rounded-circle" width="32" height="32" alt="${supervisor.username}" style="object-fit: cover;">` : 
                    `<div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                        <i class="bi bi-person text-primary"></i>
                    </div>`;
                
                const attendanceStatus = supervisor.attendance_status || 'Absent';
                const statusBadge = attendanceStatus === 'Present' ? 
                    '<span class="badge bg-success">Present</span>' : 
                    '<span class="badge bg-danger">Absent</span>';
                
                const punchInTime = supervisor.punch_in ? 
                    `<div class="small text-muted">In: ${new Date(supervisor.punch_in).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>` : '';
                
                table += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                ${profilePic}
                                <div class="ms-2">
                                    <div class="fw-semibold">${supervisor.username}</div>
                                    <div class="small text-muted">${supervisor.unique_id || ''}</div>
                                </div>
                            </div>
                        </td>
                        <td>${supervisor.designation || 'Site Supervisor'}</td>
                        <td>${supervisor.department || 'Construction'}</td>
                        <td>
                            ${supervisor.phone_number ? 
                                `<a href="tel:${supervisor.phone_number}" class="text-decoration-none contact-link">
                                    <i class="bi bi-telephone-fill text-success me-1"></i>
                                    ${supervisor.phone_number}
                                </a>` : '<span class="text-muted">Not available</span>'}
                        </td>
                        <td>
                            ${supervisor.email ? 
                                `<a href="mailto:${supervisor.email}" class="text-decoration-none contact-link">
                                    <i class="bi bi-envelope-fill text-primary me-1"></i>
                                    ${supervisor.email}
                                </a>` : '<span class="text-muted">Not available</span>'}
                        </td>
                        <td>
                            <div class="d-flex flex-column align-items-start">
                                ${statusBadge}
                                ${punchInTime}
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                // Format for sample data
            table += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                                <i class="bi bi-person text-primary"></i>
                            </div>
                            <div>${supervisor.name}</div>
                        </div>
                    </td>
                        <td>Site Supervisor</td>
                        <td>Construction</td>
                        <td>
                            <a href="#" class="text-decoration-none contact-link">
                                <i class="bi bi-telephone-fill text-success me-1"></i>
                                555-${1000 + index}
                            </a>
                        </td>
                        <td>
                            <a href="#" class="text-decoration-none contact-link">
                                <i class="bi bi-envelope-fill text-primary me-1"></i>
                                supervisor${index}@example.com
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-success">Present</span>
                    </td>
                </tr>
            `;
            }
        });
        
        table += `
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>
            </div>
        `;
        
        return table;
    }
    
    // Function to create and show modal
function showDetailsModal(title, content) {
        // Check if modal already exists, remove if it does
        const existingModal = document.getElementById('siteDetailsModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="siteDetailsModal" tabindex="-1" aria-labelledby="siteDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title" id="siteDetailsModalLabel">
                                <i class="bi bi-info-circle me-2 text-primary"></i>
                                ${title}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            ${content}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-1"></i>
                                Close
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Append modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add modal styling
        const modalElement = document.getElementById('siteDetailsModal');
        if (modalElement) {
            modalElement.style.backdropFilter = 'blur(5px)';
        }
        
        // Add custom styles for print
        const style = document.createElement('style');
        style.id = 'print-style';
        style.innerHTML = `
            @media print {
                body * {
                    visibility: hidden;
                }
                .modal-content, .modal-content * {
                    visibility: visible;
                }
                .modal {
                    position: absolute;
                    left: 0;
                    top: 0;
                    margin: 0;
                    padding: 0;
                    overflow: visible !important;
                }
                .modal-dialog {
                    max-width: 100%;
                    width: 100%;
                }
                .modal-footer, .btn-close {
                    display: none !important;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Show modal
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    
    // Manually trigger the shown.bs.modal event after a short delay
    // This ensures charts are initialized properly
    setTimeout(() => {
        modalElement.dispatchEvent(new Event('shown.bs.modal'));
    }, 300);
        
        // Add event listener to remove print style when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function () {
            const printStyle = document.getElementById('print-style');
            if (printStyle) {
                printStyle.remove();
            }
        });
    }

    // Initialize with 'all' sites data
    updateSiteOverview('all');

    // Add event listener for detail badges (vendors, labours, etc.)
    document.addEventListener('click', function(event) {
        // Check if the clicked element is a detail badge
        if (event.target.classList.contains('detail-badge') || 
            event.target.closest('.detail-badge')) {
            
            const badge = event.target.classList.contains('detail-badge') ? 
                event.target : event.target.closest('.detail-badge');
            
            const eventId = badge.getAttribute('data-event-id');
            const detailType = badge.getAttribute('data-detail-type');
            
            // Fetch and show details based on type
            if (detailType === 'vendors') {
                fetchVendorDetails(eventId);
            } else if (detailType === 'labours') {
                fetchLabourDetails(eventId);
            } else if (detailType === 'beverages') {
                fetchBeverageDetails(eventId);
            } else if (detailType === 'work_progress') {
                fetchWorkProgressDetails(eventId);
            } else if (detailType === 'inventory') {
                fetchInventoryDetails(eventId);
            }
        }
    });
});

// Function to fetch vendor details for a specific event
async function fetchVendorDetails(eventId) {
    try {
        // Show loading modal
        showDetailsLoadingModal('Vendor Details', 'Loading vendor information...');
        
        // Fetch vendor details from the server
        const response = await fetch(`backend/get_event_vendors.php?event_id=${eventId}`);
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // Update the modal with vendor details
            createVendorDetailsModal(data.vendors, data.event_title);
        } else {
            throw new Error(data.message || 'Failed to fetch vendor details');
        }
    } catch (error) {
        console.error('Error fetching vendor details:', error);
        // Show error in modal
        if (document.querySelector('#siteDetailsModal .modal-body')) {
            document.querySelector('#siteDetailsModal .modal-body').innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error loading vendor details: ${error.message}
                </div>
            `;
        } else {
            // If modal doesn't exist yet, create it with error message
            const errorContent = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error loading vendor details: ${error.message}
                </div>
            `;
            showDetailsModal('Error', errorContent);
        }
    }
}

// Function to fetch labour details for a specific event
async function fetchLabourDetails(eventId) {
    try {
        // Show loading modal
        showDetailsLoadingModal('Labour Details', 'Loading labour information...');
        
        // Fetch labour details from the server
        const response = await fetch(`backend/get_event_labours.php?event_id=${eventId}`);
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // Update the modal with labour details
            createLabourDetailsModal(data.labours, data.event_title);
        } else {
            throw new Error(data.message || 'Failed to fetch labour details');
        }
    } catch (error) {
        console.error('Error fetching labour details:', error);
        // Show error in modal
        if (document.querySelector('#siteDetailsModal .modal-body')) {
            document.querySelector('#siteDetailsModal .modal-body').innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error loading labour details: ${error.message}
                </div>
            `;
        } else {
            // If modal doesn't exist yet, create it with error message
            const errorContent = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error loading labour details: ${error.message}
                </div>
            `;
            showDetailsModal('Error', errorContent);
        }
    }
}

// Function to show loading modal while fetching details
function showDetailsLoadingModal(title, message) {
    // Create modal HTML
    const modalHTML = `
        <div class="text-center my-5">
            <div class="spinner-border" role="status"></div>
            <div class="mt-3">${message}</div>
        </div>
    `;
    
    // Create and show modal
    showDetailsModal(title, modalHTML);
}

// Function to create vendor details modal content
function createVendorDetailsModal(vendors, eventTitle) {
    if (!vendors || vendors.length === 0) {
        const modalBody = document.querySelector('#siteDetailsModal .modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    No vendor details available for this event.
                </div>
            `;
        } else {
            // If modal doesn't exist yet, create it with message
            const content = `
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    No vendor details available for this event.
                </div>
            `;
            showDetailsModal('Vendor Details', content);
        }
        return;
    }
    
    // Update modal title
    const modalTitle = document.querySelector('#siteDetailsModalLabel');
    if (modalTitle) {
        modalTitle.innerHTML = `
            <i class="bi bi-shop me-2 text-secondary"></i>
            Vendor Details: ${eventTitle}
            <span class="badge bg-secondary ms-2">${vendors.length} Vendors</span>
        `;
    }
    
    // Create content HTML
    let content = `
        <div class="vendor-details">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Vendor Name</th>
                                    <th scope="col">Contact Person</th>
                                    <th scope="col">Phone</th>
                                    <th scope="col">Materials</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
    `;
    
    vendors.forEach((vendor, index) => {
        // Determine status badge
        let statusBadge = '';
        switch(vendor.status?.toLowerCase() || 'pending') {
            case 'confirmed':
                statusBadge = '<span class="badge bg-success">Confirmed</span>';
                break;
            case 'pending':
                statusBadge = '<span class="badge bg-warning">Pending</span>';
                break;
            case 'cancelled':
                statusBadge = '<span class="badge bg-danger">Cancelled</span>';
                break;
            default:
                statusBadge = `<span class="badge bg-secondary">${vendor.status || 'Unknown'}</span>`;
        }
        
        content += `
            <tr>
                <td>${index + 1}</td>
                <td>
                    <div class="fw-semibold">${vendor.vendor_name || 'N/A'}</div>
                    <div class="small text-muted">${vendor.company_name || ''}</div>
                </td>
                <td>${vendor.contact_person || 'N/A'}</td>
                <td>
                    ${vendor.phone ? 
                        `<a href="tel:${vendor.phone}" class="text-decoration-none contact-link">
                            <i class="bi bi-telephone-fill text-success me-1"></i>
                            ${vendor.phone}
                        </a>` : 'N/A'}
                </td>
                <td>
                    <div class="materials-list">
                        ${vendor.materials || 'Not specified'}
                    </div>
                </td>
                <td>${statusBadge}</td>
            </tr>
        `;
    });
    
    content += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Update modal content
    document.querySelector('#siteDetailsModal .modal-body').innerHTML = content;
}

// Function to create labour details modal content
function createLabourDetailsModal(labours, eventTitle) {
    if (!labours || labours.length === 0) {
        const modalBody = document.querySelector('#siteDetailsModal .modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    No labour details available for this event.
                </div>
            `;
        } else {
            // If modal doesn't exist yet, create it with message
            const content = `
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    No labour details available for this event.
                </div>
            `;
            showDetailsModal('Labour Details', content);
        }
        return;
    }
    
    // Update modal title
    const modalTitle = document.querySelector('#siteDetailsModalLabel');
    if (modalTitle) {
        modalTitle.innerHTML = `
            <i class="bi bi-people me-2 text-primary"></i>
            Labour Details: ${eventTitle}
            <span class="badge bg-primary ms-2">${labours.length} Labours</span>
        `;
    }
    
    // Group labours by type (skilled, unskilled, etc.)
    const labourTypes = {};
    labours.forEach(labour => {
        const type = labour.labour_type || 'General';
        if (!labourTypes[type]) {
            labourTypes[type] = [];
        }
        labourTypes[type].push(labour);
    });
    
    // Create summary section
    let content = `
        <div class="labour-summary mb-4">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h3 class="text-primary mb-0">${labours.length}</h3>
                            <p class="text-muted mb-0">Total Labours</p>
                        </div>
    `;
    
    // Add counts for each labour type
    Object.entries(labourTypes).forEach(([type, typeLabours], index) => {
        if (index < 2) { // Only show first 2 types in summary to avoid overflow
            content += `
                <div class="col-md-4">
                    <h3 class="text-${index === 0 ? 'success' : 'info'} mb-0">${typeLabours.length}</h3>
                    <p class="text-muted mb-0">${type}</p>
                </div>
            `;
        }
    });
    
    content += `
                    </div>
                </div>
            </div>
        </div>
        
        <div class="labour-details">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col">Morning</th>
                                    <th scope="col">Evening</th>
                                </tr>
                            </thead>
                            <tbody>
    `;
    
    labours.forEach((labour, index) => {
        // Determine attendance badges
        const morningBadge = labour.morning_attendance ? 
            '<span class="badge bg-success">Present</span>' : 
            '<span class="badge bg-danger">Absent</span>';
            
        const eveningBadge = labour.evening_attendance ? 
            '<span class="badge bg-success">Present</span>' : 
            '<span class="badge bg-danger">Absent</span>';
        
        content += `
            <tr>
                <td>${index + 1}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                            <i class="bi bi-person text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">${labour.labour_name || 'N/A'}</div>
                            <div class="small text-muted">${labour.id_number || ''}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${labour.labour_type === 'Skilled' ? 'success' : 'info'}">
                        ${labour.labour_type || 'General'}
                    </span>
                </td>
                <td>
                    ${labour.contact_number ? 
                        `<a href="tel:${labour.contact_number}" class="text-decoration-none contact-link">
                            <i class="bi bi-telephone-fill text-success me-1"></i>
                            ${labour.contact_number}
                        </a>` : 'N/A'}
                </td>
                <td>${morningBadge}</td>
                <td>${eveningBadge}</td>
            </tr>
        `;
    });
    
    content += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Update modal content
    document.querySelector('#siteDetailsModal .modal-body').innerHTML = content;
}

// Function to fetch beverage details (placeholder)
async function fetchBeverageDetails(eventId) {
    showDetailsLoadingModal('Beverage Details', 'Loading beverage information...');
    
    // Placeholder for beverage details
    setTimeout(() => {
        const modalBody = document.querySelector('#siteDetailsModal .modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Beverage details feature is coming soon.
                </div>
            `;
        }
    }, 1000);
}

// Function to fetch work progress details (placeholder)
async function fetchWorkProgressDetails(eventId) {
    showDetailsLoadingModal('Work Progress', 'Loading work progress information...');
    
    // Placeholder for work progress details
    setTimeout(() => {
        const modalBody = document.querySelector('#siteDetailsModal .modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Work progress details feature is coming soon.
                </div>
            `;
        }
    }, 1000);
}

// Function to fetch inventory details (placeholder)
async function fetchInventoryDetails(eventId) {
    showDetailsLoadingModal('Inventory Details', 'Loading inventory information...');
    
    // Placeholder for inventory details
    setTimeout(() => {
        const modalBody = document.querySelector('#siteDetailsModal .modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Inventory details feature is coming soon.
                </div>
            `;
        }
    }, 1000);
}

// Helper function to calculate attendance statistics
function calculateAttendanceStats(companyLabours, vendorLabours) {
    // Combine all labours
    const allLabours = [...(companyLabours || []), ...(vendorLabours || [])];
    
    // Initialize counters
    let morningOnly = 0;
    let eveningOnly = 0;
    let fullDay = 0;
    
    // Count attendance types
    allLabours.forEach(labour => {
        const morning = labour.morning_attendance === 1 || labour.morning_attendance === true;
        const evening = labour.evening_attendance === 1 || labour.evening_attendance === true;
        
        if (morning && evening) {
            fullDay++;
        } else if (morning) {
            morningOnly++;
        } else if (evening) {
            eveningOnly++;
        }
    });
    
    return {
        morningOnly,
        eveningOnly,
        fullDay
    };
}

// Initialize labour distribution chart
function initializeLabourDistributionChart(chartId, companyLabours, vendorLabours) {
    const ctx = document.getElementById(chartId);
    if (!ctx) {
        console.error(`Chart element with ID ${chartId} not found`);
        return;
    }
    
    try {
        // Check if Chart is available
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            return;
        }
        
        // Check if a chart instance already exists
        const existingChart = Chart.getChart(ctx);
        if (existingChart) {
            existingChart.destroy();
        }
        
        // Create new chart
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Company Labours', 'Vendor Labours'],
                datasets: [{
                    data: [companyLabours, vendorLabours],
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',  // Primary
                        'rgba(14, 165, 233, 0.8)'   // Info
                    ],
                    borderColor: [
                        'rgba(99, 102, 241, 1)',
                        'rgba(14, 165, 233, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    } catch (error) {
        console.error('Error initializing labour distribution chart:', error);
    }
}

// Initialize attendance status chart
function initializeAttendanceStatusChart(chartId, morningOnly, eveningOnly, fullDay) {
    const ctx = document.getElementById(chartId);
    if (!ctx) {
        console.error(`Chart element with ID ${chartId} not found`);
        return;
    }
    
    try {
        // Check if Chart is available
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            return;
        }
        
        // Check if a chart instance already exists
        const existingChart = Chart.getChart(ctx);
        if (existingChart) {
            existingChart.destroy();
        }
        
        // Create new chart
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Morning Only', 'Evening Only', 'Full Day'],
                datasets: [{
                    label: 'Attendance Count',
                    data: [morningOnly, eveningOnly, fullDay],
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.8)',  // Warning
                        'rgba(239, 68, 68, 0.8)',   // Danger
                        'rgba(16, 185, 129, 0.8)'   // Success
                    ],
                    borderColor: [
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(16, 185, 129, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${value}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error initializing attendance status chart:', error);
    }
}

// Function to send reminder to user who hasn't added events
function sendEventReminder(userId) {
    if (!userId) {
        alert('Invalid user ID');
        return;
    }
    
    // Show loading indicator on the button
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
    
    // Send the reminder request
    fetch('backend/send_event_reminder.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-success');
            button.innerHTML = '<i class="bi bi-check"></i> Reminder Sent';
            
            // Reset button after 3 seconds
            setTimeout(() => {
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-primary');
                button.innerHTML = originalContent;
                button.disabled = false;
            }, 3000);
        } else {
            // Show error message
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="bi bi-x"></i> Failed';
            
            // Reset button after 3 seconds
            setTimeout(() => {
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-primary');
                button.innerHTML = originalContent;
                button.disabled = false;
            }, 3000);
            
            console.error('Error sending reminder:', data.message);
        }
    })
    .catch(error => {
        // Show error message
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-danger');
        button.innerHTML = '<i class="bi bi-x"></i> Failed';
        
        // Reset button after 3 seconds
        setTimeout(() => {
            button.classList.remove('btn-danger');
            button.classList.add('btn-outline-primary');
            button.innerHTML = originalContent;
            button.disabled = false;
        }, 3000);
        
        console.error('Error sending reminder:', error);
    });
} 