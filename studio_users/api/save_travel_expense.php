<?php
session_start();
include('../../config.php'); // Adjust path as needed

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['status' => 'success', 'message' => 'Expenses saved successfully'];

// Create upload directory if not exists
$target_dir = "../../uploads/travel_expenses/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// We expect data as a JSON string and files as standard $_FILES
// However, since we might have multiple expenses, it's easier to send them one by one or as a batch.
// If sending as a batch with files, we need careful mapping.

$data_json = $_POST['data'] ?? '[]';
$expenses = json_decode($data_json, true);

if (empty($expenses)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit();
}

$conn->begin_transaction();

try {
    foreach ($expenses as $index => $exp) {
        $stmt = $conn->prepare("INSERT INTO travel_expenses (user_id, date, purpose, from_location, to_location, mode_of_transport, distance_km, amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param(
            "isssssdds",
            $user_id,
            $exp['date'],
            $exp['purpose'],
            $exp['from'],
            $exp['to'],
            $exp['mode'],
            $exp['distance'],
            $exp['amount'],
            $exp['notes']
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert expense " . ($index + 1));
        }

        $expense_id = $conn->insert_id;

        // Handle Files for this specific expense
        // Key format in FormData: "meter_start_0", "meter_end_0", "bill_0", etc.
        $file_keys = [
            'meter_start' => "meter_start_$index",
            'meter_end' => "meter_end_$index",
            'bill' => "bill_$index"
        ];

        foreach ($file_keys as $type => $input_name) {
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == 0) {
                $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                $filename = "exp_" . $expense_id . "_" . $type . "_" . time() . "." . $ext;
                $target_path = $target_dir . $filename;

                if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_path)) {
                    $relative_path = "uploads/travel_expenses/" . $filename;
                    $att_stmt = $conn->prepare("INSERT INTO travel_expense_attachments (expense_id, file_path, file_type) VALUES (?, ?, ?)");
                    $att_stmt->bind_param("iss", $expense_id, $relative_path, $type);
                    $att_stmt->execute();
                }
            }
        }
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
?>