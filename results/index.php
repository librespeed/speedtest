<?php
$WATERMARK_TEXT="LibreSpeed";

error_reporting(0);
putenv('GDFONTPATH=' . realpath('.'));
function tryFont($name){
	$rp=realpath('.');
	if(imageftbbox(12,0,$name,"M")[5]==0){
		$name=$rp."/".$name.".ttf";
		if(imageftbbox(12,0,$name,"M")[5]==0){
			return null;
		}
	}
	return $name;
}
function format($d){
    if($d<10) return number_format($d,2,".","");
    if($d<100) return number_format($d,1,".","");
    return number_format($d,0,".","");
}

$SCALE=1.25;
$WIDTH=400*$SCALE;
$HEIGHT=229*$SCALE;
$im=imagecreatetruecolor($WIDTH,$HEIGHT);
$BACKGROUND_COLOR=imagecolorallocate($im,255,255,255);
$FONT_LABEL=tryFont("OpenSans-Semibold");
$FONT_LABEL_SIZE=14*$SCALE;
$FONT_LABEL_SIZE_BIG=16*$SCALE;
$FONT_METER=tryFont("OpenSans-Light");
$FONT_METER_SIZE=20*$SCALE;
$FONT_METER_SIZE_BIG=22*$SCALE;
$FONT_MEASURE=tryFont("OpenSans-Semibold");
$FONT_MEASURE_SIZE=12*$SCALE;
$FONT_MEASURE_SIZE_BIG=12*$SCALE;
$FONT_ISP=tryFont("OpenSans-Semibold");
$FONT_ISP_SIZE=9*$SCALE;
$FONT_WATERMARK=tryFont("OpenSans-Light");
$FONT_WATERMARK_SIZE=8*$SCALE;
$TEXT_COLOR_LABEL=imagecolorallocate($im,40,40,40);
$TEXT_COLOR_DL_METER=imagecolorallocate($im,96,96,170);
$TEXT_COLOR_UL_METER=imagecolorallocate($im,96,96,96);
$TEXT_COLOR_PING_METER=imagecolorallocate($im,170,96,96);
$TEXT_COLOR_JIT_METER=imagecolorallocate($im,170,96,96);
$TEXT_COLOR_MEASURE=imagecolorallocate($im,40,40,40);
$TEXT_COLOR_ISP=imagecolorallocate($im,40,40,40);
$TEXT_COLOR_WATERMARK=imagecolorallocate($im,160,160,160);
$POSITION_Y_DL_LABEL=105*$SCALE;
$POSITION_Y_UL_LABEL=105*$SCALE;
$POSITION_Y_PING_LABEL=24*$SCALE;
$POSITION_Y_JIT_LABEL=24*$SCALE;
$POSITION_Y_DL_METER=143*$SCALE;
$POSITION_Y_UL_METER=143*$SCALE;
$POSITION_Y_PING_METER=60*$SCALE;
$POSITION_Y_JIT_METER=60*$SCALE;
$POSITION_Y_DL_MEASURE=169*$SCALE;
$POSITION_Y_UL_MEASURE=169*$SCALE;
$POSITION_Y_PING_MEASURE=60*$SCALE;
$POSITION_Y_JIT_MEASURE=60*$SCALE;
$POSITION_Y_ISP=205*$SCALE;
$POSITION_X_DL=120*$SCALE;
$POSITION_X_UL=280*$SCALE;
$POSITION_X_PING=125*$SCALE;
$POSITION_X_JIT=275*$SCALE;
$POSITION_X_ISP=4*$SCALE;
$SMALL_SEP=8*$SCALE;
$SEPARATOR_Y=211*$SCALE;
$SEPARATOR_COLOR=imagecolorallocate($im,192,192,192);
$POSITION_Y_WATERMARK=223*$SCALE;
$DL_TEXT="Download";
$UL_TEXT="Upload";
$PING_TEXT="Ping";
$JIT_TEXT="Jitter";
$MBPS_TEXT="Mbps";
$MS_TEXT="ms";

