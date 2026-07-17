# Design System — FinTech Frontend

> **Canonical source:** all visual tokens live in [`Front/src/styles.scss`](../Front/src/styles.scss). Shared component APIs live in `Front/src/app/shared/`. Never hardcode `#hex`, `rgba(...)`, or arbitrary `px` values in feature code — always consume the tokens and components documented here.

---

## 1. Overview

The FinTech frontend design system is the consolidated reference for the visual language adopted during the `openspec/changes/frontend-redesign-complete` change. It unifies the previously inconsistent UI of `/user/*` and `/admin/*` around a single vocabulary: dark-first surfaces, a navy sidebar accent, elevated cards, and a shared dumb-component library that lives entirely in `Front/src/app/shared/`.

The system has three pillars:

- **Design tokens** — CSS custom properties for color, spacing, radius, shadow, typography, gradient and z-index. The single source of truth is `Front/src/styles.scss`; light/dark themes are implemented by overriding the same custom properties under `body[data-theme='light']`.
- **Shared component library** — Angular standalone, OnPush, "dumb" components (`SectionPanelComponent`, `PageHeaderComponent`, `DataTableComponent`, `ModalShellComponent`, etc.). They take inputs and emit outputs only — no service calls, no business logic.
- **Design patterns** — recurring compositions (list page, detail page, form page, modal, CRUD page, settings shell, notifications) that features compose from the shared components instead of reinventing local markup.

The goal is that any new feature can be built with a small set of shared components + tokens, and that visual regressions are caught by inspection against this document rather than by ad-hoc per-feature review.

---

## 2. Design tokens

All tokens are declared on `:root` in `Front/src/styles.scss`. The light theme is a `:root` override applied through `body[data-theme='light']`.

### 2.1 Color

| Group                  | Tokens                                                                                                                                                       | Notes                                                                                                                                       |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Brand (purple)         | `--color-brand-900`, `-800`, `-700`, `-600`, `-200`, `-100`, `-50`                                                                                           | `-700` is the primary action color; `-600` is the focus ring.                                                                               |
| Accent (cyan)          | `--color-accent`, `--color-accent-bg`                                                                                                                        | Used sparingly for highlights and the "accent" button variant.                                                                              |
| Sidebar                | `--color-sidebar-bg`, `-text`, `-text-muted`, `-hover`, `-active-bg`, `-active-bg-solid`, `-active-border`, `-brand-mark-bg`                                 | **Theme-aware.** Navy surfaces in dark, white surface + dark text + brand-tinted active in light.                                           |
| Topbar                 | `--color-topbar-bg`, `-border`, `-text`, `-text-muted`, `-icon-bg`, `-icon-bg-hover`, `-icon-border`, `-icon-color`                                          | **Theme-aware.** Translucent dark topbar with backdrop blur in dark; light translucent surface + dark text/icons in light.                  |
| Semantic               | `--color-success`/`-bg`, `--color-danger`/`-bg`/`-text`, `--color-warning`/`-bg`/`-text`                                                                     | Status semantics; both dark and light palettes are defined.                                                                                 |
| Budget                 | `--color-budget-spent`/`-bg`, `--color-budget-committed`/`-bg`, `--color-budget-track`                                                                       | Used by the budget progress bars.                                                                                                           |
| Text                   | `--color-text`, `--color-text-muted`, `--color-text-inverse`                                                                                                 | Body text, secondary text, text on dark/brand surfaces.                                                                                     |
| Surface                | `--color-bg`, `--color-bg-alt`, `--color-surface`, `--color-surface-alt`, `--color-surface-elevated`, `--color-sidebar`, `--color-topbar`, `--color-overlay` | Background and surface elevation scale.                                                                                                     |
| Border                 | `--color-border`, `--color-border-muted`                                                                                                                     | Standard and subtle borders.                                                                                                                |
| Table / filter helpers | `--color-filter-bg`, `--color-table-header-bg`, `--color-table-row-bg`, `--color-table-row-hover`                                                            | Aliased onto the surface scale + brand hover; **theme-aware** by construction.                                                              |
| Summary hero           | `--color-summary-hero-bg`, `--color-summary-hero-text`                                                                                                       | **Theme-aware.** Aliases to the sidebar palette in dark, brand-tinted light surface in light. Drives the `app-summary-hero` `navy` variant. |

### 2.2 Spacing

| Token       | Value     |
| ----------- | --------- |
| `--space-1` | `0.25rem` |
| `--space-2` | `0.5rem`  |
| `--space-3` | `0.75rem` |
| `--space-4` | `1rem`    |
| `--space-5` | `1.25rem` |
| `--space-6` | `1.5rem`  |
| `--space-8` | `2rem`    |

Use the scale, never arbitrary values. Gaps in flex/grid layouts and section margins are expressed through these tokens.

### 2.3 Radius

| Token           | Value   | Typical use                                       |
| --------------- | ------- | ------------------------------------------------- |
| `--radius-sm`   | `8px`   | Inputs, small buttons                             |
| `--radius-md`   | `12px`  | Default panel / card (the canonical panel radius) |
| `--radius-lg`   | `16px`  | Medium components                                 |
| `--radius-xl`   | `20px`  | Tabs, large surfaces                              |
| `--radius-2xl`  | `24px`  | Cards (`.card-surface`)                           |
| `--radius-3xl`  | `32px`  | Modals, hero sections                             |
| `--radius-full` | `999px` | Badges, pills, avatar chips                       |

Note: the dashboard `.panel` and `app-section-panel` default to `--radius-md` (12px) — this is the canonical "panel" radius after the redesign.

### 2.4 Shadow

| Token                      | Use                                                                                   |
| -------------------------- | ------------------------------------------------------------------------------------- |
| `--shadow-sm`              | Subtle elevation — buttons at rest                                                    |
| `--shadow-md`              | Hover of buttons, cards in rest state                                                 |
| `--shadow-lg`              | Floating panels, drawers                                                              |
| `--shadow-xl`              | Modals, topmost elements                                                              |
| `--shadow-fab`             | FAB (Floating Action Button) tinted by brand                                          |
| `--shadow-glow`            | Halo around brand-elevated cards and hero CTAs                                        |
| `--shadow-inset-highlight` | `inset 0 1px 0 rgba(255, 255, 255, 0.04)` — top inner highlight for elevated surfaces |

### 2.5 Typography

| Token                  | Value                           |
| ---------------------- | ------------------------------- |
| `--font-sans`          | `Inter, 'Segoe UI', sans-serif` |
| `--font-weight-normal` | `400`                           |
| `--font-weight-semi`   | `600`                           |
| `--font-weight-bold`   | `700`                           |
| `--font-weight-black`  | `800`                           |

