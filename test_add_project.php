<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/includes/project_payout_functions.php';

echo "<h1>Test Adding Project with Remaining Amount</h1>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project') {
    try {
        // Collect data from form
        $data = [
            'project_name' => $_POST['project_name'] ?? 'Test Project',
            'project_type' => $_POST['project_type'] ?? 'Architecture',
            'client_name' => $_POST['client_name'] ?? 'Test Client',
            'project_date' => $_POST['project_date'] ?? date('Y-m-d'),
            'amount' => $_POST['amount'] ?? 1000,
            'payment_mode' => $_POST['payment_mode'] ?? 'Cash',
            'project_stage' => $_POST['project_stage'] ?? 'Stage 1',
            'remaining_amount' => $_POST['remaining_amount'] ?? 0
        ];
        
        // Log the data
        echo "<h2>Form Data Received:</h2>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        
        // Add project
        $id = addProjectPayout($pdo, $data);
        
        if ($id) {
            echo "<p style='color:green'>Project added successfully with ID: $id</p>";
            
            // Retrieve the project to verify
            $project = getProjectPayoutById($pdo, $id);
            
            if ($project) {
                echo "<h2>Retrieved Project:</h2>";
                echo "<pre>";
                print_r($project);
                echo "</pre>";
                
                // Check if remaining amount was saved correctly
                if (isset($project['remaining_amount']) && $project['remaining_amount'] == $data['remaining_amount']) {
                    echo "<p style='color:green'>Remaining amount saved correctly: {$project['remaining_amount']}</p>";
                } else {
                    echo "<p style='color:red'>Remaining amount not saved correctly. Expected: {$data['remaining_amount']}, Got: " . 
                        (isset($project['remaining_amount']) ? $project['remaining_amount'] : 'NULL') . "</p>";
                }
            } else {
                echo "<p style='color:red'>Could not retrieve the added project</p>";
            }
        } else {
            echo "<p style='color:red'>Failed to add project</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}

// Display form
?>
<form method="post" action="test_add_project.php" style="max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
    <input type="hidden" name="action" value="add_project">
    
    <div style="margin-bottom: 15px;">
        <label for="project_name" style="display: block; margin-bottom: 5px;">Project Name:</label>
        <input type="text" id="project_name" name="project_name" value="Test Project" style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="project_type" style="display: block; margin-bottom: 5px;">Project Type:</label>
        <select id="project_type" name="project_type" style="width: 100%; padding: 8px; box-sizing: border-box;">
            <option value="Architecture">Architecture</option>
            <option value="Interior">Interior</option>
            <option value="Construction">Construction</option>
        </select>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="client_name" style="display: block; margin-bottom: 5px;">Client Name:</label>
        <input type="text" id="client_name" name="client_name" value="Test Client" style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="project_date" style="display: block; margin-bottom: 5px;">Project Date:</label>
        <input type="date" id="project_date" name="project_date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="amount" style="display: block; margin-bottom: 5px;">Amount:</label>
        <input type="number" id="amount" name="amount" value="1000" step="0.01" style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="remaining_amount" style="display: block; margin-bottom: 5px;">Remaining Amount:</label>
        <input type="number" id="remaining_amount" name="remaining_amount" value="200" step="0.01" style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="payment_mode" style="display: block; margin-bottom: 5px;">Payment Mode:</label>
        <input type="text" id="payment_mode" name="payment_mode" value="Cash" style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="project_stage" style="display: block; margin-bottom: 5px;">Project Stage:</label>
        <input type="text" id="project_stage" name="project_stage" value="Stage 1" style="width: 100%; padding: 8px; box-sizing: border-box;">
    </div>
    
    <div>
        <button type="submit" style="background-color: #4361ee; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">Add Project</button>
    </div>
</form>

<h2>Database Table Structure</h2>
<?php
// Display table structure
try {
    $stmt = $pdo->query("DESCRIBE project_payouts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Error checking table structure: " . $e->getMessage() . "</p>";
}

// Display recent projects
echo "<h2>Recent Projects</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM project_payouts ORDER BY id DESC LIMIT 5");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($projects) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Project Name</th><th>Amount</th><th>Remaining Amount</th><th>Created At</th></tr>";
        
        foreach ($projects as $project) {
            echo "<tr>";
            echo "<td>{$project['id']}</td>";
            echo "<td>{$project['project_name']}</td>";
            echo "<td>{$project['amount']}</td>";
            echo "<td>{$project['remaining_amount']}</td>";
            echo "<td>{$project['created_at']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No projects found</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Error fetching projects: " . $e->getMessage() . "</p>";
}
?> 