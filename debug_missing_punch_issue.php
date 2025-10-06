<?php
/**
 * Debug script for missing punch approval issue
 */
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Function to test database connection
function testDatabaseConnection($conn) {
    try {
        $result = $conn->query("SELECT 1 as test");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Check if we're receiving POST data
$received_post_data = !empty($_POST);
$post_data = $_POST;

// Check if we're receiving GET data
$received_get_data = !empty($_GET);
$get_data = $_GET;

// Check if we're receiving raw input
$raw_input = file_get_contents('php://input');

// Test database connection
$db_connected = testDatabaseConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Missing Punch Issue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Debug Missing Punch Approval Issue</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Request Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Method:</strong> <?= $_SERVER['REQUEST_METHOD'] ?? 'Unknown' ?></p>
                <p><strong>Content Type:</strong> <?= $_SERVER['CONTENT_TYPE'] ?? 'Not set' ?></p>
                <p><strong>Received POST Data:</strong> <?= $received_post_data ? 'Yes' : 'No' ?></p>
                <p><strong>Received GET Data:</strong> <?= $received_get_data ? 'Yes' : 'No' ?></p>
            </div>
        </div>
        
        <?php if ($received_post_data): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>POST Data</h5>
            </div>
            <div class="card-body">
                <pre><?= htmlspecialchars(print_r($post_data, true)) ?></pre>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($received_get_data): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>GET Data</h5>
            </div>
            <div class="card-body">
                <pre><?= htmlspecialchars(print_r($get_data, true)) ?></pre>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Raw Input</h5>
            </div>
            <div class="card-body">
                <pre><?= htmlspecialchars($raw_input) ?: 'No raw input' ?></pre>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Database Connection</h5>
            </div>
            <div class="card-body">
                <p><strong>Status:</strong> <?= $db_connected ? 'Connected' : 'Failed' ?></p>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Session Data</h5>
            </div>
            <div class="card-body">
                <pre><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Test Form</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="missing_punch_id" class="form-label">Missing Punch ID</label>
                        <input type="text" class="form-control" id="missing_punch_id" name="missing_punch_id" value="1">
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes">Test notes</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Test Data</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>