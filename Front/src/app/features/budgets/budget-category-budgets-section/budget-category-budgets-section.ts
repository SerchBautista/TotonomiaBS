import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { Budget, BudgetCategoryScopeStatus } from '../../../core/models/budget.model';
import { Category } from '../../../core/models/category.model';
import { BudgetsService } from '../../../core/services/budgets.service';
import { ToastService } from '../../../core/services/toast.service';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { FormCardComponent } from '../../../shared/form-card/form-card';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { CategoryBadgeComponent } from '../../../shared/category-badge/category-badge';
import { BudgetFormFieldsComponent } from '../budget-form-fields/budget-form-fields';
import {
  BudgetChangeEvent,
  createBudgetFormGroup,
  createCategoryBudgetFormGroup,
  isThresholdInvalid,
  syncCategoryBudgetSelection,
} from '../budget-form.utils';

export interface BudgetHistoryViewEvent {
  categoryId: string;
  categoryName: string;
}

@Component({
  selector: 'app-budget-category-budgets-section',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    CurrencyFormatPipe,
    SectionPanelComponent,
    FormCardComponent,
    DataTableComponent,
    TableCellDirective,
    CategoryBadgeComponent,
    BudgetFormFieldsComponent,
  ],
  templateUrl: './budget-category-budgets-section.html',
  styleUrl: '../budget-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BudgetCategoryBudgetsSectionComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly budgetsService = inject(BudgetsService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly fb = inject(FormBuilder);

  readonly workspaceId = input.required<string>();
  readonly currencyCode = input.required<string>();
  readonly categoryBudgets = input.required<Budget[]>();
  readonly categories = input.required<Category[]>();
  readonly budgetStatus = input.required<Map<string, BudgetCategoryScopeStatus>>();

  readonly budgetChanged = output<BudgetChangeEvent>();
  readonly viewHistory = output<BudgetHistoryViewEvent>();

  readonly saving = signal(false);
  readonly showCategoryForm = signal(false);
  readonly editingBudgetId = signal<string | null>(null);

  readonly categoryForm = createCategoryBudgetFormGroup(this.fb);
  readonly editForm = createBudgetFormGroup(this.fb);

  readonly columns = computed<TableColumn<Budget>[]>(() => [
    { key: 'category', header: this.translate.instant('budgets.category') },
    { key: 'amount', header: this.translate.instant('budgets.amount'), align: 'right', width: '140px' },
    { key: 'effective', header: this.translate.instant('budgets.effective_budget'), width: '160px' },
    { key: 'since', header: this.translate.instant('budgets.since'), width: '130px' },
    { key: 'actions', header: this.translate.instant('expenses.actions'), align: 'right', width: '140px' },
  ]);

  constructor() {
    effect(() => {
      syncCategoryBudgetSelection(this.categoryForm, this.categories());
    });
  }

  categoryName(budget: Budget): string {
    return budget.category?.name ?? budget.category_id ?? '';
  }

  getCategoryStatus(categoryId: string | null): BudgetCategoryScopeStatus | null {
    if (!categoryId) {
      return null;
    }
    return this.budgetStatus().get(categoryId) ?? null;
  }

  openCategoryForm(): void {
    this.categoryForm.reset({ alert_threshold: 0, alert_enabled: true });
    this.showCategoryForm.set(true);
  }

  cancelCategoryForm(): void {
    this.showCategoryForm.set(false);
  }

  openEditForm(budget: Budget): void {
    this.editingBudgetId.set(budget.id);
    this.editForm.reset({
      amount: budget.amount,
      alert_threshold: parseFloat(budget.alert_threshold),
      alert_enabled: budget.alert_enabled,
    });
  }

  cancelEditForm(): void {
    this.editingBudgetId.set(null);
  }

  saveCategoryBudget(): void {
    if (this.categoryForm.invalid) {
      this.categoryForm.markAllAsTouched();
      return;
    }
    const v = this.categoryForm.value;
    if (isThresholdInvalid(this.toastService, this.translate, v.amount, v.alert_threshold)) {
      return;
    }
    this.saving.set(true);
    this.budgetsService
      .create(this.workspaceId(), {
        category_id: v.category_id!,
        amount: v.amount!,
        alert_threshold: v.alert_threshold!,
        alert_enabled: v.alert_enabled!,
      })
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.categoryForm.reset({ alert_threshold: 0, alert_enabled: true });
          this.showCategoryForm.set(false);
          this.toastService.success(this.translate.instant('budgets.saved_ok'));
          this.budgetChanged.emit({ action: 'created', budget: r.data });
        },
        error: () => this.saving.set(false),
      });
  }

  saveEdit(budgetId: string): void {
    if (this.editForm.invalid) {
      this.editForm.markAllAsTouched();
      return;
    }
    const v = this.editForm.value;
    if (isThresholdInvalid(this.toastService, this.translate, v.amount, v.alert_threshold)) {
      return;
    }
    this.saving.set(true);
    this.budgetsService
      .update(this.workspaceId(), budgetId, {
        amount: v.amount!,
        alert_threshold: v.alert_threshold!,
        alert_enabled: v.alert_enabled!,
      })
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.editingBudgetId.set(null);
          this.toastService.success(this.translate.instant('budgets.saved_ok'));
          this.budgetChanged.emit({ action: 'updated', budget: r.data });
        },
        error: () => this.saving.set(false),
      });
  }

  deleteBudget(budgetId: string): void {
    this.budgetsService
      .delete(this.workspaceId(), budgetId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('budgets.deleted_ok'));
          this.budgetChanged.emit({ action: 'deleted', budgetId });
        },
        error: () => {},
      });
  }

  onViewHistory(budget: Budget): void {
    if (!budget.category_id) {
      return;
    }
    this.viewHistory.emit({
      categoryId: budget.category_id,
      categoryName: this.categoryName(budget),
    });
  }
}
