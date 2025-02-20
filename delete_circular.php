<?php
include('config.php');

if(isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    // First get the attachment path if any
    $query = "SELECT attachment_path FROM circulars WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($circular = $result->fetch_assoc()) {
        // Delete the file if it exists
        if($circular['attachment_path'] && file_exists($circular['attachment_path'])) {
            unlink($circular['attachment_path']);
        }
    }
    
    // Delete the circular record
    $query = "DELETE FROM circulars WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
}
?>
