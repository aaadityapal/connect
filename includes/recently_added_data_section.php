<!-- Recently Added Data Section -->
<div class="recently-added-section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-clock me-2"></i>
            Recently Added Data
        </h3>
    </div>
    
    <div class="data-tabs-container">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs data-nav-tabs" id="dataTabsNav" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="vendor-tab" data-bs-toggle="tab" data-bs-target="#vendor-pane" 
                        type="button" role="tab" aria-controls="vendor-pane" aria-selected="true">
                    <i class="fas fa-building me-2"></i>
                    Vendors
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="labour-tab" data-bs-toggle="tab" data-bs-target="#labour-pane" 
                        type="button" role="tab" aria-controls="labour-pane" aria-selected="false">
                    <i class="fas fa-users me-2"></i>
                    Labours
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="entry-tab" data-bs-toggle="tab" data-bs-target="#entry-pane" 
                        type="button" role="tab" aria-controls="entry-pane" aria-selected="false">
                    <i class="fas fa-plus-circle me-2"></i>
                    Recent Entries
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-pane" 
                        type="button" role="tab" aria-controls="reports-pane" aria-selected="false">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content data-tab-content" id="dataTabsContent">
            <!-- Vendors Tab -->
            <div class="tab-pane fade show active" id="vendor-pane" role="tabpanel" aria-labelledby="vendor-tab">
                <div class="data-content">
                    <div class="data-header">
                        <h5 class="data-title">Recently Added Vendors</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshVendorData()">
                            <i class="fas fa-sync-alt me-1"></i>
                            Refresh
                        </button>
                    </div>
                    <div class="data-list" id="vendorDataList">
                        <!-- Sample Vendor Data -->
                        <div class="data-item">
                            <div class="item-icon vendor-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">ABC Construction Supplies</h6>
                                <p class="item-details">Cement Supplier • Added 2 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewVendor(1)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editVendor(1)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon vendor-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">XYZ Steel Works</h6>
                                <p class="item-details">Steel Supplier • Added 5 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewVendor(2)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editVendor(2)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon vendor-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Modern Tiles & Ceramics</h6>
                                <p class="item-details">Tile Supplier • Added 1 day ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewVendor(3)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editVendor(3)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="data-footer">
                        <a href="#" class="view-all-link" onclick="viewAllVendors()">
                            View All Vendors <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Labours Tab -->
            <div class="tab-pane fade" id="labour-pane" role="tabpanel" aria-labelledby="labour-tab">
                <div class="data-content">
                    <div class="data-header">
                        <h5 class="data-title">Recently Added Labours</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshLabourData()">
                            <i class="fas fa-sync-alt me-1"></i>
                            Refresh
                        </button>
                    </div>
                    <div class="data-list" id="labourDataList">
                        <!-- Sample Labour Data -->
                        <div class="data-item">
                            <div class="item-icon labour-icon">
                                <i class="fas fa-hard-hat"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Rajesh Kumar</h6>
                                <p class="item-details">Mason • Permanent Labour • Added 1 hour ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewLabour(1)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editLabour(1)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon labour-icon">
                                <i class="fas fa-hard-hat"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Suresh Patel</h6>
                                <p class="item-details">Electrician • Chowk Labour • Added 3 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewLabour(2)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editLabour(2)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon labour-icon">
                                <i class="fas fa-hard-hat"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Amit Singh</h6>
                                <p class="item-details">Carpenter • Vendor Labour • Added 6 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewLabour(3)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editLabour(3)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="data-footer">
                        <a href="#" class="view-all-link" onclick="viewAllLabours()">
                            View All Labours <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Entries Tab -->
            <div class="tab-pane fade" id="entry-pane" role="tabpanel" aria-labelledby="entry-tab">
                <div class="data-content">
                    <div class="data-header">
                        <h5 class="data-title">Recent Payment Entries</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshEntryData()">
                            <i class="fas fa-sync-alt me-1"></i>
                            Refresh
                        </button>
                    </div>
                    <div class="data-list" id="entryDataList">
                        <!-- Sample Entry Data -->
                        <div class="data-item">
                            <div class="item-icon entry-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Payment #PE-001</h6>
                                <p class="item-details">₹15,000 • Salary Payment • Added 30 mins ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewEntry(27)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editEntry(27)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon entry-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Payment #PE-002</h6>
                                <p class="item-details">₹8,500 • Vendor Payment • Added 2 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewEntry(26)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editEntry(26)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon entry-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Payment #PE-003</h6>
                                <p class="item-details">₹25,000 • Material Purchase • Added 4 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewEntry(25)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editEntry(25)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="data-footer">
                        <a href="#" class="view-all-link" onclick="viewAllEntries()">
                            View All Entries <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Reports Tab -->
            <div class="tab-pane fade" id="reports-pane" role="tabpanel" aria-labelledby="reports-tab">
                <div class="data-content">
                    <div class="data-header">
                        <h5 class="data-title">Recent Reports</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshReportData()">
                            <i class="fas fa-sync-alt me-1"></i>
                            Refresh
                        </button>
                    </div>
                    <div class="data-list" id="reportDataList">
                        <!-- Sample Report Data -->
                        <div class="data-item">
                            <div class="item-icon report-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Monthly Payment Report</h6>
                                <p class="item-details">November 2024 • Generated 1 hour ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewReport(1)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="downloadReport(1)">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon report-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Vendor Summary Report</h6>
                                <p class="item-details">Q4 2024 • Generated 3 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewReport(2)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="downloadReport(2)">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-item">
                            <div class="item-icon report-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="item-info">
                                <h6 class="item-name">Labour Attendance Report</h6>
                                <p class="item-details">Week 46 • Generated 5 hours ago</p>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewReport(3)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="downloadReport(3)">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="data-footer">
                        <a href="#" class="view-all-link" onclick="viewAllReports()">
                            View All Reports <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Recently Added Data Section Styles */
