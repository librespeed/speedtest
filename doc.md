# HTML5 Speedtest

> by Federico Dossena  
> Version 4.2.8, July 16 2017  
> [https://github.com/adolfintel/speedtest/](https://github.com/adolfintel/speedtest/)


## Introduction
In this document, we will introduce an XHR based HTML5 Speedtest and see how to use it.
This test measures download speed, upload speed, ping and jitter.

First of all, the requirements to run this test:

* The browser have to support XHR Level 2 and Web Workers and Javascript must be enabled.
    * Internet Explorer 11
    * Microsoft Edge 12+
    * Mozilla Firefox 12+
    * Google Chrome / Chromium 31+
    * Apple Safari 7.1+
    * Opera 18+
* Client side, the test can use up to 500 megabytes of RAM
* Server side, you'll need a fast connection (at least 100 Mbps recommended), and the web server must accept large POST requests (up to 20 megabytes).
  Apache2 and PHP are recommended, but not mandatory.

If this looks good, let's proceed and see how to use the test.

## Quick installation videos
* [Debian 9.0 with Apache](https://fdossena.com/?p=speedtest/quickstart_deb.frag)
* [Windows Server 2016 with IIS](https://fdossena.com/?p=speedtest/quickstart_win.frag)

## Installation
To install the test on your server, upload the following files:

* `speedtest_worker.min.js`
* `garbage.php`
* `getIP.php`
* `empty.php`

You may also want to upload one of the examples to test it.  
Later we'll see how to use the test without PHP.

__Important:__ keep all the files together; all paths are relative to the js file


## Usage
To run the test, you need to do 3 things:

* Create the worker
* Write some code that handles the responses coming from the worker
* Start the test

### Creating the worker
```js
var w = new Worker("speedtest_worker.min.js")
```

__Important:__ use the minified version, it's smaller!

### Response handler
First, we set up a timer that fetches the status of the worker continuously:
```js
var timer = setInterval(function () {
  w.postMessage('status')
}, 100)
```

Then we write a response handler that receives the status and updates the page. Later
we'll see the details of the format of the response.

```js
w.onmessage = function (event) {
  var data = event.data.split(';')
  var testState = data[0]
  var dlStatus = data[1]
  var ulStatus = data[2]
  var pingStatus = data[3]
  var jitterStatus = data[5]
  var clientIp = data[4]
  if (testState >= 4) {
    clearInterval(timer) // test is finished or aborted
  }
  // .. update your page here ..
}
```

#### Response format
The response from the worker is composed of values separated by `;` (semicolon) in this
format:

`testState;dlStatus;ulStatus;pingStatus;clientIp;jitterStatus`

* __testState__ is an integer 0-5
    * `0` = Test starting
    * `1` = Download test in progress
    * `2` = Ping + Jitter test in progress
    * `3` = Upload test in progress
    * `4` = Test finished
    * `5` = Test aborted
* __dlStatus__ is either
    * Empty string (not started or aborted)
    * Download speed in Megabit/s as a number with 2 decimals
    * The string "Fail" (test failed)
* __ulStatus__ is either
    * Empty string (not started or aborted)
    * Upload speed in Megabit/s as a number with 2 decimals
    * The string "Fail" (test failed)
* __pingStatus__ is either
    * Empty string (not started or aborted)
    * Estimated ping in milliseconds as a number with 2 decimals
    * The string "Fail" (test failed)
* __clientIp__ is either
    * Empty string (not fetched yet or failed)
    * The client's IP address as a string
* __jitterStatus__ is either
    * Empty string (not started or aborted)
    * Estimated jitter in milliseconds as a number with 2 decimals (lower = stable connection)
    * The string "Fail" (test failed)

### Starting the test
To start the test with the default settings, which is usually the best choice, send the start command to the worker:

```js
w.postMessage('start')
```

If you want, you can change these settings and pass them to the worker as JSON when you start it, like this:

```js
w.postMessage('start {"param1": "value1", "param2": "value2", ...}')
```

Pass "-1" to disable specific tests

#### Test parameters
* __time_dl__: How long the download test should be in seconds. The test will continue regardless of this limit if the speed is still 0.00 when the limit is reached.
    * Default: `15`
    * Recommended: `>=5`
* __time_ul__: How long the upload test should be in seconds. The test will continue regardless of this limit if the speed is still 0.00 when the limit is reached.
    * Default: `15`
    * Recommended: `>=10`
* __count_ping__: How many pings to perform in the ping test
    * Default: `35`
    * Recommended: `>=20`
* __url_dl__: path to garbage.php or a large file to use for the download test
    * Default: `garbage.php`
    * __Important:__ path is relative to js file
* __url_ul__: path to an empty file or empty.php to use for the upload test
    * Default: `empty.php`
    * __Important:__ path is relative to js file
* __url_ping__: path to an empty file or empty.php to use for the ping test
    * Default: `empty.php`
    * __Important:__ path is relative to js file
* __url_getIp__: path to getIP.php or replacement
    * Default: `getIP.php`
    * __Important:__ path is relative to js file

#### Advanced test parameters
* __enable_quirks__: enables browser-specific optimizations. These optimizations override some of the default settings. They do not override settings that are explicitly set.
    * Default: `true`
* __garbagePhp_chunkSize__: size of chunks sent by garbage.php in megabytes
    * Default: `20`
    * Recommended: `>=10`
    * Default override: 5 on Safari if enable_quirks is true
* __xhr_dlMultistream__: how many streams should be opened for the download test
    * Default: `10`
    * Recommended: `>=3`
    * Default override: 3 on Edge if enable_quirks is true
    * Default override: 5 on Chromium-based if enable_quirks is true
* __xhr_ulMultistream__: how many streams should be opened for the upload test
    * Default: `3`
    * Recommended: `>=1`
    * Default override: 1 on Firefox if enable_quirks is true
* __xhr_ignoreErrors__: how to react to errors in download/upload streams and the ping test
    * `0`: Fail test on error (behaviour of previous versions of this test)
    * `1`: Restart a stream/ping when it fails
    * `2`: Ignore all errors
    * Default: `1`
    * Recommended: `1`
* __time_dlGraceTime__: How long to wait (in seconds) before actually measuring the download speed. This is a good idea because we want to wait for the TCP window to be at its maximum (or close to it)
    * Default: `1.5`
    * Recommended: `>=0`
* __time_ulGraceTime__: How long to wait (in seconds) before actually measuring the upload speed. This is a good idea because we want to wait for the buffers to be full (avoids the peak at the beginning of the test)
    * Default: `3`
    * Recommended: `>=1`
* __overheadCompensationFactor__: compensation for HTTP and network overhead. Default value assumes typical MTUs used over the Internet. You might want to change this if you're using this in your internal network with different MTUs, or if you're using IPv6 instead of IPv4.
    * Default: `1.13359567567567567568` (1048576/925000) assumes HTTP+TCP+IPv4+ETH with typical MTUs used over the Internet
    * `1.0513`: HTTP+TCP+IPv6+ETH, over the Internet (empirically tested, not calculated)
    * `1.0369`: Alternative value for HTTP+TCP+IPv4+ETH, over the Internet (empirically tested, not calculated)
    * `1460 / 1514`: TCP+IPv4+ETH, ignoring HTTP overhead
    * `1440 / 1514`: TCP+IPv6+ETH, ignoring HTTP overhead
    * `1`: ignore overheads. This measures the speed at which you actually download and upload files

### Aborting the test prematurely
The test can be aborted at any time by sending an abort command to the worker:

```js
w.postMessage('abort')
```

This will terminate all network activity and stop the worker.

__Important:__ do not simply kill the worker while it's running, as it may leave pending XHR requests!

## Troubleshooting
These are the most common issues reported by users, and how to fix them

#### Download test gives very low result
Are garbage.php and empty.php (or your replacements) reachable?  
Press F12, select network and start the test. Do you see errors? (cancelled requests are not errors)  
If a small download starts, open it in a text editor. Does it say it's missing openssl_random_pseudo_bytes()? In this case, install OpenSSL (this is usually included when you install Apache and PHP on most distros).

#### Upload test is inaccurate, and I see lag spikes
Check your server's maximum POST size, make sure it's at least 20Mbytes, possibly more

#### All tests are wrong, give extremely high results, browser lags/crashes, ...
You're running the test on localhost, therefore it is trying to measure the speed of your loopback interface. The test is meant to be run over an Internet connection, from a different machine.

#### Ping test shows double the actual ping
Make sure your server is sending the ```Connection:keep-alive``` header

## Using the test without PHP
If your server does not support PHP, or you're using something newer like Node.js, you can still use this test by replacing `garbage.php`, `empty.php` and `getIP.php` with equivalents.

### Replacements

#### Replacement for `garbage.php`
A replacement for `garbage.php` must generate incompressible garbage data.

A large file (10-100 Mbytes) is a possible replacement. You can get [one here](http://downloads.fdossena.com/geth.php?r=speedtest-bigfile).

If you're using Node.js or some other server, your replacement should accept the `ckSize` parameter (via GET) which tells it how many megabytes of garbage to generate.
It is important here to turn off compression, and generate incompressible data.
A symlink to `/dev/urandom` is also ok.

#### Replacement for `empty.php`
Your replacement must simply respond with a HTTP code 200 and send nothing else. You may want to send additional headers to disable caching. The test assumes that Connection:keep-alive is sent by the server.

#### Replacement for `getIP.php`
Your replacement must simply respond with the client's IP as plaintext. Nothing fancy.

#### JS
You need to start the test with your replacements like this:

```js
w.postMessage('start {"url_dl": "newGarbageURL", "url_ul": "newEmptyURL", "url_ping": "newEmptyURL", "url_getIp": "newIpURL"}')
```


## Known bugs and limitations
* The ping/jitter test is measured by seeing how long it takes for an empty XHR to complete. It is not an acutal ICMP ping
* __Chrome:__ high CPU usage from XHR requests with very fast connections (like gigabit).
  For this reason, the test may report inaccurate results if your CPU is too slow. (Does not affect most computers)
* __IE11:__ the upload test is not precise on very fast connections
* __IE11:__ the upload test may not work over HTTPS
* __Safari:__ works, but needs more testing and tweaking for very fast connections
* __Firefox:__ on some Linux systems with hardware acceleration turned off, the page rendering makes the browser lag, reducing the accuracy of the ping/jitter test

## Making changes
Since this is an open source project, you can modify it.

To make changes to the speedtest itself, edit `speedtest_worker.js`

To create the minified version, use UglifyJS like this:

```
uglifyjs -c --screw-ie8 speedtest_worker.js > speedtest_worker.min.js
```

Pull requests are much appreciated. If you don't use github (or git), simply contact me at dosse91@paranoici.org.

__Important:__ please add your name to modified versions to distinguish them from the main project.


## License
This software is under the GNU LGPL license, Version 3 or newer.

To put it short: you are free to use, study, modify, and redistribute this software and modified versions of it, for free or for money.
You can also use it in proprietary software but all changes to this software must remain under the same GNU LGPL license.
