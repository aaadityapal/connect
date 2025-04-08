<?php
// CAUTION: This is for testing purposes only. Should be removed in production.
session_start();

if (isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $_SESSION['user_id'] = $user_id;
    echo "Switched user ID to: " . $user_id;
    echo "<br><a href='test-assignments.php'>Back to assignment test</a>";
    echo "<br><a href='index.php'>Go to dashboard</a>";
} else {
    echo "No user ID provided";
    echo "<br><a href='test-assignments.php'>Back to assignment test</a>";
}
?> 