<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user has Purchase Manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Purchase Manager') {
    header("Location: unauthorized.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendors Management</title>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 40px;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 2.5em;
            font-weight: 300;
            color: #2a4365;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .page-header p {
            font-size: 1em;
            color: #718096;
            font-weight: 300;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .back-btn {
            padding: 10px 20px;
            background: #e2e8f0;
            color: #2a4365;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: #cbd5e0;
        }

        .add-vendor-btn {
            padding: 10px 20px;
            background: #2a4365;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .add-vendor-btn:hover {
            background: #1a365d;
            transform: translateY(-1px);
        }

        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 0.8em;
            color: #2a4365;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 0.9em;
            font-family: inherit;
            transition: border-color 0.2s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #2a4365;
            box-shadow: 0 0 0 2px rgba(42, 67, 101, 0.08);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-btn.apply {
            background: #2a4365;
            color: white;
        }

        .filter-btn.apply:hover {
            background: #1a365d;
        }

        .filter-btn.reset {
            background: #e2e8f0;
            color: #2a4365;
        }

        .filter-btn.reset:hover {
            background: #cbd5e0;
        }

        .vendors-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .vendors-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .vendors-header h2 {
            font-size: 1.3em;
            color: #2a4365;
            font-weight: 500;
        }

        .vendors-count {
            background: #e6f2ff;
            color: #2a4365;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .vendors-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
        }

        .vendors-table thead {
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .vendors-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2a4365;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vendors-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 0.9em;
        }

        .vendors-table tbody tr:hover {
            background-color: #f7fafc;
        }

        .vendor-code {
            font-weight: 600;
            color: #2a4365;
        }

        .vendor-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
        }

        .vendor-status.active {
            background-color: #c6f6d5;
            color: #22543d;
        }

        .vendor-status.inactive {
            background-color: #fed7d7;
            color: #742a2a;
        }

        .vendor-status.suspended {
            background-color: #feebc8;
            color: #7c2d12;
        }

        .vendor-status.archived {
            background-color: #cbd5e0;
            color: #2d3748;
        }

        .vendor-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .action-icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            border: none;
            background: white;
            color: #2a4365;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 0.95em;
        }

        .action-icon-btn:hover {
            transform: scale(1.1);
        }

        .action-icon-btn.view {
            color: #3182ce;
        }

        .action-icon-btn.view:hover {
            background: #ebf8ff;
        }

        .action-icon-btn.edit {
            color: #d69e2e;
        }

        .action-icon-btn.edit:hover {
            background: #fef5e7;
        }

        .action-icon-btn.delete {
            color: #e53e3e;
        }

        .action-icon-btn.delete:hover {
            background: #fff5f5;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 3em;
            color: #cbd5e0;
            margin-bottom: 15px;
            display: block;
        }

        .empty-state p {
            font-size: 1em;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px 20px;
        }

        .loading-spinner i {
            font-size: 2em;
            color: #2a4365;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .pagination-info {
            color: #718096;
            font-size: 0.9em;
            margin-right: 15px;
        }

        .pagination-btn {
            background: white;
            border: 1px solid #e2e8f0;
            color: #2a4365;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s ease;
            min-width: 35px;
            text-align: center;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #2a4365;
            color: white;
            border-color: #2a4365;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: #2a4365;
            color: white;
            border-color: #2a4365;
            font-weight: 600;
        }

        .vendor-details-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h2 {
            color: #2a4365;
            font-size: 1.4em;
            font-weight: 500;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5em;
            color: #718096;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close-btn:hover {
            color: #2a4365;
        }

        .modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8em;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .detail-value {
            font-size: 0.95em;
            color: #2a4365;
            font-weight: 500;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-btn.edit {
            background: #d69e2e;
            color: white;
        }

        .modal-btn.edit:hover {
            background: #b87a0b;
        }

        .modal-btn.close {
            background: #e2e8f0;
            color: #2a4365;
        }

        .modal-btn.close:hover {
            background: #cbd5e0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.8em;
            }

            .vendors-table {
                font-size: 0.85em;
            }

            .vendors-table th,
            .vendors-table td {
                padding: 10px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .modal-body {
                grid-template-columns: 1fr;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .back-btn,
            .add-vendor-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include the side panel -->
        <?php include 'includes/manager_panel.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div class="header-actions">
                    <button class="back-btn" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <h1>Vendors Management</h1>
                </div>
                <p>Manage and view all your vendors in one place</p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="searchVendor">Search Vendor</label>
                        <input type="text" id="searchVendor" placeholder="Vendor name or code...">
                    </div>
                    <div class="filter-group">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filterType">Vendor Type</label>
                        <select id="filterType">
                            <option value="">All Types</option>
                            <option value="supplier">Supplier</option>
                            <option value="contractor">Contractor</option>
                            <option value="service_provider">Service Provider</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="filter-btn apply" id="applyFilterBtn">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                    <button class="filter-btn reset" id="resetFilterBtn">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Vendors Section -->
            <div class="vendors-section">
                <div class="vendors-header">
                    <h2>All Vendors</h2>
                    <div class="vendors-count" id="vendorCount">0 vendors</div>
                </div>

                <div id="vendorsContainer">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner"></i>
                        <p>Loading vendors...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the Vendor Details Modal -->
    <?php include 'modals/vendor_details_modal.php'; ?>

    <!-- Include the Vendor Edit Modal -->
    <?php include 'modals/vendor_edit_modal.php'; ?>

    <script>
        let paginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: '',
            type: ''
        };

        // Load vendors on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadVendors(1, '', '', '');

            // Filter button listeners
            document.getElementById('applyFilterBtn').addEventListener('click', function() {
                const search = document.getElementById('searchVendor').value;
                const status = document.getElementById('filterStatus').value;
                const type = document.getElementById('filterType').value;
                loadVendors(1, search, status, type);
            });

            document.getElementById('resetFilterBtn').addEventListener('click', function() {
                document.getElementById('searchVendor').value = '';
                document.getElementById('filterStatus').value = '';
                document.getElementById('filterType').value = '';
                loadVendors(1, '', '', '');
            });

            // Enter key to search
            document.getElementById('searchVendor').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('applyFilterBtn').click();
                }
            });
        });

        function loadVendors(page, search, status, type) {
            paginationState.currentPage = page;
            paginationState.search = search;
            paginationState.status = status;
            paginationState.type = type;

            const offset = (page - 1) * paginationState.limit;
            const vendorsContainer = document.getElementById('vendorsContainer');

            vendorsContainer.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <p>Loading vendors...</p>
                </div>
            `;

            const params = new URLSearchParams({
                limit: paginationState.limit,
                offset: offset,
                search: search,
                status: status,
                type: type
            });

            fetch(`get_vendors.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        displayVendors(data.data, data.pagination);
                    } else if (data.success) {
                        vendorsContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-user-tie"></i>
                                <p>No vendors found matching your criteria.</p>
                            </div>
                        `;
                        document.getElementById('vendorCount').textContent = '0 vendors';
                    } else {
                        vendorsContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error loading vendors. Please try again.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    vendorsContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading vendors. Please try again.</p>
                        </div>
                    `;
                });
        }

        function displayVendors(vendors, pagination) {
            const vendorsContainer = document.getElementById('vendorsContainer');
            let html = '<table class="vendors-table"><thead><tr>';
            html += '<th>Vendor Code</th>';
            html += '<th>Name</th>';
            html += '<th>Email</th>';
            html += '<th>Phone</th>';
            html += '<th>Type</th>';
            html += '<th>Status</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';

            vendors.forEach(vendor => {
                const statusClass = vendor.vendor_status.toLowerCase();
                html += '<tr>';
                html += `<td class="vendor-code">${vendor.vendor_unique_code}</td>`;
                html += `<td>${vendor.vendor_full_name}</td>`;
                html += `<td><small>${vendor.vendor_email_address}</small></td>`;
                html += `<td>${vendor.vendor_phone_primary}</td>`;
                html += `<td><small>${vendor.vendor_type_category}</small></td>`;
                html += `<td><span class="vendor-status ${statusClass}">${vendor.vendor_status}</span></td>`;
                html += '<td class="vendor-actions">';
                html += `<button class="action-icon-btn view" title="View Details" onclick="viewVendor(${vendor.vendor_id})"><i class="fas fa-eye"></i></button>`;
                html += `<button class="action-icon-btn edit" title="Edit" onclick="editVendorDirect(${vendor.vendor_id})"><i class="fas fa-edit"></i></button>`;
                html += `<button class="action-icon-btn delete" title="Delete" onclick="deleteVendor(${vendor.vendor_id})"><i class="fas fa-trash"></i></button>`;
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            // Add pagination if needed
            if (pagination.totalPages > 1) {
                html += '<div class="pagination-container">';
                html += `<div class="pagination-info">Page ${pagination.currentPage} of ${pagination.totalPages} (Total: ${pagination.total} vendors)</div>`;

                if (paginationState.currentPage > 1) {
                    html += `<button class="pagination-btn" onclick="loadVendors(${paginationState.currentPage - 1}, '${paginationState.search}', '${paginationState.status}', '${paginationState.type}')">
                        <i class="fas fa-chevron-left"></i> Prev
                    </button>`;
                }

                for (let i = 1; i <= pagination.totalPages; i++) {
                    if (i >= paginationState.currentPage - 2 && i <= paginationState.currentPage + 2) {
                        if (i === paginationState.currentPage) {
                            html += `<button class="pagination-btn active">${i}</button>`;
                        } else {
                            html += `<button class="pagination-btn" onclick="loadVendors(${i}, '${paginationState.search}', '${paginationState.status}', '${paginationState.type}')">${i}</button>`;
                        }
                    }
                }

                if (paginationState.currentPage < pagination.totalPages) {
                    html += `<button class="pagination-btn" onclick="loadVendors(${paginationState.currentPage + 1}, '${paginationState.search}', '${paginationState.status}', '${paginationState.type}')">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>`;
                }

                html += '</div>';
            }

            vendorsContainer.innerHTML = html;
            document.getElementById('vendorCount').textContent = `${pagination.total} vendor${pagination.total !== 1 ? 's' : ''}`;
            paginationState.totalPages = pagination.totalPages;
        }

        function viewVendor(vendorId) {
            openVendorDetailsModal(vendorId);
        }

        function editVendorDirect(vendorId) {
            openVendorEditModal(vendorId);
        }

        function openVendorEditModal(vendorId) {
            // This function is defined in vendor_edit_modal.php
            // It will open the edit modal with the vendor data
            const vendorEditModalObj = window.openVendorEditModal;
            if (vendorEditModalObj) {
                vendorEditModalObj(vendorId);
            } else {
                console.error('Edit modal function not available');
            }
        }

        function deleteVendor(vendorId) {
            if (confirm('Are you sure you want to delete this vendor? This action cannot be undone.')) {
                fetch(`delete_vendor.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ vendor_id: vendorId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Vendor deleted successfully');
                        loadVendors(
                            paginationState.currentPage,
                            paginationState.search,
                            paginationState.status,
                            paginationState.type
                        );
                    } else {
                        alert('Error deleting vendor: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting vendor');
                });
            }
        }

    </script>
</body>
</html>
