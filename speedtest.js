/**
 * @file LibreSpeed - Main
 * @author Federico Dossena
 * @license LGPL-3.0-only
 * https://github.com/librespeed/speedtest/
 */

/**
 * This is the main interface between your webpage and the speedtest.
 * It hides the speedtest web worker to the page, and provides many convenient functions to control the test.
 *
 * The best way to learn how to use this is to look at the basic example, but here's some documentation.
 *
 * - To initialize the test, create a new Speedtest object: `const s = new Speedtest();`.
 *
 * You can think of this as a finite state machine. These are the states (use getState() to see them):
 * - __0__: here you can change the speedtest settings (such as test duration) with the setParameter("parameter",value)
 *     method. From here you can either start the test using start() (goes to state 3) or you can add multiple test
 *     points using addTestPoint(server) or addTestPoints(serverList) (goes to state 1). Additionally, this is the
 *     perfect moment to set up callbacks for the onupdate(data) and onend(aborted) events.
 * - __1__: here you can add test points. You only need to do this if you want to use multiple test points.
 *     A server is defined as an object like this:
 *     ```
 *     {
 *       name: "User friendly name",
 *       server: "http://yourBackend.com/", // server URL. If both http & https are supported, just use // without protocol
 *       dlURL: "garbage.php", // path to garbage.php or its replacement on the server
 *       ulURL: "empty.php", // path to empty.php or its replacement on the server
 *       pingURL: "empty.php", // path to empty.php or its replacement on the server
 *       getIpURL: "getIP.php", // path to getIP.php or its replacement on the server
 *     }
 *     ```
 *     While in state 1, you can only add test points, you cannot change the test settings. When you're done, use
 *     selectServer(callback) to select the test point with the lowest ping. This is asynchronous, when it's done,
 *     it will call your callback function and move to state 2. Calling setSelectedServer(server) will manually
 *     select a server and move to state 2.
 * - __2__: test point selected, ready to start the test. Use start() to begin, this will move to state 3.
 * - __3__: test running. Here, your onupdate event calback will be called periodically, with data coming
 *     from the worker about speed and progress. A data object will be passed to your onupdate function,
 *     with the following items:
 *     ```
 *     {
 *       dlStatus: number, // download speed in mbps
 *       ulStatus: number, // upload speed in mbps
 *       pingStatus: number, // ping in ms
 *       jitterStatus: number, // jitter in ms
 *       dlProgress: number, // progress of the download test as a float 0-1
 *       ulProgress: number, // progress of the upload test as a float 0-1
 *       pingProgress: number, // progress of the ping/jitter test as a float 0-1
 *       testState: number, // -1: not started, 0: starting, 1: download, 2: ping+jitter, 3: upload, 4: finished, 5: aborted
 *       clientIp: string, // IP address of the client performing the test (and optionally ISP and distance)
 *     }
 *     ```
 *     At the end of the test, the onend function will be called, with a boolean specifying whether the test was
 *     aborted or if it ended normally. The test can be aborted at any time with abort().
 *     At the end of the test, it will move to state 4.
 * - __4__: test finished. You can run it again by calling start() if you want.
 */
class Speedtest {
  constructor() {
    this._serverList = []; //when using multiple points of test, this is a list of test points
    this._selectedServer = null; //when using multiple points of test, this is the selected server
    this._settings = {}; //settings for the speedtest worker
    this._state = 0; //0=adding settings, 1=adding servers, 2=server selection done, 3=test running, 4=done
    this.onupdate = undefined;
    this.onend = undefined;
    console.log("LibreSpeed by Federico Dossena v5.2 - https://github.com/librespeed/speedtest");
  }

  /**
   * Returns the state of the test: 0=adding settings, 1=adding servers, 2=server selection done, 3=test running, 4=done
   * @returns {number} 0=adding settings, 1=adding servers, 2=server selection done, 3=test running, 4=done
   */
  getState() {
    return this._state;
  }

