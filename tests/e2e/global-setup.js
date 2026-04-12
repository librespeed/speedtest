const { execSync } = require('node:child_process');
const http = require('node:http');
const https = require('node:https');

const COMPOSE_FILE = 'tests/docker-compose-playwright.yml';

function isHttpOk(url) {
  return new Promise((resolve, reject) => {
    const client = url.startsWith('https://') ? https : http;
    const req = client.get(url, (res) => {
      const status = res.statusCode || 0;
      // Drain body so sockets can be reused/closed cleanly.
      res.resume();
      resolve(status >= 200 && status < 300);
    });
    req.on('error', reject);
  });
}

async function waitForReady(name, url, timeoutMs) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    try {
      if (await isHttpOk(url)) {
        return;
      }
    } catch {
      // Retry until timeout.
    }
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  throw new Error(`Timed out waiting for ${name} at ${url}`);
}

module.exports = async () => {
  try {
    execSync(`docker compose -f ${COMPOSE_FILE} up -d --build`, {
      stdio: 'inherit',
    });
  } catch (error) {
    throw new Error(
      `Failed to start Docker test stack from ${COMPOSE_FILE}. ` +
        `Ensure Docker is running. Original error: ${error.message}`
    );
  }

  const timeoutMs = 180_000;
  await waitForReady('standalone', 'http://127.0.0.1:18180/index.html', timeoutMs);
  await waitForReady('standalone-new', 'http://127.0.0.1:18185/index.html', timeoutMs);
  await waitForReady('backend', 'http://127.0.0.1:18181/empty.php', timeoutMs);
  await waitForReady('frontend', 'http://127.0.0.1:18182/index-modern.html', timeoutMs);
  await waitForReady('dual', 'http://127.0.0.1:18183/index-modern.html', timeoutMs);
};
