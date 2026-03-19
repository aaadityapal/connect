<?php
session_start();
$_SESSION['user_id'] = 1;
?>
<form action="/connect/studio_users/api/upload_profile_pic.php" method="post" enctype="multipart/form-data">
    Select image to upload:
    <input type="file" name="profile_pic" id="profile_pic">
    <input type="submit" value="Upload Image" name="submit">
</form>
