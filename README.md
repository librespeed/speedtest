# HTML5 Speedtest

No Flash, No Java, No Websocket, No Bullshit.

This is a very lightweight Speedtest implemented in Javascript, using XMLHttpRequest and Web Workers.

## Try it
[Take a Speedtest](http://speedtest.fdossena.com)

## Compatibility
Only modern browsers are supported (IE11, latest Edge, latest Chrome, latest Firefox, latest Safari)

## Requirements
 - A reasonably fast web server. PHP is optional but recommended (see doc.md for details)
 - Some way to generate garbage data (PHP script included, see doc.md for other solutions)
 - Your server must accept large POST requests (up to 20 Megabytes), otherwise the upload test will fail
 - It's also better if your server does not use compression, but it's not mandatory

## Quick installation videos
* [Debian 9.0 with Apache](https://fdossena.com/?p=speedtest/quickstart_deb.frag)
* [Windows Server 2016 with IIS](https://fdossena.com/?p=speedtest/quickstart_win.frag)

## How to use in your site
See the examples or doc.md

## Docker + Docker Compose

The project includes a basic `docker-compose.yml` for development.  To run, execute the following:

```
$ docker-compose build

$ docker-compose up
```


Speedtest will be available at [http://0.0.0.0:8888/](http://0.0.0.0:8888/).  You can try out all of the examples via their associated urls (i.e. `http://0.0.0.0:8888/example1.html`).

To run via Docker directly:

```
$ docker build -t adolfintel/speedtest:latest .

$ docker run -d --name  speedtest -p 0.0.0.0:80:80 adolfintel/speedtest:latest
```

## License
Copyright (C) 2016-2017 Federico Dossena

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

