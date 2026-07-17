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
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';
import { skipGlobalErrorToastContext } from '../../../core/interceptors/http-request-context';
import { ApiRequestOptions } from '../../../core/tokens/api-service.token';
import { Workspace } from '../../../core/models/workspace.model';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { WorkspaceSelectorListComponent } from '../../../shared/workspace-selector-list/workspace-selector-list';
import { handlePaymentMethodServiceError } from '../payment-method-error.handler';
import {
  applyFormTypeRules,
  buildPayload,
  defaultWorkspaceSelection,
  PaymentMethodType,
  resetPaymentMethodForm,
} from '../payment-method-form.utils';

@Component({
  selector: 'app-payment-method-create-modal',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    ModalShellComponent,
    WorkspaceSelectorListComponent,
  ],
  templateUrl: './payment-method-create-modal.html',
  styleUrl: '../payment-method-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaymentMethodCreateModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);

  readonly open = input.required<boolean>();
  readonly ownerWorkspaces = input.required<Workspace[]>();

  readonly closed = output<void>();
  readonly created = output<void>();

  readonly saving = signal(false);
  readonly selectedWorkspaceIds = signal<string[]>([]);

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  readonly form = this.fb.group({
    type: ['card' as PaymentMethodType, [Validators.required]],
    name: ['', [Validators.required, Validators.maxLength(100)]],
    card_type: ['credit' as 'credit' | 'debit'],
    brand: ['', [Validators.maxLength(50)]],
    last_4_digits: ['', [Validators.pattern(/^\d{4}$/)]],
    description: ['', [Validators.maxLength(1000)]],
  });

  constructor() {
    effect(() => {
      if (this.open()) {
        this.onOpen();
      }
    });

    this.form.controls.type.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((type) => this.applyFormTypeRules(type ?? 'card'));
  }

  updateWorkspaceSelection(workspaceIds: string[]): void {
    this.selectedWorkspaceIds.set(workspaceIds);
  }

  submitCreate(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const type = this.form.controls.type.value ?? 'card';
    const payload = buildPayload(type, this.form, this.selectedWorkspaceIds());

    this.saving.set(true);
    this.paymentMethodsService
      .createMine(payload, this.skipGlobalToast)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('payment_methods.created_ok'));
          this.created.emit();
        },
        error: (error) =>
          handlePaymentMethodServiceError('payment_methods.create_error', error, {
            translate: this.translate,
            toastService: this.toastService,
            form: this.form,
          }),
      });
  }

  close(): void {
    this.closed.emit();
  }

  private onOpen(): void {
    resetPaymentMethodForm(this.form);
    this.selectedWorkspaceIds.set(defaultWorkspaceSelection(this.ownerWorkspaces()));
  }

  private applyFormTypeRules(type: PaymentMethodType): void {
    applyFormTypeRules(type, this.form);
  }
}
