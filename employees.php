<?php
// Add database connection using absolute path
require_once __DIR__ . 'config/db_connect.php';

if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
        ?>
    </div>
<?php endif; ?>

<!-- Add hierarchy display -->
<div class="employee-hierarchy">
    <h3>Employee Reporting Structure</h3>
    <?php
    // Fetch all users with their manager information
    $query = "SELECT 
        id,
        username as name,
        designation as position,
        reporting_manager,
        username as manager_name 
    FROM users  
    LEFT JOIN users ON reporting_manager = username 
    WHERE status = 'active'
    ORDER BY reporting_manager, username";

    $result = mysqli_query($conn, $query);

    // Create an array to store the hierarchy
    $hierarchy = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $manager = $row['reporting_manager'] ?? 'top';
        $hierarchy[$manager][] = $row;
    }

    // Function to display hierarchy recursively
    function displayHierarchy($hierarchy, $manager = 'top', $level = 0) {
        if (!isset($hierarchy[$manager])) {
            return;
        }
        
        echo "<ul class='hierarchy-level-" . $level . "'>";
        foreach ($hierarchy[$manager] as $employee) {
            echo "<li>";
            echo "<div class='employee-info'>";
            echo "<strong>" . htmlspecialchars($employee['name']) . "</strong>";
            if (!empty($employee['position'])) {
                echo " - " . htmlspecialchars($employee['position']);
            }
            echo "</div>";
            
            // Display subordinates recursively
            displayHierarchy($hierarchy, $employee['name'], $level + 1);
            echo "</li>";
        }
        echo "</ul>";
    }

    // Display the hierarchy starting from top level
    displayHierarchy($hierarchy);
    ?>
</div>

<style>
    .employee-hierarchy {
        margin: 20px;
        padding: 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .employee-hierarchy ul {
        list-style: none;
        padding-left: 30px;
        margin: 10px 0;
    }
    .employee-hierarchy li {
        margin: 15px 0;
        position: relative;
    }
    .employee-info {
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        display: inline-block;
        border-left: 4px solid #dc3545;
        transition: all 0.3s ease;
    }
    .employee-info:hover {
        background: #fff3cd;
        transform: translateX(5px);
    }
    .hierarchy-level-0 > li > .employee-info {
        border-left-color: #28a745;
    }
    .hierarchy-level-1 > li > .employee-info {
        border-left-color: #007bff;
    }
    .hierarchy-level-2 > li > .employee-info {
        border-left-color: #6f42c1;
    }
</style>
