/**
 * Design by fromScratch Studio - 2022, 2023 (fromscratch.io)
 * Implementation in HTML/CSS/JS by Timendus - 2024 (https://github.com/Timendus)
 *
 * See https://github.com/librespeed/speedtest/issues/585
 */

// States the UI can be in
const INITIALIZING = 0;
const READY = 1;
const RUNNING = 2;
const FINISHED = 3;

// Keep some global state here
const testState = {
  state: INITIALIZING,
  speedtest: null,
  servers: [],
  selectedServerDirty: false,
  testData: null,
  testDataDirty: false,
  telemetryEnabled: false,
};

// Bootstrap the application when the DOM is ready
window.addEventListener("DOMContentLoaded", async () => {
  createSpeedtest();
  hookUpButtons();
  startRenderingLoop();
  applySettingsJSON();
  applyServerListJSON();
});

/**
 * Create a new Speedtest and hook it into the global state
 */
function createSpeedtest() {
  testState.speedtest = new Speedtest();
  testState.speedtest.onupdate = (data) => {
    testState.testData = data;
    testState.testDataDirty = true;
  };
  testState.speedtest.onend = (aborted) =>
    (testState.state = aborted ? READY : FINISHED);
}

/**
 * Make all the buttons respond to the right clicks
 */
function hookUpButtons() {
  document
    .querySelector("#start-button")
    .addEventListener("click", startButtonClickHandler);
  document
    .querySelector("#choose-privacy")
    .addEventListener("click", () =>
      document.querySelector("#privacy").showModal()
    );
  document
    .querySelector("#share-results")
    .addEventListener("click", () =>
      document.querySelector("#share").showModal()
    );
  document
    .querySelector("#copy-link")
    .addEventListener("click", copyLinkButtonClickHandler);
  document
    .querySelectorAll(".close-dialog, #close-privacy")
    .forEach((element) => {
      element.addEventListener("click", () =>
        document.querySelectorAll("dialog").forEach((modal) => modal.close())
      );
    });
}

/**
 * Event listener for clicks on the main start button
 */
function startButtonClickHandler() {
  switch (testState.state) {
    case READY:
    case FINISHED:
      testState.speedtest.start();
      testState.state = RUNNING;
      return;
    case RUNNING:
      testState.speedtest.abort();
      // testState.state is updated by `onend` handler of speedtest
      return;
    default:
      return;
  }
}

/**
 * Event listener for clicks on the "Copy link" button in the modal
 */
async function copyLinkButtonClickHandler() {
  const link = document.querySelector("#share img").src;
  await navigator.clipboard.writeText(link);
  const button = document.querySelector("#copy-link");
  button.classList.add("active");
  button.textContent = "Copied!";
  setTimeout(() => {
    button.classList.remove("active");
    button.textContent = "Copy link";
  }, 3000);
}

/**
 * Load settings from settings.json on the server and apply them
 */
async function applySettingsJSON() {
  try {
    const response = await fetch("settings.json");
    const settings = await response.json();
    if (!settings || typeof settings !== "object") {
      return console.error("Settings are empty or malformed");
    }
    for (let setting in settings) {
      testState.speedtest.setParameter(setting, settings[setting]);
      if (
        setting == "telemetry_level" &&
        settings[setting] &&
        settings[setting] != "off" &&
        settings[setting] != "disabled" &&
        settings[setting] != "false"
      ) {
        testState.telemetryEnabled = true;
        document.querySelector("#privacy-warning").classList.remove("hidden");
      }
    }
  } catch (error) {
    console.error("Failed to fetch settings:", error);
  }
}

/**
 * Load server list from server-list.json on the server and populate the
 * dropdown
 */
