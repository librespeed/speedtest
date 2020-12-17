<?php

require_once 'telemetry_db.php';

error_reporting(0);
putenv('GDFONTPATH='.realpath('.'));

/**
 * @param string $name
 *
 * @return string|null
 */
function tryFont($name)
{
    if (is_array(imageftbbox(12, 0, $name, 'M'))) {
        return $name;
    }

    $fullPathToFont = realpath('.').'/'.$name.'.ttf';
    if (is_array(imageftbbox(12, 0, $fullPathToFont, 'M'))) {
        return $fullPathToFont;
    }

    return null;
}

/**
 * @param int|float $d
 *
 * @return string
 */
function format($d)
{
    if ($d < 10) {
        return number_format($d, 2, '.', '');
    }
    if ($d < 100) {
        return number_format($d, 1, '.', '');
    }

    return number_format($d, 0, '.', '');
}

/**
 * @param array $speedtest
 *
 * @return array
 */
function formatSpeedtestDataForImage($speedtest)
{
    // format values for the image
    $speedtest['dl'] = format($speedtest['dl']);
    $speedtest['ul'] = format($speedtest['ul']);
    $speedtest['ping'] = format($speedtest['ping']);
    $speedtest['jit'] = format($speedtest['jitter']);
    $speedtest['timestamp'] = $speedtest['timestamp'];

    $ispinfo = json_decode($speedtest['ispinfo'], true)['processedString'];
    $dash = strpos($ispinfo, '-');
    if ($dash !== false) {
        $ispinfo = substr($ispinfo, $dash + 2);
        $par = strrpos($ispinfo, '(');
        if ($par !== false) {
            $ispinfo = substr($ispinfo, 0, $par);
        }
    } else {
        $ispinfo = '';
    }

    $speedtest['ispinfo'] = $ispinfo;

    return $speedtest;
}

/**
 * @param array $speedtest
 *
 * @return void
 */
