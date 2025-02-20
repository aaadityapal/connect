<?php
require_once('config/db_connect.php');

$sql = "ALTER TABLE salary_details 
        CHANGE COLUMN employee_id user_id INT NOT NULL";

try {
    $pdo->exec($sql);
    echo "Column renamed successfully";
} catch(PDOException $e) {
    echo "Error renaming column: " . $e->getMessage();
} 