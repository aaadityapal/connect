<?php
// Start session at the beginning of the file
session_start();

// Include database connection
require_once '../config/db_connect.php';

// Initialize variables
$message = '';
$messageType = '';
$confirmKey = md5(uniqid(rand(), true));
$sourceCount = 0;
$destCount = 0;

// Check if source table exists and count records
try {
    $sourceResult = $conn->query("SHOW TABLES LIKE 'project_payouts'");
    if ($sourceResult->num_rows > 0) {
        $countResult = $conn->query("SELECT COUNT(*) as total FROM project_payouts");
        if ($countResult) {
            $sourceCount = $countResult->fetch_assoc()['total'];
        }
    }
    
    // Check if destination tables exist and count records
    $destResult = $conn->query("SHOW TABLES LIKE 'hrm_project_stage_payment_transactions'");
    if ($destResult->num_rows > 0) {
        $countResult = $conn->query("SELECT COUNT(*) as total FROM hrm_project_stage_payment_transactions");
        if ($countResult) {
            $destCount = $countResult->fetch_assoc()['total'];
        }
    }
} catch (Exception $e) {
    $message = "Error checking tables: " . $e->getMessage();
    $messageType = 'danger';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate confirmation key
    if (isset($_POST['confirm_key']) && isset($_SESSION['confirm_key']) && $_POST['confirm_key'] === $_SESSION['confirm_key']) {
        // Run the migration
        $output = [];
        $returnCode = 0;
        
        // Execute the migration script
        exec('php ' . __DIR__ . '/migrate_project_payouts.php 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $message = "Migration completed successfully. See details below.";
            $messageType = 'success';
        } else {
            $message = "Migration encountered errors. See details below.";
            $messageType = 'warning';
        }
        
        // Store output for display
        $_SESSION['migration_output'] = implode("\n", $output);
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?result=" . $messageType);
        exit;
    } else {
        $message = "Invalid confirmation key. Please try again.";
        $messageType = 'danger';
    }
}

// Store confirmation key in session
$_SESSION['confirm_key'] = $confirmKey;

// Check for result parameter
if (isset($_GET['result'])) {
    if ($_GET['result'] === 'success') {
        $message = "Migration completed successfully. See details below.";
        $messageType = 'success';
    } elseif ($_GET['result'] === 'warning') {
        $message = "Migration encountered some errors. See details below.";
        $messageType = 'warning';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Payouts Migration Tool</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .output-container {
            background-color: #212529;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .table-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .table-card {
            flex: 1;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="my-4 text-center">Project Payouts Migration Tool</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Migration Information</h5>
            </div>
            <div class="card-body">
                <p>This tool will migrate data from the old <code>project_payouts</code> table to the new structure:</p>
                <ul>
                    <li><code>hrm_project_stage_payment_transactions</code> - Main transaction records</li>
                    <li><code>hrm_project_payment_entries</code> - Individual payment entries</li>
                </ul>
                
                <div class="alert alert-info">
                    <strong>Important:</strong> This process will:
                    <ul>
                        <li>Create the new tables if they don't exist</li>
                        <li>Transfer all data from the old table to the new structure</li>
                        <li>Preserve existing data in the destination tables if they already exist</li>
                    </ul>
                </div>
                
                <div class="table-info">
                    <div class="card table-card">
                        <div class="card-header bg-secondary text-white">Source Table</div>
                        <div class="card-body">
                            <p><strong>Table:</strong> project_payouts</p>
                            <p><strong>Records:</strong> <?php echo $sourceCount; ?></p>
                            <p><strong>Status:</strong> 
                                <?php if ($sourceCount > 0): ?>
                                    <span class="badge bg-success">Available</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Empty or Not Found</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="card table-card">
                        <div class="card-header bg-secondary text-white">Destination Tables</div>
                        <div class="card-body">
                            <p><strong>Main Table:</strong> hrm_project_stage_payment_transactions</p>
                            <p><strong>Records:</strong> <?php echo $destCount; ?></p>
                            <p><strong>Status:</strong> 
                                <?php if ($destCount > 0): ?>
                                    <span class="badge bg-warning">Has Data</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Ready for Migration</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($sourceCount > 0): ?>
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Run Migration</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="migrationForm">
                        <input type="hidden" name="confirm_key" value="<?php echo $confirmKey; ?>">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirmCheckbox" required>
                            <label class="form-check-label" for="confirmCheckbox">
                                I understand that this operation will migrate <?php echo $sourceCount; ?> records to the new table structure
                            </label>
                        </div>
                        
                        <?php if ($destCount > 0): ?>
                            <div class="alert alert-warning">
                                <strong>Warning:</strong> The destination table already has <?php echo $destCount; ?> records. 
                                Running this migration will add more records but won't modify existing ones.
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="confirmExistingData" required>
                                <label class="form-check-label" for="confirmExistingData">
                                    I understand that there is existing data in the destination tables
                                </label>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-danger" id="runMigrationBtn" disabled>
                            <i class="bi bi-arrow-right-circle"></i> Run Migration
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> Cannot proceed with migration. The source table either doesn't exist or has no records.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['migration_output'])): ?>
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Migration Output</h5>
                </div>
                <div class="card-body">
                    <div class="output-container">
<?php echo htmlspecialchars($_SESSION['migration_output']); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable the run button only when confirmation is checked
        document.addEventListener('DOMContentLoaded', function() {
            const confirmCheckbox = document.getElementById('confirmCheckbox');
            const confirmExistingData = document.getElementById('confirmExistingData');
            const runMigrationBtn = document.getElementById('runMigrationBtn');
            
            function updateButtonState() {
                if (confirmCheckbox && confirmCheckbox.checked) {
                    if (confirmExistingData) {
                        runMigrationBtn.disabled = !confirmExistingData.checked;
                    } else {
                        runMigrationBtn.disabled = false;
                    }
                } else {
                    runMigrationBtn.disabled = true;
                }
            }
            
            if (confirmCheckbox) {
                confirmCheckbox.addEventListener('change', updateButtonState);
            }
            
            if (confirmExistingData) {
                confirmExistingData.addEventListener('change', updateButtonState);
            }
            
            // Show confirmation dialog before submitting
            const migrationForm = document.getElementById('migrationForm');
            if (migrationForm) {
                migrationForm.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to run the migration? This cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html> 