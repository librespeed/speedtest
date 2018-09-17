<?php
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

$SCALE=1.25;
$WIDTH=530*$SCALE;
$HEIGHT=150*$SCALE;
$im=imagecreatetruecolor($WIDTH,$HEIGHT);
$BACKGROUND_COLOR=imagecolorallocate($im,248,248,248);
$FONT_1=tryFont("OpenSans-Semibold");
$FONT_1_SIZE=16*$SCALE;
$FONT_2=tryFont("OpenSans-Light");
$FONT_2_SIZE=24*$SCALE;
$FONT_3=tryFont("OpenSans-Semibold");
$FONT_3_SIZE=14*$SCALE;
$FONT_4=tryFont("OpenSans-Semibold");
$FONT_4_SIZE=10*$SCALE;
$FONT_WATERMARK=tryFont("OpenSans-Light");
$FONT_WATERMARK_SIZE=8*$SCALE;
$TEXT_COLOR_1=imagecolorallocate($im,40,40,40);
$TEXT_COLOR_2=imagecolorallocate($im,96,96,96);
$TEXT_COLOR_3=imagecolorallocate($im,40,40,40);
$TEXT_COLOR_4=imagecolorallocate($im,40,40,40);
$TEXT_COLOR_WATERMARK=imagecolorallocate($im,160,160,160);
$POSITION_Y_1=24*$SCALE;
$POSITION_Y_2=78*$SCALE;
$POSITION_Y_3=118*$SCALE;
$POSITION_Y_4=146*$SCALE;
$POSITION_Y_WATERMARK=146*$SCALE;
$POSITION_X_DL=68*$SCALE;
$POSITION_X_UL=200*$SCALE;
$POSITION_X_PING=330*$SCALE;
$POSITION_X_JIT=460*$SCALE;
$POSITION_X_ISP=4*$SCALE;
$DL_TEXT="Download";
$UL_TEXT="Upload";
$PING_TEXT="Ping";
$JIT_TEXT="Jitter";
$MBPS_TEXT="Mbps";
$MS_TEXT="ms";
$WATERMARK_TEXT="HTML5 Speedtest";

$id=$_GET["id"];
include_once('../telemetry/telemetry_settings.php');
$conn=null; $q=null;
$ispinfo=null; $dl=null; $ul=null; $ping=null; $jit=null;
if($db_type=="mysql"){
	$conn = new mysqli($MySql_hostname, $MySql_username, $MySql_password, $MySql_databasename);
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

$ispinfo=json_decode($ispinfo,true)["processedString"];
$dash=strrpos($ispinfo,"-");
if(!($dash===FALSE)){
	$ispinfo=substr($ispinfo,$dash+2);
	$par=strrpos($ispinfo,"(");
	if(!($par===FALSE)) $ispinfo=substr($ispinfo,0,$par);
}else $ispinfo="";

$dlBbox=imageftbbox($FONT_1_SIZE,0,$FONT_1,$DL_TEXT);
$ulBbox=imageftbbox($FONT_1_SIZE,0,$FONT_1,$UL_TEXT);
$pingBbox=imageftbbox($FONT_1_SIZE,0,$FONT_1,$PING_TEXT);
$jitBbox=imageftbbox($FONT_1_SIZE,0,$FONT_1,$JIT_TEXT);
$dlMeterBbox=imageftbbox($FONT_2_SIZE,0,$FONT_2,$dl);
$ulMeterBbox=imageftbbox($FONT_2_SIZE,0,$FONT_2,$ul);
$pingMeterBbox=imageftbbox($FONT_2_SIZE,0,$FONT_2,$ping);
$jitMeterBbox=imageftbbox($FONT_2_SIZE,0,$FONT_2,$jit);
$mbpsBbox=imageftbbox($FONT_3_SIZE,0,$FONT_3,$MBPS_TEXT);
$msBbox=imageftbbox($FONT_3_SIZE,0,$FONT_3,$MS_TEXT);
$watermarkBbox=imageftbbox($FONT_WATERMARK_SIZE,0,$FONT_WATERMARK,$WATERMARK_TEXT);
$POSITION_X_WATERMARK=$WIDTH-$watermarkBbox[4]-4*$SCALE;

imagefilledrectangle($im, 0, 0, $WIDTH, $HEIGHT, $BACKGROUND_COLOR);
imagefttext($im,$FONT_1_SIZE,0,$POSITION_X_DL-$dlBbox[4]/2,$POSITION_Y_1,$TEXT_COLOR_1,$FONT_1,$DL_TEXT);
imagefttext($im,$FONT_1_SIZE,0,$POSITION_X_UL-$ulBbox[4]/2,$POSITION_Y_1,$TEXT_COLOR_1,$FONT_1,$UL_TEXT);
imagefttext($im,$FONT_1_SIZE,0,$POSITION_X_PING-$pingBbox[4]/2,$POSITION_Y_1,$TEXT_COLOR_1,$FONT_1,$PING_TEXT);
imagefttext($im,$FONT_1_SIZE,0,$POSITION_X_JIT-$jitBbox[4]/2,$POSITION_Y_1,$TEXT_COLOR_1,$FONT_1,$JIT_TEXT);
imagefttext($im,$FONT_2_SIZE,0,$POSITION_X_DL-$dlMeterBbox[4]/2,$POSITION_Y_2,$TEXT_COLOR_2,$FONT_2,$dl);
imagefttext($im,$FONT_2_SIZE,0,$POSITION_X_UL-$ulMeterBbox[4]/2,$POSITION_Y_2,$TEXT_COLOR_2,$FONT_2,$ul);
imagefttext($im,$FONT_2_SIZE,0,$POSITION_X_PING-$pingMeterBbox[4]/2,$POSITION_Y_2,$TEXT_COLOR_2,$FONT_2,$ping);
imagefttext($im,$FONT_2_SIZE,0,$POSITION_X_JIT-$jitMeterBbox[4]/2,$POSITION_Y_2,$TEXT_COLOR_2,$FONT_2,$jit);
imagefttext($im,$FONT_3_SIZE,0,$POSITION_X_DL-$mbpsBbox[4]/2,$POSITION_Y_3,$TEXT_COLOR_3,$FONT_3,$MBPS_TEXT);
imagefttext($im,$FONT_3_SIZE,0,$POSITION_X_UL-$mbpsBbox[4]/2,$POSITION_Y_3,$TEXT_COLOR_3,$FONT_3,$MBPS_TEXT);
imagefttext($im,$FONT_3_SIZE,0,$POSITION_X_PING-$msBbox[4]/2,$POSITION_Y_3,$TEXT_COLOR_3,$FONT_3,$MS_TEXT);
imagefttext($im,$FONT_3_SIZE,0,$POSITION_X_JIT-$msBbox[4]/2,$POSITION_Y_3,$TEXT_COLOR_3,$FONT_3,$MS_TEXT);
imagefttext($im,$FONT_4_SIZE,0,$POSITION_X_ISP,$POSITION_Y_4,$TEXT_COLOR_4,$FONT_4,$ispinfo);
imagefttext($im,$FONT_WATERMARK_SIZE,0,$POSITION_X_WATERMARK,$POSITION_Y_WATERMARK,$TEXT_COLOR_WATERMARK,$FONT_WATERMARK,$WATERMARK_TEXT);

header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);

?>