Conventions:

- Page `h1` titles: `font-weight: 800`, `letter-spacing: -0.02em`, `color: var(--color-text)`.
- Financial numbers: `letter-spacing: -0.02em`.
- Form labels: `font-size: 0.8rem`, `font-weight: bold`, `text-transform: uppercase`, `letter-spacing: 0.02em`.
- Body text: `font-size: 0.95rem`.

### 2.6 Gradients

| Token                | Definition                                                                            | Use                              |
| -------------------- | ------------------------------------------------------------------------------------- | -------------------------------- |
| `--gradient-brand`   | `linear-gradient(135deg, var(--color-brand-700), var(--color-brand-900))`             | Reserved for brand-only surfaces |
| `--gradient-hero`    | `linear-gradient(135deg, ...brand-700 12% / surface-alt, ...accent 8% / surface-alt)` | Auth/learn hero panels           |
| `--gradient-subtle`  | `linear-gradient(180deg, var(--color-surface-alt), var(--color-bg-alt))`              | Default `.surface-panel`         |
| `--gradient-sidebar` | `var(--color-sidebar)`                                                                | Sidebar (flat)                   |

> The redesign removed brand-tinted gradients from feature areas (filters, total heroes, etc.) — `--gradient-brand` is reserved for top-of-funnel surfaces only.

### 2.7 Z-index

| Token            | Value  | Layer                      |
| ---------------- | ------ | -------------------------- |
| `--z-sidebar`    | `100`  | Sidebar                    |
| `--z-topbar`     | `200`  | Topbar                     |
| `--z-mobile-nav` | `800`  | Mobile bottom navigation   |
| `--z-fab`        | `900`  | Floating Action Button     |
| `--z-dialog`     | `1000` | Modals and confirm dialogs |

### 2.8 Theme override pattern

Light theme tokens are defined as overrides under `body[data-theme='light']` (in the same `styles.scss`). The strategy is:

- Override color tokens (and shadows) only.
- Spacing, radius, typography and z-index are theme-agnostic.
- The body declares `color-scheme: dark` by default and `color-scheme: light` under `[data-theme='light']` so native form controls match.
- Transitions on `background-color` and `color` are 0.2s ease.

```scss
// existing pattern in styles.scss
body {
  color-scheme: dark;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
}

body[data-theme="light"] {
  color-scheme: light;
  // re-declared tokens for light palette
}
```

---

## 3. Shared component library

All shared components are Angular standalone, OnPush, "dumb" components under `Front/src/app/shared/`. They live next to a `.html`, `.scss` and `*.spec.ts`. Inputs are signal inputs (`input()` / `input.required()`); outputs are `output()` event emitters (or the legacy `@Output()` decorator where noted).

> For the full source of truth (including private methods and computed signals), see the linked `.ts` file of each component.

### 3.1 `app-section-panel`

- **Purpose:** the canonical card/panel surface used everywhere a group of content needs visual elevation (dashboard cards, settings sections, profile sections, form containers). Matches the dashboard `.panel` style and replaces per-feature `.panel`/`.card` markup.
- **Selector:** `app-section-panel`
- **Inputs:**
  - `title?: string` — optional panel header (`h2`).
  - `withHover?: boolean` (default `false`) — enables the hover glow (`--shadow-glow`).
  - `noPadding?: boolean` (default `false`) — drops the body padding (e.g. when the body is itself a `app-data-table`).
- **Outputs:** none.
- **Slots:**
  - default → panel body
  - `[actions]` → right side of the header
- **Example:**
  ```html
  <app-section-panel title="Estado de presupuesto" [withHover]="true">
    <ng-container actions>
      <app-month-navigator ... />
    </ng-container>
    <app-budget-status-widget />
  </app-section-panel>
  ```
- **Tokens used:** `--color-surface-alt`, `--color-border`, `--color-border-muted`, `--radius-md`, `--shadow-md`, `--shadow-glow`, `--space-4`/`-6`.
- **Notes:** when wrapping a `app-data-table`, set `[noPadding]="true"` to avoid double padding. Source: [`section-panel.ts`](../Front/src/app/shared/section-panel/section-panel.ts).

### 3.2 `app-page-header`

- **Purpose:** the page-level header: title (`h1`), optional subtitle, and a right-aligned actions slot for primary CTAs. Use on every list, detail, form, settings and admin page.
- **Selector:** `app-page-header`
- **Inputs:**
  - `title: string` (required) — the page `h1`.
  - `subtitle?: string` — secondary line below the title.
- **Outputs:** none.
- **Slots:**
  - default → left column (below the subtitle)
  - `[actions]` → right column (primary CTAs, e.g. "Crear gasto")
- **Example:**
  ```html
  <app-page-header title="Gastos" subtitle="Workspace personal">
    <ng-container actions>
      <button class="btn primary" (click)="quickAddService.open()">
        <i class="fa-solid fa-plus"></i> Crear gasto
      </button>
    </ng-container>
  </app-page-header>
  ```
- **Tokens used:** `--color-text`, `--color-text-muted`, `--space-6`, `--font-weight-black`.
- **Notes:** never re-style the title in feature SCSS — go through the component. Source: [`page-header.ts`](../Front/src/app/shared/page-header/page-header.ts).

### 3.3 `app-page-filters`

- **Purpose:** a flat surface container (no gradient) for a row of filter controls (selects, inputs, search). Decoupled from any specific control — the consumer projects the controls.
- **Selector:** `app-page-filters`
- **Inputs:** none.
- **Outputs:** none.
- **Slots:**
  - default → the filter row.
- **Example:**
  ```html
  <app-page-filters>
    <div class="filter-field">
      <label>Categoría</label>
      <select>
        ...
      </select>
    </div>
  </app-page-filters>
  ```
- **Tokens used:** `--color-filter-bg`, `--color-border`, `--radius-xl`, `--space-4`.
- **Notes:** the `gap` and `flex-wrap` are baked in; put each control in a wrapper div for proper spacing on narrow viewports. Source: [`page-filters.ts`](../Front/src/app/shared/page-filters/page-filters.ts).

### 3.4 `app-summary-hero`

- **Purpose:** KPI / total card with a label and a big value. Two variants: `navy` (default, sidebar navy solid) for the canonical "Total" hero, and `surface` for secondary KPIs.
- **Selector:** `app-summary-hero`
- **Inputs:**
  - `label: string` (required) — uppercase label above the value.
  - `value: string` (required) — the big number (already formatted).
  - `variant?: 'navy' | 'surface'` (default `'navy'`).
- **Outputs:** none.
- **Example:**
  ```html
  <app-summary-hero label="Total gastado" value="$850.00" />
  ```
