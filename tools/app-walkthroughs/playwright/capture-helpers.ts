import { expect, type Page } from '@playwright/test';

/** ~3.5 s per slide @ 30 fps — snappy overview pacing. */
export const OVERVIEW_SLIDE_DURATION = 105;

export async function waitForNoLoaders(page: Page): Promise<void> {
  await expect(page.locator('.fa-spinner.fa-spin')).toHaveCount(0, { timeout: 30_000 });
  await expect(page.locator('app-loading-state, .loading-state-box, .data-table__loading')).toHaveCount(
    0,
    { timeout: 30_000 },
  );
  await expect(page.locator('.loading-row')).toHaveCount(0, { timeout: 30_000 });
}

export async function settlePage(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => undefined);
  await waitForNoLoaders(page);
  await page.waitForTimeout(400);
}

export async function waitForDashboardReady(page: Page): Promise<void> {
  await page.waitForSelector('.dashboard-shell', { timeout: 30_000 });
  await settlePage(page);
  await expect(page.locator('app-budget-status-widget')).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('app-spending-breakdown, app-spending-rhythm').first()).toBeVisible({
    timeout: 30_000,
  });
}

export async function waitForExpenseListReady(page: Page): Promise<void> {
  await page.waitForSelector('app-expense-list', { timeout: 30_000 });
  await settlePage(page);
  await expect(page.locator('table.data-table tbody tr').first()).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('app-summary-hero')).toBeVisible({ timeout: 30_000 });
}

export async function waitForBudgetsReady(page: Page): Promise<void> {
  await page.waitForSelector('app-budget-settings', { timeout: 30_000 });
  await settlePage(page);
  await expect(page.locator('app-budget-general-section')).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('app-budget-category-budgets-section')).toBeVisible({ timeout: 30_000 });
  await expect(
    page.locator('app-budget-general-section .budget-card, app-budget-general-section .amount').first(),
  ).toBeVisible({ timeout: 30_000 });
}

export async function waitForFixedExpensesReady(page: Page): Promise<void> {
  await page.waitForSelector('app-fixed-expense-list', { timeout: 30_000 });
  await settlePage(page);
  await expect(page.locator('table.data-table tbody tr').first()).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('app-summary-hero')).toBeVisible({ timeout: 30_000 });
}

export async function waitForWorkspacesReady(page: Page): Promise<void> {
  await page.waitForSelector('app-workspace-list', { timeout: 30_000 });
  await settlePage(page);

  const upgradeOverlay = page.locator('.upgrade-overlay');
  if (await upgradeOverlay.count()) {
    await page.keyboard.press('Escape');
    await upgradeOverlay.waitFor({ state: 'hidden', timeout: 5_000 }).catch(async () => {
      await page.locator('.upgrade-overlay').click({ position: { x: 8, y: 8 } });
    });
  }

  await expect(page.locator('.workspace-card').first()).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('.workspace-card .ws-name').first()).not.toBeEmpty({ timeout: 30_000 });
}

export async function openQuickAddPanel(page: Page): Promise<void> {
  await page.waitForSelector('.quick-add-fab', { timeout: 30_000 });
  await page.locator('.quick-add-fab').click();
  await expect(page.locator('.quick-add-panel')).toBeVisible({ timeout: 10_000 });
  await expect(page.locator('#qa-category option').nth(0)).toBeAttached({ timeout: 10_000 });
  await page.waitForTimeout(300);
}

export async function captureLocator(
  page: Page,
  selector: string,
  outputPath: string,
): Promise<void> {
  const panel = page.locator(selector).first();
  await panel.scrollIntoViewIfNeeded();
  await expect(panel).toBeVisible();
  await page.waitForTimeout(200);
  await panel.screenshot({ path: outputPath });
}