async function applyServerListJSON() {
  try {
    const response = await fetch("server-list.json");
    const servers = await response.json();
    if (!servers || !Array.isArray(servers) || servers.length === 0) {
      return console.error("Server list is empty or malformed");
    }
    testState.servers = servers;
    populateDropdown(testState.servers);
    if (servers.length > 1) {
      testState.speedtest.addTestPoints(servers);
      testState.speedtest.selectServer((server) => {
        if (server) {
          selectServer(server);
        } else {
          alert(
            "Can't reach any of the speedtest servers! But you're on this page. Something weird is going on with your network."
          );
        }
      });
    }
  } catch (error) {
    console.error("Failed to fetch server list:", error);
  }
}

/**
 * Add all the servers to the server selection dropdown and make it actually
 * work.
 * @param {Array} servers - an array of server objects
 */
function populateDropdown(servers) {
  const serverSelector = document.querySelector("div.server-selector");
  const serverList = serverSelector.querySelector("ul.servers");
  serverSelector.classList.add("active");

  // If we have only a single server, just show it
  if (servers.length === 1) {
    serverSelector.classList.add("single-server");
    selectServer(servers[0]);
    return;
  }

  // Make the dropdown open and close
  serverSelector.addEventListener("click", () => {
    serverList.classList.toggle("active");
  });
  document.addEventListener("click", (e) => {
    if (e.target.closest("div.server-selector") !== serverSelector)
      serverList.classList.remove("active");
  });

  // Populate the list to choose from
  servers.forEach((server) => {
    const item = document.createElement("li");
    const link = document.createElement("a");
    link.href = "#";
    link.innerHTML = `${server.name}${
      server.sponsorName ? ` <span>(${server.sponsorName})</span>` : ""
    }`;
    link.addEventListener("click", () => selectServer(server));
    item.appendChild(link);
    serverList.appendChild(item);
  });
}

/**
 * Set the given server as the selected server for the speedtest
 * @param {Object} server - a server object
 */
function selectServer(server) {
  testState.speedtest.setSelectedServer(server);
  testState.selectedServerDirty = true;
  testState.state = READY;
}

/**
 * Start the requestAnimationFrame UI rendering loop
 */
