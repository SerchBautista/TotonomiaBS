import { Component, input } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { DataTableComponent, TableColumn } from './data-table';
import { TableCellDirective } from './table-cell.directive';

// The tests in `theme coverage (token-override contract)` need to read
// `src/styles.scss` and `data-table.scss` from disk. vitest runs in Node
// and these modules are available at runtime; we use the global require to
// keep the spec free of TypeScript imports that would require pulling
// `@types/node` into the test config.
declare const require: (id: string) => unknown;
declare const process: { cwd: () => string };

interface TestRow {
  id: number;
  name: string;
  amount: number;
}

@Component({
  selector: 'app-data-table-test-host',
  template: `
    <app-data-table
      [columns]="columns()"
      [rows]="rows()"
      [loading]="loading()"
      [emptyActionLabel]="emptyActionLabel()"
      [ariaLabel]="ariaLabel()"
    >
      <ng-template appTableCell="name" let-row>
        <strong>{{ row.name }}</strong>
      </ng-template>
    </app-data-table>
  `,
  standalone: true,
  imports: [DataTableComponent, TableCellDirective],
})
class TestHostComponent {
  readonly columns = input<TableColumn<TestRow>[]>([]);
  readonly rows = input<TestRow[]>([]);
  readonly loading = input<boolean>(false);
  readonly emptyActionLabel = input<string | undefined>();
  readonly ariaLabel = input<string | undefined>();
}

/**
 * Static-analysis helpers for the data-table theme contract.
 *
 * jsdom does not implement the CSS cascade for custom properties (it does not
 * resolve `var(--*)` references inside stylesheet rules, and
 * `getComputedStyle().backgroundColor` returns `rgba(0,0,0,0)` because the
 * component's SCSS never gets parsed by jsdom). That makes it impossible to
 * verify the *actual rendered color* from inside a vitest/jsdom test.
 *
 * The real risk we are guarding against is a token being referenced by a
 * component but missing an override in `body[data-theme='light']`. This was
 * the actual root cause of the previous "table is still dark in light theme"
 * regression: `--color-table-row-bg` was declared in `:root` as an alias
 * (`var(--color-surface-alt)`), and CSS custom property values are computed
 * at the *declaration site* — the alias froze at the dark value and the
 * light-theme override of `--color-surface-alt` never reached the table rows.
 *
 * The fix is to either (a) re-declare the alias under
 * `body[data-theme='light']` with a literal light value, or (b) consume the
 * underlying token directly. The tests below enforce the contract on
 * `src/styles.scss`: every CSS custom property consumed by
 * `data-table.scss` must be overridden under `body[data-theme='light']` with
 * a literal value (i.e. not a `var(--*)` reference). This is the same
 * contract the user expects from a `getComputedStyle` assertion, expressed
 * in a form jsdom can actually evaluate.
 */
function readScssSource(relativePath: string): string {
  // `require` is declared above as a global to avoid importing `node:fs` /
  // `node:path` (which would require `@types/node` in the test config).
  // vitest runs in Node, so `require` is available at runtime. The spec
  // file lives at `src/app/shared/data-table/`, so `process.cwd()` is the
  // project root and we can build the absolute path from there.
  const path = require('node:path') as { resolve: (...parts: string[]) => string };
  const fs = require('node:fs') as { readFileSync: (path: string, encoding: string) => string };
  const absolute = path.resolve(process.cwd(), relativePath);
  return fs.readFileSync(absolute, 'utf8');
}

