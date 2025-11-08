<?php
// Handle overtime request resubmission
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

session_start();

// Function to send JSON response and exit
function sendResponse($data) {
    // Clear any previous output
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendResponse(['success' => false, 'message' => 'User not logged in']);
    }

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(['success' => false, 'message' => 'Invalid request method']);
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(['success' => false, 'message' => 'Invalid JSON data']);
    }

    // Validate required fields
    if (!isset($input['attendance_id']) || !isset($input['overtime_description'])) {
        sendResponse(['success' => false, 'message' => 'Missing required fields']);
    }

    $attendance_id = intval($input['attendance_id']);
    $work_report = isset($input['work_report']) ? trim($input['work_report']) : '';
    $overtime_description = trim($input['overtime_description']);
    $user_id = $_SESSION['user_id'];
    
    if ($attendance_id <= 0) {
        sendResponse(['success' => false, 'message' => 'Invalid attendance ID']);
    }
    
    if (empty($overtime_description)) {
        sendResponse(['success' => false, 'message' => 'Overtime description is required']);
    }
    
    // Count words in overtime description
    $words = preg_split('/\s+/', trim($overtime_description));
    $wordCount = count(array_filter($words, function($word) {
        return preg_match('/[a-zA-Z0-9]/', $word);
    }));
    
    if ($wordCount < 15) {
        sendResponse(['success' => false, 'message' => 'Overtime description must be at least 15 words']);
    }

    // Include database connection
    if (!file_exists('includes/db_connect.php')) {
        sendResponse(['success' => false, 'message' => 'Database connection file not found']);
    }
    
    include_once('includes/db_connect.php');

    // Check database connection
    if (!isset($pdo) && !isset($conn)) {
        sendResponse(['success' => false, 'message' => 'Database connection failed']);
    }
    
    // Use $pdo if available, otherwise use $conn
    $db = isset($pdo) ? $pdo : $conn;

    // Get the rejected or resubmitted overtime request
    $query = "SELECT a.*, o.status as request_status, o.overtime_description as rejection_reason, o.resubmit_count
              FROM attendance a 
              LEFT JOIN overtime_requests o ON a.id = o.attendance_id 
              WHERE a.id = ? AND a.user_id = ? AND (o.status = 'rejected' OR o.status = 'resubmitted')";
    
    if (isset($pdo)) {
        $stmt = $pdo->prepare($query);
        if (!$stmt) {
            sendResponse(['success' => false, 'message' => 'Database prepare failed: ' . $pdo->error]);
        }
        
        $stmt->execute([$attendance_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            sendResponse(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
        }
        
        $stmt->bind_param("ii", $attendance_id, $user_id);
        if (!$stmt->execute()) {
            sendResponse(['success' => false, 'message' => 'Database execute failed: ' . $stmt->error]);
        }
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    if (!$result) {
        sendResponse(['success' => false, 'message' => 'Rejected or resubmitted overtime request not found']);
    }
    
    // Check resubmit count - limit to 2 resubmissions
    if (isset($result['resubmit_count']) && $result['resubmit_count'] >= 2) {
        sendResponse(['success' => false, 'message' => 'Maximum resubmission limit (2) reached for this overtime request']);
    }

    // Start transaction
    if (isset($pdo)) {
        $pdo->beginTransaction();
    } else {
        $conn->begin_transaction();
    }

    try {
        // Update the existing overtime request and increment resubmit count
        $updateQuery = "UPDATE overtime_requests 
                        SET overtime_description = ?, 
                            status = 'resubmitted', 
                            resubmit_count = resubmit_count + 1,
                            updated_at = NOW() 
                        WHERE attendance_id = ?";
        
        if (isset($pdo)) {
            $updateStmt = $pdo->prepare($updateQuery);
            if (!$updateStmt) {
                throw new Exception('Update prepare failed: ' . $pdo->error);
            }
            
            $updateStmt->execute([$overtime_description, $attendance_id]);
        } else {
            $updateStmt = $conn->prepare($updateQuery);
            if (!$updateStmt) {
                throw new Exception('Update prepare failed: ' . $conn->error);
            }
            
            $updateStmt->bind_param("si", $overtime_description, $attendance_id);
            if (!$updateStmt->execute()) {
                throw new Exception('Update execute failed: ' . $updateStmt->error);
            }
            $updateStmt->close();
        }
        
        // Update work report in attendance table if provided
        if (!empty($work_report)) {
            $workReportQuery = "UPDATE attendance SET work_report = ? WHERE id = ?";
            
            if (isset($pdo)) {
                $workReportStmt = $pdo->prepare($workReportQuery);
                if (!$workReportStmt) {
                    throw new Exception('Work report update prepare failed: ' . $pdo->error);
                }
                
                $workReportStmt->execute([$work_report, $attendance_id]);
            } else {
                $workReportStmt = $conn->prepare($workReportQuery);
                if (!$workReportStmt) {
                    throw new Exception('Work report update prepare failed: ' . $conn->error);
                }
                
                $workReportStmt->bind_param("si", $work_report, $attendance_id);
                if (!$workReportStmt->execute()) {
                    throw new Exception('Work report update execute failed: ' . $workReportStmt->error);
                }
                $workReportStmt->close();
            }
        }

        // Update attendance status to resubmitted
        $statusQuery = "UPDATE attendance SET overtime_status = 'resubmitted' WHERE id = ?";
        
        if (isset($pdo)) {
            $statusStmt = $pdo->prepare($statusQuery);
            if (!$statusStmt) {
                throw new Exception('Status update prepare failed: ' . $pdo->error);
            }
            
            $statusStmt->execute([$attendance_id]);
        } else {
            $statusStmt = $conn->prepare($statusQuery);
            if (!$statusStmt) {
                throw new Exception('Status update prepare failed: ' . $conn->error);
            }
            
            $statusStmt->bind_param("i", $attendance_id);
            if (!$statusStmt->execute()) {
                throw new Exception('Status update execute failed: ' . $statusStmt->error);
            }
            $statusStmt->close();
        }

        // Commit transaction
        if (isset($pdo)) {
            $pdo->commit();
        } else {
            $conn->commit();
        }

        sendResponse([
            'success' => true,
            'message' => 'Overtime request resubmitted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($pdo)) {
            $pdo->rollback();
        } else {
            $conn->rollback();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    // Rollback transaction if still open
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    } elseif (isset($conn) && $conn->errno) {
        $conn->rollback();
    }
    
    sendResponse(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
} catch (Error $e) {
    // Rollback transaction if still open
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    } elseif (isset($conn) && $conn->errno) {
        $conn->rollback();
    }
    
    sendResponse(['success' => false, 'message' => 'Fatal Error: ' . $e->getMessage()]);
}

// This should never be reached due to sendResponse calls
sendResponse(['success' => false, 'message' => 'Unexpected end of script']);
?>