function startRenderingLoop() {
  // Do these queries once to speed up the rendering itself
  const serverSelector = document.querySelector("div.server-selector");
  const selectedServer = serverSelector.querySelector("#selected-server");
  const sponsor = serverSelector.querySelector("#sponsor");
  const startButton = document.querySelector("#start-button");
  const privacyWarning = document.querySelector("#privacy-warning");

  const gauges = document.querySelectorAll("#download-gauge, #upload-gauge");
  const downloadProgress = document.querySelector("#download-gauge .progress");
  const uploadProgress = document.querySelector("#upload-gauge .progress");
  const downloadGauge = document.querySelector("#download-gauge .speed");
  const uploadGauge = document.querySelector("#upload-gauge .speed");
  const downloadText = document.querySelector("#download-gauge span");
  const uploadText = document.querySelector("#upload-gauge span");

  const pingAndJitter = document.querySelectorAll(".ping, .jitter");
  const ping = document.querySelector("#ping");
  const jitter = document.querySelector("#jitter");
  const shareResults = document.querySelector("#share-results");
  const copyLink = document.querySelector("#copy-link");
  const resultsImage = document.querySelector("#results");

  const buttonTexts = {
    [INITIALIZING]: "Loading...",
    [READY]: "Let's start baby",
    [RUNNING]: "Abort",
    [FINISHED]: "Restart",
  };

  // Show copy link button only if navigator.clipboard is available
  copyLink.classList.toggle("hidden", !navigator.clipboard);

  function renderUI() {
    // Make the main button reflect the current state
    startButton.textContent = buttonTexts[testState.state];
    startButton.classList.toggle("disabled", testState.state === INITIALIZING);
    startButton.classList.toggle("active", testState.state === RUNNING);

    // Disable the server selector while test is running
    serverSelector.classList.toggle("disabled", testState.state === RUNNING);

    // Show selected server
    if (testState.selectedServerDirty) {
      const server = testState.speedtest.getSelectedServer();
      selectedServer.textContent = server.name;
      if (server.sponsorName) {
        if (server.sponsorURL) {
          sponsor.innerHTML = `Sponsor: <a href="${server.sponsorURL}">${server.sponsorName}</a>`;
        } else {
          sponsor.textContent = `Sponsor: ${server.sponsorName}`;
        }
      } else {
        sponsor.innerHTML = "&nbsp;";
      }
      testState.selectedServerDirty = false;
    }

    // Activate the gauges when test running or finished
    gauges.forEach((e) =>
      e.classList.toggle(
        "enabled",
        testState.state === RUNNING || testState.state === FINISHED
      )
    );

    // Show ping and jitter if data is available
    pingAndJitter.forEach((e) =>
      e.classList.toggle(
        "hidden",
        !(
          testState.testData &&
          testState.testData.pingStatus &&
          testState.testData.jitterStatus
        )
      )
    );

    // Show share button after test if server supports it
    shareResults.classList.toggle(
      "hidden",
      !(
        testState.state === FINISHED &&
        testState.telemetryEnabled &&
        testState.testData.testId
      )
    );

    if (testState.testDataDirty) {
      // Set gauge rotations
      downloadProgress.style = `--progress-rotation: ${
        testState.testData.dlProgress * 180
      }deg`;
      uploadProgress.style = `--progress-rotation: ${
        testState.testData.ulProgress * 180
      }deg`;
      downloadGauge.style = `--speed-rotation: ${mbpsToRotation(
        testState.testData.dlStatus,
        testState.testData.testState === 1
      )}deg`;
      uploadGauge.style = `--speed-rotation: ${mbpsToRotation(
        testState.testData.ulStatus,
        testState.testData.testState === 3
      )}deg`;

      // Set numeric values
      downloadText.textContent = numberToText(testState.testData.dlStatus);
      uploadText.textContent = numberToText(testState.testData.ulStatus);
      ping.textContent = numberToText(testState.testData.pingStatus);
      jitter.textContent = numberToText(testState.testData.jitterStatus);

      // Set user's IP and provider
      if (testState.testData.clientIp) {
        privacyWarning.innerHTML = `<span>You are connected through:</span><br/>${testState.testData.clientIp}`;
        privacyWarning.classList.remove("hidden");
      }

      // Set image for sharing results
      if (testState.testData.testId) {
        resultsImage.src =
          window.location.href.substring(
            0,
            window.location.href.lastIndexOf("/")
          ) +
          "/results/?id=" +
          testState.testData.testId;
      }

      testState.testDataDirty = false;
    }

    requestAnimationFrame(renderUI);
  }

  renderUI();
}

/**
 * Convert a speed in Mbits per second to a rotation for the gauge
 * @param {string} speed Speed in Mbits
 * @param {boolean} oscillate If the gauge should wiggle a bit
 * @returns {number} Rotation for the gauge in degrees
 */
function mbpsToRotation(speed, oscillate) {
  speed = Number(speed);
  if (speed <= 0) return 0;

  const minSpeed = 0;
  const maxSpeed = 10000; // 10 Gbps maxes out the gauge
  const minRotation = 0;
  const maxRotation = 180;

  // Can't do log10 of values less than one, +1 all to keep it fair
  const logMinSpeed = Math.log10(minSpeed + 1);
  const logMaxSpeed = Math.log10(maxSpeed + 1);
  const logSpeed = Math.log10(speed + 1);

  const power = (logSpeed - logMinSpeed) / (logMaxSpeed - logMinSpeed);
  const oscillation = oscillate ? 1 + 0.01 * Math.sin(Date.now() / 100) : 1;
  const rotation = power * oscillation * maxRotation;

  // Make sure we stay within bounds at all times
  return Math.max(Math.min(rotation, maxRotation), minRotation);
}

/**
 * Convert a number to a user friendly version
 * @param {string} value Speed, ping or jitter
 * @returns {string} A text version with proper decimals
 */
function numberToText(value) {
  if (!value) return "00";
  value = Number(value);
  if (value < 10) return value.toFixed(2);
  if (value < 100) return value.toFixed(1);
  return value.toFixed(0);
}
