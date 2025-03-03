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

        // Load documents when page loads
        document.addEventListener('DOMContentLoaded', loadHRDocuments);
    </script>
</body>
</html> 