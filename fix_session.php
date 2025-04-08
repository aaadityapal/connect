<?php
// WARNING: This is a temporary debugging tool only
// It should be removed in production

session_start();

echo "<h1>Assignment User Switcher</h1>";
echo "<p>Current user ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "</p>";

if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $_SESSION['user_id'] = $user_id;
    echo "<p style='color:green'>Successfully switched to user ID: $user_id</p>";
}

echo "<h2>Switch to User with Assignments</h2>";
echo "<p>Based on the database, users 15 and 21 have assignment records.</p>";

echo "<p><a href='fix_session.php?user_id=15' style='padding:10px; background:#4a6cf7; color:white; text-decoration:none; border-radius:4px; margin-right:10px;'>Switch to User 15</a>";
echo "<a href='fix_session.php?user_id=21' style='padding:10px; background:#28a745; color:white; text-decoration:none; border-radius:4px;'>Switch to User 21</a></p>";

echo "<p><a href='index.php' style='color:#4a6cf7;'>Return to Dashboard</a></p>";
?> 