  /**
   * Change one of the test settings from their defaults.
   * Invalid values or nonexistant parameters will be ignored by the speedtest worker.
   * @param {string} parameter - string with the name of the parameter that you want to set
   * @param value - new value for the parameter
   */
  setParameter(parameter, value) {
    if (this._state !== 0)
      throw new Error("You cannot change the test settings after adding server or starting the test");
    this._settings[parameter] = value;
    if (parameter === "telemetry_extra") {
      this._originalExtra = this._settings.telemetry_extra;
    }
  }

  /**
   * Used internally to check if a server object contains all the required elements.
   * Also fixes the server URL if needed.
   * @param {Server} server
   */
  _checkServerDefinition(server) {
    try {
      if (typeof server.name !== "string")
        throw new Error("Name string missing from server definition (name)");
      if (typeof server.server !== "string")
        throw new Error("Server address string missing from server definition (server)");
      if (server.server.charAt(server.server.length - 1) !== "/")
        server.server += "/";
      if (server.server.indexOf("//") === 0)
        server.server = location.protocol + server.server;
      if (typeof server.dlURL !== "string")
        throw new Error("Download URL string missing from server definition (dlURL)");
      if (typeof server.ulURL !== "string")
        throw new Error("Upload URL string missing from server definition (ulURL)");
      if (typeof server.pingURL !== "string")
        throw new Error("Ping URL string missing from server definition (pingURL)");
      if (typeof server.getIpURL !== "string")
        throw new Error("GetIP URL string missing from server definition (getIpURL)");
    } catch (e) {
      throw new Error(`Invalid server definition: ${e.message}`);
    }
  }

  /**
   * Add a test point (multiple points of test)
   * @param {Server} server - the server to be added as an object. Must contain the following elements:
   * ```
   * name: "User friendly name"
   * server: "http://yourBackend.com/" // server URL. If both http & https are supported, just use // without protocol
   * dlURL: "garbage.php" // path to garbage.php or its replacement on the server
   * ulURL: "empty.php" // path to empty.php or its replacement on the server
   * pingURL: "empty.php" // path to empty.php or its replacement on the server
   * getIpURL: "getIP.php" // path to getIP.php or its replacement on the server
   * ```
   */
  addTestPoint(server) {
    this._checkServerDefinition(server);
    if (this._state === 0) this._state = 1;
    if (this._state !== 1) throw new Error("You can't add a server after server selection");
    this._settings.mpot = true;
    this._serverList.push(server);
  }

  /**
   * Same as `addTestPoint`, but you can pass an array of servers
   * @param {Server[]} list - array of server objects
   */
  addTestPoints(list) {
    for (let i = 0; i < list.length; i++) {
      this.addTestPoint(list[i]);
    }
  }

  /**
   * Load a JSON server list from URL (multiple points of test)
   * @param {string} url - the url where the server list can be fetched.
   * Must be an array with objects containing the following elements:
   * ```
   * name: "User friendly name",
   * server: "http://yourBackend.com/", // server URL. If both http & https are supported, just use // without protocol
   * dlURL: "garbage.php", // path to garbage.php or its replacement on the server
   * ulURL: "empty.php", // path to empty.php or its replacement on the server
   * pingURL: "empty.php", // path to empty.php or its replacement on the server
   * getIpURL: "getIP.php", // path to getIP.php or its replacement on the server
   * ```
   * @param {(x: Server[] | null) => void} result - callback to be called when the list is loaded correctly.
   * An array with the loaded servers will be passed to this function, or null if it failed.
   */
  loadServerList(url, result) {
    if (this._state === 0) this._state = 1;
    if (this._state !== 1) throw new Error("You can't add a server after server selection");
    this._settings.mpot = true;
    const xhr = new XMLHttpRequest();
    xhr.onload = () => {
      try {
        /** @type {Server[]} */
        const servers = JSON.parse(xhr.responseText);
        for (let i = 0; i < servers.length; i++){
          this._checkServerDefinition(servers[i]);
        }
        this.addTestPoints(servers);
        result(servers);
      } catch (e) {
        result(null);
      }
    };
    xhr.onerror = () => { result(null); };
    xhr.open("GET", url);
    xhr.send();
  }

