<?php
// ============================================
// db.php — Shared Database Connection
// ============================================

// Adjust path to reach the central config directory
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/db_connect.php';

// The $pdo object is now provided by db_connect.php.
// We keep the getDB() function for mysqli backward compatibility if needed.
function getDB() {
    global $conn;
    return $conn;
}
?>
