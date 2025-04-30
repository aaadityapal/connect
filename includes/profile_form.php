<form id="personalInfoForm" onsubmit="return updatePersonalInfo(event)" enctype="multipart/form-data">
    <!-- Profile Picture Section -->
    <div class="form-section">
        <h2 class="section-title">Profile Picture</h2>
        <div class="profile-picture-container">
            <div class="profile-picture-wrapper">
                <div class="current-picture">
                    <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/images/default-avatar.png'; ?>" 
                         alt="Profile Picture" 
                         id="profilePreview">
                    <div class="picture-overlay">
                        <label for="profile_picture" class="change-picture-btn">
                            <i class="fas fa-camera"></i>
                            <span>Change Picture</span>
                        </label>
                    </div>
                </div>
                <input type="file" 
                       id="profile_picture" 
                       name="profile_picture" 
                       accept="image/*" 
                       onchange="previewImage(this)" 
                       style="display: none;">
            </div>
            <div class="picture-info">
                <p class="file-requirements">
                    <i class="fas fa-info-circle"></i>
                    Maximum file size: 5MB<br>
                    Supported formats: JPG, PNG
                </p>
            </div>
        </div>
    </div>

    <!-- Basic Information -->
    <div class="form-section">
        <h2 class="section-title">Basic Information</h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="username">Username*</label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address*</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" class="form-control">
                    <option value="">Select Gender</option>
                    <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" class="form-control" 
                       value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- Bio Section -->
    <div class="form-section">
        <h2 class="section-title">About Me</h2>
        <div class="form-group">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
        </div>
    </div>

    <!-- Personal Details -->
    <div class="form-section">
        <h2 class="section-title">Personal Details</h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="marital_status">Marital Status</label>
                <select id="marital_status" name="marital_status" class="form-control">
                    <option value="">Select Status</option>
                    <option value="single" <?php echo ($user['marital_status'] ?? '') === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="married" <?php echo ($user['marital_status'] ?? '') === 'married' ? 'selected' : ''; ?>>Married</option>
                    <option value="divorced" <?php echo ($user['marital_status'] ?? '') === 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                    <option value="widowed" <?php echo ($user['marital_status'] ?? '') === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="blood_group">Blood Group</label>
                <select id="blood_group" name="blood_group" class="form-control">
                    <option value="">Select Blood Group</option>
                    <?php
                    $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                    foreach ($blood_groups as $bg) {
                        $selected = ($user['blood_group'] ?? '') === $bg ? 'selected' : '';
                        echo "<option value=\"$bg\" $selected>$bg</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" 
                       value="<?php echo htmlspecialchars($user['nationality'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="languages">Languages</label>
                <input type="text" id="languages" name="languages" class="form-control" 
                       value="<?php echo htmlspecialchars($user['languages'] ?? ''); ?>" 
                       placeholder="e.g., English, Spanish, French">
            </div>
        </div>
    </div>

    <!-- Skills and Interests -->
    <div class="form-section">
        <h2 class="section-title">Skills & Interests</h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="skills">Professional Skills</label>
                <textarea id="skills" name="skills" class="form-control" rows="3" 
                          placeholder="Enter your professional skills"><?php echo htmlspecialchars($user['skills'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="interests">Interests & Hobbies</label>
                <textarea id="interests" name="interests" class="form-control" rows="3" 
                          placeholder="Enter your interests and hobbies"><?php echo htmlspecialchars($user['interests'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Social Media Links -->
    <div class="form-section">
        <h2 class="section-title">Social Media</h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="linkedin"><i class="fab fa-linkedin"></i> LinkedIn</label>
                <input type="url" id="linkedin" name="social_media[linkedin]" class="form-control" 
                       value="<?php echo htmlspecialchars(json_decode($user['social_media'] ?? '{}')->linkedin ?? ''); ?>" 
                       placeholder="LinkedIn Profile URL">
            </div>
            <div class="form-group">
                <label for="twitter"><i class="fab fa-twitter"></i> Twitter</label>
                <input type="url" id="twitter" name="social_media[twitter]" class="form-control" 
                       value="<?php echo htmlspecialchars(json_decode($user['social_media'] ?? '{}')->twitter ?? ''); ?>" 
                       placeholder="Twitter Profile URL">
            </div>
            <div class="form-group">
                <label for="facebook"><i class="fab fa-facebook"></i> Facebook</label>
                <input type="url" id="facebook" name="social_media[facebook]" class="form-control" 
                       value="<?php echo htmlspecialchars(json_decode($user['social_media'] ?? '{}')->facebook ?? ''); ?>" 
                       placeholder="Facebook Profile URL">
            </div>
            <div class="form-group">
                <label for="instagram"><i class="fab fa-instagram"></i> Instagram</label>
                <input type="url" id="instagram" name="social_media[instagram]" class="form-control" 
                       value="<?php echo htmlspecialchars(json_decode($user['social_media'] ?? '{}')->instagram ?? ''); ?>" 
                       placeholder="Instagram Profile URL">
            </div>
            <div class="form-group">
                <label for="github"><i class="fab fa-github"></i> GitHub</label>
                <input type="url" id="github" name="social_media[github]" class="form-control" 
                       value="<?php echo htmlspecialchars(json_decode($user['social_media'] ?? '{}')->github ?? ''); ?>" 
                       placeholder="GitHub Profile URL">
            </div>
            <div class="form-group">
                <label for="youtube"><i class="fab fa-youtube"></i> YouTube</label>
                <input type="url" id="youtube" name="social_media[youtube]" class="form-control" 
                       value="<?php echo htmlspecialchars(json_decode($user['social_media'] ?? '{}')->youtube ?? ''); ?>" 
                       placeholder="YouTube Channel URL">
            </div>
        </div>
    </div>

    <!-- Education Section -->
    <div class="form-section education-section">
        <h2>Education Background</h2>
        <div class="education-form">
            <div class="form-group">
                <label for="highest_degree">Degree Level*</label>
                <select id="highest_degree" name="education[highest_degree]" class="form-control">
                    <option value="">Select Degree</option>
                    <option value="high_school">High School</option>
                    <option value="diploma">Diploma</option>
                    <option value="associate">Associate Degree</option>
                    <option value="bachelors">Bachelor's Degree</option>
                    <option value="masters">Master's Degree</option>
                    <option value="phd">Ph.D.</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="institution">Institution Name*</label>
                <input type="text" id="institution" name="education[institution]" class="form-control"
                       placeholder="Enter institution name">
            </div>
            <div class="form-group">
                <label for="field_of_study">Field of Study*</label>
                <input type="text" id="field_of_study" name="education[field_of_study]" class="form-control"
                       placeholder="Enter your field of study">
            </div>
            <div class="form-group">
                <label for="graduation_year">Graduation Year*</label>
                <input type="number" id="graduation_year" name="education[graduation_year]" class="form-control"
                       min="1950" max="<?php echo date('Y'); ?>">
            </div>
            <div class="form-group">
                <button type="button" class="add-education-btn" onclick="addEducation()">
                    <i class="fas fa-plus"></i> Add Education
                </button>
            </div>
        </div>

        <!-- Education Table -->
        <div class="education-table-container">
            <table id="educationTable" class="education-table">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>Degree Level</th>
                        <th>Institution</th>
                        <th>Field of Study</th>
                        <th>Graduation Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($user['education_background'])) {
                        $education = json_decode($user['education_background'], true);
                        foreach ($education as $index => $edu) {
                            echo "<tr data-index='$index'>";
                            echo "<td>" . ($index + 1) . "</td>";
                            echo "<td>" . htmlspecialchars($edu['highest_degree']) . "</td>";
                            echo "<td>" . htmlspecialchars($edu['institution']) . "</td>";
                            echo "<td>" . htmlspecialchars($edu['field_of_study']) . "</td>";
                            echo "<td>" . htmlspecialchars($edu['graduation_year']) . "</td>";
                            echo "<td>
                                    <button type='button' class='action-btn edit-btn' onclick='editEducation($index)'>
                                        <i class='fas fa-edit'></i>
                                    </button>
                                    <button type='button' class='action-btn delete-btn' onclick='deleteEducation($index)'>
                                        <i class='fas fa-trash'></i>
                                    </button>
                                </td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Work Experience Section -->
    <div class="form-section">
        <h2 class="section-title">Work Experience</h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="current_company">Company Name</label>
                <input type="text" id="current_company" name="work_experience[current_company]" class="form-control"
                       placeholder="Enter company name">
            </div>
            <div class="form-group">
                <label for="job_title">Job Title</label>
                <input type="text" id="job_title" name="work_experience[job_title]" class="form-control"
                       placeholder="Enter your job title">
            </div>
            <div class="form-group">
                <label for="experience_years">Years of Experience</label>
                <input type="number" id="experience_years" name="work_experience[experience_years]" class="form-control"
                       min="0" step="0.5">
            </div>
            <div class="form-group full-width">
                <label for="responsibilities">Key Responsibilities</label>
                <textarea id="responsibilities" name="work_experience[responsibilities]" class="form-control" rows="3"
                          placeholder="Describe your key responsibilities"></textarea>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-primary" onclick="addWorkExperience()">Add Experience</button>
            </div>
        </div>

        <!-- Work Experience Table -->
        <div class="work-experience-table-container" style="margin-top: 20px;">
            <table id="workExperienceTable" class="table">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>Company</th>
                        <th>Job Title</th>
                        <th>Experience (Years)</th>
                        <th>Responsibilities</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($user['work_experiences'])) {
                        $experiences = json_decode($user['work_experiences'], true);
                        foreach ($experiences as $index => $exp) {
                            echo "<tr data-index='$index'>";
                            echo "<td>" . ($index + 1) . "</td>";
                            echo "<td>" . htmlspecialchars($exp['current_company']) . "</td>";
                            echo "<td>" . htmlspecialchars($exp['job_title']) . "</td>";
                            echo "<td>" . htmlspecialchars($exp['experience_years']) . "</td>";
                            echo "<td>" . htmlspecialchars($exp['responsibilities']) . "</td>";
                            echo "<td>
                                    <button type='button' class='btn btn-sm btn-edit' onclick='editExperience($index)'>
                                        <i class='fas fa-edit'></i>
                                    </button>
                                    <button type='button' class='btn btn-sm btn-delete' onclick='deleteExperience($index)'>
                                        <i class='fas fa-trash'></i>
                                    </button>
                                </td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bank Account Details Section -->
    <div class="form-section">
        <h2 class="section-title">Bank Account Details</h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="bank_name">Bank Name</label>
                <input type="text" id="bank_name" name="bank_details[bank_name]" class="form-control"
                       value="<?php echo htmlspecialchars(json_decode($user['bank_details'] ?? '{}')->bank_name ?? ''); ?>"
                       placeholder="Enter bank name">
            </div>
            <div class="form-group">
                <label for="account_holder">Account Holder Name</label>
                <input type="text" id="account_holder" name="bank_details[account_holder]" class="form-control"
                       value="<?php echo htmlspecialchars(json_decode($user['bank_details'] ?? '{}')->account_holder ?? ''); ?>"
                       placeholder="Enter account holder name">
            </div>
            <div class="form-group">
                <label for="account_number">Account Number</label>
                <input type="text" id="account_number" name="bank_details[account_number]" class="form-control"
                       value="<?php echo htmlspecialchars(json_decode($user['bank_details'] ?? '{}')->account_number ?? ''); ?>"
                       placeholder="Enter account number">
            </div>
            <div class="form-group">
                <label for="ifsc_code">IFSC Code</label>
                <input type="text" id="ifsc_code" name="bank_details[ifsc_code]" class="form-control"
                       value="<?php echo htmlspecialchars(json_decode($user['bank_details'] ?? '{}')->ifsc_code ?? ''); ?>"
                       placeholder="Enter IFSC code">
            </div>
        </div>
    </div>

    <!-- Documents Section -->
    <div class="form-section">
        <h2 class="section-title">Documents</h2>
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="document_type">Document Type</label>
                <select id="document_type" name="document[type]" class="form-control">
                    <option value="">Select Document Type</option>
                    <option value="aadhar">Aadhar Card</option>
                    <option value="pan">PAN Card</option>
                    <option value="passport">Passport</option>
                    <option value="driving_license">Driving License</option>
                    <option value="voter_id">Voter ID</option>
                    <option value="resume">Resume</option>
                    <option value="certificates">Certificates</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label for="document_file">Upload Document</label>
                <input type="file" id="document_file" name="document[file]" class="form-control" 
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Supported formats: PDF, DOC, DOCX, JPG, PNG (Max size: 5MB)
                </small>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-primary" onclick="addDocument()">Add Document</button>
            </div>
        </div>

        <!-- Documents Table -->
        <div class="documents-table-container" style="margin-top: 20px;">
            <table id="documentsTable" class="table">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>Document Type</th>
                        <th>File Name</th>
                        <th>Upload Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($user['documents'])) {
                        $documents = json_decode($user['documents'], true);
                        foreach ($documents as $index => $doc) {
                            echo "<tr data-index='$index'>";
                            echo "<td>" . ($index + 1) . "</td>";
                            echo "<td>" . htmlspecialchars($doc['type']) . "</td>";
                            echo "<td>" . htmlspecialchars($doc['filename']) . "</td>";
                            echo "<td>" . htmlspecialchars($doc['upload_date']) . "</td>";
                            echo "<td>
                                    <button type='button' class='btn btn-sm btn-view' onclick='viewDocument(\"" . htmlspecialchars($doc['file_path']) . "\")'>
                                        <i class='fas fa-eye'></i>
                                    </button>
                                    <button type='button' class='btn btn-sm btn-download' onclick='downloadDocument(\"" . htmlspecialchars($doc['file_path']) . "\")'>
                                        <i class='fas fa-download'></i>
                                    </button>
                                    <button type='button' class='btn btn-sm btn-delete' onclick='deleteDocument($index)'>
                                        <i class='fas fa-trash'></i>
                                    </button>
                                </td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="button-group">
        <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>

<style>
.profile-picture-container {
    display: flex;
    align-items: center;
    gap: 30px;
    padding: 30px;
    background: #f8f9fa;
    border-radius: 12px;
}

.profile-picture-wrapper {
    position: relative;
    width: 200px;
    height: 200px;
}

.current-picture {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    background: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.current-picture img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.picture-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 50%;
}

.current-picture:hover .picture-overlay {
    opacity: 1;
}

.change-picture-btn {
    color: white;
    cursor: pointer;
    text-align: center;
    padding: 10px;
}

.change-picture-btn i {
    font-size: 24px;
    margin-bottom: 8px;
    display: block;
}

.change-picture-btn span {
    display: block;
    font-size: 14px;
}

.picture-info {
    flex: 1;
}

.file-requirements {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    line-height: 1.6;
}

.file-requirements i {
    color: #3498db;
    margin-right: 8px;
}

/* Animation for hover effect */
.current-picture:hover img {
    transform: scale(1.1);
}

@media (max-width: 768px) {
    .profile-picture-container {
        flex-direction: column;
        text-align: center;
    }

    .profile-picture-wrapper {
        margin: 0 auto;
    }

    .picture-info {
        text-align: center;
    }
}

.form-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.form-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}

.section-title {
    color: #2c3e50;
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    position: relative;
    padding-bottom: 10px;
}

.section-title::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 50px;
    height: 3px;
    background: #3498db;
    transition: width 0.3s ease;
}

.form-section:hover .section-title::after {
    width: 100px;
}

.form-control {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    transform: translateY(-1px);
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #3498db;
    border: none;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
}

.btn-secondary {
    background: #95a5a6;
    border: none;
}

.btn-secondary:hover {
    background: #7f8c8d;
    transform: translateY(-2px);
}

.button-group {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

/* Animation for form sections on load */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-section {
    animation: fadeInUp 0.5s ease forwards;
}

.form-section:nth-child(2) { animation-delay: 0.1s; }
.form-section:nth-child(3) { animation-delay: 0.2s; }
.form-section:nth-child(4) { animation-delay: 0.3s; }
.form-section:nth-child(5) { animation-delay: 0.4s; }
.form-section:nth-child(6) { animation-delay: 0.5s; }

/* Social media icons styling */
.fab {
    margin-right: 8px;
    transition: transform 0.3s ease;
}

.form-group:hover .fab.fa-linkedin { color: #0077b5; transform: scale(1.2); }
.form-group:hover .fab.fa-twitter { color: #1da1f2; transform: scale(1.2); }

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #3498db;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #2980b9;
}

/* Additional styles for new sections */
.form-section {
    margin-bottom: 30px;
}

.full-width {
    grid-column: 1 / -1;
}

/* Secure field styling */
input[name="bank_details[account_number]"] {
    letter-spacing: 0.5px;
    font-family: monospace;
}

/* Add some spacing between sections */
.form-section + .form-section {
    margin-top: 40px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.work-experience-table-container {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table th, .table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 14px;
}

.btn-edit {
    color: #2980b9;
    margin-right: 5px;
}

.btn-delete {
    color: #e74c3c;
}

.btn-edit:hover, .btn-delete:hover {
    opacity: 0.8;
}
</style>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function updatePersonalInfo(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // Show loading indicator
    Swal.fire({
        title: 'Updating...',
        text: 'Please wait while we update your profile',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('update_personal_info.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin' // Include cookies if using sessions
    })
    .then(async response => {
        const contentType = response.headers.get('content-type');
        if (!response.ok) {
            // Try to get error message from response
            const errorText = await response.text();
            console.error('Server response:', errorText);
            throw new Error('Server returned: ' + (errorText || response.statusText));
        }
        
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        }
        throw new Error('Expected JSON response but got ' + contentType);
    })
    .then(data => {
        Swal.close(); // Close loading indicator
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message || 'Profile updated successfully'
            }).then(() => {
                // Update profile image if it was changed
                if (data.profile_picture) {
                    const profileImg = document.querySelector('.profile-image img');
                    if (profileImg) {
                        profileImg.src = data.profile_picture + '?v=' + new Date().getTime();
                    }
                }
            });
        } else {
            throw new Error(data.message || 'Failed to update profile');
        }
    })
    .catch(error => {
        console.error('Error details:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'An error occurred while updating profile'
        });
    });
    
    return false;
}

function resetForm() {
    document.getElementById('personalInfoForm').reset();
}

function addWorkExperience() {
    const company = document.getElementById('current_company').value;
    const jobTitle = document.getElementById('job_title').value;
    const years = document.getElementById('experience_years').value;
    const responsibilities = document.getElementById('responsibilities').value;

    if (!company || !jobTitle || !years || !responsibilities) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please fill all required fields'
        });
        return;
    }

    const formData = new FormData();
    formData.append('company', company);
    formData.append('jobTitle', jobTitle);
    formData.append('years', years);
    formData.append('responsibilities', responsibilities);
    formData.append('action', 'add');

    fetch('update_work_experience.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add new row to table
            const tbody = document.querySelector('#workExperienceTable tbody');
            const newRow = `
                <tr data-index="${data.index}">
                    <td>${data.index + 1}</td>
                    <td>${company}</td>
                    <td>${jobTitle}</td>
                    <td>${years}</td>
                    <td>${responsibilities}</td>
                    <td>
                        <button type='button' class='btn btn-sm btn-edit' onclick='editExperience(${data.index})'>
                            <i class='fas fa-edit'></i>
                        </button>
                        <button type='button' class='btn btn-sm btn-delete' onclick='deleteExperience(${data.index})'>
                            <i class='fas fa-trash'></i>
                        </button>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', newRow);

            // Clear form
            document.getElementById('current_company').value = '';
            document.getElementById('job_title').value = '';
            document.getElementById('experience_years').value = '';
            document.getElementById('responsibilities').value = '';

            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Work experience added successfully'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to add work experience'
        });
    });
}

function editExperience(index) {
    const row = document.querySelector(`tr[data-index="${index}"]`);
    const cells = row.getElementsByTagName('td');

    // Fill form with existing data
    document.getElementById('current_company').value = cells[1].textContent;
    document.getElementById('job_title').value = cells[2].textContent;
    document.getElementById('experience_years').value = cells[3].textContent;
    document.getElementById('responsibilities').value = cells[4].textContent;

    // Change add button to update
    const addButton = document.querySelector('.form-group button');
    addButton.textContent = 'Update Experience';
    addButton.onclick = () => updateExperience(index);
}

function updateExperience(index) {
    // Similar to addWorkExperience but with update logic
    // You'll need to create update_work_experience.php to handle the update
    // After successful update, change button back to "Add Experience"
}

function deleteExperience(index) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('index', index);
            formData.append('action', 'delete');

            fetch('update_work_experience.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`tr[data-index="${index}"]`).remove();
                    Swal.fire('Deleted!', 'Work experience has been deleted.', 'success');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Failed to delete work experience', 'error');
            });
        }
    });
}

// Add these new functions for education management
function addEducation() {
    const degree = document.getElementById('highest_degree').value;
    const institution = document.getElementById('institution').value;
    const fieldOfStudy = document.getElementById('field_of_study').value;
    const graduationYear = document.getElementById('graduation_year').value;

    if (!degree || !institution || !fieldOfStudy || !graduationYear) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please fill all required education fields'
        });
        return;
    }

    const formData = new FormData();
    formData.append('degree', degree);
    formData.append('institution', institution);
    formData.append('fieldOfStudy', fieldOfStudy);
    formData.append('graduationYear', graduationYear);
    formData.append('action', 'add');

    fetch('update_education.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#educationTable tbody');
            const newRow = `
                <tr data-index="${data.index}">
                    <td>${data.index + 1}</td>
                    <td>${degree}</td>
                    <td>${institution}</td>
                    <td>${fieldOfStudy}</td>
                    <td>${graduationYear}</td>
                    <td>
                        <button type='button' class='action-btn edit-btn' onclick='editEducation(${data.index})'>
                            <i class='fas fa-edit'></i>
                        </button>
                        <button type='button' class='action-btn delete-btn' onclick='deleteEducation(${data.index})'>
                            <i class='fas fa-trash'></i>
                        </button>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', newRow);

            // Clear form
            document.getElementById('highest_degree').value = '';
            document.getElementById('institution').value = '';
            document.getElementById('field_of_study').value = '';
            document.getElementById('graduation_year').value = '';

            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Education background added successfully'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to add education background'
        });
    });
}

