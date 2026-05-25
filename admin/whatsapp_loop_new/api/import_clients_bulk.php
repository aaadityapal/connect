<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->clients) && is_array($data->clients)) {
    $imported_count = 0;
    $skipped_count = 0;
    $skipped_details = [];
    
    // Resolve Tag IDs first if any tags were passed
    $tag_ids = [];
    if (!empty($data->tags) && is_array($data->tags)) {
        foreach($data->tags as $tag_name) {
            $tag_query = "SELECT id FROM tags WHERE name = :tag_name";
            $tag_stmt = $conn->prepare($tag_query);
            $tag_stmt->bindParam(":tag_name", $tag_name);
            $tag_stmt->execute();
            $tag_row = $tag_stmt->fetch(PDO::FETCH_ASSOC);
            if($tag_row){
                $tag_ids[] = $tag_row['id'];
            } else {
                $insert_tag_query = "INSERT INTO tags (name) VALUES (:tag_name)";
                $insert_tag_stmt = $conn->prepare($insert_tag_query);
                $insert_tag_stmt->bindParam(":tag_name", $tag_name);
                $insert_tag_stmt->execute();
                $tag_ids[] = $conn->lastInsertId();
            }
        }
    }
    
    
    // Prepare statements outside loop for performance
    $tag_query = "SELECT id FROM tags WHERE name = :tag_name";
    $tag_stmt = $conn->prepare($tag_query);
    
    $insert_tag_query = "INSERT INTO tags (name) VALUES (:tag_name)";
    $insert_tag_stmt = $conn->prepare($insert_tag_query);
    
    $tag_cache = []; // Cache to avoid duplicate DB queries for the same tag
    $check_query = "SELECT id FROM clients WHERE phone = :phone";
    $check_stmt = $conn->prepare($check_query);
    
    $insert_query = "INSERT INTO clients (name, phone, email, notes) VALUES (:name, :phone, :email, :notes)";
    $insert_stmt = $conn->prepare($insert_query);
    
    $link_query = "INSERT INTO client_tags (client_id, tag_id) VALUES (:client_id, :tag_id)";
    $link_stmt = $conn->prepare($link_query);

    foreach($data->clients as $client) {
        if(empty($client->phone)) continue;
        
        // Basic check if client already exists
        $check_stmt->execute([':phone' => $client->phone]);
        if($check_stmt->rowCount() > 0) {
            $skipped_count++;
            $skipped_details[] = array("name" => !empty($client->name) ? $client->name : 'Unknown', "phone" => $client->phone);
            continue; // Skip existing client
        }

        // Insert client
        $name = !empty($client->name) ? $client->name : 'Unknown';
        $email = !empty($client->email) ? $client->email : '';
        $notes = 'Imported via Bulk Upload';
        
        $insert_stmt->bindParam(":name", $name);
        $insert_stmt->bindParam(":phone", $client->phone);
        $insert_stmt->bindParam(":email", $email);
        $insert_stmt->bindParam(":notes", $notes);
        
        if($insert_stmt->execute()){
            $new_id = $conn->lastInsertId();
            $imported_count++;
            
            // Combine global tag IDs with client-specific tags
            $client_tag_ids = $tag_ids; // Start with global tags
            
            if (!empty($client->tags) && is_array($client->tags)) {
                foreach($client->tags as $tag_name) {
                    $tag_name = trim($tag_name);
                    if(empty($tag_name)) continue;
                    
                    if (isset($tag_cache[$tag_name])) {
                        $client_tag_ids[] = $tag_cache[$tag_name];
                    } else {
                        $tag_stmt->bindParam(":tag_name", $tag_name);
                        $tag_stmt->execute();
                        $tag_row = $tag_stmt->fetch(PDO::FETCH_ASSOC);
                        if($tag_row){
                            $tid = $tag_row['id'];
                            $client_tag_ids[] = $tid;
                            $tag_cache[$tag_name] = $tid;
                        } else {
                            $insert_tag_stmt->bindParam(":tag_name", $tag_name);
                            $insert_tag_stmt->execute();
                            $tid = $conn->lastInsertId();
                            $client_tag_ids[] = $tid;
                            $tag_cache[$tag_name] = $tid;
                        }
                    }
                }
            }
            
            $client_tag_ids = array_unique($client_tag_ids);
            
            // Assign tags
            foreach($client_tag_ids as $tid) {
                $link_stmt->execute([
                    ':client_id' => $new_id,
                    ':tag_id' => $tid
                ]);
            }
        }
    }

    echo json_encode(array(
        "success" => true, 
        "message" => "Import completed.", 
        "imported_count" => $imported_count,
        "skipped_count" => $skipped_count,
        "skipped_details" => $skipped_details
    ));
} else {
    echo json_encode(array("success" => false, "message" => "No client data provided."));
}
?>
