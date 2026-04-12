const { execSync } = require('node:child_process');

const COMPOSE_FILE = 'tests/docker-compose-playwright.yml';

async function waitForReady(name, url, timeoutMs) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    try {
      const response = await fetch(url, { redirect: 'manual' });
      if (response.ok) {
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
