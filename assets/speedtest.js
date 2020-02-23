/*
	LibreSpeed - Main
	by Federico Dossena
	https://github.com/librespeed/speedtest/
	GNU LGPLv3 License
*/

/*
   This is the main interface between your webpage and the speedtest.
   It hides the speedtest web worker to the page, and provides many convenient functions to control the test.
   
   The best way to learn how to use this is to look at the basic example, but here's some documentation.
  
   To initialize the test, create a new Speedtest object:
    var s=new Speedtest();
   Now you can think of this as a finite state machine. These are the states (use getState() to see them):
   - 0: here you can change the speedtest settings (such as test duration) with the setParameter("parameter",value) method. From here you can either start the test using start() (goes to state 3) or you can add multiple test points using addTestPoint(server) or addTestPoints(serverList) (goes to state 1). Additionally, this is the perfect moment to set up callbacks for the onupdate(data) and onend(aborted) events.
   - 1: here you can add test points. You only need to do this if you want to use multiple test points.
        A server is defined as an object like this:
        {
            name: "User friendly name",
            server:"http://yourBackend.com/",     <---- URL to your server. You can specify http:// or https://. If your server supports both, just write // without the protocol
            dlURL:"garbage.php"    <----- path to garbage.php or its replacement on the server
            ulURL:"empty.php"    <----- path to empty.php or its replacement on the server
            pingURL:"empty.php"    <----- path to empty.php or its replacement on the server. This is used to ping the server by this selector
            getIpURL:"getIP.php"    <----- path to getIP.php or its replacement on the server
        }
        While in state 1, you can only add test points, you cannot change the test settings. When you're done, use selectServer(callback) to select the test point with the lowest ping. This is asynchronous, when it's done, it will call your callback function and move to state 2. Calling setSelectedServer(server) will manually select a server and move to state 2.
    - 2: test point selected, ready to start the test. Use start() to begin, this will move to state 3
    - 3: test running. Here, your onupdate event calback will be called periodically, with data coming from the worker about speed and progress. A data object will be passed to your onupdate function, with the following items:
            - dlStatus: download speed in mbps
            - ulStatus: upload speed in mbps
            - pingStatus: ping in ms
            - jitterStatus: jitter in ms
            - dlProgress: progress of the download test as a float 0-1
            - ulProgress: progress of the upload test as a float 0-1
            - pingProgress: progress of the ping/jitter test as a float 0-1
            - testState: state of the test (-1=not started, 0=starting, 1=download test, 2=ping+jitter test, 3=upload test, 4=finished, 5=aborted)
            - clientIp: IP address of the client performing the test (and optionally ISP and distance) 
        At the end of the test, the onend function will be called, with a boolean specifying whether the test was aborted or if it ended normally.
        The test can be aborted at any time with abort().
        At the end of the test, it will move to state 4
    - 4: test finished. You can run it again by calling start() if you want.
 */

function Speedtest() {
  this._serverList = []; //when using multiple points of test, this is a list of test points
  this._selectedServer = null; //when using multiple points of test, this is the selected server
  this._settings = {}; //settings for the speedtest worker
  this._state = 0; //0=adding settings, 1=adding servers, 2=server selection done, 3=test running, 4=done
  console.log(
    "LibreSpeed by Federico Dossena v5.1 - https://github.com/librespeed/speedtest"
  );
}

