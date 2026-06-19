/**
 * e2e-flow: auth setup
 * intent: log in as admin@example.com and capture storageState for authenticated specs
 * steps: navigate /login → fill email + password → submit → assert /dashboard → save storageState
 *
 * Note: the login page renders in English by default (locale resolves from session after login).
 * Labels on the login form are not associated via <label for="...">, so inputs are
 * located by type attribute.
 */
import { test as setup, expect } from '@playwright/test'

const authFile = 'playwright/.auth/user.json'

setup('authenticate', async ({ page }) => {
  await page.goto('/login')

  await page.locator('input[type="email"]').fill('admin@example.com')
  await page.locator('input[type="password"]').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await page.waitForURL(/\/dashboard$/)
  await expect(page).toHaveURL(/\/dashboard$/)

  await page.context().storageState({ path: authFile })
})