$id=$_GET["id"];
include_once('telemetry_settings.php');
require 'idObfuscation.php';
if($enable_id_obfuscation) $id=deobfuscateId($id);
$conn=null; $q=null;
$ispinfo=null; $dl=null; $ul=null; $ping=null; $jit=null;
if($db_type=="mysql"){
	$conn = new mysqli($MySql_hostname, $MySql_username, $MySql_password, $MySql_databasename, $MySql_port);
	$q = $conn->prepare("select ispinfo,dl,ul,ping,jitter from speedtest_users where id=?");
	$q->bind_param("i",$id);
	$q->execute();
	$q->bind_result($ispinfo,$dl,$ul,$ping,$jit);
	$q->fetch();
}else if($db_type=="sqlite"){
	$conn = new PDO("sqlite:$Sqlite_db_file") or die();
	$q=$conn->prepare("select ispinfo,dl,ul,ping,jitter from speedtest_users where id=?") or die();
	$q->execute(array($id)) or die();
	$row=$q->fetch() or die();
	$ispinfo=$row["ispinfo"];
	$dl=$row["dl"];
	$ul=$row["ul"];
	$ping=$row["ping"];
	$jit=$row["jitter"];
	$conn=null;
}else if($db_type=="postgresql"){
    $conn_host = "host=$PostgreSql_hostname";
    $conn_db = "dbname=$PostgreSql_databasename";
    $conn_user = "user=$PostgreSql_username";
    $conn_password = "password=$PostgreSql_password";
    $conn = new PDO("pgsql:$conn_host;$conn_db;$conn_user;$conn_password") or die();
	$q=$conn->prepare("select ispinfo,dl,ul,ping,jitter from speedtest_users where id=?") or die();
	$q->execute(array($id)) or die();
	$row=$q->fetch() or die();
	$ispinfo=$row["ispinfo"];
	$dl=$row["dl"];
	$ul=$row["ul"];
	$ping=$row["ping"];
	$jit=$row["jitter"];
	$conn=null;
}else die();

$dl=format($dl);
$ul=format($ul);
$ping=format($ping);
$jit=format($jit);

$ispinfo=json_decode($ispinfo,true)["processedString"];
$dash=strpos($ispinfo,"-");
if(!($dash===FALSE)){
	$ispinfo=substr($ispinfo,$dash+2);
	$par=strrpos($ispinfo,"(");
	if(!($par===FALSE)) $ispinfo=substr($ispinfo,0,$par);
}else $ispinfo="";

$dlBbox=imageftbbox($FONT_LABEL_SIZE_BIG,0,$FONT_LABEL,$DL_TEXT);
$ulBbox=imageftbbox($FONT_LABEL_SIZE_BIG,0,$FONT_LABEL,$UL_TEXT);
$pingBbox=imageftbbox($FONT_LABEL_SIZE,0,$FONT_LABEL,$PING_TEXT);
$jitBbox=imageftbbox($FONT_LABEL_SIZE,0,$FONT_LABEL,$JIT_TEXT);
$dlMeterBbox=imageftbbox($FONT_METER_SIZE_BIG,0,$FONT_METER,$dl);
$ulMeterBbox=imageftbbox($FONT_METER_SIZE_BIG,0,$FONT_METER,$ul);
$pingMeterBbox=imageftbbox($FONT_METER_SIZE,0,$FONT_METER,$ping);
$jitMeterBbox=imageftbbox($FONT_METER_SIZE,0,$FONT_METER,$jit);
$mbpsBbox=imageftbbox($FONT_MEASURE_SIZE_BIG,0,$FONT_MEASURE,$MBPS_TEXT);
$msBbox=imageftbbox($FONT_MEASURE_SIZE,0,$FONT_MEASURE,$MS_TEXT);
$watermarkBbox=imageftbbox($FONT_WATERMARK_SIZE,0,$FONT_WATERMARK,$WATERMARK_TEXT);
$POSITION_X_WATERMARK=$WIDTH-$watermarkBbox[4]-4*$SCALE;

