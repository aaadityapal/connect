<?php
session_start();
require_once 'config/db_connect.php';

// Test user ID (you can modify this as needed)
$_SESSION['user_id'] = 1; // Assuming user ID 1 exists

// Function to create test data
function createTestData($pdo) {
    try {
        // First check if shifts exist
        $checkShifts = $pdo->query("SELECT id, shift_name FROM shifts")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($checkShifts)) {
            // Insert test shifts
            $shifts = [
                ['Morning Shift', '09:00:00', '18:00:00'],
                ['Afternoon Shift', '14:00:00', '23:00:00'],
                ['Night Shift', '23:00:00', '08:00:00']
            ];

            $stmt = $pdo->prepare("INSERT INTO shifts (shift_name, start_time, end_time) VALUES (?, ?, ?)");
            foreach ($shifts as $shift) {
                $stmt->execute($shift);
            }
            echo "Test shifts created successfully.<br>";
            
            // Fetch the newly created shifts
            $checkShifts = $pdo->query("SELECT id, shift_name FROM shifts")->fetchAll(PDO::FETCH_ASSOC);
        }

        // Display available shifts
        echo "Available shifts:<br>";
        foreach ($checkShifts as $shift) {
            echo "ID: {$shift['id']} - Name: {$shift['shift_name']}<br>";
        }

        // Check if user already has a shift assigned
        $currentDate = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_shifts WHERE user_id = ? AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)");
        $stmt->execute([$_SESSION['user_id'], $currentDate, $currentDate]);

        if ($stmt->fetchColumn() == 0) {
            // Assign the first shift to user
            $firstShiftId = $checkShifts[0]['id'];
            
            $stmt = $pdo->prepare("INSERT INTO user_shifts (user_id, shift_id, weekly_offs, effective_from) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $firstShiftId,
                'Sunday',
                $currentDate
            ]);
            
            echo "Test shift assigned to user.<br>";
        } else {
            echo "User already has a shift assigned.<br>";
        }

        return true;
    } catch (PDOException $e) {
        echo "Error creating test data: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Function to test shift data retrieval
function testShiftRetrieval($pdo) {
    try {
        $userId = $_SESSION['user_id'];
        $currentDate = date('Y-m-d');
        
        $query = "SELECT s.shift_name, s.start_time, s.end_time, us.weekly_offs
                 FROM shifts s
                 INNER JOIN user_shifts us ON us.shift_id = s.id
                 WHERE us.user_id = ?
                 AND us.effective_from <= ?
                 AND (us.effective_to IS NULL OR us.effective_to >= ?)";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $currentDate, $currentDate]);

        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($shift) {
            echo "<h3>Shift Details Found:</h3>";
            echo "Shift Name: " . htmlspecialchars($shift['shift_name']) . "<br>";
            echo "Start Time: " . $shift['start_time'] . "<br>";
            echo "End Time: " . $shift['end_time'] . "<br>";
            echo "Weekly Offs: " . htmlspecialchars($shift['weekly_offs']) . "<br>";
            
            // Check if today is a weekly off
            $currentDay = date('l'); // Gets the current day name
            if (strpos($shift['weekly_offs'], $currentDay) !== false) {
                echo "<strong>Note: Today is a weekly off.</strong><br>";
            } else {
                // Calculate remaining time
                $now = new DateTime();
                $endTime = new DateTime($currentDate . ' ' . $shift['end_time']);
                
                if ($endTime < $now) {
                    $endTime->modify('+1 day');
                }
                
                $remaining = $now->diff($endTime);
                echo "Remaining Time: " . $remaining->format('%H:%I:%S') . "<br>";
            }
        } else {
            echo "No active shift assignment found.<br>";
        }

        return true;
    } catch (PDOException $e) {
        echo "Error testing shift retrieval: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Display current database state
function displayDatabaseState($pdo) {
    try {
        echo "<h3>Current Database State:</h3>";
        
        echo "<h4>Shifts Table:</h4>";
        $shifts = $pdo->query("SELECT * FROM shifts")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($shifts);
        echo "</pre>";

        echo "<h4>User Shifts Table:</h4>";
        $userShifts = $pdo->query("SELECT * FROM user_shifts WHERE user_id = " . $_SESSION['user_id'])->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($userShifts);
        echo "</pre>";
    } catch (PDOException $e) {
        echo "Error fetching database state: " . $e->getMessage();
    }
}

// Run tests
echo "<h2>Running Shift System Tests</h2>";

echo "<h3>Step 1: Creating Test Data</h3>";
if (createTestData($pdo)) {
    echo "Test data creation completed.<br>";
} else {
    echo "Failed to create test data.<br>";
}

echo "<h3>Step 2: Testing Shift Retrieval</h3>";
if (testShiftRetrieval($pdo)) {
    echo "Shift retrieval test completed.<br>";
} else {
    echo "Failed to test shift retrieval.<br>";
}

// Display current database state
displayDatabaseState($pdo);

// Test the actual get_shift_data.php endpoint
echo "<h3>Step 3: Testing API Endpoint</h3>";
echo "Making request to get_shift_data.php...<br>";

$ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/get_shift_data.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "API Response (HTTP $httpCode):<br>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        line-height: 1.6;
    }
    h2 {
        color: #333;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }
    h3 {
        color: #666;
        margin-top: 20px;
    }
    h4 {
        color: #888;
        margin-top: 15px;
    }
    pre {
        background: #f5f5f5;
        padding: 10px;
        border-radius: 4px;
        overflow-x: auto;
    }
    strong {
        color: #dc3545;
    }
</style> 