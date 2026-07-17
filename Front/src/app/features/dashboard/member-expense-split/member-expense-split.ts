import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  input,
  OnInit,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { AnalyticsService } from '../../../core/services/analytics.service';
import { MemberSplitData } from '../../../core/models/analytics.model';
import { formatCurrency } from '../../../shared/pipes/currency-format.pipe';

const MEMBER_COLORS = [
  '#6366f1', '#f59e0b', '#10b981', '#ef4444',
  '#8b5cf6', '#06b6d4', '#f97316', '#84cc16',
];

@Component({
  selector: 'app-member-expense-split',
  imports: [TranslateModule],
  templateUrl: './member-expense-split.html',
  styleUrl: './member-expense-split.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MemberExpenseSplitComponent implements OnInit {
  readonly workspaceId = input.required<string>();
  readonly currencyCode = input<string>('USD');

  private readonly analyticsService = inject(AnalyticsService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly translate = inject(TranslateService);

  readonly loading = signal(false);
  readonly splitData = signal<MemberSplitData | null>(null);
  readonly year = signal(new Date().getFullYear());
  readonly month = signal(new Date().getMonth() + 1);

  readonly monthLabel = computed(() => {
    const date = new Date(this.year(), this.month() - 1);
    return new Intl.DateTimeFormat(this.translate.currentLang ?? 'en', { month: 'long', year: 'numeric' }).format(date);
  });

  readonly isNextMonthDisabled = computed(() => {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;
    return this.year() > currentYear || (this.year() === currentYear && this.month() >= currentMonth);
  });

  readonly membersWithMeta = computed(() => {
    const data = this.splitData();
    if (!data) return [];
    const total = parseFloat(data.total) || 1;
    return data.members.map((m, i) => ({
      ...m,
      color: MEMBER_COLORS[i % MEMBER_COLORS.length],
      paidNum: parseFloat(m.paid),
      balanceNum: parseFloat(m.balance),
      barPct: Math.round((parseFloat(m.paid) / total) * 100),
    }));
  });

  ngOnInit(): void {
    this.loadData();
  }

  changeMonth(delta: number): void {
    let y = this.year();
    let m = this.month() + delta;
    if (m < 1) { m = 12; y--; }
    if (m > 12) { m = 1; y++; }
    this.year.set(y);
    this.month.set(m);
    this.loadData();
  }

  resetMonth(): void {
    const now = new Date();
    this.year.set(now.getFullYear());
    this.month.set(now.getMonth() + 1);
    this.loadData();
  }

  fmt(value: number | string): string {
    return formatCurrency(value, this.currencyCode(), true);
  }

  private loadData(): void {
    this.loading.set(true);
    this.analyticsService
      .memberSplit(this.workspaceId(), this.year(), this.month())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.splitData.set(r.data);
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }
}