Speedtest.prototype = {
  constructor: Speedtest,
  /**
   * Returns the state of the test: 0=adding settings, 1=adding servers, 2=server selection done, 3=test running, 4=done
   */
  getState: function() {
    return this._state;
  },
  /**
   * Change one of the test settings from their defaults.
   * - parameter: string with the name of the parameter that you want to set
   * - value: new value for the parameter
   *
   * Invalid values or nonexistant parameters will be ignored by the speedtest worker.
   */
  setParameter: function(parameter, value) {
    if (this._state != 0)
      throw "You cannot change the test settings after adding server or starting the test";
    this._settings[parameter] = value;
    if(parameter === "temeletry_extra"){
        this._originalExtra=this._settings.telemetry_extra;
    }
  },
  /**
   * Used internally to check if a server object contains all the required elements.
   * Also fixes the server URL if needed.
   */
  _checkServerDefinition: function(server) {
    try {
      if (typeof server.name !== "string")
        throw "Name string missing from server definition (name)";
      if (typeof server.server !== "string")
        throw "Server address string missing from server definition (server)";
      if (server.server.charAt(server.server.length - 1) != "/")
        server.server += "/";
      if (server.server.indexOf("//") == 0)
        server.server = location.protocol + server.server;
      if (typeof server.dlURL !== "string")
        throw "Download URL string missing from server definition (dlURL)";
      if (typeof server.ulURL !== "string")
        throw "Upload URL string missing from server definition (ulURL)";
      if (typeof server.pingURL !== "string")
        throw "Ping URL string missing from server definition (pingURL)";
      if (typeof server.getIpURL !== "string")
        throw "GetIP URL string missing from server definition (getIpURL)";
    } catch (e) {
      throw "Invalid server definition";
    }
  },
  /**
   * Add a test point (multiple points of test)
   * server: the server to be added as an object. Must contain the following elements:
   *  {
   *       name: "User friendly name",
   *       server:"http://yourBackend.com/",   URL to your server. You can specify http:// or https://. If your server supports both, just write // without the protocol
   *       dlURL:"garbage.php"   path to garbage.php or its replacement on the server
   *       ulURL:"empty.php"   path to empty.php or its replacement on the server
   *       pingURL:"empty.php"   path to empty.php or its replacement on the server. This is used to ping the server by this selector
   *       getIpURL:"getIP.php"   path to getIP.php or its replacement on the server
   *   }
   */
  addTestPoint: function(server) {
    this._checkServerDefinition(server);
    if (this._state == 0) this._state = 1;
    if (this._state != 1) throw "You can't add a server after server selection";
    this._settings.mpot = true;
    this._serverList.push(server);
  },
  /**
   * Same as addTestPoint, but you can pass an array of servers
   */
  addTestPoints: function(list) {
    for (var i = 0; i < list.length; i++) this.addTestPoint(list[i]);
  },
  /**
   * Returns the selected server (multiple points of test)
   */
  getSelectedServer: function() {
    if (this._state < 2 || this._selectedServer == null)
      throw "No server is selected";
    return this._selectedServer;
  },
  /**
   * Manually selects one of the test points (multiple points of test)
   */
  setSelectedServer: function(server) {
    this._checkServerDefinition(server);
    if (this._state == 3)
      throw "You can't select a server while the test is running";
    this._selectedServer = server;
    this._state = 2;
  },
  /**
   * Automatically selects a server from the list of added test points. The server with the lowest ping will be chosen. (multiple points of test)
   * The process is asynchronous and the passed result callback function will be called when it's done, then the test can be started.
   */
  selectServer: function(result) {
    if (this._state != 1) {
      if (this._state == 0) throw "No test points added";
      if (this._state == 2) throw "Server already selected";
      if (this._state >= 3)
        throw "You can't select a server while the test is running";
    }
    if (this._selectServerCalled) throw "selectServer already called"; else this._selectServerCalled=true;
    /*this function goes through a list of servers. For each server, the ping is measured, then the server with the function result is called with the best server, or null if all the servers were down.
     */
    var select = function(serverList, result) {
      //pings the specified URL, then calls the function result. Result will receive a parameter which is either the time it took to ping the URL, or -1 if something went wrong.
      var PING_TIMEOUT = 2000;
      var USE_PING_TIMEOUT = true; //will be disabled on unsupported browsers
      if (/MSIE.(\d+\.\d+)/i.test(navigator.userAgent)) {
        //IE11 doesn't support XHR timeout
        USE_PING_TIMEOUT = false;
      }
      var ping = function(url, result) {
        url += (url.match(/\?/) ? "&" : "?") + "cors=true";
        var xhr = new XMLHttpRequest();
        var t = new Date().getTime();
        xhr.onload = function() {
          if (xhr.responseText.length == 0) {
            //we expect an empty response
            var instspd = new Date().getTime() - t; //rough timing estimate
            try {
              //try to get more accurate timing using performance API
              var p = performance.getEntriesByName(url);
              p = p[p.length - 1];
              var d = p.responseStart - p.requestStart;
              if (d <= 0) d = p.duration;
              if (d > 0 && d < instspd) instspd = d;
            } catch (e) {}
            result(instspd);
          } else result(-1);
        }.bind(this);
        xhr.onerror = function() {
          result(-1);
        }.bind(this);
        xhr.open("GET", url);
        if (USE_PING_TIMEOUT) {
          try {
            xhr.timeout = PING_TIMEOUT;
            xhr.ontimeout = xhr.onerror;
          } catch (e) {}
        }
        xhr.send();
      }.bind(this);

      //this function repeatedly pings a server to get a good estimate of the ping. When it's done, it calls the done function without parameters. At the end of the execution, the server will have a new parameter called pingT, which is either the best ping we got from the server or -1 if something went wrong.
      var PINGS = 3, //up to 3 pings are performed, unless the server is down...
        SLOW_THRESHOLD = 500; //...or one of the pings is above this threshold
      var checkServer = function(server, done) {
        var i = 0;
        server.pingT = -1;
        if (server.server.indexOf(location.protocol) == -1) done();
        else {
          var nextPing = function() {
            if (i++ == PINGS) {
              done();
              return;
            }
            ping(
              server.server + server.pingURL,
              function(t) {
                if (t >= 0) {
                  if (t < server.pingT || server.pingT == -1) server.pingT = t;
                  if (t < SLOW_THRESHOLD) nextPing();
                  else done();
                } else done();
              }.bind(this)
            );
          }.bind(this);
          nextPing();
        }
      }.bind(this);
      //check servers in list, one by one
      var i = 0;
      var done = function() {
        var bestServer = null;
        for (var i = 0; i < serverList.length; i++) {
          if (
            serverList[i].pingT != -1 &&
            (bestServer == null || serverList[i].pingT < bestServer.pingT)
          )
            bestServer = serverList[i];
        }
        result(bestServer);
      }.bind(this);
      var nextServer = function() {
        if (i == serverList.length) {
          done();
          return;
        }
        checkServer(serverList[i++], nextServer);
      }.bind(this);
      nextServer();
    }.bind(this);

    //parallel server selection
    var CONCURRENCY = 6;
    var serverLists = [];
    for (var i = 0; i < CONCURRENCY; i++) {
      serverLists[i] = [];
    }
    for (var i = 0; i < this._serverList.length; i++) {
      serverLists[i % CONCURRENCY].push(this._serverList[i]);
    }
    var completed = 0;
    var bestServer = null;
    for (var i = 0; i < CONCURRENCY; i++) {
      select(
        serverLists[i],
        function(server) {
          if (server != null) {
            if (bestServer == null || server.pingT < bestServer.pingT)
              bestServer = server;
          }
          completed++;
          if (completed == CONCURRENCY) {
            this._selectedServer = bestServer;
            this._state = 2;
            if (result) result(bestServer);
          }
        }.bind(this)
      );
    }
  },
  /**
   * Starts the test.
   * During the test, the onupdate(data) callback function will be called periodically with data from the worker.
   * At the end of the test, the onend(aborted) function will be called with a boolean telling you if the test was aborted or if it ended normally.
   */
  start: function() {
    if (this._state == 3) throw "Test already running";
    this.worker = new Worker("speedtest_worker.js?r=" + Math.random());
    this.worker.onmessage = function(e) {
      if (e.data === this._prevData) return;
      else this._prevData = e.data;
      var data = JSON.parse(e.data);
      try {
        if (this.onupdate) this.onupdate(data);
      } catch (e) {
        console.error("Speedtest onupdate event threw exception: " + e);
      }
      if (data.testState >= 4) {
        try {
          if (this.onend) this.onend(data.testState == 5);
        } catch (e) {
          console.error("Speedtest onend event threw exception: " + e);
        }
        clearInterval(this.updater);
        this._state = 4;
      }
    }.bind(this);
    this.updater = setInterval(
      function() {
        this.worker.postMessage("status");
      }.bind(this),
      200
    );
    if (this._state == 1)
        throw "When using multiple points of test, you must call selectServer before starting the test";
    if (this._state == 2) {
      this._settings.url_dl =
        this._selectedServer.server + this._selectedServer.dlURL;
      this._settings.url_ul =
        this._selectedServer.server + this._selectedServer.ulURL;
      this._settings.url_ping =
        this._selectedServer.server + this._selectedServer.pingURL;
      this._settings.url_getIp =
        this._selectedServer.server + this._selectedServer.getIpURL;
      if (typeof this._originalExtra !== "undefined") {
        this._settings.telemetry_extra = JSON.stringify({
          server: this._selectedServer.name,
          extra: this._originalExtra
        });
      } else
        this._settings.telemetry_extra = JSON.stringify({
          server: this._selectedServer.name
        });
    }
    this._state = 3;
    this.worker.postMessage("start " + JSON.stringify(this._settings));
  },
  /**
   * Aborts the test while it's running.
   */
  abort: function() {
    if (this._state < 3) throw "You cannot abort a test that's not started yet";
    if (this._state < 4) this.worker.postMessage("abort");
  }
};
