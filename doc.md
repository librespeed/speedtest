# LibreSpeed

> by Federico Dossena
> Version 5.4.1
> [https://github.com/librespeed/speedtest/](https://github.com/librespeed/speedtest/)

## Introduction

LibreSpeed is a Free and Open Source speed test that you can host on your server(s), and users can run in their browser.

__Features:__

* Download test
* Upload test
* Ping and Jitter test
* IP address, ISP and distance detection
* Telemetry (optional)
* Results sharing (optional)
* Multiple Points of Test (optional)

__Browser support:__
The test supports any browser that supports XHR Level 2 and Web Workers. JavaScript must be enabled.

The following browsers are officially supported:

* Internet Explorer 11
* Microsoft Edge (last 2 versions)
* Mozilla Firefox (latest ESR and last 2 versions)
* Google Chrome and other Chromium-based browsers (last 2 versions)
* Apple Safari (last 2 versions)
* Opera (last 2 versions)

Client side, the test can use up to 500MB of RAM on very fast connections.

## Quick start guides

This video shows the installation process of a standalone LibreSpeed server: [Quick start installation guide for Debian 12](https://fdossena.com/?p=speedtest/quickstart_deb12.frag)

More videos will be added later.

## Installation

### Single server, PHP

Server side, you'll need:

* Apache 2 (nginx and IIS also supported). A fast internet connection is required (possibly gigabit), and the web server must accept large POST requests (up to 20MB)
* PHP 5.4 or newer (8.0 required for ISP and distance detection), a 64-bit version is strongly recommended
* OpenSSL and its PHP module (this is usually installed automatically by most distros)
* If you want to store test results (telemetry), one of the following:
  * MySQL/MariaDB and its PHP PDO module
  * PostgreSQL and its PHP PDO module
  * SQLite 3 and its PHP PDO module
  * MSSQL and its PHP PDO module (Windows only)
* If you want to enable results sharing (these are usually installed automatically by most distros when you install PHP):
  * FreeType 2 and its PHP module
  * The PHP gd library

Let's install the speed test.

Put all files on your web server via FTP or by copying them directly. You can install it in the root, or in a subdirectory.

__Important:__ The speed test needs write permissions in the installation folder!

#### ipinfo.io

The speed test uses the [ipinfo.io](https://ipinfo.io) offline database to detect ISP and country (CC-BY-SA 4.0 license), enabled by default.
It's possible to use the full [ipinfo.io](https://ipinfo.io) API to detect distance from server as well. This is completely optional and can be enabled by obtaining an [access token](https://ipinfo.io) and putting it in `backend/getIP_ipInfo_apikey.php`. When this feature is enabled, the offline database will only be used as fallback.

#### Telemetry and results sharing

The test supports storing test results and can generate shareable images that users can embed in forum signatures and such.

To use this function, you will need a database. The test supports MySQL, PostgreSQL and SQLite as backends.

##### Creating the database

This step is only required for MySQL, PostgreSQL and MSSQL. If you want to use SQLite, skip to the next step.

Log into your database using phpMyAdmin or a similar software and create a new database. Inside the `results` folder you will find `telemetry_mysql.sql`, `telemetry_postgresql.sql` and `telemetry_mssql.sql`, which are templates for MySQL, PostgreSQL and MSSQL respectively. Import the one you need, and you will see a `speedtest_users` table in the database. You can delete the templates afterwards.

##### Configuring telemetry

Open `results/telemetry_settings.php` in a text editor. Set `$db_type` to either `mysql`,`postgresql`, `mssql` or `sqlite`.

If you chose to use SQLite, you might want to change `$Sqlite_db_file` to another path where you want the database to be stored. Just make sure that the file cannot be downloaded by users. Sqlite doesn't require any additional configuration, you can skip the rest of this section.

If you chose to use MySQL, you must set your database credentials:

```php
$MySql_username="USERNAME"; //your database username
$MySql_password="PASSWORD"; //your database password
$MySql_hostname="DB_HOSTNAME"; //database address, usually localhost
$MySql_databasename="DB_NAME"; //the name of the database where you loaded telemetry_mysql.sql
```

If you chose to use PostgreSQL, you must set your database credentials:

```php
$PostgreSql_username="USERNAME"; //your database username
$PostgreSql_password="PASSWORD"; //your database password
$PostgreSql_hostname="DB_HOSTNAME"; //database address, usually localhost
$PostgreSql_databasename="DB_NAME"; //the name of the database where you loaded telemetry_postgresql.sql
```

Ifyou chose to use MSSQL, you must set your database credentials:

```php
$MsSql_server = 'DB_HOSTNAME';
$MsSql_databasename = 'DB_NAME';
$MsSql_WindowsAuthentication = true;   //true or false
$MsSql_username = 'USERNAME';          //not used if MsSql_WindowsAuthentication is true
$MsSql_password = 'PASSWORD';          //not used if MsSql_WindowsAuthentication is true
$MsSql_TrustServerCertificate = true;  //true, false or comment out for driver default
```

##### Results sharing

This feature generates an image that can be share by the user containing the download, upload, ping, jitter and ISP (if enabled).

By default, the telemetry generates a progressive ID for each test. Even if no sensitive information is leaked, you might not want users to be able to guess other test IDs. To avoid this, you can turn on ID obfuscation, which turns IDs into a reversible hash, much like YouTube video IDs.

To enable ID obfuscation, edit `results/telemetry_settings.php` and set `$enable_id_obfuscation` to `true`. From now on, all test IDs will be obfuscated using a unique salt. The IDs in the database are still progressive, but users will only know their obfuscated versions and won't be able to easily guess other IDs.

__Important:__ ID obfuscation currently only works on 64-bit PHP!

While you're editing `results/telemetry_settings.php`, you might want to set `$redact_ip_addresses` to `true`, this way, all IP addresses will be removed from the telemetry for better privacy. This is disabled by default.

##### Seeing the results

A basic front-end for visualizing and searching tests by ID is available in `results/stats.php`.

A login is required to access the interface. __Important__: change the default password in `results/telemetry_settings.php`.

#### The end

Now that the test is installed, the default page uses telemetry and results sharing. If you want another UI, you can easily customize `index.html` or one of the examples in the `examples` folder (the best starting point for most people is `example-singleServer-gauges.html`).

If you're not using telemetry and results sharing, you can delete the `results` folder too.

Details about the examples and how to make custom UIs will be discussed later.

#### Privacy

Telemetry contains personal information (according to GDPR definition), therefore it is important to treat this data respectfully of national and international laws, especially if you plan to offer the service in the European Union.

Default `index.html` and `example-multipleServers-full.html` both contain a privacy policy for the service: you MUST read it, change it if necessary, and add your email address for data deletion requests. __Failure to comply with GDPR regulations can get you in serious trouble.__

### Multiple servers, PHP

The speed test can automatically choose between multiple test points and use the one with the lowest ping in a list.

Note that this is an advanced use case and it is recommended that you already know how to use the speed test with a single server.

We must distinguish 2 types of servers:

* __Frontend server__: hosts the UI, the JS files, and optionally telemetry and results sharing stuff. You only need 1 of these, and this is the server that your clients will first connect to.
* __Test backends__: the servers used to actually perform the test. There can be 1+ of these, and they only host the backend files.

#### Frontend server

This is the server that your users will first connect to. It hosts the UI, the JS files, and optionally telemetry and results sharing stuff.

Requirements:

* Apache 2 (nginx and IIS also supported). A fast connection is not mandatory, but is still recommended
* PHP 5.4 or newer, a 64-bit version is strongly recommended
* If you want to store test results (telemetry), one of the following:
  * MySQL/MariaDB and its PHP PDO module
  * PostgreSQL and its PHP PDO module
  * SQLite 3 and its PHP PDO module
  * MSSQL and its PHP PDO module (Windows only)
* If you want to enable results sharing (these are usually installed automatically by most distros when you install PHP):
  * FreeType 2 and its PHP module
  * The PHP gd library

To install the speed test frontend, copy the following files to your web server:

* `speedtest.js`
* `speedtest_worker.js`
* Optionally, the `results` folder
* `index.html` (or one of the example UIs in the `examples` folder)

__Important:__ The speed test needs write permissions in the installation folder!

##### Server list

Edit `index.html` and uncomment the list of servers:

```js
var SPEEDTEST_SERVERS=[
 /*{
  name:"Example Server 1", //user friendly name for the server
  server:"//test1.mydomain.com/", //URL to the server. // at the beginning will be replaced with http:// or https:// automatically
  dlURL:"backend/garbage.php",  //path to download test on this server (garbage.php or replacement)
  ulURL:"backend/empty.php",  //path to upload test on this server (empty.php or replacement)
  pingURL:"backend/empty.php",  //path to ping/jitter test on this server (empty.php or replacement)
  getIpURL:"backend/getIP.php"  //path to getIP on this server (getIP.php or replacement)
 },
 {
  name:"Example Server 2", //user friendly name for the server
  server:"//test2.example.com/", //URL to the server. // at the beginning will be replaced with http:// or https:// automatically
  dlURL:"garbage.php",  //path to download test on this server (garbage.php or replacement)
  ulURL:"empty.php",  //path to upload test on this server (empty.php or replacement)
  pingURL:"empty.php",  //path to ping/jitter test on this server (empty.php or replacement)
  getIpURL:"getIP.php"  //path to getIP on this server (getIP.php or replacement)
 }*/
 //add other servers here, comma separated
];
```

Replace the demo servers with your test points. Each server in the list is an object containing:

* `"name"`: user friendly name for this test point
* `"server"`: URL to the server. If your server only supports HTTP or HTTPS, put http:// or https:// at the beginning, respectively; if it supports both, put // at the beginning and it will be replaced automatically
* `"dlURL"`: path to the download test on this server (garbage.php or replacement)
* `"ulURL"`: path to the upload test on this server (empty.php or replacement)
* `"pingURL"`: path to the ping test on this server (empty.php or replacement)
* `"getIpURL"`: path to getIP on this server (getIP.php or replacement)

None of these parameters can be omitted.

__Important__: You can't mix HTTP with HTTPS; if the frontend uses HTTP, you won't be able to connect to HTTPS backends, and viceversa.

__Important__: For HTTPS, all your servers must have valid certificates or the browser will refuse to connect.

If your list of servers changes often, you might not want to have it hardcoded in the HTML file. LibreSpeed can load the server list from a JSON file. To do this, remove the server list and replace it with the URL to your server list, like this:

```js
var SPEEDTEST_SERVERS="your URL here";
```

The URL doesn't need to be complete, it can just point to a file in the current directory. The URL should point to a JSON file with the same format used above:

```js
[
    {
        "name":...
    },
    ...
]
```

__Important:__ The same origin policy applies to which URLs you can and cannot load with this method. If possible, it's best to just point it to a file on the current server.

##### Telemetry and results sharing in multiple server scenarios

Telemetry is stored on the frontend server. The setup procedure is the same as the single server version.

#### Test backends

These are the servers that will actually be used to perform the test.

Requirements:

* Apache 2 (nginx and IIS also supported). A fast internet connection is required (possibly gigabit), and the web server must accept large POST requests (up to 20MB)
* PHP 5.4 or newer (8.0 required for ISP and distance detection)
* OpenSSL and its PHP module (this is usually installed automatically by most distros)

To install a backend, simply copy all the files in the `backend` folder to your backend server.

__Important:__ The speed test needs write permissions in the installation folder!

#### ipinfo.io, see single server

see [above](#ipinfoio)

## Making a custom front-end

This section explains how to use speedtest.js in your webpages.

The best way to learn is by looking at the provided examples.

__Single server:__

* `example-singleServer-basic.html`: The most basic configuration possible. Runs the test with the default settings when the page is loaded and displays the results with no fancy graphics.
* `example-singleServer-pretty.html`: A more sophisticated example with a nicer layout and a start/stop button. __This is the best starting point for most users__
* `example-singleServer-progressBar.html`: Same as `example-singleServer-pretty.html` but adds a progress indicator
* `example-singleServer-customSettings.html`: Same as `example-singleServer-pretty.html` but configures the test so that it only performs download and upload tests, and with a fixed length instead of automatic
* `example-singleServer-gauges.html`: The most sophisticated example, with the same functionality as `example-singleServer-pretty.html` but adds gauges. This is also a good starting point, but the gauges may slow down underpowered devices
* `example-singleServer-chart.html`: Shows how to use the test with the Chart.js library
* default `index.html`: A full UI that supports both single server and multiple servers installations and even has a dark theme

__Multiple servers:__

* `example-multipleServers-pretty.html`: Same as `example-singleServer-pretty.html` but with multiple test points. Server selection is fully automatic
* `example-multipleServers-full.html`: Same as default `index.html` but with multiple test points. Server selection is automatic but the server can be changed afterwards by the user

### Initialization

To use the speed test in your page, first you need to load it:

```xml
<script type="text/javascript" src="speedtest.js"></script>
```

After loading, you can initialize the test:

```js
var s=new Speedtest();
```

### Event handlers

Now, you can set up event handlers to update your UI:

```js
s.onupdate=function(data){
    //update your UI here
}
s.onend=function(aborted){
    //end of the test
    if(aborted){
        //something to do if the test was aborted instead of ending normally
    }
}
```

The `onupdate` event handler will be called periodically by the test with data coming from the speed test worker thread. The `data` argument is an object containing the following:

* __testState__: an integer between -1 and 5
  * `-1` = Test not started yet
  * `0` = Test starting
  * `1` = Download test in progress
  * `2` = Ping + Jitter test in progress
  * `3` = Upload test in progress
  * `4` = Test finished
  * `5` = Test aborted
* __dlStatus__: either
  * Empty string (not started or aborted)
  * Download speed in Megabit/s as a number with 2 decimals
  * The string "Fail" (test failed)
* __ulStatus__: either
  * Empty string (not started or aborted)
  * Upload speed in Megabit/s as a number with 2 decimals
  * The string "Fail" (test failed)
* __pingStatus__: either
  * Empty string (not started or aborted)
  * Estimated ping in milliseconds as a number with 2 decimals
  * The string "Fail" (test failed)
* __clientIp__: either
  * Empty string (not fetched yet or failed)
  * The client's IP address as a string (with ISP info if enabled)
* __jitterStatus__: either
  * Empty string (not started or aborted)
  * Estimated jitter in milliseconds as a number with 2 decimals (lower = stable connection)
  * The string "Fail" (test failed)
* __dlProgress__: the progress of the download test as a number between 0 and 1
* __ulProgress__: the progress of the upload test as a number between 0 and 1
* __pingProgress__: the progress of the ping+jitter test as a number between 0 and 1
* __testId__: when telemetry is active, this is the ID of the test in the database. This is null until the test is finished, or if telemetry encounters an error. This ID is used for results sharing

The `onend` event handler will be called at the end of the test (`onupdate` will be called first), with a boolean telling you if the test was aborted (either manually or because of an error) or if it ended normally.

### Test parameters

Before starting the test, you can change some of the settings from their default values. You might want to do this to better adapt the speed test to a specific scenario, such as a satellite connection. To change a setting, use

```js
s.setParameter("parameter_name",value);
```

For instance, to enable telemetry we can use:

```js
s.setParameter("telemetry_level","basic");
```

And now the test results will be stored and we will get our test ID at the end of the test (along with the other data)

__Main parameters:__

* __time_dl_max__: Maximum duration of the download test in seconds. If auto duration is disabled, this is used as the duration of the test.
  * Default: `15`
  * Recommended: `>=5`
* __time_ul_max__: Maximum duration of the upload test in seconds. If auto duration is disabled, this is used as the duration of the test.
  * Default: `15`
  * Recommended: `>=10`
* __time_auto__: Automatically determine the duration of the download and upload tests, making them faster on faster connections, to avoid wasting data.
  * Default: `true`
* __count_ping__: How many pings to perform in the ping test
  * Default: `10`
  * Recommended: `>=3, <30`
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
* __url_telemetry__: path to telemetry.php or replacement
  * Default: `results/telemetry.php`
  * __Important:__ path is relative to js file
  * __Note:__ you can ignore this parameter if you're not using the telemetry
* __telemetry_level__: The type of telemetry to use. See the telemetry section for more info about this
  * Default: `none`
  * `basic`: send results only
  * `full`: send results and timing information, even for aborted tests
  * `debug`: same as full but also sends debug information. Not recommended.
* __test_order__: the order in which tests will be performed. You can use this to change the order of the test, or to only enable specific tests. Each character represents an operation:
  * `I`: get IP
  * `D`: download test
  * `U`: upload test
  * `P`: ping + jitter test
  * `_`: delay 1 second
  * Default test order: `IP_D_U`
  * __Important:__ Tests can only be run once
  * __Important:__ On Firefox, it is better to run the upload test last
* __getIp_ispInfo__: if true, the server will try to get ISP info and pass it along with the IP address. This will add `isp=true` to the request to `url_getIp`. getIP.php accomplishes this using ipinfo.io
  * Default: `true`
* __getIp_ispInfo_distance__: if true, the server will try to get an estimate of the distance from the client to the speed test server. This will add a `distance` argument to the request to `url_getIp`. `__getIp_ispInfo__` must be enabled in order for this to work. getIP.php accomplishes this using ipinfo.io (API key required)
  * `km`: estimate distance in kilometers
  * `mi`: estimate distance in miles
  * not set: do not measure distance
  * Default: `km`

__Advanced parameters:__ (Seriously, don't change these unless you know what you're doing)

* __telemetry_extra__: Extra data that you want to be passed to the telemetry. This is a string field, if you want to pass an object, make sure you use ``JSON.stringify``. This string will be added to the database entry for this test.
* __enable_quirks__: enables browser-specific optimizations. These optimizations override some of the default settings. They do not override settings that are explicitly set.
  * Default: `true`
* __garbagePhp_chunkSize__: size of chunks sent by garbage.php in megabytes
  * Default: `100`
  * Recommended: `>=10`
  * Maximum: `1024`
* __xhr_dlMultistream__: how many streams should be opened for the download test
  * Default: `6`
  * Recommended: `>=3`
  * Default override: 3 on Edge if enable_quirks is true
  * Default override: 5 on Chromium-based if enable_quirks is true
* __xhr_ulMultistream__: how many streams should be opened for the upload test
  * Default: `3`
  * Recommended: `>=1`
* __xhr_ul_blob_megabytes__: size in megabytes of the blobs sent during the upload test
  * Default: `20`
  * Default override: 4 on Chromium-based mobile browsers (limitation introduced around version 65). This will be forced
  * Default override: IE11 and Edge currently use a different method for the upload test. This parameter is ignored
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
  * Default override: `false` on Firefox because its performance API implementation is inaccurate
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

### Multiple Points of Test

If you want to use more than one test server, this is the time to add all your test points and select the best one. Skip this part if you don't want to use this feature.

The best way to do this is to declare an array with all your servers, and give it to the speed test:

```js
var SPEEDTEST_SERVERS=[
 server1,
 server2,
 ...
];
s.addTestPoints(SPEEDTEST_SERVERS);
```

Each server in the list is an object containing:

* `name`: user friendly name for this test point
* `server`: URL to the server. If your server only supports HTTP or HTTPS, put `http://` or `https://` at the beginning, respectively; if it supports both, put `//` at the beginning and it will be replaced automatically
* `dlURL`: path to the download test on this server (garbage.php or replacement)
* `ulURL`: path to the upload test on this server (empty.php or replacement)
* `pingURL`: path to the ping test on this server (empty.php or replacement)
* `getIpURL`: path to getIP on this server (getIP.php or replacement)

None of these parameters can be omitted.

Example:

```js
{
 name:"Milano, IT",
 server:"http://backend1.myspeedtest.net/",
 dlURL:"garbage.php",
 ulURL:"empty.php",
 pingURL:"empty.php",
 getIpURL:"getIP.php"
}
```

Now, we can run the server selector:

```js
s.selectServer(function(server){
    //do something
})
```

The `selectServer` function is asynchronous in order to avoid freeing the UI, and it will run a callback function when it is done choosing the server with the lowest ping.
The `server` argument is the selected server, and you can display it in the UI if you want. __You cannot start the test until the selection is done!__

You can also set the test point manually (for instance, from a combobox in the UI):

```js
s.setSelectedServer(server)
```

where `server` is the server that you want to use.

### Running the test

Finally, we can run the test:

```js
s.start();
```

During the test, your `onupdate` event handler will be called periodically with data that you can use to update your UI. Your `onend` handler will be called at the end of the test.

You can abort the test at any time:

```js
s.abort();
```

When the test is finished, you can run it again if you want, or you can just destroy `s`.

## Implementation details

The purpose of this section is to help developers who want to make changes to the inner workings of the speed test.
It will be divided into 4 sections: `speedtest.js`, `speedtest_worker.js`, the `backend` files and the `results` files.

### `speedtest.js`

This is the main interface between your webpage and the speed test.
It hides the speed test web worker to the page, and provides many convenient functions to control the test.

You can think of this as a finite state machine. These are the states (use getState() to see them):

* __0__: here you can change the speed test settings (such as test duration) with the `setParameter("parameter",value)` function. From here you can either start the test using `start()` (goes to state 3) or you can add multiple test points using `addTestPoint(server)` or `addTestPoints(serverList)` (goes to state 1). Additionally, this is the perfect moment to set up callbacks for the `onupdate(data)` and `onend(aborted)` events.
* __1__: here you can add test points. You only need to do this if you want to use multiple test points.
    A server is defined as an object like this:

    ```jsonc
    {
        name: "User friendly name",
        server:"http://yourBackend.com/",   //  <---- URL to your server. You can specify http:// or https://. If your server supports both, just write // without the protocol
        dlURL:"garbage.php" //   <----- path to garbage.php or its replacement on the server
        ulURL:"empty.php"  //  <----- path to empty.php or its replacement on the server
        pingURL:"empty.php"  //  <----- path to empty.php or its replacement on the server. This is used to ping the server by this selector
        getIpURL:"getIP.php"  //  <----- path to getIP.php or its replacement on the server
    }
    ```

    While in state 1, you can only add test points, you cannot change the test settings. When you're done, use selectServer(callback) to select the test point with the lowest ping. This is asynchronous, when it's done, it will call your callback function and move to state 2. Calling setSelectedServer(server) will manually select a server and move to state 2.
* __2__: test point selected, ready to start the test. Use `start()` to begin, this will move to state 3
* __3__: test running. Here, your `onupdate` event callback will be called periodically, with data coming from the worker about speed and progress. A data object will be passed to your `onupdate` function, with the following items:
        - `dlStatus`: download speed in Mbit/s
        - `ulStatus`: upload speed in Mbit/s
        - `pingStatus`: ping in ms
        - `jitterStatus`: jitter in ms
        - `dlProgress`: progress of the download test as a float 0-1
        - `ulProgress`: progress of the upload test as a float 0-1
        - `pingProgress`: progress of the ping/jitter test as a float 0-1
        - `testState`: state of the test (-1=not started, 0=starting, 1=download test, 2=ping+jitter test, 3=upload test, 4=finished, 5=aborted)
        - `clientIp`: IP address of the client performing the test (and optionally ISP and distance)
    At the end of the test, the `onend` function will be called, with a boolean specifying whether the test was aborted or if it ended normally.
    The test can be aborted at any time with `abort()`.
    At the end of the test, it will move to state 4
* __4__: test finished. You can run it again by calling `start()` if you want.

#### List of functions in the Speedtest class

##### getState()

Returns the state of the test: 0=adding settings, 1=adding servers, 2=server selection done, 3=test running, 4=done

##### setParameter(parameter,value)

Change one of the test settings from their defaults.

* parameter: string with the name of the parameter that you want to set
* value: new value for the parameter

Invalid values or nonexistant parameters will be ignored by the speed test worker.

##### addTestPoint(server)

Add a test point (multiple points of test)

* server: the server to be added as an object. Must contain the following elements:

    ```jsonc
    {
        name: "User friendly name",
        server:"http://yourBackend.com/",   // URL to your server. You can specify http:// or https://. If your server supports both, just write // without the protocol
        dlURL:"garbage.php", //  path to garbage.php or its replacement on the server
        ulURL:"empty.php", //   path to empty.php or its replacement on the server
        pingURL:"empty.php", //   path to empty.php or its replacement on the server. This is used to ping the server by this selector
        getIpURL:"getIP.php", //   path to getIP.php or its replacement on the server
    }
    ```

Note that this will add `mpot`:`true` to the parameters sent to the speed test worker.

##### addTestPoints(list)

Same as addTestPoint, but you can pass an array of servers

##### loadServerList(url,result)

Loads a list of servers from a JSON file pointed by the `url`.

The process is asynchronous and the `result` function will be called when it's done. If the request succeeded, an array containing the list of loaded servers will be passed to the function, otherwise `null` will be passed.

##### getSelectedServer()

Returns the selected server (multiple points of test)

##### setSelectedServer()

Manually selects one of the test points (multiple points of test)

##### selectServer(result)

Automatically selects a server from the list of added test points. The server with the lowest ping will be chosen. (multiple points of test)

The selector checks multiple servers in parallel (default: 6 streams) to speed things up if the list of servers is long.

The process is asynchronous and the passed `result` callback function will be called when it's done, then the test can be started.

##### start()

Starts the test.

Note (multiple points of test): the selected server will be added to the `telemetry_extra` string. If this string was already set, then `telemetry_extra` will be a JSON string containing both the server and the original string

During the test, the `onupdate(data)` callback function will be called periodically with data from the worker.
At the end of the test, the `onend(aborted)` function will be called with a boolean telling you if the test was aborted or if it ended normally.

##### abort()

Aborts the test while it's running.

### `speedtest_worker.js`

This is where the actual speed test code is. It receives the settings from the main thread, runs the test, and reports back the results.

The worker accepts 3 commands:

* `start`: starts the test. Optionally, test settings can be passed as a JSON string after the word start and a space
* `status`: returns the current status as a JSON string. The status string contents are the ones described in the Event handlers section in the section about making a custom front-end.
* `abort`: aborts the test

#### Parameters

In addition to the parameters listed in the Test settings section in the section about making a custom front-end, there is one additional setting:

* `mpot`: set this to true to run the test with multiple points of test. This will add `cors=true` to all requests (all responses will contain CORS headers) and enable some extra quirks.
    Default: `false`

#### Download test

The download test is performed by transferring large blobs of garbage data using XHR from the server to the client.

The test uses multiple streams. If a stream finishes the download, it is restarted. The amount of downloaded data for each stream is tracked using the XHR Level 2 `onprogress` event.

The test streams are not perfectly synchronized because we don't want them to finish all at the same time if they do.

Every 200ms, a timer updates the `dlStatus` string with the current speed and calculates a "bonus" time by which to shorten the test depending on how high the speed is, (when `time_auto` is set to `true`).

See the code for more implementation details.

#### Upload test

This works similarly to the download test, but in reverse. A large blob of garbage data is generated and it is sent to the server repeatedly using multiple streams.

To keep track of the amount of transferred data, the XHR Level 2 `upload.onprogress` event is used.

This test has a couple of complications:

* Some browsers don't have a working `upload.onprogress` event. For this, we use a small blobs instead of a large one and we keep track of progress using the `onload` event. This is referred to as IE11 Workaround (but the same bug was also found in some versions of Edge and Safari)
* When `mpot` is set to `true`, an empty request must first be sent in order to load the CORS headers before the test can start

See the code for more implementation details.

#### Ping + Jitter test

The Ping/Jitter test __is NOT an ICMP ping__. This is a common misconception. You cannot use ICMP over HTTP, and certainly not in a browser.

This test works by creating a persistent HTTP connection to the server, and then repeatedly downloading an empty file, and measuring how long it takes between the request and the response.

Timing can be measured as a simple timestamp difference or with the Performance API if available.

Jitter is the variance in ping times.

See the code for more implementation details.

### `backend` files

#### `garbage.php`

Uses OpenSSL to generate a stream of incompressible garbage data for the download test.

If accepts a `ckSize` GET parameter, which specifies how much garbage data to generate in megabytes (4-1024).

#### `empty.php`

An empty file used for the upload and ping test. It only sends headers to create the connection.

#### `getIP.php`

Returns client IP, ISP and distance from the server.

GET parameters:

* `isp`: if set, fetches ISP info from ipinfo.io
* `distance`: if set, calculates distance from server. You can specify `km` or `mi` for the format.

If `isp` is set, the output is a JSON string containing:

* `processedString`: string that can be displayed to the user
* `rawIspInfo`: info about the client as a JSON string, straight from ipinfo.io

If `isp` is not set, the output is just a string containing the client's IP address.

Note: if your server is behind some proxy, firewall, VPN, etc., the client's IP address may not be detected properly. If this happens, you must analyze the traffic coming from the client to find the name of the HTTP header that contains the original IP address. `getIP.php` contains some of these headers but not all of them.

#### CORS headers

All these files will send the following CORS headers if the GET parameter `cors=true` is passed to them:

```header
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST
Access-Control-Allow-Headers: Content-Encoding, Content-Type
```

### `results` files

#### `telemetry.php`

This file stores telemetry information into the database.

Data is passed as POST parameters:

* `ispinfo`: ISP info (if enabled, empty string otherwise)
* `extra`: the `telemetry_extra` string passed to the worker (if set, empty string otherwise)
* `dl`: download speed
* `ul`: upload speed
* `ping`: ping time
* `jitter`: jitter value
* `log`: telemetry log (if `telemetry_level` is set to `full` or higher, empty string otherwise)

#### `index.php`

Generates a shareable results image for a given test ID.

GET parameters:

* `id`: ID of the test you want to display

The looks of this image can be customized by editing the variables in this file.

#### `idObfuscation.php`

Contains the implementation of ID obfuscation and deobfuscation.

See the code for the implementation details, it's basically a bunch of bitwise operations.

#### `stats.php`

Simple UI to display and search test results. Not required to run the test.

## Alternative backends

If for some reason you can't or don't want to use PHP, the speed test can run with other backends, or even no backend (with limited functionality).

You will need replacements for `backend/garbage.php` and `backend/empty.php` and optionally `backend/getIP.php`, and the test needs to know where to find them:

```js
//Speed test initialization
var s=new Speedtest();
...
//Custom backend
s.setParameter("url_dl","URL to your garbage.php replacement");
s.setParameter("url_ul","URL to your empty.php replacement");
s.setParameter("url_ping","URL to your empty.php replacement");
s.setParameter("url_getIp","URL to your getIP.php replacement");
```

### Replacement for `garbage.php`

A replacement for `garbage.php` must generate incompressible garbage data.

A large file (10-100 Mbytes) is a possible replacement. You can get one [here](http://downloads.fdossena.com/geth.php?r=speedtest-bigfile).

A symlink to `/dev/urandom` is also ok.

If you want to make your own backend, see the section on the implementation details of `garbage.php`.

#### Replacement for `empty.php`

Your replacement must simply respond with a HTTP code 200 and send nothing else. You may want to send additional headers to disable caching. The test assumes that `Connection:keep-alive` is sent by the server.

An empty file can be used for this.

If you want to make your own backend, see the section on the implementation details of `empty.php`.

#### Replacement for `getIP.php`

Your replacement can simply respond with the client's IP as plaintext or do something more fancy.

If you want to make your own backend, see the section on the implementation details of `getIP.php`.

### No backend

The speed test can run, albeit with limited functionality, using only a web server as backend, with no PHP or other server-side scripting.

You will be able to run the download and upload test, but no IP, ISP and distance detection, no telemetry and results sharing, and only a single point of test.

To do this, you will need:

* A replacement for `garbage.php`: a large incompressible file, like [this](http://downloads.fdossena.com/geth.php?r=speedtest-bigfile). We'll call this `backend/garbage.dat`
* A replacement for `empty.php`: an empty file will do. We'll call this `backend/empty.dat`

Now you need to configure the test to use them. Look for `s=new Speedtest()` and right below it, put the following:

```js
s.setParameter("url_dl","backend/garbage.dat");
s.setParameter("url_ul","backend/empty.dat");
s.setParameter("url_ping","backend/empty.dat");
s.setParameter("test_order","P_D_U");
```

This will point to our static files and set the test to only do ping/jitter, download and upload tests.

## Troubleshooting

These are the most common issues reported by users, and how to fix them. If you still need help, contact me at [info@fdossena.com](mailto:info@fdossena.com).

### Download test gives very low result

Are garbage.php and empty.php (or your replacements) reachable?
Press F12, select network and start the test. Do you see errors? (cancelled requests are not errors)
If a small download starts, open it in a text editor. Does it say it's missing openssl_random_pseudo_bytes()? In this case, install OpenSSL (this is usually included when you install Apache and PHP on most distros).

#### Upload test is inaccurate, and/or I see lag spikes

Check your server's maximum POST size, make sure it's at least 20Mbytes, possibly more

#### Download and/or upload results are slightly too optimistic

The test was fine tuned to run over a typical IPv4 internet connection. If you're using it under different conditions, see the `overheadCompensationFactor` parameter.

#### All tests are wrong, give extremely high results, browser lags/crashes

You're running the test on localhost, therefore it is trying to measure the speed of your loopback interface. The test is meant to be run over an Internet connection, from a different machine.

#### Ping test shows double the actual ping

Make sure your server is sending the `Connection:keep-alive` header

#### The server is behind a load balancer, proxy, etc. and I get the wrong IP address

Edit getIP.php and replace lines 14-23 with what is more appropriate in your scenario.
Example: `$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];`

#### The results sharing just generates a blank image

If the image doesn't display and the browser displays a broken image icon, FreeType2 is not installed or configured properly.
If the image is blank, this usually happens because PHP can't find the font files inside the `results` folder. You can fix your PHP config or edit `results/index.php` and use absolute paths for the fonts. This is a [known issue with PHP](http://php.net/manual/en/function.imagefttext.php) and no real solution is known.

#### My server is behind Cloudflare and I can't reach full speed on some of the tests

This is not a speed test related issue, as it can be replicated in virtually any HTTP file upload/download.
Go to your domain's DNS settings and change "DNS and HTTP proxy (CDN)" to "DNS only", and wait for the settings to be applied (can take a few minutes).

#### On Windows Server, using IIS, the upload test doesn't work, CORS errors are visible in the console

This is a configuration issue. Make a file called web.config in wwwroot and adapt the following code:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <cors enabled="true" failUnlistedOrigins="false">
      <add origin="*">
        <allowHeaders allowAllRequestedHeaders="true" />
        <allowMethods>
          <add method="GET" />
          <add method="POST" />
          <add method="PUT" />
          <add method="DELETE" />
          <add method="OPTIONS" />
        </allowMethods>
        <exposeHeaders>
        </exposeHeaders>
      </add>
    </cors>
  </system.webServer>
</configuration>
```

#### ID obfuscation doesn't work (incorrect output, blank results image)

ID obfuscation only works on 64-bit PHP (requires PHP_INT_SIZE to be 8).
Note that older versions of PHP 5 on Windows use PHP_INT_SIZE of 4, even if they're 64 bit. If you're in this situation, update your PHP install.

Also, make sure that the web server has write permission on the `results` folder.

## Known bugs and limitations

### General

* The ping/jitter test is measured by seeing how long it takes for an empty XHR to complete. It is not an actual ICMP ping. Different browsers may also show different results, especially on very fast connections on slow devices.

### IE specific

* The upload test is not precise on very fast connections with high latency (will probably be fixed by Edge 17)
* On IE11, a same origin policy error is erroneously triggered under unknown conditions. Seems to be related to running the test from unusual URLs like a top level domain (for instance <http://abc/speedtest>). These are bugs in IE11's implementation of the same origin policy, not in the speed test itself.
* On IE11, under unknown circumstances, on some systems the test can only be run once, after which speedtest_worker.js will not be loaded by IE until the browser is restarted. This is a rare bug in IE11.

### Firefox specific

* On some Linux systems with hardware acceleration turned off, the page rendering makes the browser lag, reducing the accuracy of the ping/jitter test, and potentially even the download and upload tests on very fast connections.

## Contributing

Since this is an open source project, you can modify it.

If you made some changes that you think should make it into the main project, send a Pull Request on GitHub, or contact me at [info@fdossena.com](mailto:info@fdossena.com).
We don't require you to use a specific coding convention, write the code however you want and we'll change the formatting if necessary.

Donations are also appreciated: you can donate with [PayPal](https://www.paypal.me/sineisochronic) or [Liberapay](https://liberapay.com/fdossena/donate).

## License

This software is under the GNU LGPL license, Version 3 or newer.

To put it short: you are free to use, study, modify, and redistribute this software and modified versions of it, for free or for money.
You can also use it in proprietary software but all changes to this software must remain under the same GNU LGPL license.

Contact me at [info@fdossena.com](mailto:info@fdossena.com) for other licensing models.
