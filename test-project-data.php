<?php
// Include database connection

require_once 'config/db_connect.php';


// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user ID
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

echo '<h1>Project Data Diagnostic Tool</h1>';
echo '<p>This tool helps diagnose issues with project data loading for the calendar view.</p>';

// Display current user info
echo '<h2>Current User Information</h2>';
if ($current_user_id) {
    echo "<p>Logged in user ID: $current_user_id</p>";
    
    // Get user details
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user = $user_result->fetch_assoc()) {
        echo "<p>Username: {$user['username']}</p>";
        // Check if firstname/lastname fields exist or use username
        if (isset($user['firstname']) && isset($user['lastname'])) {
            echo "<p>Name: {$user['firstname']} {$user['lastname']}</p>";
        } else {
            echo "<p>Name: {$user['username']}</p>";
        }
    } else {
        echo "<p class='error'>User not found in database!</p>";
    }
} else {
    echo "<p class='error'>No user is currently logged in!</p>";
}

// Function to get all projects, including those not assigned to the current user
function getAllProjects($conn) {
    $query = "SELECT * FROM projects ORDER BY id DESC";
    $result = $conn->query($query);
    
    $projects = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
    }
    
    return $projects;
}

// Function to get all stages for a project
function getProjectStages($conn, $project_id) {
    $query = "SELECT * FROM project_stages WHERE project_id = ? ORDER BY stage_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stages = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stages[] = $row;
        }
    }
    
    return $stages;
}

// Function to get all substages for a stage
function getStageSubstages($conn, $stage_id) {
    $query = "SELECT * FROM project_substages WHERE stage_id = ? ORDER BY substage_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $stage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $substages = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $substages[] = $row;
        }
    }
    
    return $substages;
}

// Get all projects
$all_projects = getAllProjects($conn);

echo '<h2>Projects in Database</h2>';
echo '<p>Total projects found: ' . count($all_projects) . '</p>';

if (count($all_projects) === 0) {
    echo "<p class='error'>No projects found in the database!</p>";
    echo "<p>Please check the following:</p>";
    echo "<ul>";
    echo "<li>The database connection is working correctly</li>";
    echo "<li>The 'projects' table exists and contains data</li>";
    echo "<li>The SQL query is correct</li>";
    echo "</ul>";
}

// Display project data with stages and substages
echo '<h2>Project Details</h2>';

// Counter for items that should appear in calendar
$calendar_items = [
    'projects' => 0,
    'stages' => 0,
    'substages' => 0
];

foreach ($all_projects as $project) {
    echo "<div class='project-item'>";
    echo "<h3>Project: {$project['title']} (ID: {$project['id']})</h3>";
    echo "<p>Status: {$project['status']}</p>";
    echo "<p>End Date: {$project['end_date']}</p>";
    
    // Check if project should appear in calendar
    if (!empty($project['end_date'])) {
        $calendar_items['projects']++;
    }
    
    // Display assigned users for this project
    echo "<p>Assigned to: ";
    if (!empty($project['assigned_to'])) {
        $assigned_users = explode(',', $project['assigned_to']);
        foreach ($assigned_users as $user_id) {
            echo "User ID: $user_id";
            if ($user_id == $current_user_id) {
                echo " (Current User)";
            }
            echo ", ";
        }
    } else {
        echo "None";
    }
    echo "</p>";
    
    // Get stages for this project
    $stages = getProjectStages($conn, $project['id']);
    echo "<div class='stages' style='margin-left: 20px;'>";
    echo "<h4>Stages (" . count($stages) . ")</h4>";
    
    if (count($stages) === 0) {
        echo "<p>No stages found for this project.</p>";
    }
    
    foreach ($stages as $stage) {
        echo "<div class='stage-item'>";
        echo "<h5>Stage: #{$stage['stage_number']} (ID: {$stage['id']})</h5>";
        echo "<p>Status: {$stage['status']}</p>";
        echo "<p>End Date: {$stage['end_date']}</p>";
        
        // Check if assigned to current user
        $is_assigned_to_user = false;
        if (!empty($stage['assigned_to'])) {
            $assigned_users = explode(',', $stage['assigned_to']);
            $is_assigned_to_user = in_array($current_user_id, $assigned_users);
        }
        
        echo "<p>Assigned to: ";
        if (!empty($stage['assigned_to'])) {
            $assigned_users = explode(',', $stage['assigned_to']);
            foreach ($assigned_users as $user_id) {
                echo "User ID: $user_id";
                if ($user_id == $current_user_id) {
                    echo " (Current User)";
                }
                echo ", ";
            }
        } else {
            echo "None";
        }
        echo "</p>";
        
        // Check if stage should appear in calendar for current user
        if ($is_assigned_to_user && !empty($stage['end_date'])) {
            $calendar_items['stages']++;
            echo "<p class='highlight'>Should appear in calendar for current user</p>";
        }
        
        // Get substages
        $substages = getStageSubstages($conn, $stage['id']);
        echo "<div class='substages' style='margin-left: 20px;'>";
        echo "<h6>Substages (" . count($substages) . ")</h6>";
        
        if (count($substages) === 0) {
            echo "<p>No substages found for this stage.</p>";
        }
        
        foreach ($substages as $substage) {
            echo "<div class='substage-item'>";
            echo "<p>Substage: {$substage['title']} (ID: {$substage['id']})</p>";
            echo "<p>Substage Number: {$substage['substage_number']}</p>";
            echo "<p>Status: {$substage['status']}</p>";
            echo "<p>End Date: {$substage['end_date']}</p>";
            
            // Check if assigned to current user
            $is_substage_assigned_to_user = false;
            if (!empty($substage['assigned_to'])) {
                $assigned_users = explode(',', $substage['assigned_to']);
                $is_substage_assigned_to_user = in_array($current_user_id, $assigned_users);
            }
            
            echo "<p>Assigned to: ";
            if (!empty($substage['assigned_to'])) {
                $assigned_users = explode(',', $substage['assigned_to']);
                foreach ($assigned_users as $user_id) {
                    echo "User ID: $user_id";
                    if ($user_id == $current_user_id) {
                        echo " (Current User)";
                    }
                    echo ", ";
                }
            } else {
                echo "None";
            }
            echo "</p>";
            
            // Check if substage should appear in calendar for current user
            if ($is_substage_assigned_to_user && !empty($substage['end_date'])) {
                $calendar_items['substages']++;
                echo "<p class='highlight'>Should appear in calendar for current user</p>";
            }
            
            echo "</div>"; // end substage-item
        }
        
        echo "</div>"; // end substages
        echo "</div>"; // end stage-item
    }
    
    echo "</div>"; // end stages
    echo "</div>"; // end project-item
    echo "<hr>";
}

