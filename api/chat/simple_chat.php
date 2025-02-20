<?php
session_start();
require_once '../../config/db_connect.php';

ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', '300');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function getMessages($conn, $user_id, $other_user_id) {
    $query = "SELECT 
                m.*,
                sender.username as sender_name,
                receiver.username as receiver_name
              FROM chat_messages m
              JOIN users sender ON m.sender_id = sender.id
              JOIN users receiver ON m.receiver_id = receiver.id
              WHERE (m.sender_id = ? AND m.receiver_id = ?)
              OR (m.sender_id = ? AND m.receiver_id = ?)
              ORDER BY m.created_at ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Add file URL for file messages
    foreach ($messages as &$message) {
        if ($message['is_file']) {
            $message['file_url'] = '../../' . $message['file_path'];
        }
    }
    
    return $messages;
}

function sendMessage($conn, $sender_id, $receiver_id, $message) {
    $query = "INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    return $stmt->execute();
}

function getUsers($conn, $current_user_id) {
    try {
        $today = date('Y-m-d');
        $query = "SELECT 
                    u.id, 
                    u.username,
                    COALESCE(us.is_online, FALSE) as is_online,
                    COALESCE(us.last_seen, CURRENT_TIMESTAMP) as last_seen,
                    (SELECT COUNT(*) FROM chat_messages 
                     WHERE receiver_id = ? AND sender_id = u.id AND is_read = 0) as unread_count,
                    (SELECT message FROM chat_messages 
                     WHERE (sender_id = ? AND receiver_id = u.id) 
                     OR (sender_id = u.id AND receiver_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT 
                        CASE 
                            WHEN punch_in IS NOT NULL AND punch_out IS NULL THEN 'online'
                            WHEN punch_in IS NOT NULL AND punch_out IS NOT NULL THEN 'offline'
                            ELSE 'offline'
                        END
                     FROM attendance 
                     WHERE user_id = u.id 
                     AND date = ? 
                     ORDER BY id DESC LIMIT 1) as attendance_status,
                    (SELECT punch_in 
                     FROM attendance 
                     WHERE user_id = u.id 
                     AND date = ? 
                     ORDER BY id DESC LIMIT 1) as punch_in_time
                  FROM users u
                  LEFT JOIN user_status us ON u.id = us.user_id
                  WHERE u.id != ?
                  ORDER BY attendance_status = 'online' DESC, us.last_seen DESC";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiissi", 
            $current_user_id, 
            $current_user_id, 
            $current_user_id, 
            $today,
            $today,
            $current_user_id
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getUsers: " . $e->getMessage());
        throw $e;
    }
}

function markAsRead($conn, $sender_id, $receiver_id) {
    $query = "UPDATE chat_messages SET is_read = TRUE 
              WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $sender_id, $receiver_id);
    return $stmt->execute();
}

function getTotalUnreadCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as total_unread 
              FROM chat_messages 
              WHERE receiver_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total_unread'];
}

