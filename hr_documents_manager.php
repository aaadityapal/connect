<?php
session_start();
require_once 'config.php';

// Check authentication and HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Documents Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Root Variables */
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #343a40;
            --light: #f8f9fa;
            --border: #e9ecef;
            --text: #212529;
            --text-muted: #6c757d;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
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
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
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

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: #4361ee;
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #4361ee;
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout Link */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: black!important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background-color: #f5f8fa;
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .main-content .container {
            max-width: none;
            width: 100%;
            padding: 0;
            margin: 0;
        }

        /* Header Styles */
        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            width: 100%;
        }

        .header h1 {
            font-size: 1.5rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header h1 i {
            color: var(--primary-color);
        }

        .btn-add-doc {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-add-doc:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        /* Section Styles */
        .offer-letters-section {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            width: 100%;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 250px;
        }

        .filter-group label {
            color: var(--gray-600);
            font-weight: 500;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .filter-group select {
            min-width: 200px;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background-color: white;
            font-size: 0.875rem;
        }

        /* Table Styles */
        .offer-letter-table {
            width: 100%;
            margin-top: 1rem;
        }

        .offer-letter-table th,
        .offer-letter-table td {
            padding: 1rem;
            white-space: nowrap;
        }

        /* Form Styles */
        .document-upload-form {
            width: 100%;
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .form-control {
            width: 100%;
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .btn-add-doc {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .header,
            .offer-letters-section {
                padding: 1rem;
            }

            .document-upload-form {
                padding: 1rem;
            }
        }

        /* Table Wrapper for Horizontal Scroll */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Toggle Button */
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
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary);
            color: white;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        /* Your existing styles */
        :root {
            --primary-color: #4F46E5;
            --secondary-color: #7C3AED;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #111827;
            --light-color: #F3F4F6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #F9FAFB;
            color: var(--gray-800);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .no-data {
            text-align: center;
            padding: 2rem !important;
        }

        .no-data-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
        }

        .no-data-message i {
            font-size: 2rem;
            color: var(--gray-400);
        }

        .offer-letter-table td:last-child {
            min-width: 160px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="bi bi-people-fill"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-file-alt"></i> HR Documents Manager</h1>
            </div>

            <!-- Official Documents Section -->
            <div class="offer-letters-section">
                <div class="section-header">
                    <h2><i class="fas fa-file-contract"></i> Official Documents</h2>
                    <div class="header-actions">
                        <div class="filter-group">
                            <label for="userFilter">Filter by Employee:</label>
                            <select id="userFilter" class="form-control" onchange="filterDocuments()">
                                <option value="">All Employees</option>
                                <!-- Users will be loaded dynamically -->
                            </select>
                        </div>
                        <button class="btn-add-doc" onclick="toggleOfficialDocForm()">
                            <i class="fas fa-plus"></i> Add Official Document
                        </button>
                    </div>
                </div>

                <!-- Official Document Upload Form -->
                <div id="officialDocUploadForm" class="document-upload-form" style="display: none;">
                    <form id="officialUploadForm" onsubmit="handleOfficialDocUpload(event)">
                        <div class="form-group">
                            <label for="assignedUser">Assigned User</label>
                            <select id="assignedUser" name="user_id" class="form-control" required>
                                <option value="">Select User</option>
                                <!-- Users will be loaded dynamically -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="officialDocType">Document Type</label>
                            <select id="officialDocType" name="type" required>
                                <option value="">Select Document Type</option>
                                <option value="offer_letter">Offer Letter</option>
                                <option value="training_letter">Training Letter</option>
                                <option value="internship_letter">Internship Letter</option>
                                <option value="completion_letter">Completion Letter</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="officialDocFile">Document File</label>
                            <input type="file" id="officialDocFile" name="file" required>
                            <small class="text-muted">Maximum file size: 50MB</small>
                        </div>
                        <button type="submit" class="btn-add-doc">Upload Official Document</button>
                    </form>
                </div>

                <!-- Official Documents Table -->
                <table class="offer-letter-table">
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Type</th>
                            <th>Upload Date</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="officialDocumentsList">
                        <tr class="no-data">
                            <td colspan="6">
                                <div class="no-data-message">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No official documents available</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Users Personal Documents Section -->
            <div class="offer-letters-section">
                <div class="section-header">
                    <h2><i class="fas fa-user-shield"></i> Users Personal Documents</h2>
                    <div class="header-actions">
                        <div class="filter-group">
                            <label for="personalDocUserFilter">Filter by Employee:</label>
                            <select id="personalDocUserFilter" class="form-control" onchange="filterPersonalDocuments()">
                                <option value="">All Employees</option>
                                <!-- Users will be loaded dynamically -->
                            </select>
                        </div>
                        <button class="btn-add-doc" onclick="togglePersonalDocForm()">
                            <i class="fas fa-plus"></i> Add Personal Document
                        </button>
                    </div>
                </div>

                <!-- Personal Document Upload Form -->
                <div id="personalDocUploadForm" class="document-upload-form" style="display: none;">
                    <form id="personalUploadForm" onsubmit="handlePersonalDocUpload(event)">
                        <div class="form-group">
                            <label for="personalDocUser">Assigned User</label>
                            <select id="personalDocUser" name="user_id" class="form-control" required>
                                <option value="">Select User</option>
                                <!-- Users will be loaded dynamically -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="personalDocType">Document Type</label>
                            <select id="personalDocType" name="type" required>
                                <option value="">Select Document Type</option>
                                <!-- Identity Documents -->
                                <option value="aadhar_card">Aadhar Card</option>
                                <option value="pan_card">PAN Card</option>
                                <option value="voter_id">Voter ID</option>
                                <option value="passport">Passport</option>
                                <option value="driving_license">Driving License</option>
                                <option value="ration_card">Ration Card</option>

                                <!-- Educational Documents -->
                                <optgroup label="Educational Documents">
                                    <option value="tenth_certificate">10th Certificate (Secondary School)</option>
                                    <option value="twelfth_certificate">12th Certificate (Higher Secondary)</option>
                                    <option value="graduation_certificate">Graduation Certificate</option>
                                    <option value="post_graduation">Post Graduation Certificate</option>
                                    <option value="diploma_certificate">Diploma Certificate</option>
                                    <option value="other_education">Other Educational Certificate</option>
                                </optgroup>

                                <!-- Professional Documents -->
                                <optgroup label="Professional Documents">
                                    <option value="resume">Resume/CV</option>
                                    <option value="experience_certificate">Experience Certificate</option>
                                    <option value="relieving_letter">Relieving Letter</option>
                                    <option value="salary_slips">Salary Slips</option>
                                </optgroup>

                                <!-- Financial Documents -->
                                <optgroup label="Financial Documents">
                                    <option value="bank_passbook">Bank Passbook/Statement</option>
                                    <option value="cancelled_cheque">Cancelled Cheque</option>
                                    <option value="form_16">Form 16</option>
                                    <option value="pf_documents">PF Documents</option>
                                </optgroup>

                                <!-- Other Documents -->
                                <optgroup label="Other Documents">
                                    <option value="marriage_certificate">Marriage Certificate</option>
                                    <option value="caste_certificate">Caste Certificate</option>
                                    <option value="disability_certificate">Disability Certificate</option>
                                    <option value="other">Other</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="personalDocFile">Document File</label>
                            <input type="file" id="personalDocFile" name="file" required>
                            <small class="text-muted">Maximum file size: 50MB</small>
                        </div>
                        <button type="submit" class="btn-add-doc">Upload Personal Document</button>
                    </form>
                </div>

                <!-- Personal Documents Table -->
                <table class="offer-letter-table">
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Type</th>
                            <th>Upload Date</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="personalDocumentsList">
                        <tr class="no-data">
                            <td colspan="5">
                                <div class="no-data-message">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No personal documents available</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Policy Documents Section -->
            <div class="offer-letters-section">
                <div class="section-header">
                    <h2><i class="fas fa-book"></i> Policy Documents & Staff Requirements Forms</h2>
                    <div class="header-actions">
                        <div class="filter-group">
                            <label for="policyDocTypeFilter">Filter by Type:</label>
                            <select id="policyDocTypeFilter" class="form-control" onchange="filterPolicyDocuments()">
                                <option value="">All Policies</option>
                                <option value="company_policy">Company Policy</option>
                                <option value="hr_policy">HR Policy</option>
                                <option value="leave_policy">Leave Policy</option>
                                <option value="travel_policy">Travel Policy</option>
                                <option value="code_of_conduct">Code of Conduct</option>
                                <option value="leave_application_form">Leave Application Form</option>
                                <option value="travel_application_form">Travel Application Form</option>
                                <option value="overtime_application_form">Overtime Application Form</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <button class="btn-add-doc" onclick="togglePolicyDocForm()">
                            <i class="fas fa-plus"></i> Add Policy Document
                        </button>
                    </div>
                </div>

                <!-- Policy Document Upload Form -->
                <div id="policyDocUploadForm" class="document-upload-form" style="display: none;">
                    <form id="policyUploadForm" onsubmit="handlePolicyDocUpload(event)">
                        <div class="form-group">
                            <label for="policyDocType">Policy Type</label>
                            <select id="policyDocType" name="type" required>
                                <option value="">Select Policy Type</option>
                                <option value="company_policy">Company Policy</option>
                                <option value="hr_policy">HR Policy</option>
                                <option value="leave_policy">Leave Policy</option>
                                <option value="travel_policy">Travel Policy</option>
                                <option value="code_of_conduct">Code of Conduct</option>
                                <option value="leave_application_form">Leave Application Form</option>
                                <option value="travel_application_form">Travel Application Form</option>
                                <option value="overtime_application_form">Overtime Application Form</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="policyDocName">Policy Name</label>
                            <input type="text" id="policyDocName" name="policy_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="policyDocFile">Document File</label>
                            <input type="file" id="policyDocFile" name="file" required>
                            <small class="text-muted">Maximum file size: 50MB</small>
                        </div>
                        <button type="submit" class="btn-add-doc">Upload Policy Document</button>
                    </form>
                </div>

                <!-- Policy Documents Table -->
                <table class="offer-letter-table">
                    <thead>
                        <tr>
                            <th>Policy Name</th>
                            <th>Type</th>
                            <th>Upload Date</th>
                            <th>Last Updated</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="policyDocumentsList">
                        <tr class="no-data">
                            <td colspan="6">
                                <div class="no-data-message">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No policy documents available</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Add sidebar functionality
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
                icon.classList.remove('bi-chevron-left');
                icon.classList.add('bi-chevron-right');
            } else {
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-left');
            }
        });
        
        // Handle responsive behavior
        function checkWidth() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                sidebarToggle.classList.remove('collapsed');
            }
        }
        
        // Check on load
        checkWidth();
        
        // Check on resize
        window.addEventListener('resize', checkWidth);
        
        // Handle click outside on mobile
        document.addEventListener('click', function(e) {
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile && !sidebar.contains(e.target) && !sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }
        });

        function toggleUploadForm() {
            const form = document.getElementById('documentUploadForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function handleDocumentUpload(event) {
            event.preventDefault();
            
            const formData = new FormData();
            const docType = document.getElementById('docType').value;
            const file = document.getElementById('docFile').files[0];
            
            formData.append('action', 'add_hr_doc');
            formData.append('type', docType);
            formData.append('file', file);

            fetch('update_hr_documents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Document uploaded successfully', 'success');
                    document.getElementById('uploadForm').reset();
                    toggleUploadForm();
                    loadHRDocuments();
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            })
            .catch(error => {
                Swal.fire('Error', error.message, 'error');
            });
        }

        function loadHRDocuments() {
            fetch('get_hr_documents.php')
            .then(response => response.json())
            .then(data => {
                console.log('Response from server:', data);
                const documentsList = document.getElementById('documentsList');
                
                if (!data.documents || data.documents.length === 0) {
                    console.log('No documents found');
                    documentsList.innerHTML = `
                        <div class="no-documents">
                            <i class="fas fa-folder-open"></i>
                            <p>No documents available</p>
                        </div>`;
                    return;
                }

                documentsList.innerHTML = data.documents.map(doc => `
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas ${doc.icon_class}"></i>
                        </div>
                        <div class="document-info">
                            <h3 class="document-name">${doc.original_name}</h3>
                            <p class="document-type">${formatDocumentType(doc.type)}</p>
                            <p class="document-meta">
                                <span><i class="fas fa-clock"></i> ${doc.upload_date}</span>
                                <span><i class="fas fa-file-alt"></i> ${doc.formatted_size}</span>
                            </p>
                        </div>
                        <div class="document-actions">
                            <button onclick="viewDocument(${doc.id}, 'official')" class="btn-action view">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="downloadDocument(${doc.id}, 'official')" class="btn-action download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button onclick="deleteDocument(${doc.id}, 'official')" class="btn-action delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Detailed error:', error);
                Swal.fire('Error', 'Failed to load documents: ' + error.message, 'error');
            });
        }

        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            switch(ext) {
                case 'pdf': return 'fa-file-pdf';
                case 'doc':
                case 'docx': return 'fa-file-word';
                case 'xls':
                case 'xlsx': return 'fa-file-excel';
                case 'ppt':
                case 'pptx': return 'fa-file-powerpoint';
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'bmp': return 'fa-file-image';
                case 'zip':
                case 'rar':
                case '7z': return 'fa-file-archive';
                case 'txt': return 'fa-file-alt';
                case 'mp4':
                case 'avi':
                case 'mov': return 'fa-file-video';
                case 'mp3':
                case 'wav':
                case 'ogg': return 'fa-file-audio';
                default: return 'fa-file';
            }
        }

        function formatDocumentType(type) {
            return type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }

        function viewDocument(docId, docType) {
            window.open(`document_action_handler.php?action=view&id=${docId}&type=${docType}`, '_blank');
        }

        function downloadDocument(docId, docType) {
            window.location.href = `document_action_handler.php?action=download&id=${docId}&type=${docType}`;
        }

        function deleteDocument(docId, docType) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This document will be deleted",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('document_action_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete&id=${docId}&type=${docType}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted!', 'Document has been deleted.', 'success');
                            // Reload the appropriate document list
                            if (docType === 'official') {
                                loadOfficialDocuments();
                            } else {
                                loadPersonalDocuments();
                            }
                        } else {
                            throw new Error(data.message || 'Failed to delete document');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', error.message, 'error');
                    });
                }
            });
        }

        function toggleOfficialDocForm() {
            const form = document.getElementById('officialDocUploadForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function handleOfficialDocUpload(event) {
            event.preventDefault();
            
            const file = document.getElementById('officialDocFile').files[0];
            if (file && file.size > 52428800) { // 50MB in bytes
                Swal.fire('Error', 'File size exceeds 50MB limit', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', document.getElementById('officialDocType').value);
            formData.append('assigned_user_id', document.getElementById('assignedUser').value);
            formData.append('category', 'official');

            fetch('document_upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Document uploaded successfully', 'success');
                    document.getElementById('officialUploadForm').reset();
                    toggleOfficialDocForm();
                    loadOfficialDocuments();
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            })
            .catch(error => {
                Swal.fire('Error', error.message, 'error');
            });
        }

        function handlePersonalDocUpload(event) {
            event.preventDefault();
            
            const file = document.getElementById('personalDocFile').files[0];
            if (file && file.size > 52428800) { // 50MB in bytes
                Swal.fire('Error', 'File size exceeds 50MB limit', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', document.getElementById('personalDocType').value);
            formData.append('assigned_user_id', document.getElementById('personalDocUser').value);
            formData.append('category', 'personal');

            fetch('document_upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Document uploaded successfully', 'success');
                    document.getElementById('personalUploadForm').reset();
                    togglePersonalDocForm();
                    loadPersonalDocuments();
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            })
            .catch(error => {
                Swal.fire('Error', error.message, 'error');
            });
        }

        function loadUsers() {
            fetch('get_employees.php')
                .then(response => response.json())
                .then(data => {
                    const userSelect = document.getElementById('assignedUser');
                    if (data.success && data.employees && data.employees.length > 0) {
                        const options = data.employees.map(employee => 
                            `<option value="${employee.id}">${employee.username} - ${employee.designation}</option>`
                        ).join('');
                        userSelect.innerHTML = '<option value="">Select User</option>' + options;
                    } else {
                        userSelect.innerHTML = '<option value="">No employees available</option>';
                    }
                })
                .catch(error => {
                    console.error('Error loading employees:', error);
                    Swal.fire('Error', 'Failed to load employees', 'error');
                });
        }

        function loadUserFilter() {
            fetch('get_employees.php')
                .then(response => response.json())
                .then(data => {
                    const userFilter = document.getElementById('userFilter');
                    if (data.success && data.employees && data.employees.length > 0) {
                        const options = data.employees.map(employee => 
                            `<option value="${employee.id}">${employee.username} - ${employee.designation}</option>`
                        ).join('');
                        userFilter.innerHTML = '<option value="">All Employees</option>' + options;
                    }
                })
                .catch(error => {
                    console.error('Error loading employee filter:', error);
                });
        }

        function filterDocuments() {
            const selectedUserId = document.getElementById('userFilter').value;
            loadOfficialDocuments(selectedUserId);
        }

        function togglePersonalDocForm() {
            const form = document.getElementById('personalDocUploadForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function loadPersonalDocUserFilter() {
            fetch('get_employees.php')
                .then(response => response.json())
                .then(data => {
                    const userFilter = document.getElementById('personalDocUserFilter');
                    const personalDocUser = document.getElementById('personalDocUser');
                    if (data.success && data.employees && data.employees.length > 0) {
                        const options = data.employees.map(employee => 
                            `<option value="${employee.id}">${employee.username} - ${employee.designation}</option>`
                        ).join('');
                        userFilter.innerHTML = '<option value="">All Employees</option>' + options;
                        personalDocUser.innerHTML = '<option value="">Select User</option>' + options;
                    }
                })
                .catch(error => {
                    console.error('Error loading employee filter:', error);
                });
        }

        function filterPersonalDocuments() {
            const selectedUserId = document.getElementById('personalDocUserFilter').value;
            loadPersonalDocuments(selectedUserId);
        }

        function loadOfficialDocuments(userId = null) {
            const queryParams = userId ? `?type=official&user_id=${userId}` : '?type=official';

            fetch('document_retrieval_handler.php' + queryParams)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message);
                    }

                    const tbody = document.getElementById('officialDocumentsList');
                    
                    if (!data.documents || data.documents.length === 0) {
                        tbody.innerHTML = `
                            <tr class="no-data">
                                <td colspan="6">
                                    <div class="no-data-message">
                                        <i class="fas fa-folder-open"></i>
                                        <p>${userId ? 'No official documents found for selected employee' : 'No official documents available'}</p>
                                    </div>
                                </td>
                            </tr>`;
                        return;
                    }

                    tbody.innerHTML = data.documents.map(doc => `
                        <tr data-user-id="${doc.assigned_user_id}">
                            <td>
                                <div class="document-info">
                                    <i class="fas ${doc.icon_class} document-icon"></i>
                                    ${doc.document_name}
                                </div>
                            </td>
                            <td>${doc.document_type}</td>
                            <td>${doc.upload_date}</td>
                            <td>${doc.assigned_to} - ${doc.assigned_designation}</td>
                            <td>
                                <span class="status-badge status-${doc.status.toLowerCase()}">
                                    ${doc.status.charAt(0).toUpperCase() + doc.status.slice(1)}
                                </span>
                            </td>
                            <td>
                                <div class="document-actions">
                                    <button onclick="viewDocument(${doc.id}, 'official')" class="btn-action view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="downloadDocument(${doc.id}, 'official')" class="btn-action download" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    ${doc.status === 'pending' ? `
                                        <button onclick="updateDocumentStatus(${doc.id})" class="btn-action status" title="Update Status">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    ` : ''}
                                    <button onclick="deleteDocument(${doc.id}, 'official')" class="btn-action delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error loading official documents:', error);
                    Swal.fire('Error', error.message, 'error');
                });
        }

        function loadPersonalDocuments(userId = null) {
            const queryParams = userId ? `?type=personal&user_id=${userId}` : '?type=personal';

            fetch('document_retrieval_handler.php' + queryParams)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message);
                    }

                    const tbody = document.getElementById('personalDocumentsList');
                    
                    if (!data.documents || data.documents.length === 0) {
                        tbody.innerHTML = `
                            <tr class="no-data">
                                <td colspan="5">
                                    <div class="no-data-message">
                                        <i class="fas fa-folder-open"></i>
                                        <p>${userId ? 'No personal documents found for selected employee' : 'No personal documents available'}</p>
                                    </div>
                                </td>
                            </tr>`;
                        return;
                    }

                    tbody.innerHTML = data.documents.map(doc => `
                        <tr data-user-id="${doc.assigned_user_id}">
                            <td>
                                <div class="document-info">
                                    <i class="fas ${doc.icon_class} document-icon"></i>
                                    ${doc.document_name}
                                </div>
                            </td>
                            <td>${doc.document_type}</td>
                            <td>${doc.upload_date}</td>
                            <td>${doc.assigned_to} - ${doc.assigned_designation}</td>
                            <td>
                                <div class="document-actions">
                                    <button onclick="viewDocument(${doc.id}, 'personal')" class="btn-action view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="downloadDocument(${doc.id}, 'personal')" class="btn-action download" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button onclick="deleteDocument(${doc.id}, 'personal')" class="btn-action delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error loading personal documents:', error);
                    Swal.fire('Error', error.message, 'error');
                });
        }

        function updateDocumentStatus(docId) {
            Swal.fire({
                title: 'Update Document Status',
                html: `
                    <select id="statusUpdate" class="swal2-select">
                        <option value="accepted">Accept</option>
                        <option value="rejected">Reject</option>
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: 'Update',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const status = document.getElementById('statusUpdate').value;
                    return fetch('update_document_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            document_id: docId,
                            status: status,
                            type: 'official'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to update status');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Success', 'Document status updated successfully', 'success');
                    loadOfficialDocuments();
                }
            });
        }

        function togglePolicyDocForm() {
            const form = document.getElementById('policyDocUploadForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function handlePolicyDocUpload(event) {
            event.preventDefault();
            
            const file = document.getElementById('policyDocFile').files[0];
            if (file && file.size > 52428800) { // 50MB in bytes
                Swal.fire('Error', 'File size exceeds 50MB limit', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', document.getElementById('policyDocType').value);
            formData.append('policy_name', document.getElementById('policyDocName').value);
            formData.append('category', 'policy');

            fetch('policy_document_upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Policy document uploaded successfully', 'success');
                    document.getElementById('policyUploadForm').reset();
                    togglePolicyDocForm();
                    loadPolicyDocuments();
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            })
            .catch(error => {
                Swal.fire('Error', error.message, 'error');
            });
        }

        function filterPolicyDocuments() {
            const selectedType = document.getElementById('policyDocTypeFilter').value;
            loadPolicyDocuments(selectedType);
        }

        function loadPolicyDocuments(policyType = null) {
            const queryParams = policyType ? `?type=policy&policy_type=${policyType}` : '?type=policy';

            fetch('policy_document_retrieval_handler.php' + queryParams)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message);
                    }

                    const tbody = document.getElementById('policyDocumentsList');
                    
                    if (!data.documents || data.documents.length === 0) {
                        tbody.innerHTML = `
                            <tr class="no-data">
                                <td colspan="6">
                                    <div class="no-data-message">
                                        <i class="fas fa-folder-open"></i>
                                        <p>${policyType ? 'No policy documents found for selected type' : 'No policy documents available'}</p>
                                    </div>
                                </td>
                            </tr>`;
                        return;
                    }

                    tbody.innerHTML = data.documents.map(doc => `
                        <tr>
                            <td>
                                <div class="document-info">
                                    <i class="fas ${doc.icon_class} document-icon"></i>
                                    ${doc.policy_name}
                                </div>
                            </td>
                            <td>${formatDocumentType(doc.policy_type)}</td>
                            <td>${doc.upload_date}</td>
                            <td>${doc.last_updated || doc.upload_date}</td>
                            <td>
                                <span class="status-badge status-${doc.status.toLowerCase()}">
                                    ${doc.status.charAt(0).toUpperCase() + doc.status.slice(1)}
                                </span>
                            </td>
                            <td>
                                <div class="document-actions">
                                    <button onclick="viewDocument(${doc.id}, 'policy')" class="btn-action view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="downloadDocument(${doc.id}, 'policy')" class="btn-action download" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button onclick="editPolicyDocument(${doc.id})" class="btn-action edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    ${doc.status === 'pending' ? `
                                        <button onclick="updatePolicyStatus(${doc.id})" class="btn-action status" title="Update Status">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    ` : ''}
                                    <button onclick="deleteDocument(${doc.id}, 'policy')" class="btn-action delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error loading policy documents:', error);
                    Swal.fire('Error', error.message, 'error');
                });
        }

        function editPolicyDocument(docId) {
            // First fetch the document details
            fetch(`get_policy_document.php?id=${docId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message);
                    }

                    const doc = data.document;
                    
                    Swal.fire({
                        title: 'Edit Policy Document',
                        html: `
                            <div class="form-group" style="margin-bottom: 15px; text-align: left;">
                                <label for="editPolicyName" style="display: block; margin-bottom: 5px; font-weight: 500;">Policy Name</label>
                                <input type="text" id="editPolicyName" class="swal2-input" style="width: 100%;" value="${doc.policy_name}" placeholder="Policy Name">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px; text-align: left;">
                                <label for="editPolicyType" style="display: block; margin-bottom: 5px; font-weight: 500;">Policy Type</label>
                                <select id="editPolicyType" class="swal2-select" style="width: 100%;">
                                    <option value="company_policy" ${doc.policy_type === 'company_policy' ? 'selected' : ''}>Company Policy</option>
                                    <option value="hr_policy" ${doc.policy_type === 'hr_policy' ? 'selected' : ''}>HR Policy</option>
                                    <option value="leave_policy" ${doc.policy_type === 'leave_policy' ? 'selected' : ''}>Leave Policy</option>
                                    <option value="travel_policy" ${doc.policy_type === 'travel_policy' ? 'selected' : ''}>Travel Policy</option>
                                    <option value="code_of_conduct" ${doc.policy_type === 'code_of_conduct' ? 'selected' : ''}>Code of Conduct</option>
                                    <option value="other" ${doc.policy_type === 'other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Update',
                        preConfirm: () => {
                            const policyName = document.getElementById('editPolicyName').value;
                            const policyType = document.getElementById('editPolicyType').value;
                            
                            if (!policyName.trim()) {
                                Swal.showValidationMessage('Policy name is required');
                                return false;
                            }
                            
                            return { policyName, policyType };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const { policyName, policyType } = result.value;
                            
                            // Send update request
                            fetch('update_policy_document.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    id: docId,
                                    policy_name: policyName,
                                    policy_type: policyType
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('Updated!', 'Policy document has been updated.', 'success');
                                    loadPolicyDocuments();
                                } else {
                                    throw new Error(data.message || 'Failed to update policy document');
                                }
                            })
                            .catch(error => {
                                Swal.fire('Error', error.message, 'error');
                            });
                        }
                    });
                })
                .catch(error => {
                    Swal.fire('Error', error.message, 'error');
                });
        }

        function updatePolicyStatus(docId) {
            Swal.fire({
                title: 'Update Policy Status',
                html: `
                    <select id="policyStatusUpdate" class="swal2-select">
                        <option value="acknowledged">Acknowledged</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: 'Update',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const status = document.getElementById('policyStatusUpdate').value;
                    return fetch('update_policy_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            document_id: docId,
                            status: status
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to update status');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Success', 'Policy status updated successfully', 'success');
                    loadPolicyDocuments();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
            loadUserFilter();
            loadPersonalDocUserFilter();
            loadOfficialDocuments();
            loadPersonalDocuments();
            loadPolicyDocuments();
        });

        function uploadDocument(formData) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'document_upload_handler.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                resolve(result);
                            } else {
                                console.error('Upload Error:', result);
                                reject(new Error(result.message));
                            }
                        } catch (e) {
                            console.error('Parse Error:', e);
                            reject(new Error('Invalid server response'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax Error:', {xhr, status, error});
                        reject(new Error('Upload failed: ' + error));
                    }
                });
            });
        }

        // Form submit handler
        $(document).on('submit', '#documentForm', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                Swal.fire({
                    title: 'Uploading...',
                    text: 'Please wait while we upload your document',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const result = await uploadDocument(formData);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Document uploaded successfully'
                }).then(() => {
                    // Refresh document list or perform other actions
                    loadDocuments();
                });
            } catch (error) {
                console.error('Upload Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: error.message,
                    footer: 'Check console and server logs for more details'
                });
            }
        });

        function handleUploadError(error) {
            console.error('Upload Error Details:', {
                message: error.message,
                url: error.sourceURL,
                line: error.line,
                stack: error.stack
            });
            
            // Your existing error handling remains the same
            Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: 'An error occurred during upload. Check logs for details.'
            });
        }

        // Modify your existing upload code to include error logging
        $('#documentForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.ajax({
                url: 'document_upload_handler.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Upload Response:', response);
                    // Your existing success handling
                },
                error: function(xhr, status, error) {
                    console.error('Upload Failed:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    handleUploadError(error);
                }
            });
        });
    </script>
</body>
</html> 