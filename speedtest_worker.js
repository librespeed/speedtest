/*
	HTML5 Speedtest v4.5.4
	by Federico Dossena
	https://github.com/adolfintel/speedtest/
	GNU LGPLv3 License
*/

// data reported to main thread
var testStatus = -1 // -1=not started, 0=starting, 1=download test, 2=ping+jitter test, 3=upload test, 4=finished, 5=abort/error
var dlStatus = '' // download speed in megabit/s with 2 decimal digits
var ulStatus = '' // upload speed in megabit/s with 2 decimal digits
var pingStatus = '' // ping in milliseconds with 2 decimal digits
var jitterStatus = '' // jitter in milliseconds with 2 decimal digits
var clientIp = '' // client's IP address as reported by getIP.php
var dlProgress = 0 //progress of download test 0-1
var ulProgress = 0 //progress of upload test 0-1
var pingProgress = 0 //progress of ping+jitter test 0-1

var log='' //telemetry log
function tlog(s){log+=Date.now()+': '+s+'\n'}
function twarn(s){log+=Date.now()+' WARN: '+s+'\n'; console.warn(s)}

// test settings. can be overridden by sending specific values with the start command
var settings = {
  test_order: "IP_D_U", //order in which tests will be performed as a string. D=Download, U=Upload, P=Ping+Jitter, I=IP, _=1 second delay
  time_ul: 15, // duration of upload test in seconds
  time_dl: 15, // duration of download test in seconds
  time_ulGraceTime: 3, //time to wait in seconds before actually measuring ul speed (wait for buffers to fill)
  time_dlGraceTime: 1.5, //time to wait in seconds before actually measuring dl speed (wait for TCP window to increase)
  count_ping: 35, // number of pings to perform in ping test
  url_dl: 'garbage.php', // path to a large file or garbage.php, used for download test. must be relative to this js file
  url_ul: 'empty.php', // path to an empty file, used for upload test. must be relative to this js file
  url_ping: 'empty.php', // path to an empty file, used for ping test. must be relative to this js file
  url_getIp: 'getIP.php', // path to getIP.php relative to this js file, or a similar thing that outputs the client's ip
  getIp_ispInfo: true, //if set to true, the server will include ISP info with the IP address
  getIp_ispInfo_distance: 'km', //km or mi=estimate distance from server in km/mi; set to false to disable distance estimation. getIp_ispInfo must be enabled in order for this to work
  xhr_dlMultistream: 10, // number of download streams to use (can be different if enable_quirks is active)
  xhr_ulMultistream: 3, // number of upload streams to use (can be different if enable_quirks is active)
  xhr_multistreamDelay: 300, //how much concurrent requests should be delayed
  xhr_ignoreErrors: 1, // 0=fail on errors, 1=attempt to restart a stream if it fails, 2=ignore all errors
  xhr_dlUseBlob: false, // if set to true, it reduces ram usage but uses the hard drive (useful with large garbagePhp_chunkSize and/or high xhr_dlMultistream)
  xhr_ul_blob_megabytes: 20, //size in megabytes of the upload blobs sent in the upload test (forced to 4 on chrome mobile)
  garbagePhp_chunkSize: 20, // size of chunks sent by garbage.php (can be different if enable_quirks is active)
  enable_quirks: true, // enable quirks for specific browsers. currently it overrides settings to optimize for specific browsers, unless they are already being overridden with the start command
  ping_allowPerformanceApi: true, // if enabled, the ping test will attempt to calculate the ping more precisely using the Performance API. Currently works perfectly in Chrome, badly in Edge, and not at all in Firefox. If Performance API is not supported or the result is obviously wrong, a fallback is provided.
  overheadCompensationFactor: 1.06, //can be changed to compensatie for transport overhead. (see doc.md for some other values)
  useMebibits: false, //if set to true, speed will be reported in mebibits/s instead of megabits/s
  telemetry_level: 0, // 0=disabled, 1=basic (results only), 2=full (results+log)
  url_telemetry: 'telemetry.php' // path to the script that adds telemetry data to the database
}

