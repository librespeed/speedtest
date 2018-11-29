<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no" />
<script type="text/javascript" src="server_selector.min.js"></script>
<script type="text/javascript">
function I(i){return document.getElementById(i);}

//LIST OF TEST SERVERS. See documentation for details if needed
var SPEEDTEST_SERVERS=[
	{	//this is my demo server, remove it
		name:"Speedtest Demo Server 1 (HTTP)", //user friendly name for the server
		server:"http://mpotdemo.fdossena.com/", //Full URL to the server, complete with / at the end
		dlURL:"garbage.php",  //path to download test on this server (garbage.php or replacement)
		ulURL:"empty.php",  //path to upload test on this server (empty.php or replacement)
		pingURL:"empty.php",  //path to ping/jitter test on this server (empty.php or replacement)
		getIpURL:"getIP.php"  //path to getIP on this server (getIP.php or replacement)
	},
	{	//this is my demo server, remove it
		name:"Speedtest Demo Server 2 (HTTP)",
		server:"http://mpotdemo.adolfintel.com/",
		dlURL:"garbage.php",
		ulURL:"empty.php",
		pingURL:"empty.php",
		getIpURL:"getIP.php"
	},
	{	//this is my demo server, remove it
		name:"Speedtest Demo Server 1 (HTTPS)",
		server:"https://mpotdemo.fdossena.com/",
		dlURL:"garbage.php",
		ulURL:"empty.php",
		pingURL:"empty.php",
		getIpURL:"getIP.php"
	},
	{	//this is my demo server, remove it
		name:"Speedtest Demo Server 2 (HTTPS)",
		server:"https://mpotdemo.adolfintel.com/",
		dlURL:"garbage.php",
		ulURL:"empty.php",
		pingURL:"empty.php",
		getIpURL:"getIP.php"
	}
	//add other servers here, comma separated
];

//SERVER AUTO SELECTION
function initServers(){
	fastSelectServer(SPEEDTEST_SERVERS,function(server){
		if(server!=null){ //at least 1 server is available
			I("loading").className="hidden"; //hide loading message
			//configure speedtest to use the specified server
			speedtestSettings.url_dl=server.server+server.dlURL;
			speedtestSettings.url_ul=server.server+server.ulURL;
			speedtestSettings.url_ping=server.server+server.pingURL;
			speedtestSettings.url_getIp=server.server+server.getIpURL;
			speedtestSettings.telemetry_extra=JSON.stringify({server:server.name}); //add server name as extra data in the telemetry
			I("server").textContent=server.name; //show the name of the server in the UI
			//show test UI
			I("testWrapper").className="visible";
			initUI();
		}else{ //no servers are available, the test cannot proceed
			I("message").innerHTML="No servers available";
		}
	});
}

var meterBk="#E0E0E0";
var dlColor="#6060AA",
	ulColor="#309030",
	pingColor="#AA6060",
	jitColor="#AA6060";
var progColor="#EEEEEE";

//CODE FOR GAUGES
function drawMeter(c,amount,bk,fg,progress,prog){
	var ctx=c.getContext("2d");
	var dp=window.devicePixelRatio||1;
	var cw=c.clientWidth*dp, ch=c.clientHeight*dp;
	var sizScale=ch*0.0055;
	if(c.width==cw&&c.height==ch){
		ctx.clearRect(0,0,cw,ch);
	}else{
		c.width=cw;
		c.height=ch;
	}
	ctx.beginPath();
	ctx.strokeStyle=bk;
	ctx.lineWidth=16*sizScale;
	ctx.arc(c.width/2,c.height-58*sizScale,c.height/1.8-ctx.lineWidth,-Math.PI*1.1,Math.PI*0.1);
	ctx.stroke();
	ctx.beginPath();
	ctx.strokeStyle=fg;
	ctx.lineWidth=16*sizScale;
	ctx.arc(c.width/2,c.height-58*sizScale,c.height/1.8-ctx.lineWidth,-Math.PI*1.1,amount*Math.PI*1.2-Math.PI*1.1);
	ctx.stroke();
	if(typeof progress !== "undefined"){
		ctx.fillStyle=prog;
		ctx.fillRect(c.width*0.3,c.height-16*sizScale,c.width*0.4*progress,4*sizScale);
	}
}
function mbpsToAmount(s){
	return 1-(1/(Math.pow(1.3,Math.sqrt(s))));
}
function msToAmount(s){
	return 1-(1/(Math.pow(1.08,Math.sqrt(s))));
}

