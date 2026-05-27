function modernStartButton(page) {
  return page.locator("#start-button");
}

function classicStartButton(page) {
  return page.locator("#startStopBtn");
}

function stabilityStartButton(page) {
  return page.locator("#startBtn");
}

module.exports = {
  modernStartButton,
  classicStartButton,
  stabilityStartButton
};
