"use strict";

/*
 * LibreSpeed UDP test client (mini-iperf3 style, raw UDP).
 *
 * Usage:
 *   node udp-client.js [-h <host>] [-p <port>] [-6] [--datagram <bytes>]
 *                      [--ping <n>] [--upload <sec>] [--download <sec>] [--all <sec>]
 *
 *   -h, --host       server host (default 127.0.0.1)
 *   -p, --port       server port (default 5201)
 *   -6, --ipv6       use IPv6
 *   --datagram       payload size in bytes for throughput datagrams (default 1200)
 *   --ping <n>       send n ping datagrams and report RTT / jitter / loss
 *   --upload <sec>   flood upload datagrams for <sec> seconds
 *   --download <sec> receive download datagrams for <sec> seconds
 *   --all <sec>      run ping (30) + upload + download, each using <sec> seconds
 *
 * Results are printed as human-readable lines and as a single JSON object.
 */

const dgram = require("dgram");
const P = require("./protocol");

const args = parseArgs(process.argv.slice(2));
const HOST = args.host;
const PORT = args.port;
const DATAGRAM = args.datagram;
const sock = dgram.createSocket(args.ipv6 ? "udp6" : "udp4");

function parseArgs(argv) {
  const out = { host: "127.0.0.1", port: 5201, datagram: 1200, ipv6: false };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === "-h" || a === "--host") out.host = argv[++i];
    else if (a === "-p" || a === "--port") out.port = parseInt(argv[++i], 10);
    else if (a === "-6" || a === "--ipv6") out.ipv6 = true;
    else if (a === "--datagram") out.datagram = parseInt(argv[++i], 10);
    else if (a === "--ping") out.ping = parseInt(argv[++i], 10);
    else if (a === "--upload") out.upload = parseFloat(argv[++i]);
    else if (a === "--download") out.download = parseFloat(argv[++i]);
    else if (a === "--all") out.all = parseFloat(argv[++i]);
    else if (a === "--help") out.help = true;
  }
  return out;
}

if (args.help) {
  console.log(
    "LibreSpeed UDP client\n\n" +
    "  node udp-client.js [-h <host>] [-p <port>] [-6] [--datagram <bytes>]\n" +
    "                     [--ping <n>] [--upload <sec>] [--download <sec>] [--all <sec>]\n"
  );
  process.exit(0);
}

// The current message collector (installed per test). Receives decoded packets.
let collector = null;

sock.on("message", function(msg) {
  const p = P.decode(msg);
  if (p && collector) collector(p, msg.length);
});

sock.on("error", function(err) {
  console.error("client error:", err.message);
  process.exit(1);
});

function nowMs() {
  return Number(process.hrtime.bigint() / 1000000n);
}

function mbps(bytes, seconds) {
  return (bytes * 8) / seconds / 1000000;
}

function fmt(n, digits) {
  return typeof n === "number" ? n.toFixed(digits === undefined ? 2 : digits) : String(n);
}

// ---- ping / jitter / loss -------------------------------------------------
function pingTest(count) {
  return new Promise(function(resolve) {
    const rtts = [];
    const sentAt = new Map(); // seq -> client timestamp ms
    let received = 0;

    collector = function(p) {
      if (p.type !== P.TYPE_PING_ECHO) return;
      const sent = sentAt.get(p.seq);
      if (sent === undefined) return;
      rtts.push(nowMs() - p.timestamp); // server echoes the client timestamp
      received++;
    };

    for (let seq = 0; seq < count; seq++) {
      sentAt.set(seq, nowMs());
      sock.send(P.encode(P.TYPE_PING, seq, sentAt.get(seq)), PORT, HOST);
    }

    // wait for echoes (2s grace), then finish
    setTimeout(function() {
      collector = null;
      const loss = count > 0 ? (count - received) / count : 0;
      rtts.sort(function(a, b) { return a - b; });
      const avg = rtts.length ? rtts.reduce(function(a, b) { return a + b; }, 0) / rtts.length : 0;
      const min = rtts.length ? rtts[0] : 0;
      const max = rtts.length ? rtts[rtts.length - 1] : 0;
      // jitter = mean absolute difference between consecutive RTTs
      let jitter = 0;
      for (let i = 1; i < rtts.length; i++) jitter += Math.abs(rtts[i] - rtts[i - 1]);
      if (rtts.length > 1) jitter /= rtts.length - 1;
      resolve({
        kind: "ping",
        sent: count,
        received: received,
        loss: loss * 100,
        avgMs: avg,
        minMs: min,
        maxMs: max,
        jitterMs: jitter
      });
    }, 2000);
  });
}

