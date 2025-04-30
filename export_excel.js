// Function to export site details to Excel
function exportToExcel() {
    // Get the site name and date from the modal
    const siteName = document.getElementById('modalSiteName').textContent;
    const siteDate = document.getElementById('modalDate').textContent;
    
    if (!siteName || !siteDate) {
        alert('Site information not found. Please try again later.');
        return;
    }
    
    // Show loading state
    document.getElementById('siteDetailExportBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    
    // Create export URL with parameters
    const exportUrl = `export_to_excel.php?site_name=${encodeURIComponent(siteName)}&date=${encodeURIComponent(siteDate)}`;
    
    // Create a temporary link element and trigger the download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.target = '_blank';
    
    // Append the link, click it, then remove it
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button state after a delay
    setTimeout(function() {
        document.getElementById('siteDetailExportBtn').innerHTML = '<i class="fas fa-file-excel"></i> Export to Excel';
    }, 2000);
}

// Add the Export to Excel button dynamically when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Function to add the export button
    function addExportButton() {
        // Check if the site detail modal header is available
        const headerActions = document.querySelector('.site-detail-header-actions');
        if (!headerActions) return;
        
        // Check if the export button already exists
        if (document.getElementById('siteDetailExportBtn')) return;
        
        // Check if the edit button exists
        const editBtn = document.getElementById('siteDetailEditBtn');
        if (!editBtn) return;
        
        // Create the export button
        const exportBtn = document.createElement('button');
        exportBtn.type = 'button';
        exportBtn.className = 'btn btn-success site-detail-export-btn';
        exportBtn.id = 'siteDetailExportBtn';
        exportBtn.onclick = exportToExcel;
        exportBtn.innerHTML = '<i class="fas fa-file-excel"></i> Export to Excel';
        
        // Insert the export button after the edit button
        editBtn.insertAdjacentElement('afterend', exportBtn);
    }
    
    // Try adding the button immediately
    addExportButton();
    
    // Also set an interval to try adding the button when the modal is opened
    // This ensures the button is added even if the modal is loaded dynamically
    setInterval(addExportButton, 1000);
}); 