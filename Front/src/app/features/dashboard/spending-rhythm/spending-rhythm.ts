import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { HeatmapDay } from '../../../core/models/analytics.model';
import { formatCurrency } from '../../../shared/pipes/currency-format.pipe';

interface BarSegment {
  day: number;
  total: number;
  count: number;
  heightPct: number;
  isFuture: boolean;
  isEmpty: boolean;
}

@Component({
  selector: 'app-spending-rhythm',
  imports: [TranslateModule],
  templateUrl: './spending-rhythm.html',
  styleUrl: './spending-rhythm.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SpendingRhythmComponent {
  readonly data = input.required<HeatmapDay[]>();
  readonly year = input.required<number>();
  readonly month = input.required<number>();
  readonly currencyCode = input<string>('USD');
  readonly today = input<Date>(new Date());

  private readonly translate = inject(TranslateService);

  readonly tooltipBar = signal<BarSegment | null>(null);
  readonly tooltipVisible = signal(false);
  readonly tooltipX = signal(0);
  readonly tooltipY = signal(0);
  readonly focusedDay = signal<number | null>(null);

  readonly monthTotal = computed(() =>
    this.data().reduce((sum, d) => sum + parseFloat(d.total), 0)
  );

  readonly daysWithSpending = computed(() =>
    this.data().filter(d => parseFloat(d.total) > 0).length
  );

  readonly dailyAverage = computed(() => {
    const days = this.daysWithSpending();
    return days > 0 ? this.monthTotal() / days : 0;
  });

  readonly bars = computed<BarSegment[]>(() => {
    const dataMap = new Map<number, { total: number; count: number }>();
    for (const d of this.data()) {
      const day = new Date(d.date + 'T00:00:00').getDate();
      dataMap.set(day, { total: parseFloat(d.total), count: d.count });
    }

    const maxTotal = Math.max(...[...dataMap.values()].map(v => v.total), 1);
    const today = this.today();
    const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    const daysInMonth = new Date(this.year(), this.month(), 0).getDate();
    const bars: BarSegment[] = [];

    for (let day = 1; day <= daysInMonth; day++) {
      const entry = dataMap.get(day);
      const total = entry?.total ?? 0;
      const count = entry?.count ?? 0;
      const cellDate = new Date(this.year(), this.month() - 1, day);
      bars.push({
        day,
        total,
        count,
        heightPct: maxTotal > 0 ? (total / maxTotal) * 100 : 0,
        isFuture: cellDate > todayDate,
        isEmpty: total === 0,
      });
    }

    return bars;
  });

  readonly averageLinePct = computed(() => {
    const maxTotal = Math.max(...this.bars().map(b => b.total), 1);
    return (this.dailyAverage() / maxTotal) * 100;
  });

  showTooltip(bar: BarSegment, event: MouseEvent | FocusEvent): void {
    this.tooltipBar.set(bar);
    this.focusedDay.set(bar.day);
    this.tooltipVisible.set(true);

    const target = (event.target ?? event.currentTarget) as Element | null;
    const container = target?.closest('.rhythm-chart') as HTMLElement | null;
    if (!target || !container) {
      return;
    }

    const targetRect = target.getBoundingClientRect();
    const containerRect = container.getBoundingClientRect();
    this.tooltipX.set(targetRect.left - containerRect.left + targetRect.width / 2);
    this.tooltipY.set(targetRect.top - containerRect.top - 8);
  }

  hideTooltip(): void {
    this.tooltipVisible.set(false);
    this.focusedDay.set(null);
  }

  ariaLabel(bar: BarSegment): string {
    const amount = this.formatAmount(bar.total);
    const countKey = bar.count === 1 ? 'dashboard.expense_count' : 'dashboard.expenses_count';
    const countText = this.translate.instant(countKey, { count: bar.count });
    return this.translate.instant('dashboard.rhythm_bar_aria', {
      day: bar.day,
      amount,
      count: countText,
    });
  }

  formatAmount(value: number): string {
    return formatCurrency(value, this.currencyCode(), true);
  }
}
