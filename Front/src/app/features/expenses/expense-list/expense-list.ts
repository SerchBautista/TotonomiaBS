import { ChangeDetectionStrategy, Component, computed, DestroyRef, effect, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { skip } from 'rxjs';
import { ExpensesService } from '../../../core/services/expenses';
import { CategoriesService } from '../../../core/services/categories';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { Expense } from '../../../core/models/expense.model';
import { Category } from '../../../core/models/category.model';
import { isCard } from '../../../core/models/payment-method.model';
import { ConfirmDialogComponent } from '../../../shared/confirm-dialog/confirm-dialog';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { ToastService } from '../../../core/services/toast.service';
import { QuickAddExpenseService } from '../../../core/services/quick-add-expense.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { PageFiltersComponent } from '../../../shared/page-filters/page-filters';
import { SummaryHeroComponent } from '../../../shared/summary-hero/summary-hero';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { CategoryBadgeComponent } from '../../../shared/category-badge/category-badge';
import { ActionButtonsComponent } from '../../../shared/action-buttons/action-buttons';
import { PaginationBarComponent } from '../../../shared/pagination-bar/pagination-bar';

import { getTodayInTimezone, getFirstDayOfMonthInTimezone } from '../../../core/utils/date-utils';

@Component({
  selector: 'app-expense-list',
  imports: [
    TranslateModule,
    ConfirmDialogComponent,
    CurrencyFormatPipe,
    PageHeaderComponent,
    PageFiltersComponent,
    SummaryHeroComponent,
    DataTableComponent,
    TableCellDirective,
    CategoryBadgeComponent,
    ActionButtonsComponent,
    PaginationBarComponent,
  ],
  templateUrl: './expense-list.html',
  styleUrl: './expense-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ExpenseListComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly expensesService = inject(ExpensesService);
  private readonly categoriesService = inject(CategoriesService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly toastService = inject(ToastService);
  private readonly preferencesService = inject(UserPreferencesService);
  readonly quickAddService = inject(QuickAddExpenseService);

  readonly loading = signal(false);
  readonly expenses = signal<Expense[]>([]);
  readonly categories = signal<Category[]>([]);
  readonly workspaceOptions = this.workspaceContext.workspaces;
  readonly currentWorkspace = this.workspaceContext.selectedWorkspace;

  readonly today = computed(() => getTodayInTimezone(this.preferencesService.timezone()));

  private getFirstDayOfMonth(): string {
    return getFirstDayOfMonthInTimezone(this.preferencesService.timezone());
  }

  readonly filterFrom = signal(this.getFirstDayOfMonth());
  readonly filterTo = signal(getTodayInTimezone(this.preferencesService.timezone()));
  readonly filterCategory = signal('');
  readonly filterPaymentType = signal('');
  readonly filterSearch = signal('');

  readonly totalAmount = signal<string | null>(null);

  readonly columns = computed<TableColumn<Expense>[]>(() => [
    { key: 'date', header: this.translate.instant('expenses.date'), width: '130px' },
    { key: 'amount', header: this.translate.instant('expenses.amount'), align: 'right', width: '140px' },
    { key: 'category', header: this.translate.instant('expenses.category'), width: '180px' },
    { key: 'payment_method', header: this.translate.instant('expenses.payment_method'), width: '180px' },
    { key: 'description', header: this.translate.instant('expenses.description') },
    { key: 'actions', header: this.translate.instant('expenses.actions'), align: 'right', width: '110px' },
  ]);

  currentPage = 1;
  lastPage = 1;
  total = 0;

  workspaceId = '';
  readonly routeWorkspaceId = this.route.snapshot.parent?.paramMap.get('id');

  confirmOpen = false;
  itemToDelete: string | null = null;

  constructor() {
    toObservable(this.filterFrom).pipe(
      skip(1),
      debounceTime(400),
      distinctUntilChanged(),
      takeUntilDestroyed()
    ).subscribe(() => this.resetAndLoad());

    toObservable(this.filterTo).pipe(
      skip(1),
      debounceTime(400),
      distinctUntilChanged(),
      takeUntilDestroyed()
    ).subscribe(() => this.resetAndLoad());

    toObservable(this.filterCategory).pipe(
      skip(1),
      debounceTime(200),
      distinctUntilChanged(),
      takeUntilDestroyed()
    ).subscribe(() => this.resetAndLoad());

    toObservable(this.filterPaymentType).pipe(
      skip(1),
      debounceTime(200),
      distinctUntilChanged(),
      takeUntilDestroyed()
    ).subscribe(() => this.resetAndLoad());

    toObservable(this.filterSearch).pipe(
      skip(1),
      debounceTime(400),
      distinctUntilChanged(),
      takeUntilDestroyed()
    ).subscribe(() => this.resetAndLoad());

    effect(() => {
      const workspaceId = this.workspaceContext.currentWorkspaceId();
      if (workspaceId && workspaceId !== this.workspaceId) {
        this.workspaceId = workspaceId;
        this.loadCategories();
        this.loadExpenses();
      }
    });

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
    this.quickAddService.created$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(workspaceId => {
        if (workspaceId === this.workspaceContext.currentWorkspaceId()) {
          this.loadExpenses();
        }
      });

    if (this.routeWorkspaceId) {
      this.workspaceId = this.routeWorkspaceId;
      this.workspaceContext.setCurrentWorkspaceId(this.workspaceId);
      this.loadCategories();
      this.loadExpenses();
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
          this.currentPage = 1;
          this.loadCategories();
          this.loadExpenses();
        }
      });
  }

  createRoute(): unknown[] {
    return this.routeWorkspaceId
      ? ['/user/workspaces', this.workspaceId, 'expenses', 'create']
      : ['/user/expenses/create'];
  }

  editRoute(expenseId: string): unknown[] {
    return this.routeWorkspaceId
      ? ['/user/workspaces', this.workspaceId, 'expenses', expenseId, 'edit']
      : ['/user/expenses', expenseId, 'edit'];
  }

  navigateToEdit(expenseId: string): void {
    void this.router.navigate(this.editRoute(expenseId));
  }

  loadExpenses(): void {
    if (!this.workspaceId) {
      return;
    }

    this.loading.set(true);
    this.loadTotal();

    this.expensesService
      .list(this.workspaceId, {
        from: this.filterFrom() || undefined,
        to: this.filterTo() || undefined,
        category_id: this.filterCategory() || undefined,
        payment_type: this.filterPaymentType() || undefined,
        search: this.filterSearch() || undefined,
        page: this.currentPage
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.expenses.set(response.data);
          this.currentPage = response.meta.current_page;
          this.lastPage = response.meta.last_page;
          this.total = response.meta.total;
          this.loading.set(false);
        },
        error: () => {
          this.loading.set(false);
        }
      });
  }

  private loadTotal(): void {
    this.expensesService
      .total(this.workspaceId, {
        from: this.filterFrom() || undefined,
        to: this.filterTo() || undefined,
        category_id: this.filterCategory() || undefined,
        payment_type: this.filterPaymentType() || undefined,
        search: this.filterSearch() || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.totalAmount.set(response.data.total);
        },
        error: () => {
          this.totalAmount.set(null);
        }
      });
  }

  loadCategories(): void {
    this.categoriesService.listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.categories.set(r.data);
          this.syncCategoryFilter(r.data);
        },
      });
  }

  onFromChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    if (value && value > this.today()) {
      (event.target as HTMLInputElement).value = this.filterFrom();
      return;
    }
    if (value && this.filterTo() && value > this.filterTo()) {
      this.filterTo.set('');
    }
    this.filterFrom.set(value);
  }

  onToChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    if (value && value > this.today()) {
      (event.target as HTMLInputElement).value = this.filterTo();
      return;
    }
    if (value && this.filterFrom() && value < this.filterFrom()) {
      (event.target as HTMLInputElement).value = this.filterTo();
      return;
    }
    this.filterTo.set(value);
  }

  onCategoryChange(event: Event): void {
    this.filterCategory.set((event.target as HTMLSelectElement).value);
  }

  onPaymentTypeChange(event: Event): void {
    this.filterPaymentType.set((event.target as HTMLSelectElement).value);
  }

  onSearchChange(event: Event): void {
    this.filterSearch.set((event.target as HTMLInputElement).value);
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

    this.expensesService
      .delete(this.workspaceId, this.itemToDelete)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.confirmOpen = false;
          this.itemToDelete = null;
          this.toastService.success(this.translate.instant('expenses.deleted_ok'));
          this.currentPage = 1;
          this.loadExpenses();
        },
        error: () => {
          this.confirmOpen = false;
          this.itemToDelete = null;
        }
      });
  }

  prevPage(): void {
    if (this.currentPage > 1) {
      this.currentPage--;
      this.loadExpenses();
    }
  }

  nextPage(): void {
    if (this.currentPage < this.lastPage) {
      this.currentPage++;
      this.loadExpenses();
    }
  }

  getPaymentLabel(expense: Expense): string {
    if (expense.payment_type === 'cash') {
      return this.translate.instant('payment.cash');
    }
    if (!expense.payment_instrument) {
      return expense.payment_type === 'card'
        ? this.translate.instant('payment.card')
        : this.translate.instant('payment.other');
    }
    if (isCard(expense.payment_instrument)) {
      const card = expense.payment_instrument;
      return card.last_4_digits ? `${card.name} ····${card.last_4_digits}` : card.name;
    }
    return expense.payment_instrument.name;
  }

  private resetAndLoad(): void {
    if (!this.workspaceId) {
      return;
    }

    this.currentPage = 1;
    this.loadExpenses();
  }

  private syncCategoryFilter(categories: Category[]): void {
    const selectedCategoryId = this.filterCategory();
    if (!selectedCategoryId) {
      return;
    }

    const isValid = categories.some(category => category.id === selectedCategoryId);
    if (isValid) {
      return;
    }

    this.filterCategory.set('');
  }
}
