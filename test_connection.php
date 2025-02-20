<?php
require_once 'test_config.php';

if (isset($conn) && $conn->ping()) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed!";
}
?> 