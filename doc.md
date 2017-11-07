# HTML5 Speedtest

> by Federico Dossena  
> Version 4.5, November 1, 2017
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
* one of the examples

Later we'll see how to use the test without PHP, and how to configure the telemetry if you want to use it.

__Important:__ keep all the files together; all paths are relative to the js file

## Basic usage
You can start using this speedtest on your site without any special knowledge.  
Start by copying one of the included examples. Here's a description for each of them:
* `example-basic.html`: This example shows the most basic configuration possible. Everything runs with the default settings, in a very simple page where the output is shown
* `example-pretty.html`: This is a more sophisticated example, with a nicer layout and a start/stop button. __This is the best starting point for most users__
* `example-progressBar.html`: A modified version of `example-pretty.html` with a progress indicator
* `example-customSettings.html`: A modified version of `example-pretty.html` showing how the test can be started with custom parameters
* `example-customSettings2.html`: A modified version of `example-pretty.html` showing how to make a custom test with only download and upload
* `example-gauges.html`: The most sophisticated example, with the same functions as `example-pretty.html` but also gauges and progress indicators for each test. This is the nicest example included, and also a good starting point, but drawing the gauges may slow down the test on slow devices like a Raspberry Pi
* `example-chart.html`: The old example5.html, showing how to use the test with the Chart.js library
* `example-telemetry.html`: A modified version of `example-pretty.html` with basic telemetry turned on. See the section on Telemetry for details

### Customizing your example
The included examples are good starting places if you want to have a simple speedtest on your site.  
Once you've tested everything and you're sure that everything works, edit it and add some custom stuff like your logo or new colors.  
If you want to change the test parameters, for instance to make the download test shorter, you can do so in every example:  
Look for the line that contains `postMessage('start `  
This is where custom parameters can be passed to the test as a JSON string. You can write the string manually or use ``JSON.stringify`` to do that for you.
Here's an example:
```js
w.postMessage('start {"time_dl":"10"}');
```
This starts the test with default settings, but sets the download test to last only 10 seconds.  
Here's a cleaner version using ``JSON.stringify``:
```js
var params={
	time_dl:10
}
w.postMessage('start '+JSON.stringify(params))
```
Notice that there is a space after the word `start`, don't forget that!  

For a list of all test settings, look below, under Test parameters and Advanced test parameters. __Do not change anything if you don't know what you're doing.__

## Advanced usage
If you don't want to start from one of the examples, here's how to use the worker. Examples are still good for reference, so keep them handy.

To run the test, you need to do 3 things:

