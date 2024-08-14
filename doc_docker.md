# Using the docker image

A docker version of LibreSpeed is available here: [GitHub Packages](https://github.com/librespeed/speedtest/pkgs/container/speedtest)

# Alpine Linux variant

An Alpine Linux based docker version of LibreSpeed is also available here: [GitHub Packages](https://github.com/librespeed/speedtest/pkgs/container/speedtest) under all the tags that have the `-alpine` suffix. This variant is significantly smaller but can have slightly different behaviour due to its toolchain being based in [musl](https://en.wikipedia.org/wiki/Musl) libc as mentioned in [here](https://alpinelinux.org/about/).

## Quickstart

If you just want to try it, the fastest way is:

```shell
docker run -p 80:80 -d --name speedtest --rm ghcr.io/librespeed/speedtest
```

Then go with your browser to port 80 of your server and try it out. If port 80 is already in use, adjust the first number in 80:80 above.
Default is to run in standalone mode.

## Docker Compose

In production environments we would recommend using docker-compose.

To start the container using [docker compose](https://docs.docker.com/compose/) the following `docker-compose.yml` configuration can be used:

```yml
version: '3.7'
services:
  speedtest:
    container_name: speedtest
    image: ghcr.io/librespeed/speedtest:latest
    restart: always
    environment:
      MODE: standalone
      #TITLE: "LibreSpeed"
      #TELEMETRY: "false"
      #ENABLE_ID_OBFUSCATION: "false"
      #REDACT_IP_ADDRESSES: "false"
      #PASSWORD:
      #EMAIL:
      #DISABLE_IPINFO: "false"
      #IPINFO_APIKEY: "your api key"
      #DISTANCE: "km"
      #WEBPORT: 80
    ports:
      - "80:80" # webport mapping (host:container)
```

Please adjust the environment variables according to the intended operating mode.

## Standalone mode

If you want to install LibreSpeed on a single server, you need to configure it in standalone mode. To do this, set the `MODE` environment variable to `standalone`.

The test can be accessed on port 80.

Here's a list of additional environment variables available in this mode:

* __`TITLE`__: Title of your speed test. Default value: `LibreSpeed`
* __`TELEMETRY`__: Whether to enable telemetry or not. If enabled, you maybe want your data to be persisted. See below. Default value: `false`
* __`ENABLE_ID_OBFUSCATION`__: When set to true with telemetry enabled, test IDs are obfuscated, to avoid exposing the database internal sequential IDs. Default value: `false`
* __`REDACT_IP_ADDRESSES`__: When set to true with telemetry enabled, IP addresses and hostnames are redacted from the collected telemetry, for better privacy. Default value: `false`
* __`DB_TYPE`__: When set to one of the supported DB-Backends it will use this instead of the default sqlite database backend. TELEMETRY has to be set to `true`. Also you have to create the database as described in [doc.md](doc.md#creating-the-database). Supported backend types are:
  * sqlite - no additional settings required
  * mysql, postgresql - set additional env-variables:
    * DB_HOSTNAME - Name or IP of the DB server
    * DB_PORT (mysql only) - Port where DB is running
    * DB_NAME - Name of the telemetry db
    * DB_USERNAME, DB_PASSWORD - credentials of the user with read and update permissions to the db
  * mssql - not supported in docker image yet (feel free to open a PR with that, has to be done in `entrypoint.sh`)
* __`PASSWORD`__: Password to access the stats page. If not set, stats page will not allow accesses.
* __`EMAIL`__: Email address for GDPR requests. Must be specified when telemetry is enabled.
* __`DISABLE_IPINFO`__: If set to `true`, ISP info and distance will not be fetched from either [ipinfo.io](https://ipinfo.io) or the offline database. Default: value: `false`
* __`IPINFO_APIKEY`__: API key for [ipinfo.io](https://ipinfo.io). Optional, but required if you want to use the full [ipinfo.io](https://ipinfo.io) APIs (required for distance measurement)
* __`DISTANCE`__: When `DISABLE_IPINFO` is set to false, this specifies how the distance from the server is measured. Can be either `km` for kilometers, `mi` for miles, or an empty string to disable distance measurement. Requires an [ipinfo.io](https://ipinfo.io) API key. Default value: `km`
* __`WEBPORT`__: Allows choosing a custom port for the included web server. Default value: `80`. Note that you will have to expose it through docker with the -p argument. This is not the port where the service is exposed outside docker!

If telemetry is enabled, a stats page will be available at `http://your.server/results/stats.php`, but a password must be specified.

### Persist sqlite database

Default DB driver is sqlite. The DB file is written to `/database/db.sql`.

So if you want your data to be persisted over image updates, you have to mount a volume with `-v $PWD/db-dir:/database`.

#### Example Standalone Mode with telemetry

This command starts LibreSpeed in standalone mode, with persisted telemetry, ID obfuscation and a stats password, on port 86:

```shell
docker run -e MODE=standalone -e TELEMETRY=true -e ENABLE_ID_OBFUSCATION=true -e PASSWORD="yourPasswordHere" -e WEBPORT=86 -p 86:86 -v $PWD/db-dir/:/database -it ghcr.io/librespeed/speedtest
```

## Multiple Points of Test

For multiple servers, you need to set up 1+ LibreSpeed backends, and 1 LibreSpeed frontend.

### Backend mode

In backend mode, LibreSpeed provides only a test point with no UI. To do this, set the `MODE` environment variable to `backend`.

The following backend files can be accessed on port 80: `garbage.php`, `empty.php`, `getIP.php`

Here's a list of additional environment variables available in this mode:

* __`IPINFO_APIKEY`__: API key for [ipinfo.io](https://ipinfo.io). Optional, but required if you want to use the full [ipinfo.io](https://ipinfo.io) APIs (required for distance measurement). If no API key is provided, the offline database will be used instead.

#### Example Backend mode

This command starts LibreSpeed in backend mode, with the default settings, on port 80:

```shell
docker run -e MODE=backend -p 80:80 -it ghcr.io/librespeed/speedtest
```

### Frontend mode

In frontend mode, LibreSpeed serves clients the Web UI and a list of servers. To do this:

* Set the `MODE` environment variable to `frontend`
* Create a servers.json file with your test points. The syntax is the following:

    ```jsonc
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
        //...more servers...
    ]
    ```

    Note: if a server only supports HTTP or HTTPS, specify the protocol in the server field. If it supports both, just use `//`.
* Mount this file to `/servers.json` in the container (example at the end of this file)

The test can be accessed on port 80.

The list of environment variables available in this mode is the same as [above in standalone mode](#standalone-mode).

#### Example Frontend mode

This command starts LibreSpeed in frontend mode, with a given `servers.json` file, and with telemetry, ID obfuscation, and a stats password and a persistant sqlite database for results:

```shell
docker run -e MODE=frontend -e TELEMETRY=true -e ENABLE_ID_OBFUSCATION=true -e PASSWORD="yourPasswordHere" -v $PWD/servers.json:/servers.json -v $PWD/db-dir/:/database -p 80:80 -it ghcr.io/librespeed/speedtest
```

### Dual mode

In dual mode, LibreSpeed operates as a standalone server that can also connect to other test points.
To do this:

* Set the `MODE` environment variable to `dual`
* Follow the `servers.json` instructions for the frontend mode
* The first server entry should be the local server, using the server endpoint address that a client can access.
