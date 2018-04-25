<?php

$ip = "";
header('Content-Type: text/plain; charset=utf-8');
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['X-Real-IP'])) {
    $ip = $_SERVER['X-Real-IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

$ip = preg_replace("/^::ffff:/", "", $ip);

if (strpos($ip, '::1') !== false) {
    echo $ip . " - localhost ipv6 access";
    die();
}
if (strpos($ip, '127.0.0') !== false) {
    echo $ip . " - localhost ipv4 access";
    die();
}

/**
 * Optimized algorithm from http://www.codexworld.com
 *
 * @param float $latitudeFrom
 * @param float $longitudeFrom
 * @param float $latitudeTo
 * @param float $longitudeTo
 *
 * @return float [km]
 */
function distance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo) {
    $rad = M_PI / 180;
    $theta = $longitudeFrom - $longitudeTo;
    $dist = sin($latitudeFrom * $rad) * sin($latitudeTo * $rad) + cos($latitudeFrom * $rad) * cos($latitudeTo * $rad) * cos($theta * $rad);
    return acos($dist) / $rad * 60 * 1.853;
}

if (isset($_GET["isp"])) {
    $isp = "";
    try {
        $json = file_get_contents("https://ipinfo.io/" . $ip . "/json");
        $details = json_decode($json, true);
        if (array_key_exists("org", $details))
            $isp .= $details["org"];
        else
            $isp .= "Unknown ISP";
        if (array_key_exists("country", $details))
            $isp .= ", " . $details["country"];
        $clientLoc = NULL;
        $serverLoc = NULL;
        if (array_key_exists("loc", $details))
            $clientLoc = $details["loc"];
        if (isset($_GET["distance"])) {
            if ($clientLoc) {
                $json = file_get_contents("https://ipinfo.io/json");
                $details = json_decode($json, true);
                if (array_key_exists("loc", $details))
                    $serverLoc = $details["loc"];
                if ($serverLoc) {
                    try {
                        $clientLoc = explode(",", $clientLoc);
                        $serverLoc = explode(",", $serverLoc);
                        $dist = distance($clientLoc[0], $clientLoc[1], $serverLoc[0], $serverLoc[1]);
                        if ($_GET["distance"] == "mi") {
                            $dist /= 1.609344;
                            $dist = round($dist, -1);
                            if ($dist < 15)
                                $dist = "<15";
                            $isp .= " (" . $dist . " mi)";
                        }else if ($_GET["distance"] == "km") {
                            $dist = round($dist, -1);
                            if ($dist < 20)
                                $dist = "<20";
                            $isp .= " (" . $dist . " km)";
                        }
                    } catch (Exception $e) {
                        
                    }
                }
            }
        }
    } catch (Exception $ex) {
        $isp = "Unknown ISP";
    }
    echo $ip . " - " . $isp;
} else {
    echo $ip;
}
?>
