// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Seating planner admin UI tests. Runs in a mobile-sized, touch-enabled
 * Chromium so touch-punch patches jQuery UI at load (the condition the real
 * bugs depend on). A tiny static server serves the plugin + fixture.
 */
module.exports = defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'line' : 'list',
  use: {
    baseURL: 'http://localhost:8777',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'mobile-chromium',
      use: {
        ...devices['Pixel 5'], // hasTouch: true, mobile viewport
        browserName: 'chromium',
      },
    },
  ],
  webServer: {
    command: 'node tests/server.js',
    url: 'http://localhost:8777/tests/fixture.html',
    reuseExistingServer: !process.env.CI,
    timeout: 20 * 1000,
  },
});
