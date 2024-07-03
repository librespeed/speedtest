<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no" />
<link rel="apple-touch-icon" href="favicon.ico">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta charset="UTF-8" />
<link rel="shortcut icon" href="favicon.ico">
<script type="text/javascript" src="speedtest.js"></script>
<script type="text/javascript">
function I(i){return document.getElementById(i);}
//INITIALIZE SPEED TEST
var s=new Speedtest(); //create speed test object
<?php if(getenv("TELEMETRY")=="true"){ ?>
s.setParameter("telemetry_level","basic");
<?php } ?>
<?php if(getenv("DISABLE_IPINFO")=="true"){ ?>
s.setParameter("getIp_ispInfo",false);
<?php } ?>
<?php if(getenv("DISTANCE")){ ?>
s.setParameter("getIp_ispInfo_distance","<?=getenv("DISTANCE") ?>");
<?php } ?>

var meterBk = /Trident.*rv:(\d+\.\d+)/i.test(navigator.userAgent) ? "#EAEAEA" : "#80808040";
		var dlColor = "#6060AA",
			ulColor = "#616161";
		var progColor = meterBk;

		//CODE FOR GAUGES

		// function drawMeter(c, amount, bk, fg, progress, prog) {
		// 	var ctx = c.getContext("2d");
		// 	var dp = window.devicePixelRatio || 1;
		// 	var cw = c.clientWidth * dp, ch = c.clientHeight * dp;
		// 	var sizScale = ch * 0.0055;
		// 	if (c.width == cw && c.height == ch) {
		// 		ctx.clearRect(0, 0, cw, ch);
		// 	} else {
		// 		c.width = cw;
		// 		c.height = ch;
		// 	}
		// 	ctx.beginPath();
		// 	ctx.strokeStyle = bk;
		// 	ctx.lineWidth = 12 * sizScale;
		// 	ctx.arc(c.width / 2, c.height - 58 * sizScale, c.height / 1.8 - ctx.lineWidth, -Math.PI * 1.1, Math.PI * 0.1);
		// 	ctx.stroke();
		// 	ctx.beginPath();
		// 	ctx.strokeStyle = fg;
		// 	ctx.lineWidth = 12 * sizScale;
		// 	ctx.arc(c.width / 2, c.height - 58 * sizScale, c.height / 1.8 - ctx.lineWidth, -Math.PI * 1.1, amount * Math.PI * 1.2 - Math.PI * 1.1);
		// 	ctx.stroke();
		// 	if (typeof progress !== "undefined") {
		// 		ctx.fillStyle = prog;
		// 		ctx.fillRect(c.width * 0.3, c.height - 16 * sizScale, c.width * 0.4 * progress, 4 * sizScale);
		// 	}
		// }

		function mbpsToAmount(s) {
			return 1 - (1 / (Math.pow(1.3, Math.sqrt(s))));
		}
		function format(d) {
			d = Number(d);
			if (d < 10) return d.toFixed(2);
			if (d < 100) return d.toFixed(1);
			return d.toFixed(0);
		}


		// 3: Good
		// 2: Average
		// 1: Poor
		// "": Empty

		function classifySpeed(value) {
				if (value=="") return 0;
                if (value > 50) return 3;
                if (value >= 10) return 2;
                return 1;
            
        }

		function updateDlColor(value) {

				const dl_text_element = document.getElementById("dlText");
				const speed_classification = classifySpeed(value);

				// TextElement.textContent = value;

				// if (classification === "Good") {
				// 	TextElement.style.color = "#16A34A";
				// } 
				// else if (classification === "Average") {
				// 	TextElement.style.color = "#CA8A04";
				// }
				// else {
				// 	TextElement.style.color = "#DC2626"; // Reset to default color or set another color
				// }

				// classification === 2?TextElement.style.color = "#16A34A":(classification === 1?TextElement.style.color = "#CA8A04":TextElement.style.color = "#DC2626");

				switch (speed_classification){
					case 3: dl_text_element.style.color = "#16A34A";
					break;
					case 2: dl_text_element.style.color = "#CA8A04";
					break;
					case 1: dl_text_element.style.color = "#DC2626";
					break;
					case 0: dl_text_element.style.color = "#111827";
					break;

					default: dl_text_element.style.color = "#111827";
				}
				}

		function updateUlColor(value) {

			const ul_text_element = document.getElementById("ulText");
			const speed_classification = classifySpeed(value);

			switch (speed_classification){
				case 3: ul_text_element.style.color = "#16A34A";
				break;
				case 2: ul_text_element.style.color = "#CA8A04";
				break;
				case 1: ul_text_element.style.color = "#DC2626";
				break;
				case 0: ul_text_element.style.color = "#111827";
				break;

				default: ul_element.style.color = "#111827";
			}
			}		

		function classifyPing(value){

			// console.log("Ping Value: ",value);
			if (value=="") return 0;
			if (value>100) return 1;
			if (value>=20) return 2;
			return 3;
		}

		function updatePingColor(value){
			const ping_element = document.getElementById("pingText");
			const ping_classification = classifyPing(value)

			// ping_classification === 3?ping_element.style.color = "#16A34A":(ping_classification === 2?ping_element.style.color = "#CA8A04":(ping_classification === 1?ping_element.style.color = "#DC2626":ping_element.style.color = "#111827"));

			switch (ping_classification){
				case 3: ping_element.style.color = "#16A34A";
				break;
				case 2: ping_element.style.color = "#CA8A04";
				break;
				case 1: ping_element.style.color = "#DC2626";
				break;
				case 0: ping_element.style.color = "#111827";
				break;

				default: ping_element.style.color = "#111827";
			}
		}

		function classifyJitter(value){
			if (value=="") return 0;
			if (value>20) return 1;
			if (value>=5) return 2;
			return 3;
		}

		function updateJitterColor(value){
			const jitter_element = document.getElementById("jitText");
			const jitter_classification = classifyJitter(value);

			// jitter_classification === 2?jitter_element.style.color = "#16A34A":(jitter_classification === 1?jitter_element.style.color = "#CA8A04":jitter_element.style.color = "#DC2626");

			switch (jitter_classification){
				case 3: jitter_element.style.color = "#16A34A";
				break;
				case 2: jitter_element.style.color = "#CA8A04";
				break;
				case 1: jitter_element.style.color = "#DC2626";
				break;
				case 0: jitter_element.style.color = "#111827";
				break;

				default: jitter_element.style.color = "#111827";
			}
		}

		//UI CODE
		var uiData = null;
		function startStop() {
			if (s.getState() == 3) {
				//speedtest is running, abort
				s.abort();
				data = null;
				I("startStopBtn").className = "";
				initUI();
			} else {
				//test is not running, begin
				I("startStopBtn").className = "running";
				I("shareArea").style.display = "none";
				s.onupdate = function (data) {
					uiData = data;
				};
				s.onend = function (aborted) {
					I("startStopBtn").className = "";
					updateUI(true);
					if (!aborted) {
						//if testId is present, show sharing panel, otherwise do nothing
						// I("startStopBtn").textContent = "Try Again";
						I("startStopBtn").className = "new-class";
						try {
							var testId = uiData.testId;
							if (testId != null) {
								var shareURL = window.location.href.substring(0, window.location.href.lastIndexOf("/")) + "/results/?id=" + testId;
								console.log("ShareURL: ", shareURL);
								// I("resultsImg").src = shareURL;
								I("resultsURL").value = shareURL;
								I("testId").innerHTML = testId;
								I("shareArea").style.display = "";
							}
						} catch (e) { }
					}
				};
				s.start();
			}
		}
		//this function reads the data sent back by the test and updates the UI
		function updateUI(forced) {
			if (!forced && s.getState() != 3) return;
			if (uiData == null) return;
			var status = uiData.testState;
			// I("ip").textContent = uiData.clientIp;
			
			I("dlText").textContent = (status == 1 && uiData.dlStatus == 0) ? "..." : format(uiData.dlStatus);
			// drawMeter(I("dlMeter"), mbpsToAmount(Number(uiData.dlStatus * (status == 1 ? oscillate() : 1))), meterBk, dlColor, Number(uiData.dlProgress), progColor);
			I("ulText").textContent = (status == 3 && uiData.ulStatus == 0) ? "..." : format(uiData.ulStatus);
			// drawMeter(I("ulMeter"), mbpsToAmount(Number(uiData.ulStatus * (status == 3 ? oscillate() : 1))), meterBk, ulColor, Number(uiData.ulProgress), progColor);
			I("pingText").textContent = format(uiData.pingStatus);
			I("jitText").textContent = format(uiData.jitterStatus);

			if (status == 1){
				updateDlColor(uiData.dlStatus);
				// updateDlColor(30, "download");
			}else if(status == 3){
				updateUlColor(uiData.ulStatus);
			}else{
				updateDlColor(uiData.dlStatus);
				updateUlColor(uiData.ulStatus);

			}
			

			updatePingColor(uiData.pingStatus);
			updateJitterColor(uiData.jitterStatus);

			// updateJitterColor(1);
			// updatePingColor(105);
			
			// console.log(uiData);

		}
		function oscillate() {
			return 1 + 0.02 * Math.sin(Date.now() / 100);
		}
		//update the UI every frame
		window.requestAnimationFrame = window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || window.msRequestAnimationFrame || (function (callback, element) { setTimeout(callback, 1000 / 60); });
		function frame() {
			requestAnimationFrame(frame);
			updateUI();
		}
		frame(); //start frame loop
		//function to (re)initialize UI
		function initUI() {
			// drawMeter(I("dlMeter"), 0, meterBk, dlColor, 0);
			// drawMeter(I("ulMeter"), 0, meterBk, ulColor, 0);
			I("dlText").textContent = "";
			I("ulText").textContent = "";
			I("pingText").textContent = "";
			I("jitText").textContent = "";
			// I("ip").textContent = "";

			
		}
	</script>



	<style type="text/css">
		

		html,
		body {
			height: 100%;
			margin: 0;
		}
		body {
			display: flex;
			flex-direction: column;
		}

		.header {
			width: 100%;
			/* margin: -8px; */
			height: 64px;
			border: 1px solid;
			border-color: white;
			background-color: white;
			padding: 15px 40px;
			/* background-repeat:no-repeat;
			object-fit: cover; */

			position: relative;
			

		}
		* {
			box-sizing: border-box;
		}

		.logo-container {
			/* position: absolute; */
			/* top: 50%; */

			/* transform: translateY(40%);
			top: 126px;
			left: 40px;
			width: 104px;
			height: 35px; */

			background-repeat: no-repeat;
			object-fit: cover;
			/* padding-top: 15px;
			padding-left:40px;
			padding-bottom: 15px; */
			width: auto;
			height: 100%;

		}
		.logo-container img {
			height: 100%;
			width: auto;
		}


		.container {

			background-color: #F9FAFB;
			padding-left: 7%;
			padding-right: 7%;
			padding-top: 16px;
			/* padding-bottom: 24px; */
			padding-bottom: 2.5%;
			flex: 1;
			overflow: auto;


			/* width: 100%; */
			/* max-width: 1200px; */
			/* padding: 20px; */
			display: flex;
			flex-direction: column;
			flex-wrap: wrap;
			
		}

		.child-container {
			position:relative;
			background-color:white;
			height: 100%;
			display: flex;
			flex-direction: column;
			/* justify-content: center; */
			align-items: center;


			width: 100%;
			/* background-color: #FFFFFF; */
			/* padding: 20px; */
			border-radius: 8px;
			/* box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); */

			padding-top: 44px;
			

		}

		div.testGroup {
			display: flex;
			/* flex-direction: row; */
			gap: 24px;
			top:10%;


			/* display: flex; */
			/* flex-wrap: wrap; */
			justify-content: center;
			/* gap: 20px; */
			margin-bottom: 20px;
		}


		.startBtnStyle{
			display: flex;
			align-items: center;
			justify-content: center;
			margin-top: 32px;
		}

		#startStopBtn {
			display: flex;
			width: 136px;
			height: 50px;
			padding: 13px 12px;
			justify-content: center;
			align-items: center;
			gap: 8px;
			flex-shrink: 0;
			border-radius: 8px;
			background-color: #1D4ED8;
			font-family: Inter;
			color: white;
			font-size: 16px;

		}

		#startStopBtn:hover {
			box-shadow: 0 0 2em rgba(0, 0, 0, 0.1), inset 0 0 1em rgba(0, 0, 0, 0.1);
		}

		#startStopBtn.running {
			
			content: "Start";
			background-color: #DC2626;
		}

		#startStopBtn:before {
			font-family: Inter;
			font-size: 16px;
			font-style: normal;
			font-weight: 500;
			line-height: 24px;
			content: "Start";
			
			
		}

		#startStopBtn.running:before {
			
			content: "Stop";

			display: flex;
			width: 136px;
			height: 50px;
			padding: 13px 12px;
			justify-content: center;
			align-items: center;
			gap: 8px;
			flex-shrink: 0;
		}

		#startStopBtn.new-class:before {
			
			content: "Try Again";

			display: flex;
			width: 136px;
			height: 50px;
			padding: 13px 12px;
			justify-content: center;
			align-items: center;
			gap: 8px;
			flex-shrink: 0;
			
		}

		.test {
			/* top: 125px; */
			/* position: absolute; */
			display: flex;
			flex-direction: column;
			gap: 20px;
			align-items: center; 
			padding-top: 43px;
		}

		div.testArea {
			display: flex;
			width: 248px;
			padding: 24px 33px 24px 24px;
			flex-direction: column;
			align-items: flex-start;
			gap: 12px;

			border-radius: 16px;
			border: 1px solid var(--Monochrome-Grey-7, #D1D5DB);
			background: var(--Monochrome-White, #FFF);

		}

		div.testArea2 {
			display: flex;
			width: 248px;
			padding: 24px 33px 24px 24px;
			flex-direction: column;
			align-items: flex-start;
			gap: 12px;

			border-radius: 16px;
			border: 1px solid var(--Monochrome-Grey-7, #D1D5DB);
			background: var(--Monochrome-White, #FFF);



		}

		#testWrapper {
			display: flex;
			flex-direction: column;
		}

		.title-class{
			display:flex;
			/* top: 44px; */
			top: 3.6%;
			/* position: absolute; */
			color: #1F2937;
			font-family: Roboto;
			font-size: 32px;
			font-style: normal;
			font-weight: 600;
			line-height: normal;

			/* font-size: 24px; */
			/* font-weight: 600; */
			/* margin-bottom: 20px; */
			/* color: #1F2937; */
		}

		div.testName {
			color: var(--Monochrome-Grey-4, #4B5563);
			font-family: Roboto;
			font-size: 16px;
			font-style: normal;
			font-weight: 400;
			line-height: normal;
		}


		div.meterText {
			color: var(--Gray-900, #111827);
			font-family: Roboto;
			font-size: 30px;
			font-style: normal;
			font-weight: 600;
			line-height: 36px;
			/* 120% */
		}

		div.meterText:empty:before {
			content: "0.00";
			
		}

		div.unit {
			color: var(--Monochrome-Grey-4, #4B5563);
			font-family: Roboto;
			font-size: 14px;
			font-style: normal;
			font-weight: 400;
			line-height: normal;

		}

		#shareArea {
			width: 95%;
			max-width: 40em;
			margin: 0 auto;
			/* margin-top: 2em; */
			border-radius: 8px;
		}

		#shareArea>* {
			display: block;
			width: 100%;
			height: auto;
			margin: 0.25em 0;
		}




		@media (max-width: 768px) {
            .testGroup {
                flex-direction: row;
                gap: 12px;
            }
			
        }

        @media (max-width: 614px) {
            .testArea,
            .testArea2 {
                width: 100% !important;
                padding: 12px !important;
                border-radius: 8px !important;
                gap: 6px !important;
            }

            .title-class {
                font-size: 24px !important;
            }

            .container {
                padding: 10px !important;
            }

			/* .startBtnStyle{
				margin-top:32px;
			} */

            

            
        }
	</style>

	<title>Talview Speedtest</title>
</head>

<body>

	<div class="header">
		<div class="logo-container"><img src="logo.png" class="logo" alt="Logo"></div>
	</div>

	<div class="container">
		<!-- <h1>LibreSpeed Example</h1> -->

		<div class="child-container">

				<div class="title-class">Internet Speed Test</div>

				
				<!-- <a class="privacy" href="#" onclick="I('privacyPolicy').style.display=''">Privacy</a> -->
				<div class="test">

					<div class="testGroup">
						<div class="testArea2">
							<div class="testName">Ping</div>
							<div id="pingText" class="meterText"></div>
							<div class="unit">ms</div>
						</div>
						<div class="testArea2">
							<div class="testName">Jitter</div>
							<div id="jitText" class="meterText"></div>
							<div class="unit">ms</div>
						</div>
					</div>
					<div class="testGroup">
						<div class="testArea">
							<div class="testName">Download</div>

							<div id="dlText" class="meterText"></div>
							<div class="unit">Mbit/s</div>
						</div>
						<div class="testArea">
							<div class="testName">Upload</div>
							<!-- <canvas id="ulMeter" class="meter"></canvas> -->
							<div id="ulText" class="meterText"></div>
							<div class="unit">Mbit/s</div>
						</div>
					</div>

				</div>	

					
					<!-- <div id="ipArea">
						<span id="ip"></span>
					</div> -->
					<div id="shareArea" style="display:none">
						<h3>Share results</h3>
						<p>Test ID: <span id="testId"></span></p>
						<input type="text" value="" id="resultsURL" readonly="readonly"
							onclick="this.select();this.focus();this.select();document.execCommand('copy');alert('Link copied')" />
						<!-- <img src="" id="resultsImg" /> -->
					</div>


					<div class="startBtnStyle">

						<div id="startStopBtn" onclick="startStop()"></div>
					</div>
				

				<!-- <div id="startStopBtn" onclick="startStop()"></div> -->


				
			<div id="privacyPolicy" style="display:none">
				<h2>Privacy Policy</h2>
				<p>This HTML5 speed test server is configured with telemetry enabled.</p>
				<h4>What data we collect</h4>
				<p>
					At the end of the test, the following data is collected and stored:
				<ul>
					<li>Test ID</li>
					<li>Time of testing</li>
					<li>Test results (download and upload speed, ping and jitter)</li>
					<li>IP address</li>
					<li>ISP information</li>
					<li>Approximate location (inferred from IP address, not GPS)</li>
					<li>User agent and browser locale</li>
					<li>Test log (contains no personal information)</li>
				</ul>
				</p>
				<h4>How we use the data</h4>
				<p>
					Data collected through this service is used to:
				<ul>
					<li>Allow sharing of test results (sharable image for forums, etc.)</li>
					<li>To improve the service offered to you (for instance, to detect problems on our side)</li>
				</ul>
				No personal information is disclosed to third parties.
				</p>
				<h4>Your consent</h4>
				<p>
					By starting the test, you consent to the terms of this privacy policy.
				</p>
				<h4>Data removal</h4>
				<p>
					If you want to have your information deleted, you need to provide either the ID of the test or your
					IP address. This is the only way to identify your data, without this information we won't be able to
					comply with your request.<br /><br />
					Contact this email address for all deletion requests: <a href="mailto:PUT@YOUR_EMAIL.HERE">TO BE
						FILLED BY DEVELOPER</a>.
				</p>
				<br /><br />
				<div class="closePrivacyPolicy">
					<a class="privacy" href="#" onclick="I('privacyPolicy').style.display='none'">Close</a>
				</div>
				<br />
			</div>

		</div>



	</div>
	<script type="text/javascript">setTimeout(function () { initUI() }, 100);</script>

</body>

</html>