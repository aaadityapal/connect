<?php
/**
 * Activity Logger
 * 
 * Functions to log user activities in the system
 */

/**
 * Log an activity
 * 
 * @param int $userId User ID who performed the action
 * @param string $activityType Type of activity (create, update, delete, etc.)
 * @param string $description Description of the activity
 * @param int|null $referenceId ID of the related record (optional)
 * @param string|null $referenceTable Table name of the related record (optional)
 * @return bool True on success, false on failure
 */
function logActivity($userId, $activityType, $description, $referenceId = null, $referenceTable = null) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO activity_logs (user_id, activity_type, description, reference_id, reference_table, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        // Get client IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        return $stmt->execute([
            $userId,
            $activityType,
            $description,
            $referenceId,
            $referenceTable,
            $ipAddress,
            $userAgent
        ]);
    } catch (Exception $e) {
        // Log error to a file instead of showing it to users
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to log site event creation
 * 
 * @param int $userId User ID who created the event
 * @param int $eventId The ID of the created event
 * @param string $siteName Name of the site
 * @param string $eventDate Date of the event
 * @return bool True on success, false on failure
 */
function logSiteEventCreation($userId, $eventId, $siteName, $eventDate) {
    $description = "Created site event for '{$siteName}' on {$eventDate}";
    return logActivity($userId, 'create', $description, $eventId, 'site_events');
}

/**
 * Helper function to log vendor addition to site event
 * 
 * @param int $userId User ID who added the vendor
 * @param int $vendorId The ID of the vendor record
 * @param int $eventId The ID of the site event
 * @param string $vendorName Name of the vendor
 * @return bool True on success, false on failure
 */
function logVendorAddition($userId, $vendorId, $eventId, $vendorName) {
    $description = "Added vendor '{$vendorName}' to site event #{$eventId}";
    return logActivity($userId, 'create', $description, $vendorId, 'event_vendors');
}

/**
 * Helper function to log company labour addition
 * 
 * @param int $userId User ID who added the labour
 * @param int $labourId The ID of the labour record
 * @param int $eventId The ID of the site event
 * @param string $labourName Name of the labour
 * @return bool True on success, false on failure
 */
function logCompanyLabourAddition($userId, $labourId, $eventId, $labourName) {
    $description = "Added company labour '{$labourName}' to site event #{$eventId}";
    return logActivity($userId, 'create', $description, $labourId, 'event_company_labours');
}

/**
 * Helper function to log vendor labour addition
 * 
 * @param int $userId User ID who added the labour
 * @param int $labourId The ID of the labour record
 * @param int $vendorId The ID of the vendor
 * @param string $labourName Name of the labour
 * @return bool True on success, false on failure
 */
function logVendorLabourAddition($userId, $labourId, $vendorId, $labourName) {
    $description = "Added labour '{$labourName}' to vendor #{$vendorId}";
    return logActivity($userId, 'create', $description, $labourId, 'vendor_laborers');
}

/**
 * Helper function to log travel expense addition
 * 
 * @param int $userId User ID who added the expense
 * @param int $expenseId The ID of the expense record
 * @param int $eventId The ID of the site event
 * @param string $fromLocation Starting location
 * @param string $toLocation Destination location
 * @return bool True on success, false on failure
 */
function logTravelExpenseAddition($userId, $expenseId, $eventId, $fromLocation, $toLocation) {
    $description = "Added travel expense from '{$fromLocation}' to '{$toLocation}' for event #{$eventId}";
    return logActivity($userId, 'create', $description, $expenseId, 'event_travel_expenses');
}

/**
 * Helper function to log beverage addition
 * 
 * @param int $userId User ID who added the beverage
 * @param int $beverageId The ID of the beverage record
 * @param int $eventId The ID of the site event
 * @param string $beverageType Type of beverage
 * @param int $quantity Quantity added
 * @return bool True on success, false on failure
 */
function logBeverageAddition($userId, $beverageId, $eventId, $beverageType, $quantity) {
    $description = "Added {$quantity} {$beverageType} to event #{$eventId}";
    return logActivity($userId, 'create', $description, $beverageId, 'event_beverages');
}

/**
 * Helper function to log work progress entry
 * 
 * @param int $userId User ID who added the work progress
 * @param int $workId The ID of the work progress record
 * @param int $eventId The ID of the site event
 * @param string $category Work category
 * @param int $completionPercentage Percentage of completion
 * @return bool True on success, false on failure
 */
function logWorkProgressAddition($userId, $workId, $eventId, $category, $completionPercentage) {
    $description = "Added work progress for '{$category}' ({$completionPercentage}% complete) to event #{$eventId}";
    return logActivity($userId, 'create', $description, $workId, 'event_work_progress');
}

/**
 * Helper function to log inventory item addition
 * 
 * @param int $userId User ID who added the inventory
 * @param int $inventoryId The ID of the inventory record
 * @param int $eventId The ID of the site event
 * @param string $itemName Name of the item
 * @param float $quantity Quantity added
 * @param string $units Units of measurement
 * @return bool True on success, false on failure
 */
function logInventoryAddition($userId, $inventoryId, $eventId, $itemName, $quantity, $units) {
    $description = "Added {$quantity} {$units} of '{$itemName}' to inventory for event #{$eventId}";
    return logActivity($userId, 'create', $description, $inventoryId, 'event_inventory_items');
}

/**
 * Get recent activities for a user
 * 
 * @param int $userId User ID
 * @param int $limit Number of activities to fetch (default 10)
 * @return array Array of activity logs
 */
function getRecentActivities($userId, $limit = 10) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Activity Log Fetch Error: " . $e->getMessage());
        return [];
    }
} 