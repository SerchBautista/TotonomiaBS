import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { captureLocator, waitForWorkspacesReady } from './capture-helpers';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screensDir = path.join(__dirname, '../assets/screens');
const manifestPath = path.join(__dirname, '../assets/screens-manifest.workspaces.json');

const SLIDE_DURATION = 105;

test.describe('workspaces walkthrough captures', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screensDir, { recursive: true });
  });

  test('captures workspaces frames for Remotion', async ({ page }) => {
    const slides: Array<{ file: string; caption: string; durationFrames: number }> = [];

    await page.goto('/user/workspaces', { waitUntil: 'domcontentloaded' });
    await waitForWorkspacesReady(page);

    const overviewPath = path.join(screensDir, 'workspaces-01-overview.png');
    await captureLocator(page, 'app-workspace-list', overviewPath);
    slides.push({
      file: 'workspaces-01-overview.png',
      caption: 'Organiza tus finanzas por espacio',
      durationFrames: SLIDE_DURATION,
    });

    const gridPath = path.join(screensDir, 'workspaces-02-grid.png');
    await captureLocator(page, '.workspace-grid', gridPath);
    slides.push({
      file: 'workspaces-02-grid.png',
      caption: 'Personal y compartidos en un vistazo',
      durationFrames: SLIDE_DURATION,
    });

    await page.locator('.workspace-card .btn.secondary').first().click();
    await page.waitForSelector('app-workspace-detail, app-expense-list', { timeout: 30_000 });
    await expect(page.locator('.fa-spinner.fa-spin, app-loading-state, .data-table__loading')).toHaveCount(
      0,
      { timeout: 30_000 },
    );
    await page.waitForTimeout(400);

    const detailPath = path.join(screensDir, 'workspaces-03-detail.png');
    await captureLocator(page, 'app-workspace-detail, app-expense-list', detailPath);
    slides.push({
      file: 'workspaces-03-detail.png',
      caption: 'Cada espacio con sus gastos y categorías',
      durationFrames: SLIDE_DURATION,
    });

    const manifest = {
      id: 'WorkspacesWalkthrough',
      compositionId: 'WorkspacesWalkthrough',
      title: 'Workspaces',
      fps: 30,
      width: 1440,
      height: 900,
      slides,
    };

    fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
    fs.copyFileSync(manifestPath, path.join(__dirname, '../remotion/screens.workspaces.json'));
  });
});
