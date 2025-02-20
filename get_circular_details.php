<?php
include('config.php');

if(isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    $query = "SELECT c.*, u.username as created_by_name 
              FROM circulars c 
              JOIN users u ON c.created_by = u.id 
              WHERE c.id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($circular = $result->fetch_assoc()) {
        echo "<h5>" . htmlspecialchars($circular['title']) . "</h5>";
        echo "<p class='text-muted small'>Created by " . htmlspecialchars($circular['created_by_name']) . 
             " on " . date('d M Y H:i', strtotime($circular['created_at'])) . "</p>";
        echo "<div class='mb-3'>" . nl2br(htmlspecialchars($circular['description'])) . "</div>";
        
        if($circular['attachment_path']) {
            echo "<div class='mb-3'>";
            echo "<a href='" . htmlspecialchars($circular['attachment_path']) . "' class='btn btn-sm btn-primary'>";
            echo "<i class='bi bi-download me-1'></i>Download Attachment</a>";
            echo "</div>";
        }
        
        echo "<div class='text-muted small'>Valid until: " . date('d M Y', strtotime($circular['valid_until'])) . "</div>";
    }
}
?>
