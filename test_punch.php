<?php
$data = array(
    'action' => 'punch_in',
    'latitude' => 28.636937,
    'longitude' => 77.302613,
    'accuracy' => 10,
    'device_info' => 'test_device',
    'punch_in_photo' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAAAAAAAD/2wBDAP...'
);

$ch = curl_init('http://localhost/connect/punch.php');
// let's pass a fake session cookie if needed, but wait punch.php reads $_SESSION
// We can just dump the db columns of attendance instead