* Create the worker
* Write some code that handles the data coming from the worker
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
  var dlProgress = data[6]
  var ulProgress = data[7]
  var pingProgress = data[8]
  if (testState >= 4) {
    clearInterval(timer) // test is finished or aborted
  }
  // .. update your page here ..
}
```

#### Response format
The response from the worker is composed of values separated by `;` (semicolon) in this
format:

`testState;dlStatus;ulStatus;pingStatus;clientIp;jitterStatus;dlProgress;ulProgress;pingProgress`

* __testState__ is an integer between -1 and 5
    * `-1` = Test not started yet
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
* __dlProgress__: the progress of the download test as a number between 0 and 1
* __ulProgress__: the progress of the upload test as a number between 0 and 1
* __pingProgress__: the progress of the ping+jitter test as a number between 0 and 1

Note: clientIp appears before jitterStatus. This is not a mistake, it's to keep the js file compatible with older pages from before the jitter test was introduced.

### Starting the test
To start the test with the default settings, which is usually the best choice, send the start command to the worker:

```js
w.postMessage('start')
```

If you want, you can change these settings and pass them to the worker as JSON when you start it, like this:

```js
w.postMessage('start {"param1": "value1", "param2": "value2", ...}')
```
or this:
```js
var params{
	param1:value1,
	param2:value2,
	...
}
w.postMessage('start '+JSON.stringify(params))
```

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
* __url_dl__: path to garbage.php or a large file to use for the download test.
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
* __test_order__: the order in which tests will be performed. Each character represents an operation:
    * `I`: get IP
    * `D`: download test
    * `U`: upload test
    * `P`: ping + jitter test
    * `_`: delay 1 second
    * Default test order: `IP_D_U`
    * __Important:__ Tests can only be run once
    * __Important:__ On Firefox, it is better to run the upload test last
* __enable_quirks__: enables browser-specific optimizations. These optimizations override some of the default settings. They do not override settings that are explicitly set.
    * Default: `true`
* __garbagePhp_chunkSize__: size of chunks sent by garbage.php in megabytes
    * Default: `20`
    * Recommended: `>=10`
    * Maximum: `100`
* __xhr_dlMultistream__: how many streams should be opened for the download test
    * Default: `10`
    * Recommended: `>=3`
    * Default override: 3 on Edge if enable_quirks is true
    * Default override: 5 on Chromium-based if enable_quirks is true
* __xhr_ulMultistream__: how many streams should be opened for the upload test
    * Default: `3`
    * Recommended: `>=1`
    * Default override: 1 on Firefox if enable_quirks is true
* __xhr_multistreamDelay__: how long should the multiple streams be delayed (in ms)
    * Default: `300`
    * Recommended: `>=100`, `<=700`
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
* __ping_allowPerformanceApi__: toggles use of Performance API to improve accuracy of Ping/Jitter test on browsers that support it.
	* Default: `true`
* __useMebibits__: use mebibits/s instead of megabits/s for the speeds
	* Default: `false`
* __overheadCompensationFactor__: compensation for HTTP and network overhead. Default value assumes typical MTUs used over the Internet. You might want to change this if you're using this in your internal network with different MTUs, or if you're using IPv6 instead of IPv4.
    * Default: `1.06` probably a decent estimate for all overhead. This was measured empirically by comparing the measured speed and the speed reported by my the network adapter.
    * `1048576/925000`: old default value. This is probably too high.
	* `1.0513`: HTTP+TCP+IPv6+ETH, over the Internet (empirically tested, not calculated)
    * `1.0369`: Alternative value for HTTP+TCP+IPv4+ETH, over the Internet (empirically tested, not calculated)
	* `1.081`: Yet another alternative value for over the Internet (empirically tested, not calculated)
    * `1514 / 1460`: TCP+IPv4+ETH, ignoring HTTP overhead
    * `1514 / 1440`: TCP+IPv6+ETH, ignoring HTTP overhead
    * `1`: ignore overheads. This measures the speed at which you actually download and upload files rather than the raw connection speed

### Aborting the test prematurely
The test can be aborted at any time by sending an abort command to the worker:

```js
w.postMessage('abort')
```

This will terminate all network activity and stop the worker.

__Important:__ do not simply kill the worker while it's running, as it may leave pending XHR requests!

### Important notice on backwards compatibility
__Do NOT link the js file from github or fdossena.com directly into your html file.  __

A lot of web developers think that referring to the latest version of a library in their project is a good thing. It is not.  
Things may change and I don't want to break your project, so do yourself a favor, and keep all files on your server.  
You have been warned.

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
## Telemetry
Telemetry currently requires PHP and either MySQL, PostgreSQL or SQLite.
To set up the telemetry, we need to do 4 things:
* copy `telemetry.php` and `telemetry_settings.php`
* edit `telemetry_settings.php` to add your database settings
* create the database
* enable telemetry

### Creating the database
This step is only for MySQL and PostgreSQL. Skip this if you want to use SQLite.
Log into your database using phpMyAdmin or a similar software and import the appropriate sql file into an empty database. For MySQL databases use `telemetry_mysql.sql` and for PostgreSQL databases use `telemetry_postgesql.sql`.
If you see a table called `speedtest_users`, empty, you did it right.

### Configuring `telemetry.php`
Open telemetry_settings.php with notepad or a similar text editor.
Set your preferred database, ``$db_type="mysql";``, ``$db_type="sqlite";`` or ``$db_type="postgresql";``
If you choose to use Sqlite3, you must set the path to your database file:
```php
$Sqlite_db_file = "../telemetry.sql";
```

If you choose to use MySQL, you must also add your database credentials:
```php
$MySql_username="USERNAME"; //your database username
$MySql_password="PASSWORD"; //your database password
$MySql_hostname="DB_HOSTNAME"; //database address, usually localhost\
$MySql_databasename="DB_NAME"; //the name of the database where you loaded telemetry_mysql.sql
```

If you choose to use PostgreSQL, you must also add your database credentials:
```php
$PostgreSql_username="USERNAME"; //your database username
$PostgreSql_password="PASSWORD"; //your database password
$PostgreSql_hostname="DB_HOSTNAME"; //database address, usually localhost
$PostgreSql_databasename="DB_NAME"; //the name of the database where you loaded telemetry_postgresql.sql
```

### Enabling telemetry
Edit your test page; where you start the worker, you need to specify the `telemetry_level`.  
There are 3 levels:
* `none`: telemetry is disabled (default)
* `basic`: telemetry collects IP, User Agent, Preferred language, Test results
* `full`: same as above, but also collects a log (10-150 Kb each, not recommended)

Example:
```js
w.postMessage('start {"telemetry_level":"basic"}')
```

Also, see example-telemetry.html

### Seeing the results
At the moment there is no front-end to see the telemetry data; you can connect to the database and see the collected results in the `speedtest_users` table.

## Troubleshooting
These are the most common issues reported by users, and how to fix them. If you still need help, contact me at [dosse91@paranoici.org](mailto:dosse91@paranoici.org).

#### Download test gives very low result
Are garbage.php and empty.php (or your replacements) reachable?  
Press F12, select network and start the test. Do you see errors? (cancelled requests are not errors)  
If a small download starts, open it in a text editor. Does it say it's missing openssl_random_pseudo_bytes()? In this case, install OpenSSL (this is usually included when you install Apache and PHP on most distros).

#### Upload test is inaccurate, and/or I see lag spikes
Check your server's maximum POST size, make sure it's at least 20Mbytes, possibly more

#### Download and/or upload results are slightly too optimistic
The test was fine tuned to run over a typical IPv4 internet connection. If you're using it under different conditions, see the ``overheadCompensationFactor`` parameter.

#### All tests are wrong, give extremely high results, browser lags/crashes, ...
You're running the test on localhost, therefore it is trying to measure the speed of your loopback interface. The test is meant to be run over an Internet connection, from a different machine.

#### Ping test shows double the actual ping
Make sure your server is sending the ```Connection:keep-alive``` header

## Known bugs and limitations
### General
* The ping/jitter test is measured by seeing how long it takes for an empty XHR to complete. It is not an acutal ICMP ping. Different browsers may also show different results, especially on very fast connections on slow devices.
### IE-Specific
* The upload test is not precise on very fast connections with high latency (will probably be fixed by Edge 17)
* On IE11, a same origin policy error is erroneously triggered under unknown conditions. Seems to be related to running the test from unusual URLs like a top level domain (for instance http://abc/speedtest). These are bugs in IE11's implementation of the same origin policy, not in the speedtest itself.
* On IE11, under unknown circumstances, on some systems the test can only be run once, after which speedtest_worker.js will not be loaded by IE until the browser is restarted. This is a rare bug in IE11.
### Firefox-Specific
* On some Linux systems with hardware acceleration turned off, the page rendering makes the browser lag, reducing the accuracy of the ping/jitter test

## Making changes
Since this is an open source project, you can modify it.

To make changes to the speedtest itself, edit `speedtest_worker.js`

To create the minified version, use UglifyJS like this:

```
uglifyjs -c speedtest_worker.js > speedtest_worker.min.js
```

Pull requests are much appreciated. If you don't use github (or git), simply contact me at [dosse91@paranoici.org](mailto:dosse91@paranoici.org).

__Important:__ please add your name to modified versions to distinguish them from the main project.


## License
This software is under the GNU LGPL license, Version 3 or newer.

To put it short: you are free to use, study, modify, and redistribute this software and modified versions of it, for free or for money.
You can also use it in proprietary software but all changes to this software must remain under the same GNU LGPL license.

Contact me at [dosse91@paranoici.org](mailto:dosse91@paranoici.org) for other licensing models.
