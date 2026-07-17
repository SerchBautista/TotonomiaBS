import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { captureLocator, waitForFixedExpensesReady } from './capture-helpers';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screensDir = path.join(__dirname, '../assets/screens');
const manifestPath = path.join(__dirname, '../assets/screens-manifest.fixed-expenses.json');

const SLIDE_DURATION = 105;

test.describe('fixed expenses walkthrough captures', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screensDir, { recursive: true });
  });

  test('captures fixed expenses frames for Remotion', async ({ page }) => {
    const slides: Array<{ file: string; caption: string; durationFrames: number }> = [];

    await page.goto('/user/fixed-expenses', { waitUntil: 'domcontentloaded' });
    await waitForFixedExpensesReady(page);

    const overviewPath = path.join(screensDir, 'fixed-expenses-01-overview.png');
    await captureLocator(page, 'app-fixed-expense-list', overviewPath);
    slides.push({
      file: 'fixed-expenses-01-overview.png',
      caption: 'Controla tus gastos recurrentes',
      durationFrames: SLIDE_DURATION,
    });

    const listPath = path.join(screensDir, 'fixed-expenses-02-list.png');
    await captureLocator(page, '.fixed-expense-stack', listPath);
    slides.push({
      file: 'fixed-expenses-02-list.png',
      caption: 'Consulta montos y próximos vencimientos',
      durationFrames: SLIDE_DURATION,
    });

    await page.locator('app-page-header .btn.primary').click();
    await expect(page.locator('app-fixed-expense-create-modal .modal-panel')).toBeVisible({
      timeout: 10_000,
    });
    await page.waitForTimeout(300);
    const createPath = path.join(screensDir, 'fixed-expenses-03-create.png');
    await page.locator('app-fixed-expense-create-modal .modal-panel').screenshot({ path: createPath });
    await page.keyboard.press('Escape');
    slides.push({
      file: 'fixed-expenses-03-create.png',
      caption: 'Registra suscripciones y pagos fijos',
      durationFrames: SLIDE_DURATION,
    });

    const manifest = {
      id: 'FixedExpensesWalkthrough',
      compositionId: 'FixedExpensesWalkthrough',
      title: 'FixedExpenses',
      fps: 30,
      width: 1440,
      height: 900,
      slides,
    };

    fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
    fs.copyFileSync(manifestPath, path.join(__dirname, '../remotion/screens.fixed-expenses.json'));
  });
});