var xhr = null // array of currently active xhr requests
var interval = null // timer used in tests
var test_pointer = 0 //pointer to the next test to run inside settings.test_order

/*
  this function is used on URLs passed in the settings to determine whether we need a ? or an & as a separator
*/
function url_sep (url) { return url.match(/\?/) ? '&' : '?'; }

/*
	listener for commands from main thread to this worker.
	commands:
	-status: returns the current status as a string of values spearated by a semicolon (;) in this order: testStatus;dlStatus;ulStatus;pingStatus;clientIp;jitterStatus;dlProgress;ulProgress;pingProgress
	-abort: aborts the current test
	-start: starts the test. optionally, settings can be passed as JSON.
		example: start {"time_ul":"10", "time_dl":"10", "count_ping":"50"}
*/
this.addEventListener('message', function (e) {
  var params = e.data.split(' ')
  if (params[0] === 'status') { // return status
    postMessage(testStatus + ';' + dlStatus + ';' + ulStatus + ';' + pingStatus + ';' + clientIp + ';' + jitterStatus + ';' + dlProgress + ';' + ulProgress + ';' + pingProgress)
  }
  if (params[0] === 'start' && testStatus === -1) { // start new test
    testStatus = 0
    try {
      // parse settings, if present
      var s = {}
      try{
        var ss = e.data.substring(5)
        if (ss) s = JSON.parse(ss)
      }catch(e){ twarn('Error parsing custom settings JSON. Please check your syntax') }
      //copy custom settings
      for(var key in s){
        if(typeof settings[key] !== 'undefined') settings[key]=s[key]; else twarn("Unknown setting ignored: "+key);
      }
      // quirks for specific browsers. apply only if not overridden. more may be added in future releases
      if (settings.enable_quirks||(typeof s.enable_quirks !== 'undefined' && s.enable_quirks)) {
        var ua = navigator.userAgent
        if (/Firefox.(\d+\.\d+)/i.test(ua)) {
          if(typeof s.xhr_ulMultistream === 'undefined'){
            // ff more precise with 1 upload stream
            settings.xhr_ulMultistream = 1
          }
        }
        if (/Edge.(\d+\.\d+)/i.test(ua)) {
          if(typeof s.xhr_dlMultistream === 'undefined'){
            // edge more precise with 3 download streams
            settings.xhr_dlMultistream = 3
          }
        }
        if (/Chrome.(\d+)/i.test(ua) && (!!self.fetch)) {
          if(typeof s.xhr_dlMultistream === 'undefined'){
            // chrome more precise with 5 streams
            settings.xhr_dlMultistream = 5
          }
        }
      }
      if (/Edge.(\d+\.\d+)/i.test(ua)) {
        //Edge 15 introduced a bug that causes onprogress events to not get fired, we have to use the "small chunks" workaround that reduces accuracy
        settings.forceIE11Workaround = true
      }
	  if (/Chrome.(\d+)/i.test(ua)&&/Android|iPhone|iPad|iPod|Windows Phone/i.test(ua)){ //cheap af
		//Chrome mobile introduced a limitation somewhere around version 65, we have to limit XHR upload size to 4 megabytes
		settings.xhr_ul_blob_megabytes=4;
	  }
      //telemetry_level has to be parsed and not just copied
      if(typeof s.telemetry_level !== 'undefined') settings.telemetry_level = s.telemetry_level === 'basic' ? 1 : s.telemetry_level === 'full' ? 2 : 0; // telemetry level
      //transform test_order to uppercase, just in case
      settings.test_order=settings.test_order.toUpperCase();
    } catch (e) { twarn('Possible error in custom test settings. Some settings may not be applied. Exception: '+e) }
    // run the tests
    tlog(JSON.stringify(settings))
    test_pointer=0;
	var iRun=false,dRun=false,uRun=false,pRun=false;
    var runNextTest=function(){
      if(testStatus==5) return;
      if(test_pointer>=settings.test_order.length){testStatus=4; sendTelemetry(); return;}
      switch(settings.test_order.charAt(test_pointer)){
        case 'I':{test_pointer++; if(iRun) {runNextTest(); return;} else iRun=true; getIp(runNextTest);} break;
        case 'D':{test_pointer++; if(dRun) {runNextTest(); return;} else dRun=true;  testStatus=1; dlTest(runNextTest);} break;
        case 'U':{test_pointer++; if(uRun) {runNextTest(); return;} else uRun=true; testStatus=3; ulTest(runNextTest);} break;
        case 'P':{test_pointer++; if(pRun) {runNextTest(); return;} else pRun=true; testStatus=2; pingTest(runNextTest);} break;
        case '_':{test_pointer++; setTimeout(runNextTest,1000);} break;
        default: test_pointer++;
      }
    }
    runNextTest()
  }
  if (params[0] === 'abort') { // abort command
    tlog('manually aborted')
    clearRequests() // stop all xhr activity
    runNextTest=null;
    if (interval) clearInterval(interval) // clear timer if present
    if (settings.telemetry_level > 1) sendTelemetry()
	  testStatus = 5; dlStatus = ''; ulStatus = ''; pingStatus = ''; jitterStatus = '' // set test as aborted
  }
})
// stops all XHR activity, aggressively
function clearRequests () {
  tlog('stopping pending XHRs')
  if (xhr) {
    for (var i = 0; i < xhr.length; i++) {
      try { xhr[i].onprogress = null; xhr[i].onload = null; xhr[i].onerror = null } catch (e) { }
      try { xhr[i].upload.onprogress = null; xhr[i].upload.onload = null; xhr[i].upload.onerror = null } catch (e) { }
      try { xhr[i].abort() } catch (e) { }
      try { delete (xhr[i]) } catch (e) { }
    }
    xhr = null
  }
}
// gets client's IP using url_getIp, then calls the done function
var ipCalled = false // used to prevent multiple accidental calls to getIp
function getIp (done) {
  tlog('getIp')
  if (ipCalled) return; else ipCalled = true // getIp already called?
  xhr = new XMLHttpRequest()
  xhr.onload = function () {
	tlog("IP: "+xhr.responseText)
    clientIp = xhr.responseText
    done()
  }
  xhr.onerror = function () {
	tlog('getIp failed')
    done()
  }
  xhr.open('GET', settings.url_getIp + url_sep(settings.url_getIp) + (settings.getIp_ispInfo?("isp=true"+(settings.getIp_ispInfo_distance?("&distance="+settings.getIp_ispInfo_distance+"&"):"&")):"&") + 'r=' + Math.random(), true)
  xhr.send()
}
// download test, calls done function when it's over
var dlCalled = false // used to prevent multiple accidental calls to dlTest
function dlTest (done) {
  tlog('dlTest')
  if (dlCalled) return; else dlCalled = true // dlTest already called?
  var totLoaded = 0.0, // total number of loaded bytes
    startT = new Date().getTime(), // timestamp when test was started
    graceTimeDone = false, //set to true after the grace time is past
    failed = false // set to true if a stream fails
  xhr = []
  // function to create a download stream. streams are slightly delayed so that they will not end at the same time
  var testStream = function (i, delay) {
    setTimeout(function () {
      if (testStatus !== 1) return // delayed stream ended up starting after the end of the download test
      tlog('dl test stream started '+i+' '+delay)
      var prevLoaded = 0 // number of bytes loaded last time onprogress was called
      var x = new XMLHttpRequest()
      xhr[i] = x
      xhr[i].onprogress = function (event) {
        tlog('dl stream progress event '+i+' '+event.loaded)
        if (testStatus !== 1) { try { x.abort() } catch (e) { } } // just in case this XHR is still running after the download test
        // progress event, add number of new loaded bytes to totLoaded
        var loadDiff = event.loaded <= 0 ? 0 : (event.loaded - prevLoaded)
        if (isNaN(loadDiff) || !isFinite(loadDiff) || loadDiff < 0) return // just in case
        totLoaded += loadDiff
        prevLoaded = event.loaded
      }.bind(this)
      xhr[i].onload = function () {
        // the large file has been loaded entirely, start again
        tlog('dl stream finished '+i)
        try { xhr[i].abort() } catch (e) { } // reset the stream data to empty ram
        testStream(i, 0)
      }.bind(this)
      xhr[i].onerror = function () {
        // error
        tlog('dl stream failed '+i)
        if (settings.xhr_ignoreErrors === 0) failed=true //abort
        try { xhr[i].abort() } catch (e) { }
        delete (xhr[i])
        if (settings.xhr_ignoreErrors === 1) testStream(i, 0) //restart stream
      }.bind(this)
      // send xhr
      try { if (settings.xhr_dlUseBlob) xhr[i].responseType = 'blob'; else xhr[i].responseType = 'arraybuffer' } catch (e) { }
      xhr[i].open('GET', settings.url_dl + url_sep(settings.url_dl) + 'r=' + Math.random() + '&ckSize=' + settings.garbagePhp_chunkSize, true) // random string to prevent caching
      xhr[i].send()
    }.bind(this), 1 + delay)
  }.bind(this)
  // open streams
  for (var i = 0; i < settings.xhr_dlMultistream; i++) {
    testStream(i, settings.xhr_multistreamDelay * i)
  }
  // every 200ms, update dlStatus
  interval = setInterval(function () {
    tlog('DL: '+dlStatus+(graceTimeDone?'':' (in grace time)'))
    var t = new Date().getTime() - startT
	if (graceTimeDone) dlProgress = t / (settings.time_dl * 1000)
    if (t < 200) return
    if (!graceTimeDone){
      if (t > 1000 * settings.time_dlGraceTime){
        if (totLoaded > 0){ // if the connection is so slow that we didn't get a single chunk yet, do not reset
          startT = new Date().getTime()
          totLoaded = 0.0;
        }
        graceTimeDone = true;
      }
    }else{
      var speed = totLoaded / (t / 1000.0)
      dlStatus = ((speed * 8 * settings.overheadCompensationFactor)/(settings.useMebibits?1048576:1000000)).toFixed(2) // speed is multiplied by 8 to go from bytes to bits, overhead compensation is applied, then everything is divided by 1048576 or 1000000 to go to megabits/mebibits
      if (((t / 1000.0) > settings.time_dl && dlStatus > 0) || failed) { // test is over, stop streams and timer
        if (failed || isNaN(dlStatus)) dlStatus = 'Fail'
        clearRequests()
        clearInterval(interval)
		dlProgress = 1
        tlog('dlTest finished '+dlStatus)
        done()
      }
    }
  }.bind(this), 200)
}
// upload test, calls done function whent it's over

