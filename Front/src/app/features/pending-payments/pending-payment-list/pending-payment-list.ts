import { ChangeDetectionStrategy, Component, computed, DestroyRef, effect, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { OccurrencesService } from '../../../core/services/occurrences';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { FixedExpenseOccurrence, PayOccurrencePayload } from '../../../core/models/fixed-expense.model';
import { WorkspacePaymentMethodSummary } from '../../../core/models/payment-method.model';
import { Workspace, WorkspaceMember } from '../../../core/models/workspace.model';
import { PayOccurrenceFormComponent } from '../pay-occurrence-form/pay-occurrence-form';
import { ToastService } from '../../../core/services/toast.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { CategoryBadgeComponent } from '../../../shared/category-badge/category-badge';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';


@Component({
  selector: 'app-pending-payment-list',
  imports: [
    TranslateModule,
    PayOccurrenceFormComponent,
    PageHeaderComponent,
    DataTableComponent,
    TableCellDirective,
    CategoryBadgeComponent,
    StatusBadgeComponent,
    CurrencyFormatPipe,
    ModalShellComponent,
    LoadingStateComponent,
  ],
  templateUrl: './pending-payment-list.html',
  styleUrl: './pending-payment-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class PendingPaymentListComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly occurrencesService = inject(OccurrencesService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly membersService = inject(WorkspaceMembersService);
  private readonly workspacesService = inject(WorkspacesService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);

  readonly loading = signal(false);
  readonly paying = signal(false);
  readonly occurrences = signal<FixedExpenseOccurrence[]>([]);
  readonly selectedOccurrence = signal<FixedExpenseOccurrence | null>(null);
  readonly workspaceOptions = this.workspaceContext.workspaces;
  readonly currentWorkspace = this.workspaceContext.selectedWorkspace;
  readonly workspaceMembers = signal<WorkspaceMember[]>([]);
  readonly workspaceDetail = signal<Workspace | null>(null);
  readonly validPaymentMethods = signal<WorkspacePaymentMethodSummary[]>([]);

  readonly columns = computed<TableColumn<FixedExpenseOccurrence>[]>(() => [
    { key: 'description', header: this.translate.instant('fixed_expenses.description') },
    { key: 'category', header: this.translate.instant('expenses.category'), width: '180px' },
    { key: 'amount', header: this.translate.instant('expenses.amount'), align: 'right', width: '140px' },
    { key: 'due', header: this.translate.instant('pending_payments.due'), width: '150px' },
    { key: 'status', header: this.translate.instant('pending_payments.status'), width: '140px' },
    { key: 'actions', header: this.translate.instant('expenses.actions'), align: 'right', width: '110px' },
  ]);

  workspaceId = '';
  readonly routeWorkspaceId = this.route.snapshot.parent?.paramMap.get('id');

  constructor() {
    effect(() => {
      const wsId = this.workspaceContext.currentWorkspaceId();
      if (!wsId || this.routeWorkspaceId) return;
      void this.router.navigate([], {
        relativeTo: this.route,
        queryParams: { workspaceId: wsId },
        queryParamsHandling: 'merge',
      });
    });
  }

  ngOnInit(): void {
    this.paymentMethodsService.paymentMethodCreated$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ workspaceId }) => {
        if (workspaceId === this.workspaceId) {
          this.loadValidPaymentMethods();
        }
      });

    if (this.routeWorkspaceId) {
      this.workspaceId = this.routeWorkspaceId;
      this.workspaceContext.setCurrentWorkspaceId(this.workspaceId);
      this.loadWorkspaceDetails();
      this.loadOccurrences();
      return;
    }

    this.route.queryParamMap
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(async (params) => {
        const workspaceId = await this.workspaceContext.resolveWorkspaceId(params.get('workspaceId'));
        if (!workspaceId) {
          this.toastService.info(this.translate.instant('workspaces.empty'));
          return;
        }
        const changed = workspaceId !== this.workspaceId;
        this.workspaceId = workspaceId;
        if (changed) {
          this.loadWorkspaceDetails();
          this.loadOccurrences();
        }
      });
  }

  isOverdue(occurrence: FixedExpenseOccurrence): boolean {
    return occurrence.status === 'overdue';
  }

  onPayRequest(occurrence: FixedExpenseOccurrence): void {
    this.selectedOccurrence.set(occurrence);
  }

  onCancelPay(): void {
    this.selectedOccurrence.set(null);
  }

  onSubmitPay(payload: PayOccurrencePayload): void {
    const occurrence = this.selectedOccurrence();
    if (!occurrence) return;

    this.paying.set(true);

    this.occurrencesService
      .pay(occurrence.id, payload)
      .pipe(
        finalize(() => this.paying.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: () => {
          this.selectedOccurrence.set(null);
          this.toastService.success(this.translate.instant('pending_payments.paid_ok'));
          this.loadOccurrences();
        },
        error: () => {},
      });
  }

  private loadWorkspaceDetails(): void {
    this.workspacesService.getById(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (r) => this.workspaceDetail.set(r.data) });

    this.membersService.list(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (r) => this.workspaceMembers.set(r.data) });

    this.loadValidPaymentMethods();
  }

  private loadOccurrences(): void {
    this.loading.set(true);
    this.occurrencesService
      .list(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.occurrences.set(r.data);
          this.loading.set(false);
        },
        error: () => {
          this.loading.set(false);
        }
      });
  }

  private loadValidPaymentMethods(): void {
    this.paymentMethodsService.listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.validPaymentMethods.set(response.data),
        error: () => {
          this.validPaymentMethods.set([]);
        },
      });
  }
}
