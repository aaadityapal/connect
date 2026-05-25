<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->id)){
    try {
        $conn->beginTransaction();

        // 1. Delete associations first (if not cascading)
        $query1 = "DELETE FROM client_tags WHERE client_id = :id";
        $stmt1 = $conn->prepare($query1);
        $stmt1->bindParam(":id", $data->id);
        $stmt1->execute();

        // 2. Delete the client
        $query2 = "DELETE FROM clients WHERE id = :id";
        $stmt2 = $conn->prepare($query2);
        $stmt2->bindParam(":id", $data->id);
        
        if($stmt2->execute()){
            $conn->commit();
            echo json_encode(array("message" => "Client was deleted.", "success" => true));
        } else {
            $conn->rollBack();
            echo json_encode(array("message" => "Unable to delete client.", "success" => false));
        }
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(array("message" => "Error: " . $e->getMessage(), "success" => false));
    }
} else {
    echo json_encode(array("message" => "Incomplete data. ID missing.", "success" => false));
}
?>
