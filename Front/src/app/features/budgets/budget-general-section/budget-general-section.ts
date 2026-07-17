import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { Budget } from '../../../core/models/budget.model';
import { BudgetsService } from '../../../core/services/budgets.service';
import { ToastService } from '../../../core/services/toast.service';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { FormCardComponent } from '../../../shared/form-card/form-card';
import { BudgetFormFieldsComponent } from '../budget-form-fields/budget-form-fields';
import {
  BudgetChangeEvent,
  createBudgetFormGroup,
  isThresholdInvalid,
} from '../budget-form.utils';

@Component({
  selector: 'app-budget-general-section',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    CurrencyFormatPipe,
    SectionPanelComponent,
    FormCardComponent,
    BudgetFormFieldsComponent,
  ],
  templateUrl: './budget-general-section.html',
  styleUrl: '../budget-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BudgetGeneralSectionComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly budgetsService = inject(BudgetsService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly fb = inject(FormBuilder);

  readonly workspaceId = input.required<string>();
  readonly currencyCode = input.required<string>();
  readonly generalBudget = input<Budget | undefined>();

  readonly budgetChanged = output<BudgetChangeEvent>();

  readonly saving = signal(false);
  readonly showGeneralForm = signal(false);
  readonly editing = signal(false);

  readonly generalForm = createBudgetFormGroup(this.fb);
  readonly generalEditForm = createBudgetFormGroup(this.fb);

  openGeneralForm(): void {
    this.generalForm.reset({ alert_threshold: 0, alert_enabled: true });
    this.showGeneralForm.set(true);
  }

  cancelGeneralForm(): void {
    this.showGeneralForm.set(false);
  }

  openEditForm(): void {
    const budget = this.generalBudget();
    if (!budget) {
      return;
    }
    this.editing.set(true);
    this.generalEditForm.reset({
      amount: budget.amount,
      alert_threshold: parseFloat(budget.alert_threshold),
      alert_enabled: budget.alert_enabled,
    });
  }

  cancelEditForm(): void {
    this.editing.set(false);
  }

  saveGeneralBudget(): void {
    if (this.generalForm.invalid) {
      this.generalForm.markAllAsTouched();
      return;
    }
    const v = this.generalForm.value;
    if (isThresholdInvalid(this.toastService, this.translate, v.amount, v.alert_threshold)) {
      return;
    }
    this.saving.set(true);
    this.budgetsService
      .create(this.workspaceId(), {
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
          this.showGeneralForm.set(false);
          this.toastService.success(this.translate.instant('budgets.saved_ok'));
          this.budgetChanged.emit({ action: 'created', budget: r.data });
        },
        error: () => this.saving.set(false),
      });
  }

  saveEdit(): void {
    const budget = this.generalBudget();
    if (!budget || this.generalEditForm.invalid) {
      this.generalEditForm.markAllAsTouched();
      return;
    }
    const v = this.generalEditForm.value;
    if (isThresholdInvalid(this.toastService, this.translate, v.amount, v.alert_threshold)) {
      return;
    }
    this.saving.set(true);
    this.budgetsService
      .update(this.workspaceId(), budget.id, {
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
          this.editing.set(false);
          this.toastService.success(this.translate.instant('budgets.saved_ok'));
          this.budgetChanged.emit({ action: 'updated', budget: r.data });
        },
        error: () => this.saving.set(false),
      });
  }

  deleteBudget(): void {
    const budget = this.generalBudget();
    if (!budget) {
      return;
    }
    this.budgetsService
      .delete(this.workspaceId(), budget.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('budgets.deleted_ok'));
          this.budgetChanged.emit({ action: 'deleted', budgetId: budget.id });
        },
        error: () => {},
      });
  }
}
