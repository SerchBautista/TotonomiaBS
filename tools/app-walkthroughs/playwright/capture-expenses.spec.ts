import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screensDir = path.join(__dirname, '../assets/screens');
const manifestPath = path.join(__dirname, '../assets/screens-manifest.expenses.json');

async function capturePanel(
  page: import('@playwright/test').Page,
  selector: string,
  outputPath: string,
): Promise<void> {
  const panel = page.locator(selector).first();
  await panel.scrollIntoViewIfNeeded();
  await expect(panel).toBeVisible();
  await page.waitForTimeout(300);
  await panel.screenshot({ path: outputPath });
}

async function waitForAppReady(page: import('@playwright/test').Page): Promise<void> {
  await expect(page.locator('.fa-spinner.fa-spin')).toHaveCount(0, { timeout: 30_000 });
}

test.describe('expenses walkthrough captures', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screensDir, { recursive: true });
  });

  test('captures expenses frames for Remotion', async ({ page }) => {
    const slides: Array<{ file: string; caption: string; durationFrames: number }> = [];

    await page.goto('/user/expenses');
    await page.waitForSelector('app-expense-list, app-section-panel', { timeout: 30_000 });
    await waitForAppReady(page);

    const overviewPath = path.join(screensDir, 'expenses-01-overview.png');
    await page.screenshot({ path: overviewPath, fullPage: false });
    slides.push({
      file: 'expenses-01-overview.png',
      caption: 'Todos tus gastos en un solo lugar',
      durationFrames: 105,
    });

    const listPath = path.join(screensDir, 'expenses-02-list.png');
    await capturePanel(page, 'app-expense-list, app-section-panel', listPath);
    slides.push({
      file: 'expenses-02-list.png',
      caption: 'Consulta y filtra tus movimientos',
      durationFrames: 105,
    });

    const filterBar = page.locator('.expense-filters, app-expense-filters, [data-testid="expense-filters"]');
    if (await filterBar.count()) {
      const filtersPath = path.join(screensDir, 'expenses-03-filters.png');
      await capturePanel(
        page,
        '.expense-filters, app-expense-filters, [data-testid="expense-filters"]',
        filtersPath,
      );
      slides.push({
        file: 'expenses-03-filters.png',
        caption: 'Filtra por fecha, categoría o importe',
        durationFrames: 105,
      });
    }

    await page.waitForSelector('.quick-add-fab', { timeout: 30_000 });
    await page.locator('.quick-add-fab').click();
    await expect(page.locator('.quick-add-panel')).toBeVisible();
    const quickAddPath = path.join(
      screensDir,
      slides.length === 2 ? 'expenses-03-quick-add.png' : 'expenses-04-quick-add.png',
    );
    await page.locator('.quick-add-panel').screenshot({ path: quickAddPath });
    await page.keyboard.press('Escape');
    slides.push({
      file: path.basename(quickAddPath),
      caption: 'Añade un gasto en segundos',
      durationFrames: 120,
    });

    const manifest = {
      id: 'ExpensesWalkthrough',
      compositionId: 'ExpensesWalkthrough',
      title: 'Expenses',
      fps: 30,
      width: 1440,
      height: 900,
      slides,
    };

    fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
    fs.copyFileSync(manifestPath, path.join(__dirname, '../remotion/screens.expenses.json'));
  });
});
