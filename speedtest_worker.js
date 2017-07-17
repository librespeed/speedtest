/*
	HTML5 Speedtest v4.2.8
	by Federico Dossena
	https://github.com/adolfintel/speedtest/
	GNU LGPLv3 License
*/

// data reported to main thread
var testStatus = 0 // 0=not started, 1=download test, 2=ping+jitter test, 3=upload test, 4=finished, 5=abort/error
var dlStatus = '' // download speed in megabit/s with 2 decimal digits
var ulStatus = '' // upload speed in megabit/s with 2 decimal digits
var pingStatus = '' // ping in milliseconds with 2 decimal digits
var jitterStatus = '' // jitter in milliseconds with 2 decimal digits
var clientIp = '' // client's IP address as reported by getIP.php

// test settings. can be overridden by sending specific values with the start command
var settings = {
  time_ul: 15, // duration of upload test in seconds
  time_dl: 15, // duration of download test in seconds
  time_ulGraceTime: 3, //time to wait in seconds before actually measuring ul speed (wait for buffers to fill)
  time_dlGraceTime: 1.5, //time to wait in seconds before actually measuring dl speed (wait for TCP window to increase)
  count_ping: 35, // number of pings to perform in ping test
  url_dl: 'garbage.php', // path to a large file or garbage.php, used for download test. must be relative to this js file
  url_ul: 'empty.php', // path to an empty file, used for upload test. must be relative to this js file
  url_ping: 'empty.php', // path to an empty file, used for ping test. must be relative to this js file
  url_getIp: 'getIP.php', // path to getIP.php relative to this js file, or a similar thing that outputs the client's ip
  xhr_dlMultistream: 10, // number of download streams to use (can be different if enable_quirks is active)
  xhr_ulMultistream: 3, // number of upload streams to use (can be different if enable_quirks is active)
  xhr_ignoreErrors: 1, // 0=fail on errors, 1=attempt to restart a stream if it fails, 2=ignore all errors
  xhr_dlUseBlob: false, // if set to true, it reduces ram usage but uses the hard drive (useful with large garbagePhp_chunkSize and/or high xhr_dlMultistream)
  garbagePhp_chunkSize: 20, // size of chunks sent by garbage.php (can be different if enable_quirks is active)
  enable_quirks: true, // enable quirks for specific browsers. currently it overrides settings to optimize for specific browsers, unless they are already being overridden with the start command
  overheadCompensationFactor: 1048576/925000 //compensation for HTTP+TCP+IP+ETH overhead. 925000 is how much data is actually carried over 1048576 (1mb) bytes downloaded/uploaded. This default value assumes HTTP+TCP+IPv4+ETH with typical MTUs over the Internet. You may want to change this if you're going through your local network with a different MTU or if you're going over IPv6 (see doc.md for some other values)
}

var xhr = null // array of currently active xhr requests
var interval = null // timer used in tests

/*
	when set to true (automatically) the download test will use the fetch api instead of xhr.
	fetch api is used if
		-allow_fetchAPI is true AND
		-(we're on chrome that supports fetch api AND enable_quirks is true) OR (we're on any browser that supports fetch api AND force_fetchAPI is true)
*/
var useFetchAPI = false

/*
  this function is used on URLs passed in the settings to determine whether we need a ? or an & as a separator
*/
function url_sep (url) { return url.match(/\?/) ? '&' : '?'; }

