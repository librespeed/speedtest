const fs = require("node:fs");
const path = require("node:path");
const { test, expect } = require("@playwright/test");

const speedtestSource = fs.readFileSync(
  path.join(__dirname, "..", "..", "speedtest.js"),
  "utf8"
);

async function installSpeedtest(page) {
  await page.goto("about:blank");
  await page.evaluate(() => {
    window.__workers = [];
    window.Worker = class FakeWorker {
      constructor(url) {
        this.url = url;
        this.messages = [];
        this.terminated = false;
        this.terminateCount = 0;
        window.__workers.push(this);
      }

      postMessage(message) {
        this.messages.push(message);
      }

      terminate() {
        this.terminated = true;
        this.terminateCount++;
      }

      emit(data) {
        this.onmessage(new MessageEvent("message", { data: JSON.stringify(data) }));
      }
    };
  });
  await page.addScriptTag({ content: speedtestSource });
}

function terminalState(testState) {
  return { testState: testState };
}

test.describe("Speedtest worker lifecycle", () => {
  test.beforeEach(async ({ page }) => {
    await installSpeedtest(page);
  });

  test("terminates a worker after normal completion", async ({ page }) => {
    const result = await page.evaluate((data) => {
      const speedtest = new Speedtest();
      speedtest.start();
      const worker = window.__workers[0];
      worker.emit(data);
      return {
        state: speedtest.getState(),
        worker: speedtest.worker,
        updater: speedtest.updater,
        terminated: worker.terminated,
        terminateCount: worker.terminateCount
      };
    }, terminalState(4));

    expect(result).toEqual({
      state: 4,
      worker: null,
      updater: null,
      terminated: true,
      terminateCount: 1
    });
  });

  test("can start again after normal completion", async ({ page }) => {
    const result = await page.evaluate((data) => {
      const speedtest = new Speedtest();
      speedtest.start();
      const first = window.__workers[0];
      first.emit(data);
      speedtest.start();
      const second = window.__workers[1];
      return {
        state: speedtest.getState(),
        firstTerminated: first.terminated,
        secondTerminated: second.terminated,
        activeWorkerIsSecond: speedtest.worker === second
      };
    }, terminalState(4));

    expect(result).toEqual({
      state: 3,
      firstTerminated: true,
      secondTerminated: false,
      activeWorkerIsSecond: true
    });
  });

  test("can start again after an abort response", async ({ page }) => {
    const result = await page.evaluate((data) => {
      const speedtest = new Speedtest();
      speedtest.start();
      const first = window.__workers[0];
      speedtest.abort();
      first.emit(data);
      speedtest.start();
      const second = window.__workers[1];
      return {
        state: speedtest.getState(),
        firstTerminated: first.terminated,
        secondTerminated: second.terminated,
        activeWorkerIsSecond: speedtest.worker === second
      };
    }, terminalState(5));

    expect(result).toEqual({
      state: 3,
      firstTerminated: true,
      secondTerminated: false,
      activeWorkerIsSecond: true
    });
  });

  test("does not terminate a worker started synchronously by onend", async ({ page }) => {
    const result = await page.evaluate((data) => {
      const speedtest = new Speedtest();
      let endCalls = 0;
      speedtest.onend = () => {
        endCalls++;
        speedtest.start();
      };
      speedtest.start();
      const first = window.__workers[0];
      first.emit(data);
      const second = window.__workers[1];
      return {
        endCalls: endCalls,
        state: speedtest.getState(),
        firstTerminated: first.terminated,
        secondTerminated: second.terminated,
        activeWorkerIsSecond: speedtest.worker === second
      };
    }, terminalState(4));

    expect(result).toEqual({
      endCalls: 1,
      state: 3,
      firstTerminated: true,
      secondTerminated: false,
      activeWorkerIsSecond: true
    });
  });

  test("forces a single aborted completion when a worker does not respond", async ({ page }) => {
    await page.evaluate(() => {
      window.__speedtest = new Speedtest();
      window.__endCalls = 0;
      window.__speedtest.onend = (aborted) => {
        if (aborted) window.__endCalls++;
      };
      window.__speedtest.start();
      window.__speedtest.abort();
    });

    await expect
      .poll(() =>
        page.evaluate(() => ({
          state: window.__speedtest.getState(),
          worker: window.__speedtest.worker,
          updater: window.__speedtest.updater,
          terminated: window.__workers[0].terminated,
          endCalls: window.__endCalls
        }))
      )
      .toEqual({
        state: 4,
        worker: null,
        updater: null,
        terminated: true,
        endCalls: 1
      });

    await page.waitForTimeout(1100);
    expect(await page.evaluate(() => window.__endCalls)).toBe(1);
  });

  test("ignores delayed events and an old abort timeout after a new run starts", async ({ page }) => {
    await page.evaluate((data) => {
      window.__speedtest = new Speedtest();
      window.__endCalls = 0;
      window.__updates = 0;
      window.__speedtest.onupdate = () => window.__updates++;
      window.__speedtest.onend = () => {
        window.__endCalls++;
        window.__speedtest.start();
      };
      window.__speedtest.start();
      window.__first = window.__workers[0];
      window.__speedtest.abort();
      window.__first.emit(data);
      window.__second = window.__workers[1];
    }, terminalState(5));

    await page.waitForTimeout(1100);
    const result = await page.evaluate((data) => {
      window.__first.emit(data);
      return {
        endCalls: window.__endCalls,
        updates: window.__updates,
        state: window.__speedtest.getState(),
        firstTerminated: window.__first.terminated,
        secondTerminated: window.__second.terminated,
        activeWorkerIsSecond: window.__speedtest.worker === window.__second
      };
    }, terminalState(5));

    expect(result).toEqual({
      endCalls: 1,
      updates: 1,
      state: 3,
      firstTerminated: true,
      secondTerminated: false,
      activeWorkerIsSecond: true
    });
  });
});