imagefilledrectangle($im, 0, 0, $WIDTH, $HEIGHT, $BACKGROUND_COLOR);
imagefttext($im,$FONT_LABEL_SIZE_BIG,0,$POSITION_X_DL-$dlBbox[4]/2,$POSITION_Y_DL_LABEL,$TEXT_COLOR_LABEL,$FONT_LABEL,$DL_TEXT);
imagefttext($im,$FONT_LABEL_SIZE_BIG,0,$POSITION_X_UL-$ulBbox[4]/2,$POSITION_Y_UL_LABEL,$TEXT_COLOR_LABEL,$FONT_LABEL,$UL_TEXT);
imagefttext($im,$FONT_LABEL_SIZE,0,$POSITION_X_PING-$pingBbox[4]/2,$POSITION_Y_PING_LABEL,$TEXT_COLOR_LABEL,$FONT_LABEL,$PING_TEXT);
imagefttext($im,$FONT_LABEL_SIZE,0,$POSITION_X_JIT-$jitBbox[4]/2,$POSITION_Y_JIT_LABEL,$TEXT_COLOR_LABEL,$FONT_LABEL,$JIT_TEXT);
imagefttext($im,$FONT_METER_SIZE_BIG,0,$POSITION_X_DL-$dlMeterBbox[4]/2,$POSITION_Y_DL_METER,$TEXT_COLOR_DL_METER,$FONT_METER,$dl);
imagefttext($im,$FONT_METER_SIZE_BIG,0,$POSITION_X_UL-$ulMeterBbox[4]/2,$POSITION_Y_UL_METER,$TEXT_COLOR_UL_METER,$FONT_METER,$ul);
imagefttext($im,$FONT_METER_SIZE,0,$POSITION_X_PING-$pingMeterBbox[4]/2-$msBbox[4]/2-$SMALL_SEP/2,$POSITION_Y_PING_METER,$TEXT_COLOR_PING_METER,$FONT_METER,$ping);
imagefttext($im,$FONT_METER_SIZE,0,$POSITION_X_JIT-$jitMeterBbox[4]/2-$msBbox[4]/2-$SMALL_SEP/2,$POSITION_Y_JIT_METER,$TEXT_COLOR_JIT_METER,$FONT_METER,$jit);
imagefttext($im,$FONT_MEASURE_SIZE_BIG,0,$POSITION_X_DL-$mbpsBbox[4]/2,$POSITION_Y_DL_MEASURE,$TEXT_COLOR_MEASURE,$FONT_MEASURE,$MBPS_TEXT);
imagefttext($im,$FONT_MEASURE_SIZE_BIG,0,$POSITION_X_UL-$mbpsBbox[4]/2,$POSITION_Y_UL_MEASURE,$TEXT_COLOR_MEASURE,$FONT_MEASURE,$MBPS_TEXT);
imagefttext($im,$FONT_MEASURE_SIZE,0,$POSITION_X_PING+$pingMeterBbox[4]/2+$SMALL_SEP/2-$msBbox[4]/2,$POSITION_Y_PING_MEASURE,$TEXT_COLOR_MEASURE,$FONT_MEASURE,$MS_TEXT);
imagefttext($im,$FONT_MEASURE_SIZE,0,$POSITION_X_JIT+$jitMeterBbox[4]/2+$SMALL_SEP/2-$msBbox[4]/2,$POSITION_Y_JIT_MEASURE,$TEXT_COLOR_MEASURE,$FONT_MEASURE,$MS_TEXT);
imagefttext($im,$FONT_ISP_SIZE,0,$POSITION_X_ISP,$POSITION_Y_ISP,$TEXT_COLOR_ISP,$FONT_ISP,$ispinfo);
imagefttext($im,$FONT_WATERMARK_SIZE,0,$POSITION_X_WATERMARK,$POSITION_Y_WATERMARK,$TEXT_COLOR_WATERMARK,$FONT_WATERMARK,$WATERMARK_TEXT);
imagefilledrectangle($im, 0, $SEPARATOR_Y, $WIDTH, $SEPARATOR_Y, $SEPARATOR_COLOR);
header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);

?>