/** Extract every `var(--foo)` reference from a CSS source. */
function collectVarReferences(source: string): Set<string> {
  const refs = new Set<string>();
  const re = /var\(\s*--([a-z0-9-]+)/gi;
  let m: RegExpExecArray | null;
  while ((m = re.exec(source))) {
    refs.add(m[1].toLowerCase());
  }
  return refs;
}

/**
 * Walk a chain of `var(--foo)` aliases starting from `token` and return the
 * set of tokens whose final value (after following aliases declared in
 * `:root`) is itself a `var(--*)` reference rather than a literal. Those are
 * the tokens whose *declared value* is an alias — and per the CSS spec, an
 * alias's computed value is frozen at the element where it is declared, so
 * `body[data-theme='light']` must re-declare them to override the theme.
 */
function aliasedTokens(token: string, rootBlockSource: string): Set<string> {
  const aliased = new Set<string>();
  // Find a declaration of `--token` whose value still contains `var(--...)`.
  const declRe = new RegExp(`--${token}\\s*:\\s*([^;]+);`, 'i');
  const m = rootBlockSource.match(declRe);
  if (m) {
    const valueRefs = collectVarReferences(m[1]);
    if (valueRefs.size > 0) {
      aliased.add(token);
      // Also walk the underlying tokens (one level is enough for our system).
      for (const ref of valueRefs) {
        aliased.add(ref);
      }
    }
  }
  return aliased;
}

interface LightThemeBlock {
  /** Map of `--token -> raw declared value` from the body[data-theme='light'] block. */
  declarations: Map<string, string>;
  /** Source text of the block. */
  source: string;
}

function extractLightThemeBlock(stylesSource: string): LightThemeBlock {
  // Find the `body[data-theme='light'] { ... }` block. The closing brace
  // matches the opening one; a simple brace-counting scan is sufficient
  // because the SCSS does not contain nested rule bodies inside the block.
  const openRe = /body\[data-theme=['"]light['"]\]\s*\{/i;
  const match = openRe.exec(stylesSource);
  if (!match) {
    throw new Error('Could not find `body[data-theme=light]` block in styles.scss');
  }
  const start = match.index + match[0].length;
  let depth = 1;
  let i = start;
  while (i < stylesSource.length && depth > 0) {
    const ch = stylesSource[i];
    if (ch === '{') depth++;
    else if (ch === '}') depth--;
    i++;
  }
  if (depth !== 0) {
    throw new Error('Unbalanced braces in `body[data-theme=light]` block');
  }
  let source = stylesSource.slice(start, i - 1);
  // Strip `// ...` line comments before parsing — a commented-out override
  // must NOT satisfy the contract (that was the original bug: the override
  // existed on a commented line in some PRs and the test never noticed).
  source = source.replace(/\/\/[^\n]*/g, '');
  // Strip `/* ... */` block comments too, for the same reason.
  source = source.replace(/\/\*[\s\S]*?\*\//g, '');
  const declarations = new Map<string, string>();
  const declRe = /--([a-z0-9-]+)\s*:\s*([^;]+);/gi;
  let m: RegExpExecArray | null;
  while ((m = declRe.exec(source))) {
    declarations.set(m[1].toLowerCase(), m[2].trim());
  }
  return { declarations, source };
}

describe('DataTableComponent', () => {
  let fixture: ComponentFixture<TestHostComponent>;

  const columns: TableColumn<TestRow>[] = [
    { key: 'id', header: 'ID' },
    { key: 'name', header: 'Name' },
    { key: 'amount', header: 'Amount', align: 'right' },
  ];

  const rows: TestRow[] = [
    { id: 1, name: 'A', amount: 100 },
    { id: 2, name: 'B', amount: 200 },
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TestHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TestHostComponent);
  });

  it('renders the table headers', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', rows);
    fixture.detectChanges();

    const headers = fixture.nativeElement.querySelectorAll('th');
    expect(headers.length).toBe(3);
    expect(headers[0].textContent).toContain('ID');
    expect(headers[1].textContent).toContain('Name');
    expect(headers[2].textContent).toContain('Amount');
  });

  it('renders rows with default cell values', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', rows);
    fixture.detectChanges();

    const cells = fixture.nativeElement.querySelectorAll('tbody td');
    expect(cells[0].textContent).toContain('1');
    expect(cells[1].textContent).toContain('A');
    expect(cells[2].textContent).toContain('100');
  });

  it('renders custom cell templates via the appTableCell directive', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', rows);
    fixture.detectChanges();

    const nameCells = fixture.nativeElement.querySelectorAll('tbody td');
    const strong = nameCells[1].querySelector('strong');
    expect(strong).toBeTruthy();
    expect(strong.textContent).toBe('A');
  });

  it('applies right alignment class when configured', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', rows);
    fixture.detectChanges();

    const amountHeader = fixture.nativeElement.querySelectorAll('th')[2] as HTMLElement;
    const amountCell = fixture.nativeElement.querySelectorAll('tbody td')[2] as HTMLElement;
    expect(amountHeader.classList.contains('data-table__cell--right')).toBe(true);
    expect(amountCell.classList.contains('data-table__cell--right')).toBe(true);
  });

  it('shows a loading state', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', rows);
    fixture.componentRef.setInput('loading', true);
    fixture.detectChanges();

    const table = fixture.nativeElement.querySelector('table');
    const loading = fixture.nativeElement.querySelector('.data-table__loading');
    expect(table).toBeNull();
    expect(loading).toBeTruthy();
    expect(loading.querySelector('.loading-spinner')).toBeTruthy();
  });

  it('shows an empty state when no rows are provided', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', []);
    fixture.detectChanges();

    const empty = fixture.nativeElement.querySelector('.data-table__empty') as HTMLElement;
    expect(empty).toBeTruthy();
    expect(empty.textContent).toContain('No hay registros');
  });

  it('emits emptyAction when the empty CTA is clicked', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', []);
    fixture.componentRef.setInput('emptyActionLabel', 'Crear registro');
    fixture.detectChanges();

    const dataTable = fixture.debugElement.query(By.directive(DataTableComponent));
    const component = dataTable.componentInstance as DataTableComponent<TestRow>;
    const spy = vi.fn();
    component.emptyAction.subscribe(spy);

    const button = fixture.nativeElement.querySelector(
      '.data-table__empty button',
    ) as HTMLButtonElement;
    expect(button).toBeTruthy();
    button.click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('adds data-label attributes to cells for mobile layout', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', rows);
    fixture.detectChanges();

    const cells = fixture.nativeElement.querySelectorAll('tbody td');
    expect(cells[0].getAttribute('data-label')).toBe('ID');
    expect(cells[1].getAttribute('data-label')).toBe('Name');
    expect(cells[2].getAttribute('data-label')).toBe('Amount');
  });

  it('attaches an aria-label to the table when provided', () => {
    fixture.componentRef.setInput('columns', columns);
    fixture.componentRef.setInput('rows', rows);
    fixture.componentRef.setInput('ariaLabel', 'Lista de gastos');
    fixture.detectChanges();

    const table = fixture.nativeElement.querySelector('table') as HTMLElement;
    expect(table.getAttribute('aria-label')).toBe('Lista de gastos');
  });

  describe('theme coverage (token-override contract)', () => {
    /**
     * These tests guard against the regression that the user already saw once
     * (`<app-data-table>` rendering dark in light theme on `/user/expenses`
     * and `/user/fixed-expenses`).
     *
     * The actual root cause was: `--color-table-row-bg` was declared in
     * `:root` as an alias (`var(--color-surface-alt)`). CSS custom property
     * values are computed at the element where they are *declared*, so the
     * alias froze at the dark value. The light-theme override of
     * `--color-surface-alt` did not reach the alias, and every table row
     * rendered with the dark surface. The previous fix attempt only checked
     * that no inline `style="background:..."` was applied — a negative
     * contract that catches one class of regression but not this one.
     *
     * The contract enforced below is positive: every CSS custom property
     * used by the table must have a light-theme override in
     * `body[data-theme='light']` with a *literal* value (not another
     * `var(--*)` reference, which would re-introduce the same aliasing
     * problem).
     */
    let stylesSource: string;
    let dataTableSource: string;
    let lightBlock: LightThemeBlock;
    let usedTokens: Set<string>;

    beforeAll(() => {
      stylesSource = readScssSource('src/styles.scss');
      dataTableSource = readScssSource('src/app/shared/data-table/data-table.scss');
      lightBlock = extractLightThemeBlock(stylesSource);
      usedTokens = collectVarReferences(dataTableSource);
    });

    it('uses only design tokens (no hardcoded colors)', () => {
      // The component must consume its palette exclusively through
      // `var(--color-*)` tokens — no `#hex` / `rgb()` / `rgba()` literals.
      expect(dataTableSource).not.toMatch(/#[0-9a-f]{3,8}\b/i);
      expect(dataTableSource).not.toMatch(/\brgba?\s*\(/i);
    });

    it('exposes the expected set of theme-aware tokens to the data-table contract', () => {
      // The docs/designs.md table contract says the data-table uses these
      // tokens. If a future change drops one of them, this test will tell
      // us to update the docs (and vice versa). The list mirrors the
      // `var(--*)` references that actually appear in data-table.scss;
      // the alias tokens (`color-table-header-bg`, `color-table-row-bg`,
      // `color-table-row-hover`) are declared in styles.scss so the table
      // can opt into either the alias or the underlying surface token.
      const expected = [
        'color-table-row-bg',
        'color-table-row-hover',
        'color-surface-alt',
        'color-surface-elevated',
        'color-border',
        'color-border-muted',
        'color-text-muted',
        'radius-md',
        'shadow-sm',
        'font-weight-bold',
        'space-2',
        'space-3',
        'space-4',
      ];
      for (const token of expected) {
        expect(usedTokens.has(token)).toBe(true);
      }
    });

    it('overrides every table-relevant token in body[data-theme="light"] with a literal value', () => {
      // Tokens that drive the table's surface, header, rows, hover and
      // borders. Each MUST be present in the light-theme block as a
      // literal value (no `var(--*)` chain) — otherwise the alias freezes
      // at the dark value and the table renders dark in light theme.
      const required = [
        'color-surface-alt',
        'color-surface-elevated',
        'color-border',
        'color-border-muted',
        'color-table-header-bg',
        'color-table-row-bg',
        'color-table-row-hover',
      ];
      for (const token of required) {
        const value = lightBlock.declarations.get(token);
        expect(value, `body[data-theme='light'] must override --${token}`).toBeDefined();
        expect(
          value,
          `--${token} must be a literal in body[data-theme='light']; got "${value}" (aliased values are frozen at the declaration site)`,
        ).not.toMatch(/var\s*\(/i);
        // Sanity: the literal must not be the dark-theme value. The dark
        // --color-surface-alt is `rgba(19, 27, 43, 0.94)`; the light one
        // is `rgba(247, 250, 255, 0.96)`.
        expect(value).not.toBe('rgba(19, 27, 43, 0.94)');
        expect(value).not.toBe('rgba(26, 35, 55, 0.92)');
      }
    });

    it('overrides the text color so cell text is dark on a light surface', () => {
      const text = lightBlock.declarations.get('color-text');
      expect(text).toBeDefined();
      expect(text).not.toMatch(/var\s*\(/i);
      // The dark theme --color-text is `#eff6ff`. The light override must
      // not be the dark value.
      expect(text).not.toBe('#eff6ff');
    });

    it('regression guard: the aliasing pattern that broke the table is documented in styles.scss', () => {
      // This is a meta-test that points future readers at the explanation
      // of the aliasing gotcha. If the comment is ever removed, the next
      // person who tries to "clean up" the alias tokens will be warned by
      // a failing test and can read the comment that this test points at.
      expect(stylesSource).toMatch(
        /Table\s*\/\s*filter helpers[\s\S]{0,800}frozen at :root/i,
      );
    });
  });
});
