import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  OVERVIEW_SLIDE_DURATION,
  captureLocator,
  openQuickAddPanel,
  waitForBudgetsReady,
  waitForDashboardReady,
  waitForExpenseListReady,
  waitForFixedExpensesReady,
  waitForWorkspacesReady,
} from './capture-helpers';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screensDir = path.join(__dirname, '../assets/screens');
const manifestPath = path.join(__dirname, '../assets/screens-manifest.overview.json');

type Slide = { file: string; caption: string; durationFrames: number };

test.describe('overview walkthrough captures', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screensDir, { recursive: true });
  });

  test('captures full product tour frames for Remotion', async ({ page }) => {
    const slides: Slide[] = [];
    let index = 0;

    const addSlide = async (
      slug: string,
      caption: string,
      capture: (outputPath: string) => Promise<void>,
      durationFrames = OVERVIEW_SLIDE_DURATION,
    ): Promise<void> => {
      index += 1;
      const file = `overview-${String(index).padStart(2, '0')}-${slug}.png`;
      const outputPath = path.join(screensDir, file);
      await capture(outputPath);
      slides.push({ file, caption, durationFrames });
    };

    // —— Dashboard ——
    await page.goto('/user/dashboard', { waitUntil: 'domcontentloaded' });
    await waitForDashboardReady(page);

    await addSlide('dashboard', 'Tu panel financiero en un solo lugar', (outputPath) =>
      captureLocator(page, '.dashboard-shell', outputPath),
    );
    await addSlide('budget-widget', 'Controla tus presupuestos del mes', (outputPath) =>
      captureLocator(page, 'app-section-panel:has(app-budget-status-widget)', outputPath),
    );
    await addSlide('breakdown', 'Distribución por categoría', (outputPath) =>
      captureLocator(page, 'app-section-panel:has(app-spending-breakdown)', outputPath),
    );
    await addSlide('rhythm', 'Ritmo de gasto diario', (outputPath) =>
      captureLocator(page, 'app-section-panel:has(app-spending-rhythm)', outputPath),
    );

    // —— Expenses ——
    await page.goto('/user/expenses', { waitUntil: 'domcontentloaded' });
    await waitForExpenseListReady(page);

    await addSlide('expenses', 'Registra y organiza tus gastos', (outputPath) =>
      captureLocator(page, 'app-expense-list', outputPath),
    );

    const filterBar = page.locator('.expense-filters, app-expense-filters, [data-testid="expense-filters"]');
    if (await filterBar.count()) {
      await addSlide('expense-filters', 'Filtra por fecha, categoría o importe', (outputPath) =>
        captureLocator(
          page,
          '.expense-filters, app-expense-filters, [data-testid="expense-filters"]',
          outputPath,
        ),
      );
    }

    await page.goto('/user/dashboard', { waitUntil: 'domcontentloaded' });
    await waitForDashboardReady(page);
    await openQuickAddPanel(page);
    await addSlide('quick-add', 'Añade un gasto en segundos', async (outputPath) => {
      await page.locator('.quick-add-panel').screenshot({ path: outputPath });
    });
    await page.keyboard.press('Escape');

    // —— Budgets ——
    await page.goto('/user/settings/budgets', { waitUntil: 'domcontentloaded' });
    await waitForBudgetsReady(page);

    await addSlide('budgets', 'Configura tus presupuestos', (outputPath) =>
      captureLocator(page, 'app-budget-settings', outputPath),
    );
    await addSlide('budget-general', 'Presupuesto general del mes', (outputPath) =>
      captureLocator(page, 'app-budget-general-section', outputPath),
    );
    await addSlide('budget-categories', 'Límites por categoría', (outputPath) =>
      captureLocator(page, 'app-budget-category-budgets-section', outputPath),
    );

    // —— Fixed expenses ——
    await page.goto('/user/fixed-expenses', { waitUntil: 'domcontentloaded' });
    await waitForFixedExpensesReady(page);

    await addSlide('fixed-expenses', 'Controla tus gastos recurrentes', (outputPath) =>
      captureLocator(page, 'app-fixed-expense-list', outputPath),
    );
    await addSlide('fixed-list', 'Consulta montos y próximos vencimientos', (outputPath) =>
      captureLocator(page, '.fixed-expense-stack', outputPath),
    );

    await page.locator('app-page-header .btn.primary').click();
    await expect(page.locator('app-fixed-expense-create-modal .modal-panel')).toBeVisible({
      timeout: 10_000,
    });
    await page.waitForTimeout(300);
    await addSlide('fixed-create', 'Registra suscripciones y pagos fijos', async (outputPath) => {
      await page.locator('app-fixed-expense-create-modal .modal-panel').screenshot({ path: outputPath });
    });
    await page.keyboard.press('Escape');

    // —— Workspaces ——
    await page.goto('/user/workspaces', { waitUntil: 'domcontentloaded' });
    await waitForWorkspacesReady(page);

    await addSlide('workspaces', 'Organiza tus finanzas por espacio', (outputPath) =>
      captureLocator(page, 'app-workspace-list', outputPath),
    );
    await addSlide('workspace-grid', 'Personal y compartidos en un vistazo', (outputPath) =>
      captureLocator(page, '.workspace-grid', outputPath),
    );

    await page.locator('.workspace-card .btn.secondary').first().click();
    await page.waitForSelector('app-workspace-detail, app-expense-list', { timeout: 30_000 });
    await expect(page.locator('.fa-spinner.fa-spin, app-loading-state, .data-table__loading')).toHaveCount(
      0,
      { timeout: 30_000 },
    );
    await page.waitForTimeout(400);
    await addSlide('workspace-detail', 'Cada espacio con sus gastos y categorías', (outputPath) =>
      captureLocator(page, 'app-workspace-detail, app-expense-list', outputPath),
    );

    const manifest = {
      id: 'OverviewWalkthrough',
      compositionId: 'OverviewWalkthrough',
      title: 'Overview',
      fps: 30,
      width: 1440,
      height: 900,
      slides,
    };

    fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
    fs.copyFileSync(manifestPath, path.join(__dirname, '../remotion/screens.overview.json'));
  });
});