/*
	listener for commands from main thread to this worker.
	commands:
	-status: returns the current status as a string of values spearated by a semicolon (;) in this order: testStatus;dlStatus;ulStatus;pingStatus;clientIp;jitterStatus
	-abort: aborts the current test
	-start: starts the test. optionally, settings can be passed as JSON.
		example: start {"time_ul":"10", "time_dl":"10", "count_ping":"50"}
*/
this.addEventListener('message', function (e) {
  var params = e.data.split(' ')
  if (params[0] === 'status') { // return status
    postMessage(testStatus + ';' + dlStatus + ';' + ulStatus + ';' + pingStatus + ';' + clientIp + ';' + jitterStatus)
  }
  if (params[0] === 'start' && testStatus === 0) { // start new test
    testStatus = 1
    try {
      // parse settings, if present
      var s = {}
      try{
        var ss = e.data.substring(5);
        if (ss) s = JSON.parse(ss);
      }catch(e){ console.warn("Error parsing custom settings JSON. Please check your syntax"); }
      if (typeof s.url_dl !== 'undefined') settings.url_dl = s.url_dl // download url
      if (typeof s.url_ul !== 'undefined') settings.url_ul = s.url_ul // upload url
      if (typeof s.url_ping !== 'undefined') settings.url_ping = s.url_ping // ping url
      if (typeof s.url_getIp !== 'undefined') settings.url_getIp = s.url_getIp // url to getIP.php
      if (typeof s.time_dl !== 'undefined') settings.time_dl = s.time_dl // duration of download test
      if (typeof s.time_ul !== 'undefined') settings.time_ul = s.time_ul // duration of upload test
      if (typeof s.enable_quirks !== 'undefined') settings.enable_quirks = s.enable_quirks // enable quirks or not
      // quirks for specific browsers. more may be added in future releases
      if (settings.enable_quirks) {
        var ua = navigator.userAgent
        if (/Firefox.(\d+\.\d+)/i.test(ua)) {
          // ff more precise with 1 upload stream
          settings.xhr_ulMultistream = 1
        }
        if (/Edge.(\d+\.\d+)/i.test(ua)) {
          // edge more precise with 3 download streams
          settings.xhr_dlMultistream = 3
        }
        if (/Chrome.(\d+)/i.test(ua) && (!!self.fetch)) {
          // chrome more precise with 5 streams
          settings.xhr_dlMultistream = 5
        }
      }

      if (typeof s.count_ping !== 'undefined') settings.count_ping = s.count_ping // number of pings for ping test
      if (typeof s.xhr_dlMultistream !== 'undefined') settings.xhr_dlMultistream = s.xhr_dlMultistream // number of download streams
      if (typeof s.xhr_ulMultistream !== 'undefined') settings.xhr_ulMultistream = s.xhr_ulMultistream // number of upload streams
      if (typeof s.xhr_ignoreErrors !== 'undefined') settings.xhr_ignoreErrors = s.xhr_ignoreErrors // what to do in case of errors during the test
      if (typeof s.xhr_dlUseBlob !== 'undefined') settings.xhr_dlUseBlob = s.xhr_dlUseBlob // use blob for download test
      if (typeof s.garbagePhp_chunkSize !== 'undefined') settings.garbagePhp_chunkSize = s.garbagePhp_chunkSize // size of garbage.php chunks
      if (typeof s.time_dlGraceTime !== 'undefined') settings.time_dlGraceTime = s.time_dlGraceTime // dl test grace time before measuring
      if (typeof s.time_ulGraceTime !== 'undefined') settings.time_ulGraceTime = s.time_ulGraceTime // ul test grace time before measuring
      if (typeof s.overheadCompensationFactor !== 'undefined') settings.overheadCompensationFactor = s.overheadCompensationFactor //custom overhead compensation factor (default assumes HTTP+TCP+IP+ETH with typical MTUs)
    } catch (e) { console.warn("Possible error in custom test settings. Some settings may not be applied. Exception: "+e) }
    // run the tests
    console.log(settings)
    getIp(function () { dlTest(function () { testStatus = 2; pingTest(function () { testStatus = 3; ulTest(function () { testStatus = 4 }) }) }) })
  }
  if (params[0] === 'abort') { // abort command
    clearRequests() // stop all xhr activity
    if (interval) clearInterval(interval) // clear timer if present
    testStatus = 5; dlStatus = ''; ulStatus = ''; pingStatus = ''; jitterStatus = '' // set test as aborted
  }
})
// stops all XHR activity, aggressively
function clearRequests () {
  if (xhr) {
    for (var i = 0; i < xhr.length; i++) {
      if (useFetchAPI) try { xhr[i].cancelRequested = true } catch (e) { }
      try { xhr[i].onprogress = null; xhr[i].onload = null; xhr[i].onerror = null } catch (e) { }
      try { xhr[i].upload.onprogress = null; xhr[i].upload.onload = null; xhr[i].upload.onerror = null } catch (e) { }
      try { xhr[i].abort() } catch (e) { }
      try { delete (xhr[i]) } catch (e) { }
    }
    xhr = null
  }
}
// gets client's IP using url_getIp, then calls the done function
function getIp (done) {
  if (settings.url_getIp == "-1") {done(); return}
  xhr = new XMLHttpRequest()
  xhr.onload = function () {
    clientIp = xhr.responseText
    done()
  }
  xhr.onerror = function () {
    done()
  }
  xhr.open('GET', settings.url_getIp + url_sep(settings.url_getIp) + 'r=' + Math.random(), true)
  xhr.send()
}
// download test, calls done function when it's over
var dlCalled = false // used to prevent multiple accidental calls to dlTest
function dlTest (done) {
  if (dlCalled) return; else dlCalled = true // dlTest already called?
  if (settings.url_dl == "-1") {done(); return}
  var totLoaded = 0.0, // total number of loaded bytes
    startT = new Date().getTime(), // timestamp when test was started
    graceTimeDone = false, //set to true after the grace time is past
    failed = false // set to true if a stream fails
  xhr = []
  // function to create a download stream. streams are slightly delayed so that they will not end at the same time
  var testStream = function (i, delay) {
    setTimeout(function () {
      if (testStatus !== 1) return // delayed stream ended up starting after the end of the download test
      var prevLoaded = 0 // number of bytes loaded last time onprogress was called
      var x = new XMLHttpRequest()
      xhr[i] = x
      xhr[i].onprogress = function (event) {
        if (testStatus !== 1) { try { x.abort() } catch (e) { } } // just in case this XHR is still running after the download test
        // progress event, add number of new loaded bytes to totLoaded
        var loadDiff = event.loaded <= 0 ? 0 : (event.loaded - prevLoaded)
        if (isNaN(loadDiff) || !isFinite(loadDiff) || loadDiff < 0) return // just in case
        totLoaded += loadDiff
        prevLoaded = event.loaded
      }.bind(this)
      xhr[i].onload = function () {
        // the large file has been loaded entirely, start again
        try { xhr[i].abort() } catch (e) { } // reset the stream data to empty ram
        testStream(i, 0)
      }.bind(this)
      xhr[i].onerror = function () {
        // error
        if (settings.xhr_ignoreErrors === 0) failed=true //abort
        try { xhr[i].abort() } catch (e) { }
        delete (xhr[i])
        if (settings.xhr_ignoreErrors === 1) testStream(i, 100) //restart stream after 100ms
      }.bind(this)
      // send xhr
      try { if (settings.xhr_dlUseBlob) xhr[i].responseType = 'blob'; else xhr[i].responseType = 'arraybuffer' } catch (e) { }
      xhr[i].open('GET', settings.url_dl + url_sep(settings.url_dl) + 'r=' + Math.random() + '&ckSize=' + settings.garbagePhp_chunkSize, true) // random string to prevent caching
      xhr[i].send()
    }.bind(this), 1 + delay)
  }.bind(this)
  // open streams
  for (var i = 0; i < settings.xhr_dlMultistream; i++) {
    testStream(i, 100 * i)
  }
  // every 200ms, update dlStatus
  interval = setInterval(function () {
    var t = new Date().getTime() - startT
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
      dlStatus = ((speed * 8 * settings.overheadCompensationFactor)/1048576).toFixed(2) // speed is multiplied by 8 to go from bytes to bits, overhead compensation is applied, then everything is divided by 1048576 to go to megabits/s
      if (((t / 1000.0) > settings.time_dl && dlStatus > 0) || failed) { // test is over, stop streams and timer
        if (failed || isNaN(dlStatus)) dlStatus = 'Fail'
        clearRequests()
        clearInterval(interval)
        done()
      }
    }
  }.bind(this), 200)
}
// upload test, calls done function whent it's over
// garbage data for upload test
var r = new ArrayBuffer(1048576)
try { r = new Float32Array(r); for (var i = 0; i < r.length; i++)r[i] = Math.random() } catch (e) { }
var req = []
var reqsmall = []
for (var i = 0; i < 20; i++) req.push(r)
req = new Blob(req)
r = new ArrayBuffer(262144)
try { r = new Float32Array(r); for (var i = 0; i < r.length; i++)r[i] = Math.random() } catch (e) { }
reqsmall.push(r)
reqsmall = new Blob(reqsmall)
var ulCalled = false // used to prevent multiple accidental calls to ulTest
function ulTest (done) {
  if (ulCalled) return; else ulCalled = true // ulTest already called?
  if (settings.url_ul == "-1") {done(); return}
  var totLoaded = 0.0, // total number of transmitted bytes
    startT = new Date().getTime(), // timestamp when test was started
    graceTimeDone = false, //set to true after the grace time is past
    failed = false // set to true if a stream fails
  xhr = []
  // function to create an upload stream. streams are slightly delayed so that they will not end at the same time
  var testStream = function (i, delay) {
    setTimeout(function () {
      if (testStatus !== 3) return // delayed stream ended up starting after the end of the upload test
      var prevLoaded = 0 // number of bytes transmitted last time onprogress was called
      var x = new XMLHttpRequest()
      xhr[i] = x
      var ie11workaround
      try {
        xhr[i].upload.onprogress
        ie11workaround = false
      } catch (e) {
        ie11workaround = true
      }
      if (ie11workaround) {
        // IE11 workarond: xhr.upload does not work properly, therefore we send a bunch of small 256k requests and use the onload event as progress. This is not precise, especially on fast connections
        xhr[i].onload = function () {
          totLoaded += 262144
          testStream(i, 0)
        }
        xhr[i].onerror = function () {
          // error, abort
          if (settings.xhr_ignoreErrors === 0) failed = true //abort
          try { xhr[i].abort() } catch (e) { }
          delete (xhr[i])
          if (settings.xhr_ignoreErrors === 1) testStream(i,100); //restart stream after 100ms
        }
        xhr[i].open('POST', settings.url_ul + url_sep(settings.url_ul) + 'r=' + Math.random(), true) // random string to prevent caching
        xhr[i].setRequestHeader('Content-Encoding', 'identity') // disable compression (some browsers may refuse it, but data is incompressible anyway)
        xhr[i].send(reqsmall)
      } else {
        // REGULAR version, no workaround
        xhr[i].upload.onprogress = function (event) {
          if (testStatus !== 3) { try { x.abort() } catch (e) { } } // just in case this XHR is still running after the upload test
          // progress event, add number of new loaded bytes to totLoaded
          var loadDiff = event.loaded <= 0 ? 0 : (event.loaded - prevLoaded)
          if (isNaN(loadDiff) || !isFinite(loadDiff) || loadDiff < 0) return // just in case
          totLoaded += loadDiff
          prevLoaded = event.loaded
        }.bind(this)
        xhr[i].upload.onload = function () {
          // this stream sent all the garbage data, start again
          testStream(i, 0)
        }.bind(this)
        xhr[i].upload.onerror = function () {
          if (settings.xhr_ignoreErrors === 0) failed=true //abort
          try { xhr[i].abort() } catch (e) { }
          delete (xhr[i])
          if (settings.xhr_ignoreErrors === 1) testStream(i, 100) //restart stream after 100ms
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
    testStream(i, 100 * i)
  }
  // every 200ms, update ulStatus
  interval = setInterval(function () {
    var t = new Date().getTime() - startT
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
      ulStatus = ((speed * 8 * settings.overheadCompensationFactor)/1048576).toFixed(2) // speed is multiplied by 8 to go from bytes to bits, overhead compensation is applied, then everything is divided by 1048576 to go to megabits/s
      if (((t / 1000.0) > settings.time_ul && ulStatus > 0) || failed) { // test is over, stop streams and timer
        if (failed || isNaN(ulStatus)) ulStatus = 'Fail'
        clearRequests()
        clearInterval(interval)
        done()
      }
    }
  }.bind(this), 200)
}
// ping+jitter test, function done is called when it's over
var ptCalled = false // used to prevent multiple accidental calls to pingTest
function pingTest (done) {
  if (ptCalled) return; else ptCalled = true // pingTest already called?
  if (settings.url_ping == "-1") {done(); return}
  var prevT = null // last time a pong was received
  var ping = 0.0 // current ping value
  var jitter = 0.0 // current jitter value
  var i = 0 // counter of pongs received
  var prevInstspd = 0 // last ping time, used for jitter calculation
  xhr = []
  // ping function
  var doPing = function () {
    prevT = new Date().getTime()
    xhr[0] = new XMLHttpRequest()
    xhr[0].onload = function () {
      // pong
      if (i === 0) {
        prevT = new Date().getTime() // first pong
      } else {
        var instspd = (new Date().getTime() - prevT)
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
      if (i < settings.count_ping) doPing(); else done() // more pings to do?
    }.bind(this)
    xhr[0].onerror = function () {
      // a ping failed, cancel test
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
