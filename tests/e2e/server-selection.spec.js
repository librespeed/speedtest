const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const speedtestSource = fs.readFileSync(
  path.resolve(__dirname, '../../speedtest.js'),
  'utf8'
);

function createHarness(protocol, responders = {}) {
  const requests = [];

  function FakeXMLHttpRequest() {
    this.responseText = '';
    this.timeout = 0;
  }

  FakeXMLHttpRequest.prototype.open = function open(method, url) {
    this._method = method;
    this._url = url;
  };

  FakeXMLHttpRequest.prototype.send = function send() {
    requests.push(this._url);
    const baseUrl = this._url.replace(/[?&]cors=true$/, '');
    const outcome = responders[baseUrl] || 'error';

    if (outcome === 'success') {
      if (typeof this.onload === 'function') {
        this.onload();
      }
      return;
    }

    if (typeof this.onerror === 'function') {
      this.onerror(new Error(`Request failed for ${baseUrl}`));
    }
  };

  const context = {
    console: { log() {} },
    Date,
    location: { protocol },
    navigator: { userAgent: 'Playwright' },
    performance: { getEntriesByName: () => [] },
    XMLHttpRequest: FakeXMLHttpRequest
  };

  vm.createContext(context);
  vm.runInContext(speedtestSource, context, { filename: 'speedtest.js' });

  return { Speedtest: context.Speedtest, requests };
}

function createServer(server) {
  return {
    name: server,
    server,
    dlURL: 'garbage.php',
    ulURL: 'empty.php',
    pingURL: 'empty.php',
    getIpURL: 'getIP.php'
  };
}

function selectServer(speedtest) {
  return new Promise((resolve) => {
    speedtest.selectServer(resolve);
  });
}

test.describe('Speedtest server selection protocol handling', () => {
  test('allows HTTPS backends to be pinged from an HTTP frontend', async () => {
    const secureServer = 'https://secure.example/';
    const { Speedtest, requests } = createHarness('http:', {
      [`${secureServer}empty.php`]: 'success'
    });

    const speedtest = new Speedtest();
    speedtest.addTestPoint(createServer(secureServer));

    const selectedServer = await selectServer(speedtest);

    expect(selectedServer.server).toBe(secureServer);
    expect(
      requests.some((url) => url.startsWith(`${secureServer}empty.php`))
    ).toBeTruthy();
  });

  test('still skips insecure HTTP backends from an HTTPS frontend', async () => {
    const insecureServer = 'http://insecure.example/';
    const secureServer = 'https://secure.example/';
    const { Speedtest, requests } = createHarness('https:', {
      [`${insecureServer}empty.php`]: 'success',
      [`${secureServer}empty.php`]: 'success'
    });

    const speedtest = new Speedtest();
    speedtest.addTestPoint(createServer(insecureServer));
    speedtest.addTestPoint(createServer(secureServer));

    const selectedServer = await selectServer(speedtest);

    expect(selectedServer.server).toBe(secureServer);
    expect(
      requests.some((url) => url.startsWith(`${insecureServer}empty.php`))
    ).toBeFalsy();
    expect(
      requests.some((url) => url.startsWith(`${secureServer}empty.php`))
    ).toBeTruthy();
  });
});
