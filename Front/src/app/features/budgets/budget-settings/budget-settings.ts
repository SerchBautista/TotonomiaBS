import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
  signal,
  viewChild,
} from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { BudgetsService } from '../../../core/services/budgets.service';
import { CategoriesService } from '../../../core/services/categories';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { Budget, BudgetCategoryScopeStatus } from '../../../core/models/budget.model';
import { Category } from '../../../core/models/category.model';
import { BudgetAdjustmentModalComponent, AdjustmentModalData } from '../budget-adjustment-modal/budget-adjustment-modal';
import { BudgetAdjustmentHistoryModalComponent } from '../budget-adjustment-history-modal/budget-adjustment-history-modal';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';
import { BudgetGeneralSectionComponent } from '../budget-general-section/budget-general-section';
import {
  BudgetCategoryBudgetsSectionComponent,
  BudgetHistoryViewEvent,
} from '../budget-category-budgets-section/budget-category-budgets-section';
import { BudgetChangeEvent } from '../budget-form.utils';

@Component({
  selector: 'app-budget-settings',
  imports: [
    TranslateModule,
    BudgetAdjustmentModalComponent,
    BudgetAdjustmentHistoryModalComponent,
    PageHeaderComponent,
    LoadingStateComponent,
    BudgetGeneralSectionComponent,
    BudgetCategoryBudgetsSectionComponent,
  ],
  templateUrl: './budget-settings.html',
  styleUrl: './budget-settings.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BudgetSettingsComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly budgetsService = inject(BudgetsService);
  private readonly categoriesService = inject(CategoriesService);
  private readonly toastService = inject(ToastService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly route = inject(ActivatedRoute);
  private readonly translate = inject(TranslateService);

  readonly historyModal = viewChild.required(BudgetAdjustmentHistoryModalComponent);

  readonly loading = signal(false);
  readonly budgets = signal<Budget[]>([]);
  readonly categories = signal<Category[]>([]);
  readonly budgetStatus = signal<Map<string, BudgetCategoryScopeStatus>>(new Map());

  readonly adjustmentModalOpen = signal(false);
  readonly adjustmentModalData = signal<AdjustmentModalData>({
    workspaceId: '',
    month: '',
    categories: [],
  });

  readonly historyModalOpen = signal(false);
  readonly historyCategoryId = signal('');
  readonly historyCategoryName = signal('');
  readonly workspaces = this.workspaceContext.workspaces;
  readonly currentWorkspace = this.workspaceContext.selectedWorkspace;
  readonly workspaceName = computed(() => this.workspaceContext.selectedWorkspace()?.name ?? '');
  readonly currencyCode = computed(() => this.workspaceContext.selectedWorkspace()?.currency_code ?? 'USD');

  readonly generalBudget = computed(() => this.budgets().find((b) => b.category_id === null));
  readonly categoryBudgets = computed(() => this.budgets().filter((b) => b.category_id !== null));

  workspaceId = '';

  async ngOnInit(): Promise<void> {
    this.workspaceId = this.route.snapshot.parent?.paramMap.get('id') ?? '';

    if (!this.workspaceId) {
      await this.workspaceContext.ensureLoaded();
      this.workspaceId = this.workspaceContext.selectedWorkspace()?.id ?? '';
    }

    if (this.workspaceId) {
      this.loadData();
    }
  }

  openAdjustmentModal(): void {
    const month = new Date().toISOString().slice(0, 7);
    this.budgetsService
      .status(this.workspaceId, month)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          const status = r.data;
          const validCategories = this.categories();
          const validCategoryIds = new Set(validCategories.map((category) => category.id));
          const validStatusCategories = status.categories.filter((category) =>
            validCategoryIds.has(category.category_id),
          );
          this.adjustmentModalData.set({
            workspaceId: this.workspaceId,
            month: status.month,
            categories: validStatusCategories,
          });
          this.adjustmentModalOpen.set(true);
        },
        error: () => {},
      });
  }

  closeAdjustmentModal(): void {
    this.adjustmentModalOpen.set(false);
  }

  onAdjustmentCreated(): void {
    this.adjustmentModalOpen.set(false);
    this.toastService.success(this.translate.instant('budgets.adjustment_created'));
  }

  onBudgetChanged(event: BudgetChangeEvent): void {
    if (event.action === 'created' && event.budget) {
      this.budgets.update((list) => [...list, event.budget!]);
      return;
    }
    if (event.action === 'updated' && event.budget) {
      this.budgets.update((list) =>
        list.map((b) => (b.id === event.budget!.id ? event.budget! : b)),
      );
      return;
    }
    if (event.action === 'deleted' && event.budgetId) {
      this.budgets.update((list) => list.filter((b) => b.id !== event.budgetId));
    }
  }

  openHistoryModal(event: BudgetHistoryViewEvent): void {
    this.historyCategoryId.set(event.categoryId);
    this.historyCategoryName.set(event.categoryName);
    this.historyModalOpen.set(true);
    setTimeout(() => this.historyModal().loadAdjustments());
  }

  closeHistoryModal(): void {
    this.historyModalOpen.set(false);
  }

  onAdjustmentRefresh(): void {
    this.loadBudgetStatus();
  }

  private loadData(): void {
    this.loading.set(true);
    this.categoriesService
      .listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => this.categories.set(r.data),
      });

    this.budgetsService
      .list(this.workspaceId)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => this.budgets.set(r.data),
        error: () => this.loading.set(false),
      });

    this.loadBudgetStatus();
  }

  private loadBudgetStatus(): void {
    if (!this.workspaceId) {
      return;
    }
    const month = new Date().toISOString().slice(0, 7);
    this.budgetsService
      .status(this.workspaceId, month)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          const statusMap = new Map<string, BudgetCategoryScopeStatus>();
          for (const cat of r.data.categories) {
            statusMap.set(cat.category_id, cat);
          }
          this.budgetStatus.set(statusMap);
        },
      });
  }
}
