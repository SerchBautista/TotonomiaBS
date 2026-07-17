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

@Component({
  selector: 'app-payment-method-workspace-manager-modal',
  imports: [TranslateModule, ModalShellComponent, WorkspaceSelectorListComponent],
  templateUrl: './payment-method-workspace-manager-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaymentMethodWorkspaceManagerModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);

  readonly method = input.required<UserPaymentMethodSummary | null>();
  readonly ownerWorkspaces = input.required<Workspace[]>();

  readonly closed = output<void>();
  readonly saved = output<void>();

  readonly manageSaving = signal(false);
  readonly selectedWorkspaceIds = signal<string[]>([]);

  readonly isOpen = computed(() => this.method() !== null);

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  constructor() {
    effect(() => {
      const method = this.method();
      if (method && method.type !== 'cash') {
        this.selectedWorkspaceIds.set(
          (method.linked_workspaces ?? []).map((workspace) => workspace.id),
        );
      }
    });
  }

  updateWorkspaceSelection(workspaceIds: string[]): void {
    this.selectedWorkspaceIds.set(workspaceIds);
  }

  save(): void {
    const method = this.method();
    if (!method || method.type === 'cash') {
      return;
    }

    this.manageSaving.set(true);
    this.paymentMethodsService
      .updateWorkspaces(method.id, this.selectedWorkspaceIds(), this.skipGlobalToast)
      .pipe(
        finalize(() => this.manageSaving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('payment_methods.workspaces_updated_ok'));
          this.saved.emit();
        },
        error: (error) =>
          handlePaymentMethodServiceError('payment_methods.workspaces_update_error', error, {
            translate: this.translate,
            toastService: this.toastService,
          }),
      });
  }

  close(): void {
    this.closed.emit();
  }
}
