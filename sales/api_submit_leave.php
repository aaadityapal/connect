<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// Database connection
require_once '../config/db_connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $user_id = $_SESSION['user_id'];
    $leave_type_id = isset($_POST['leave_type']) ? intval($_POST['leave_type']) : 0;
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $contact_during_leave = isset($_POST['contact_during_leave']) ? trim($_POST['contact_during_leave']) : '';
    $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';
    $duration_type = isset($_POST['duration_type']) ? $_POST['duration_type'] : 'full_day'; // full_day, half_day
    $day_type = isset($_POST['day_type']) ? $_POST['day_type'] : null; // first_half, second_half (for half day)

    // Validate required fields
    if (empty($leave_type_id) || empty($start_date) || empty($end_date) || empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }

    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    // Check if start date is more than 15 days in the past
    $fifteenDaysAgo = clone $today;
    $fifteenDaysAgo->modify('-15 days');

    if ($start < $fifteenDaysAgo) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Leave start date cannot be more than 15 days in the past. Please select a date from ' . $fifteenDaysAgo->format('Y-m-d') . ' onwards.'
        ]);
        exit;
    }

    if ($end < $start) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'End date cannot be before start date']);
        exit;
    }

    // Calculate duration (number of days)
    $interval = $start->diff($end);
    $duration = $interval->days + 1;

    // If half day, duration is 0.5
    if ($duration_type === 'half_day') {
        $duration = 0.5;
    }

    // Verify leave type exists and is active
    $stmt = $pdo->prepare("SELECT id, name, max_days FROM leave_types WHERE id = ? AND status = 'active'");
    $stmt->execute([$leave_type_id]);
    $leave_type = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave_type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid leave type selected']);
        exit;
    }

    // Check if user has sufficient balance (optional - can be enforced or just warned)
    // This would require a user_leave_balance table
    // For now, we'll allow the request and let managers/HR decide

    // Handle file upload if present
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/leave_attachments/';

        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $file_name = 'leave_' . $user_id . '_' . time() . '.' . $file_extension;
            $attachment_path = $upload_dir . $file_name;

            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
                $attachment_path = null;
            }
        }
    }

    // Insert leave request
    $sql = "INSERT INTO leave_request 
            (user_id, leave_type, start_date, end_date, reason, duration, 
             status, created_at, updated_at, updated_by) 
            VALUES 
            (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW(), ?)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $user_id,
        $leave_type_id,
        $start_date,
        $end_date,
        $reason,
        $duration,
        $user_id
    ]);

    if ($result) {
        $leave_request_id = $pdo->lastInsertId();

        // Store additional contact information in a separate table or as JSON
        // For now, we'll add it to the reason field or create a separate metadata table

        // Log the activity (optional - won't fail if table doesn't exist)
        try {
            $log_sql = "INSERT INTO activity_logs (user_id, action, description, created_at) 
                       VALUES (?, 'leave_request_submitted', ?, NOW())";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([
                $user_id,
                "Leave request submitted for {$duration} day(s) from {$start_date} to {$end_date}"
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to log activity (table may not exist): " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully! You will be notified once it is reviewed.',
            'leave_request_id' => $leave_request_id,
            'duration' => $duration,
            'leave_type' => $leave_type['name']
        ]);

        // -------------------------------------------------------------------------
        // WhatsApp Notification Logic
        // -------------------------------------------------------------------------
        try {
            require_once '../whatsapp/WhatsAppService.php';
            $waService = new WhatsAppService();

            // 1. Fetch User Details
            $uStmt = $pdo->prepare("SELECT username, unique_id, phone, role FROM users WHERE id = ?");
            $uStmt->execute([$user_id]);
            $user = $uStmt->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['phone'])) {
                $userName = $user['username'] ?: $user['unique_id'];
                $startDateStr = date('d M Y', strtotime($start_date));
                $endDateStr = date('d M Y', strtotime($end_date));

                // Formulate Leave Type Name and Admin details
                $leaveTypeName = $leave_type['name'];
                $conditionalLine = "⏰ Leave Time: Full Day";
                $adminLeaveType = $leaveTypeName;

                // Handle Half Day Display
                if ($duration_type === 'half_day') {
                    $conditionalLine = "⏰ Leave Time: Half Day";
                    $adminLeaveType .= " (Half Day)";

                    if (!empty($day_type)) {
                        $timeDesc = ($day_type === 'first_half') ? 'Morning Half' : 'Afternoon Half';
                        $conditionalLine = "⏰ Leave Time: " . $timeDesc;
                        $adminLeaveType = $leaveTypeName . " ($timeDesc)";
                    }
                }

                // A. Send Confirmation to Employee
                $waService->sendTemplateMessage(
                    $user['phone'],
                    'leave_submission_confirmation',
                    'en_US',
                    [
                        $userName,        // {{1}}
                        $startDateStr,    // {{2}}
                        $endDateStr,      // {{3}}
                        $leaveTypeName,   // {{4}}
                        $conditionalLine  // {{5}}
                    ]
                );

                // B. Send Notification to admins (HR + Manager)
                // Determine Manager based on role (Senior Manager Site/Studio)
                $siteRoles = [
                    'Site Supervisor',
                    'Site Coordinator',
                    'Purchase Manager',
                    'Maid Back Office',
                    'Social Media Marketing',
                    'Sales',
                    'Graphic Designer'
                ];

                $targetRoles = ['HR'];
                if (in_array($user['role'], $siteRoles)) {
                    $targetRoles[] = 'Senior Manager (Site)';
                } else {
                    $targetRoles[] = 'Senior Manager (Studio)';
                }

                $inQuery = implode(',', array_fill(0, count($targetRoles), '?'));
                $adminStmt = $pdo->prepare("SELECT username, phone FROM users WHERE role IN ($inQuery)");
                $adminStmt->execute($targetRoles);

                while ($admin = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($admin['phone'])) {
                        $waService->sendTemplateMessage(
                            $admin['phone'],
                            'admin_leave_action_required',
                            'en_US',
                            [
                                $admin['username'], // {{1}} Manager Name
                                $userName,          // {{2}} Employee Name
                                $startDateStr,      // {{3}} Start
                                $endDateStr,        // {{4}} End
                                $adminLeaveType,    // {{5}} Type (with time info)
                                $reason             // {{6}} Reason
                            ]
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("Sales Leave Notification Error: " . $e->getMessage());
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit leave request. Please try again.']);
    }

} catch (PDOException $e) {
    error_log("Database error in api_submit_leave.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please contact support.']);
} catch (Exception $e) {
    error_log("Error in api_submit_leave.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>