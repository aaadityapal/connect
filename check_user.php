<?php
session_start();
echo "Current User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set');
?> 