# App walkthrough videos (Playwright + Remotion)

Generates product preview videos for the Learn Hub from real app screenshots.

## Prerequisites

- Front + back running (`Front`: `npm start`, `back`: `php artisan serve`)
- Staging user with dashboard data (expenses, budgets, heatmap)
- Node.js 20+

## Setup

```bash
cd tools/app-walkthroughs
cp .env.example .env
# Edit E2E_EMAIL / E2E_PASSWORD / BASE_URL
npm install
npx playwright install chromium
npm install --prefix remotion
```

## Pipeline (dashboard pilot)

```bash
npm run capture:dashboard
npm run render:dashboard
npm run publish:videos
```

## Pipeline (overview — Learn Hub landing)

```bash
npm run capture:overview
npm run render:overview
npm run publish:videos
```

Captures six areas: dashboard, expenses, quick add, budgets, fixed expenses, workspaces (~48 s total).

## Pipeline (expenses — gastos / quick add)

```bash
npm run capture:expenses
npm run render:expenses
npm run publish:videos
```

Captures 3 slides: expenses list overview, list with data, optional filters, and quick-add panel (~11–14 s total).

## Pipeline (budgets — presupuestos)

```bash
npm run capture:budgets
npm run render:budgets
npm run publish:videos
```

Captures 3 slides: overview, general budget, category budgets (~10.5 s total).

## Pipeline (fixed expenses — gastos fijos)

```bash
npm run capture:fixed-expenses
npm run render:fixed-expenses
npm run publish:videos
```

Captures 3 slides: list overview, monthly total + table, create modal (~10.5 s total).

## Pipeline (workspaces — espacios de trabajo)

```bash
npm run capture:workspaces
npm run render:workspaces
npm run publish:videos
```

Captures 3 slides: workspace list, card grid, space detail (~10.5 s total).

## Output

| Step | Output |
|---|---|
| Playwright | `assets/screens/{dashboard,overview,expenses,budgets,fixed-expenses,workspaces}-*.png` |
| Remotion | `remotion/out/{dashboard,overview,expenses,budgets,fixed-expenses,workspaces}.{mp4,webm}` |
| Publish | `Front/public/media/walkthroughs/` |

> **Importante:** Los videos van en `/media/walkthroughs/` (no en `/learn/videos/`) porque la ruta `/learn/*` la intercepta el router de Angular y devuelve 404.
>
> Tras publicar videos nuevos en `public/`, reinicia el dev server (`docker restart fintech_frontend` o `npm start`) para que los sirva.

## Adding a new feature video

1. Copy `playwright/capture-dashboard.spec.ts` → `capture-{feature}.spec.ts`
2. Add `screens.{feature}.json` template in `remotion/`
3. Register a new `<Composition>` in `remotion/src/Root.tsx`
4. Add feature entry in `back/resources/content/learn/content.json`
