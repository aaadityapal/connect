<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Not authenticated"]);
    exit();
}

// Include database connection
include_once("includes/db_connect.php");

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit();
}

// Get user ID from session
$user_id = $_SESSION["user_id"];

// Get expense ID and validate it belongs to the current user
$expense_id = isset($_POST["expense_id"]) ? intval($_POST["expense_id"]) : 0;

// Verify the expense belongs to the current user
$check_stmt = $conn->prepare("SELECT id FROM travel_expenses WHERE id = ? AND user_id = ? AND status = 'pending'");
$check_stmt->bind_param("ii", $expense_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Expense not found or not editable"]);
    $check_stmt->close();
    exit();
}
$check_stmt->close();

// Get form data
$purpose = isset($_POST["purpose"]) ? $_POST["purpose"] : "";
$from_location = isset($_POST["from_location"]) ? $_POST["from_location"] : "";
$to_location = isset($_POST["to_location"]) ? $_POST["to_location"] : "";
$mode_of_transport = isset($_POST["mode_of_transport"]) ? $_POST["mode_of_transport"] : "";
$travel_date = isset($_POST["travel_date"]) ? $_POST["travel_date"] : "";
$distance = isset($_POST["distance"]) ? floatval($_POST["distance"]) : 0;
$amount = isset($_POST["amount"]) ? floatval($_POST["amount"]) : 0;
$notes = isset($_POST["notes"]) ? $_POST["notes"] : "";

// Validate required fields
if (empty($purpose) || empty($from_location) || empty($to_location) || 
    empty($mode_of_transport) || empty($travel_date) || $distance <= 0 || $amount <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "All required fields must be filled"]);
    exit();
}

// Update the expense in the database
$update_stmt = $conn->prepare("UPDATE travel_expenses SET 
    purpose = ?, 
    from_location = ?, 
    to_location = ?, 
    mode_of_transport = ?, 
    travel_date = ?, 
    distance = ?, 
    amount = ?, 
    notes = ?, 
    updated_at = NOW() 
    WHERE id = ? AND user_id = ? AND status = 'pending'");

$update_stmt->bind_param("sssssddsii", 
    $purpose, 
    $from_location, 
    $to_location, 
    $mode_of_transport, 
    $travel_date, 
    $distance, 
    $amount, 
    $notes, 
    $expense_id, 
    $user_id
);

$success = $update_stmt->execute();
$update_stmt->close();

// Return response
header("Content-Type: application/json");
if ($success) {
    echo json_encode(["success" => true, "message" => "Expense updated successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Error updating expense: " . $conn->error]);
}
?> 