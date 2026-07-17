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
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { FixedExpensesService } from '../../../core/services/fixed-expenses';
import { CardsService } from '../../../core/services/cards.service';
import { OtherPaymentMethodsService } from '../../../core/services/other-payment-methods.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import {
  FixedExpenseFrequency,
  FixedExpenseType,
} from '../../../core/models/fixed-expense.model';
import { Category } from '../../../core/models/category.model';
import {
  buildPaymentValue,
  parsePaymentValue,
  WorkspacePaymentMethodSummary,
} from '../../../core/models/payment-method.model';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { applyBackendFieldErrors } from '../../../core/errors/apply-backend-field-errors';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { skipGlobalErrorToastContext } from '../../../core/interceptors/http-request-context';
import { ApiRequestOptions } from '../../../core/tokens/api-service.token';
import { ToastService } from '../../../core/services/toast.service';
import { EXPENSE_TYPES, FREQUENCIES } from '../fixed-expense-form.constants';
import {
  getDefaultPaymentValue,
  syncCategorySelection,
  syncPaymentSelection,
} from '../fixed-expense-form.utils';

@Component({
  selector: 'app-fixed-expense-create-modal',
  imports: [ReactiveFormsModule, TranslateModule, ModalShellComponent],
  templateUrl: './fixed-expense-create-modal.html',
  styleUrl: '../fixed-expense-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FixedExpenseCreateModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fixedExpensesService = inject(FixedExpensesService);
  private readonly cardsService = inject(CardsService);
  private readonly otherPaymentMethodsService = inject(OtherPaymentMethodsService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);
  private readonly translate = inject(TranslateService);
  private readonly fb = inject(FormBuilder);
  private readonly toastService = inject(ToastService);

  readonly open = input.required<boolean>();
  readonly workspaceId = input.required<string>();
  readonly categories = input.required<Category[]>();
  readonly validPaymentMethods = input.required<WorkspacePaymentMethodSummary[]>();

  readonly closed = output<void>();
  readonly created = output<void>();
  readonly paymentMethodsChanged = output<void>();

  readonly saving = signal(false);
  readonly savingNewInstrument = signal(false);
  readonly showNewCardForm = signal(false);
  readonly showNewOtherForm = signal(false);
  readonly isInstallmentForm = signal(false);

  private readonly preferredPaymentValue = signal<string | null>(null);

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

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  readonly form = this.fb.group({
    amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
    description: ['', [Validators.required, Validators.maxLength(200)]],
    frequency: ['monthly' as FixedExpenseFrequency, [Validators.required]],
    next_due_date: [new Date().toISOString().split('T')[0], [Validators.required]],
    alert_date: [null as string | null],
    category_id: ['', [Validators.required]],
    payment_value: ['', [Validators.required]],
    reminders_enabled: [false],
    type: ['recurring' as FixedExpenseType, [Validators.required]],
    total_installments: [null as number | null],
    remaining_installments: [null as number | null],
  });

  readonly newCardForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    card_type: ['credit', [Validators.required]],
    brand: [''],
    last_4_digits: ['', [Validators.pattern(/^\d{4}$/)]],
  });

  readonly newOtherForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    description: [''],
  });

  constructor() {
    effect(() => {
      if (this.open()) {
        this.onOpen();
      } else {
        this.resetFormState();
      }
    });

    effect(() => {
      const methods = this.validPaymentMethods();
      const preferred = this.preferredPaymentValue();
      syncPaymentSelection(this.form, methods, {
        preferredPaymentValue: preferred ?? undefined,
        fallbackToFirst: this.open(),
      });
      if (preferred) {
        this.preferredPaymentValue.set(null);
      }
    });

    effect(() => {
      syncCategorySelection(this.form, this.categories());
    });
  }

  onTypeChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value as FixedExpenseType;
    const isInstallment = value === 'installment';
    this.isInstallmentForm.set(isInstallment);

    const totalCtrl = this.form.controls.total_installments;
    const remainingCtrl = this.form.controls.remaining_installments;

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

  onPaymentValueChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    if (value === 'card:new') {
      this.showNewCardForm.set(true);
      this.showNewOtherForm.set(false);
      this.form.patchValue({ payment_value: '' });
    } else if (value === 'other:new') {
      this.showNewOtherForm.set(true);
      this.showNewCardForm.set(false);
      this.form.patchValue({ payment_value: '' });
    } else {
      this.showNewCardForm.set(false);
      this.showNewOtherForm.set(false);
    }
  }

  submitNewCard(): void {
    if (this.newCardForm.invalid) {
      this.newCardForm.markAllAsTouched();
      return;
    }

    this.savingNewInstrument.set(true);
    const last4 = this.newCardForm.value.last_4_digits?.trim() || null;

    this.cardsService
      .create(
        this.workspaceId(),
        {
          name: (this.newCardForm.value.name ?? '').trim(),
          card_type: this.newCardForm.value.card_type as 'credit' | 'debit',
          brand: this.newCardForm.value.brand?.trim() || null,
          last_4_digits: last4,
        },
        this.skipGlobalToast,
      )
      .pipe(
        finalize(() => this.savingNewInstrument.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.paymentMethodsService.notifyCreated(this.workspaceId(), r.data);
          this.showNewCardForm.set(false);
          this.newCardForm.reset({ card_type: 'credit' });
          this.preferredPaymentValue.set(buildPaymentValue('card', r.data.id));
          this.paymentMethodsChanged.emit();
        },
        error: (err) => this.handleInlineFormError(err, this.newCardForm, 'cards.save_error'),
      });
  }

  cancelNewCard(): void {
    this.showNewCardForm.set(false);
    this.newCardForm.reset({ card_type: 'credit' });
  }

  submitNewOther(): void {
    if (this.newOtherForm.invalid) {
      this.newOtherForm.markAllAsTouched();
      return;
    }

    this.savingNewInstrument.set(true);

    this.otherPaymentMethodsService
      .create(
        this.workspaceId(),
        {
          name: (this.newOtherForm.value.name ?? '').trim(),
          description: this.newOtherForm.value.description?.trim() || null,
        },
        this.skipGlobalToast,
      )
      .pipe(
        finalize(() => this.savingNewInstrument.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.paymentMethodsService.notifyCreated(this.workspaceId(), r.data);
          this.showNewOtherForm.set(false);
          this.newOtherForm.reset();
          this.preferredPaymentValue.set(buildPaymentValue('other', r.data.id));
          this.paymentMethodsChanged.emit();
        },
        error: (err) => this.handleInlineFormError(err, this.newOtherForm, 'other_methods.save_error'),
      });
  }

  cancelNewOther(): void {
    this.showNewOtherForm.set(false);
    this.newOtherForm.reset();
  }

  submitCreate(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);

    const paymentValue = this.form.value.payment_value ?? '';
    const { paymentType, paymentInstrumentId } = parsePaymentValue(paymentValue);
    const type = (this.form.value.type ?? 'recurring') as FixedExpenseType;

    this.fixedExpensesService
      .create(this.workspaceId(), {
        amount: (this.form.value.amount ?? '').trim(),
        description: (this.form.value.description ?? '').trim(),
        frequency: this.form.value.frequency as FixedExpenseFrequency,
        next_due_date: this.form.value.next_due_date ?? '',
        alert_date: this.form.value.alert_date || null,
        category_id: this.form.value.category_id ?? '',
        payment_type: paymentType,
        payment_instrument_id: paymentInstrumentId,
        reminders_enabled: this.form.value.reminders_enabled ?? false,
        type,
        ...(type === 'installment'
          ? {
              total_installments: this.form.value.total_installments ?? undefined,
              remaining_installments: this.form.value.remaining_installments ?? undefined,
            }
          : {}),
      })
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('fixed_expenses.created_ok'));
          this.created.emit();
        },
        error: () => this.saving.set(false),
      });
  }

  close(): void {
    this.closed.emit();
  }

  private onOpen(): void {
    this.resetForm();
    syncPaymentSelection(this.form, this.validPaymentMethods(), { fallbackToFirst: true });
    this.focusCreateField();
  }

  private resetForm(): void {
    this.form.reset({
      frequency: 'monthly',
      next_due_date: new Date().toISOString().split('T')[0],
      alert_date: null,
      reminders_enabled: false,
      type: 'recurring',
      total_installments: null,
      remaining_installments: null,
      payment_value: getDefaultPaymentValue(this.validPaymentMethods()),
      category_id: '',
      amount: '',
      description: '',
    });
    this.isInstallmentForm.set(false);
    this.form.controls.total_installments.clearValidators();
    this.form.controls.total_installments.updateValueAndValidity();
    this.form.controls.remaining_installments.clearValidators();
    this.form.controls.remaining_installments.updateValueAndValidity();
    syncCategorySelection(this.form, this.categories());
    syncPaymentSelection(this.form, this.validPaymentMethods(), { fallbackToFirst: true });
  }

  private resetFormState(): void {
    this.showNewCardForm.set(false);
    this.showNewOtherForm.set(false);
    this.isInstallmentForm.set(false);
  }

  private focusCreateField(): void {
    setTimeout(() => {
      const input = document.getElementById('fe-description') as HTMLElement | null;
      input?.focus();
    }, 0);
  }

  private handleInlineFormError(error: unknown, form: FormGroup, fallbackKey: string): void {
    if (applyBackendFieldErrors(form, error)) {
      return;
    }

    const normalized = ensureNormalizedBackendError(error);
    this.toastService.error(normalized.message || this.translate.instant(fallbackKey));
  }
}