function drawImage($speedtest)
{
    // format values for the image
    $data = formatSpeedtestDataForImage($speedtest);
    $dl = $data['dl'];
    $ul = $data['ul'];
    $ping = $data['ping'];
    $jit = $data['jitter'];
    $ispinfo = $data['ispinfo'];
    $timestamp = $data['timestamp'];

    // initialize the image
    $SCALE = 1.25;
    $SMALL_SEP = 8 * $SCALE;
    $WIDTH = 400 * $SCALE;
    $HEIGHT = 229 * $SCALE;
    $im = imagecreatetruecolor($WIDTH, $HEIGHT);
    $BACKGROUND_COLOR = imagecolorallocate($im, 255, 255, 255);

    // configure fonts
    $FONT_LABEL = tryFont('OpenSans-Semibold');
    $FONT_LABEL_SIZE = 14 * $SCALE;
    $FONT_LABEL_SIZE_BIG = 16 * $SCALE;

    $FONT_METER = tryFont('OpenSans-Light');
    $FONT_METER_SIZE = 20 * $SCALE;
    $FONT_METER_SIZE_BIG = 22 * $SCALE;

    $FONT_MEASURE = tryFont('OpenSans-Semibold');
    $FONT_MEASURE_SIZE = 12 * $SCALE;
    $FONT_MEASURE_SIZE_BIG = 12 * $SCALE;

    $FONT_ISP = tryFont('OpenSans-Semibold');
    $FONT_ISP_SIZE = 9 * $SCALE;

    $FONT_TIMESTAMP = tryFont("OpenSans-Light");
    $FONT_TIMESTAMP_SIZE = 8 * $SCALE;

    $FONT_WATERMARK = tryFont('OpenSans-Light');
    $FONT_WATERMARK_SIZE = 8 * $SCALE;

    // configure text colors
    $TEXT_COLOR_LABEL = imagecolorallocate($im, 40, 40, 40);
    $TEXT_COLOR_PING_METER = imagecolorallocate($im, 170, 96, 96);
    $TEXT_COLOR_JIT_METER = imagecolorallocate($im, 170, 96, 96);
    $TEXT_COLOR_DL_METER = imagecolorallocate($im, 96, 96, 170);
    $TEXT_COLOR_UL_METER = imagecolorallocate($im, 96, 96, 96);
    $TEXT_COLOR_MEASURE = imagecolorallocate($im, 40, 40, 40);
    $TEXT_COLOR_ISP = imagecolorallocate($im, 40, 40, 40);
    $SEPARATOR_COLOR = imagecolorallocate($im, 192, 192, 192);
    $TEXT_COLOR_TIMESTAMP = imagecolorallocate($im, 160, 160, 160);
    $TEXT_COLOR_WATERMARK = imagecolorallocate($im, 160, 160, 160);

    // configure positioning or the different parts on the image
    $POSITION_X_PING = 125 * $SCALE;
    $POSITION_Y_PING_LABEL = 24 * $SCALE;
    $POSITION_Y_PING_METER = 60 * $SCALE;
    $POSITION_Y_PING_MEASURE = 60 * $SCALE;

    $POSITION_X_JIT = 275 * $SCALE;
    $POSITION_Y_JIT_LABEL = 24 * $SCALE;
    $POSITION_Y_JIT_METER = 60 * $SCALE;
    $POSITION_Y_JIT_MEASURE = 60 * $SCALE;

    $POSITION_X_DL = 120 * $SCALE;
    $POSITION_Y_DL_LABEL = 105 * $SCALE;
    $POSITION_Y_DL_METER = 143 * $SCALE;
    $POSITION_Y_DL_MEASURE = 169 * $SCALE;

    $POSITION_X_UL = 280 * $SCALE;
    $POSITION_Y_UL_LABEL = 105 * $SCALE;
    $POSITION_Y_UL_METER = 143 * $SCALE;
    $POSITION_Y_UL_MEASURE = 169 * $SCALE;

    $POSITION_X_ISP = 4 * $SCALE;
    $POSITION_Y_ISP = 205 * $SCALE;

    $SEPARATOR_Y = 211 * $SCALE;

    $POSITION_X_TIMESTAMP= 4 * $SCALE;
    $POSITION_Y_TIMESTAMP = 223 * $SCALE;

    $POSITION_Y_WATERMARK = 223 * $SCALE;

    // configure labels
    $MBPS_TEXT = 'Mbps';
    $MS_TEXT = 'ms';
    $PING_TEXT = 'Ping';
    $JIT_TEXT = 'Jitter';
    $DL_TEXT = 'Download';
    $UL_TEXT = 'Upload';
    $WATERMARK_TEXT = 'LibreSpeed';

    // create text boxes for each part of the image
    $mbpsBbox = imageftbbox($FONT_MEASURE_SIZE_BIG, 0, $FONT_MEASURE, $MBPS_TEXT);
    $msBbox = imageftbbox($FONT_MEASURE_SIZE, 0, $FONT_MEASURE, $MS_TEXT);
    $pingBbox = imageftbbox($FONT_LABEL_SIZE, 0, $FONT_LABEL, $PING_TEXT);
    $pingMeterBbox = imageftbbox($FONT_METER_SIZE, 0, $FONT_METER, $ping);
    $jitBbox = imageftbbox($FONT_LABEL_SIZE, 0, $FONT_LABEL, $JIT_TEXT);
    $jitMeterBbox = imageftbbox($FONT_METER_SIZE, 0, $FONT_METER, $jit);
    $dlBbox = imageftbbox($FONT_LABEL_SIZE_BIG, 0, $FONT_LABEL, $DL_TEXT);
    $dlMeterBbox = imageftbbox($FONT_METER_SIZE_BIG, 0, $FONT_METER, $dl);
    $ulBbox = imageftbbox($FONT_LABEL_SIZE_BIG, 0, $FONT_LABEL, $UL_TEXT);
    $ulMeterBbox = imageftbbox($FONT_METER_SIZE_BIG, 0, $FONT_METER, $ul);
    $watermarkBbox = imageftbbox($FONT_WATERMARK_SIZE, 0, $FONT_WATERMARK, $WATERMARK_TEXT);
    $POSITION_X_WATERMARK = $WIDTH - $watermarkBbox[4] - 4 * $SCALE;

    // put the parts together to draw the image
    imagefilledrectangle($im, 0, 0, $WIDTH, $HEIGHT, $BACKGROUND_COLOR);
    // ping
    imagefttext($im, $FONT_LABEL_SIZE, 0, $POSITION_X_PING - $pingBbox[4] / 2, $POSITION_Y_PING_LABEL, $TEXT_COLOR_LABEL, $FONT_LABEL, $PING_TEXT);
    imagefttext($im, $FONT_METER_SIZE, 0, $POSITION_X_PING - $pingMeterBbox[4] / 2 - $msBbox[4] / 2 - $SMALL_SEP / 2, $POSITION_Y_PING_METER, $TEXT_COLOR_PING_METER, $FONT_METER, $ping);
    imagefttext($im, $FONT_MEASURE_SIZE, 0, $POSITION_X_PING + $pingMeterBbox[4] / 2 + $SMALL_SEP / 2 - $msBbox[4] / 2, $POSITION_Y_PING_MEASURE, $TEXT_COLOR_MEASURE, $FONT_MEASURE, $MS_TEXT);
    // jitter
    imagefttext($im, $FONT_LABEL_SIZE, 0, $POSITION_X_JIT - $jitBbox[4] / 2, $POSITION_Y_JIT_LABEL, $TEXT_COLOR_LABEL, $FONT_LABEL, $JIT_TEXT);
    imagefttext($im, $FONT_METER_SIZE, 0, $POSITION_X_JIT - $jitMeterBbox[4] / 2 - $msBbox[4] / 2 - $SMALL_SEP / 2, $POSITION_Y_JIT_METER, $TEXT_COLOR_JIT_METER, $FONT_METER, $jit);
    imagefttext($im, $FONT_MEASURE_SIZE, 0, $POSITION_X_JIT + $jitMeterBbox[4] / 2 + $SMALL_SEP / 2 - $msBbox[4] / 2, $POSITION_Y_JIT_MEASURE, $TEXT_COLOR_MEASURE, $FONT_MEASURE, $MS_TEXT);
    // dl
    imagefttext($im, $FONT_LABEL_SIZE_BIG, 0, $POSITION_X_DL - $dlBbox[4] / 2, $POSITION_Y_DL_LABEL, $TEXT_COLOR_LABEL, $FONT_LABEL, $DL_TEXT);
    imagefttext($im, $FONT_METER_SIZE_BIG, 0, $POSITION_X_DL - $dlMeterBbox[4] / 2, $POSITION_Y_DL_METER, $TEXT_COLOR_DL_METER, $FONT_METER, $dl);
    imagefttext($im, $FONT_MEASURE_SIZE_BIG, 0, $POSITION_X_DL - $mbpsBbox[4] / 2, $POSITION_Y_DL_MEASURE, $TEXT_COLOR_MEASURE, $FONT_MEASURE, $MBPS_TEXT);
    // ul
    imagefttext($im, $FONT_LABEL_SIZE_BIG, 0, $POSITION_X_UL - $ulBbox[4] / 2, $POSITION_Y_UL_LABEL, $TEXT_COLOR_LABEL, $FONT_LABEL, $UL_TEXT);
    imagefttext($im, $FONT_METER_SIZE_BIG, 0, $POSITION_X_UL - $ulMeterBbox[4] / 2, $POSITION_Y_UL_METER, $TEXT_COLOR_UL_METER, $FONT_METER, $ul);
    imagefttext($im, $FONT_MEASURE_SIZE_BIG, 0, $POSITION_X_UL - $mbpsBbox[4] / 2, $POSITION_Y_UL_MEASURE, $TEXT_COLOR_MEASURE, $FONT_MEASURE, $MBPS_TEXT);
    // isp
    imagefttext($im, $FONT_ISP_SIZE, 0, $POSITION_X_ISP, $POSITION_Y_ISP, $TEXT_COLOR_ISP, $FONT_ISP, $ispinfo);
    // separator
    imagefilledrectangle($im, 0, $SEPARATOR_Y, $WIDTH, $SEPARATOR_Y, $SEPARATOR_COLOR);
    // timestamp
    imagefttext($im, $FONT_TIMESTAMP_SIZE, 0, $POSITION_X_TIMESTAMP, $POSITION_Y_TIMESTAMP, $TEXT_COLOR_TIMESTAMP, $FONT_TIMESTAMP, $timestamp);
    // watermark
    imagefttext($im, $FONT_WATERMARK_SIZE, 0, $POSITION_X_WATERMARK, $POSITION_Y_WATERMARK, $TEXT_COLOR_WATERMARK, $FONT_WATERMARK, $WATERMARK_TEXT);

    // send the image to the browser
    header('Content-Type: image/png');
    imagepng($im);
}

$speedtest = getSpeedtestUserById($_GET['id']);
if (!is_array($speedtest)) {
    exit(1);
}

drawImage($speedtest);