//SPEEDTEST AND UI CODE
var w=null; //speedtest worker
var data=null; //data from worker
var speedtestSettings={
	//add test settings here if you want
};
function startStop(){
	if(w!=null){
		//speedtest is running, abort
		w.postMessage('abort');
		w=null;
		data=null;
		I("startStopBtn").className="";
		initUI();
	}else{
		//test is not running, begin
		w=new Worker('speedtest_worker.min.js');
		w.postMessage('start '+JSON.stringify(speedtestSettings)); //Add optional parameters as a JSON object to this command
		I("startStopBtn").className="running";
		w.onmessage=function(e){
			data=JSON.parse(e.data);
			var status=data.testState;
			if(status>=4){
				//test completed
				I("startStopBtn").className="";
				w=null;
				updateUI(true);
			}
		};
	}
}
//this function reads the data sent back by the worker and updates the UI
function updateUI(forced){
	if(!forced&&(!data||!w)) return;
	var status=data.testState;
	I("ip").textContent=data.clientIp;
	I("dlText").textContent=(status==1&&data.dlStatus==0)?"...":data.dlStatus;
	drawMeter(I("dlMeter"),mbpsToAmount(Number(data.dlStatus*(status==1?oscillate():1))),meterBk,dlColor,Number(data.dlProgress),progColor);
	I("ulText").textContent=(status==3&&data.ulStatus==0)?"...":data.ulStatus;
	drawMeter(I("ulMeter"),mbpsToAmount(Number(data.ulStatus*(status==3?oscillate():1))),meterBk,ulColor,Number(data.ulProgress),progColor);
	I("pingText").textContent=data.pingStatus;
	drawMeter(I("pingMeter"),msToAmount(Number(data.pingStatus*(status==2?oscillate():1))),meterBk,pingColor,Number(data.pingProgress),progColor);
	I("jitText").textContent=data.jitterStatus;
	drawMeter(I("jitMeter"),msToAmount(Number(data.jitterStatus*(status==2?oscillate():1))),meterBk,jitColor,Number(data.pingProgress),progColor);
}
function oscillate(){
	return 1+0.02*Math.sin(Date.now()/100);
}
//poll the status from the worker (this will call updateUI)
setInterval(function(){
	if(w) w.postMessage('status');
},200);
//update the UI every frame
window.requestAnimationFrame=window.requestAnimationFrame||window.webkitRequestAnimationFrame||window.mozRequestAnimationFrame||window.msRequestAnimationFrame||(function(callback,element){setTimeout(callback,1000/60);});
function frame(){
	requestAnimationFrame(frame);
	updateUI();
}
frame(); //start frame loop
//function to (re)initialize UI
function initUI(){
	drawMeter(I("dlMeter"),0,meterBk,dlColor,0);
	drawMeter(I("ulMeter"),0,meterBk,ulColor,0);
	drawMeter(I("pingMeter"),0,meterBk,pingColor,0);
	drawMeter(I("jitMeter"),0,meterBk,jitColor,0);
	I("dlText").textContent="";
	I("ulText").textContent="";
	I("pingText").textContent="";
	I("jitText").textContent="";
	I("ip").textContent="";
}
</script>
<style type="text/css">
	html,body{
		border:none; padding:0; margin:0;
		background:#FFFFFF;
		color:#202020;
	}
	body{
		text-align:center;
		font-family:"Roboto",sans-serif;
	}
	h1{
		color:#404040;
	}
	#loading{
		background-color:#FFFFFF;
		color:#404040;
		text-align:center;
	}
	span.loadCircle{
		display:inline-block;
		width:2em;
		height:2em;
		vertical-align:middle;
		background:url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAMAAAD04JH5AAAAP1BMVEUAAAB2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZ2dnZyFzwnAAAAFHRSTlMAEvRFvX406baecwbf0casimhSHyiwmqgAAADpSURBVHja7dbJbQMxAENRahnN5lkc//5rDRAkDeRgHszXgACJoKiIiIiIiIiIiIiIiIiIiIj4HHspsrpAVhdVVguzrA4OWc10WcEqpwKbnBo0OU1Q5NSpsoJFTgOecrrdEag85DRgktNqfoEdTjnd7hrEHMEJvmRUYJbTYk5Agy6nau6Abp5Cm7mDBtRdPi9gyKdU7w4p1fsLvyqs8hl4z9/w3n/Hmr9WoQ65lAU4d7lMYOz//QboRR5jBZibLMZdAR6O/Vfa1PlxNr3XdS3HzK/HVPRu/KnLs8iAOh993VpRRERERMT/fAN60wwWaVyWwAAAAABJRU5ErkJggg==');
		background-size:2em 2em;
		margin-right:0.5em;
		animation: spin 0.6s linear infinite;
	}
	@keyframes spin{
		0%{transform:rotate(0deg);}
		100%{transform:rotate(359deg);}
	}
	#startStopBtn{
		display:inline-block;
		margin:0 auto;
		color:#6060AA;
		background-color:rgba(0,0,0,0);
		border:0.15em solid #6060FF;
		border-radius:0.3em;
		transition:all 0.3s;
		box-sizing:border-box;
		width:8em; height:3em;
		line-height:2.7em;
		cursor:pointer;
		box-shadow: 0 0 0 rgba(0,0,0,0.1), inset 0 0 0 rgba(0,0,0,0.1);
	}
	#startStopBtn:hover{
		box-shadow: 0 0 2em rgba(0,0,0,0.1), inset 0 0 1em rgba(0,0,0,0.1);
	}
	#startStopBtn.running{
		background-color:#FF3030;
		border-color:#FF6060;
		color:#FFFFFF;
	}
	#startStopBtn:before{
		content:"Start";
	}
	#startStopBtn.running:before{
		content:"Abort";
	}
	#serverArea{
		margin-top:1em;
	}
	#test{
		margin-top:2em;
		margin-bottom:12em;
	}
	div.testArea{
		display:inline-block;
		width:16em;
		height:12.5em;
		position:relative;
		box-sizing:border-box;
	}
	div.testName{
		position:absolute;
		top:0.1em; left:0;
		width:100%;
		font-size:1.4em;
		z-index:9;
	}
	div.meterText{
		position:absolute;
		bottom:1.55em; left:0;
		width:100%;
		font-size:2.5em;
		z-index:9;
	}
	div.meterText:empty:before{
		content:"0.00";
	}
	div.unit{
		position:absolute;
		bottom:2em; left:0;
		width:100%;
		z-index:9;
	}
	div.testArea canvas{
		position:absolute;
		top:0; left:0; width:100%; height:100%;
		z-index:1;
	}
	div.testGroup{
		display:inline-block;
	}
	div.visible{
		animation: fadeIn 0.4s;
		display:block;
	}
	div.hidden{
		animation: fadeOut 0.4s;
		display:none;
	}
	@keyframes fadeIn{
		0%{
			opacity:0;
		}
		100%{
			opacity:1;
		}
	}
	@keyframes fadeOut{
		0%{
			display:block;
			opacity:1;
		}
		100%{
			display:block;
			opacity:0;
		}
	}
	@media all and (max-width:65em){
		body{
			font-size:1.5vw;
		}
	}
	@media all and (max-width:40em){
		body{
			font-size:0.8em;
		}
		div.testGroup{
			display:block;
			margin: 0 auto;
		}
	}
