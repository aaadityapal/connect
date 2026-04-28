<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$_SERVER['REQUEST_METHOD'] = 'POST';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

$_POST['employee_id'] = '1';
$_POST['document_type'] = 'Offer Letter';
$_POST['document_name'] = 'Test';
$_POST['document_date'] = '2026-04-17';
$_POST['visibility_mode'] = 'all';

$_FILES['document_file'] = [
    'name' => 'af tio eta (2).pdf',
    'type' => 'application/pdf',
    'tmp_name' => '/tmp/dummy.pdf',
    'error' => UPLOAD_ERR_OK,
    'size' => 1024
];

file_put_contents('/tmp/dummy.pdf', 'dummy pdf content');

ob_start();
include 'upload_employee_document.php';
$output = ob_get_clean();
echo "OUTPUT: \n" . $output;
