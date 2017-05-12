<?php
// Headers
header('HTTP/1.1 200 OK');
header('Content-Type: application/json');
header('Accept: application/json');

$input = file_get_contents('php://input');
$json = json_decode($input);

// do whatever you need to store test results

$response = array(
    "code" => 200,
    "message" => "Test result saved."
);

echo json_encode($response);
?>