- **Tokens used:** `--color-summary-hero-bg`, `--color-summary-hero-text` (for the `navy` variant), `--color-surface-elevated` (for the `surface` variant), `--color-border`, `--radius-md`, `--shadow-md`, `--space-6`/`-8`, `--font-weight-black`.
- **Notes:**
  - The `navy` variant is **theme-aware**: it renders as a dark navy panel with white text in dark theme, and as a light brand-tinted surface with dark text in light theme. Consumers do not need to switch variants between themes.
  - The `surface` variant is a flat card that follows the standard surface scale (already theme-aware).
  - The value must be a pre-formatted string — formatting (currency, decimals) is the consumer's responsibility. Source: [`summary-hero.ts`](../Front/src/app/shared/summary-hero/summary-hero.ts).

### 3.5 `app-data-table`

- **Purpose:** the client-side table for tabular data with custom cell templates and a built-in loading/empty state. Use for client-side data (expenses, fixed expenses, categories, etc.).
- **Selector:** `app-data-table`
- **Inputs:**
  - `columns: TableColumn<T>[]` (required) — column definitions.
  - `rows: T[]` (required) — the data rows.
  - `loading?: boolean` (default `false`) — replaces the table with the inline loading state.
  - `emptyMessage?: string` (default `''`) — copy when there are no rows.
  - `emptyActionLabel?: string` — optional CTA label inside the empty state.
  - `ariaLabel?: string` — `aria-label` for the `<table>`.
- **Outputs:**
  - `emptyAction: void` — emitted when the empty-state CTA is clicked.
- **Slots / directives:**
  - Optional cell templates via the `appTableCell` directive. Each `TableColumn` may also provide a `cellTemplate` directly. Source: [`table-cell.directive.ts`](../Front/src/app/shared/data-table/table-cell.directive.ts).
- **Column shape (`TableColumn<T>`):**
  ```ts
  interface TableColumn<T = unknown> {
    key: string;
    header: string;
    width?: string;
    align?: "left" | "right" | "center";
    cellTemplate?: TemplateRef<{ $implicit: T }>;
  }
  ```
- **Example:**
  ```html
  <app-data-table [columns]="columns" [rows]="expenses()" [loading]="loading()">
    <ng-template appTableCell="category" let-expense>
      <app-category-badge [category]="expense.category" />
    </ng-template>
    <ng-template appTableCell="actions" let-expense>
      <app-action-buttons
        (edit)="onEdit(expense)"
        (delete)="onDelete(expense)"
      />
    </ng-template>
  </app-data-table>
  ```
