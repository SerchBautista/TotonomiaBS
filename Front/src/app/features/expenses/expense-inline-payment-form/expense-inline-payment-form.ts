import {
  ChangeDetectionStrategy,
  Component,
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
import { CardsService } from '../../../core/services/cards.service';
import { OtherPaymentMethodsService } from '../../../core/services/other-payment-methods.service';
import { Workspace } from '../../../core/models/workspace.model';
import { Card, OtherPaymentMethod, buildPaymentValue } from '../../../core/models/payment-method.model';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceSelectorListComponent } from '../../../shared/workspace-selector-list/workspace-selector-list';
import { skipGlobalErrorToastContext } from '../../../core/interceptors/http-request-context';
import { ApiRequestOptions } from '../../../core/tokens/api-service.token';
import { handleInlineFormError } from '../expense-form.utils';

export interface ExpenseInlinePaymentCreatedEvent {
  paymentValue: string;
  instrument: Card | OtherPaymentMethod;
}

@Component({
  selector: 'app-expense-inline-payment-form',
  imports: [ReactiveFormsModule, TranslateModule, WorkspaceSelectorListComponent],
  templateUrl: './expense-inline-payment-form.html',
  styleUrl: '../expense-inline-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ExpenseInlinePaymentFormComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cardsService = inject(CardsService);
  private readonly otherPaymentMethodsService = inject(OtherPaymentMethodsService);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly fb = inject(FormBuilder);

  readonly mode = input.required<'card' | 'other'>();
  readonly workspaceId = input.required<string>();
  readonly ownerWorkspaces = input.required<Workspace[]>();
  readonly initialWorkspaceIds = input.required<string[]>();

  readonly created = output<ExpenseInlinePaymentCreatedEvent>();
  readonly canceled = output<void>();

  readonly saving = signal(false);
  readonly workspaceIds = signal<string[]>([]);

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  readonly cardForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    card_type: ['credit', [Validators.required]],
    brand: [''],
    last_4_digits: ['', [Validators.pattern(/^\d{4}$/)]],
  });

  readonly otherForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    description: [''],
  });

  constructor() {
    effect(() => {
      this.workspaceIds.set([...this.initialWorkspaceIds()]);
    });
  }

  updateWorkspaceSelection(workspaceIds: string[]): void {
    this.workspaceIds.set(workspaceIds);
  }

  submit(): void {
    if (this.mode() === 'card') {
      this.submitCard();
    } else {
      this.submitOther();
    }
  }

  cancel(): void {
    if (this.mode() === 'card') {
      this.cardForm.reset({ card_type: 'credit' });
    } else {
      this.otherForm.reset();
    }
    this.canceled.emit();
  }

  private submitCard(): void {
    if (this.cardForm.invalid) {
      this.cardForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    const last4 = this.cardForm.value.last_4_digits?.trim() || null;

    this.cardsService
      .create(
        this.workspaceId(),
        {
          name: (this.cardForm.value.name ?? '').trim(),
          card_type: this.cardForm.value.card_type as 'credit' | 'debit',
          brand: this.cardForm.value.brand?.trim() || null,
          last_4_digits: last4,
          workspace_ids: this.workspaceIds(),
        },
        this.skipGlobalToast,
      )
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.cardForm.reset({ card_type: 'credit' });
          this.created.emit({
            paymentValue: buildPaymentValue('card', r.data.id),
            instrument: r.data,
          });
        },
        error: (err) =>
          handleInlineFormError(err, this.cardForm, 'cards.save_error', {
            translate: this.translate,
            toastService: this.toastService,
          }),
      });
  }

  private submitOther(): void {
    if (this.otherForm.invalid) {
      this.otherForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);

    this.otherPaymentMethodsService
      .create(
        this.workspaceId(),
        {
          name: (this.otherForm.value.name ?? '').trim(),
          description: this.otherForm.value.description?.trim() || null,
          workspace_ids: this.workspaceIds(),
        },
        this.skipGlobalToast,
      )
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.otherForm.reset();
          this.created.emit({
            paymentValue: buildPaymentValue('other', r.data.id),
            instrument: r.data,
          });
        },
        error: (err) =>
          handleInlineFormError(err, this.otherForm, 'other_methods.save_error', {
            translate: this.translate,
            toastService: this.toastService,
          }),
      });
  }
}
