<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recce Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --text-color: #2c3e50;
            --light-gray: #f5f6fa;
            --border-color: #dcdde1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f0f2f5;
            color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .recee-form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            padding: 1.5rem;
            color: white;
        }

        .form-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-header p {
            margin-top: 0.5rem;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .form-body {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .image-upload-container {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .image-upload-container:hover {
            border-color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }

        .upload-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .image-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        .preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            padding: 5px;
            cursor: pointer;
            color: var(--danger-color);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background-color: #e2e8f0;
            color: #64748b;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-secondary:hover {
            background-color: #cbd5e1;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .location-map {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid var(--border-color);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .required-field::after {
            content: '*';
            color: var(--danger-color);
            margin-left: 4px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                margin: 1rem auto;
            }

            .form-body {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .site-type-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .site-type-tabs input[type="radio"] {
            display: none;
        }

        .site-type-tabs label {
            padding: 8px 16px;
            background-color: var(--light-gray);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .site-type-tabs input[type="radio"]:checked + label {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .site-type-tabs label:hover {
            background-color: var(--border-color);
        }

        @media (max-width: 768px) {
            .site-type-tabs {
                gap: 8px;
            }
            
            .site-type-tabs label {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
        }

        .team-member-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            align-items: start;
        }

        .team-member-input {
            margin: 0;
        }

        .remove-member {
            padding: 0.75rem;
            height: 42px;
            width: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .add-team-member {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .team-member-row {
                grid-template-columns: 1fr;
            }
            
            .remove-member {
                justify-self: end;
            }
        }

        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M8 11.5l-5-5h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        select.form-control:focus {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%233498db' viewBox='0 0 16 16'%3E%3Cpath d='M8 11.5l-5-5h10l-5 5z'/%3E%3C/svg%3E");
        }

        .site-details-table table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .site-details-table td {
            padding: 1rem;
            border: 1px solid var(--border-color);
            vertical-align: top;
        }

        .site-details-table td:first-child {
            width: 50px;
            font-weight: 500;
        }

        .dimensions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .radio-group {
            display: flex;
            gap: 2rem;
            margin-top: 0.5rem;
        }

        .radio-group label {
            margin-left: 0.5rem;
        }

        .abutting-grid,
        .road-width-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .dimensions-grid,
            .abutting-grid,
            .road-width-grid {
                grid-template-columns: 1fr;
            }
        }

        .file-upload-section {
            margin-top: 1rem;
        }

        .upload-container {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-container:hover {
            border-color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }

        .upload-container i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .preview-item {
            position: relative;
            border-radius: 4px;
            overflow: hidden;
        }

        .preview-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .preview-item video {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .remove-file {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            padding: 4px;
            cursor: pointer;
            color: var(--danger-color);
            font-size: 12px;
        }

        .requirements-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            background: #fff;
            padding: 1rem;
        }

        @media (max-width: 768px) {
            .requirements-container {
                grid-template-columns: 1fr;
            }
        }

        .section-subtitle {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirement-row {
            display: grid;
            grid-template-columns: 30px 1fr;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .requirement-number {
            font-weight: 500;
            color: #666;
        }

        .add-btn {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #3498db;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 0.5rem;
            transition: all 0.2s ease;
        }

        .add-btn:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }

        .form-control {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 0.5rem;
            width: 100%;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
        }

        .input-with-remove {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .remove-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .input-with-remove:hover .remove-btn {
            opacity: 1;
        }

        .form-control {
            padding-right: 35px; /* Make space for the remove button */
        }

        .notes-section {
            background: #fff;
            padding: 1rem;
            border-radius: 4px;
        }

        .notes-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .note-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
            padding: 0.5rem 0;
        }

        .note-text {
            flex: 1;
            font-size: 0.95rem;
            color: #333;
        }

        .note-checkbox {
            display: flex;
            align-items: center;
            gap: 1rem;
            white-space: nowrap;
        }

        .note-checkbox input[type="radio"] {
            margin-right: 4px;
        }

        .note-checkbox label {
            margin-right: 1rem;
        }

        @media (max-width: 768px) {
            .note-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .note-checkbox {
                align-self: flex-start;
            }
        }

        .signatures-section {
            background: #fff;
            padding: 2rem;
            border-radius: 4px;
            margin-bottom: 2rem;
        }

        .signatures-grid {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            margin-top: 1rem;
        }

        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }

        .signature-row.single {
            grid-template-columns: 1fr;
        }

        .signature-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .signature-label {
            font-weight: 500;
            color: #333;
        }

        .signature-line {
            position: relative;
        }

        .signature-input {
            width: 100%;
            border: none;
            border-bottom: 1px solid #dee2e6;
            padding: 0.5rem 0;
            background: transparent;
            font-size: 1rem;
        }

        .signature-input:focus {
            outline: none;
            border-color: #3498db;
        }

        @media (max-width: 768px) {
            .signature-row {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .signatures-section {
                padding: 1rem;
            }
        }

        .site-toggle-container {
            margin: 20px auto;
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 240px;
            height: 36px;
            cursor: pointer;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: white;
            border-radius: 18px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
        }

        .slider:before {
            content: "";
            position: absolute;
            height: 34px;
            width: 120px;
            left: 1px;
            bottom: 0;
            background-color: #3498db;
            transition: 0.3s;
            border-radius: 17px;
            z-index: 1;
        }

        .barren, .constructed {
            flex: 1;
            text-align: center;
            color: #666;
            z-index: 2;
            transition: 0.3s;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 0;
        }

        input:checked + .slider:before {
            transform: translateX(118px);
        }

        input:checked + .slider .barren {
            color: #666;
        }

        input:checked + .slider .constructed {
            color: white;
        }

        .slider .barren {
            color: white;
        }

        .title-with-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .title-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .site-toggle-container {
            margin: 0;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 240px;
            height: 36px;
            cursor: pointer;
        }

        /* Barren Site Table */
        .barren-site-table {
            display: block;
        }

        /* Constructed Site Table */
        .constructed-site-table {
            display: none;
        }

        .constructed-site-table .site-details-table {
            /* Your existing table structure stays the same */
        }

        .constructed-site-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .constructed-site-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
        }
        
        .constructed-site-table input[type="text"] {
            width: 100%;
            padding: 4px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .constructed-site-table input[type="radio"] {
            margin-right: 4px;
        }

        .site-tables-container {
            width: 100%;
            margin-top: 1rem;
        }

        .barren-site-table {
            display: block;
        }

        .constructed-site-table {
            display: none;
        }

        .constructed-site-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .constructed-site-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
        }

        #barrenTable {
            display: block;
        }

        #constructedTable {
            display: none;
        }

        .site-tables-container {
            width: 100%;
            margin-top: 1rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .table td {
            border: 1px solid #dee2e6;
            padding: 8px;
        }

        .form-control {
            width: 100%;
            padding: 0.375rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="recee-form-container">
            <div class="form-header">
                <h1>Recce Details Form</h1>
                <p>Please fill in the details for the site recce</p>
            </div>

            <div class="form-body">
                <form id="receeForm" enctype="multipart/form-data">
                    <!-- Site Details Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <div class="title-with-toggle">
                                <div class="title-content">
                                    <i class="fas fa-map-marked-alt"></i>
                                    Site Details
                                </div>
                                <div class="site-toggle-container">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="siteToggle" name="site_toggle">
                                        <span class="slider">
                                            <span class="barren">Barren Site</span>
                                            <span class="constructed">Constructed Site</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </h2>

                        <!-- Add this right after your Site Details title -->
                        <div class="site-tables-container">
                            <!-- Barren Site Table -->
                            <div class="barren-site-table" id="barrenTable">
                                <!-- Your existing barren site table content -->
                            </div>

                            <!-- Constructed Site Table -->
                            <div class="constructed-site-table" id="constructedTable" style="display: none;">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <td>1.</td>
                                            <td colspan="7">All Rooms Dimension</td>
                                        </tr>
                                        <!-- Room rows -->
                                        <tr>
                                            <td>a</td>
                                            <td>Room 01</td>
                                            <td colspan="2"><input type="text" class="form-control"></td>
                                            <td>f</td>
                                            <td>Room 06</td>
                                            <td colspan="2"><input type="text" class="form-control"></td>
                                        </tr>
                                        <!-- ... rest of your constructed site table content ... -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Basic Information
                        </h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required-field">Client Name</label>
                                <input type="text" class="form-control" name="client_name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required-field">Site Location</label>
                                <input type="text" class="form-control" name="location" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required-field">Project Type</label>
                                <select class="form-control" name="site_category" required>
                                    <option value="">Select Project Type</option>
                                    <option value="architecture">Architecture</option>
                                    <option value="interior">Interior</option>
                                    <option value="construction">Construction</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label required-field">Date of Visit</label>
                                <input type="date" class="form-control" name="visit_date" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required-field">Phone Number</label>
                                <input type="tel" class="form-control" name="phone_number" pattern="[0-9]{10}" placeholder="Enter 10-digit number" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required-field">Email ID</label>
                                <input type="email" class="form-control" name="email" placeholder="Enter email address" required>
                            </div>
                            
                            <!-- Site Team Section -->
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Site Team Members</label>
                                <div id="teamMembersContainer">
                                    <div class="team-member-row">
                                        <input type="text" class="form-control team-member-input" name="team_members[]" placeholder="Enter team member name">
                                        <input type="tel" class="form-control team-member-input" name="team_phones[]" placeholder="Enter phone number">
                                        <button type="button" class="btn btn-secondary remove-member" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary add-team-member" style="margin-top: 10px;">
                                    <i class="fas fa-plus"></i> Add Team Member
                                </button>
                            </div>

                            <!-- After Site Team Members, add this table section -->
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Site Measurements & Details</label>
                                <div class="site-details-table">
                                    <table>
                                        <tr>
                                            <td>1.</td>
                                            <td>
                                                <div>a. Site Dimensions</div>
                                                <div class="dimensions-grid">
                                                    <input type="text" class="form-control" name="s1" placeholder="S1">
                                                    <input type="text" class="form-control" name="s2" placeholder="S2">
                                                    <input type="text" class="form-control" name="d1" placeholder="D1">
                                                    <input type="text" class="form-control" name="d2" placeholder="D2">
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td></td>
                                            <td>
                                                <div>b. Extra Dimension (if any):</div>
                                                <input type="text" class="form-control" name="extra_dimension">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>2.</td>
                                            <td>
                                                <div>North point</div>
                                                <div class="radio-group">
                                                    <input type="radio" id="north_yes" name="north_point" value="yes" onchange="toggleFileUpload('north')">
                                                    <label for="north_yes">YES</label>
                                                    <input type="radio" id="north_no" name="north_point" value="no" onchange="toggleFileUpload('north')">
                                                    <label for="north_no">NO</label>
                                                </div>
                                                <div id="north_upload" class="file-upload-section" style="display: none;">
                                                    <div class="upload-container" onclick="triggerFileInput('north_files')">
                                                        <i class="fas fa-compass"></i>
                                                        <p>Click to upload compass screenshot</p>
                                                        <input type="file" id="north_files" name="north_files[]" accept="image/*" style="display: none">
                                                    </div>
                                                    <div id="north_preview" class="file-preview"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>3.</td>
                                            <td>
                                                <div>Plumbing Existing Drain (Photos / Videos):</div>
                                                <div class="radio-group">
                                                    <input type="radio" id="plumbing_yes" name="plumbing" value="yes" onchange="toggleFileUpload('plumbing')">
                                                    <label for="plumbing_yes">YES</label>
                                                    <input type="radio" id="plumbing_no" name="plumbing" value="no" onchange="toggleFileUpload('plumbing')">
                                                    <label for="plumbing_no">NO</label>
                                                </div>
                                                <div id="plumbing_upload" class="file-upload-section" style="display: none;">
                                                    <div class="upload-container" onclick="triggerFileInput('plumbing_files')">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <p>Click to upload plumbing photos/videos</p>
                                                        <input type="file" id="plumbing_files" name="plumbing_files[]" multiple accept="image/*,video/*" style="display: none">
                                                    </div>
                                                    <div id="plumbing_preview" class="file-preview"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>4.</td>
                                            <td>
                                                <div>Electrical Lines / Pole (Photos / Videos):</div>
                                                <div class="radio-group">
                                                    <input type="radio" id="electrical_yes" name="electrical" value="yes" onchange="toggleFileUpload('electrical')">
                                                    <label for="electrical_yes">YES</label>
                                                    <input type="radio" id="electrical_no" name="electrical" value="no" onchange="toggleFileUpload('electrical')">
                                                    <label for="electrical_no">NO</label>
                                                </div>
                                                <div id="electrical_upload" class="file-upload-section" style="display: none;">
                                                    <div class="upload-container" onclick="triggerFileInput('electrical_files')">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <p>Click to upload electrical photos/videos</p>
                                                        <input type="file" id="electrical_files" name="electrical_files[]" multiple accept="image/*,video/*" style="display: none">
                                                    </div>
                                                    <div id="electrical_preview" class="file-preview"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>5.</td>
                                            <td>
                                                <div>Overall Site (Photos / Videos):</div>
                                                <div class="radio-group">
                                                    <input type="radio" id="overall_yes" name="overall_site" value="yes" onchange="toggleFileUpload('overall')">
                                                    <label for="overall_yes">YES</label>
                                                    <input type="radio" id="overall_no" name="overall_site" value="no" onchange="toggleFileUpload('overall')">
                                                    <label for="overall_no">NO</label>
                                                </div>
                                                <div id="overall_upload" class="file-upload-section" style="display: none;">
                                                    <div class="upload-container" onclick="triggerFileInput('overall_files')">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <p>Click to upload site photos/videos</p>
                                                        <input type="file" id="overall_files" name="overall_files[]" multiple accept="image/*,video/*" style="display: none">
                                                    </div>
                                                    <div id="overall_preview" class="file-preview"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>6.</td>
                                            <td>
                                                <div>Plot Abutting Areas</div>
                                                <div class="abutting-grid">
                                                    <div class="form-group">
                                                        <label>Front Side:</label>
                                                        <input type="text" class="form-control" name="front_side">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Rear Side:</label>
                                                        <input type="text" class="form-control" name="rear_side">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Side 01:</label>
                                                        <input type="text" class="form-control" name="side_01">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Side 02:</label>
                                                        <input type="text" class="form-control" name="side_02">
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>8.</td>
                                            <td>
                                                <div>Road Width</div>
                                                <div class="road-width-grid">
                                                    <div class="form-group">
                                                        <label>Front:</label>
                                                        <input type="text" class="form-control" name="road_width_front">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Rear:</label>
                                                        <input type="text" class="form-control" name="road_width_rear">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Side 01:</label>
                                                        <input type="text" class="form-control" name="road_width_side_01">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Side 02:</label>
                                                        <input type="text" class="form-control" name="road_width_side_02">
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Move this section right after the site measurements table -->
                    <div class="form-group" style="grid-column: 1 / -1; margin-top: 2rem;">
                        <div class="requirements-container">
                            <!-- Client Requirements Section -->
                            <div class="form-group">
                                <h3 class="section-subtitle">
                                    <i class="fas fa-list-ul" style="color: #3498db;"></i>
                                    Requirements by Client
                                </h3>
                                <div id="clientRequirementsContainer">
                                    <div class="requirement-row">
                                        <span class="requirement-number">1.</span>
                                        <div class="input-with-remove">
                                            <input type="text" class="form-control" name="client_requirements[]" placeholder="Enter client requirement">
                                            <button type="button" class="remove-btn" onclick="removeRow(this)" style="display: none;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-light add-btn" onclick="addRequirement('client')">
                                    <i class="fas fa-plus"></i> Add Requirement
                                </button>
                            </div>

                            <!-- Site Team Comments Section -->
                            <div class="form-group">
                                <h3 class="section-subtitle">
                                    <i class="fas fa-comments" style="color: #3498db;"></i>
                                    Site Team Comments
                                </h3>
                                <div id="teamCommentsContainer">
                                    <div class="requirement-row">
                                        <span class="requirement-number">1.</span>
                                        <div class="input-with-remove">
                                            <input type="text" class="form-control" name="team_comments[]" placeholder="Enter team comment">
                                            <button type="button" class="remove-btn" onclick="removeRow(this)" style="display: none;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-light add-btn" onclick="addRequirement('team')">
                                    <i class="fas fa-plus"></i> Add Comment
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Add this after the requirements-container section -->
                    <div class="form-group" style="grid-column: 1 / -1; margin-top: 2rem;">
                        <div class="notes-section">
                            <h3 class="section-subtitle">
                                <i class="fas fa-clipboard-list" style="color: #3498db;"></i>
                                Note:-
                            </h3>
                            <div class="notes-container">
                                <div class="note-item">
                                    <div class="note-text">
                                        Senior Architect must have a look on site over Video Call.
                                    </div>
                                    <div class="note-checkbox">
                                        <input type="radio" id="architect_yes" name="architect_video" value="yes">
                                        <label for="architect_yes">YES</label>
                                        <input type="radio" id="architect_no" name="architect_video" value="no">
                                        <label for="architect_no">NO</label>
                                    </div>
                                </div>

                                <div class="note-item">
                                    <div class="note-text">
                                        All Dimensions shall be in feet / Inches / Metric as per the guidance of Senior Architect.
                                    </div>
                                    <div class="note-checkbox">
                                        <input type="radio" id="dimensions_yes" name="dimensions_guidance" value="yes">
                                        <label for="dimensions_yes">YES</label>
                                        <input type="radio" id="dimensions_no" name="dimensions_guidance" value="no">
                                        <label for="dimensions_no">NO</label>
                                    </div>
                                </div>

                                <div class="note-item">
                                    <div class="note-text">
                                        Call should be arrange by the Site Team between client & Senior Architect for better understanding of the Site.
                                    </div>
                                    <div class="note-checkbox">
                                        <input type="radio" id="call_yes" name="team_call" value="yes">
                                        <label for="call_yes">YES</label>
                                        <input type="radio" id="call_no" name="team_call" value="no">
                                        <label for="call_no">NO</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Site Images Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-images"></i>
                            Site Images
                        </h2>
                        <div class="image-upload-container" onclick="document.getElementById('siteImages').click()">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <p>Click to upload site images</p>
                            <p class="text-muted">Supported formats: JPG, PNG (Max 5MB each)</p>
                            <input type="file" id="siteImages" name="site_images[]" multiple accept="image/*" style="display: none">
                        </div>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>

                    <!-- Additional Notes Section -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-clipboard"></i>
                            Additional Notes
                        </h2>
                        <div class="form-group">
                            <label class="form-label">Site Observations</label>
                            <textarea class="form-control" name="observations" placeholder="Enter any additional observations or notes about the site"></textarea>
                        </div>
                    </div>

                    <!-- Move this to be the last section before the closing form tag -->
                    <div class="form-group" style="grid-column: 1 / -1; margin-top: 2rem;">
                        <div class="signatures-section">
                            <h3 class="section-subtitle">
                                <i class="fas fa-signature" style="color: #3498db;"></i>
                                Signatures:-
                            </h3>
                            <div class="signatures-grid">
                                <div class="signature-row">
                                    <div class="signature-group">
                                        <label class="signature-label">Site Team:-</label>
                                        <div class="signature-line">
                                            <input type="text" class="signature-input" name="site_team_signature">
                                        </div>
                                    </div>
                                    <div class="signature-group">
                                        <label class="signature-label">Client:-</label>
                                        <div class="signature-line">
                                            <input type="text" class="signature-input" name="client_signature">
                                        </div>
                                    </div>
                                </div>
                                <div class="signature-row single">
                                    <div class="signature-group">
                                        <label class="signature-label">Senior Manager:</label>
                                        <div class="signature-line">
                                            <input type="text" class="signature-input" name="manager_signature">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Recce</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Image preview functionality
        document.getElementById('siteImages').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            [...e.target.files].forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <span class="remove-image" onclick="removeImage(this)">
                                <i class="fas fa-times"></i>
                            </span>
                        `;
                        preview.appendChild(div);
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        function removeImage(element) {
            element.parentElement.remove();
            // Note: You'll need to handle the actual file removal from the input
        }

        // Form submission
        document.getElementById('receeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Add your form submission logic here
            console.log('Form submitted');
        });

        // Team Members Functionality
        document.querySelector('.add-team-member').addEventListener('click', function() {
            const container = document.getElementById('teamMembersContainer');
            const newRow = document.createElement('div');
            newRow.className = 'team-member-row';
            newRow.innerHTML = `
                <input type="text" class="form-control team-member-input" name="team_members[]" placeholder="Enter team member name">
                <input type="tel" class="form-control team-member-input" name="team_phones[]" placeholder="Enter phone number">
                <button type="button" class="btn btn-secondary remove-member">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(newRow);
            
            // Show all remove buttons if there's more than one row
            const removeButtons = container.querySelectorAll('.remove-member');
            if (removeButtons.length > 1) {
                removeButtons.forEach(button => button.style.display = 'flex');
            }
        });

        // Event delegation for remove buttons
        document.getElementById('teamMembersContainer').addEventListener('click', function(e) {
            if (e.target.closest('.remove-member')) {
                const row = e.target.closest('.team-member-row');
                const container = row.parentElement;
                row.remove();
                
                // Hide remove button if only one row remains
                const removeButtons = container.querySelectorAll('.remove-member');
                if (removeButtons.length === 1) {
                    removeButtons[0].style.display = 'none';
                }
            }
        });

        function toggleFileUpload(section) {
            const uploadSection = document.getElementById(`${section}_upload`);
            const isYes = document.getElementById(`${section}_yes`).checked;
            uploadSection.style.display = isYes ? 'block' : 'none';
        }

        function triggerFileInput(inputId) {
            document.getElementById(inputId).click();
        }

        // Update the array of file inputs to include north_files
        ['north_files', 'plumbing_files', 'electrical_files', 'overall_files'].forEach(inputId => {
            document.getElementById(inputId).addEventListener('change', function(e) {
                const previewDiv = document.getElementById(`${this.id.replace('files', 'preview')}`);
                previewDiv.innerHTML = '';
                
                [...this.files].forEach(file => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="Preview">
                                <span class="remove-file" onclick="removeFile(this)">
                                    <i class="fas fa-times"></i>
                                </span>
                            `;
                        }
                        reader.readAsDataURL(file);
                    }
                    
                    previewDiv.appendChild(div);
                });
            });
        });

        function removeFile(element) {
            element.parentElement.remove();
            // Note: You'll need to handle the actual file removal from the input
        }

        function addRequirement(type) {
            const container = document.getElementById(type === 'client' ? 'clientRequirementsContainer' : 'teamCommentsContainer');
            const rows = container.getElementsByClassName('requirement-row');
            const newRow = document.createElement('div');
            newRow.className = 'requirement-row';
            
            newRow.innerHTML = `
                <span class="requirement-number">${rows.length + 1}.</span>
                <div class="input-with-remove">
                    <input type="text" class="form-control" name="${type === 'client' ? 'client_requirements' : 'team_comments'}[]" 
                        placeholder="Enter ${type === 'client' ? 'client requirement' : 'team comment'}">
                    <button type="button" class="remove-btn" onclick="removeRow(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(newRow);
            
            // Show all remove buttons if there's more than one row
            updateRemoveButtons(container);
        }

        function removeRow(button) {
            const row = button.closest('.requirement-row');
            const container = row.parentElement;
            row.remove();
            
            // Renumber the remaining rows
            const rows = container.getElementsByClassName('requirement-row');
            Array.from(rows).forEach((row, index) => {
                row.querySelector('.requirement-number').textContent = `${index + 1}.`;
            });
            
            // Update remove buttons visibility
            updateRemoveButtons(container);
        }

        function updateRemoveButtons(container) {
            const removeButtons = container.querySelectorAll('.remove-btn');
            const shouldShow = removeButtons.length > 1;
            removeButtons.forEach(button => {
                button.style.display = shouldShow ? 'flex' : 'none';
            });
        }

        // Add event listeners to the site type radio buttons
        document.querySelectorAll('input[name="site_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const barrenTable = document.querySelector('.barren-site-table');
                const constructedTable = document.querySelector('.constructed-site-table');
                
                if (this.value === 'barren') {
                    if (barrenTable) barrenTable.style.display = 'block';
                    if (constructedTable) constructedTable.style.display = 'none';
                } else if (this.value === 'constructed') {
                    if (barrenTable) barrenTable.style.display = 'none';
                    if (constructedTable) constructedTable.style.display = 'block';
                }
            });
        });

        // Trigger the change event on page load if a radio button is already selected
        window.addEventListener('load', function() {
            const selectedSiteType = document.querySelector('input[name="site_type"]:checked');
            if (selectedSiteType) {
                selectedSiteType.dispatchEvent(new Event('change'));
            }
        });

        // Update the toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const siteToggle = document.getElementById('siteToggle');
            const barrenTable = document.querySelector('.barren-site-table');
            const constructedTable = document.querySelector('.constructed-site-table');

            if (siteToggle && barrenTable && constructedTable) {
                siteToggle.addEventListener('change', function() {
                    if (this.checked) {
                        // When Constructed Site is selected
                        barrenTable.style.display = 'none';
                        constructedTable.style.display = 'block';
                    } else {
                        // When Barren Site is selected
                        barrenTable.style.display = 'block';
                        constructedTable.style.display = 'none';
                    }
                });
            } else {
                console.error('One or more elements not found:', {
                    toggle: !!siteToggle,
                    barrenTable: !!barrenTable,
                    constructedTable: !!constructedTable
                });
            }
        });
    </script>
</body>
</html> 