// ---- upload ---------------------------------------------------------------
function uploadTest(seconds) {
  return new Promise(function(resolve) {
    const payload = Buffer.alloc(DATAGRAM, 0xcd);
    let sentBytes = 0;
    let sentPackets = 0;
    let seq = 0;
    let report = null;

    collector = function(p) {
      if (p.type === P.TYPE_REPORT && p.payload.length) {
        try { report = JSON.parse(p.payload.toString()); } catch (e) {}
      }
    };

    (async function() {
      const durationMs = Math.round(seconds * 1000);
      const startAt = process.hrtime.bigint();
      const endAt = startAt + BigInt(durationMs) * 1000000n;
      while (process.hrtime.bigint() < endAt) {
        for (let i = 0; i < 500; i++) {
          const pkt = P.encode(P.TYPE_UPLOAD, seq++, Date.now(), payload);
          sock.send(pkt, PORT, HOST);
          sentBytes += pkt.length;
          sentPackets++;
        }
        await new Promise(function(r) { setImmediate(r); });
      }
      const elapsed = Number(process.hrtime.bigint() - startAt) / 1e9;

      // signal end of upload and wait for the server's report
      sock.send(P.encode(P.TYPE_END, 0, Date.now()), PORT, HOST);
      await new Promise(function(r) { setTimeout(r, 1500); });
      collector = null;

      const receivedBytes = report ? report.bytes : 0;
      const receivedPackets = report ? report.packets : 0;
      const loss = sentPackets > 0 ? (sentPackets - receivedPackets) / sentPackets : 0;
      resolve({
        kind: "upload",
        seconds: elapsed,
        sentBytes: sentBytes,
        sentPackets: sentPackets,
        receivedBytes: receivedBytes,
        receivedPackets: receivedPackets,
        loss: loss * 100,
        sentMbps: mbps(sentBytes, elapsed),
        receivedMbps: mbps(receivedBytes, elapsed)
      });
    })();
  });
}

// ---- download -------------------------------------------------------------
function downloadTest(seconds) {
  return new Promise(function(resolve) {
    let receivedBytes = 0;
    let receivedPackets = 0;
    let report = null;

    collector = function(p, msgLen) {
      if (p.type === P.TYPE_DOWNLOAD) {
        receivedBytes += msgLen;
        receivedPackets++;
      } else if (p.type === P.TYPE_REPORT && p.payload.length) {
        try { report = JSON.parse(p.payload.toString()); } catch (e) {}
      }
    };

    const durationMs = Math.round(seconds * 1000);
    const durBuf = Buffer.alloc(4);
    durBuf.writeUInt32BE(durationMs, 0);
    sock.send(P.encode(P.TYPE_START_DL, 0, Date.now(), durBuf), PORT, HOST);

    // collect for the requested duration plus a small grace for the final report
    setTimeout(function() {
      collector = null;
      const sentBytes = report ? report.bytes : 0;
      const sentPackets = report ? report.packets : 0;
      const loss = sentPackets > 0 ? (sentPackets - receivedPackets) / sentPackets : 0;
      resolve({
        kind: "download",
        seconds: seconds,
        sentBytes: sentBytes,
        sentPackets: sentPackets,
        receivedBytes: receivedBytes,
        receivedPackets: receivedPackets,
        loss: loss * 100,
        sentMbps: mbps(sentBytes, seconds),
        receivedMbps: mbps(receivedBytes, seconds)
      });
    }, durationMs + 1500);
  });
}

// ---- main -----------------------------------------------------------------
(async function main() {
  const results = [];

  if (args.all) {
    const sec = args.all;
    results.push(await pingTest(30));
    results.push(await uploadTest(sec));
    results.push(await downloadTest(sec));
  } else {
    if (args.ping) results.push(await pingTest(args.ping));
    if (args.upload) results.push(await uploadTest(args.upload));
    if (args.download) results.push(await downloadTest(args.download));
  }

  if (results.length === 0) {
    console.log("Nothing to do. Use --ping <n>, --upload <sec>, --download <sec> or --all <sec>.");
    process.exit(1);
  }

  printResults(results);
  console.log("\nJSON: " + JSON.stringify(results));
  sock.close();
})();

function printResults(results) {
  for (const r of results) {
    if (r.kind === "ping") {
      console.log(
        "PING   sent=" + r.sent + " received=" + r.received +
        " loss=" + fmt(r.loss, 1) + "%" +
        " rtt=" + fmt(r.avgMs) + "ms (min " + fmt(r.minMs) + ", max " + fmt(r.maxMs) +
        ") jitter=" + fmt(r.jitterMs) + "ms"
      );
    } else if (r.kind === "upload") {
      console.log(
        "UPLOAD sent=" + fmt(r.sentMbps) + "Mbps received=" + fmt(r.receivedMbps) +
        "Mbps loss=" + fmt(r.loss, 2) + "% (" + r.sentPackets + " -> " + r.receivedPackets + " pkts)"
      );
    } else if (r.kind === "download") {
      console.log(
        "DOWNLOAD sent=" + fmt(r.sentMbps) + "Mbps received=" + fmt(r.receivedMbps) +
        "Mbps loss=" + fmt(r.loss, 2) + "% (" + r.sentPackets + " -> " + r.receivedPackets + " pkts)"
      );
    }
  }
}
