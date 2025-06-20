/**
 * Attendance Visualizer JavaScript
 * This file handles the interactive functionality for the attendance visualizer page
 */

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    if (typeof $ !== 'undefined' && typeof $.fn.tooltip !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Handle the view mode toggle
    const viewModeSelect = document.getElementById('viewMode');
    if (viewModeSelect) {
        viewModeSelect.addEventListener('change', toggleDateFields);
    }

    // Highlight today's record in the table
    highlightTodayRecord();

    // Add event listeners to date inputs for range validation
    setupDateRangeValidation();

    // Setup export functionality
    setupExportButtons();

    // Set up detailed view modal triggers
    setupDetailViewTriggers();
    
    // Mobile menu toggle functionality
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const leftPanel = document.getElementById('leftPanel');
    
    if (mobileMenuToggle && leftPanel) {
        mobileMenuToggle.addEventListener('click', function() {
            leftPanel.classList.toggle('mobile-visible');
            // Change icon based on panel state
            const icon = this.querySelector('i');
            if (leftPanel.classList.contains('mobile-visible')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
        
        // Close panel when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isClickInsidePanel = leftPanel.contains(event.target);
            const isClickOnToggle = mobileMenuToggle.contains(event.target);
            
            if (!isClickInsidePanel && !isClickOnToggle && leftPanel.classList.contains('mobile-visible') && window.innerWidth <= 768) {
                leftPanel.classList.remove('mobile-visible');
                const icon = mobileMenuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }

    // Handle opening the photo modal
    $('#photoModal').on('show.bs.modal', function (event) {
        const link = $(event.relatedTarget);
        const photoUrl = link.data('photo');
        const photoDate = link.data('date');
        const photoTime = link.data('time');
        const photoType = link.data('type');
        const photoAddress = link.data('address'); // Get address data
        
        // Set photo source and show photo view
        $('#attendancePhoto').attr('src', photoUrl);
        $('#photoDate').text(photoDate);
        $('#photoTime').text(photoTime);
        $('#photoType').text(photoType === 'in' ? 'Punch In' : 'Punch Out');
        $('#photoAddress').text(photoAddress || 'Address not available'); // Display address
    });
    
    // Add custom tooltip for overtime explanation
    addOvertimeTooltips();
});

/**
 * Add tooltips to explain the overtime calculation rule
 */
function addOvertimeTooltips() {
    // Add tooltip to overtime column header
    const overtimeHeader = document.querySelector('th.hours-column:nth-of-type(8)');
    if (overtimeHeader) {
        overtimeHeader.setAttribute('data-toggle', 'tooltip');
        overtimeHeader.setAttribute('data-placement', 'top');
        overtimeHeader.setAttribute('title', 'Overtime is counted when working ≥ 1hr 30min beyond shift end time');
        
        // Initialize the tooltip
        if (typeof $ !== 'undefined' && typeof $.fn.tooltip !== 'undefined') {
            $(overtimeHeader).tooltip();
        }
    }
    
    // Add tooltip to overtime card
    const overtimeCard = document.querySelector('.dashboard-card.overview-card .icon-box.bg-info');
    if (overtimeCard) {
        overtimeCard.parentElement.setAttribute('data-toggle', 'tooltip');
        overtimeCard.parentElement.setAttribute('data-placement', 'top');
        overtimeCard.parentElement.setAttribute('title', 'Overtime is counted when working ≥ 1hr 30min beyond shift end time');
        
        // Initialize the tooltip
        if (typeof $ !== 'undefined' && typeof $.fn.tooltip !== 'undefined') {
            $(overtimeCard.parentElement).tooltip();
        }
    }
}

/**
 * Toggle the left panel sidebar visibility
 */
function togglePanel() {
    const leftPanel = document.getElementById('leftPanel');
    const mainContent = document.getElementById('mainContent');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (leftPanel && mainContent) {
        // Toggle the collapsed class
        leftPanel.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // Change the icon direction
        if (toggleIcon) {
            if (leftPanel.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }
    }
}

/**
 * Toggle between monthly and date range filter views
 */
function toggleDateFields() {
    const viewMode = document.getElementById('viewMode').value;
    
    if (viewMode === 'monthly') {
        document.getElementById('monthlyFilters').style.display = 'flex';
        document.getElementById('rangeFilters').style.display = 'none';
    } else {
        document.getElementById('monthlyFilters').style.display = 'none';
        document.getElementById('rangeFilters').style.display = 'flex';
    }
}

/**
 * Highlight today's record in the attendance table
 */
function highlightTodayRecord() {
    const today = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
    const tableRows = document.querySelectorAll('.table tbody tr');
    
    tableRows.forEach(row => {
        const dateCell = row.querySelector('td:first-child');
        if (dateCell) {
            const dateText = dateCell.textContent;
            if (dateText) {
                // Convert display date (e.g., "25 May 2023") back to YYYY-MM-DD
                const dateParts = dateText.split(' ');
                if (dateParts.length >= 3) {
                    const day = dateParts[0];
                    const month = getMonthNumber(dateParts[1]);
                    const year = dateParts[2];
                    
                    const rowDate = `${year}-${month}-${day.padStart(2, '0')}`;
                    
                    if (rowDate === today) {
                        row.classList.add('today');
                    }
                }
            }
        }
    });
}

/**
 * Helper to convert month name to number
 */
function getMonthNumber(monthName) {
    const months = {
        'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04', 'May': '05', 'Jun': '06',
        'Jul': '07', 'Aug': '08', 'Sep': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
    };
    
    return months[monthName] || '01';
}

/**
 * Set up validation for date range inputs
 */
function setupDateRangeValidation() {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            if (endDateInput.value && this.value > endDateInput.value) {
                alert('Start date cannot be after end date');
                this.value = endDateInput.value;
            }
        });
        
        endDateInput.addEventListener('change', function() {
            if (startDateInput.value && this.value < startDateInput.value) {
                alert('End date cannot be before start date');
                this.value = startDateInput.value;
            }
        });
    }
}

/**
 * Set up functionality for exporting attendance data
 */
function setupExportButtons() {
    // Check if export buttons exist
    const exportPdfBtn = document.getElementById('exportPdf');
    const exportCsvBtn = document.getElementById('exportCsv');
    
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', exportToPdf);
    }
    
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', exportToCsv);
    }
}

