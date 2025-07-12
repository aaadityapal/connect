<?php
// Script to reset update preferences for all users
// This will make the update modal show again for everyone

require_once '../config/db_connect.php';

// Get the current version (should match what's in the check/save files)
$current_update_version = '1.0';

// Option 1: Reset for a specific version (modal will show again for this version)
$stmt = $conn->prepare("DELETE FROM user_update_preferences WHERE update_version = ?");
$stmt->bind_param("s", $current_update_version);

// Option 2: Reset for all versions (uncomment to use)
// $stmt = $conn->prepare("DELETE FROM user_update_preferences");

if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    echo "Successfully reset preferences for {$affected_rows} users.\n";
    echo "The update modal will now show again for these users.\n";
} else {
    echo "Error resetting preferences: " . $stmt->error . "\n";
}

$conn->close(); 