.recently-added-section {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.recently-added-section .section-header {
    padding: 1.5rem 2rem 1rem;
    border-bottom: 1px solid #f3f4f6;
}

.recently-added-section .section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0;
    display: flex;
    align-items: center;
}

.recently-added-section .section-title i {
    color: #6b7280;
    margin-right: 0.5rem;
}

.data-tabs-container {
    padding: 0;
}

/* Custom Tab Styles */
.data-nav-tabs {
    border-bottom: 1px solid #e5e7eb;
    padding: 0 2rem;
    background-color: #fafbfc;
    margin: 0;
}

.data-nav-tabs .nav-item {
    margin-bottom: -1px;
}

.data-nav-tabs .nav-link {
    background: none;
    border: none;
    color: #6b7280;
    font-weight: 500;
    padding: 1rem 1.5rem;
    border-radius: 0;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}

.data-nav-tabs .nav-link:hover {
    color: #374151;
    background-color: #f9fafb;
    border-color: transparent;
}

.data-nav-tabs .nav-link.active {
    color: #2563eb;
    background-color: #ffffff;
    border-bottom: 2px solid #2563eb;
}

.data-nav-tabs .nav-link i {
    font-size: 0.875rem;
}

/* Tab Content */
.data-tab-content {
    padding: 0;
}

.data-content {
    padding: 1.5rem 2rem 2rem;
}

.data-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.data-title {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}

/* Data List */
.data-list {
    space-y: 1rem;
}

.data-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    background-color: #fafbfc;
    border: 1px solid #f3f4f6;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
}

.data-item:hover {
    background-color: #f9fafb;
    border-color: #e5e7eb;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.item-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.125rem;
}

.vendor-icon {
    background-color: #dbeafe;
    color: #2563eb;
}

.labour-icon {
    background-color: #dcfce7;
    color: #16a34a;
}

.entry-icon {
    background-color: #fef3c7;
    color: #d97706;
}

.report-icon {
    background-color: #f3e8ff;
    color: #9333ea;
}

.item-info {
    flex: 1;
}

.item-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.25rem 0;
}

.item-details {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0;
}

.item-actions {
    display: flex;
    gap: 0.5rem;
}

.item-actions .btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
    border-radius: 6px;
}

/* Data Footer */
.data-footer {
    padding-top: 1rem;
    border-top: 1px solid #f3f4f6;
    text-align: center;
}

.view-all-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    transition: color 0.2s ease;
}

.view-all-link:hover {
    color: #1d4ed8;
    text-decoration: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .data-nav-tabs {
        padding: 0 1rem;
    }
    
    .data-nav-tabs .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }
    
    .data-content {
        padding: 1rem 1.5rem 1.5rem;
    }
    
    .data-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .data-item {
        padding: 0.75rem;
    }
    
    .item-icon {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }
    
    .item-name {
        font-size: 0.85rem;
    }
    
    .item-details {
        font-size: 0.75rem;
    }
}
</style>

<script>
// Recently Added Data Functions
function refreshVendorData() {
    console.log('Refreshing vendor data...');
    // Here you would typically make an AJAX call to refresh the data
    alert('Vendor data refreshed!');
}

function refreshLabourData() {
    console.log('Refreshing labour data...');
    alert('Labour data refreshed!');
}

function refreshEntryData() {
    console.log('Refreshing entry data...');
    alert('Entry data refreshed!');
}

function refreshReportData() {
    console.log('Refreshing report data...');
    alert('Report data refreshed!');
}

// View Functions
function viewVendor(id) {
    console.log('Viewing vendor:', id);
    alert(`Viewing vendor details for ID: ${id}`);
}

function editVendor(id) {
    console.log('Editing vendor:', id);
    alert(`Editing vendor for ID: ${id}`);
}

function viewLabour(id) {
    console.log('Viewing labour:', id);
    alert(`Viewing labour details for ID: ${id}`);
}

function editLabour(id) {
    console.log('Editing labour:', id);
    alert(`Editing labour for ID: ${id}`);
}

function viewEntry(id) {
    console.log('Viewing entry:', id);
    alert(`Viewing entry details for ID: ${id}`);
}

function editEntry(id) {
    console.log('Editing entry:', id);
    alert(`Editing entry for ID: ${id}`);
}

function viewReport(id) {
    console.log('Viewing report:', id);
    alert(`Viewing report for ID: ${id}`);
}

function downloadReport(id) {
    console.log('Downloading report:', id);
    alert(`Downloading report for ID: ${id}`);
}

// View All Functions
function viewAllVendors() {
    console.log('Viewing all vendors');
    alert('Redirecting to all vendors page...');
}

function viewAllLabours() {
    console.log('Viewing all labours');
    alert('Redirecting to all labours page...');
}

function viewAllEntries() {
    console.log('Viewing all entries');
    alert('Redirecting to all entries page...');
}

function viewAllReports() {
    console.log('Viewing all reports');
    alert('Redirecting to all reports page...');
}
</script>