/**
 * Export attendance data to PDF
 */
function exportToPdf() {
    alert('PDF export functionality will be implemented here');
    // This would typically use a library like jsPDF or call a server endpoint
}

/**
 * Export attendance data to CSV
 */
function exportToCsv() {
    // Get table data
    const table = document.querySelector('.table');
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Extract header row
    const headers = [];
    const headerCells = rows[0].querySelectorAll('th');
    headerCells.forEach(cell => {
        headers.push(cell.textContent.trim());
    });
    csvContent += headers.join(',') + '\r\n';
    
    // Extract data rows
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const rowData = [];
        
        const cells = row.querySelectorAll('td');
        cells.forEach(cell => {
            // Clean up text (remove badges, icons, etc.)
            let text = cell.textContent.trim().replace(/\n/g, ' ');
            
            // Escape quotes and wrap in quotes if contains comma
            if (text.includes(',')) {
                text = `"${text.replace(/"/g, '""')}"`;
            }
            
            rowData.push(text);
        });
        
        csvContent += rowData.join(',') + '\r\n';
    }
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', 'attendance_data.csv');
    document.body.appendChild(link);
    
    // Trigger download and cleanup
    link.click();
    document.body.removeChild(link);
}

/**
 * Set up triggers for detailed attendance view
 */
function setupDetailViewTriggers() {
    const detailViewLinks = document.querySelectorAll('.detail-view-link');
    
    detailViewLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const recordId = this.getAttribute('data-record-id');
            if (recordId) {
                showDetailedView(recordId);
            }
        });
    });
}

/**
 * Show detailed view for a specific attendance record
 */
function showDetailedView(recordId) {
    // This would typically fetch data via AJAX and show in a modal
    console.log(`Showing detailed view for record ID: ${recordId}`);
    // For now, just show a placeholder alert
    alert(`Detailed view for record ID: ${recordId} will be implemented here`);
} 