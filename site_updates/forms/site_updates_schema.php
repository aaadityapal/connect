<?php
/**
 * Site Updates Schema Handler
 * 
 * This file contains functions to check and create the necessary database tables
 * and columns for the site updates functionality.
 */

/**
 * Check if the site_updates table exists and create it if it doesn't
 * 
 * @param mysqli $conn Database connection
 * @return bool True if table exists or was created successfully, False otherwise
 */
function ensure_site_updates_table_exists($conn) {
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'site_updates'");
    
    if ($table_check->num_rows == 0) {
        // Table doesn't exist, create it
        $create_table_query = "
            CREATE TABLE `site_updates` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `site_name` varchar(255) NOT NULL,
                `update_date` date NOT NULL,
                `created_by` int(11) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if ($conn->query($create_table_query)) {
            error_log("Created site_updates table successfully");
            return true;
        } else {
            error_log("Error creating site_updates table: " . $conn->error);
            return false;
        }
    }
    
    return true;
}

/**
 * Check if required columns exist in the site_updates table and add them if they don't
 * 
 * @param mysqli $conn Database connection
 * @return bool True if all columns exist or were created successfully, False otherwise
 */
function ensure_site_updates_columns_exist($conn) {
    $required_columns = [
        'site_name' => "ALTER TABLE site_updates ADD COLUMN site_name VARCHAR(255) AFTER id",
        'update_date' => "ALTER TABLE site_updates ADD COLUMN update_date DATE AFTER site_name"
    ];
    
    $success = true;
    
    foreach ($required_columns as $column => $alter_query) {
        $column_check = $conn->query("SHOW COLUMNS FROM site_updates LIKE '$column'");
        
        if ($column_check->num_rows == 0) {
            // Column doesn't exist, add it
            if (!$conn->query($alter_query)) {
                error_log("Error adding column $column: " . $conn->error);
                $success = false;
            } else {
                error_log("Added column $column successfully");
            }
        }
    }
    
    return $success;
}

/**
 * Initialize the site updates database schema
 * 
 * @param mysqli $conn Database connection
 * @return bool True if schema is set up correctly, False otherwise
 */
function initialize_site_updates_schema($conn) {
    // First ensure the table exists
    if (!ensure_site_updates_table_exists($conn)) {
        return false;
    }
    
    // Then ensure all required columns exist
    if (!ensure_site_updates_columns_exist($conn)) {
        return false;
    }
    
    return true;
}

/**
 * Add a new site update to the database
 * 
 * @param mysqli $conn Database connection
 * @param string $site_name Name of the site
 * @param string $update_date Date of the update (YYYY-MM-DD)
 * @param int $user_id ID of the user creating the update
 * @return bool|int ID of the inserted update if successful, False otherwise
 */
function add_site_update($conn, $site_name, $update_date, $user_id) {
    $insert_query = "INSERT INTO site_updates (site_name, update_date, created_by, created_at) 
                     VALUES (?, ?, ?, NOW())";
                     
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("ssi", $site_name, $update_date, $user_id);
    
    if ($stmt->execute()) {
        return $stmt->insert_id;
    } else {
        error_log("Error adding site update: " . $conn->error);
        return false;
    }
}

/**
 * Get recent site updates
 * 
 * @param mysqli $conn Database connection
 * @param int $limit Maximum number of updates to return
 * @return array Array of site updates
 */
function get_recent_site_updates($conn, $limit = 5) {
    $query = "SELECT * FROM site_updates ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $updates = [];
    
    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
    }
    
    return $updates;
}

/**
 * Get site updates for a specific month
 * 
 * @param mysqli $conn Database connection
 * @param int $month Month number (1-12)
 * @param int $year Year (optional, defaults to current year)
 * @return array Array of site updates
 */
function get_site_updates_by_month($conn, $month, $year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    
    $query = "SELECT * FROM site_updates 
              WHERE MONTH(update_date) = ? AND YEAR(update_date) = ? 
              ORDER BY update_date DESC, created_at DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $updates = [];
    
    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
    }
    
    return $updates;
}
?> 