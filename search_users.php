<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Senior Manager (Studio)') {
    exit('Unauthorized');
}

if (isset($_POST['search'])) {
    $search = '%' . $_POST['search'] . '%';
    
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username LIKE ? AND id != ?");
    $stmt->execute([$search, $_SESSION['user_id']]);
    
    while ($row = $stmt->fetch()) {
        echo '<a href="#" class="list-group-item list-group-item-action user-result" data-user-id="' . 
             htmlspecialchars($row['id']) . '">' . 
             htmlspecialchars($row['username']) . '</a>';
    }
}
?> 