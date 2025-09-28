<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check user role for access control
$allowed_roles = ['HR', 'admin', 'Senior Manager (Studio)', 'Senior Manager (Site)', 'Site Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: unauthorized.php');
    exit();
}

$pageTitle = "Excel Import";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Import - <?php echo $_SESSION['role']; ?> Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #7C3AED;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --bg-light: #F3F4F6;
            --bg-white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --border-radius: 16px;
            --spacing-sm: 12px;
            --spacing-md: 18px;
            --spacing-lg: 24px;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            font-family: 'Inter', sans-serif;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px);
        }

        .nav-link {
            color: var(--text-light);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(79, 70, 229, 0.1);
        }

        .nav-link.active {
            background-color: rgba(79, 70, 229, 0.1);
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: var(--primary-color);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: white !important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .toggle-sidebar:hover {
            transform: translateY(-50%) scale(1.1);
        }

        .toggle-sidebar i {
            font-size: 0.75rem;
            color: var(--primary-color);
        }

        .toggle-sidebar.collapsed {
            left: 16px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: none;
            margin-bottom: var(--spacing-lg);
        }

        .card-header {
            background: var(--bg-white);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-dark);
        }

        .card-body {
            padding: var(--spacing-lg);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .alert {
            border-radius: 0.5rem;
        }

        .table-responsive {
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
        }

        .table tbody tr:hover {
            background-color: rgba(79, 70, 229, 0.05);
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.collapsed {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .toggle-sidebar.collapsed {
                left: calc(var(--sidebar-width) - 16px);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Toggle Button -->
    <div class="toggle-sidebar" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="header">
            <h1><i class="fas fa-file-excel me-2"></i>Excel Import</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Import Excel Data</h5>
            </div>
            <div class="card-body">
                <form id="excelImportForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Select Excel File</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xls,.xlsx">
                        <div class="form-text">Upload .xls or .xlsx files only</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload and Process</button>
                </form>
                
                <div id="loadingIndicator" class="mt-3" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Processing...</span>
                    </div>
                    <span class="ms-2">Processing Excel file...</span>
                </div>
                
                <div id="resultContainer" class="mt-4" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Imported Data</h5>
                        <button id="saveToDatabaseBtn" class="btn btn-success" style="display: none;">
                            <i class="fas fa-save me-2"></i>Save to Database
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="dataTable">
                            <thead>
                                <tr id="tableHeader">
                                    <!-- Headers will be populated by JavaScript -->
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="errorMessage" class="alert alert-danger mt-3" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Change icon direction
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
            
            // Excel import form handling
            document.getElementById('excelImportForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const fileInput = document.getElementById('excelFile');
                const file = fileInput.files[0];
                
                if (!file) {
                    showError('Please select an Excel file to upload.');
                    return;
                }
                
                // Check file extension
                const fileName = file.name.toLowerCase();
                if (!fileName.endsWith('.xls') && !fileName.endsWith('.xlsx')) {
                    showError('Please upload a valid Excel file (.xls or .xlsx)');
                    return;
                }
                
                // Show loading indicator
                document.getElementById('loadingIndicator').style.display = 'block';
                document.getElementById('resultContainer').style.display = 'none';
                document.getElementById('errorMessage').style.display = 'none';
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    try {
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, {type: 'array'});
                        
                        // Get the first worksheet
                        const firstSheetName = workbook.SheetNames[0];
                        const worksheet = workbook.Sheets[firstSheetName];
                        
                        // Convert to JSON
                        const jsonData = XLSX.utils.sheet_to_json(worksheet, {header: 1});
                        
                        if (jsonData.length === 0) {
                            showError('The Excel file is empty.');
                            document.getElementById('loadingIndicator').style.display = 'none';
                            return;
                        }
                        
                        // Display data in table
                        displayDataInTable(jsonData);
                        
                        // Hide loading indicator and show results
                        document.getElementById('loadingIndicator').style.display = 'none';
                        document.getElementById('resultContainer').style.display = 'block';
                        
                        // Store data for saving
                        window.importedData = jsonData;
                        
                        // Check if save functionality is available
                        checkSaveAvailability();
                    } catch (error) {
                        console.error('Error processing Excel file:', error);
                        showError('Error processing Excel file: ' + error.message);
                        document.getElementById('loadingIndicator').style.display = 'none';
                    }
                };
                
                reader.onerror = function() {
                    showError('Error reading the file.');
                    document.getElementById('loadingIndicator').style.display = 'none';
                };
                
                reader.readAsArrayBuffer(file);
            });
            
            // Save to database functionality
            document.getElementById('saveToDatabaseBtn').addEventListener('click', function() {
                if (!window.importedData) {
                    showError('No data to save.');
                    return;
                }
                
                // Show loading indicator
                document.getElementById('loadingIndicator').style.display = 'block';
                document.getElementById('errorMessage').style.display = 'none';
                
                // Send data to server
                fetch('save_imported_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ rows: window.importedData })
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    
                    if (data.success) {
                        alert('Data saved successfully! ' + data.rows_imported + ' rows imported.');
                    } else {
                        showError('Error saving data: ' + data.message);
                    }
                })
                .catch(error => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    showError('Error saving data: ' + error.message);
                });
            });
        });

        function displayDataInTable(data) {
            const tableHeader = document.getElementById('tableHeader');
            const tableBody = document.getElementById('tableBody');
            
            // Clear existing content
            tableHeader.innerHTML = '';
            tableBody.innerHTML = '';
            
            if (data.length === 0) return;
            
            // Create headers
            const headers = data[0];
            headers.forEach(header => {
                const th = document.createElement('th');
                th.textContent = header || 'Column ' + (Array.from(th.parentNode.children).length + 1);
                tableHeader.appendChild(th);
            });
            
            // Create rows
            for (let i = 1; i < data.length; i++) {
                const row = document.createElement('tr');
                const rowData = data[i];
                
                // Ensure rowData has the same length as headers
                for (let j = 0; j < headers.length; j++) {
                    const cell = document.createElement('td');
                    cell.textContent = rowData[j] || '';
                    row.appendChild(cell);
                }
                
                tableBody.appendChild(row);
            }
            
            // Show save button
            document.getElementById('saveToDatabaseBtn').style.display = 'block';
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }
        
        function checkSaveAvailability() {
            fetch('check_imported_data_table.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.table_exists) {
                        document.getElementById('saveToDatabaseBtn').style.display = 'block';
                    } else {
                        document.getElementById('saveToDatabaseBtn').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error checking save availability:', error);
                    document.getElementById('saveToDatabaseBtn').style.display = 'none';
                });
        }
    </script>
</body>
</html>