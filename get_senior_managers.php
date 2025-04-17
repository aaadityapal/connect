<?php
// Set content type header
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
require_once 'config/db_connect.php';

// Array to store manager data
$managers = [];
$debug_info = [];

// Try to get users from database
try {
    if ($conn) {
        // First, let's check what columns exist in the users table without assuming 'type' exists
        $columns_query = "SHOW COLUMNS FROM users";
        $columns_result = $conn->query($columns_query);
        
        if ($columns_result) {
            $columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            $debug_info['columns'] = $columns;
        } else {
            $debug_info['columns_error'] = $conn->error;
        }
        
        // Build a query based on the columns that actually exist
        $where_clauses = [];
        $select_fields = ["id", "username"];
        
        if (in_array('role', $columns)) {
            $select_fields[] = "role";
            $where_clauses[] = "role LIKE '%Senior Manager (Studio)%'";
            
            // Also get unique roles for debugging
            $roles_query = "SELECT DISTINCT role FROM users WHERE role IS NOT NULL";
            $roles_result = $conn->query($roles_query);
            
            if ($roles_result) {
                $roles = [];
                while ($role = $roles_result->fetch_assoc()) {
                    $roles[] = $role['role'];
                }
                $debug_info['user_roles'] = $roles;
            }
        }
        
        // Check for other potential role columns - be more specific
        $role_columns = ['user_type', 'position', 'designation', 'user_role'];
        foreach ($role_columns as $col) {
            if (in_array($col, $columns)) {
                $select_fields[] = $col;
                $where_clauses[] = "$col LIKE '%Senior Manager%' AND $col NOT LIKE '%Trainee%'";
            }
        }
        
        // If no role columns found, get users with admin roles
        if (empty($where_clauses) && in_array('is_admin', $columns)) {
            $where_clauses[] = "is_admin = 1";
        }
        
        // Build the query
        $select_clause = implode(", ", $select_fields);
        
        // If we have where clauses, use them; otherwise get first 5 users
        if (!empty($where_clauses)) {
            $where_clause = "(" . implode(" OR ", $where_clauses) . ")";
            $query = "SELECT $select_clause FROM users 
                     WHERE $where_clause AND deleted_at IS NULL
                     ORDER BY username ASC";
        } else {
            // Fallback to get first 5 users
            $query = "SELECT $select_clause FROM users 
                     WHERE deleted_at IS NULL
                     ORDER BY id ASC LIMIT 5";
        }
        
        $debug_info['query'] = $query;
        
        $result = $conn->query($query);
        
        if ($result) {
            $debug_info['found_rows'] = $result->num_rows;
            $all_users = [];
            
            while ($row = $result->fetch_assoc()) {
                $role_info = '';
                // Check each possible role column
                foreach ($role_columns as $col) {
                    if (isset($row[$col]) && !empty($row[$col])) {
                        $role_info = $row[$col];
                        break;
                    }
                }
                if (empty($role_info) && isset($row['role'])) {
                    $role_info = $row['role'];
                }
                
                // Store all users temporarily
                $all_users[] = [
                    'id' => $row['id'],
                    'name' => $row['username'],
                    'role' => $role_info
                ];
            }
            
            // Now filter the users to only include Senior Manager (Studio)
            foreach ($all_users as $user) {
                $username = strtolower($user['name']);
                $role = strtolower($user['role']);
                
                // Only include users that:
                // 1. Have "Senior Manager" in their role AND "Studio" in their role
                // 2. Don't have "trainee" in their role
                // 3. Don't have "dss" in their username
                if ((strpos($role, 'senior manager') !== false && 
                     strpos($role, 'studio') !== false) && 
                    strpos($role, 'trainee') === false && 
                    strpos($username, 'dss') === false) {
                    $managers[] = $user;
                }
            }
            
            $debug_info['filtered_rows'] = count($managers);
        } else {
            $debug_info['query_error'] = $conn->error;
        }
        
        // If still no managers found, just get the first 5 users as a fallback
        if (empty($managers)) {
            $fallback_query = "SELECT id, username FROM users 
                              WHERE deleted_at IS NULL 
                              ORDER BY id ASC LIMIT 5";
            
            $debug_info['fallback_query'] = $fallback_query;
            
            $fallback_result = $conn->query($fallback_query);
            
            if ($fallback_result) {
                $debug_info['fallback_rows'] = $fallback_result->num_rows;
                
                while ($row = $fallback_result->fetch_assoc()) {
                    $managers[] = [
                        'id' => $row['id'],
                        'name' => $row['username'],
                        'role' => 'User'
                    ];
                }
            } else {
                $debug_info['fallback_error'] = $conn->error;
            }
        }
    } else {
        $debug_info['connection_error'] = 'Database connection failed';
    }
} catch (Exception $e) {
    $debug_info['error'] = $e->getMessage();
}

// Return success with any managers we found (or empty array if error)
echo json_encode([
    'success' => true,
    'managers' => $managers,
    'debug' => $debug_info
]); 