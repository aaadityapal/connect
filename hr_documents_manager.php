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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #4F46E5;
            --secondary-color: #4338CA;
            --success-color: #10B981;
            --danger-color: #EF4444;
            --warning-color: #F59E0B;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-600: #4B5563;
            --gray-800: #1F2937;
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

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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

        .document-upload-form {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .text-muted {
            color: var(--gray-600);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .document-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .document-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .document-type {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .document-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .document-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .document-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .btn-action {
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-action.view:hover {
            background: var(--gray-100);
            color: var(--primary-color);
        }

        .btn-action.download:hover {
            background: var(--gray-100);
            color: var(--success-color);
        }

        .btn-action.delete:hover {
            background: var(--gray-100);
            color: var(--danger-color);
        }

        .no-documents {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            color: var(--gray-600);
        }

        .no-documents i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-300);
        }

        .offer-letters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .offer-letter-table {
            width: 100%;
            border-collapse: collapse;
        }

        .offer-letter-table th,
        .offer-letter-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .offer-letter-table th {
            background-color: var(--gray-100);
            font-weight: 500;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: var(--warning-color);
            color: white;
        }

        .status-accepted {
            background-color: var(--success-color);
            color: white;
        }

        .status-rejected {
            background-color: var(--danger-color);
            color: white;
        }

        .employee-info {
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 8px;
        }

        .employee-info h3 {
            margin-bottom: 1rem;
            color: var(--gray-800);
            font-size: 1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-item label {
            font-size: 0.75rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .info-item span {
            font-size: 0.875rem;
            color: var(--gray-800);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
                gap: 0.5rem;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group select {
                width: 100%;
            }

            .btn-add-doc {
                width: 100%;
                justify-content: center;
            }
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-alt"></i> HR Documents Manager</h1>
            <button class="btn-add-doc" onclick="toggleUploadForm()">
                <i class="fas fa-plus"></i> Add HR Document
            </button>
        </div>

        <!-- Document Upload Form -->
        <div id="documentUploadForm" class="document-upload-form" style="display: none;">
            <form id="uploadForm" onsubmit="handleDocumentUpload(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="docType">Document Type</label>
                        <select id="docType" name="type" required>
                            <option value="">Select Document Type</option>
                            <option value="hr_policy">HR Policy</option>
                            <option value="senior_manager_handbook">Senior Manager HandBook</option>
                            <option value="travel_expenses_reimbursement_form">Travel Expenses Reimbursement Form</option>
                            <option value="employee_handbook">Employee Handbook</option>
                            <option value="code_of_conduct">Code of Conduct</option>
                            <option value="safety_guidelines">Safety Guidelines</option>
                            <option value="training_material">Training Material</option>
                            <option value="benefits_policy">Benefits Policy</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="docFile">Document File</label>
                        <input type="file" id="docFile" name="file" required accept=".pdf,.doc,.docx">
                    </div>
                </div>
                <button type="submit" class="btn-upload">Upload Document</button>
            </form>
        </div>

        <!-- Documents List -->
        <div id="documentsList" class="documents-grid">
            <!-- Documents will be loaded here -->
        </div>

        <div class="offer-letters-section">
            <div class="section-header">
                <h2><i class="fas fa-file-signature"></i> Employee Offer Letters</h2>
                <div class="header-actions">
                    <div class="filter-group">
                        <label for="userFilter">Filter by:</label>
                        <select id="userFilter" class="form-control" onchange="filterOfferLetters()">
                            <option value="">All Employees</option>
                            <!-- Will be populated with employees -->
                        </select>
                    </div>
                    <button class="btn-add-doc" onclick="toggleOfferLetterForm()">
                        <i class="fas fa-plus"></i> Add Offer Letter
                    </button>
                </div>
            </div>

            <!-- Offer Letter Upload Form -->
            <div id="offerLetterUploadForm" class="document-upload-form" style="display: none;">
                <form id="offerLetterForm" onsubmit="handleOfferLetterUpload(event)">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="employeeSelect">Select Employee</label>
                            <select id="employeeSelect" name="user_id" required class="form-control">
                                <option value="">Select Employee</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="offerLetterFile">Offer Letter (PDF)</label>
                            <input type="file" id="offerLetterFile" name="file" required accept=".pdf" class="form-control">
                        </div>
                    </div>
                    <div id="selectedEmployeeInfo" class="employee-info" style="display: none;">
                        <h3>Employee Details</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Role:</label>
                                <span id="empRole"></span>
                            </div>
                            <div class="info-item">
                                <label>Designation:</label>
                                <span id="empDesignation"></span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span id="empEmail"></span>
                            </div>
                            <div class="info-item">
                                <label>Phone:</label>
                                <span id="empPhone"></span>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-add-doc">Upload Offer Letter</button>
                </form>
            </div>

            <!-- Offer Letters Table -->
            <table class="offer-letter-table">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Role</th>
                        <th>Designation</th>
                        <th>Upload Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="offerLettersTableBody">
                    <!-- Will be populated via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
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
                            <button onclick="viewDocument(${doc.id})" class="btn-action view">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="downloadDocument(${doc.id})" class="btn-action download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button onclick="deleteHRDocument(${doc.id})" class="btn-action delete">
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
                default: return 'fa-file';
            }
        }

        function formatDocumentType(type) {
            return type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }

        function viewDocument(docId) {
            window.open(`hr_document_handler.php?action=view&id=${docId}`, '_blank');
        }

        function downloadDocument(docId) {
            window.location.href = `hr_document_handler.php?action=download&id=${docId}`;
        }

        function deleteHRDocument(docId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This document will be permanently deleted",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', docId);
                    formData.append('action', 'delete_hr_doc');

                    fetch('update_hr_documents.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadHRDocuments();
                            Swal.fire(
                                'Deleted!',
                                'Document has been deleted.',
                                'success'
                            );
                        } else {
                            throw new Error(data.message || 'Failed to delete document');
                        }
                    })
                    .catch(error => {
                        Swal.fire(
                            'Error',
                            error.message || 'Failed to delete document',
                            'error'
                        );
                    });
                }
            });
        }

        function toggleOfferLetterForm() {
            const form = document.getElementById('offerLetterUploadForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                loadEmployees();
            }
        }

        function loadEmployees() {
            fetch('get_employees.php')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('employeeSelect');
                select.innerHTML = '<option value="">Select Employee</option>';
                data.employees.forEach(employee => {
                    select.innerHTML += `<option value="${employee.id}" 
                        data-role="${employee.role}"
                        data-designation="${employee.designation}"
                        data-email="${employee.email}"
                        data-phone="${employee.phone}"
                    >${employee.username}</option>`;
                });

                // Add change event listener
                select.addEventListener('change', updateEmployeeInfo);
            })
            .catch(error => {
                Swal.fire('Error', 'Failed to load employees', 'error');
            });
        }

        function updateEmployeeInfo() {
            const select = document.getElementById('employeeSelect');
            const infoDiv = document.getElementById('selectedEmployeeInfo');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                document.getElementById('empRole').textContent = option.dataset.role;
                document.getElementById('empDesignation').textContent = option.dataset.designation;
                document.getElementById('empEmail').textContent = option.dataset.email;
                document.getElementById('empPhone').textContent = option.dataset.phone;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }

        function handleOfferLetterUpload(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'add_offer_letter');

            fetch('update_offer_letters.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Offer letter uploaded successfully', 'success');
                    event.target.reset();
                    toggleOfferLetterForm();
                    loadOfferLetters();
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            })
            .catch(error => {
                Swal.fire('Error', error.message, 'error');
            });
        }

        function populateUserFilter() {
            fetch('get_employees.php')
            .then(response => response.json())
            .then(data => {
                const filterSelect = document.getElementById('userFilter');
                filterSelect.innerHTML = '<option value="">All Employees</option>';
                data.employees.forEach(employee => {
                    filterSelect.innerHTML += `
                        <option value="${employee.id}">${employee.username}</option>
                    `;
                });
            })
            .catch(error => {
                console.error('Error loading employees for filter:', error);
            });
        }

        function filterOfferLetters() {
            const selectedUserId = document.getElementById('userFilter').value;
            
            fetch(`get_offer_letters.php${selectedUserId ? '?user_id=' + selectedUserId : ''}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('offerLettersTableBody');
                if (!data.offerLetters || data.offerLetters.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="no-data">
                                <div class="no-data-message">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No offer letters found</p>
                                </div>
                            </td>
                        </tr>`;
                    return;
                }

                tbody.innerHTML = data.offerLetters.map(letter => `
                    <tr>
                        <td>${letter.employee_name}</td>
                        <td>${letter.role}</td>
                        <td>${letter.designation}</td>
                        <td>${letter.upload_date}</td>
                        <td><span class="status-badge status-${letter.status}">${letter.status}</span></td>
                        <td>
                            <button onclick="viewOfferLetter(${letter.id})" class="btn-action view" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="downloadOfferLetter(${letter.id})" class="btn-action download" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button onclick="deleteOfferLetter(${letter.id})" class="btn-action delete" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to load offer letters', 'error');
            });
        }

        function loadOfferLetters() {
            filterOfferLetters();
        }

        function viewOfferLetter(id) {
            window.open(`offer_letter_handler.php?action=view&id=${id}`, '_blank');
        }

        function downloadOfferLetter(id) {
            window.location.href = `offer_letter_handler.php?action=download&id=${id}`;
        }

        function deleteOfferLetter(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This offer letter will be permanently deleted",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_offer_letters.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'delete_offer_letter',
                            id: id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadOfferLetters();
                            Swal.fire('Deleted!', 'Offer letter has been deleted.', 'success');
                        } else {
                            throw new Error(data.message || 'Failed to delete offer letter');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', error.message, 'error');
                    });
                }
            });
        }

        // Load documents when page loads
        document.addEventListener('DOMContentLoaded', () => {
            loadHRDocuments();
            populateUserFilter();
            loadOfferLetters();
        });
    </script>
</body>
</html> 