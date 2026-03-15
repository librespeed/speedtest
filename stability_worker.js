/*
	LibreSpeed - Stability Test Worker
	https://github.com/librespeed/speedtest/
	GNU LGPLv3 License
*/

// data reported to main thread
let testState = -1; // -1=idle, 0=starting, 1=running, 4=finished, 5=aborted
let currentPing = 0;
let avgPing = 0;
let minPing = -1;
let maxPing = 0;
let jitter = 0;
let packetLoss = 0;
let elapsed = 0;
let progress = 0;

let pingData = []; // all ping data points {t: elapsedMs, ping: ms, lost: bool}
let lastReportedIndex = 0; // for delta delivery
let totalSamples = 0;
let failedSamples = 0;
let pingSum = 0;

let settings = {
  url_ping: "backend/empty.php",
  url_ping_external: "", // external URL to ping (uses fetch no-cors, e.g. "https://www.google.com/generate_204")
  duration: 60, // seconds
  ping_interval: 200, // minimum ms between pings to limit sample rate
  ping_allowPerformanceApi: true,
  mpot: false
};

let xhr = null;
let startTime = 0;
let prevInstspd = 0;
let aborted = false;

function url_sep(url) {
  return url.match(/\?/) ? "&" : "?";
}

this.addEventListener("message", function (e) {
  const params = e.data.split(" ");
  if (params[0] === "status") {
    // return current state with delta ping data
    const newData = pingData.slice(lastReportedIndex);
    lastReportedIndex = pingData.length;
    postMessage(
      JSON.stringify({
        testState: testState,
        currentPing: currentPing,
        avgPing: avgPing,
        minPing: minPing,
        maxPing: maxPing,
        jitter: jitter,
        packetLoss: packetLoss,
        elapsed: elapsed,
        duration: settings.duration,
        progress: progress,
        pingData: newData,
        totalSamples: totalSamples,
        failedSamples: failedSamples
      })
    );
  }
  if (params[0] === "start" && testState === -1) {
    testState = 0;
    try {
      let s = {};
      try {
        const ss = e.data.substring(6);
        if (ss) s = JSON.parse(ss);
      } catch (e) {
        console.warn("Error parsing settings JSON");
      }
      for (let key in s) {
        if (typeof settings[key] !== "undefined") settings[key] = s[key];
      }
    } catch (e) {
      console.warn("Error applying settings: " + e);
    }
    // start the stability test
    aborted = false;
    startTime = new Date().getTime();
    testState = 1;
    doPing();
  }
  if (params[0] === "abort") {
    if (testState >= 4) return;
    aborted = true;
    testState = 5;
    if (xhr) {
      try {
        xhr.abort();
      } catch (e) {}
    }
  }
});

function recordPing(instspd) {
  // guard against 0ms pings
  if (instspd < 1) instspd = prevInstspd;
  if (instspd < 1) instspd = 1;

  totalSamples++;
  currentPing = instspd;
  pingSum += instspd;
  avgPing = parseFloat((pingSum / (totalSamples - failedSamples)).toFixed(2));

  if (minPing === -1 || instspd < minPing) minPing = instspd;
  if (instspd > maxPing) maxPing = instspd;

  // jitter calculation (same weighted formula as speedtest_worker.js)
  if (totalSamples > 1 && prevInstspd > 0) {
    const instjitter = Math.abs(instspd - prevInstspd);
    if (totalSamples === 2) {
      jitter = instjitter;
    } else {
      jitter = instjitter > jitter ? jitter * 0.3 + instjitter * 0.7 : jitter * 0.8 + instjitter * 0.2;
    }
  }
  prevInstspd = instspd;

  // packet loss
  packetLoss = totalSamples > 0 ? parseFloat(((failedSamples / totalSamples) * 100).toFixed(2)) : 0;

  // record data point
  const now = new Date().getTime();
  elapsed = (now - startTime) / 1000;
  pingData.push({ t: elapsed, ping: parseFloat(instspd.toFixed(2)), lost: false });
}

function recordLoss() {
  const now = new Date().getTime();
  totalSamples++;
  failedSamples++;
  packetLoss = parseFloat(((failedSamples / totalSamples) * 100).toFixed(2));
  elapsed = (now - startTime) / 1000;
  pingData.push({ t: elapsed, ping: 0, lost: true });
}

// pace pings to avoid excessive sample rates on low-latency links
function schedulePing(rtt) {
  const delay = Math.max(0, settings.ping_interval - rtt);
  if (delay > 0) {
    setTimeout(doPing, delay);
  } else {
    doPing();
  }
}

function doPing() {
  if (aborted || testState >= 4) return;

  // check if duration exceeded
  const now = new Date().getTime();
  elapsed = (now - startTime) / 1000;
  progress = Math.min(1, elapsed / settings.duration);
  if (elapsed >= settings.duration) {
    testState = 4;
    progress = 1;
    return;
  }

  // external ping mode: use fetch with no-cors
  if (settings.url_ping_external) {
    doPingExternal();
    return;
  }

  const prevT = new Date().getTime();
  xhr = new XMLHttpRequest();
  xhr.onload = function () {
    if (aborted || testState >= 4) return;
    const now = new Date().getTime();
    let instspd = now - prevT;

    if (settings.ping_allowPerformanceApi) {
      try {
        let p = performance.getEntries();
        p = p[p.length - 1];
        let d = p.responseStart - p.requestStart;
        if (d <= 0) d = p.duration;
        if (d > 0 && d < instspd) instspd = d;
      } catch (e) {
        // Performance API not available, use estimate
      }
    }

    recordPing(instspd);
    schedulePing(instspd);
  };
  xhr.onerror = function () {
    if (aborted || testState >= 4) return;
    recordLoss();
    schedulePing(0);
  };
  xhr.ontimeout = xhr.onerror;
  xhr.open(
    "GET",
    settings.url_ping + url_sep(settings.url_ping) + (settings.mpot ? "cors=true&" : "") + "r=" + Math.random(),
    true
  );
  try {
    xhr.timeout = 5000;
  } catch (e) {}
  xhr.send();
}

// ping an external host using fetch with no-cors (opaque response, but timing still works)
function doPingExternal() {
  const prevT = new Date().getTime();
  const url =
    settings.url_ping_external + (settings.url_ping_external.indexOf("?") >= 0 ? "&" : "?") + "r=" + Math.random();
  fetch(url, { mode: "no-cors", cache: "no-store" })
    .then(function () {
      if (aborted || testState >= 4) return;
      const instspd = new Date().getTime() - prevT;
      recordPing(instspd);
      schedulePing(instspd);
    })
    .catch(function () {
      if (aborted || testState >= 4) return;
      recordLoss();
      schedulePing(0);
    });
}
