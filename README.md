![LibreSpeed Logo](https://github.com/librespeed/speedtest/blob/master/.logo/logo3.png?raw=true)

# LibreSpeed

No Flash, No Java, No Websocket, No Bullshit.

This is a very lightweight speed test implemented in Javascript, using XMLHttpRequest and Web Workers.

## Try it

[Take a speed test](https://librespeed.org)

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

![Screenrecording of a running Speedtest](https://speedtest.fdossena.com/mpot_v6.gif)

## Server requirements

* A reasonably fast web server with Apache 2 (nginx, IIS also supported)
* PHP 5.4 or newer (other backends also available)
* MariaDB or MySQL database to store test results (optional, Microsoft SQL Server, PostgreSQL and SQLite also supported)
* A fast! internet connection

## Installation

Assuming you have PHP and a web server installed, the installation steps are quite simple.

1. Download the source code and extract it
1. Copy the following files to your web server's shared folder (ie. /var/www/html/speedtest for Apache): index.html, speedtest.js, speedtest_worker.js, favicon.ico and the backend folder
1. Optionally, copy the results folder too, and set up the database using the config file in it.
1. Be sure your permissions allow execute (755).
1. Visit YOURSITE/speedtest/index.html and voila!

### Installation Video

This video shows the installation process of a standalone LibreSpeed server: [Quick start installation guide for Debian 12](https://fdossena.com/?p=speedtest/quickstart_deb12.frag)

More videos will be added later.

## Android app

A template to build an Android client for your LibreSpeed installation is available [here](https://github.com/librespeed/speedtest-android).

## CLI client

A command line client is available [here](https://github.com/librespeed/speedtest-cli).

## Docker

A docker image is available on [GitHub](https://github.com/librespeed/speedtest/pkgs/container/speedtest), check our [docker documentation](doc_docker.md) for more info about it.
The image is built every week to include an updated version of the ipinfo-DB used for ISP detection. Also this ensures, that the latest security patches in PHP are installed. Therefore we recommend to use the `latest` image.

## Go backend

A Go implementation is available in the [`speedtest-go`](https://github.com/librespeed/speedtest-go) repo, maintained by [Maddie Zhan](https://github.com/maddie).

## Rust backend

A Rust implementation is available in the [`speedtest-rust`](https://github.com/librespeed/speedtest-rust) repo, maintained by [Sudo Dios](https://github.com/sudodios).

## Node.js backend

A partial Node.js implementation is available in the `node` branch, developed by [dunklesToast](https://github.com/dunklesToast). It's not recommended to use at the moment.

## Donate

[![Donate with Liberapay](https://liberapay.com/assets/widgets/donate.svg)](https://liberapay.com/fdossena/donate)
[Donate with PayPal](https://www.paypal.me/sineisochronic)

## License

Copyright (C) 2016-2024 Federico Dossena

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
