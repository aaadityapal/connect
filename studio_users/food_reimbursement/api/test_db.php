<?php
require_once '../../config/db_connect.php';
$stmt = $pdo->query("SELECT id, attendance_id, claim_status, payment_status, amount FROM food_reimbursement_claims");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
