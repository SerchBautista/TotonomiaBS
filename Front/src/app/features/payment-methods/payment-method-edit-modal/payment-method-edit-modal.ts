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
import { UserPaymentMethodSummary } from '../../../core/models/payment-method.model';
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
  parseLast4FromMasked,
  PaymentMethodType,
} from '../payment-method-form.utils';

@Component({
  selector: 'app-payment-method-edit-modal',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    ModalShellComponent,
    WorkspaceSelectorListComponent,
  ],
  templateUrl: './payment-method-edit-modal.html',
  styleUrl: '../payment-method-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaymentMethodEditModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);

  readonly method = input.required<UserPaymentMethodSummary | null>();
  readonly ownerWorkspaces = input.required<Workspace[]>();

  readonly closed = output<void>();
  readonly updated = output<void>();

  readonly editSaving = signal(false);
  readonly selectedWorkspaceIds = signal<string[]>([]);

  readonly isOpen = computed(() => this.method() !== null);

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
      const method = this.method();
      if (method && method.type !== 'cash') {
        this.openFor(method);
      }
    });

    this.form.controls.type.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((type) => this.applyFormTypeRules(type ?? 'card'));
  }

  updateWorkspaceSelection(workspaceIds: string[]): void {
    this.selectedWorkspaceIds.set(workspaceIds);
  }

  submitEdit(): void {
    const method = this.method();
    if (!method || method.type === 'cash') return;

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const type = this.form.controls.type.value ?? 'card';
    const payload = buildPayload(type, this.form, this.selectedWorkspaceIds());

    this.editSaving.set(true);
    this.paymentMethodsService
      .updateMine(method.id, payload, this.skipGlobalToast)
      .pipe(
        finalize(() => this.editSaving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('payment_methods.updated_ok'));
          this.updated.emit();
        },
        error: (error) =>
          handlePaymentMethodServiceError('payment_methods.update_error', error, {
            translate: this.translate,
            toastService: this.toastService,
            form: this.form,
          }),
      });
  }

  close(): void {
    this.closed.emit();
  }

  private openFor(method: UserPaymentMethodSummary): void {
    const isCard = method.type === 'card';
    const last4 = parseLast4FromMasked(method.masked_details);

    this.form.reset({
      type: isCard ? 'card' : 'other',
      name: method.name,
      card_type: 'credit',
      brand: '',
      last_4_digits: last4,
      description: '',
    });
    this.applyFormTypeRules(isCard ? 'card' : 'other');
    this.selectedWorkspaceIds.set((method.linked_workspaces ?? []).map((workspace) => workspace.id));
  }

  private applyFormTypeRules(type: PaymentMethodType): void {
    applyFormTypeRules(type, this.form);
  }
}
