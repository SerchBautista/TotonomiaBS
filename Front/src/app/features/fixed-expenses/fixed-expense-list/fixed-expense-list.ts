import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FixedExpensesService } from '../../../core/services/fixed-expenses';
import { CategoriesService } from '../../../core/services/categories';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { FixedExpense } from '../../../core/models/fixed-expense.model';
import { Category } from '../../../core/models/category.model';
import { WorkspacePaymentMethodSummary } from '../../../core/models/payment-method.model';
import { ConfirmDialogComponent } from '../../../shared/confirm-dialog/confirm-dialog';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SummaryHeroComponent } from '../../../shared/summary-hero/summary-hero';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { CategoryBadgeComponent } from '../../../shared/category-badge/category-badge';
import { ActionButtonsComponent } from '../../../shared/action-buttons/action-buttons';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { FixedExpenseCreateModalComponent } from '../fixed-expense-create-modal/fixed-expense-create-modal';
import { FixedExpenseEditModalComponent } from '../fixed-expense-edit-modal/fixed-expense-edit-modal';

@Component({
  selector: 'app-fixed-expense-list',
  imports: [
    TranslateModule,
    ConfirmDialogComponent,
    CurrencyFormatPipe,
    PageHeaderComponent,
    SummaryHeroComponent,
    DataTableComponent,
    TableCellDirective,
    CategoryBadgeComponent,
    ActionButtonsComponent,
    StatusBadgeComponent,
    FixedExpenseCreateModalComponent,
    FixedExpenseEditModalComponent,
  ],
  templateUrl: './fixed-expense-list.html',
  styleUrl: './fixed-expense-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FixedExpenseListComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fixedExpensesService = inject(FixedExpensesService);
  private readonly categoriesService = inject(CategoriesService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly toastService = inject(ToastService);

  readonly loading = signal(false);
  readonly fixedExpenses = signal<FixedExpense[]>([]);
  readonly categories = signal<Category[]>([]);
  readonly validPaymentMethods = signal<WorkspacePaymentMethodSummary[]>([]);
  readonly showForm = signal(false);
  readonly editingExpense = signal<FixedExpense | null>(null);
  readonly workspaceOptions = this.workspaceContext.workspaces;
  readonly currentWorkspace = this.workspaceContext.selectedWorkspace;
  readonly monthlyTotalAmount = computed(() =>
    this.fixedExpenses().reduce((total, expense) => {
      const amount = Number.parseFloat(expense.amount);
      return Number.isFinite(amount) ? total + amount : total;
    }, 0),
  );

  workspaceId = '';
  readonly routeWorkspaceId = this.route.snapshot.parent?.paramMap.get('id');
  confirmOpen = false;
  itemToDelete: string | null = null;

  readonly columns = computed<TableColumn<FixedExpense>[]>(() => [
    { key: 'description', header: this.translate.instant('fixed_expenses.description') },
    { key: 'category', header: this.translate.instant('expenses.category'), width: '180px' },
    {
      key: 'amount',
      header: this.translate.instant('fixed_expenses.amount'),
      align: 'right',
      width: '140px',
    },
    {
      key: 'frequency',
      header: this.translate.instant('fixed_expenses.frequency'),
      width: '180px',
    },
    {
      key: 'next_due_date',
      header: this.translate.instant('fixed_expenses.next_due_date'),
      width: '150px',
    },
    {
      key: 'payment_method',
      header: this.translate.instant('fixed_expenses.payment_method'),
      width: '180px',
    },
    {
      key: 'actions',
      header: this.translate.instant('expenses.actions'),
      align: 'right',
      width: '100px',
    },
  ]);

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
      this.loadOptions();
      this.loadFixedExpenses();
      return;
    }

    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(async (params) => {
      const workspaceId = await this.workspaceContext.resolveWorkspaceId(params.get('workspaceId'));
      if (!workspaceId) {
        this.toastService.info(this.translate.instant('workspaces.empty'));
        return;
      }

      const changed = workspaceId !== this.workspaceId;
      this.workspaceId = workspaceId;

      if (changed) {
        this.showForm.set(false);
        this.editingExpense.set(null);
        this.loadOptions();
        this.loadFixedExpenses();
      }
    });
  }

  loadFixedExpenses(): void {
    this.loading.set(true);
    this.fixedExpensesService
      .list(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.fixedExpenses.set(r.data);
          this.loading.set(false);
        },
        error: () => {
          this.loading.set(false);
        },
      });
  }

  toggleForm(): void {
    this.showForm.set(!this.showForm());
  }

  onCreateClosed(): void {
    this.showForm.set(false);
  }

  onCreateSaved(): void {
    this.showForm.set(false);
    this.loadFixedExpenses();
  }

  startEdit(fe: FixedExpense): void {
    this.editingExpense.set(fe);
  }

  onEditClosed(): void {
    this.editingExpense.set(null);
  }

  onEditSaved(): void {
    this.editingExpense.set(null);
    this.loadFixedExpenses();
  }

  requestDelete(id: string): void {
    this.itemToDelete = id;
    this.confirmOpen = true;
  }

  cancelDelete(): void {
    this.confirmOpen = false;
    this.itemToDelete = null;
  }

  confirmDelete(): void {
    if (!this.itemToDelete) return;

    this.fixedExpensesService
      .delete(this.workspaceId, this.itemToDelete)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.confirmOpen = false;
          this.itemToDelete = null;
          this.toastService.success(this.translate.instant('fixed_expenses.deleted_ok'));
          this.loadFixedExpenses();
        },
        error: () => {
          this.confirmOpen = false;
          this.itemToDelete = null;
        },
      });
  }

  getPaymentLabel(fe: FixedExpense): string {
    if (fe.payment_type === 'cash') return this.translate.instant('payment.cash');
    if (fe.payment_instrument) return fe.payment_instrument.name;
    return this.translate.instant('payment.' + fe.payment_type);
  }

  loadValidPaymentMethods(): void {
    this.paymentMethodsService
      .listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.validPaymentMethods.set(response.data);
        },
        error: () => {
          this.validPaymentMethods.set([]);
        },
      });
  }

  private loadOptions(): void {
    this.categoriesService
      .listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.categories.set(r.data);
        },
      });

    this.loadValidPaymentMethods();
  }
}
