"use strict";

/*
 * Minimal UDP speed-test protocol (mini-iperf3 style).
 *
 * Every datagram has a fixed 13-byte header:
 *   byte 0      : type (single ASCII char)
 *   bytes 1-4   : sequence number (uint32 big-endian)
 *   bytes 5-12  : timestamp in milliseconds (uint64 big-endian)
 *   bytes 13+   : payload
 *
 * Client -> Server:
 *   P : ping request  (server echoes it back as 'p' with the same seq + timestamp)
 *   U : upload data   (server counts and discards)
 *   S : start download (payload starts with uint32 duration_ms; server floods 'D')
 *   E : end of upload (server reports upload counters and resets them)
 *
 * Server -> Client:
 *   p : ping echo
 *   D : download data
 *   R : report (payload is a JSON string)
 */

const HEADER_SIZE = 13;

const TYPE_PING = 0x50; // 'P'
const TYPE_UPLOAD = 0x55; // 'U'
const TYPE_START_DL = 0x53; // 'S'
const TYPE_END = 0x45; // 'E'
const TYPE_PING_ECHO = 0x70; // 'p'
const TYPE_DOWNLOAD = 0x44; // 'D'
const TYPE_REPORT = 0x52; // 'R'

function encode(type, seq, timestampMs, payload) {
  const payloadBuf = payload ? Buffer.from(payload) : Buffer.alloc(0);
  const buf = Buffer.alloc(HEADER_SIZE + payloadBuf.length);
  buf[0] = type;
  buf.writeUInt32BE(seq >>> 0, 1);
  buf.writeBigUInt64BE(BigInt(timestampMs), 5);
  payloadBuf.copy(buf, HEADER_SIZE);
  return buf;
}

function decode(buf) {
  if (!buf || buf.length < HEADER_SIZE) return null;
  return {
    type: buf[0],
    seq: buf.readUInt32BE(1),
    timestamp: Number(buf.readBigUInt64BE(5)),
    payload: buf.subarray(HEADER_SIZE)
  };
}

module.exports = {
  HEADER_SIZE,
  TYPE_PING,
  TYPE_UPLOAD,
  TYPE_START_DL,
  TYPE_END,
  TYPE_PING_ECHO,
  TYPE_DOWNLOAD,
  TYPE_REPORT,
  encode,
  decode
};
