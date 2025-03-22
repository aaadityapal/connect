<?php
$base_path = dirname(__FILE__);
// Update paths to match your directory structure
$official_docs_path = 'uploads/documents/official';
$personal_docs_path = 'uploads/documents/personal';

echo "Current script path: " . $base_path . "<br>";
echo "Official documents path: " . $official_docs_path . "<br>";
echo "Personal documents path: " . $personal_docs_path . "<br>";

echo "<hr>";
echo "Official documents directory exists: " . (is_dir($official_docs_path) ? 'Yes' : 'No') . "<br>";
echo "Personal documents directory exists: " . (is_dir($personal_docs_path) ? 'Yes' : 'No') . "<br>";

echo "<hr>";
echo "Official documents directory permissions: " . substr(sprintf('%o', fileperms($official_docs_path)), -4) . "<br>";
echo "Personal documents directory permissions: " . substr(sprintf('%o', fileperms($personal_docs_path)), -4) . "<br>";

echo "<hr>";
echo "Official documents directory contents:<br>";
if (is_dir($official_docs_path)) {
    if ($handle = opendir($official_docs_path)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                $file_path = $official_docs_path . '/' . $entry;
                echo "$entry (Size: " . filesize($file_path) . " bytes)<br>";
                echo "File permissions: " . substr(sprintf('%o', fileperms($file_path)), -4) . "<br>";
            }
        }
        closedir($handle);
    }
} else {
    echo "Cannot open official documents directory<br>";
}

echo "<hr>";
echo "Personal documents directory contents:<br>";
if (is_dir($personal_docs_path)) {
    if ($handle = opendir($personal_docs_path)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                $file_path = $personal_docs_path . '/' . $entry;
                echo "$entry (Size: " . filesize($file_path) . " bytes)<br>";
                echo "File permissions: " . substr(sprintf('%o', fileperms($file_path)), -4) . "<br>";
            }
        }
        closedir($handle);
    }
} else {
    echo "Cannot open personal documents directory<br>";
}

// Test file reading
echo "<hr>";
echo "Testing file reading:<br>";

// Try to read a sample file from each directory
if (is_dir($official_docs_path)) {
    $files = scandir($official_docs_path);
    if (!empty($files[2])) { // First file after . and ..
        $test_file = $official_docs_path . '/' . $files[2];
        echo "Can read official document: " . (is_readable($test_file) ? 'Yes' : 'No') . "<br>";
    }
}

if (is_dir($personal_docs_path)) {
    $files = scandir($personal_docs_path);
    if (!empty($files[2])) { // First file after . and ..
        $test_file = $personal_docs_path . '/' . $files[2];
        echo "Can read personal document: " . (is_readable($test_file) ? 'Yes' : 'No') . "<br>";
    }
}

// Check absolute paths
echo "<hr>";
echo "Absolute paths:<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Absolute official path: " . realpath($official_docs_path) . "<br>";
echo "Absolute personal path: " . realpath($personal_docs_path) . "<br>"; 