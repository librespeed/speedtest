![LibreSpeed Logo](https://github.com/librespeed/speedtest/blob/master/.logo/logo3.png?raw=true)

# LibreSpeed

No Flash, No Java, No WebSocket, No Bullshit.

This is a very lightweight speed test implemented in JavaScript, using XMLHttpRequest and Web Workers.

## Try it
[Take a speed test](https://speedtest.fdossena.com) (PHP implementation)

## Compatibility
All modern browsers are supported: IE11, latest Edge, latest Chrome, latest Firefox, latest Safari.  
Works with mobile versions too.

## Features
* Download
* Upload
* Ping
* Jitter
* IP Address, ISP, distance from server (optional)
* Telemetry (optional)
* Results sharing (optional)
* Multiple Points of Test (optional)

![Screenshot](https://speedtest.fdossena.com/mpot_v6.gif)

## Server requirements
* Any [Go supported platforms](https://github.com/golang/go/wiki/MinimumRequirements)
* BoltDB, PostgreSQL or MySQL database to store test results (optional)
* A fast! Internet connection

## Installation

You need Go 1.13+ to compile the binary.

1. Use this to install the binary into your `GOPATH`:

    ```
    $ go get github.com/librespeed/speedtest@go
    ```

2. If you have telemetry enabled,
    - For PostgreSQL/MySQL, create database and import the corresponding `.sql` file under `database/{postgresql,mysql}`

        ```
        # assume you have already created a database named `speedtest` under current user
        $ psql speedtest < database/postgresql/telemetry_postgresql.sql
        ```
     
    - For embedded BoltDB, make sure to define the `database_file` path in `settings.toml`:
    
        ```
        database_file="speedtest.db"
        ```

3. Put `assets` folder under the same directory as your compiled binary.
    - Make sure the font files and JavaScripts are in the `assets` directory
    - You can have multiple HTML pages under `assets` directory. They can be access directly under the server root
    (e.g. `/example-singleServer-full.html`)
    - It's possible to have a default page mapped to `/`, simply put a file named `index.html` under `assets`

4. Change `settings.toml` according to your environment:

    ```toml
    # bind address
    bind_address="127.0.0.1"
    # backend listen port, default is 8989
    listen_port=8989
    # download test chunks, default is 4
    download_chunks=4
    # ipinfo.io API key, if applicable
    ipinfo_api_key=""
    # distance unit used in frontend, available options: M (miles), K (kilometers), N (nautical miles), default is "K"
    distance_unit="K"
    # enable CORS headers
    enable_cors=false
    
    # password for logging into statistics page, change this to enable stats page
    statistics_password="PASSWORD"
    # redact IP addresses
    redact_ip_addresses=false
    
    # database type for statistics data, currently supports: bolt, mysql, postgresql
    database_type="postgresql"
    database_hostname="localhost"
    database_name="speedtest"
    database_username="postgres"
    database_password=""
   
    # if you use `bolt` as database, set database_file to database file location
    database_file="speedtest.db"
    ```

## Differences between Go and PHP implementation and caveats

- Since there is no CGo-free SQLite implementation available, I've opted to use [BoltDB](https://github.com/etcd-io/bbolt)
  instead, as an embedded database alternative to SQLite
- Test IDs are generated ULID, there is no option to change them to plain ID
- API endpoints have the same names, except the `.php` extension (e.g `empty`, `garbage`, `getIP`)
- You can use the same HTML template from the PHP implementation
- There might be a slight delay on program start if your Internet connection is slow. That's because the program will
attempt to fetch your current network's ISP info for distance calculation between your network and the speed test client's.
This action will only be taken once, and cached for later use.

## License
Copyright (C) 2016-2019 Federico Dossena  
Copyright (C) 2020 Maddie Zhan

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/lgpl>.