<?php
$userId = "testuser";
$targetDir = dirname(__FILE__) . "/uploads/users/documents/$userId/";
echo "Dir: $targetDir\n";
if (!is_dir($targetDir)) {
    if (mkdir($targetDir, 0777, true)) {
        echo "Successfully created test folder.";
    } else {
        echo "Failed to create test folder.";
    }
} else {
    echo "Test folder already exists.";
}
?>