- **Tokens used:** `--color-table-header-bg`, `--color-table-row-bg`, `--color-table-row-hover`, `--color-border-muted`, `--color-text-muted`, `--radius-md`.
- **Notes:** below 640px the table collapses into stacked cards using `data-label` per cell; keep headers short for that. For server-side sort/pagination, use [`app-server-table`](#319-app-server-table) instead. The wrapper, header, rows, hover, and borders are all theme-aware (light surface in light theme, dark surface in dark theme). Source: [`data-table.ts`](../Front/src/app/shared/data-table/data-table.ts).

### 3.6 `app-pagination-bar`

- **Purpose:** client-side prev/next pagination bar with a `current / last` indicator. Pair with `app-data-table`.
- **Selector:** `app-pagination-bar`
- **Inputs:**
  - `currentPage: number` (required).
  - `lastPage: number` (required).
- **Outputs:**
  - `prev: void`
  - `next: void`
- **Example:**
  ```html
  <app-pagination-bar
    [currentPage]="page()"
    [lastPage]="lastPage()"
    (prev)="prev()"
    (next)="next()"
  />
  ```
- **Tokens used:** `--color-text-muted`, `.btn.secondary.sm` from the global button styles.
- **Notes:** buttons are disabled automatically at the boundaries. Source: [`pagination-bar.ts`](../Front/src/app/shared/pagination-bar/pagination-bar.ts).

### 3.7 `app-category-badge`

- **Purpose:** solid color pill showing a category name and (optionally) its icon. The text color is auto-selected (white/black) for WCAG contrast against the category color.
- **Selector:** `app-category-badge`
- **Inputs:**
  - `category: CategoryBadgeData` (required) — `{ name, color, icon? }`.
  - `showIcon?: boolean` (default `true`).
- **Outputs:** none.
- **Example:**
  ```html
  <app-category-badge [category]="expense.category" />
  ```
- **Tokens used:** none directly (the colors come from the per-category `color` input).
- **Notes:** the contrast helper is [`shared/utils/contrast-color.ts`](../Front/src/app/shared/utils/contrast-color.ts) (`contrastColor(hex): '#ffffff' | '#000000'`). Source: [`category-badge.ts`](../Front/src/app/shared/category-badge/category-badge.ts).

### 3.8 `app-status-badge`

- **Purpose:** semantic badge (brand, success, warning, danger) backed by the global `.badge` classes. Use for entity status, account state, plan tier, etc.
- **Selector:** `app-status-badge`
- **Inputs:**
  - `variant: 'brand' | 'success' | 'warning' | 'danger'` (required).
  - `label: string` (required).
- **Outputs:** none.
- **Example:**
  ```html
  <app-status-badge variant="success" label="Activo" />
  ```
- **Tokens used:** the global `.badge`/`.badge-*` styles (`--color-brand-700`, `--color-success`, `--color-danger`, `--color-warning`, etc.).
- **Notes:** the default `.badge` background is not styled — always provide a `variant`. Source: [`status-badge.ts`](../Front/src/app/shared/status-badge/status-badge.ts).

### 3.9 `app-action-buttons`

- **Purpose:** the row-action icon cluster (view / edit / delete) for tables. Each button emits a dedicated event so the consumer can map it to its handler.
- **Selector:** `app-action-buttons`
- **Inputs:**
  - `showView?: boolean` (default `false`).
  - `showEdit?: boolean` (default `true`).
  - `showDelete?: boolean` (default `true`).
  - `viewAriaLabel?: string` (default `'Ver'`).
  - `editAriaLabel?: string` (default `'Editar'`).
  - `deleteAriaLabel?: string` (default `'Eliminar'`).
- **Outputs:**
  - `view: void`
  - `edit: void`
  - `delete: void`
- **Example:**
  ```html
  <app-action-buttons
    (view)="openDetail(row)"
    (edit)="onEdit(row)"
    (delete)="onDelete(row)"
  />
  ```
- **Tokens used:** `.icon-btn.ghost`, `.icon-btn.danger` from global styles, `--space-2`.
- **Notes:** all buttons have `aria-label`. The component is purely presentational; the consumer decides whether "delete" is a direct delete or opens a confirm dialog. Source: [`action-buttons.ts`](../Front/src/app/shared/action-buttons/action-buttons.ts).

### 3.10 `app-empty-state`

- **Purpose:** zero-data state with an icon, title, optional description, and optional CTA. Use for "no rows", "no notifications", "no workspaces", etc.
- **Selector:** `app-empty-state`
- **Inputs:**
  - `icon?: string` (default `'fa-inbox'`) — Font Awesome icon class.
  - `title: string` (required).
  - `message?: string`.
  - `actionLabel?: string` — if present, renders a `btn secondary`.
- **Outputs:**
  - `action: void` — CTA click.
- **Example:**
  ```html
  <app-empty-state
    icon="fa-receipt"
    title="Sin gastos"
    message="Cuando registres un gasto aparecerá aquí."
    actionLabel="Crear gasto"
    (action)="openCreate()"
  />
  ```
- **Tokens used:** `--color-text-muted`, `--color-text`, `--space-*`.
- **Notes:** the icon is hidden from assistive tech (`aria-hidden="true"`); the `title` carries the meaning. Source: [`empty-state.ts`](../Front/src/app/shared/empty-state/empty-state.ts).

### 3.11 `app-loading-state`

- **Purpose:** centered spinner + message. Use for full-page or full-section initial loads.
- **Selector:** `app-loading-state`
- **Inputs:**
  - `message?: string` (default `'Cargando...'`).
- **Outputs:** none.
- **Example:**
  ```html
  <app-loading-state message="Cargando gastos..." />
  ```
- **Tokens used:** `--color-border`, `--color-brand-600`, `--color-text-muted` via the global `.loading-spinner`/`.loading-state` styles.
- **Notes:** the spinner is decorative (`aria-hidden="true"`); the message is the accessible label. Source: [`loading-state.ts`](../Front/src/app/shared/loading-state/loading-state.ts).

### 3.12 `app-month-navigator`

- **Purpose:** month-to-month navigator with optional "Today" reset. Use in any view that pivots on a single month (budget, dashboard, charts, etc.).
- **Selector:** `app-month-navigator`
- **Inputs:**
  - `label: string` (required) — formatted month label (e.g. "Junio de 2026").
  - `nextDisabled?: boolean` (default `false`) — disable the next button.
  - `showToday?: boolean` (default `true`) — show the "Today" button.
  - `previousLabel?: string` (default `'Mes anterior'`).
  - `nextLabel?: string` (default `'Mes siguiente'`).
  - `todayLabel?: string` (default `'Hoy'`).
- **Outputs:**
  - `previous: void`
  - `next: void`
  - `today: void`
- **Example:**
  ```html
  <app-month-navigator
    [label]="currentMonthLabel()"
    [nextDisabled]="atCurrentMonth()"
    (previous)="goPrev()"
    (next)="goNext()"
    (today)="goToday()"
  />
  ```
- **Tokens used:** `--color-surface-alt`, `--color-border`, `--color-text-muted`, `--color-text`.
- **Notes:** all buttons are icon-only with `aria-label` and `title`. Source: [`month-navigator.ts`](../Front/src/app/shared/month-navigator/month-navigator.ts).

### 3.13 `app-modal-shell`

- **Purpose:** the canonical modal frame for form and detail dialogs. Visual language matches the quick-add expense panel (blurred backdrop, rounded elevated surface, compact header, inline footer actions). Closes on backdrop click and Escape; the consumer drives visibility via `open` / `close`.
- **Selector:** `app-modal-shell`
- **Inputs:**
  - `open: boolean` (required).
  - `title: string` (required).
  - `size?: 'sm' | 'md' | 'lg'` (default `'md'`).
- **Outputs:**
  - `close: void`.
- **Slots:**
  - default → body
  - `[footer]` → action buttons
- **Visual spec (aligned with quick-add):**
  - Backdrop: `var(--color-overlay)` + `backdrop-filter: blur(4px)`.
  - Panel: `var(--color-surface-elevated)`, `border-radius: var(--radius-2xl)`, `box-shadow: var(--shadow-xl)`.
  - Width: `md` → max `440px`; `sm` → `24rem`; `lg` → `36rem`.
  - Mobile (`≤640px`): bottom sheet, top corners `var(--radius-3xl)`, full width, max-height `85vh`.
  - Header: title `1.1rem` bold, close button top-right; no heavy separator under the header.
  - Body: horizontal padding `1.75rem`; scroll when content overflows.
  - Footer: no top border; `gap: 0.75rem`, `justify-content: flex-end`; primary action first, cancel with `btn secondary`.
- **Form body:** use the global `.modal-form` class (see §3.13.1). **Do not** wrap modal body content in `app-form-card` — it creates a double-surface look inconsistent with quick-add.
- **Example:**
  ```html
  <app-modal-shell
    [open]="showForm()"
    [title]="'categories.create' | translate"
    size="md"
    (close)="toggleForm()"
  >
    <form class="modal-form" [formGroup]="form" (ngSubmit)="submitCreate()">
      <div class="field">
        <label for="cat-name">
          <i class="fa-solid fa-tags"></i> {{ 'categories.name' | translate }}
        </label>
        <input id="cat-name" type="text" formControlName="name" />
        @if (form.controls.name.invalid && form.controls.name.touched) {
          <small class="error">{{ 'categories.name_required' | translate }}</small>
        }
      </div>
      <div class="form-grid">
        <div class="field">…</div>
        <div class="field">…</div>
      </div>
    </form>
    <ng-container footer>
      <button type="button" class="btn primary sm" (click)="submitCreate()" [disabled]="saving()">
        {{ 'common.save' | translate }}
      </button>
      <button type="button" class="btn secondary sm" (click)="toggleForm()">
        {{ 'common.cancel' | translate }}
      </button>
    </ng-container>
  </app-modal-shell>
  ```
- **Tokens used:** `--color-overlay`, `--color-surface-elevated`, `--color-border`, `--color-text`, `--color-text-muted`, `--radius-2xl`, `--radius-3xl`, `--space-*`, `--z-dialog`, `--shadow-xl`.
- **Notes:** sets `role="dialog"`, `aria-modal="true"`, `aria-labelledby="modal-title"`; focuses the panel on open. Escape and backdrop clicks emit `close`. Reference implementations: `features/categories/category-list`, `features/payment-methods/payment-method-list`. Source: [`modal-shell.ts`](../Front/src/app/shared/modal-shell/modal-shell.ts).

### 3.13.1 `.modal-form` (global form layout inside modals)

- **Purpose:** shared form layout for content projected into `app-modal-shell`. Matches quick-add field spacing and label style (sentence case, optional Font Awesome icon in label).
- **Location:** [`styles.scss`](../Front/src/styles.scss) — global class, not a component.
- **Structure:**
  - `.modal-form` — flex column, `gap: 1rem`.
  - `.modal-form .field` — label + control stack; labels `0.85rem`, `var(--font-weight-semi)`, `var(--color-text-muted)`; **no** uppercase.
  - `.modal-form .form-grid` — two-column grid on desktop, single column `≤480px`.
  - `.modal-form .error` — validation messages, `var(--color-danger)`.
- **When to use:** any form inside `app-modal-shell`. Prefer `.field` over `.field-group` inside modals — `.field-group` (uppercase labels) is for full-page forms.
- **When not to use:** full-page forms (`app-form-card` on a route), quick-add inline sub-forms (`.inline-form` in quick-add), fixed-expense modals (documented exception).

### 3.14 `app-form-card`

- **Purpose:** consistent form surface for **full-page** forms (padding, border, radius). Replaces legacy `.inline-form`/`.card-form` markup on routes.
- **Selector:** `app-form-card`
- **Inputs:**
  - `title?: string` — optional `h3` above the form.
- **Outputs:** none.
- **Slots:**
  - default → form content
- **When to use:** `app-page-header` + form on a dedicated route (§4.3).
- **When not to use:** inside `app-modal-shell` — the modal panel already provides the elevated surface; nesting `app-form-card` duplicates borders and padding.
- **Example:**
  ```html
  <app-form-card title="Datos del gasto">
    <div class="field-group">
      <label for="amount">Importe</label>
      <input id="amount" type="number" />
    </div>
  </app-form-card>
  ```
- **Tokens used:** `--color-surface-alt`, `--color-border`, `--radius-2xl`, `--space-6`.
- **Notes:** global `input`/`select`/`textarea` styles provide border and focus ring. Source: [`form-card.ts`](../Front/src/app/shared/form-card/form-card.ts).

### 3.15 `app-confirm-dialog`

- **Purpose:** simple yes/no confirmation dialog. Use for destructive actions (delete), irreversible changes, and small acknowledgements that don't justify a full form modal.
- **Selector:** `app-confirm-dialog`
- **Inputs (legacy decorator form):**
  - `open?: boolean` (default `false`)
  - `title?: string` (default `''`)
  - `message?: string` (default `''`)
  - `confirmLabel?: string` (default `''`)
  - `cancelLabel?: string` (default `''`)
- **Outputs:**
  - `confirmed: void`
  - `canceled: void`
- **Example:**
  ```html
  <app-confirm-dialog
    [open]="confirmingDelete()"
    title="Eliminar gasto"
    message="Esta acción no se puede deshacer."
    confirmLabel="Eliminar"
    cancelLabel="Cancelar"
    (confirmed)="doDelete()"
    (canceled)="confirmingDelete.set(false)"
  />
  ```
- **Tokens used:** `--color-overlay`, `--color-surface-elevated`, `--color-text`, `.btn.ghost`, `.btn.danger`.
- **Notes:** ARIA: `role="dialog"`, `aria-modal="true"`, `aria-labelledby` is wired to a per-instance `titleId` so multiple dialogs don't collide. Escape and backdrop click both emit `canceled`. Source: [`confirm-dialog.ts`](../Front/src/app/shared/confirm-dialog/confirm-dialog.ts).

### 3.16 `app-icon-picker`

- **Purpose:** grid of Font Awesome icons used by the category create/edit form. Implements `ControlValueAccessor`, so it can be used with reactive or template-driven forms as a regular form control.
- **Selector:** `app-icon-picker`
- **Inputs:**
  - `ariaLabel?: string | null`
  - `ariaLabelledBy?: string | null` — pass the id of a visible label to wire the radiogroup to it.
- **Outputs:** none directly — the value flows through `ControlValueAccessor`.
- **Example:**
  ```html
  <app-icon-picker formControlName="icon" ariaLabelledBy="icon-label" />
  ```
- **Tokens used:** `.icon-btn` styles, `--color-brand-600`, `--color-brand-50`, `--color-brand-200`, `--color-text`.
- **Notes:** the icon catalogue is exported as `CATEGORY_ICONS` from the same file; reuse the same list anywhere a category icon is needed. The grid uses `role="radiogroup"` and each option is a `role="radio"` with `aria-checked`. Source: [`icon-picker.ts`](../Front/src/app/shared/icon-picker/icon-picker.ts).

### 3.17 `app-server-table`

- **Purpose:** server-driven table with server-side sort, page, and per-page selection. Used for views where the backend already paginates and the frontend should not load the full result set (e.g. admin tables). Kept as a separate component rather than merged into `app-data-table` because the contract is fundamentally different (server events vs. inputs only).
- **Selector:** `app-server-table`
- **Inputs (legacy decorator form):**
  - `columns: TableColumn[]` — `{ key, label, sortable? }`
  - `rows: Record<string, unknown>[]`
  - `loading?: boolean`
  - `currentPage?: number` (default `1`)
  - `lastPage?: number` (default `1`)
  - `total?: number` (default `0`)
  - `perPage?: number` (default `10`)
  - `perPageOptions?: number[]` (default `[10, 25, 50, 100]`)
  - `sortBy?: string` (default `'created_at'`)
  - `sortDir?: 'asc' | 'desc'` (default `'desc'`)
  - `emptyLabel?: string`
  - `deleteButtonVariant?: 'danger' | 'warning'` (default `'danger'`)
  - `actionsLabelKey?`, `viewLabelKey?`, `editLabelKey?`, `deleteLabelKey?`, `perPageLabelKey?`, `prevLabelKey?`, `nextLabelKey?` — i18n keys for the actions column and pagination controls.
  - `ariaLabel?: string`
- **Outputs:**
  - `sortChanged: { sortBy: string; sortDir: 'asc' | 'desc' }`
  - `pageChanged: number`
  - `perPageChanged: number`
  - `actionClicked: { action: 'view' | 'edit' | 'delete'; row }`
- **Example:**
  ```html
  <app-server-table
    [columns]="columns()"
    [rows]="users()"
    [loading]="loading()"
    [currentPage]="page()"
    [lastPage]="lastPage()"
    [total]="total()"
    (sortChanged)="onSort($event)"
    (pageChanged)="onPage($event)"
    (perPageChanged)="onPerPage($event)"
    (actionClicked)="onAction($event)"
  />
  ```
- **Tokens used:** `--color-surface-alt`, `--color-border`, `--color-border-muted`, `--color-text-muted`, `.btn.secondary.sm`, `--space-*`.
- **Notes:** consumers in the codebase are `user-list` and `administrator-list`. Do not introduce new consumers unless the data set really must be paginated server-side. Source: [`server-table.ts`](../Front/src/app/shared/server-table/server-table.ts).

### 3.18 `app-cta-banner`

- **Purpose:** public/learn-style call-to-action banner with title, subtitle, and two button links. Lives in public surfaces (auth/learn pages).
- **Selector:** `app-cta-banner`
- **Inputs (legacy decorator form, all required unless noted):**
  - `title: string`
  - `subtitle: string`
  - `primaryCta: string`
  - `primaryCtaLink?: string` (default `'/register'`)
  - `secondaryCta: string`
  - `secondaryCtaLink?: string` (default `'/pricing'`)
- **Outputs:** none — buttons are router links.
- **Example:**
  ```html
  <app-cta-banner
    title="Empieza gratis"
    subtitle="Sin tarjeta, sin permanencia."
    primaryCta="Crear cuenta"
    secondaryCta="Ver planes"
  />
  ```
- **Tokens used:** `.card-surface` (and therefore `--color-surface-alt`, `--color-border`, `--radius-md`, `--shadow-md`), `.btn.primary`, `.btn.secondary`.
- **Notes:** uses `RouterLink` internally, so it must be rendered inside a routed context. Source: [`cta-banner.ts`](../Front/src/app/shared/cta-banner/cta-banner.ts).

### 3.19 `app-topic-card`

- **Purpose:** clickable card for the public/learn topics grid (icon, title, summary, "read more"). Renders as a `RouterLink`.
- **Selector:** `app-topic-card`
- **Inputs (legacy decorator form, all required):**
  - `icon: string` — Font Awesome class (e.g. `'fa-piggy-bank'`).
  - `title: string`
  - `summary: string`
  - `link: string` — router path.
  - `readMoreLabel: string`
- **Outputs:** none.
- **Example:**
  ```html
  <app-topic-card
    icon="fa-wallet"
    title="Controla tus gastos"
    summary="Aprende a registrar y categorizar tus gastos."
    link="/learn/gastos"
    readMoreLabel="Leer más"
  />
  ```
- **Tokens used:** `--color-surface-alt`, `--color-border`, `--radius-*`, `--color-text`, `--color-text-muted`, `--color-brand-700`.
- **Notes:** the entire card is a link, so the icon and "read more" label are decorative (`aria-hidden="true"`). Source: [`topic-card.ts`](../Front/src/app/shared/topic-card/topic-card.ts).

### 3.20 `app-nav`

- **Purpose:** the primary navigation surface — sidebar for desktop and the source-of-truth for the active section. It is **not** a generic layout component; it owns its own role-based menu, collapse state (persisted in `localStorage`), and route-prefix detection. There is only one of these per app.
- **Selector:** `app-nav`
- **Inputs:** none.
- **Outputs:** none — it dispatches via `RouterLink`/`RouterLinkActive` and reacts to `NavigationEnd` events.
- **Notes:**
  - Active section is derived from the URL prefix (`/user/` or `/admin/`) plus the authenticated user's effective roles (see [`core/auth/role-hierarchy.ts`](../Front/src/app/core/auth/role-hierarchy.ts)).
  - The collapsed state is persisted under the storage key `totonomia:sidebar:collapsed`. All `localStorage` access must still go through `BrowserStorageService` per `docs/angular-solid-clean.md`; this component is the one documented exception because of its proximity to the layout root.
  - Uses `AUTH_STATE_TOKEN` injection token for the auth state, not a concrete service.
  - Source: [`nav.ts`](../Front/src/app/shared/nav/nav.ts).

---

## 4. Design patterns

These are the established compositions for the most common page types. Features should follow them by default; deviations must be justified in the PR.

### 4.1 List page pattern

`app-page-header` (with primary CTA) + `app-page-filters` + (optional) `app-summary-hero` + `app-data-table` (or `app-server-table`) + `app-pagination-bar`. Replace the table with `app-empty-state` when there are no rows, and with `app-loading-state` for the initial load.

```
[PageHeader: title + "Crear X" button]
[PageFilters: workspace | date range | category | search | ...]
[SummaryHero: navy variant — total / KPI]
[DataTable or ServerTable: rows]
[PaginationBar]
```

Applied in: `/user/expenses`, `/user/fixed-expenses`, `/user/pending-payments`, `/user/settings/categories`, `/user/settings/payment-methods`, `/user/settings/budgets`.

### 4.2 Detail page pattern

`app-page-header` (read-only title, no primary CTA unless there is a page-level action) + a stack of `app-section-panel` sections, each with a `[withHover]` flag on interactive cards.

```
[PageHeader: title + subtitle + optional actions]
[SectionPanel: summary]
[SectionPanel: line items / history]
[SectionPanel: metadata]
```

Applied in: dashboard, `/user/workspaces/:id` detail, `/admin/users/:id` detail, profile.

### 4.3 Form page pattern

`app-page-header` + `app-form-card` for full-page forms on a dedicated route. Inputs use the global `input/select/textarea` styles and `.field-group` for label + control composition (uppercase labels).

```
[PageHeader: title + (optional) secondary action]
[FormCard: title]
  [field-group: label + input]
  [field-group: label + select]
  [Submit row: btn primary + btn ghost]
```

Modal forms use §4.4 instead — do not nest `app-form-card` inside `app-modal-shell`.

### 4.4 Modal pattern

All new form/detail modals MUST use `app-modal-shell` + `.modal-form` unless listed in §8.

- **Visual reference:** `features/expenses/quick-add/quick-add-expense-fab` — `app-modal-shell` replicates this panel look (blur, radius, field labels, footer buttons). Quick-add keeps its own markup for FAB positioning but is the canonical visual target.
- **Form modal:**
  - Body: `<form class="modal-form">` with `.field` / `.form-grid` (§3.13.1).
  - Footer slot: `btn primary sm` (submit/save) **first**, then `btn secondary sm` (cancel). Do not use `btn ghost` for cancel in modals.
  - Optional icons in `<label>` (`<i class="fa-solid fa-…">`) matching quick-add.
  - Sizes: `md` (default, 440px) for standard forms; `lg` (36rem) when the form needs a wider grid (e.g. payment method create/edit).
- **Confirmation modal:** `app-confirm-dialog` — destructive actions, delete, simple yes/no.
- **Consumer contract:** smart component owns an `open` signal; `(close)` resets it. Row actions or header CTA set `open` to `true`.

```
[PageHeader "Crear" → open signal = true]
[app-modal-shell open]
  [form.modal-form]
    [.field × n]
  [footer: primary Save | secondary Cancel]
[app-confirm-dialog for delete]
```

**Reference implementations:** `features/categories/category-list`, `features/payment-methods/payment-method-list` (create, edit, manage-workspaces modals).

**Documented exceptions** (own markup, not `app-modal-shell`): see §8.

### 4.5 CRUD page pattern

Combines §4.1 (list) + create modal (§4.4) + edit modal (§4.4) + delete confirmation via `app-confirm-dialog`. The list owns the "create" CTA in the `app-page-header` `[actions]` slot; row actions come from `app-action-buttons` inside the `app-data-table` cell template; `edit`/`delete` events open the corresponding modal.

```
[PageHeader with "Crear" button → opens create modal]
[DataTable rows → action-buttons]
  - edit   → opens edit modal (pre-filled)
  - delete → opens confirm-dialog → on confirm, delete
```

### 4.6 User-scoped vs workspace-scoped pattern

Some entities (e.g. categories) exist at both the user scope and the workspace scope. The established pattern is **two dedicated smart components** rather than one component with a flag:

- `CategoryListComponent` (`features/categories/category-list/`) — personal categories.
- `WorkspaceCategoriesComponent` (`features/workspaces/workspace-categories/`) — workspace categories.

Both compose the same shared components (`app-page-header`, `app-data-table`, `app-category-badge`, `app-action-buttons`, `app-confirm-dialog`), but each owns its own data sources, routes, and tests. Do not introduce scope flags into the shared components; keep them scope-agnostic.

### 4.7 Settings shell pattern

`/user/settings` is a hub that links to per-area settings pages. Its layout is:

- `app-page-header` (settings title)
- A stack of `app-section-panel`s, each containing a column of button rows (text + chevron). No per-card color classes — all cards use the default surface.

This keeps the settings hub visually consistent with detail pages while clearly indicating that each row is a navigation action.

### 4.8 Notifications pattern

`/user/notifications` groups items by read/unread state:

- `app-page-header` (notifications title)
- `app-section-panel` for "Unread" — items rendered with a brand icon chip, slightly stronger background.
- `app-section-panel` for "Read" — same layout, muted icon chip.
- `app-empty-state` when there are no notifications.
- `app-loading-state` for the initial fetch.

This pattern is intentionally simple and does not introduce a `notification-card` shared component — the section panels are enough.

---

## 5. Theme strategy

- **Default theme:** dark. The `body` declares `color-scheme: dark` and the `:root` tokens represent the dark palette.
- **Light theme:** activated by setting `data-theme="light"` on the `body`. The selector `body[data-theme='light']` in `styles.scss` overrides the same custom properties (color + shadow) with the light palette.
- **Theme-agnostic tokens:** spacing (`--space-*`), radius (`--radius-*`), typography (`--font-*`) and z-index (`--z-*`) are not redefined per theme.
- **Contrast:** both themes target WCAG 2.1 AA — 4.5:1 for body text, 3:1 for non-text UI (icons, borders on interactive elements, focus rings). The `--color-brand-600` focus ring meets the 3:1 minimum against both `--color-bg` (dark) and the light palette's surface.
- **Transitions:** the `body` carries a 0.2s ease transition on `background-color` and `color` so the theme switch is not jarring.
- **Native form controls:** the `color-scheme` switch ensures that browser-native controls (scrollbars, `<select>` popups) match the active theme.

### 5.1 Theme coverage

The components and surfaces that render in both themes must consume theme-aware tokens. The table below is the contract for the canonical shared components — when adding a new shared component or touching the layout, keep it in sync.

| Component / surface                    | Theme-aware? | Notes                                                                                                                                                                                        |
| -------------------------------------- | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app-section-panel`                    | yes          | Uses `--color-surface-alt`, `--color-border`, `--shadow-*`.                                                                                                                                  |
| `app-page-header`                      | yes          | Uses `--color-text`, `--color-text-muted`.                                                                                                                                                   |
| `app-page-filters`                     | yes          | Uses `--color-filter-bg`, `--color-border`.                                                                                                                                                  |
| `app-summary-hero` (`navy` variant)    | yes          | Uses `--color-summary-hero-bg` / `--color-summary-hero-text` (added in Phase 6.4). Aliases to the sidebar palette in dark, brand-tinted light surface in light.                              |
| `app-summary-hero` (`surface` variant) | yes          | Uses `--color-surface-elevated`, `--color-text`.                                                                                                                                             |
| `app-data-table`                       | yes          | All surfaces (wrapper, header, rows, hover) are theme-aware tokens. If the table is rendered next to a dark hero on the same page, fix the hero — the table follows the theme automatically. |
| `app-pagination-bar`                   | yes          | Uses `--color-text-muted`, global `.btn.secondary`.                                                                                                                                          |
| `app-category-badge`                   | yes          | Colors come from the per-category `color` input; contrast helper picks foreground.                                                                                                           |
| `app-status-badge`                     | yes          | Global `.badge-*` styles are theme-aware.                                                                                                                                                    |
| `app-action-buttons`                   | yes          | Global `.icon-btn` styles are theme-aware.                                                                                                                                                   |
| `app-empty-state`                      | yes          | Uses `--color-text`, `--color-text-muted`.                                                                                                                                                   |
| `app-loading-state`                    | yes          | Uses `--color-border`, `--color-brand-600`.                                                                                                                                                  |
| `app-month-navigator`                  | yes          | Uses `--color-surface-alt`, `--color-border`.                                                                                                                                                |
| `app-modal-shell`                      | yes          | Uses `--color-overlay`, `--color-surface-elevated`.                                                                                                                                          |
| `app-form-card`                        | yes          | Uses `--color-surface-alt`, `--color-border`.                                                                                                                                                |
| `app-confirm-dialog`                   | yes          | Uses `--color-overlay`, `--color-surface-elevated`.                                                                                                                                          |
| `app-icon-picker`                      | yes          | Uses `--color-brand-600`, `--color-brand-50`.                                                                                                                                                |
| `app-server-table`                     | yes          | Uses `--color-surface-alt`, `--color-border`, `--color-border-muted`.                                                                                                                        |
| `app-cta-banner`                       | yes          | Inherits from `.card-surface`.                                                                                                                                                               |
| `app-topic-card`                       | yes          | Uses `--color-surface-alt`, `--color-border`.                                                                                                                                                |
| `app-nav` (sidebar)                    | yes          | Uses `--color-sidebar-*` tokens, all overridden in light theme (Phase 6.4): white surface, dark text, brand-tinted active state.                                                             |
| App topbar (in `app.scss`)             | yes          | Uses `--color-topbar-*` tokens, all overridden in light theme (Phase 6.4): light translucent surface, dark text, dark icons.                                                                 |

---

## 6. Accessibility conventions

These are baseline requirements for any new feature. Existing features were audited in Phase 6.3.

- **Visible focus:** all interactive elements must show a visible focus ring. The global rule `:focus-visible { outline: 2px solid var(--color-brand-600); outline-offset: 2px; }` in `styles.scss` provides the default; do not remove or override it for ordinary controls.
- **Labels:** every form control must have a paired `<label for="…">` + `id` (preferred) or an `aria-label` / `aria-labelledby`. Use `.field-group` to keep the label structure consistent.
- **Modals:** must declare `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` pointing to the title element. `app-modal-shell` and `app-confirm-dialog` already do this — keep that contract.
- **Modal close:** modals must close on `Escape` and (optionally) on backdrop click. `app-modal-shell` and `app-confirm-dialog` both implement this; any new modal must do the same.
- **Switches / toggles:** use `role="switch"` + `aria-checked`. There is no shared toggle component today; add one when the third use case appears (YAGNI for now).
- **Live regions:** success messages use `role="status"`; errors use `role="alert"`. Avoid `aria-live="assertive"` for routine feedback.
- **Contrast:** WCAG 2.1 AA minimum (4.5:1 body text, 3:1 non-text UI and focus). The category badge contrast helper (`shared/utils/contrast-color.ts`) ensures badge text is always legible.
- **Icon-only buttons:** must have an `aria-label`. `app-action-buttons`, `app-month-navigator`, `app-icon-picker`, the modal close button, and the topbar buttons all carry `aria-label`.
- **Tables:** use semantic `<th>` for headers, and `data-label` per `<td>` for the mobile stacked layout. `app-data-table` and `app-server-table` handle this internally.
- **Landmarks:** the sidebar uses `<aside>`-equivalent semantics via `app-nav`; the topbar uses `<header>`; main content sits inside the route outlet's `<main>`.

---

## 7. Don'ts / forbidden patterns

The following patterns are explicitly not allowed in feature code (and were removed during the redesign):

- **Re-declaring global styles in feature SCSS.** Do not re-declare `.btn`, `.badge`, `.filters`, `.total-hero`, `.expense-table`, `.pagination`, `.modal-backdrop`, `.modal-panel`, `.inline-form` (or any of their variants) inside a feature's `*.scss`. If a feature needs a global style tweak, change it in `styles.scss` only.
- **Hardcoded visual values.** Do not introduce `#hex`, `rgba(...)`, or arbitrary `px` (for color, padding, radius, shadow) outside `styles.scss`. Spacing must use `--space-*`; radius must use `--radius-*`; colors must use `--color-*` (or the per-category `color` for category badges).
- **Raw `<table>` markup.** Use `app-data-table` (client-side) or `app-server-table` (server-side sort/pagination). If neither fits, document the gap in the PR before adding a new table component.
- **Raw modals when `app-modal-shell` would suffice.** Use `app-modal-shell` + `.modal-form` for any new form/detail modal. Exceptions: `quick-add-expense-fab` (FAB overlay) and `fixed-expense-list` modals — see §8.
- **Raw confirmation prompts.** Use `app-confirm-dialog`; do not roll a one-off `.modal-panel` + yes/no buttons.
- **Icon-only buttons without `aria-label`.** If the icon is the only content, it must carry `aria-label` (or `aria-labelledby`).
- **Re-declaring the same gradient** (`linear-gradient(135deg, var(--color-brand-700), var(--color-brand-900))`) inline; use `var(--gradient-brand)`.
- **Using `z-index` without a token.** Always use `--z-*` (`--z-sidebar`, `--z-topbar`, `--z-mobile-nav`, `--z-fab`, `--z-dialog`).
- **Accessing `localStorage` outside `BrowserStorageService`.** `app-nav` is the only documented exception (§3.20) due to its proximity to the layout root; new code must use the service.
- **Importing across features.** A component in `features/expenses/...` must not import from `features/fixed-expenses/...`. The shared library is the only allowed cross-feature surface.

---

## 8. Migration notes

This document is the consolidated reference for the redesign that was implemented across Phases 0–6 of `openspec/changes/frontend-redesign-complete`. Key migration outcomes and intentional exceptions:

- **Phases 0–5 are complete.** The shared component library (`SectionPanelComponent`, `PageHeaderComponent`, `PageFiltersComponent`, `SummaryHeroComponent`, `DataTableComponent`, `PaginationBarComponent`, `CategoryBadgeComponent`, `StatusBadgeComponent`, `ActionButtonsComponent`, `EmptyStateComponent`, `LoadingStateComponent`, `MonthNavigatorComponent`, `ModalShellComponent`, `FormCardComponent`) is in place and consumed by all `/user/*` and `/admin/*` features. The redesign deliberately did not modify the topbar.
- **Hardcoded value audit (Phase 6.1) and duplication audit (Phase 6.2) passed.** No `#hex`, `rgba(...)`, or arbitrary `px` values live outside `styles.scss` in the touched features. No duplicated `.btn`, `.badge`, `.filters`, `.total-hero`, `.expense-table`, `.pagination`, `.modal-backdrop`, `.modal-panel`, or `.inline-form` blocks remain in feature SCSS.
- **Accessibility audit (Phase 6.3) passed.** All listed accessibility conventions are satisfied by the shared components and the consuming features.
- **Documented modal exceptions.** The following modals keep their own markup (not `app-modal-shell`). They remain exceptions for positioning/history reasons; new modals must not copy their structure — use §3.13 + §4.4 instead.
  - `features/expenses/quick-add/quick-add-expense-fab.{ts,html,scss}` — FAB overlay; **visual reference** for modal panel styling (blur, radius, `.field`, footer buttons). `app-modal-shell` is aligned to this look.
  - `features/fixed-expenses/fixed-expense-list/fixed-expense-list.{ts,html,scss}` — create and edit modals (legacy inline markup).
- **Modal alignment (2026):** `app-modal-shell` was updated to match quick-add visuals. Settings modals (`category-list`, `payment-method-list`) use `.modal-form` without nested `app-form-card`. Workspace create modals (`workspace-categories`, `workspace-payment-methods`) should follow the same pattern when touched.
- **`app-server-table` is retained.** It is a pre-existing component used by `features/admin/users/user-list` and `features/admin/administrators/administrator-list` for server-side sort and pagination. It is not the default for new tables — prefer `app-data-table` unless the data set must be paginated server-side.
- **Pre-existing shared components outside the redesign.** The following shared components predate the redesign and are not part of the Phase 0 spec; they continue to be used as-is and are out of scope for this document: `crud-form`, `plan-card`, `upgrade-prompt`, `category-toggle-item`, `language-switcher`, `public-shell`, `content-hero`, `workspace-switcher`. They are listed in `docs/designs.md` §8 of the previous version but are not part of the new design system reference.