function createGroup($conn, $creator_id, $group_name, $member_ids) {
    try {
        $conn->begin_transaction();

        // Create group
        $query = "INSERT INTO chat_groups (name, created_by) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $group_name, $creator_id);
        $stmt->execute();
        $group_id = $conn->insert_id;

        // Add creator as admin
        $query = "INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $group_id, $creator_id);
        $stmt->execute();

        // Add other members
        $query = "INSERT INTO group_members (group_id, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        foreach ($member_ids as $member_id) {
            if ($member_id != $creator_id) {
                $stmt->bind_param("ii", $group_id, $member_id);
                $stmt->execute();
            }
        }

        $conn->commit();
        return $group_id;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function getGroups($conn, $user_id) {
    $query = "SELECT 
                g.*,
                gm.role,
                (SELECT COUNT(*) FROM group_messages gms
                 LEFT JOIN group_message_status gst 
                    ON gms.id = gst.message_id AND gst.user_id = ?
                 WHERE gms.group_id = g.id AND 
                    (gst.is_read = FALSE OR gst.is_read IS NULL)) as unread_count
              FROM chat_groups g
              JOIN group_members gm ON g.id = gm.group_id
              WHERE gm.user_id = ?
              ORDER BY g.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getGroupMessages($conn, $group_id, $user_id) {
    // Verify user is group member
    $check = "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?";
    $stmt = $conn->prepare($check);
    $stmt->bind_param("ii", $group_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("Unauthorized");
    }

    $query = "SELECT 
                gm.*,
                u.username as sender_name
              FROM group_messages gm
              JOIN users u ON gm.sender_id = u.id
              WHERE gm.group_id = ?
              ORDER BY gm.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function sendFileMessage($conn, $sender_id, $receiver_id, $file_data) {
    try {
        error_log("Starting sendFileMessage function");
        error_log("File data: " . print_r($file_data, true));

        // Validate file size (10MB limit)
        $max_size = 10 * 1024 * 1024; // 10MB in bytes
        if ($file_data['size'] > $max_size) {
            throw new Exception("File size exceeds limit (10MB)");
        }

        // Validate file type
        $allowed_types = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        $file_type = $file_data['type'];
        error_log("File type: " . $file_type);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Invalid file type: " . $file_type);
        }

        // Generate safe filename
        $file_ext = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        $unique_filename = uniqid('chat_', true) . '.' . $file_ext;
        $upload_path = '../../uploads/chat_files/';
        $full_path = $upload_path . $unique_filename;

        error_log("Attempting to move file to: " . $full_path);

        // Move the uploaded file
        if (!move_uploaded_file($file_data['tmp_name'], $full_path)) {
            error_log("Failed to move uploaded file. Upload error code: " . $file_data['error']);
            throw new Exception("Failed to save uploaded file");
        }

        // Insert into database
        $query = "INSERT INTO chat_messages (sender_id, receiver_id, message, file_name, file_path, is_file) 
                 VALUES (?, ?, ?, ?, ?, 1)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Database prepare error: " . $conn->error);
            throw new Exception("Database error");
        }

        $file_path = 'uploads/chat_files/' . $unique_filename;
        $original_filename = $file_data['name'];
        
        $stmt->bind_param("iisss", 
            $sender_id, 
            $receiver_id, 
            $original_filename, 
            $file_path,
            $original_filename
        );

        if (!$stmt->execute()) {
            error_log("Database execute error: " . $stmt->error);
            throw new Exception("Failed to save file information to database");
        }

        error_log("File upload successful");
        return true;

    } catch (Exception $e) {
        error_log("Error in sendFileMessage: " . $e->getMessage());
        throw $e;
    }
}

try {
    switch ($action) {
        case 'get_users':
            $users = getUsers($conn, $user_id);
            $total_unread = getTotalUnreadCount($conn, $user_id);
            echo json_encode([
                'users' => $users,
                'total_unread' => $total_unread
            ]);
            break;
            
        case 'get_messages':
            $other_user_id = $_POST['other_user_id'] ?? 0;
            echo json_encode(getMessages($conn, $user_id, $other_user_id));
            break;
            
        case 'send_message':
            $receiver_id = $_POST['receiver_id'] ?? 0;
            $message = $_POST['message'] ?? '';
            $success = sendMessage($conn, $user_id, $receiver_id, $message);
            echo json_encode(['success' => $success]);
            break;
            
        case 'mark_read':
            $sender_id = $_POST['sender_id'] ?? 0;
            echo json_encode(['success' => markAsRead($conn, $sender_id, $user_id)]);
            break;
            
        case 'create_group':
            $group_name = $_POST['group_name'] ?? '';
            $member_ids = json_decode($_POST['member_ids'] ?? '[]');
            if (!$group_name || empty($member_ids)) {
                throw new Exception('Invalid group data');
            }
            $group_id = createGroup($conn, $user_id, $group_name, $member_ids);
            echo json_encode(['success' => true, 'group_id' => $group_id]);
            break;
            
        case 'get_groups':
            $groups = getGroups($conn, $user_id);
            echo json_encode($groups);
            break;
            
        case 'get_group_messages':
            $group_id = $_POST['group_id'] ?? 0;
            $messages = getGroupMessages($conn, $group_id, $user_id);
            echo json_encode($messages);
            break;
            
        case 'send_group_message':
            $group_id = $_POST['group_id'] ?? 0;
            $message = $_POST['message'] ?? '';
            
            // Verify user is group member
            $check = "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?";
            $stmt = $conn->prepare($check);
            $stmt->bind_param("ii", $group_id, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception("Unauthorized");
            }
            
            // Insert message
            $query = "INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iis", $group_id, $user_id, $message);
            $success = $stmt->execute();
            
            echo json_encode(['success' => $success]);
            break;
            
        case 'get_group_members':
            $group_id = $_POST['group_id'] ?? 0;
            
            $query = "SELECT u.username, gm.role
                      FROM group_members gm
                      JOIN users u ON gm.user_id = u.id
                      WHERE gm.group_id = ?
                      ORDER BY gm.role = 'admin' DESC, u.username";
                      
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $group_id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            break;
            
        case 'edit_group':
            $group_id = $_POST['group_id'] ?? 0;
            $group_name = $_POST['group_name'] ?? '';
            
            // Verify user is group admin
            $check = "SELECT 1 FROM group_members 
                      WHERE group_id = ? AND user_id = ? AND role = 'admin'";
            $stmt = $conn->prepare($check);
            $stmt->bind_param("ii", $group_id, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception("Unauthorized: Only admins can edit groups");
            }
            
            // Update group name
            $query = "UPDATE chat_groups SET name = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $group_name, $group_id);
            $success = $stmt->execute();
            
            echo json_encode(['success' => $success]);
            break;
            
        case 'delete_group':
            $group_id = $_POST['group_id'] ?? 0;
            
            try {
                // Verify user is group admin
                $check = "SELECT 1 FROM group_members 
                          WHERE group_id = ? AND user_id = ? AND role = 'admin'";
                $stmt = $conn->prepare($check);
                $stmt->bind_param("ii", $group_id, $user_id);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows === 0) {
                    throw new Exception("Unauthorized: Only admins can delete groups");
                }
                
                // Start transaction
                $conn->begin_transaction();
                
                // Delete group message status records first
                $query1 = "DELETE FROM group_message_status 
                           WHERE message_id IN (SELECT id FROM group_messages WHERE group_id = ?)";
                $stmt = $conn->prepare($query1);
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                
                // Delete group messages
                $query2 = "DELETE FROM group_messages WHERE group_id = ?";
                $stmt = $conn->prepare($query2);
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                
                // Delete group members
                $query3 = "DELETE FROM group_members WHERE group_id = ?";
                $stmt = $conn->prepare($query3);
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                
                // Finally delete the group
                $query4 = "DELETE FROM chat_groups WHERE id = ?";
                $stmt = $conn->prepare($query4);
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode(['success' => true]);
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                error_log("Error deleting group: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'send_file':
            try {
                error_log("=== File Upload Debug ===");
                error_log("POST data: " . print_r($_POST, true));
                error_log("FILES data: " . print_r($_FILES, true));
                
                if (!isset($_FILES['file'])) {
                    throw new Exception('No file data received');
                }

                $receiver_id = $_POST['receiver_id'] ?? null;
                if (!$receiver_id) {
                    throw new Exception('No receiver ID specified');
                }

                // Check upload directory
                $upload_dir = '../../uploads/chat_files/';
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        error_log("Failed to create upload directory: " . $upload_dir);
                        throw new Exception('Failed to create upload directory');
                    }
                }

                // Check directory permissions
                error_log("Upload directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
                
                // Verify file upload
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    error_log("File upload error code: " . $_FILES['file']['error']);
                    throw new Exception('File upload failed with error code: ' . $_FILES['file']['error']);
                }

                $success = sendFileMessage($conn, $user_id, $receiver_id, $_FILES['file']);
                echo json_encode([
                    'success' => $success,
                    'message' => 'File uploaded successfully'
                ]);
                
            } catch (Exception $e) {
                error_log("File upload error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Update last seen when the user leaves
register_shutdown_function(function() use ($conn, $user_id) {
    $update_status = "UPDATE user_status SET is_online = FALSE WHERE user_id = ?";
    $stmt = $conn->prepare($update_status);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}); 