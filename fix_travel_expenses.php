<?php
// This script will fix the travel expenses functionality

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to use this script.");
}

// Include database connection
require_once 'config/db_connect.php';

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Check if travel_expenses table exists
if (!tableExists($conn, 'travel_expenses')) {
    echo "Creating travel_expenses table...<br>";
    
    // Create travel_expenses table
    $sql = "
    CREATE TABLE IF NOT EXISTS travel_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        purpose VARCHAR(255) NOT NULL,
        mode_of_transport VARCHAR(50) NOT NULL,
        from_location VARCHAR(255) NOT NULL,
        to_location VARCHAR(255) NOT NULL,
        travel_date DATE NOT NULL,
        distance DECIMAL(10,2) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        notes TEXT,
        bill_file_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($sql) === TRUE) {
        echo "Table travel_expenses created successfully.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads/bills';
if (!file_exists($uploadsDir)) {
    if (mkdir($uploadsDir, 0755, true)) {
        echo "Created uploads directory: $uploadsDir<br>";
    } else {
        echo "Failed to create uploads directory: $uploadsDir<br>";
    }
}

// Create test data
echo "Creating test data...<br>";

// Insert some test data
$user_id = $_SESSION['user_id'];

// Check if user already has travel expenses
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM travel_expenses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo "User already has " . $row['count'] . " travel expenses. Skipping test data creation.<br>";
} else {
    // Insert test data
    $stmt = $conn->prepare("
        INSERT INTO travel_expenses 
        (user_id, purpose, mode_of_transport, from_location, to_location, travel_date, distance, amount, status, notes) 
        VALUES 
        (?, 'Client Meeting', 'Car', 'Office', 'Client Site', '2023-06-15', 25.5, 350.00, 'approved', 'Met with client to discuss project requirements'),
        (?, 'Site Visit', 'Taxi', 'Office', 'Construction Site', '2023-06-16', 30.0, 500.00, 'pending', 'Visited construction site for inspection'),
        (?, 'Team Outing', 'Bus', 'Office', 'Resort', '2023-06-18', 15.0, 150.00, 'pending', 'Team building activity'),
        (?, 'Conference', 'Train', 'City', 'Conference Center', '2023-06-20', 100.0, 800.00, 'rejected', 'Attended industry conference'),
        (?, 'Document Delivery', 'Bike', 'Office', 'Client Office', '2023-06-22', 10.0, 120.00, 'approved', 'Delivered important documents to client')
    ");
    
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    
    if ($stmt->execute()) {
        echo "Test data created successfully.<br>";
    } else {
        echo "Error creating test data: " . $stmt->error . "<br>";
    }
}

echo "Done! <a href='std_travel_expenses.php'>Go back to Travel Expenses page</a>";
?> 