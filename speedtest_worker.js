/*
    HTML5 Speedtest v4.2.1
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
var pot = '' // point of test (fastest answer for a ping test)
var packetLoss = 0 // failed ping requests
var country = '' // country of origin
var region = '' // region of origin
var isp = '' // internet service provider

// test settings. can be overridden by sending specific values with the start command
var settings = {
  time_ul: 15, // duration of upload test in seconds
  time_dl: 15, // duration of download test in seconds
  count_ping: 35, // number of pings to perform in ping test
  url_dl: '/download/', // path to a large file or garbage.php, used for download test.
  url_ul: '/upload', // path to an empty file, used for upload test.
  url_ping: '/ping', // path to an empty file, used for ping test.
  url_getIp: '/ip', // path to getIP.php, or a similar thing that outputs the client's ip
  url_getPointsOfTest: '/pots', // REST service URL to retrieve the list of available Points of Test
  url_saveResult: '/save', // REST service URL to save test results
  url_ispInfo: 'http://ipinfo.io', // Geolocation service and ISP information
  xhr_dlMultistream: 10, // number of download streams to use (can be different if enable_quirks is active)
  xhr_ulMultistream: 3, // number of upload streams to use (can be different if enable_quirks is active)
  xhr_dlUseBlob: false, // if set to true, it reduces ram usage but uses the hard drive (useful with large garbagePhp_chunkSize and/or high xhr_dlMultistream)
  garbagePhp_chunkSize: 20, // size of chunks sent by garbage.php (can be different if enable_quirks is active)
  enable_quirks: true, // enable quirks for specific browsers. currently it overrides settings to optimize for specific browsers, unless they are already being overridden with the start command
  enable_multiPots: false, // enable multiple points of test
  allow_fetchAPI: false, // enables Fetch API. currently disabled because it leaks memory like no tomorrow
  force_fetchAPI: false // when Fetch API is enabled, it will force usage on every browser that supports it
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
    listener for commands from main thread to this worker.
    commands:
    -status: returns the current status as a string of values spearated by a semicolon (;) in this order: testStatus;dlStatus;ulStatus;pingStatus;clientIp;jitterStatus
    -abort: aborts the current test
    -start: starts the test. optionally, settings can be passed as JSON.
        example: start {"time_ul":"10", "time_dl":"10", "count_ping":"50"}
*/
this.addEventListener('message', function (e) {
  var params = e.data.split(' ')
  var useragent = ''
  try {
    useragent = navigator.userAgent
  } catch (e) { console.log(e.message) }
  if (params[0] === 'status') { // return status
    postMessage(testStatus + ';' + dlStatus + ';' + ulStatus + ';' + pingStatus + ';' + clientIp + ';' + jitterStatus + ';' + pot + ';' + packetLoss + ';' + country + ';' + region + ';' + isp + ';' + useragent)
    saveResult({
      'download': dlStatus,
      'upload': ulStatus,
      'ping': pingStatus,
      'jitter': jitterStatus,
      'packetloss': packetLoss,
      'pot': pot,
      'useragent': useragent,
      'country': country,
      'region': region,
      'isp': isp
    })
  }
  if (params[0] === 'start' && testStatus === 0) { // start new test
    testStatus = 1
    try {
      // parse settings, if present
      var s = JSON.parse(e.data.substring(5))
      if (typeof s.url_dl !== 'undefined') settings.url_dl = s.url_dl // download url
      if (typeof s.url_ul !== 'undefined') settings.url_ul = s.url_ul // upload url
      if (typeof s.url_ping !== 'undefined') settings.url_ping = s.url_ping // ping url
      if (typeof s.url_getIp !== 'undefined') settings.url_getIp = s.url_getIp // url to getIP.php
      if (typeof s.url_getPointsOfTest !== 'undefined') settings.url_getPointsOfTest = s.url_getPointsOfTest // point of test list url
      if (typeof s.url_saveResult !== 'undefined') settings.url_saveResult = s.url_saveResult // save result url
      if (typeof s.time_dl !== 'undefined') settings.time_dl = s.time_dl // duration of download test
      if (typeof s.time_ul !== 'undefined') settings.time_ul = s.time_ul // duration of upload test
      if (typeof s.enable_quirks !== 'undefined') settings.enable_quirks = s.enable_quirks // enable quirks or not
      if (typeof s.enable_multiPots !== 'undefined') settings.enable_multiPots = s.enable_multiPots // enable multiple points of test and lowest latency detection using cross domain requests
      if (typeof s.allow_fetchAPI !== 'undefined') settings.allow_fetchAPI = s.allow_fetchAPI // allows fetch api to be used if supported
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
        if ((/Safari.(\d+)/i.test(ua)) && !(/Chrome.(\d+)/i.test(ua))) {
          // safari more precise with 10 upload streams and 5mb chunks for download test
          settings.xhr_ulMultistream = 10
          settings.garbagePhp_chunkSize = 5
        }
        if (/Chrome.(\d+)/i.test(ua) && (!!self.fetch)) {
          // chrome can't handle large xhr very well, use fetch api if available and allowed
          if (settings.allow_fetchAPI) useFetchAPI = true
          // chrome more precise with 5 streams
          settings.xhr_dlMultistream = 5
        }
      }
      if (typeof s.count_ping !== 'undefined') settings.count_ping = s.count_ping // number of pings for ping test
      if (typeof s.xhr_dlMultistream !== 'undefined') settings.xhr_dlMultistream = s.xhr_dlMultistream // number of download streams
      if (typeof s.xhr_ulMultistream !== 'undefined') settings.xhr_ulMultistream = s.xhr_ulMultistream // number of upload streams
      if (typeof s.xhr_dlUseBlob !== 'undefined') settings.xhr_dlUseBlob = s.xhr_dlUseBlob // use blob for download test
      if (typeof s.garbagePhp_chunkSize !== 'undefined') settings.garbagePhp_chunkSize = s.garbagePhp_chunkSize // size of garbage.php chunks
      if (typeof s.force_fetchAPI !== 'undefined') settings.force_fetchAPI = s.force_fetchAPI // use fetch api on all browsers that support it if enabled
      if (settings.allow_fetchAPI && settings.force_fetchAPI && (!!self.fetch)) useFetchAPI = true
    } catch (e) { }
    // run the tests
    console.log(settings)
    console.log('Fetch API: ' + useFetchAPI)
    getServer(function () {
      ispInfo(function () {
        dlTest(function () {
          testStatus = 2
          pingTest(function () {
            testStatus = 3
            ulTest(function () {
              testStatus = 4
            })
          })
        })
      })
    })
  }
  if (params[0] === 'abort') { // abort command
    clearRequests() // stop all xhr activity
    if (interval) clearInterval(interval) // clear timer if present
      // set test as aborted
    testStatus = 5
    dlStatus = ''
    ulStatus = ''
    pingStatus = ''
    jitterStatus = ''
    pot = ''
    packetLoss = 0
    country = ''
    region = ''
    isp = ''
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
  xhr = new XMLHttpRequest()
  xhr.onload = function () {
    clientIp = xhr.responseText
    done()
  }
  xhr.onerror = function () {
    done()
  }
  xhr.open('GET', settings.url_getIp + '?r=' + Math.random(), true)
  xhr.send()
}
// returns a server url with the lowest latency
function getServer (done) {
  // single point of test
  if (!settings.enable_multiPots) { return done() }
  var pots = {}
  var times = []
  var ping = ''
  xhr = new XMLHttpRequest()
  xhr.onload = function () {
    var tmp = JSON.parse(xhr.responseText)
    var routes = tmp['routes']
    var pointsOfTest = tmp['result']
    var doPing = function (url) {
      var start = new Date().getTime()
      xhr = new XMLHttpRequest()
      xhr.onload = function () {
        var end = new Date().getTime()
        ping = end - start
        ping = ping.toFixed(2)
      }
      xhr.onerror = function () {
        ping = 'Fail'
      }
      xhr.open('GET', url, false)
      xhr.send()
    }
    for (var i = 0; i < pointsOfTest.length; i++) {
      try {
        doPing(pointsOfTest[i])
      } catch (e) {
        ping = 'Fail'
      }
      if (ping !== 'Fail') {
        pots[ping] = pointsOfTest[i]
        times.push(ping)
      }
    }
    times.sort(function (a, b) { return a - b })
    pot = pots[times[0]]
    settings.url_ping = pot
    settings.url_dl = pot + routes['download']
    settings.url_ul = pot + routes['upload']
    console.log('Available servers: ' + JSON.stringify(pots))
    console.log('Best point of test: ' + pot)
    done()
  }
  xhr.onerror = function () {
    done()
  }
  xhr.open('POST', settings.url_getPointsOfTest, true)
  xhr.setRequestHeader('Content-Type', 'application/json')
  xhr.setRequestHeader('Accept', 'application/json')
  xhr.send(JSON.stringify({'data': {}}))
}
// download test, calls done function when it's over
var dlCalled = false // used to prevent multiple accidental calls to dlTest
function dlTest (done) {
  if (dlCalled) return; else dlCalled = true // dlTest already called?
  var totLoaded = 0.0 // total number of loaded bytes
  var startT = new Date().getTime() // timestamp when test was started
  var failed = false // set to true if a stream fails
  xhr = []
  // function to create a download stream. streams are slightly delayed so that they will not end at the same time
  var testStream = function (i, delay) {
    setTimeout(function () {
      if (testStatus !== 1) return // delayed stream ended up starting after the end of the download test
      if (useFetchAPI) {
        var queryString = settings.url_dl + settings.garbagePhp_chunkSize + '?r=' + Math.random()
        xhr[i] = fetch(queryString).then(function (response) {
          var reader = response.body.getReader()
          var consume = function () {
            return reader.read().then(function (result) {
              if (result.done) testStream(i); else {
                totLoaded += result.value.length
                if (xhr[i].cancelRequested) reader.cancel()
              }
              return consume()
            })
          }
          return consume()
        })
      } else {
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
        }
        xhr[i].onload = function () {
          // the large file has been loaded entirely, start again
          try { xhr[i].abort() } catch (e) { } // reset the stream data to empty ram
          testStream(i, 0)
        }
        xhr[i].onerror = function () {
          // error, abort
          failed = true
          try { xhr[i].abort() } catch (e) { }
          delete (xhr[i])
        }
        // send xhr
        try { if (settings.xhr_dlUseBlob) xhr[i].responseType = 'blob'; else xhr[i].responseType = 'arraybuffer' } catch (e) { }
        var queryString = settings.url_dl + settings.garbagePhp_chunkSize + '?r=' + Math.random()
        xhr[i].open('GET', queryString, true) // random string to prevent caching
        xhr[i].send()
      }
    }, 1 + delay)
  }
  // open streams
  for (var i = 0; i < settings.xhr_dlMultistream; i++) {
    testStream(i, 100 * i)
  }
  // every 200ms, update dlStatus
  interval = setInterval(function () {
    var t = new Date().getTime() - startT
    if (t < 200) return
    var speed = totLoaded / (t / 1000.0)
    dlStatus = ((speed * 8) / 925000.0).toFixed(2) // 925000 instead of 1048576 to account for overhead
    if ((t / 1000.0) > settings.time_dl || failed) { // test is over, stop streams and timer
      if (failed || isNaN(dlStatus)) dlStatus = 'Fail'
      clearRequests()
      clearInterval(interval)
      done()
    }
  }, 200)
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
  var totLoaded = 0.0 // total number of transmitted bytes
  var startT = new Date().getTime() // timestamp when test was started
  var failed = false // set to true if a stream fails
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
          failed = true
          try { xhr[i].abort() } catch (e) { }
          delete (xhr[i])
        }
        xhr[i].open('POST', settings.url_ul + '?r=' + Math.random(), true) // random string to prevent caching
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
        }
        xhr[i].upload.onload = function () {
          // this stream sent all the garbage data, start again
          testStream(i, 0)
        }
        xhr[i].upload.onerror = function () {
          // error, abort
          failed = true
          try { xhr[i].abort() } catch (e) { }
          delete (xhr[i])
        }
        // send xhr
        xhr[i].open('POST', settings.url_ul + '?r=' + Math.random(), true) // random string to prevent caching
        xhr[i].setRequestHeader('Content-Encoding', 'identity') // disable compression (some browsers may refuse it, but data is incompressible anyway)
        xhr[i].send(req)
      }
    }, 1)
  }
  // open streams
  for (var i = 0; i < settings.xhr_ulMultistream; i++) {
    testStream(i, 100 * i)
  }
  // every 200ms, update ulStatus
  interval = setInterval(function () {
    var t = new Date().getTime() - startT
    if (t < 200) return
    var speed = totLoaded / (t / 1000.0)
    ulStatus = ((speed * 8) / 925000.0).toFixed(2) // 925000 instead of 1048576 to account for overhead
    if ((t / 1000.0) > settings.time_ul || failed) { // test is over, stop streams and timer
      if (failed || isNaN(ulStatus)) ulStatus = 'Fail'
      clearRequests()
      clearInterval(interval)
      done()
    }
  }, 200)
}
// ping+jitter test, function done is called when it's over
var ptCalled = false // used to prevent multiple accidental calls to pingTest
function pingTest (done) {
  if (ptCalled) return; else ptCalled = true // pingTest already called?
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
        var instspd = (new Date().getTime() - prevT) / 2
        var instjitter = Math.abs(instspd - prevInstspd)
        if (i === 1) ping = instspd; /* first ping, can't tell jiutter yet */ else {
          ping = ping * 0.9 + instspd * 0.1 // ping, weighted average
          jitter = instjitter > jitter ? (jitter * 0.2 + instjitter * 0.8) : (jitter * 0.9 + instjitter * 0.1) // update jitter, weighted average. spikes in ping values are given more weight.
        }
        prevInstspd = instspd
      }
      pingStatus = ping.toFixed(2)
      jitterStatus = jitter.toFixed(2)
      i++
      if (i < settings.count_ping) { doPing() } else { done() } // more pings to do?
    }
    xhr[0].onerror = function () {
      packetLoss += 1
    }
    // sent xhr
    xhr[0].open('GET', settings.url_ping + '?r=' + Math.random(), true) // random string to prevent caching
    xhr[0].send()
  }
  doPing() // start first ping
}
// get isp and geolocation data from ipinfo.io
function ispInfo (done) {
  try {
    xhr = new XMLHttpRequest()
    xhr.onload = function () {
      var ispInfo = JSON.parse(xhr.responseText)
      if (ispInfo.hasOwnProperty('ip')) { clientIp = ispInfo['ip'] }
      if (ispInfo.hasOwnProperty('country')) { country = ispInfo['country'] }
      if (ispInfo.hasOwnProperty('region')) { region = ispInfo['region'] }
      if (ispInfo.hasOwnProperty('org')) { isp = ispInfo['org'] }
      done()
    }
    xhr.onerror = function () {
      done()
    }
    xhr.open('GET', settings.url_ispInfo, true)
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.send()
  } catch (e) {
    console.log(e.message)
  }
}
// report test results
function saveResult (result) {
  if (testStatus !== 4) { return }
  xhr = new XMLHttpRequest()
  xhr.onload = function () {
    console.log(xhr.responseText)
  }
  xhr.onerror = function () {}
  xhr.open('POST', settings.url_saveResult, true)
  xhr.setRequestHeader('Content-Type', 'application/json')
  xhr.setRequestHeader('Accept', 'application/json')
  xhr.send(JSON.stringify({'data': result}))
}
