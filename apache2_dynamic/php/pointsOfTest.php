<?php
// Headers
header('HTTP/1.1 200 OK');
header('Content-Type: application/json');
header('Accept: application/json');

// Points of test
$pots = array(
    "http://pot1.myserver", 
    "http://pot2.myserver"
);
$routes = array(
    "download" => "/download/",
    "upload" => "/upload"
);
$response = array(
    "result" => $pots,
    "routes" => $routes
);

echo json_encode($response);
?>