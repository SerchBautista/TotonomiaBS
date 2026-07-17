import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
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
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { PageFiltersComponent } from '../../../shared/page-filters/page-filters';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { ActionButtonsComponent } from '../../../shared/action-buttons/action-buttons';
import { EmptyStateComponent } from '../../../shared/empty-state/empty-state';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';
import { ConfirmDialogComponent } from '../../../shared/confirm-dialog/confirm-dialog';
import { PaymentMethodCreateModalComponent } from '../payment-method-create-modal/payment-method-create-modal';
import { PaymentMethodEditModalComponent } from '../payment-method-edit-modal/payment-method-edit-modal';
import { PaymentMethodWorkspaceManagerModalComponent } from '../payment-method-workspace-manager-modal/payment-method-workspace-manager-modal';
import { handlePaymentMethodServiceError } from '../payment-method-error.handler';

@Component({
  selector: 'app-payment-method-list',
  imports: [
    TranslateModule,
    PageHeaderComponent,
    PageFiltersComponent,
    DataTableComponent,
    TableCellDirective,
    StatusBadgeComponent,
    ActionButtonsComponent,
    EmptyStateComponent,
    LoadingStateComponent,
    ConfirmDialogComponent,
    PaymentMethodCreateModalComponent,
    PaymentMethodEditModalComponent,
    PaymentMethodWorkspaceManagerModalComponent,
  ],
  templateUrl: './payment-method-list.html',
  styleUrl: './payment-method-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaymentMethodListComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly authState = inject(AuthStateService);

  readonly loading = signal(false);
  readonly methods = signal<UserPaymentMethodSummary[]>([]);
  readonly ownerWorkspaces = signal<Workspace[]>([]);
  readonly managingMethod = signal<UserPaymentMethodSummary | null>(null);
  readonly search = signal('');
  readonly showForm = signal(false);
  readonly editingMethod = signal<UserPaymentMethodSummary | null>(null);

  readonly filteredMethods = computed(() => {
    const query = this.search().trim().toLowerCase();
    if (!query) return this.methods();
    return this.methods().filter((method) => {
      const haystack =
        `${method.name} ${method.display_name} ${method.masked_details ?? ''}`.toLowerCase();
      return haystack.includes(query);
    });
  });

  readonly columns = computed<TableColumn<UserPaymentMethodSummary>[]>(() => [
    { key: 'name', header: this.translate.instant('payment_methods.table_name') },
    {
      key: 'type',
      header: this.translate.instant('payment_methods.table_type'),
      width: '140px',
    },
    {
      key: 'status',
      header: this.translate.instant('payment_methods.table_status'),
      width: '200px',
    },
    {
      key: 'actions',
      header: this.translate.instant('expenses.actions'),
      align: 'right',
      width: '220px',
    },
  ]);

  confirmOpen = false;
  methodToDelete: UserPaymentMethodSummary | null = null;

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  ngOnInit(): void {
    void this.loadOwnerWorkspaces();
    this.loadMethods();
  }

  async loadOwnerWorkspaces(): Promise<void> {
    await this.workspaceContext.ensureLoaded();
    const userId = this.authState.userId();
    const workspaces = this.workspaceContext
      .workspaces()
      .filter((workspace) => workspace.owner_id === userId);

    this.ownerWorkspaces.set(workspaces);
  }

  onSearchChange(value: string): void {
    this.search.set(value);
  }

  toggleCreateForm(): void {
    this.showForm.set(!this.showForm());
  }

  onCreateClosed(): void {
    this.showForm.set(false);
  }

  onCreateSaved(): void {
    this.showForm.set(false);
    this.loadMethods();
  }

  startEdit(method: UserPaymentMethodSummary): void {
    if (method.type === 'cash') {
      return;
    }
    this.editingMethod.set(method);
  }

  onEditClosed(): void {
    this.editingMethod.set(null);
  }

  onEditSaved(): void {
    this.editingMethod.set(null);
    this.loadMethods();
  }

  openWorkspaceManager(method: UserPaymentMethodSummary): void {
    if (method.type === 'cash') {
      return;
    }
    this.managingMethod.set(method);
  }

  onWorkspaceManagerClosed(): void {
    this.managingMethod.set(null);
  }

  onWorkspaceManagerSaved(): void {
    this.managingMethod.set(null);
    this.loadMethods();
  }

  requestDelete(method: UserPaymentMethodSummary): void {
    this.methodToDelete = method;
    this.confirmOpen = true;
  }

  cancelDelete(): void {
    this.confirmOpen = false;
    this.methodToDelete = null;
  }

  confirmDelete(): void {
    const method = this.methodToDelete;
    if (!method) return;

    this.paymentMethodsService
      .deleteMine(method.id, this.skipGlobalToast)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.confirmOpen = false;
          this.methodToDelete = null;
          this.toastService.success(this.translate.instant('payment_methods.deleted_ok'));
          this.loadMethods();
        },
        error: (error) => {
          this.confirmOpen = false;
          this.methodToDelete = null;
          handlePaymentMethodServiceError('payment_methods.delete_error', error, {
            translate: this.translate,
            toastService: this.toastService,
          });
        },
      });
  }

  methodTypeLabel(method: UserPaymentMethodSummary): string {
    if (method.type === 'cash') return this.translate.instant('payment.cash');
    if (method.type === 'card') return this.translate.instant('payment.card');
    return this.translate.instant('payment.other');
  }

  linkedWorkspaceNames(method: UserPaymentMethodSummary): string {
    return (method.linked_workspaces ?? []).map((workspace) => workspace.name).join(', ');
  }

  canDelete(method: UserPaymentMethodSummary): boolean {
    return method.type !== 'cash';
  }

  canEdit(method: UserPaymentMethodSummary): boolean {
    return method.type !== 'cash';
  }

  private loadMethods(): void {
    this.loading.set(true);
    this.paymentMethodsService
      .listMine(this.skipGlobalToast)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => this.methods.set(response.data),
        error: (error) =>
          handlePaymentMethodServiceError('payment_methods.load_error', error, {
            translate: this.translate,
            toastService: this.toastService,
          }),
      });
  }
}
