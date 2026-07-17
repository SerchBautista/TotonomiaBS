import { ChangeDetectionStrategy, ChangeDetectorRef, Component, computed, DestroyRef, effect, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { ExpensesService } from '../../../core/services/expenses';
import { CategoriesService } from '../../../core/services/categories';
import { BudgetsService } from '../../../core/services/budgets.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { WorkspacesService } from '../../../core/services/workspaces';
import { Category } from '../../../core/models/category.model';
import { parsePaymentValue, WorkspacePaymentMethodSummary } from '../../../core/models/payment-method.model';
import { Workspace, WorkspaceMember } from '../../../core/models/workspace.model';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { ToastService } from '../../../core/services/toast.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { Expense } from '../../../core/models/expense.model';
import { BudgetWarning } from '../../../core/models/budget.model';
import { BudgetAdjustmentModalComponent, AdjustmentModalData } from '../../budgets/budget-adjustment-modal/budget-adjustment-modal';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { FormCardComponent } from '../../../shared/form-card/form-card';
import { syncCategorySelection, syncPaymentSelection } from '../../fixed-expenses/fixed-expense-form.utils';
import {
  buildAdjustmentModalData,
  buildExpenseFormPatch,
  emitBudgetWarningToasts,
  getInlineCategoryWorkspaceSelection,
  getInlineWorkspaceSelection,
} from '../expense-form.utils';
import { ExpenseInlineCategoryFormComponent } from '../expense-inline-category-form/expense-inline-category-form';
import {
  ExpenseInlinePaymentCreatedEvent,
  ExpenseInlinePaymentFormComponent,
} from '../expense-inline-payment-form/expense-inline-payment-form';
import { getTodayInTimezone, notFutureDateValidator } from '../../../core/utils/date-utils';

@Component({
  selector: 'app-expense-form',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    RouterLink,
    BudgetAdjustmentModalComponent,
    PageHeaderComponent,
    FormCardComponent,
    ExpenseInlineCategoryFormComponent,
    ExpenseInlinePaymentFormComponent,
  ],
  templateUrl: './expense-form.html',
  styleUrl: './expense-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ExpenseFormComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly expensesService = inject(ExpensesService);
  private readonly categoriesService = inject(CategoriesService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);
  private readonly budgetsService = inject(BudgetsService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly fb = inject(FormBuilder);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly membersService = inject(WorkspaceMembersService);
  private readonly toastService = inject(ToastService);
  private readonly authState = inject(AuthStateService);
  private readonly preferencesService = inject(UserPreferencesService);
  private readonly workspacesService = inject(WorkspacesService);

  readonly loading = signal(false);
  readonly todayLocal = computed(() => getTodayInTimezone(this.preferencesService.timezone()));
  readonly categories = signal<Category[]>([]);
  readonly validPaymentMethods = signal<WorkspacePaymentMethodSummary[]>([]);
  readonly cashMethod = computed(() => this.validPaymentMethods().find((method) => method.type === 'cash') ?? null);
  readonly cards = computed(() => this.validPaymentMethods().filter((method) => method.type === 'card'));
  readonly otherMethods = computed(() => this.validPaymentMethods().filter((method) => method.type === 'other'));
  readonly workspaceParticipants = signal<WorkspaceMember[]>([]);
  readonly isSharedWorkspace = signal(false);
  readonly workspaceOptions = this.workspaceContext.workspaces;
  readonly ownerWorkspaces = computed(() =>
    this.workspaceOptions().filter((workspace) => workspace.owner_id === this.authState.userId()),
  );
  readonly showNewCardForm = signal(false);
  readonly showNewOtherForm = signal(false);
  readonly showNewCategoryForm = signal(false);
  readonly workspaceDetail = signal<Workspace | null>(null);
  readonly isWorkspaceOwner = computed(() => {
    const ws = this.workspaceDetail();
    const userId = this.authState.userId();
    return !!ws && !!userId && ws.owner_id === userId;
  });
  readonly canCreateCategory = computed(() =>
    this.isWorkspaceOwner() || (this.workspaceDetail()?.current_user_permissions?.can_add_categories ?? false),
  );
  readonly canSelectAdditionalCategoryWorkspaces = computed(() => this.ownerWorkspaces().length > 0);
  readonly adjustmentModalOpen = signal(false);
  readonly adjustmentModalData = signal<AdjustmentModalData>({ workspaceId: '', month: '', categories: [] });
  readonly formTitle = computed(() =>
    this.mode === 'create'
      ? this.translate.instant('expenses.form_create_title')
      : this.translate.instant('expenses.form_edit_title'),
  );
  readonly form = this.fb.group({
    amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
    date: [
      getTodayInTimezone(this.preferencesService.timezone()),
      [Validators.required, notFutureDateValidator(this.preferencesService.timezone())],
    ],
    category_id: ['', [Validators.required]],
    payment_value: ['', [Validators.required]],
    description: [''],
    paid_by_user_id: [''],
  });

  mode: 'create' | 'edit' = 'create';
  workspaceId = '';
  expenseId: string | null = null;
  private optionsLoadedWorkspaceId = '';
  readonly routeWorkspaceId = this.route.snapshot.parent?.paramMap.get('id');

  constructor() {
    effect(() => {
      const id = this.workspaceContext.currentWorkspaceId();
      if (id && id !== this.workspaceId && !this.routeWorkspaceId) {
        const wasInitialized = this.workspaceId !== '';
        this.workspaceId = id;
        if (wasInitialized) {
          this.form.patchValue({ category_id: '', payment_value: '' });
          this.loadOptions();
        }
        this.cdr.markForCheck();
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
    this.paymentMethodsService.paymentMethodCreated$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ workspaceId }) => {
        if (workspaceId === this.workspaceId) this.loadValidPaymentMethods();
      });

    this.mode = (this.route.snapshot.data['mode'] as 'create' | 'edit' | undefined) ?? 'create';
    this.expenseId = this.route.snapshot.paramMap.get('eid');

    if (this.routeWorkspaceId) {
      this.workspaceId = this.routeWorkspaceId;
      this.workspaceContext.setCurrentWorkspaceId(this.workspaceId);
      this.loadOptions(() => this.maybeLoadExpense());
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
      const optionsLoaded = this.optionsLoadedWorkspaceId === workspaceId;

      if (changed) {
        this.form.patchValue({ category_id: '', payment_value: '' });
        this.loadOptions(() => this.maybeLoadExpense());
      } else if (!optionsLoaded) {
        this.loadOptions(() => this.maybeLoadExpense());
      } else if (this.mode === 'edit' && this.expenseId) {
        this.loadExpense();
      }
    });
  }

  selectedWorkspace(): Workspace | null {
    return this.workspaceContext.selectedWorkspace() ?? this.workspaceDetail();
  }

  backRoute(): string {
    return this.routeWorkspaceId ? `/user/workspaces/${this.workspaceId}/expenses` : '/user/expenses';
  }

  categoryInlineInitialWorkspaceIds(): string[] {
    return getInlineCategoryWorkspaceSelection(this.workspaceId, this.ownerWorkspaces());
  }

  paymentInlineInitialWorkspaceIds(): string[] {
    return getInlineWorkspaceSelection(this.workspaceId, this.ownerWorkspaces());
  }

  onPaymentValueChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const isNewCard = value === 'card:new';
    const isNewOther = value === 'other:new';
    this.showNewCardForm.set(isNewCard);
    this.showNewOtherForm.set(isNewOther);
    if (isNewCard || isNewOther) this.form.patchValue({ payment_value: '' });
  }

  onCategoryValueChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.showNewCategoryForm.set(value === 'category:new');
    this.form.patchValue({ category_id: value === 'category:new' ? '' : value });
  }

  onCategoryCreated(category: Category): void {
    this.categories.update((list) => [...list, category]);
    this.form.patchValue({ category_id: category.id });
    this.showNewCategoryForm.set(false);
  }

  onCategoryInlineCanceled(): void {
    this.showNewCategoryForm.set(false);
  }

  onPaymentInstrumentCreated(event: ExpenseInlinePaymentCreatedEvent): void {
    this.paymentMethodsService.notifyCreated(this.workspaceId, event.instrument);
    this.showNewCardForm.set(false);
    this.showNewOtherForm.set(false);
    this.loadValidPaymentMethods(event.paymentValue);
  }

  onPaymentInlineCanceled(): void {
    this.showNewCardForm.set(false);
    this.showNewOtherForm.set(false);
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    const paymentValue = this.form.value.payment_value ?? '';
    const { paymentType, paymentInstrumentId } = parsePaymentValue(paymentValue);
    const payload = {
      amount: (this.form.value.amount ?? '').trim(),
      date: this.form.value.date ?? '',
      category_id: this.form.value.category_id ?? '',
      payment_type: paymentType,
      payment_instrument_id: paymentInstrumentId,
      description: this.form.value.description || null,
      paid_by_user_id: this.isSharedWorkspace() ? (this.form.value.paid_by_user_id || null) : null,
    };
    const request$ =
      this.mode === 'create'
        ? this.expensesService.create(this.workspaceId, payload)
        : this.expensesService.update(this.workspaceId, this.expenseId!, payload);

    request$
      .pipe(finalize(() => this.loading.set(false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (this.mode === 'create') {
            const data = response.data as Expense & { budget_warnings?: BudgetWarning[] };
            const overBudgetWarning = emitBudgetWarningToasts(
              data.budget_warnings,
              this.translate,
              this.toastService,
            );
            if (overBudgetWarning) {
              this.openAdjustmentModal(overBudgetWarning);
              return;
            }
          }
          void this.router.navigateByUrl(this.backRoute());
        },
        error: () => {},
      });
  }

  closeAdjustmentModal(): void {
    this.adjustmentModalOpen.set(false);
  }

  onAdjustmentCreated(): void {
    this.adjustmentModalOpen.set(false);
    void this.router.navigateByUrl(this.backRoute());
  }

  private maybeLoadExpense(): void {
    if (this.mode === 'edit' && this.expenseId) this.loadExpense();
  }

  private openAdjustmentModal(_warning: BudgetWarning): void {
    const date = this.form.value.date ?? getTodayInTimezone(this.preferencesService.timezone());
    this.budgetsService
      .status(this.workspaceId, date.substring(0, 7))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.adjustmentModalData.set(
            buildAdjustmentModalData(
              this.workspaceId,
              date,
              this.form.value.category_id ?? '',
              this.categories(),
              response.data,
            ),
          );
          this.adjustmentModalOpen.set(true);
          this.cdr.markForCheck();
        },
        error: () => void this.router.navigateByUrl(this.backRoute()),
      });
  }

  private loadOptions(afterCategoriesLoaded?: () => void): void {
    this.optionsLoadedWorkspaceId = this.workspaceId;
    this.workspaceParticipants.set([]);
    this.isSharedWorkspace.set(false);

    this.workspacesService
      .getById(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (response) => this.workspaceDetail.set(response.data) });

    this.categoriesService
      .listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.categories.set(response.data);
          syncCategorySelection(this.form, response.data);
          afterCategoriesLoaded?.();
        },
      });

    this.loadValidPaymentMethods();

    this.membersService
      .list(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.workspaceParticipants.set(response.data);
          const shared = response.data.length > 1;
          this.isSharedWorkspace.set(shared);
          if (shared && !this.form.value.paid_by_user_id) {
            const owner = response.data.find((member) => member.id === this.selectedWorkspace()?.owner_id);
            if (owner) this.form.patchValue({ paid_by_user_id: owner.id });
          }
        },
      });
  }

  private loadExpense(): void {
    this.loading.set(true);
    this.expensesService
      .getById(this.workspaceId, this.expenseId!)
      .pipe(finalize(() => this.loading.set(false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.form.patchValue(buildExpenseFormPatch(response.data));
          syncCategorySelection(this.form, this.categories());
          syncPaymentSelection(this.form, this.validPaymentMethods());
        },
        error: () => this.loading.set(false),
      });
  }

  private loadValidPaymentMethods(preferredPaymentValue?: string): void {
    this.paymentMethodsService
      .listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.validPaymentMethods.set(response.data);
          syncPaymentSelection(this.form, response.data, {
            preferredPaymentValue,
            fallbackToFirst: true,
          });
        },
        error: () => {
          this.validPaymentMethods.set([]);
          syncPaymentSelection(this.form, [], { fallbackToFirst: true });
        },
      });
  }
}
