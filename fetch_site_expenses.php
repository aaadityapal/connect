<?php
// Include database connection
require_once 'config.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'expenses' => []
];

try {
    // Prepare the query to fetch expenses with related data
    $query = "
        SELECT 
            e.expense_id,
            p.project_name,
            e.amount,
            m.mode_name AS payment_mode,
            t.type_name AS payment_type,
            e.expense_datetime,
            s.staff_name AS payment_access_by,
            e.status,
            e.receipt_file_path,
            e.created_at,
            CASE 
                WHEN er.rental_id IS NOT NULL THEN CONCAT('Equipment: ', er.equipment_name)
                WHEN vp.vendor_payment_id IS NOT NULL THEN CONCAT('Vendor: ', v.vendor_name)
                ELSE NULL
            END AS additional_info
        FROM se_expenses e
        JOIN se_projects p ON e.project_id = p.project_id
        JOIN se_payment_modes m ON e.payment_mode_id = m.mode_id
        JOIN se_payment_types t ON e.payment_type_id = t.type_id
        JOIN se_staff s ON e.payment_access_by = s.staff_id
        LEFT JOIN se_equipment_rentals er ON e.expense_id = er.expense_id
        LEFT JOIN se_vendor_payments vp ON e.expense_id = vp.expense_id
        LEFT JOIN se_vendors v ON vp.vendor_id = v.vendor_id
        ORDER BY e.created_at DESC
        LIMIT 50
    ";
    
    // Execute query
    $result = $conn->query($query);
    
    if ($result) {
        $expenses = [];
        
        while ($row = $result->fetch_assoc()) {
            // Format datetime for display
            $dateTime = new DateTime($row['expense_datetime']);
            $formattedDate = $dateTime->format('M d, h:i A');
            
            // Format amount with currency symbol
            $formattedAmount = '$' . number_format($row['amount'], 2);
            
            // Add formatted data to the row
            $row['formatted_date'] = $formattedDate;
            $row['formatted_amount'] = $formattedAmount;
            
            // Add status class for styling
            $row['status_class'] = strtolower($row['status']);
            
            // Add row to expenses array
            $expenses[] = $row;
        }
        
        // Set success response
        $response['success'] = true;
        $response['expenses'] = $expenses;
    } else {
        throw new Exception("Query failed: " . $conn->error);
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode($response); 