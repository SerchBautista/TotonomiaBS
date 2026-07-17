import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SummaryHeroComponent } from './summary-hero';

// The tests in `theme coverage (token-override contract)` need to read
// `src/styles.scss` and `summary-hero.scss` from disk. vitest runs in Node
// and these modules are available at runtime; we use the global require to
// keep the spec free of TypeScript imports that would require pulling
// `@types/node` into the test config.
declare const require: (id: string) => unknown;
declare const process: { cwd: () => string };

interface LightThemeBlock {
  /** Map of `--token -> raw declared value` from the body[data-theme='light'] block. */
  declarations: Map<string, string>;
}

function readScssSource(relativePath: string): string {
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

function extractLightThemeBlock(stylesSource: string): LightThemeBlock {
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
  // Strip `// ...` and `/* ... */` comments before parsing — a commented-out
  // override must NOT satisfy the contract.
  source = source.replace(/\/\/[^\n]*/g, '');
  source = source.replace(/\/\*[\s\S]*?\*\//g, '');
  const declarations = new Map<string, string>();
  const declRe = /--([a-z0-9-]+)\s*:\s*([^;]+);/gi;
  let m: RegExpExecArray | null;
  while ((m = declRe.exec(source))) {
    declarations.set(m[1].toLowerCase(), m[2].trim());
  }
  return { declarations };
}

describe('SummaryHeroComponent', () => {
  let fixture: ComponentFixture<SummaryHeroComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SummaryHeroComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(SummaryHeroComponent);
  });

  it('renders the label and value', () => {
    fixture.componentRef.setInput('label', 'Total Spent');
    fixture.componentRef.setInput('value', '$850.00');
    fixture.detectChanges();

    const label = fixture.nativeElement.querySelector('.summary-hero__label') as HTMLElement;
    const value = fixture.nativeElement.querySelector('.summary-hero__value') as HTMLElement;

    expect(label.textContent).toBe('Total Spent');
    expect(value.textContent).toBe('$850.00');
  });

  it('uses the navy variant by default', () => {
    fixture.componentRef.setInput('label', 'Total');
    fixture.componentRef.setInput('value', '$0.00');
    fixture.detectChanges();

    const hero = fixture.nativeElement.querySelector('.summary-hero') as HTMLElement;
    expect(hero.classList.contains('summary-hero--surface')).toBe(false);
  });

  it('applies the surface variant when requested', () => {
    fixture.componentRef.setInput('label', 'Total');
    fixture.componentRef.setInput('value', '$0.00');
    fixture.componentRef.setInput('variant', 'surface');
    fixture.detectChanges();

    const hero = fixture.nativeElement.querySelector('.summary-hero') as HTMLElement;
    expect(hero.classList.contains('summary-hero--surface')).toBe(true);
  });

  describe('theme coverage (token-override contract)', () => {
    /**
     * Mirrors the data-table contract: every CSS custom property the
     * component depends on must be overridden under `body[data-theme='light']`
     * with a literal value. The old test (absence of inline `style="..."`)
     * was too weak — it would have missed the same aliasing gotcha that
     * broke the data-table.
     */
    let stylesSource: string;
    let heroSource: string;
    let lightBlock: LightThemeBlock;
    let usedTokens: Set<string>;

    beforeAll(() => {
      stylesSource = readScssSource('src/styles.scss');
      heroSource = readScssSource('src/app/shared/summary-hero/summary-hero.scss');
      lightBlock = extractLightThemeBlock(stylesSource);
      usedTokens = collectVarReferences(heroSource);
    });

    it('uses only design tokens (no hardcoded colors)', () => {
      expect(heroSource).not.toMatch(/#[0-9a-f]{3,8}\b/i);
      expect(heroSource).not.toMatch(/\brgba?\s*\(/i);
    });

    it('exposes the expected set of theme-aware tokens to the summary-hero contract', () => {
      const expected = [
        'color-summary-hero-bg',
        'color-summary-hero-text',
        'color-surface-elevated',
        'color-border',
        'color-text',
        'radius-md',
        'shadow-md',
        'space-2',
        'space-6',
        'space-8',
        'font-weight-semi',
        'font-weight-black',
      ];
      for (const token of expected) {
        expect(usedTokens.has(token)).toBe(true);
      }
    });

    it('overrides the navy-variant tokens in body[data-theme="light"] with a literal value', () => {
      // `--color-summary-hero-bg` and `--color-summary-hero-text` are the
      // alias tokens that drive the navy variant. They are declared in
      // :root as `var(--color-sidebar-bg)` / `var(--color-sidebar-text)`.
      // The light block re-declares `--color-sidebar-bg/-text`, but the
      // alias's computed value freezes at :root (same gotcha as the
      // data-table), so the alias must be re-declared here as a literal
      // for the navy variant to actually pick up the light palette.
      const bg = lightBlock.declarations.get('color-summary-hero-bg');
      const text = lightBlock.declarations.get('color-summary-hero-text');
      expect(bg, 'body[data-theme=light] must override --color-summary-hero-bg').toBeDefined();
      expect(text, 'body[data-theme=light] must override --color-summary-hero-text').toBeDefined();
      expect(
        bg,
        '--color-summary-hero-bg must be a literal in body[data-theme=light] (alias would freeze at :root)',
      ).not.toMatch(/var\s*\(/i);
      expect(
        text,
        '--color-summary-hero-text must be a literal in body[data-theme=light] (alias would freeze at :root)',
      ).not.toMatch(/var\s*\(/i);
      // The dark `--color-summary-hero-bg` was a flat color (sidebar navy).
      // The light override must not be that.
      expect(bg).not.toBe('#122140');
    });
  });
});
