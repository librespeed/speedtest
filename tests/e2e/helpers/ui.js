function modernStartButton(page) {
  return page.locator('#start-button');
}

function classicStartButton(page) {
  return page.locator('#startStopBtn');
}

module.exports = {
  modernStartButton,
  classicStartButton,
};
