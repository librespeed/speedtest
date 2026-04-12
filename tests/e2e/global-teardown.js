const { execSync } = require('node:child_process');

const COMPOSE_FILE = 'tests/docker-compose-playwright.yml';

module.exports = async () => {
  execSync(`docker compose -f ${COMPOSE_FILE} down --remove-orphans`, {
    stdio: 'inherit',
  });
};
