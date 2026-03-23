<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$_SESSION = ['user_id' => 1];
$_POST = [
    'current_password' => 'wrong',
    'new_password' => 'Abcd@123',
    'confirm_password' => 'Abcd@123'
];

// Include the script and capture output buffer
ob_start();
require_once 'change_password.php';
$output = ob_get_clean();

echo "RAW OUTPUT:\n";
var_dump($output);

?>