function editEducation(index) {
    const row = document.querySelector(`tr[data-index="${index}"]`);
    const cells = row.getElementsByTagName('td');

    document.getElementById('highest_degree').value = cells[1].textContent;
    document.getElementById('institution').value = cells[2].textContent;
    document.getElementById('field_of_study').value = cells[3].textContent;
    document.getElementById('graduation_year').value = cells[4].textContent;

    const addButton = document.querySelector('.add-education-btn');
    addButton.innerHTML = '<i class="fas fa-check"></i> Update Education';
    addButton.setAttribute('data-editing-index', index);
    addButton.onclick = () => updateEducation(index);
}

function updateEducation(index) {
    const degree = document.getElementById('highest_degree').value;
    const institution = document.getElementById('institution').value;
    const fieldOfStudy = document.getElementById('field_of_study').value;
    const graduationYear = document.getElementById('graduation_year').value;

    if (!degree || !institution || !fieldOfStudy || !graduationYear) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please fill all required fields'
        });
        return;
    }

    const formData = new FormData();
    formData.append('degree', degree);
    formData.append('institution', institution);
    formData.append('fieldOfStudy', fieldOfStudy);
    formData.append('graduationYear', graduationYear);
    formData.append('index', index);
    formData.append('action', 'update');

    fetch('update_education.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr[data-index="${index}"]`);
            const cells = row.getElementsByTagName('td');
            cells[1].textContent = degree;
            cells[2].textContent = institution;
            cells[3].textContent = fieldOfStudy;
            cells[4].textContent = graduationYear;

            // Reset form and button
            document.getElementById('highest_degree').value = '';
            document.getElementById('institution').value = '';
            document.getElementById('field_of_study').value = '';
            document.getElementById('graduation_year').value = '';

            const addButton = document.querySelector('.add-education-btn');
            addButton.innerHTML = '<i class="fas fa-plus"></i> Add Education';
            addButton.onclick = addEducation;
            addButton.removeAttribute('data-editing-index');

            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Education background updated successfully'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to update education background'
        });
    });
}

