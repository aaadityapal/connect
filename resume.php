<?php
session_start();

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Helper functions
function generateRandomPassword($length = 10) {
    // Define character sets
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $special = '@#$%^&*()_+';
    
    // Initialize password
    $password = '';
    
    // Add at least one character from each set
    $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
    $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $special[rand(0, strlen($special) - 1)];
    
    // Complete the password to desired length
    $allChars = $lowercase . $uppercase . $numbers . $special;
    while(strlen($password) < $length) {
        $password .= $allChars[rand(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password to make it more random
    $password = str_shuffle($password);
    
    return $password;
}

function sendCredentialsToUser($mobile, $password) {
    // Here you would implement your SMS or email notification system
    // For now, we'll just store it in a session for demonstration
    $_SESSION['new_user_credentials'] = [
        'username' => $mobile,
        'password' => $password
    ];
    
    // You could implement actual SMS sending here, for example:
    /*
    $message = "Your account has been created. Username: $mobile, Password: $password";
    // Using a hypothetical SMS service
    sendSMS($mobile, $message);
    */
}

// Handle resume upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
    $uploadDir = 'uploads/resumes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $candidateName = $_POST['candidate_name'];
    $position = $_POST['position'];
    $uploadDate = date('Y-m-d');
    $fileName = time() . '_' . basename($_FILES['resume']['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("INSERT INTO resumes (candidate_name, position, file_name, upload_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$candidateName, $position, $fileName, $uploadDate]);
    }
}

// Get filter parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Fetch resumes based on filters
$query = "SELECT * FROM resumes WHERE MONTH(upload_date) = ? AND YEAR(upload_date) = ? ORDER BY upload_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$month, $year]);
$resumes = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert basic resume information
        $stmt = $pdo->prepare("INSERT INTO resumes (candidate_name, mobile_number, dob, 
            current_address, permanent_address, position, current_salary, expected_salary, 
            file_name, upload_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $status = $_POST['action'] === 'accept' ? 'accepted' : 
                 ($_POST['action'] === 'reject' ? 'rejected' : 'sent_to_admin');

        $fileName = time() . '_' . basename($_FILES['resume']['name']);
        
        $stmt->execute([
            $_POST['candidate_name'],
            $_POST['mobile_number'],
            $_POST['dob'],
            $_POST['current_address'],
            $_POST['permanent_address'],
            $_POST['position'],
            $_POST['current_salary'],
            $_POST['expected_salary'],
            $fileName,
            date('Y-m-d'),
            $status
        ]);

        $resumeId = $pdo->lastInsertId();

        // Insert qualifications
        $qualStmt = $pdo->prepare("INSERT INTO qualifications (resume_id, completion_year, 
            institute_name, board, overall_score) VALUES (?, ?, ?, ?, ?)");

        foreach ($_POST['qual_year'] as $key => $year) {
            $qualStmt->execute([
                $resumeId,
                $year,
                $_POST['qual_institute'][$key],
                $_POST['qual_board'][$key],
                $_POST['qual_score'][$key]
            ]);
        }

        // Insert experiences
        $expStmt = $pdo->prepare("INSERT INTO experiences (resume_id, office_name, 
            from_date, to_date, role, experience_years) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($_POST['exp_office'] as $key => $office) {
            $fromDate = new DateTime($_POST['exp_from'][$key]);
            $toDate = !empty($_POST['exp_to'][$key]) ? 
                     new DateTime($_POST['exp_to'][$key]) : new DateTime();
            
            $interval = $fromDate->diff($toDate);
            $years = $interval->y + ($interval->m / 12) + ($interval->d / 365.25);

            $expStmt->execute([
                $resumeId,
                $office,
                $_POST['exp_from'][$key],
                $_POST['exp_to'][$key] ?: null,
                $_POST['exp_role'][$key],
                $years
            ]);
        }

        // Move uploaded file
        $uploadDir = 'uploads/resumes/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $fileName);

        // If status is accepted, create user account
        if ($status === 'accepted') {
            $password = generateRandomPassword(); // Create this function
            $userStmt = $pdo->prepare("INSERT INTO users (username, password, role) 
                VALUES (?, ?, 'user')");
            $userStmt->execute([$_POST['mobile_number'], password_hash($password, PASSWORD_DEFAULT)]);
            
            // Send credentials to user (implement your notification system)
            sendCredentialsToUser($_POST['mobile_number'], $password); // Create this function
        }

        $pdo->commit();
        $_SESSION['success'] = "Resume processed successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --success-color: #059669;
            --danger-color: #dc2626;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        body {
            background-color: #f1f5f9;
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .resume-container {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 2rem;
            background: transparent;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .form-section {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .section-header {
            background: #ffffff;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .section-header h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-header i {
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .section-content {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="tel"],
        input[type="number"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.975rem;
            color: var(--text-primary);
            background-color: #ffffff;
            transition: all 0.2s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.975rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #ffffff;
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-color);
        }

        .btn-success {
            background: var(--success-color);
        }

        .btn-danger {
            background: var(--danger-color);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Entry Sections */
        .qualification-entry,
        .experience-entry {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            position: relative;
            border: 1px solid var(--border-color);
        }

        .remove-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .remove-btn:hover {
            background: #fecaca;
            transform: scale(1.1);
        }

        .add-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .add-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .total-experience {
            background: #dbeafe;
            color: var(--primary-color);
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            margin-top: 1.5rem;
            display: inline-block;
        }

        .action-section {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding: 2rem;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
        }

        /* Alert Styling */
        .alert {
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        /* File Upload */
        .file-upload {
            border: 2px dashed var(--border-color);
            padding: 2rem;
            text-align: center;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .file-upload:hover {
            border-color: var(--primary-color);
            background: var(--bg-light);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .resume-container {
                margin: 1rem;
                padding: 1rem;
            }

            .section-content {
                padding: 1.5rem;
            }

            .action-section {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Include your sidebar from hr_dashboard.php -->

    <div class="main-content">
        <div class="header">
            <!-- Include your header content from hr_dashboard.php -->
        </div>

        <div class="resume-container">
            <h2>Resume Management</h2>
            
            <form action="" method="POST" enctype="multipart/form-data" id="resumeForm">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                    </div>
                    <div class="section-content">
                        <div class="form-group">
                            <label for="candidate_name">Full Name*</label>
                            <input type="text" id="candidate_name" name="candidate_name" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group flex-1">
                                <label for="mobile_number">Mobile Number*</label>
                                <input type="tel" id="mobile_number" name="mobile_number" pattern="[0-9]{10}" required>
                            </div>
                            <div class="form-group flex-1">
                                <label for="dob">Date of Birth*</label>
                                <input type="date" id="dob" name="dob" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="current_address">Current Address*</label>
                            <textarea id="current_address" name="current_address" rows="3" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="permanent_address">Permanent Address*</label>
                            <textarea id="permanent_address" name="permanent_address" rows="3" required></textarea>
                        </div>
                    </div>
                </div>

                <!-- Professional Information Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                    </div>
                    <div class="section-content">
                        <div class="form-group">
                            <label for="position">Applied Position (Designation)*</label>
                            <input type="text" id="position" name="position" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group flex-1">
                                <label for="current_salary">Current Salary (₹)</label>
                                <input type="number" id="current_salary" name="current_salary">
                            </div>
                            <div class="form-group flex-1">
                                <label for="expected_salary">Expected Salary (₹)*</label>
                                <input type="number" id="expected_salary" name="expected_salary" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Qualifications Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3><i class="fas fa-graduation-cap"></i> Educational Qualifications</h3>
                    </div>
                    <div class="section-content">
                        <div id="qualificationsList">
                            <!-- Qualification entries will be added here -->
                        </div>
                        <button type="button" class="add-btn" id="addQualification">
                            <i class="fas fa-plus"></i> Add Qualification
                        </button>
                    </div>
                </div>

                <!-- Experience Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3><i class="fas fa-history"></i> Work Experience</h3>
                    </div>
                    <div class="section-content">
                        <div id="experienceList">
                            <!-- Experience entries will be added here -->
                        </div>
                        <button type="button" class="add-btn" id="addExperience">
                            <i class="fas fa-plus"></i> Add Experience
                        </button>
                        <div class="total-experience">
                            Total Experience: <span id="totalExperience">0 years</span>
                        </div>
                    </div>
                </div>

                <!-- Resume Upload Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3><i class="fas fa-file-upload"></i> Resume Upload</h3>
                    </div>
                    <div class="section-content">
                        <div class="form-group">
                            <label for="resume">Upload Resume (PDF, DOC, DOCX)*</label>
                            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" required>
                            <small class="file-hint">Maximum file size: 5MB</small>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-section">
                    <button type="submit" name="action" value="send_to_admin" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send to Admin
                    </button>
                    <button type="submit" name="action" value="accept" class="btn btn-success">
                        <i class="fas fa-check"></i> Accept
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Qualification Entry Template
            const qualificationHtml = `
                <div class="qualification-entry">
                    <button type="button" class="remove-btn">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label>Year of Completion*</label>
                            <input type="number" name="qual_year[]" required min="1900" max="2099">
                        </div>
                        <div class="form-group flex-1">
                            <label>Institute Name*</label>
                            <input type="text" name="qual_institute[]" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label>Board/University*</label>
                            <input type="text" name="qual_board[]" required>
                        </div>
                        <div class="form-group flex-1">
                            <label>Overall Score (%)*</label>
                            <input type="number" step="0.01" name="qual_score[]" required min="0" max="100">
                        </div>
                    </div>
                </div>
            `;

            // Experience Entry Template
            const experienceHtml = `
                <div class="experience-entry">
                    <button type="button" class="remove-btn">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label>Company/Organization Name*</label>
                            <input type="text" name="exp_office[]" required>
                        </div>
                        <div class="form-group flex-1">
                            <label>Role/Designation*</label>
                            <input type="text" name="exp_role[]" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label>From Date*</label>
                            <input type="date" name="exp_from[]" class="exp-date" required>
                        </div>
                        <div class="form-group flex-1">
                            <label>To Date</label>
                            <input type="date" name="exp_to[]" class="exp-date">
                            <small class="file-hint">Leave empty if currently working</small>
                        </div>
                    </div>
                    <div class="experience-duration"></div>
                </div>
            `;

            // Qualification Section
            document.getElementById('addQualification').addEventListener('click', function() {
                document.getElementById('qualificationsList').insertAdjacentHTML('beforeend', qualificationHtml);
            });

            // Experience Section
            document.getElementById('addExperience').addEventListener('click', function() {
                document.getElementById('experienceList').insertAdjacentHTML('beforeend', experienceHtml);
            });

            // Remove buttons functionality
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-btn')) {
                    e.target.closest('.qualification-entry, .experience-entry').remove();
                    calculateTotalExperience();
                }
            });

            // Calculate experience duration
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('exp-date')) {
                    calculateTotalExperience();
                }
            });

            function calculateTotalExperience() {
                let totalYears = 0;
                const entries = document.querySelectorAll('.experience-entry');
                
                entries.forEach(entry => {
                    const fromDate = new Date(entry.querySelector('input[name="exp_from[]"]').value);
                    const toDateInput = entry.querySelector('input[name="exp_to[]"]');
                    const toDate = toDateInput.value ? new Date(toDateInput.value) : new Date();
                    
                    if (fromDate && toDate) {
                        const diff = toDate - fromDate;
                        const years = diff / (1000 * 60 * 60 * 24 * 365.25);
                        totalYears += years;
                        
                        entry.querySelector('.experience-duration').textContent = 
                            `Experience: ${years.toFixed(1)} years`;
                    }
                });

                document.getElementById('totalExperience').textContent = 
                    `${totalYears.toFixed(1)} years`;
            }
        });
    </script>

    <?php if (isset($_SESSION['new_user_credentials'])): ?>
        <div class="alert alert-success">
            <h4>New User Account Created</h4>
            <p>Username: <?php echo htmlspecialchars($_SESSION['new_user_credentials']['username']); ?></p>
            <p>Password: <?php echo htmlspecialchars($_SESSION['new_user_credentials']['password']); ?></p>
        </div>
        <?php unset($_SESSION['new_user_credentials']); ?>
    <?php endif; ?>
</body>
</html> 