  /**
   * Returns the selected server (multiple points of test)
   */
  getSelectedServer() {
    if (this._state < 2 || this._selectedServer == null) throw new Error("No server is selected");
    return this._selectedServer;
  }

  /**
   * @typedef {Object} Server
   * @property {string} name user friendly name
   * @property {string} server URL to your server. You can specify http:// or https://. If your server supports both, just write // without the protocol
   * @property {string} dlURL path to __garbage.php__ or its replacement on the server
   * @property {string} ulURL path to __empty.php__ or its replacement on the server
   * @property {string} pingURL path to __empty.php__ or its replacement on the server. This is used to ping the server by this selector
   * @property {string} getIpURL path to __getIP.php__ or its replacement on the server
   * @property {number} pingT calculated (do not set). either the best ping we got from the server or -1 if something went wrong.
   */
  /**
   * Manually selects one of the test points (multiple points of test)
   * @param {Server} server
   */
  setSelectedServer(server) {
    this._checkServerDefinition(server);
    if (this._state === 3) throw new Error("You can't select a server while the test is running");
    this._selectedServer = server;
    this._state = 2;
  }

  /**
   * Automatically selects a server from the list of added test points.
   * The server with the lowest ping will be chosen (multiple points of test).
   * The process is asynchronous and the passed result callback function will
   * be called when it's done, then the test can be started.
   * @param {(x: Server) => void} result
   */
  selectServer(result) {
    if (this._state !== 1) {
      if (this._state === 0) throw new Error("No test points added");
      if (this._state === 2) throw new Error("Server already selected");
      if (this._state >= 3) throw new Error("You can't select a server while the test is running");
    }
    if (this._selectServerCalled) throw new Error("selectServer already called");
    else this._selectServerCalled = true;
    /**
     * This function goes through a list of servers. For each server, the ping is measured,
     * then the server with the function result is called with the best server,
     * or null if all the servers were down.
     * @param {Server[]} serverList
     * @param {(x: Server | null) => void} result parameter is either the best server or null if all servers were down
     */
    const select = (serverList, result) => {
      const PING_TIMEOUT = 2000;
      // will be disabled on unsupported browsers
      let USE_PING_TIMEOUT = true;
      if (/MSIE.(\d+\.\d+)/i.test(navigator.userAgent)) {
        // IE11 doesn't support XHR timeout
        USE_PING_TIMEOUT = false;
      }
      /**
       * Pings the specified URL, then calls the function result. Result will receive a parameter
       * which is either the time it took to ping the URL, or -1 if something went wrong.
       * @param {string} url
       * @param {(pingMs: number) => void} result parameter is either the time it took to ping the URL, or -1 if something went wrong
       */
      const ping = (url, result) => {
        url += (url.match(/\?/) ? "&" : "?") + "cors=true";
        const xhr = new XMLHttpRequest();
        const t = new Date().getTime();
        xhr.onload = () => {
          // We expect an empty response
          if (xhr.responseText.length === 0) {
            // Rough timing estimate
            let instspd = new Date().getTime() - t;
            try {
              // Try to get more accurate timing using Performance API
              const pArr = performance.getEntriesByName(url);
              const p = pArr[pArr.length - 1];
              let d = p.responseStart - p.requestStart;
              if (d <= 0) d = p.duration;
              if (d > 0 && d < instspd) instspd = d;
            } catch (e) {}
            result(instspd);
          } else {
            result(-1);
          }
        };
        xhr.onerror = () => { result(-1); };
        xhr.open("GET", url);
        if (USE_PING_TIMEOUT) {
          try {
            xhr.timeout = PING_TIMEOUT;
            xhr.ontimeout = xhr.onerror;
          } catch (e) {}
        }
        xhr.send();
      };
      const PINGS = 3; // up to 3 pings are performed, unless the server is down...
      const SLOW_THRESHOLD = 500; // ...or one of the pings is above this threshold
      /**
       * This function repeatedly pings a server to get a good estimate of the ping.
       * When it's done, it calls the done function without parameters.
       * At the end of the execution, the server will have a new parameter called pingT,
       * which is either the best ping we got from the server or -1 if something went wrong.
       * @param {Server} server
       * @param {() => void} done
       */
      const checkServer = (server, done) => {
        let i = 0;
        server.pingT = -1;
        if (server.server.indexOf(location.protocol) === -1) return void done();
        const nextPing = () => {
          if (i++ === PINGS) return void done();
          ping(
            server.server + server.pingURL,
            (t) => {
              if (t >= 0) {
                if (t < server.pingT || server.pingT === -1) server.pingT = t;
                if (t < SLOW_THRESHOLD) nextPing();
                else done();
              } else {
                done();
              }
            }
          );
        };
        nextPing();
      };
      let index = 0;
      /**
       * Check servers in list, one by one
       */
      const done = () => {
        let bestServer = null;
        for (let i = 0; i < serverList.length; i++) {
          if (serverList[i].pingT !== -1 && (!bestServer || serverList[i].pingT < bestServer.pingT)) {
            bestServer = serverList[i];
          }
          index++;
        }
        result(bestServer);
      };
      const nextServer = () => {
        if (index === serverList.length) return void done();
        checkServer(serverList[index++], nextServer);
      };
      nextServer();
    };
    // Parallel server selection
    const CONCURRENCY = 6;
    const serverLists = [];
    for (let i = 0; i < CONCURRENCY; i++) {
      serverLists[i] = [];
    }
    for (let i = 0; i < this._serverList.length; i++) {
      serverLists[i % CONCURRENCY].push(this._serverList[i]);
    }
    let completed = 0;
    /** @type {Server} */
    let bestServer = null;
    for (let i = 0; i < CONCURRENCY; i++) {
      select(
        serverLists[i],
        (server) => {
          if (server && (!bestServer || server.pingT < bestServer.pingT)) {
            bestServer = server;
          }
          completed++;
          if (completed === CONCURRENCY) {
            this._selectedServer = bestServer;
            this._state = 2;
            if (result) result(bestServer);
          }
        }
      );
    }
  }