var ulCalled = false // used to prevent multiple accidental calls to ulTest
function ulTest (done) {
  tlog('ulTest')
  if (ulCalled) return; else ulCalled = true // ulTest already called?
// garbage data for upload test
  var r = new ArrayBuffer(1048576)
  try { r = new Float32Array(r); for (var i = 0; i < r.length; i++)r[i] = Math.random() } catch (e) { }
  var req = []
  var reqsmall = []
  for (var i = 0; i < settings.xhr_ul_blob_megabytes; i++) req.push(r)
  req = new Blob(req)
  r = new ArrayBuffer(262144)
  try { r = new Float32Array(r); for (var i = 0; i < r.length; i++)r[i] = Math.random() } catch (e) { }
  reqsmall.push(r)
  reqsmall = new Blob(reqsmall)
  var totLoaded = 0.0, // total number of transmitted bytes
    startT = new Date().getTime(), // timestamp when test was started
    graceTimeDone = false, //set to true after the grace time is past
    failed = false // set to true if a stream fails
  xhr = []
  // function to create an upload stream. streams are slightly delayed so that they will not end at the same time
  var testStream = function (i, delay) {
    setTimeout(function () {
      if (testStatus !== 3) return // delayed stream ended up starting after the end of the upload test
      tlog('ul test stream started '+i+' '+delay)
      var prevLoaded = 0 // number of bytes transmitted last time onprogress was called
      var x = new XMLHttpRequest()
      xhr[i] = x
      var ie11workaround
      if (settings.forceIE11Workaround) ie11workaround = true; else {
        try {
          xhr[i].upload.onprogress
          ie11workaround = false
        } catch (e) {
          ie11workaround = true
        }
      }
      if (ie11workaround) {
        // IE11 workarond: xhr.upload does not work properly, therefore we send a bunch of small 256k requests and use the onload event as progress. This is not precise, especially on fast connections
        xhr[i].onload = function () {
        tlog('ul stream progress event (ie11wa)')
          totLoaded += reqsmall.size;
          testStream(i, 0)
        }
        xhr[i].onerror = function () {
          // error, abort
          tlog('ul stream failed (ie11wa)')
          if (settings.xhr_ignoreErrors === 0) failed = true //abort
          try { xhr[i].abort() } catch (e) { }
          delete (xhr[i])
          if (settings.xhr_ignoreErrors === 1) testStream(i,0); //restart stream
        }
        xhr[i].open('POST', settings.url_ul + url_sep(settings.url_ul) + 'r=' + Math.random(), true) // random string to prevent caching
        xhr[i].setRequestHeader('Content-Encoding', 'identity') // disable compression (some browsers may refuse it, but data is incompressible anyway)
        xhr[i].send(reqsmall)
      } else {
        // REGULAR version, no workaround
        xhr[i].upload.onprogress = function (event) {
          tlog('ul stream progress event '+i+' '+event.loaded)
          if (testStatus !== 3) { try { x.abort() } catch (e) { } } // just in case this XHR is still running after the upload test
          // progress event, add number of new loaded bytes to totLoaded
          var loadDiff = event.loaded <= 0 ? 0 : (event.loaded - prevLoaded)
          if (isNaN(loadDiff) || !isFinite(loadDiff) || loadDiff < 0) return // just in case
          totLoaded += loadDiff
          prevLoaded = event.loaded
        }.bind(this)
        xhr[i].upload.onload = function () {
          // this stream sent all the garbage data, start again
          tlog('ul stream finished '+i)
          testStream(i, 0)
        }.bind(this)
        xhr[i].upload.onerror = function () {
          tlog('ul stream failed '+i)
          if (settings.xhr_ignoreErrors === 0) failed=true //abort
          try { xhr[i].abort() } catch (e) { }
          delete (xhr[i])
          if (settings.xhr_ignoreErrors === 1) testStream(i, 0) //restart stream
        }.bind(this)
        // send xhr
        xhr[i].open('POST', settings.url_ul + url_sep(settings.url_ul) + 'r=' + Math.random(), true) // random string to prevent caching
        xhr[i].setRequestHeader('Content-Encoding', 'identity') // disable compression (some browsers may refuse it, but data is incompressible anyway)
        xhr[i].send(req)
      }
    }.bind(this), 1)
  }.bind(this)
  // open streams
  for (var i = 0; i < settings.xhr_ulMultistream; i++) {
    testStream(i, settings.xhr_multistreamDelay * i)
  }
  // every 200ms, update ulStatus
  interval = setInterval(function () {
	tlog('UL: '+ulStatus+(graceTimeDone?'':' (in grace time)'))
    var t = new Date().getTime() - startT
	if (graceTimeDone) ulProgress = t / (settings.time_ul * 1000)
    if (t < 200) return
    if (!graceTimeDone){
      if (t > 1000 * settings.time_ulGraceTime){
        if (totLoaded > 0){ // if the connection is so slow that we didn't get a single chunk yet, do not reset
          startT = new Date().getTime()
          totLoaded = 0.0;
        }
        graceTimeDone = true;
      }
    }else{
      var speed = totLoaded / (t / 1000.0)
      ulStatus = ((speed * 8 * settings.overheadCompensationFactor)/(settings.useMebibits?1048576:1000000)).toFixed(2) // speed is multiplied by 8 to go from bytes to bits, overhead compensation is applied, then everything is divided by 1048576 or 1000000 to go to megabits/mebibits
      if (((t / 1000.0) > settings.time_ul && ulStatus > 0) || failed) { // test is over, stop streams and timer
        if (failed || isNaN(ulStatus)) ulStatus = 'Fail'
        clearRequests()
        clearInterval(interval)
		ulProgress = 1
        tlog('ulTest finished '+ulStatus)
        done()
      }
    }
  }.bind(this), 200)
}
// ping+jitter test, function done is called when it's over
var ptCalled = false // used to prevent multiple accidental calls to pingTest
function pingTest (done) {
  tlog('pingTest')
  if (ptCalled) return; else ptCalled = true // pingTest already called?
  var prevT = null // last time a pong was received
  var ping = 0.0 // current ping value
  var jitter = 0.0 // current jitter value
  var i = 0 // counter of pongs received
  var prevInstspd = 0 // last ping time, used for jitter calculation
  xhr = []
  // ping function
  var doPing = function () {
    tlog('ping')
	pingProgress = i / settings.count_ping
    prevT = new Date().getTime()
    xhr[0] = new XMLHttpRequest()
    xhr[0].onload = function () {
      // pong
      tlog('pong')
      if (i === 0) {
        prevT = new Date().getTime() // first pong
      } else {
        var instspd = new Date().getTime() - prevT
		if(settings.ping_allowPerformanceApi){
			try{
				//try to get accurate performance timing using performance api
				var p=performance.getEntries()
				p=p[p.length-1]
				var d = p.responseStart - p.requestStart //best precision: chromium-based
				if (d<=0) d=p.duration //edge: not so good precision because it also considers the overhead and there is no way to avoid it
				if (d>0&&d<instspd) instspd=d
			}catch(e){
				//if not possible, keep the estimate
				//firefox can't access performance api from worker: worst precision
				tlog('Performance API not supported, using estimate')
			}
		}
        var instjitter = Math.abs(instspd - prevInstspd)
        if (i === 1) ping = instspd; /* first ping, can't tell jitter yet*/ else {
          ping = ping * 0.9 + instspd * 0.1 // ping, weighted average
          jitter = instjitter > jitter ? (jitter * 0.2 + instjitter * 0.8) : (jitter * 0.9 + instjitter * 0.1) // update jitter, weighted average. spikes in ping values are given more weight.
        }
        prevInstspd = instspd
      }
      pingStatus = ping.toFixed(2)
      jitterStatus = jitter.toFixed(2)
      i++
      tlog('PING: '+pingStatus+' JITTER: '+jitterStatus)
      if (i < settings.count_ping) doPing(); else {pingProgress = 1; done()} // more pings to do?
    }.bind(this)
    xhr[0].onerror = function () {
      // a ping failed, cancel test
      tlog('ping failed')
      if (settings.xhr_ignoreErrors === 0) { //abort
        pingStatus = 'Fail'
        jitterStatus = 'Fail'
        clearRequests()
        done()
      }
      if (settings.xhr_ignoreErrors === 1) doPing() //retry ping
      if (settings.xhr_ignoreErrors === 2){ //ignore failed ping
        i++
        if (i < settings.count_ping) doPing(); else done() // more pings to do?
      }
    }.bind(this)
    // sent xhr
    xhr[0].open('GET', settings.url_ping + url_sep(settings.url_ping) + 'r=' + Math.random(), true) // random string to prevent caching
    xhr[0].send()
  }.bind(this)
  doPing() // start first ping
}
// telemetry
function sendTelemetry(){
  if (settings.telemetry_level < 1) return
  xhr = new XMLHttpRequest()
  xhr.onload = function () { console.log('TELEMETRY OL '+xhr.responseText) }
  xhr.onerror = function () { console.log('TELEMETRY ERROR '+xhr) }
  xhr.open('POST', settings.url_telemetry+url_sep(settings.url_telemetry)+"r="+Math.random(), true);
  try{
    var fd = new FormData()
    fd.append('dl', dlStatus)
    fd.append('ul', ulStatus)
    fd.append('ping', pingStatus)
    fd.append('jitter', jitterStatus)
    fd.append('log', settings.telemetry_level>1?log:"")
    xhr.send(fd)
  }catch(ex){
    var postData = 'dl='+encodeURIComponent(dlStatus)+'&ul='+encodeURIComponent(ulStatus)+'&ping='+encodeURIComponent(pingStatus)+'&jitter='+encodeURIComponent(jitterStatus)+'&log='+encodeURIComponent(settings.telemetry_level>1?log:'')
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded')
    xhr.send(postData)
  }


}