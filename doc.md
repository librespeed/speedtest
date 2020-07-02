# LibreSpeed - Docker Documentation

## Downloading from Docker hub
To download LibreSpeed from the docker hub, use this command:

```
docker pull adolfintel/speedtest
```

You will now have a new docker image called `adolfintel/speedtest`.

## Standalone mode
If you want to install LibreSpeed on a single server, you need to configure it in standalone mode. To do this, set the `MODE` environment variable to `standalone`.

The test can be accessed on port 80.

Here's a list of additional environment variables available in this mode:
* __`TITLE`__: Title of your speedtest. Default value: `LibreSpeed`
* __`TELEMETRY`__: Whether to enable telemetry or not. Default value: `false`
* __`ENABLE_ID_OBFUSCATION`__: When set to true with telemetry enabled, test IDs are obfuscated, to avoid exposing the database internal sequential IDs. Default value: `false`
* __`REDACT_IP_ADDRESSES`__: When set to true with telemetry enabled, IP addresses and hostnames are redacted from the collected telemetry, for better privacy. Default value: `false`
* __`PASSWORD`__: Password to access the stats page. If not set, stats page will not allow accesses.
* __`EMAIL`__: Email address for GDPR requests. Must be specified when telemetry is enabled.
* __`IPINFO_APIKEY`__: API key for ipinfo.io. Optional, but required if you expect to serve a large number of tests
* __`DISABLE_IPINFO`__: If set to true, ISP info and distance will not be fetched from ipinfo.io. Default: value: `false`
* __`DISTANCE`__: When `DISABLE_IPINFO` is set to false, this specifies how the distance from the server is measured. Can be either `km` for kilometers, `mi` for miles, or an empty string to disable distance measurement. Default value: `km`
* __`DB_TYPE`__: To use your own database, specify `postgresql` or `mysql` here. Any other value will default to using a local sqlite database.
* __`DB_HOSTNAME`__: URL of database server
* __`DB_DATABASENAME`__: name of database
* __`DB_USERNAME`__: username for database access
* __`DB_PASSWORD`__: password for for database access

If telemetry is enabled, a stats page will be available at `http://your.server/results/stats.php`, but a password must be specified.

###### Example
This command starts LibreSpeed in standalone mode, with the default settings, on port 80:

```
docker run -e MODE=standalone -p 80:80 -it adolfintel/speedtest
```

This command starts LibreSpeed in standalone mode, with telemetry, ID obfuscation and a stats password, on port 80:

```
docker run -e MODE=standalone -e TELEMETRY=true -e ENABLE_ID_OBFUSCATION=true -e PASSWORD="botnet!123" -p 80:80 -it adolfintel/speedtest
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
* __getIp_ispInfo_distance__: if true, the server will try to get an estimate of the distance from the client to the speedtest server. This will add a `distance` argument to the request to `url_getIp`. `__getIp_ispInfo__` must be enabled in order for this to work. getIP.php accomplishes this using ipinfo.io
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

## Multiple Points of Test
For multiple servers, you need to set up 1+ LibreSpeed backends, and 1 LibreSpeed frontend.

### Backend mode
In backend mode, LibreSpeed provides only a test point with no UI. To do this, set the `MODE` environment variable to `backend`.

The following backend files can be accessed on port 80: `garbage.php`, `empty.php`, `getIP.php`

Here's a list of additional environment variables available in this mode:
* __`IPINFO_APIKEY`__: API key for ipinfo.io. Optional, but required if you expect to serve a large number of tests

###### Example:
This command starts LibreSpeed in backend mode, with the default settings, on port 80:
```
docker run -e MODE=backend -p 80:80 -it adolfintel/speedtest
```

### Frontend mode
In frontend mode, LibreSpeed serves clients the Web UI and a list of servers. To do this:
* Set the `MODE` environment variable to `frontend`
* Create a `servers.json` file with your test points. The syntax is the following:
    ```
    [
        {
            "name": "Friendly name for Server 1",
            "server" :"//server1.mydomain.com/",
            "dlURL" :"garbage.php",
            "ulURL" :"empty.php",
            "pingURL" :"empty.php",
            "getIpURL" :"getIP.php"
        },
        {
            "name": "Friendly name for Server 2",
            "server" :"https://server2.mydomain.com/",
            "dlURL" :"garbage.php",
            "ulURL" :"empty.php",
            "pingURL" :"empty.php",
            "getIpURL" :"getIP.php"
        },
        ...more servers...
    ]
    ```
    Note: if a server only supports HTTP or HTTPS, specify the protocol in the server field. If it supports both, just use `//`.
    If you want the test to load this list from an URL instead of a file, just put the URL that you want to be loaded in the file in quotes, like this:
    ```
    "//mydomain.com/ServerList.json"
    ```
    It doesn't need to be a complete URL, if it's the same domain you can just specify the file name. Note that the same origin policy still applies.
* Mount this file to `/servers.json` in the container
    
The test can be accessed on port 80.

Here's a list of additional environment variables available in this mode:
* __`TITLE`__: Title of your speedtest. Default value: `LibreSpeed`
* __`TELEMETRY`__: Whether to enable telemetry or not. Default value: `false`
* __`ENABLE_ID_OBFUSCATION`__: When set to true with telemetry enabled, test IDs are obfuscated, to avoid exposing the database internal sequential IDs. Default value: `false`
* __`REDACT_IP_ADDRESSES`__: When set to true with telemetry enabled, IP addresses and hostnames are redacted from the collected telemetry, for better privacy. Default value: `false`
* __`PASSWORD`__: Password to access the stats page. If not set, stats page will not allow accesses.
* __`EMAIL`__: Email address for GDPR requests. Must be specified when telemetry is enabled.
* __`DISABLE_IPINFO`__: If set to true, ISP info and distance will not be fetched from ipinfo.io. Default: value: `false`
* __`DISTANCE`__: When `DISABLE_IPINFO` is set to false, this specifies how the distance from the server is measured. Can be either `km` for kilometers, `mi` for miles, or an empty string to disable distance measurement. Default value: `km`
* __`DB_TYPE`__: To use your own database, specify `postgresql` or `mysql` here. Any other value will default to using a local sqlite database.
* __`DB_HOSTNAME`__: URL of database server
* __`DB_DATABASENAME`__: name of database
* __`DB_USERNAME`__: username for database access
* __`DB_PASSWORD`__: password for for database access

###### Example
This command starts LibreSpeed in frontend mode, with a given `servers.json` file, and with telemetry, ID obfuscation, and a stats password:
```
docker run -e MODE=frontend -e TELEMETRY=true -e ENABLE_ID_OBFUSCATION=true -e PASSWORD="botnet!123" -v ./my_servers.json:/servers.json -p 80:80 -it adolfintel/speedtest
```

