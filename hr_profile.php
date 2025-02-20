<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic validation
        if (empty($_POST['username']) || empty($_POST['email'])) {
            throw new Exception("Username and email are required fields.");
        }

        // Prepare update data
        $updateData = [
            'username' => $_POST['username'],
            'position' => $_POST['position'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'designation' => $_POST['designation'],
            'address' => $_POST['address'],
            'emergency_contact_name' => $_POST['emergency_contact_name'],
            'emergency_contact_phone' => $_POST['emergency_contact_phone'],
            'city' => $_POST['city'],
            'state' => $_POST['state'],
            'country' => $_POST['country'],
            'postal_code' => $_POST['postal_code'],
            'dob' => $_POST['dob'],
            'bio' => $_POST['bio'],
            'gender' => $_POST['gender'],
            'marital_status' => $_POST['marital_status'],
            'nationality' => $_POST['nationality'],
            'languages' => $_POST['languages'],
            'social_media' => json_encode($_POST['social_media']),
            'skills' => $_POST['skills'],
            'interests' => $_POST['interests'],
            'blood_group' => $_POST['blood_group'],
            'education' => json_encode($_POST['education']),
            'work_experience' => json_encode($_POST['work_experience']),
            'bank_details' => json_encode($_POST['bank_details']),
            'modified_at' => date('Y-m-d H:i:s'),
            'id' => $user_id
        ];

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);

            if (!in_array(strtolower($filetype), $allowed)) {
                throw new Exception("Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.");
            }

            $tempname = $_FILES['profile_picture']['tmp_name'];
            $folder = "uploads/profile_pictures/";
            
            // Create directory if it doesn't exist
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Delete old profile picture if exists
            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                unlink($user['profile_picture']);
            }

            $new_filename = uniqid('profile_') . '.' . $filetype;
            $destination = $folder . $new_filename;
            
            if (move_uploaded_file($tempname, $destination)) {
                $updateData['profile_picture'] = $destination;
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }

        // Build SQL query
        $sql = "UPDATE users SET 
                username = :username,
                position = :position,
                email = :email,
                phone = :phone,
                designation = :designation,
                address = :address,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                city = :city,
                state = :state,
                country = :country,
                postal_code = :postal_code,
                dob = :dob,
                bio = :bio,
                gender = :gender,
                marital_status = :marital_status,
                nationality = :nationality,
                languages = :languages,
                social_media = :social_media,
                skills = :skills,
                interests = :interests,
                blood_group = :blood_group,
                education = :education,
                work_experience = :work_experience,
                bank_details = :bank_details,
                modified_at = :modified_at";

        if (isset($updateData['profile_picture'])) {
            $sql .= ", profile_picture = :profile_picture";
        }

        $sql .= " WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateData);

        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: hr_profile.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .profile-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }

        .form-label {
            font-weight: 500;
        }

        .social-media-input {
            margin-bottom: 10px;
        }

        .education-entry, .experience-entry {
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Personal Information -->
                    <div class="profile-section">
                        <h3 class="mb-4">Personal Information</h3>
                        <div class="row">
                            <div class="col-md-3 text-center mb-4">
                                <img src="<?php echo !empty($user['profile_picture']) && file_exists($user['profile_picture']) 
                                           ? $user['profile_picture'] 
                                           : 'assets/default-profile.png'; ?>" 
                                     class="profile-picture mb-3" alt="Profile Picture">
                                <input type="file" class="form-control" name="profile_picture" accept="image/*">
                            </div>
                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="dob" 
                                               value="<?php echo $user['dob']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="profile-section">
                        <h3 class="mb-4">Professional Information</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position" 
                                       value="<?php echo htmlspecialchars($user['position']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Designation</label>
                                <input type="text" class="form-control" name="designation" 
                                       value="<?php echo htmlspecialchars($user['designation']); ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Skills</label>
                                <input type="text" class="form-control" name="skills" 
                                       value="<?php echo htmlspecialchars($user['skills']); ?>" 
                                       placeholder="Separate skills with commas">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="profile-section">
                        <h3 class="mb-4">Contact Information</h3>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" 
                                       value="<?php echo htmlspecialchars($user['city']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state" 
                                       value="<?php echo htmlspecialchars($user['state']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" 
                                       value="<?php echo htmlspecialchars($user['country']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" 
                                       value="<?php echo htmlspecialchars($user['postal_code']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="profile-section">
                        <h3 class="mb-4">Emergency Contact</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name" 
                                       value="<?php echo htmlspecialchars($user['emergency_contact_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone" 
                                       value="<?php echo htmlspecialchars($user['emergency_contact_phone']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="profile-section">
                        <h3 class="mb-4">Additional Information</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo $user['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $user['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $user['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Marital Status</label>
                                <select class="form-select" name="marital_status">
                                    <option value="">Select Status</option>
                                    <option value="single" <?php echo $user['marital_status'] === 'single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="married" <?php echo $user['marital_status'] === 'married' ? 'selected' : ''; ?>>Married</option>
                                    <option value="divorced" <?php echo $user['marital_status'] === 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="widowed" <?php echo $user['marital_status'] === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nationality</label>
                                <input type="text" class="form-control" name="nationality" 
                                       value="<?php echo htmlspecialchars($user['nationality']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Blood Group</label>
                                <select class="form-select" name="blood_group">
                                    <option value="">Select Blood Group</option>
                                    <?php
                                    $blood_groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                                    foreach ($blood_groups as $bg) {
                                        echo "<option value=\"$bg\"" . 
                                             ($user['blood_group'] === $bg ? ' selected' : '') . 
                                             ">$bg</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Languages Known</label>
                                <input type="text" class="form-control" name="languages" 
                                       value="<?php echo htmlspecialchars($user['languages']); ?>" 
                                       placeholder="Separate languages with commas">
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary px-5">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelector('input[name="profile_picture"]').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.querySelector('.profile-picture').src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    </script>
</body>
</html> 