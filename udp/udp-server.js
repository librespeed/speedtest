"use strict";

/*
 * LibreSpeed UDP test server (mini-iperf3 style, raw UDP).
 *
 * Usage:
 *   node udp-server.js [-p <port>] [-6] [--datagram <bytes>]
 *
 *   -p, --port       UDP port to listen on (default 5201)
 *   -6, --ipv6       use IPv6 instead of IPv4
 *   --datagram       payload size in bytes for throughput datagrams (default 1200)
 *
 * This server only speaks the udp/protocol.js wire format. It echoes ping
 * datagrams, counts upload datagrams, and floods download datagrams on request.
 * It intentionally avoids IP fragmentation by keeping datagrams small.
 */

const dgram = require("dgram");
const P = require("./protocol");

const args = parseArgs(process.argv.slice(2));
const HOST = args.ipv6 ? "::" : "0.0.0.0";
const PORT = args.port;
const DATAGRAM = args.datagram;

const sock = dgram.createSocket(args.ipv6 ? "udp6" : "udp4");

// counters for the current client session
let uploadBytes = 0;
let uploadPackets = 0;
let downloadBytes = 0;
let downloadPackets = 0;
let downloadActive = false;

function parseArgs(argv) {
  const out = { port: 5201, datagram: 1200, ipv6: false };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === "-p" || a === "--port") out.port = parseInt(argv[++i], 10);
    else if (a === "-6" || a === "--ipv6") out.ipv6 = true;
    else if (a === "--datagram") out.datagram = parseInt(argv[++i], 10);
    else if (a === "-h" || a === "--help") out.help = true;
  }
  return out;
}

if (args.help) {
  console.log("LibreSpeed UDP server\n\n  node udp-server.js [-p <port>] [-6] [--datagram <bytes>]");
  process.exit(0);
}

function send(pkt, rinfo) {
  sock.send(pkt, rinfo.port, rinfo.address, function(err) {
    if (err) console.error("send error:", err.message);
  });
}

function sendReport(rinfo, kind, bytes, packets) {
  const report = JSON.stringify({ kind: kind, bytes: bytes, packets: packets });
  send(P.encode(P.TYPE_REPORT, 0, Date.now(), report), rinfo);
}

// Flood download datagrams to the client for `durationMs`, then report the count.
async function startDownload(rinfo, durationMs) {
  if (downloadActive) return;
  downloadActive = true;
  downloadBytes = 0;
  downloadPackets = 0;

  const payload = Buffer.alloc(DATAGRAM, 0xab);
  const endAt = Date.now() + durationMs;
  let seq = 0;

  while (Date.now() < endAt) {
    for (let i = 0; i < 500; i++) {
      const pkt = P.encode(P.TYPE_DOWNLOAD, seq++, Date.now(), payload);
      send(pkt, rinfo);
      downloadBytes += pkt.length;
      downloadPackets++;
    }
    // yield to the event loop so the socket stays responsive
    await new Promise(function(resolve) { setImmediate(resolve); });
  }

  // Send the report a few times: a single UDP report could be lost.
  for (let i = 0; i < 3; i++) {
    sendReport(rinfo, "download", downloadBytes, downloadPackets);
    await new Promise(function(resolve) { setTimeout(resolve, 100); });
  }
  downloadActive = false;
}

sock.on("message", function(msg, rinfo) {
  const p = P.decode(msg);
  if (!p) return;

  switch (p.type) {
    case P.TYPE_PING:
      // echo back with the same seq + client timestamp
      send(P.encode(P.TYPE_PING_ECHO, p.seq, p.timestamp), rinfo);
      break;

    case P.TYPE_UPLOAD:
      uploadBytes += msg.length;
      uploadPackets++;
      break;

    case P.TYPE_START_DL: {
      const durationMs = p.payload && p.payload.length >= 4 ? p.payload.readUInt32BE(0) : 5000;
      startDownload(rinfo, Math.min(durationMs, 60000)); // cap at 60s
      break;
    }

    case P.TYPE_END: {
      // upload test over: report received counters (3x for reliability), then reset
      const bytes = uploadBytes;
      const packets = uploadPackets;
      uploadBytes = 0;
      uploadPackets = 0;
      for (let i = 0; i < 3; i++) {
        setTimeout(function() { sendReport(rinfo, "upload", bytes, packets); }, i * 100);
      }
      break;
    }
  }
});

sock.on("error", function(err) {
  console.error("server error:", err.message);
  process.exit(1);
});

sock.on("listening", function() {
  const addr = sock.address();
  console.log("LibreSpeed UDP server listening on udp://" + addr.address + ":" + addr.port);
  console.log("datagram payload: " + DATAGRAM + " bytes (" + (DATAGRAM + P.HEADER_SIZE) + " bytes on the wire)");
});

sock.bind(PORT, HOST);
