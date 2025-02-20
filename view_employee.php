<?php
session_start();
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: employees.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, s.start_time, s.end_time, s.break_start, s.break_end 
        FROM users u 
        LEFT JOIN shift_timings s ON u.id = s.user_id 
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $_GET['id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $_SESSION['error'] = "Employee not found";
        header('Location: employees.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching employee details: " . $e->getMessage();
    header('Location: employees.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details | HR Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 40px 0;
        }

        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            position: relative;
            min-height: 120px;
        }

        .profile-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .employee-id {
            position: absolute;
            top: 20px;
            right: 30px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        .profile-content {
            background: white;
            padding: 30px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .info-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .info-section h3 {
            color: #4a5568;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 500;
        }

        .badge-role {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-right: 5px;
        }

        .badge-department {
            background: #4299e1;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
        }

        .btn-custom {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
        }

        .back-btn {
            position: absolute;
            top: -30px;
            left: 20px;
            color: #4a5568;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .back-btn:hover {
            color: #2d3748;
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 10px;
        }

        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            position: relative;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.5);
            padding: 4px;
            text-align: center;
            cursor: pointer;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-picture:hover .upload-overlay {
            opacity: 1;
        }

        .profile-info {
            flex-grow: 1;
        }

        #profileImageUpload {
            display: none;
        }

        .loading-spinner {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
        }

        .status-active {
            color: #48bb78;
        }

        .status-inactive {
            color: #e53e3e;
        }

        .weekly-off-days {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .day-badge {
            background: #edf2f7;
            color: #4a5568;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .shift-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 15px;
            align-items: center;
        }

        .detail-row .label {
            font-weight: 600;
            width: 150px;
            color: #495057;
        }

        .weekly-offs-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-info {
            background: #e3f2fd;
            color: #0d6efd;
        }

        .no-shift {
            color: #6c757d;
            font-style: italic;
            margin-top: 10px;
        }

        .shift-details {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 15px;
            border: 1px solid #e2e8f0;
        }

        .detail-row {
            display: flex;
            margin-bottom: 20px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-row .label {
            font-weight: 600;
            width: 180px;
            color: #4a5568;
            display: flex;
            align-items: center;
        }

        .detail-row .value {
            color: #2d3748;
            flex: 1;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .badge-info {
            background: #ebf8ff;
            color: #3182ce;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            border: 1px solid #bee3f8;
            transition: all 0.3s ease;
        }

        .badge-info:hover {
            background: #bee3f8;
            transform: translateY(-1px);
        }

        .no-shift {
            color: #718096;
            font-style: italic;
            margin-top: 15px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            text-align: center;
            border: 1px dashed #cbd5e0;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <a href="hr_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            
            <div class="profile-header-content">
                <div class="profile-picture">
                    <?php
                    $profileImage = !empty($employee['profile_image']) 
                        ? 'uploads/profile_images/' . $employee['profile_image'] 
                        : 'assets/default-profile.png';
                    ?>
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" 
                         alt="Profile Picture" 
                         id="profileImage">
                    
                    <?php if ($_SESSION['role'] === 'HR'): ?>
                    <div class="upload-overlay" onclick="document.getElementById('profileImageUpload').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <input type="file" 
                           id="profileImageUpload" 
                           accept="image/*" 
                           onchange="uploadProfileImage(<?php echo $employee['id']; ?>)">
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <h2><i class="fas fa-user-circle mr-2"></i><?php echo htmlspecialchars($employee['username']); ?></h2>
                    <div class="employee-id">
                        ID: <?php echo htmlspecialchars($employee['unique_id']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <!-- Basic Information -->
            <div class="info-section">
                <h3><i class="fas fa-info-circle mr-2"></i>Basic Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <i class="fas fa-envelope mr-2"></i>
                            <?php echo htmlspecialchars($employee['email']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Role(s)</div>
                        <div class="info-value">
                            <?php 
                            $roles = explode(', ', $employee['role']);
                            foreach($roles as $role): ?>
                                <span class="badge-role"><?php echo htmlspecialchars($role); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Department</div>
                        <div class="info-value">
                            <span class="badge-department">
                                <?php echo htmlspecialchars($employee['department']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Designation</div>
                        <div class="info-value">
                            <i class="fas fa-id-badge mr-2"></i>
                            <?php echo htmlspecialchars($employee['designation']); ?>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Shift Information -->
             <div class="info-section">
                <h3><i class="fas fa-clock mr-2"></i>Shift Information</h3>
                <?php
                $shiftStmt = $pdo->prepare("
                    SELECT s.* 
                    FROM shifts s 
                    JOIN users u ON s.id = u.shift_id 
                    WHERE u.id = ?
                ");
                $shiftStmt->execute([$employee['id']]);
                $shift = $shiftStmt->fetch();
                
                if ($shift): 
                    $weekly_offs = explode(',', $shift['weekly_offs']);
                ?>
                    <div class="shift-details">
                        <div class="detail-row">
                            <span class="label"><i class="fas fa-id-card-alt mr-2"></i>Shift Name:</span>
                            <span class="value"><?php echo htmlspecialchars($shift['shift_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><i class="far fa-clock mr-2"></i>Timing:</span>
                            <span class="value">
                                <i class="fas fa-sun mr-1 text-warning"></i>
                                <?php echo date('h:i A', strtotime($shift['start_time'])); ?>
                                <i class="fas fa-arrow-right mx-2"></i>
                                <i class="fas fa-moon mr-1 text-primary"></i>
                                <?php echo date('h:i A', strtotime($shift['end_time'])); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><i class="fas fa-calendar-alt mr-2"></i>Weekly Offs:</span>
                            <div class="value weekly-offs-badges">
                                <?php foreach ($weekly_offs as $day): ?>
                                    <span class="badge badge-info">
                                        <i class="fas fa-calendar-day mr-1"></i>
                                        <?php echo trim($day); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="no-shift">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        No shift assigned
                    </p>
                <?php endif; ?>
            </div>



            <!-- Reporting Information -->
            <div class="info-section">
                <h3><i class="fas fa-user-tie mr-2"></i>Reporting Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Reporting Manager</div>
                        <div class="info-value">
                            <i class="fas fa-user-shield mr-2"></i>
                            <?php echo htmlspecialchars($employee['reporting_manager']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <i class="fas fa-circle mr-2 <?php echo $employee['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>"></i>
                            <?php echo ucfirst(htmlspecialchars($employee['status'])); ?>
                        </div>
                    </div>
                </div>
            </div>

           

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary btn-custom">
                    <i class="fas fa-edit mr-2"></i>Edit Employee
                </a>
                <?php if($employee['status'] === 'active'): ?>
                    <button class="btn btn-danger btn-custom" onclick="deactivateEmployee(<?php echo $employee['id']; ?>)">
                        <i class="fas fa-user-times mr-2"></i>Deactivate Employee
                    </button>
                <?php else: ?>
                    <button class="btn btn-success btn-custom" onclick="activateEmployee(<?php echo $employee['id']; ?>)">
                        <i class="fas fa-user-check mr-2"></i>Activate Employee
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- Add the new upload profile image functionality -->
    <script>
    function uploadProfileImage(employeeId) {
        const fileInput = document.getElementById('profileImageUpload');
        const file = fileInput.files[0];
        if (!file) return;

        // Check file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            return;
        }

        // Check file type
        if (!file.type.match('image.*')) {
            alert('Please select an image file');
            return;
        }

        const formData = new FormData();
        formData.append('profile_image', file);
        formData.append('employee_id', employeeId);

        // Show loading spinner
        document.querySelector('.loading-spinner').style.display = 'block';

        fetch('upload_profile_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update all instances of the image
                updateProfileImages(data.image_url);
                
                // Update session if it's the current user's image
                if (employeeId === <?php echo $_SESSION['user_id']; ?>) {
                    fetch('update_session_image.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'user_id=' + employeeId
                    });
                }
            } else {
                alert(data.error || 'Error uploading image');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error uploading image');
        })
        .finally(() => {
            // Hide loading spinner
            document.querySelector('.loading-spinner').style.display = 'none';
        });
    }

    // Helper function to update all instances of the profile image
    function updateProfileImages(newImageUrl) {
        const profileImages = document.querySelectorAll(`img[src*="${newImageUrl.split('_')[1]}"]`);
        profileImages.forEach(img => {
            img.src = newImageUrl + '?v=' + new Date().getTime();
        });
    }
    </script>
</body>
</html>

