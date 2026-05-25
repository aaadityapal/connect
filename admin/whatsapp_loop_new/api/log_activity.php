<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->type)){
    $query = "INSERT INTO user_activities (activity_type, page_name, field_name, activity_data, user_agent, ip_address) 
              VALUES (:type, :page, :field, :data, :ua, :ip)";
    
    $stmt = $conn->prepare($query);

    $type = $data->type;
    $page = $data->page ?? null;
    $field = $data->field ?? null;
    $act_data = isset($data->data) ? json_encode($data->data) : null;
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt->bindParam(":type", $type);
    $stmt->bindParam(":page", $page);
    $stmt->bindParam(":field", $field);
    $stmt->bindParam(":data", $act_data);
    $stmt->bindParam(":ua", $ua);
    $stmt->bindParam(":ip", $ip);

    if($stmt->execute()){
        echo json_encode(array("status" => "logged"));
    } else {
        echo json_encode(array("status" => "error"));
    }
}
?>
