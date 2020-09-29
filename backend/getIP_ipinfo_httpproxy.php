<?php
$IPINFO_PROXYADDRESS=""; // leave proxy address blank for direct
$IPINFO_PROXYPORT="800";

$streamContext = array(
    "ssl"=>array(
        "verify_peer"=>false,
        "verify_peer_name"=>false,
    ),
    'http' => array(
        'proxy'           => 'tcp://'.$IPINFO_PROXYADDRESS.':'.$IPINFO_PROXYPORT,
        'request_fulluri' => true,
    ),
);
$IPINFO_HTTPPROXY= stream_context_create($streamContext);
?>
