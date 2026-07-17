import { ChangeDetectionStrategy, Component, computed, DestroyRef, effect, inject, input, output, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { BackendErrorMeta } from '../../../core/errors/backend-error.model';
import { BudgetAdjustmentsService } from '../../../core/services/budget-adjustments.service';
import { ToastService } from '../../../core/services/toast.service';
import { skipGlobalErrorToastContext } from '../../../core/interceptors/http-request-context';
import { ApiRequestOptions } from '../../../core/tokens/api-service.token';
import { AvailableCategory, BudgetAdjustment } from '../../../core/models/budget-adjustment.model';
import { BudgetCategoryScopeStatus } from '../../../core/models/budget.model';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';

export interface AdjustmentModalData {
  workspaceId: string;
  month: string;
  categories: BudgetCategoryScopeStatus[];
  toCategoryId?: string;
  suggestedAmount?: number;
}

@Component({
  selector: 'app-budget-adjustment-modal',
  imports: [ReactiveFormsModule, TranslateModule, CurrencyFormatPipe, ModalShellComponent],
  templateUrl: './budget-adjustment-modal.html',
  styleUrl: './budget-adjustment-modal.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BudgetAdjustmentModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly translate = inject(TranslateService);
  private readonly budgetAdjustmentsService = inject(BudgetAdjustmentsService);
  private readonly toastService = inject(ToastService);

  readonly open = input.required<boolean>();
  readonly data = input.required<AdjustmentModalData>();
  readonly closed = output<void>();
  readonly adjustmentCreated = output<BudgetAdjustment>();

  readonly loading = signal(false);
  readonly availableCategories = signal<AvailableCategory[]>([]);
  readonly suggestedCategories = signal<AvailableCategory[]>([]);
  readonly showSuggestions = signal(false);

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  readonly form = this.fb.group({
    from_category_id: ['', [Validators.required]],
    to_category_id: ['', [Validators.required]],
    amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
    reason: [''],
  });

  constructor() {
    effect(() => {
      const data = this.data();
      if (data.toCategoryId) {
        this.form.patchValue({ to_category_id: data.toCategoryId });
      }
      if (data.suggestedAmount && data.suggestedAmount > 0) {
        this.form.patchValue({ amount: data.suggestedAmount.toFixed(2) });
      }
    });
  }

  get toCategoryName(): string {
    const toId = this.form.value.to_category_id;
    if (!toId) return '';
    const cat = this.data().categories.find(c => c.category_id === toId);
    return cat?.category_name ?? '';
  }

  readonly selectedFromCategory = computed(() => {
    const fromId = this.form.value.from_category_id;
    if (!fromId) return undefined;
    return this.data().categories.find(c => c.category_id === fromId);
  });

  availableForCategory(cat: BudgetCategoryScopeStatus): number {
    return Math.max(0, parseFloat(cat.effective_budget ?? cat.budget ?? '0') - parseFloat(cat.spent));
  }

  onFromCategoryChange(): void {
    const fromId = this.form.value.from_category_id;
    if (!fromId) {
      this.availableCategories.set([]);
      return;
    }

    const cat = this.data().categories.find(c => c.category_id === fromId);
    if (!cat) return;

    const effective = parseFloat(cat.effective_budget ?? cat.budget ?? '0');
    const spent = parseFloat(cat.spent);
    const available = Math.max(0, effective - spent);

    // Show how much is available in the selected from category
    if (available <= 0) {
      this.loadSuggestions();
    }
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.showSuggestions.set(false);

    const { workspaceId, month } = this.data();
    const payload = {
      from_category_id: this.form.value.from_category_id ?? '',
      to_category_id: this.form.value.to_category_id ?? '',
      amount: (this.form.value.amount ?? '').trim(),
      month,
      reason: this.form.value.reason?.trim() || null,
    };

    this.budgetAdjustmentsService.create(workspaceId, payload, this.skipGlobalToast)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (response) => {
          this.toastService.success(this.translate.instant('budgets.adjustment_created'));
          this.adjustmentCreated.emit(response.data);
          this.close();
        },
        error: (err) => {
          const normalizedError = ensureNormalizedBackendError(err);
          const suggestedCategories = this.extractSuggestedCategories(normalizedError.meta);

          if (this.isInsufficientFundsError(normalizedError, suggestedCategories)) {
            this.suggestedCategories.set(suggestedCategories);
            this.showSuggestions.set(true);
            this.toastService.error(normalizedError.message || this.translate.instant('budgets.insufficient_funds'));
          } else {
            this.toastService.error(this.formatErrorMessage('budgets.adjustment_error', normalizedError));
          }
        },
      });
  }

  selectSuggestion(category: AvailableCategory): void {
    this.form.patchValue({ from_category_id: category.category_id });
    this.showSuggestions.set(false);
    this.suggestedCategories.set([]);
  }

  loadSuggestions(): void {
    const { workspaceId, month } = this.data();
    const toId = this.form.value.to_category_id ?? '';

    this.budgetAdjustmentsService.available(workspaceId, month, toId, this.skipGlobalToast)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.suggestedCategories.set(response.data);
          this.showSuggestions.set(true);
        },
        error: (error) => {
          const normalizedError = ensureNormalizedBackendError(error);
          this.suggestedCategories.set([]);
          this.showSuggestions.set(false);
          this.toastService.error(this.formatErrorMessage('budgets.adjustment_error', normalizedError));
        },
      });
  }

  close(): void {
    this.form.reset();
    this.showSuggestions.set(false);
    this.suggestedCategories.set([]);
    this.closed.emit();
  }

  private isInsufficientFundsError(
    error: ReturnType<typeof ensureNormalizedBackendError>,
    suggestedCategories: AvailableCategory[]
  ): boolean {
    return error.code === BACKEND_ERROR_CODES.budgetAdjustmentInsufficientFunds
      || (error.status === 422 && suggestedCategories.length > 0);
  }

  private extractSuggestedCategories(meta: BackendErrorMeta | null): AvailableCategory[] {
    const suggestedCategories = meta?.['suggested_categories'];
    if (!Array.isArray(suggestedCategories)) {
      return [];
    }

    return suggestedCategories.filter((category): category is AvailableCategory => {
      return typeof category === 'object'
        && category !== null
        && typeof (category as AvailableCategory).category_id === 'string';
    });
  }

  private formatErrorMessage(i18nKey: string, error: ReturnType<typeof ensureNormalizedBackendError>): string {
    const baseMessage = this.translate.instant(i18nKey);
    return error.message !== baseMessage
      ? `${baseMessage}: ${error.message}`
      : baseMessage;
  }
}
