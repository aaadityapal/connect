<?php
// This file fetches site update details and returns them as JSON
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Get update ID from URL parameter
$update_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($update_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid update ID']);
    exit();
}

// Prepare response data
$response = [
    'success' => true,
    'data' => []
];

try {
    // Get main update info
    $update_query = "SELECT su.*, u.username as created_by_name 
                    FROM site_updates su 
                    LEFT JOIN users u ON su.created_by = u.id 
                    WHERE su.id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $update_id);
    $update_stmt->execute();
    $update_result = $update_stmt->get_result();
    
    if ($update_result && $update_result->num_rows > 0) {
        $response['data'] = $update_result->fetch_assoc();
        
        // Format the date for display
        if (isset($response['data']['update_date'])) {
            $response['data']['update_date'] = date('F j, Y', strtotime($response['data']['update_date']));
        }
        
        // Get work progress items
        $work_progress_query = "SELECT wp.* FROM work_progress wp WHERE wp.site_update_id = ?";
        $work_progress_stmt = $conn->prepare($work_progress_query);
        $work_progress_stmt->bind_param("i", $update_id);
        $work_progress_stmt->execute();
        $work_progress_result = $work_progress_stmt->get_result();
        
        $work_progress = [];
        while ($wp_row = $work_progress_result->fetch_assoc()) {
            // Get media for this work progress item
            $media_query = "SELECT id, work_progress_id, file_name, file_path, file_type FROM work_progress_media WHERE work_progress_id = ?";
            $media_stmt = $conn->prepare($media_query);
            $media_stmt->bind_param("i", $wp_row['id']);
            $media_stmt->execute();
            $media_result = $media_stmt->get_result();
            
            $media = [];
            while ($media_row = $media_result->fetch_assoc()) {
                // Make sure file_path is relative to root directory
                if (!empty($media_row['file_path'])) {
                    // Remove any leading '../' or './' from the path
                    $media_row['file_path'] = preg_replace('/^(\.\.\/|\.\/)+/', '', $media_row['file_path']);
                    
                    // If path doesn't start with uploads, prepend it
                    if (strpos($media_row['file_path'], 'uploads/') !== 0) {
                        $media_row['file_path'] = 'uploads/' . $media_row['file_path'];
                    }
                }
                
                $media[] = $media_row;
            }
            
            $wp_row['media'] = $media;
            $work_progress[] = $wp_row;
        }
        
        $response['data']['work_progress'] = $work_progress;
        
        // Get inventory items
        $inventory_query = "SELECT inv.* FROM inventory inv WHERE inv.site_update_id = ?";
        $inventory_stmt = $conn->prepare($inventory_query);
        $inventory_stmt->bind_param("i", $update_id);
        $inventory_stmt->execute();
        $inventory_result = $inventory_stmt->get_result();
        
        $inventory = [];
        while ($inv_row = $inventory_result->fetch_assoc()) {
            // Get media for this inventory item
            $media_query = "SELECT id, inventory_id, file_name, file_path, file_type FROM inventory_media WHERE inventory_id = ?";
            $media_stmt = $conn->prepare($media_query);
            $media_stmt->bind_param("i", $inv_row['id']);
            $media_stmt->execute();
            $media_result = $media_stmt->get_result();
            
            $media = [];
            while ($media_row = $media_result->fetch_assoc()) {
                // Make sure file_path is relative to root directory
                if (!empty($media_row['file_path'])) {
                    // Remove any leading '../' or './' from the path
                    $media_row['file_path'] = preg_replace('/^(\.\.\/|\.\/)+/', '', $media_row['file_path']);
                    
                    // If path doesn't start with uploads, prepend it
                    if (strpos($media_row['file_path'], 'uploads/') !== 0) {
                        $media_row['file_path'] = 'uploads/' . $media_row['file_path'];
                    }
                }
                
                $media[] = $media_row;
            }
            
            $inv_row['media'] = $media;
            $inventory[] = $inv_row;
        }
        
        $response['data']['inventory'] = $inventory;
        
        // Get vendors and their laborers
        $vendors_query = "SELECT v.* FROM vendors v WHERE v.site_update_id = ?";
        $vendors_stmt = $conn->prepare($vendors_query);
        $vendors_stmt->bind_param("i", $update_id);
        $vendors_stmt->execute();
        $vendors_result = $vendors_stmt->get_result();
        
        $vendors = [];
        while ($vendor_row = $vendors_result->fetch_assoc()) {
            // Get laborers for this vendor
            $laborers_query = "SELECT l.* FROM laborers l WHERE l.vendor_id = ?";
            $laborers_stmt = $conn->prepare($laborers_query);
            $laborers_stmt->bind_param("i", $vendor_row['id']);
            $laborers_stmt->execute();
            $laborers_result = $laborers_stmt->get_result();
            
            $laborers = [];
            while ($laborer_row = $laborers_result->fetch_assoc()) {
                $laborers[] = $laborer_row;
            }
            
            $vendor_row['laborers'] = $laborers;
            $vendors[] = $vendor_row;
        }
        
        $response['data']['vendors'] = $vendors;
        
        // Get company laborers
        $company_labours_query = "SELECT cl.* FROM company_labours cl WHERE cl.site_update_id = ?";
        $company_labours_stmt = $conn->prepare($company_labours_query);
        $company_labours_stmt->bind_param("i", $update_id);
        $company_labours_stmt->execute();
        $company_labours_result = $company_labours_stmt->get_result();
        
        $company_labours = [];
        while ($cl_row = $company_labours_result->fetch_assoc()) {
            $company_labours[] = $cl_row;
        }
        
        $response['data']['company_labours'] = $company_labours;
        
        // Get travel expenses
        $travel_expenses_query = "SELECT te.* FROM travel_expenses te WHERE te.site_update_id = ?";
        $travel_expenses_stmt = $conn->prepare($travel_expenses_query);
        $travel_expenses_stmt->bind_param("i", $update_id);
        $travel_expenses_stmt->execute();
        $travel_expenses_result = $travel_expenses_stmt->get_result();
        
        $travel_expenses = [];
        while ($te_row = $travel_expenses_result->fetch_assoc()) {
            $travel_expenses[] = $te_row;
        }
        
        $response['data']['travel_expenses'] = $travel_expenses;
        
        // Get beverages
        $beverages_query = "SELECT b.* FROM beverages b WHERE b.site_update_id = ?";
        $beverages_stmt = $conn->prepare($beverages_query);
        $beverages_stmt->bind_param("i", $update_id);
        $beverages_stmt->execute();
        $beverages_result = $beverages_stmt->get_result();
        
        $beverages = [];
        while ($b_row = $beverages_result->fetch_assoc()) {
            $beverages[] = $b_row;
        }
        
        $response['data']['beverages'] = $beverages;
        
        // Get grand total from summary view
        $summary_query = "SELECT grand_total FROM summary_view WHERE update_id = ?";
        $summary_stmt = $conn->prepare($summary_query);
        $summary_stmt->bind_param("i", $update_id);
        $summary_stmt->execute();
        $summary_result = $summary_stmt->get_result();
        
        if ($summary_result && $summary_result->num_rows > 0) {
            $summary_row = $summary_result->fetch_assoc();
            $response['data']['grand_total'] = $summary_row['grand_total'];
        } else {
            $response['data']['grand_total'] = 0;
        }
        
    } else {
        throw new Exception("Site update not found");
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 