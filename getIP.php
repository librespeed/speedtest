<?php
/*
	This script detects the client's IP address and fetches ISP info from ipinfo.io/
	Output from this script is a JSON string composed of 2 objects: a string called processedString which contains the combined IP, ISP, Contry and distance as it can be presented to the user; and an object called rawIspInfo which contains the raw data from ipinfo.io (will be empty if isp detection is disabled).
	Client side, the output of this script can be treated as JSON or as regular text. If the output is regular text, it will be shown to the user as is.
*/
error_reporting(0);
$ip = "";
header('Content-Type: application/json; charset=utf-8');
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['X-Real-IP'])) {
    $ip = $_SERVER['X-Real-IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    $ip = preg_replace("/,.*/", "", $ip); # hosts are comma-separated, client is first
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

$ip = preg_replace("/^::ffff:/", "", $ip);

if (strpos($ip, '::1') !== false) {
    echo json_encode(['processedString' => $ip . " - localhost ipv6 access", 'rawIspInfo' => ""]);
    die();
}
if (strpos($ip, '127.0.0') !== false) {
    echo json_encode(['processedString' => $ip . " - localhost ipv4 access", 'rawIspInfo' => ""]);
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
	$rawIspInfo=null;
    try {
        $json = file_get_contents("https://ipinfo.io/" . $ip . "/json");
        $details = json_decode($json, true);
		$rawIspInfo=$details;
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
    echo json_encode(['processedString' => $ip . " - " . $isp, 'rawIspInfo' => $rawIspInfo]);
} else {
    echo json_encode(['processedString' => $ip, 'rawIspInfo' => ""]);
}
?>
