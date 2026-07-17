import { test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { captureLocator, waitForBudgetsReady } from './capture-helpers';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screensDir = path.join(__dirname, '../assets/screens');
const manifestPath = path.join(__dirname, '../assets/screens-manifest.budgets.json');

const SLIDE_DURATION = 105;

test.describe('budgets walkthrough captures', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screensDir, { recursive: true });
  });

  test('captures budgets frames for Remotion', async ({ page }) => {
    const slides: Array<{ file: string; caption: string; durationFrames: number }> = [];

    await page.goto('/user/settings/budgets', { waitUntil: 'domcontentloaded' });
    await waitForBudgetsReady(page);

    const overviewPath = path.join(screensDir, 'budgets-01-overview.png');
    await captureLocator(page, 'app-budget-settings', overviewPath);
    slides.push({
      file: 'budgets-01-overview.png',
      caption: 'Configura tus presupuestos',
      durationFrames: SLIDE_DURATION,
    });

    const generalPath = path.join(screensDir, 'budgets-02-general.png');
    await captureLocator(page, 'app-budget-general-section', generalPath);
    slides.push({
      file: 'budgets-02-general.png',
      caption: 'Presupuesto general del mes',
      durationFrames: SLIDE_DURATION,
    });

    const categoriesPath = path.join(screensDir, 'budgets-03-categories.png');
    await captureLocator(page, 'app-budget-category-budgets-section', categoriesPath);
    slides.push({
      file: 'budgets-03-categories.png',
      caption: 'Límites por categoría',
      durationFrames: SLIDE_DURATION,
    });

    const manifest = {
      id: 'BudgetsWalkthrough',
      compositionId: 'BudgetsWalkthrough',
      title: 'Budgets',
      fps: 30,
      width: 1440,
      height: 900,
      slides,
    };

    fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
    fs.copyFileSync(manifestPath, path.join(__dirname, '../remotion/screens.budgets.json'));
  });
});
