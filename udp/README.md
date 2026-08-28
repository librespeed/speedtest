# LibreSpeed UDP test

A raw-UDP speed test for LibreSpeed, in the spirit of iperf3: it measures
**throughput, packet loss, RTT and jitter over UDP**, which the browser-based
test cannot do (browsers expose no raw UDP socket).

It is a self-contained pair of Node.js scripts with **zero dependencies** (only
the built-in `dgram` module). Node.js 12+ is sufficient.

## Why not in the browser?

The browser speed test (`speedtest_worker.js`) can only speak HTTP(S) over TCP.
There is no way for page JavaScript to send raw UDP datagrams. The closest
browser-native alternative is WebTransport over HTTP/3 (QUIC), which runs on UDP
under the hood but requires an HTTP/3-capable server and Chromium — that is a
separate, optional path and is not included here.

## Quick start

Server (run on your LibreSpeed host):

```sh
node udp/udp-server.js -p 5201
```

Client (run anywhere that can reach the server over UDP):

```sh
node udp/udp-client.js -h <server> -p 5201 --all 5
```

`--all 5` runs ping (30 packets), upload and download for 5 seconds each.

## Options

### Server

| Flag | Default | Description |
| --- | --- | --- |
| `-p, --port` | `5201` | UDP port to listen on |
| `-6, --ipv6` | off | use IPv6 |
| `--datagram` | `1200` | payload size in bytes (kept below the MTU to avoid IP fragmentation) |

### Client

| Flag | Default | Description |
| --- | --- | --- |
| `-h, --host` | `127.0.0.1` | server host |
| `-p, --port` | `5201` | server port |
| `-6, --ipv6` | off | use IPv6 |
| `--datagram` | `1200` | payload size in bytes |
| `--ping <n>` | | send `n` ping datagrams, report RTT / jitter / loss |
| `--upload <sec>` | | flood upload datagrams for `sec` seconds |
| `--download <sec>` | | receive download datagrams for `sec` seconds |
| `--all <sec>` | | run ping (30) + upload + download, `sec` seconds each |

## Example output

```
PING   sent=30 received=30 loss=0.0% rtt=6.50ms (min 5.00, max 8.00) jitter=0.10ms
UPLOAD sent=305.81Mbps received=305.81Mbps loss=0.00% (63500 -> 63500 pkts)
DOWNLOAD sent=300.82Mbps received=300.82Mbps loss=0.00% (62000 -> 62000 pkts)
```

A JSON array with the same results is printed as the last line, so the client
can be scripted.

## How it maps to LibreSpeed's metrics

| Browser test (TCP/HTTP) | UDP test |
| --- | --- |
| Download / Upload (Mbps) | `DOWNLOAD` / `UPLOAD` (Mbps) |
| Ping / Jitter (ms) | `PING` RTT / jitter (ms) |
| (implicit, HTTP retries) | explicit **packet loss %** — a UDP-only metric |

## Protocol

See `protocol.js`. Every datagram carries a 13-byte header
(`type` + `seq` + `timestamp`) followed by an optional payload. The server echoes
pings, counts upload datagrams, and floods download datagrams; loss is computed
by comparing the sender's and receiver's packet counters.

## Security notes

- The server binds `0.0.0.0` by default and has **no authentication**. Expose it
  only on a trusted network, or firewall the port.
- It reflects the client's source address/port, so it cannot be used as a UDP
  amplifier (responses are at most the size of the requests).