// Check get_user_projects.php implementation
echo '<h2>API Endpoint Analysis</h2>';

echo '<h3>Checking get_user_projects.php</h3>';

$api_file = 'get_user_projects.php';
if (file_exists($api_file)) {
    echo "<p>File exists.</p>";
    
    // Get file contents to analyze
    $contents = file_get_contents($api_file);
    echo "<pre>" . htmlspecialchars(substr($contents, 0, 500)) . "...</pre>";
    
    // Look for common issues
    $issues = [];
    
    if (strpos($contents, 'session_start') === false) {
        $issues[] = "The file might not start a session, which could prevent accessing the user ID.";
    }
    
    if (strpos($contents, 'user_id') === false && strpos($contents, 'USER_ID') === false) {
        $issues[] = "The file doesn't seem to reference user_id, which might prevent filtering projects by user.";
    }
    
    if (strpos($contents, 'project_stages') === false) {
        $issues[] = "The file doesn't seem to query for project_stages.";
    }
    
    if (strpos($contents, 'project_substages') === false) {
        $issues[] = "The file doesn't seem to query for project_substages.";
    }
    
    if (count($issues) > 0) {
        echo "<p>Potential issues found:</p>";
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li>$issue</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No obvious issues found in the file.</p>";
    }
} else {
    echo "<p class='error'>File not found! Please make sure get_user_projects.php exists in the root directory.</p>";
}

// Summary
echo '<h2>Summary</h2>';
echo "<p>Items that should appear in calendar for current user:</p>";
echo "<ul>";
echo "<li>Projects: {$calendar_items['projects']}</li>";
echo "<li>Stages: {$calendar_items['stages']}</li>";
echo "<li>Substages: {$calendar_items['substages']}</li>";
echo "</ul>";

// Check JS global variables
echo "<h3>JavaScript Global Variables Test</h3>";
echo "<script>
document.write('<p>USER_ID global variable: ' + (typeof USER_ID !== 'undefined' ? USER_ID : 'Not defined') + '</p>');
</script>";

// Suggested fixes
echo '<h2>Potential Solutions</h2>';
echo "<ol>";
echo "<li>Ensure the USER_ID JavaScript variable is defined with the current user's ID.</li>";
echo "<li>Check that get_user_projects.php is correctly querying and returning project data.</li>";
echo "<li>Verify that the assigned_to fields in the database contain the correct user IDs.</li>";
echo "<li>Make sure that end_date fields are correctly formatted (should be YYYY-MM-DD format).</li>";
echo "<li>Check if there are any JavaScript errors in the console that might be preventing data loading.</li>";
echo "</ol>";

echo '<style>
    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
    .error { color: red; font-weight: bold; }
    .highlight { color: green; font-weight: bold; }
    .project-item, .stage-item, .substage-item { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; }
    h1, h2, h3, h4, h5, h6 { margin-top: 20px; }
    pre { background-color: #f5f5f5; padding: 10px; overflow: auto; }
    hr { margin: 30px 0; border: 0; border-top: 1px solid #eee; }
</style>';
?> 