  /**
   * Starts the test.
   * During the test, the onupdate(data) callback function will be called periodically
   * with data from the worker. At the end of the test, the onend(aborted) function will
   * be called with a boolean telling you if the test was aborted or if it ended normally.
   */
  start() {
    if (this._state === 3) throw new Error("Test already running");
    this.worker = new Worker("speedtest_worker.js?r=" + Math.random());
    this.worker.onmessage = (e) => {
      if (e.data === this._prevData) return;
      this._prevData = e.data;
      const data = JSON.parse(e.data);
      try {
        if (this.onupdate) this.onupdate(data);
      } catch (e) {
        console.error("Speedtest onupdate event threw exception: " + e);
      }
      if (data.testState >= 4) {
        try {
          if (this.onend) this.onend(data.testState === 5);
        } catch (e) {
          console.error("Speedtest onend event threw exception: " + e);
        }
        clearInterval(this.updater);
        this._state = 4;
      }
    };
    this.updater = setInterval(() => { this.worker.postMessage("status"); }, 200);
    if (this._state === 1) {
      throw new Error("When using multiple points of test, you must call selectServer before starting the test");
    }
    if (this._state === 2) {
      this._settings.url_dl = this._selectedServer.server + this._selectedServer.dlURL;
      this._settings.url_ul = this._selectedServer.server + this._selectedServer.ulURL;
      this._settings.url_ping = this._selectedServer.server + this._selectedServer.pingURL;
      this._settings.url_getIp = this._selectedServer.server + this._selectedServer.getIpURL;
      this._settings.telemetry_extra = JSON.stringify({
        server: this._selectedServer.name,
        extra: this._originalExtra ? this._originalExtra : undefined
      });
    }
    this._state = 3;
    this.worker.postMessage("start " + JSON.stringify(this._settings));
  }

  /**
   * Aborts the test while it's running.
   */
  abort() {
    if (this._state < 3) throw new Error("You cannot abort a test that's not started yet");
    if (this._state < 4) this.worker.postMessage("abort");
  }
}
