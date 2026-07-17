import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  Input,
  OnChanges,
  Output,
  SimpleChanges
} from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';
import { FixedExpenseOccurrence, PayOccurrencePayload } from '../../../core/models/fixed-expense.model';
import { buildPaymentValue, parsePaymentValue, WorkspacePaymentMethodSummary } from '../../../core/models/payment-method.model';
import { WorkspaceMember } from '../../../core/models/workspace.model';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { getTodayInTimezone, notFutureDateValidator } from '../../../core/utils/date-utils';
import { CategoryBadgeComponent } from '../../../shared/category-badge/category-badge';

@Component({
  selector: 'app-pay-occurrence-form',
  imports: [ReactiveFormsModule, TranslateModule, CategoryBadgeComponent],
  templateUrl: './pay-occurrence-form.html',
  styleUrl: './pay-occurrence-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class PayOccurrenceFormComponent implements OnChanges {
  @Input({ required: true }) occurrence!: FixedExpenseOccurrence;
  @Input() paymentMethods: WorkspacePaymentMethodSummary[] = [];
  @Input() saving = false;
  @Input() isShared = false;
  @Input() members: WorkspaceMember[] = [];
  @Input() ownerId = '';
  @Output() submitted = new EventEmitter<PayOccurrencePayload>();
  @Output() cancelled = new EventEmitter<void>();

  readonly form: FormGroup;
  readonly todayMax: string;

  constructor(
    private readonly fb: FormBuilder,
    private readonly preferencesService: UserPreferencesService
  ) {
    const today = getTodayInTimezone(this.preferencesService.timezone());
    this.todayMax = today;

    this.form = this.fb.group({
      amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
      payment_value: ['', [Validators.required]],
      paid_at: [today, [Validators.required, notFutureDateValidator(this.preferencesService.timezone())]],
      paid_by_user_id: ['']
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['occurrence'] || changes['paymentMethods'] || changes['ownerId']) && this.occurrence) {
      const fe = this.occurrence.fixed_expense;
      let paymentValue = '';
      if (fe?.payment_type === 'card' && fe.payment_instrument) {
        paymentValue = buildPaymentValue('card', fe.payment_instrument.id);
      } else if (fe?.payment_type === 'other' && fe.payment_instrument) {
        paymentValue = buildPaymentValue('other', fe.payment_instrument.id);
      }

      const resolvedPaymentValue = this.hasPaymentValue(paymentValue)
        ? paymentValue
        : this.getDefaultPaymentValue();

      this.form.patchValue({
        amount: this.occurrence.suggested_amount,
        payment_value: resolvedPaymentValue,
        paid_at: getTodayInTimezone(this.preferencesService.timezone()),
        paid_by_user_id: this.ownerId || ''
      });
    }
  }

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const paymentValue: string = this.form.value.payment_value ?? '';
    const { paymentType, paymentInstrumentId } = parsePaymentValue(paymentValue);

    this.submitted.emit({
      amount: (this.form.value.amount ?? '').trim(),
      payment_type: paymentType,
      payment_instrument_id: paymentInstrumentId,
      paid_at: this.form.value.paid_at ?? '',
      paid_by_user_id: this.isShared ? (this.form.value.paid_by_user_id || null) : null
    });
  }

  onCancel(): void {
    this.cancelled.emit();
  }

  paymentOptionValue(method: WorkspacePaymentMethodSummary): string {
    return buildPaymentValue(method.type, method.id);
  }

  private getDefaultPaymentValue(): string {
    const method = this.paymentMethods[0];
    return method ? buildPaymentValue(method.type, method.id) : '';
  }

  private hasPaymentValue(value: string): boolean {
    return this.paymentMethods.some(method => buildPaymentValue(method.type, method.id) === value);
  }
}
