import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { QuickAddExpenseService } from '../../../core/services/quick-add-expense.service';
import { BudgetsService } from '../../../core/services/budgets.service';
import { AnalyticsService } from '../../../core/services/analytics.service';
import { ToastService } from '../../../core/services/toast.service';
import { BudgetStatusResponse } from '../../../core/models/budget.model';
import { HeatmapDay, SummaryData } from '../../../core/models/analytics.model';
import { BudgetStatusWidgetComponent } from '../../budgets/budget-status-widget/budget-status-widget';
import {
  BudgetAdjustmentModalComponent,
  AdjustmentModalData,
} from '../../budgets/budget-adjustment-modal/budget-adjustment-modal';
import { SpendingRhythmComponent } from '../spending-rhythm/spending-rhythm';
import { SpendingBreakdownComponent } from '../spending-breakdown/spending-breakdown';
import { SpendingBreakdownDetailModalComponent } from '../spending-breakdown-detail-modal/spending-breakdown-detail-modal';
import { MemberExpenseSplitComponent } from '../member-expense-split/member-expense-split';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { MonthNavigatorComponent } from '../../../shared/month-navigator/month-navigator';

@Component({
  selector: 'app-user-dashboard',
  imports: [
    TranslateModule,
    BudgetStatusWidgetComponent,
    BudgetAdjustmentModalComponent,
    SpendingRhythmComponent,
    SpendingBreakdownComponent,
    SpendingBreakdownDetailModalComponent,
    MemberExpenseSplitComponent,
    SectionPanelComponent,
    MonthNavigatorComponent,
  ],
  templateUrl: './user-dashboard.html',
  styleUrl: './user-dashboard.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserDashboardComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly workspaceContext = inject(WorkspaceContextService);
  readonly quickAddService = inject(QuickAddExpenseService);
  private readonly budgetsService = inject(BudgetsService);
  private readonly analyticsService = inject(AnalyticsService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);

  readonly loading = signal(true);
  readonly workspaces = this.workspaceContext.workspaces;
  readonly currentWorkspace = this.workspaceContext.selectedWorkspace;
  readonly budgetStatus = signal<BudgetStatusResponse | null>(null);
  readonly budgetYear = signal(new Date().getFullYear());
  readonly budgetMonth = signal(new Date().getMonth() + 1);
  readonly isBudgetNextMonthDisabled = computed(() => {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;
    return (
      this.budgetYear() > currentYear ||
      (this.budgetYear() === currentYear && this.budgetMonth() >= currentMonth)
    );
  });

  readonly heatmapData = signal<HeatmapDay[]>([]);
  readonly heatmapYear = signal(new Date().getFullYear());
  readonly heatmapMonth = signal(new Date().getMonth() + 1);
  readonly isHeatmapNextMonthDisabled = computed(() => {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;
    return (
      this.heatmapYear() > currentYear ||
      (this.heatmapYear() === currentYear && this.heatmapMonth() >= currentMonth)
    );
  });
  readonly heatmapLoading = signal(false);
  readonly summaryData = signal<SummaryData | null>(null);
  readonly summaryLoading = signal(false);
  readonly detailModalOpen = signal(false);
  readonly detailModalFilter = signal<'all' | 'others'>('all');

  readonly adjustmentModalOpen = signal(false);
  readonly adjustmentModalData = signal<AdjustmentModalData>({
    workspaceId: '',
    month: '',
    categories: [],
  });

  private wasInitialized = false;

  constructor() {
    effect(() => {
      const wsId = this.workspaceContext.currentWorkspaceId();
      if (!wsId || !this.wasInitialized) {
        this.wasInitialized = true;
        return;
      }
      this.budgetStatus.set(null);
      this.heatmapData.set([]);
      this.summaryData.set(null);
      this.loadBudgetStatus();
      this.loadHeatmap();
      this.loadSummary();
    });
  }

  async ngOnInit(): Promise<void> {
    await this.workspaceContext.ensureLoaded();
    this.loading.set(false);
    this.loadBudgetStatus();
    this.loadHeatmap();
    this.loadSummary();
  }

  changeMonth(delta: number): void {
    let y = this.heatmapYear();
    let m = this.heatmapMonth() + delta;
    if (m < 1) {
      m = 12;
      y--;
    }
    if (m > 12) {
      m = 1;
      y++;
    }
    this.heatmapYear.set(y);
    this.heatmapMonth.set(m);
    this.loadHeatmap();
    this.loadSummary();
  }

  resetHeatmapMonth(): void {
    const now = new Date();
    this.heatmapYear.set(now.getFullYear());
    this.heatmapMonth.set(now.getMonth() + 1);
    this.loadHeatmap();
    this.loadSummary();
  }

  get monthLabel(): string {
    const date = new Date(this.heatmapYear(), this.heatmapMonth() - 1);
    return new Intl.DateTimeFormat(this.translate.currentLang ?? 'en', {
      month: 'long',
      year: 'numeric',
    }).format(date);
  }

  get budgetMonthLabel(): string {
    const date = new Date(this.budgetYear(), this.budgetMonth() - 1);
    return new Intl.DateTimeFormat(this.translate.currentLang ?? 'en', {
      month: 'long',
      year: 'numeric',
    }).format(date);
  }

  changeBudgetMonth(delta: number): void {
    let y = this.budgetYear();
    let m = this.budgetMonth() + delta;
    if (m < 1) {
      m = 12;
      y--;
    }
    if (m > 12) {
      m = 1;
      y++;
    }
    this.budgetYear.set(y);
    this.budgetMonth.set(m);
    this.loadBudgetStatus();
  }

  resetBudgetMonth(): void {
    const now = new Date();
    this.budgetYear.set(now.getFullYear());
    this.budgetMonth.set(now.getMonth() + 1);
    this.loadBudgetStatus();
  }

  openAdjustmentModal(): void {
    const workspace = this.currentWorkspace();
    const status = this.budgetStatus();
    if (!workspace || !status) return;

    this.adjustmentModalData.set({
      workspaceId: workspace.id,
      month: status.month,
      categories: status.categories,
    });
    this.adjustmentModalOpen.set(true);
  }

  closeAdjustmentModal(): void {
    this.adjustmentModalOpen.set(false);
  }

  onAdjustmentCreated(): void {
    this.loadBudgetStatus();
  }

  onBreakdownDetailOpen(event: { filter: 'all' | 'others' }): void {
    this.detailModalFilter.set(event.filter);
    this.detailModalOpen.set(true);
  }

  closeBreakdownDetail(): void {
    this.detailModalOpen.set(false);
  }

  private loadBudgetStatus(): void {
    const workspace = this.currentWorkspace();
    if (!workspace) return;
    const month = `${this.budgetYear()}-${String(this.budgetMonth()).padStart(2, '0')}`;
    this.budgetsService
      .status(workspace.id, month)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.budgetStatus.set(r.data);
        },
      });
  }

  private loadHeatmap(): void {
    const workspace = this.currentWorkspace();
    if (!workspace) return;
    this.heatmapLoading.set(true);
    this.analyticsService
      .heatmap(workspace.id, this.heatmapYear(), this.heatmapMonth())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.heatmapData.set(r.data);
          this.heatmapLoading.set(false);
        },
        error: () => {
          this.heatmapLoading.set(false);
        },
      });
  }

  private loadSummary(): void {
    const workspace = this.currentWorkspace();
    if (!workspace) return;
    this.summaryLoading.set(true);
    const year = this.heatmapYear();
    const month = this.heatmapMonth();
    const from = `${year}-${String(month).padStart(2, '0')}-01`;
    const lastDay = new Date(year, month, 0).getDate();
    const to = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    this.analyticsService
      .summary(workspace.id, from, to)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.summaryData.set(r.data);
          this.summaryLoading.set(false);
        },
        error: () => {
          this.summaryLoading.set(false);
        },
      });
  }
}
