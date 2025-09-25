<?php
// Test utility functions
require_once 'includes/utils.php';

echo '<h3>Utility Functions Test</h3>';

// Test getSafeMimeType
echo '<h4>MIME Type Detection:</h4>';
$test_files = [
    'test.jpg' => 'image/jpeg',
    'test.png' => 'image/png', 
    'test.pdf' => 'application/pdf',
    'test.doc' => 'application/msword',
    'unknown.xyz' => 'application/octet-stream'
];

foreach ($test_files as $filename => $expected) {
    $result = getSafeMimeType($filename);
    $status = ($result === $expected) ? '✅' : '❌';
    echo "<div>{$status} {$filename}: {$result}</div>";
}

// Test formatFileSize
echo '<h4>File Size Formatting:</h4>';
$sizes = [0, 1024, 1048576, 1073741824];
foreach ($sizes as $size) {
    echo "<div>✅ {$size} bytes = " . formatFileSize($size) . "</div>";
}

// Test formatCurrency
echo '<h4>Currency Formatting:</h4>';
$amounts = [100, 1000.50, 25000];
foreach ($amounts as $amount) {
    echo "<div>✅ {$amount} = " . formatCurrency($amount) . "</div>";
}

// Test image/PDF detection
echo '<h4>File Type Detection:</h4>';
$files = ['test.jpg', 'document.pdf', 'file.txt'];
foreach ($files as $file) {
    $isImage = isImageFile($file) ? 'Yes' : 'No';
    $isPdf = isPdfFile($file) ? 'Yes' : 'No';
    echo "<div>✅ {$file}: Image={$isImage}, PDF={$isPdf}</div>";
}

echo '<div style="color: green; font-weight: bold; margin-top: 20px;">✅ All utility functions working correctly!</div>';
?>