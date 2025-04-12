<?php
require_once 'config/db_connect.php';

// Create a header
echo "<h1>Direct Drawing Number Update Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
    form { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
    input, select { padding: 8px; margin: 5px; width: 100%; box-sizing: border-box; }
    button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
</style>";

// Function to log
function logMessage($message, $type = 'info') {
    echo "<p class='$type'>" . htmlspecialchars($message) . "</p>";
}

// Get existing substage IDs for dropdown
$substage_query = "SELECT ps.id, ps.title, ps.drawing_number, pst.title as stage_title, p.title as project_title
                  FROM project_substages ps
                  JOIN project_stages pst ON ps.stage_id = pst.id
                  JOIN projects p ON pst.project_id = p.id
                  WHERE ps.deleted_at IS NULL
                  ORDER BY p.title, pst.id, ps.id
                  LIMIT 100";
$substages = [];

try {
    $result = $conn->query($substage_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $substages[] = $row;
        }
    }
} catch (Exception $e) {
    logMessage("Error fetching substages: " . $e->getMessage(), "error");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $substage_id = $_POST['substage_id'] ?? null;
        $drawing_number = $_POST['drawing_number'] ?? null;
        $update_method = $_POST['update_method'] ?? 'standard';
        
        if (!$substage_id) {
            throw new Exception("No substage selected");
        }
        
        // Get original value
        $check_sql = "SELECT drawing_number FROM project_substages WHERE id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $substage_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $original_value = $row['drawing_number'];
        logMessage("Original drawing number: " . var_export($original_value, true) . " (" . gettype($original_value) . ")", "info");
        logMessage("New drawing number: " . var_export($drawing_number, true) . " (" . gettype($drawing_number) . ")", "info");
        
        // Process drawing number
        if ($drawing_number === '' || $drawing_number === '0') {
            $processed_drawing_number = null;
            logMessage("Processed to NULL because empty or '0'", "info");
        } else {
            $processed_drawing_number = $drawing_number;
            logMessage("Using as is: " . var_export($processed_drawing_number, true), "info");
        }
        
        // Update based on selected method
        $conn->begin_transaction();
        
        switch ($update_method) {
            case 'direct_sql':
                // Method 1: Direct SQL with value in query
                if ($processed_drawing_number === null) {
                    $sql = "UPDATE project_substages SET drawing_number = NULL WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $substage_id);
                } else {
                    $sql = "UPDATE project_substages SET drawing_number = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $processed_drawing_number, $substage_id);
                }
                
                logMessage("Using direct SQL method: " . $sql, "info");
                break;
                
            case 'prepare_execute':
                // Method 2: Standard prepared statement
                $sql = "UPDATE project_substages SET drawing_number = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $processed_drawing_number, $substage_id);
                
                logMessage("Using standard prepared statement", "info");
                break;
                
            case 'quote_escape':
                // Method 3: String escaping
                if ($processed_drawing_number === null) {
                    $drawing_part = "NULL";
                } else {
                    $drawing_part = "'" . $conn->real_escape_string($processed_drawing_number) . "'";
                }
                
                $sql = "UPDATE project_substages SET drawing_number = $drawing_part WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $substage_id);
                
                logMessage("Using string escaping method: " . $sql, "info");
                break;
                
            default:
                throw new Exception("Invalid update method");
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            logMessage("Update successful!", "success");
            $conn->commit();
            
            // Verify the update
            $check_sql = "SELECT drawing_number FROM project_substages WHERE id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("i", $substage_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $new_value = $row['drawing_number'];
            logMessage("New value in database: " . var_export($new_value, true) . " (" . gettype($new_value) . ")", "info");
            
            // Refresh page to show updated data
            echo "<script>setTimeout(function() { window.location.reload(); }, 3000);</script>";
            logMessage("Page will refresh in 3 seconds...", "info");
        } else {
            logMessage("Update failed: " . $stmt->error, "error");
            $conn->rollback();
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno === 0) {
            $conn->rollback();
        }
        logMessage("Error: " . $e->getMessage(), "error");
    }
}
?>

<h2>Current Drawing Numbers</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Project</th>
        <th>Stage</th>
        <th>Substage</th>
        <th>Current Drawing #</th>
        <th>Type</th>
    </tr>
    <?php foreach ($substages as $substage): ?>
    <tr>
        <td><?= htmlspecialchars($substage['id']) ?></td>
        <td><?= htmlspecialchars($substage['project_title']) ?></td>
        <td><?= htmlspecialchars($substage['stage_title']) ?></td>
        <td><?= htmlspecialchars($substage['title']) ?></td>
        <td><?= var_export($substage['drawing_number'], true) ?></td>
        <td><?= gettype($substage['drawing_number']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>Update Drawing Number</h2>

<form method="post">
    <div>
        <label for="substage_id">Select Substage:</label>
        <select name="substage_id" id="substage_id" required>
            <option value="">-- Select Substage --</option>
            <?php foreach ($substages as $substage): ?>
            <option value="<?= htmlspecialchars($substage['id']) ?>">
                ID: <?= htmlspecialchars($substage['id']) ?> - 
                <?= htmlspecialchars($substage['project_title']) ?> / 
                <?= htmlspecialchars($substage['title']) ?> 
                (Current: <?= var_export($substage['drawing_number'], true) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div>
        <label for="drawing_number">New Drawing Number:</label>
        <input type="text" name="drawing_number" id="drawing_number" placeholder="e.g., CD_1001">
    </div>
    
    <div>
        <label for="update_method">Update Method:</label>
        <select name="update_method" id="update_method">
            <option value="standard">Standard Prepared Statement</option>
            <option value="direct_sql">Direct SQL with Conditional</option>
            <option value="quote_escape">String Escaping</option>
        </select>
    </div>
    
    <button type="submit">Update Drawing Number</button>
</form>

<h2>Database Structure</h2>

<?php
try {
    $result = $conn->query("DESCRIBE project_substages");
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    logMessage("Error getting schema: " . $e->getMessage(), "error");
}
?> 