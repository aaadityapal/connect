<?php
$data = array(
    'id' => 1,
    'role' => 'Senior Manager (Studio)',
    'reporting_manager' => ''
);
$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents('http://localhost/connect/manager_pages/employees_profile/api/update_employee_profile.php', false, $context);
if ($result === FALSE) {
    echo "Error calling API\n";
} else {
    echo $result . "\n";
}
