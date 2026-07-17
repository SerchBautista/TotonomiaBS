import budgetsJson from '../screens.budgets.json';
import dashboardJson from '../screens.dashboard.json';
import expensesJson from '../screens.expenses.json';
import fixedExpensesJson from '../screens.fixed-expenses.json';
import overviewJson from '../screens.overview.json';
import workspacesJson from '../screens.workspaces.json';
import type { WalkthroughManifest } from './types';

export type { WalkthroughManifest, WalkthroughSlide, SlideZoom } from './types';
export {
  budgetsJson as budgetsManifest,
  dashboardJson as dashboardManifest,
  expensesJson as expensesManifest,
  fixedExpensesJson as fixedExpensesManifest,
  overviewJson as overviewManifest,
  workspacesJson as workspacesManifest,
};

export function totalDurationFrames(manifest: WalkthroughManifest): number {
  return manifest.slides.reduce((total, slide) => total + slide.durationFrames, 0);
}
