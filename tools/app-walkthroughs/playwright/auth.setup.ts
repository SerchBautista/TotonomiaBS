import { expect, test as setup } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, '.auth/user.json');

setup('authenticate staging user', async ({ page }) => {
  const email = process.env.E2E_EMAIL;
  const password = process.env.E2E_PASSWORD;

  if (!email || !password) {
    throw new Error('Set E2E_EMAIL and E2E_PASSWORD in tools/app-walkthroughs/.env');
  }

  await page.goto('/login');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('form button[type="submit"]').click();

  await page.waitForURL(/\/user\//, { timeout: 30_000 });
  await expect(page.locator('.dashboard-shell, app-expense-list, app-user-dashboard').first()).toBeVisible({
    timeout: 30_000,
  });

  await page.context().storageState({ path: authFile });
});
