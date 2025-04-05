<?php
// Diagnostic file to help troubleshoot server issues

// Set appropriate headers
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Server Diagnostic</h1>";

// PHP Version
echo "<h2>PHP Version</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Check if mod_rewrite is enabled
echo "<h2>Apache Modules</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<p>mod_rewrite enabled: " . (in_array('mod_rewrite', $modules) ? 'Yes' : 'No') . "</p>";
} else {
    echo "<p>Unable to check Apache modules - function apache_get_modules() not available</p>";
}

// Directory structure 
echo "<h2>Directory Structure</h2>";
echo "<pre>";
function listDir($dir, $indent = 0) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo str_repeat(' ', $indent) . "- $file";
            if (is_dir("$dir/$file")) {
                echo " (directory)";
                if ($indent < 4) { // Limit recursion depth
                    echo "\n";
                    listDir("$dir/$file", $indent + 2);
                } else {
                    echo " ...\n";
                }
            } else {
                echo "\n";
            }
        }
    }
}

// List dashboard and handlers directories
echo "Dashboard directory:\n";
if (is_dir('dashboard')) {
    listDir('dashboard', 2);
} else {
    echo "  Dashboard directory not found!\n";
}

echo "\nHandlers directory:\n";
if (is_dir('dashboard/handlers')) {
    listDir('dashboard/handlers', 2);
} else {
    echo "  Handlers directory not found!\n";
}
echo "</pre>";

// File permissions
echo "<h2>File Permissions</h2>";
echo "<pre>";
function checkFilePermissions($file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $perms_string = sprintf('%o', $perms);
        echo "$file: $perms_string\n";
    } else {
        echo "$file: Not found\n";
    }
}

// Check key files
checkFilePermissions('dashboard/handlers/get_project_details.php');
checkFilePermissions('assets/js/task-overview-manager.js');
checkFilePermissions('similar_dashboard.php');
echo "</pre>";

// Path info
echo "<h2>Server Path Information</h2>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>PHP Self: " . $_SERVER['PHP_SELF'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// AJAX Test
echo "<h2>API Test</h2>";
echo "<div id='api-result'>Running API test...</div>";
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resultDiv = document.getElementById('api-result');
    
    // Test the API endpoint with a hard-coded project ID (you can modify this)
    const testProjectId = 1;
    
    // Test both path options
    const paths = [
        `dashboard/handlers/get_project_details.php?project_id=${testProjectId}`,
        `${window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'))}/dashboard/handlers/get_project_details.php?project_id=${testProjectId}`
    ];
    
    resultDiv.innerHTML = '<p>Testing API paths:</p>';
    
    paths.forEach((path, index) => {
        resultDiv.innerHTML += `<p>Testing path ${index + 1}: ${path}</p>`;
        
        fetch(path)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                resultDiv.innerHTML += `<p>Path ${index + 1} result: Success! Data: ${JSON.stringify(data).substring(0, 100)}...</p>`;
            })
            .catch(error => {
                resultDiv.innerHTML += `<p>Path ${index + 1} result: Failed with error: ${error.message}</p>`;
            });
    });
    
    // Add diagnostic button for SweetAlert2
    const sweetAlertButton = document.createElement('button');
    sweetAlertButton.textContent = 'Test SweetAlert2';
    sweetAlertButton.onclick = function() {
        if (typeof Swal === 'undefined') {
            alert('SweetAlert2 is not defined! Make sure the script is loaded correctly.');
        } else {
            Swal.fire({
                title: 'SweetAlert2 Test',
                text: 'If you can see this modal, SweetAlert2 is working correctly.',
                icon: 'success'
            });
        }
    };
    document.body.appendChild(sweetAlertButton);
});
</script> 