function deleteEducation(index) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('index', index);
            formData.append('action', 'delete');

            fetch('update_education.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`tr[data-index="${index}"]`).remove();
                    // Update serial numbers
                    const rows = document.querySelectorAll('#educationTable tbody tr');
                    rows.forEach((row, idx) => {
                        row.querySelector('td:first-child').textContent = idx + 1;
                    });
                    Swal.fire('Deleted!', 'Education background has been deleted.', 'success');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Failed to delete education background', 'error');
            });
        }
    });
}

function addDocument() {
    const docType = document.getElementById('document_type');
    const docFile = document.getElementById('document_file');
    
    if (!docType.value || !docFile.files[0]) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please select both document type and file'
        });
        return;
    }

    const formData = new FormData();
    formData.append('type', docType.value);
    formData.append('file', docFile.files[0]);
    formData.append('action', 'add');

    // Show loading indicator
    Swal.fire({
        title: 'Uploading...',
        text: 'Please wait while we upload your document',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('update_documents.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            // Add new row to table
            const tbody = document.querySelector('#documentsTable tbody');
            const newRow = `
                <tr data-index="${data.index}">
                    <td>${data.index + 1}</td>
                    <td>${docType.options[docType.selectedIndex].text}</td>
                    <td>${data.filename}</td>
                    <td>${data.upload_date}</td>
                    <td>
                        <button type='button' class='btn btn-sm btn-view' onclick='viewDocument("${data.file_path}")'>
                            <i class='fas fa-eye'></i>
                        </button>
                        <button type='button' class='btn btn-sm btn-download' onclick='downloadDocument("${data.file_path}")'>
                            <i class='fas fa-download'></i>
                        </button>
                        <button type='button' class='btn btn-sm btn-delete' onclick='deleteDocument(${data.index})'>
                            <i class='fas fa-trash'></i>
                        </button>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', newRow);

            // Reset form
            docType.value = '';
            docFile.value = '';

            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Document uploaded successfully'
            });
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to upload document'
        });
    });
}

function viewDocument(filePath) {
    window.open(filePath, '_blank');
}

function downloadDocument(filePath) {
    window.location.href = filePath;
}

function deleteDocument(index) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('index', index);
            formData.append('action', 'delete');

            fetch('update_documents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`tr[data-index="${index}"]`).remove();
                    // Update serial numbers
                    const rows = document.querySelectorAll('#documentsTable tbody tr');
                    rows.forEach((row, idx) => {
                        row.querySelector('td:first-child').textContent = idx + 1;
                    });
                    Swal.fire('Deleted!', 'Document has been deleted.', 'success');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Failed to delete document', 'error');
            });
        }
    });
}
</script> 