</style>
<title>HTML5 Speedtest</title>
</head>
<body onload="initServers()">
<h1>HTML5 Speedtest</h1>
<div id="loading" class="visible">
	<p id="message"><span class="loadCircle"></span>Selecting a server...</p>
</div>
<div id="testWrapper" class="hidden">
	<div id="startStopBtn" onclick="startStop()"></div>
	<div id="serverArea">
		Server: <span id="server"></span>
	</div>
	<div id="test">
		<div class="testGroup">
			<div class="testArea">
				<div class="testName">Download</div>
				<canvas id="dlMeter" class="meter"></canvas>
				<div id="dlText" class="meterText"></div>
				<div class="unit">Mbps</div>
			</div>
			<div class="testArea">
				<div class="testName">Upload</div>
				<canvas id="ulMeter" class="meter"></canvas>
				<div id="ulText" class="meterText"></div>
				<div class="unit">Mbps</div>
			</div>
		</div>
		<div class="testGroup">
			<div class="testArea">
				<div class="testName">Ping</div>
				<canvas id="pingMeter" class="meter"></canvas>
				<div id="pingText" class="meterText"></div>
				<div class="unit">ms</div>
			</div>
			<div class="testArea">
				<div class="testName">Jitter</div>
				<canvas id="jitMeter" class="meter"></canvas>
				<div id="jitText" class="meterText"></div>
				<div class="unit">ms</div>
			</div>
		</div>
		<div id="ipArea">
			IP Address: <span id="ip"></span>
		</div>
	</div>
	<a href="https://github.com/adolfintel/speedtest">Source code</a>
</div>
</body>
</html>