import { defineConfig, devices } from '@playwright/test'

// App runs on port 8080 (APP_PORT=80 in .env, but port 80 is occupied by another project on
// this dev machine; Sail containers were started with host port 8080 mapping to container port 80).
// In a clean env with APP_PORT=80, set E2E_BASE_URL=http://localhost instead.
const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8080'

export default defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: false, // sequential required: S1 IBAN persists for S5 QR
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  webServer: {
    // Sail is already running — just verify the server is reachable.
    // reuseExistingServer: true means Playwright uses it without executing the command.
    command: 'echo "dev server expected on $E2E_BASE_URL — start via: ./vendor/bin/sail up -d"',
    url: BASE_URL,
    reuseExistingServer: true,
    timeout: 10_000,
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    {
      name: 'desktop',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
    },
  ],
})
