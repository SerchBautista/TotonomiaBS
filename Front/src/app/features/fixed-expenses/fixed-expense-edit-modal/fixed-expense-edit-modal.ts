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
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { FixedExpensesService } from '../../../core/services/fixed-expenses';
import {
  FixedExpense,
  FixedExpenseFrequency,
  FixedExpenseType,
  FixedExpenseUpdatePayload,
} from '../../../core/models/fixed-expense.model';
import { Category } from '../../../core/models/category.model';
import {
  buildPaymentValue,
  parsePaymentValue,
  WorkspacePaymentMethodSummary,
} from '../../../core/models/payment-method.model';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { ToastService } from '../../../core/services/toast.service';
import { EXPENSE_TYPES, FREQUENCIES } from '../fixed-expense-form.constants';
import { syncCategorySelection, syncPaymentSelection } from '../fixed-expense-form.utils';

@Component({
  selector: 'app-fixed-expense-edit-modal',
  imports: [ReactiveFormsModule, TranslateModule, ModalShellComponent],
  templateUrl: './fixed-expense-edit-modal.html',
  styleUrl: '../fixed-expense-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FixedExpenseEditModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fixedExpensesService = inject(FixedExpensesService);
  private readonly translate = inject(TranslateService);
  private readonly fb = inject(FormBuilder);
  private readonly toastService = inject(ToastService);

  readonly expense = input.required<FixedExpense | null>();
  readonly workspaceId = input.required<string>();
  readonly categories = input.required<Category[]>();
  readonly validPaymentMethods = input.required<WorkspacePaymentMethodSummary[]>();

  readonly closed = output<void>();
  readonly updated = output<void>();

  readonly saving = signal(false);
  readonly editIsInstallmentForm = signal(false);
  readonly editingId = signal<string | null>(null);

  readonly isOpen = computed(() => this.expense() !== null);

  readonly cashMethod = computed(
    () => this.validPaymentMethods().find((method) => method.type === 'cash') ?? null,
  );
  readonly cards = computed(() =>
    this.validPaymentMethods().filter((method) => method.type === 'card'),
  );
  readonly otherMethods = computed(() =>
    this.validPaymentMethods().filter((method) => method.type === 'other'),
  );

  readonly frequencies = FREQUENCIES;
  readonly expenseTypes = EXPENSE_TYPES;

  readonly editForm = this.fb.group({
    amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
    description: ['', [Validators.required, Validators.maxLength(200)]],
    frequency: ['monthly' as FixedExpenseFrequency, [Validators.required]],
    next_due_date: ['', [Validators.required]],
    alert_date: [null as string | null],
    category_id: ['', [Validators.required]],
    payment_value: ['', [Validators.required]],
    reminders_enabled: [false],
    type: ['recurring' as FixedExpenseType, [Validators.required]],
    total_installments: [null as number | null],
    remaining_installments: [null as number | null],
  });

  constructor() {
    effect(() => {
      const fe = this.expense();
      if (fe) {
        this.openFor(fe);
      }
    });

    effect(() => {
      const methods = this.validPaymentMethods();
      if (this.isOpen()) {
        syncPaymentSelection(this.editForm, methods);
      }
    });

    effect(() => {
      if (this.isOpen()) {
        syncCategorySelection(this.editForm, this.categories());
      }
    });
  }

  onEditTypeChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value as FixedExpenseType;
    const isInstallment = value === 'installment';
    this.editIsInstallmentForm.set(isInstallment);

    const totalCtrl = this.editForm.controls.total_installments;
    const remainingCtrl = this.editForm.controls.remaining_installments;

    if (isInstallment) {
      totalCtrl.setValidators([Validators.required, Validators.min(1), Validators.max(999)]);
      remainingCtrl.setValidators([Validators.required, Validators.min(1), Validators.max(999)]);
    } else {
      totalCtrl.clearValidators();
      totalCtrl.setValue(null);
      remainingCtrl.clearValidators();
      remainingCtrl.setValue(null);
    }

    totalCtrl.updateValueAndValidity();
    remainingCtrl.updateValueAndValidity();
  }

  submitUpdate(): void {
    if (this.editForm.invalid) {
      this.editForm.markAllAsTouched();
      return;
    }

    const id = this.editingId();
    if (!id) return;

    this.saving.set(true);

    const paymentValue = this.editForm.value.payment_value ?? '';
    const { paymentType, paymentInstrumentId } = parsePaymentValue(paymentValue);
    const type = (this.editForm.value.type ?? 'recurring') as FixedExpenseType;

    const payload: FixedExpenseUpdatePayload = {
      amount: (this.editForm.value.amount ?? '').trim(),
      description: (this.editForm.value.description ?? '').trim(),
      frequency: this.editForm.value.frequency as FixedExpenseFrequency,
      next_due_date: this.editForm.value.next_due_date ?? '',
      alert_date: this.editForm.value.alert_date || null,
      category_id: this.editForm.value.category_id ?? '',
      payment_type: paymentType,
      payment_instrument_id: paymentInstrumentId,
      reminders_enabled: this.editForm.value.reminders_enabled ?? false,
      type,
      ...(type === 'installment'
        ? {
            total_installments: this.editForm.value.total_installments ?? undefined,
            remaining_installments: this.editForm.value.remaining_installments ?? undefined,
          }
        : { total_installments: null, remaining_installments: null }),
    };

    this.fixedExpensesService
      .update(this.workspaceId(), id, payload)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('fixed_expenses.updated_ok'));
          this.updated.emit();
        },
        error: () => this.saving.set(false),
      });
  }

  close(): void {
    this.closed.emit();
  }

  private openFor(fe: FixedExpense): void {
    const isInstallment = fe.type === 'installment';
    this.editIsInstallmentForm.set(isInstallment);

    const totalCtrl = this.editForm.controls.total_installments;
    const remainingCtrl = this.editForm.controls.remaining_installments;

    if (isInstallment) {
      totalCtrl.setValidators([Validators.required, Validators.min(1), Validators.max(999)]);
      remainingCtrl.setValidators([Validators.required, Validators.min(1), Validators.max(999)]);
    } else {
      totalCtrl.clearValidators();
      remainingCtrl.clearValidators();
    }
    totalCtrl.updateValueAndValidity();
    remainingCtrl.updateValueAndValidity();

    const paymentValue = buildPaymentValue(fe.payment_type, fe.payment_instrument_id);

    this.editForm.patchValue({
      amount: fe.amount,
      description: fe.description,
      frequency: fe.frequency,
      next_due_date: fe.next_due_date,
      alert_date: fe.alert_date ?? null,
      category_id: fe.category?.id ?? '',
      payment_value: paymentValue,
      reminders_enabled: fe.reminders_enabled,
      type: fe.type,
      total_installments: fe.total_installments,
      remaining_installments: fe.remaining_installments,
    });

    syncPaymentSelection(this.editForm, this.validPaymentMethods());
    this.editingId.set(fe.id);
    this.focusEditField();
  }

  private focusEditField(): void {
    setTimeout(() => {
      const input = document.getElementById('fe-edit-description') as HTMLElement | null;
      input?.focus();
    }, 0);
  }
}
