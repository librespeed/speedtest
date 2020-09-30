<?php
function getObfuscationSalt(){
	$saltFile=dirname(__FILE__)."/idObfuscation_salt.php";
	if(file_exists($saltFile)){
		require $saltFile;
	}else{
		$bytes=openssl_random_pseudo_bytes(4);
		$sf=fopen($saltFile,"w");
		fwrite($sf,chr(60)."?php\n");
		fwrite($sf,'$OBFUSCATION_SALT=0x'.bin2hex($bytes).";\n");
		fwrite($sf,"?".chr(62));
		fclose($sf);
		require $saltFile;
	}
	return isset($OBFUSCATION_SALT)?$OBFUSCATION_SALT:0;
}
/*
This is a simple reversible hash function I made for encoding and decoding test IDs.
It is not cryptographically secure, don't use it to hash passwords or something!
*/
function obfdeobf($id,$dec){
	$salt=getObfuscationSalt()&0xFFFFFFFF;
	$id=$id&0xFFFFFFFF;
	if($dec){
		$id=$id^$salt;
		$id=(($id&0xAAAAAAAA)>>1)|($id&0x55555555)<<1;
		$id=(($id&0x0000FFFF)<<16)|(($id&0xFFFF0000)>>16);
		return $id;
	}else{
		$id=(($id&0x0000FFFF)<<16)|(($id&0xFFFF0000)>>16);
		$id=(($id&0xAAAAAAAA)>>1)|($id&0x55555555)<<1;
		return $id^$salt;
	}
}
function obfuscateId($id){
	return str_pad(base_convert(obfdeobf($id+1,false),10,36),7,0,STR_PAD_LEFT);
}
function deobfuscateId($id){
	return obfdeobf(base_convert($id,36,10),true)-1;
}
