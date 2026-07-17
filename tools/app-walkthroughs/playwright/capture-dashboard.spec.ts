import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screensDir = path.join(__dirname, '../assets/screens');
const manifestPath = path.join(__dirname, '../assets/screens-manifest.dashboard.json');

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

test.describe('dashboard walkthrough captures', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screensDir, { recursive: true });
  });

  test('captures dashboard frames for Remotion', async ({ page }) => {
    await page.goto('/user/dashboard');
    await page.waitForSelector('.dashboard-shell', { timeout: 30_000 });
    await page.waitForSelector('app-section-panel', { timeout: 30_000 });
    await expect(page.locator('.fa-spinner.fa-spin')).toHaveCount(0, { timeout: 30_000 });

    const overviewPath = path.join(screensDir, 'dashboard-01-overview.png');
    await page.screenshot({ path: overviewPath, fullPage: false });

    const captures = [
      {
        file: 'dashboard-02-budget.png',
        caption: 'Controla tus presupuestos del mes',
        durationFrames: 105,
        selector: 'app-section-panel:has(app-budget-status-widget)',
      },
      {
        file: 'dashboard-03-breakdown.png',
        caption: 'Distribución por categoría',
        durationFrames: 105,
        selector: 'app-section-panel:has(app-spending-breakdown)',
      },
      {
        file: 'dashboard-04-rhythm.png',
        caption: 'Ritmo de gasto diario',
        durationFrames: 120,
        selector: 'app-section-panel:has(app-spending-rhythm)',
      },
    ] as const;

    const slides = [
      {
        file: 'dashboard-01-overview.png',
        caption: 'Tu panel financiero en un solo lugar',
        durationFrames: 105,
      },
    ];

    for (const capture of captures) {
      const outputPath = path.join(screensDir, capture.file);
      await capturePanel(page, capture.selector, outputPath);
      slides.push({
        file: capture.file,
        caption: capture.caption,
        durationFrames: capture.durationFrames,
      });
    }

    const manifest = {
      id: 'DashboardWalkthrough',
      compositionId: 'DashboardWalkthrough',
      title: 'Dashboard',
      fps: 30,
      width: 1440,
      height: 900,
      slides,
    };

    fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
    fs.copyFileSync(manifestPath, path.join(__dirname, '../remotion/screens.dashboard.json'));
  });
});
