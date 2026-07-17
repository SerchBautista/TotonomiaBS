import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { DecimalPipe } from '@angular/common';
import { CategorySummary } from '../../../core/models/analytics.model';
import { formatCurrency } from '../../../shared/pipes/currency-format.pipe';

export interface DisplayCategory {
  id: string;
  name: string;
  icon: string | null;
  color: string;
  total: string;
  count: number;
  percentage: number;
  isOther?: boolean;
}

const MAX_CATEGORIES = 7;

const FALLBACK_COLORS = [
  'var(--color-brand-700)',
  'var(--color-accent)',
  'var(--color-warning)',
  'var(--color-success)',
  'var(--color-danger)',
  'var(--color-brand-600)',
  'var(--color-brand-800)',
  'var(--color-budget-committed)',
  'var(--color-budget-spent)',
  'var(--color-brand-900)',
];

@Component({
  selector: 'app-spending-breakdown',
  imports: [TranslateModule, DecimalPipe],
  templateUrl: './spending-breakdown.html',
  styleUrl: './spending-breakdown.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SpendingBreakdownComponent {
  readonly categories = input.required<CategorySummary[]>();
  readonly currencyCode = input<string>('USD');

  readonly openDetail = output<{ filter: 'all' | 'others' }>();

  readonly total = computed(() =>
    this.categories().reduce((sum, c) => sum + this.parseAmount(c.total), 0)
  );

  readonly displayCategories = computed<DisplayCategory[]>(() => {
    const all = [...this.categories()].sort((a, b) => this.parseAmount(b.total) - this.parseAmount(a.total));
    if (all.length === 0) return [];

    const total = this.total();
    const top = all.slice(0, MAX_CATEGORIES);
    const rest = all.slice(MAX_CATEGORIES);
    const restTotal = rest.reduce((sum, c) => sum + this.parseAmount(c.total), 0);

    const mapped: DisplayCategory[] = top.map((c, i) => ({
      id: c.id,
      name: c.name,
      icon: c.icon,
      color: c.color ?? FALLBACK_COLORS[i % FALLBACK_COLORS.length],
      total: c.total,
      count: c.count,
      percentage: total > 0 ? (this.parseAmount(c.total) / total) * 100 : 0,
    }));

    if (rest.length > 0) {
      mapped.push({
        id: '__others__',
        name: '__others__',
        icon: null,
        color: 'var(--color-text-muted)',
        total: restTotal.toFixed(2),
        count: rest.reduce((sum, c) => sum + c.count, 0),
        percentage: total > 0 ? (restTotal / total) * 100 : 0,
        isOther: true,
      });
    }

    return mapped;
  });

  readonly donutSegments = computed(() => {
    const cats = this.displayCategories();
    let cumulative = 0;
    return cats.map(c => {
      const start = cumulative;
      cumulative += c.percentage;
      return {
        ...c,
        startPct: start,
        endPct: cumulative,
      };
    });
  });

  formatAmount(value: number): string {
    return formatCurrency(value, this.currencyCode(), true);
  }

  parseAmount(value: string): number {
    return parseFloat(value);
  }

  onCategoryClick(cat: DisplayCategory): void {
    if (cat.isOther) {
      this.openDetail.emit({ filter: 'others' });
    }
  }

  onViewAll(): void {
    this.openDetail.emit({ filter: 'all' });
  }
}
