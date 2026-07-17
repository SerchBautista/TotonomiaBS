import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  computed,
  DestroyRef,
  effect,
  HostListener,
  inject,
  input,
  OnInit,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { CategoriesService } from '../../../core/services/categories';
import { ExpensesService } from '../../../core/services/expenses';
import { BudgetsService } from '../../../core/services/budgets.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { ToastService } from '../../../core/services/toast.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { QuickAddExpenseService } from '../../../core/services/quick-add-expense.service';
import { Category } from '../../../core/models/category.model';
import { parsePaymentValue, WorkspacePaymentMethodSummary } from '../../../core/models/payment-method.model';
import { Workspace, WorkspaceMember } from '../../../core/models/workspace.model';
import { Expense } from '../../../core/models/expense.model';
import { BudgetWarning } from '../../../core/models/budget.model';
import { BudgetAdjustmentModalComponent, AdjustmentModalData } from '../../budgets/budget-adjustment-modal/budget-adjustment-modal';
import { WorkspaceSwitcherComponent } from '../../../shared/workspace-switcher/workspace-switcher';
import { getTodayInTimezone, notFutureDateValidator } from '../../../core/utils/date-utils';
import { syncCategorySelection, syncPaymentSelection, getDefaultPaymentValue } from '../../fixed-expenses/fixed-expense-form.utils';
import {
  buildAdjustmentModalData,
  emitBudgetWarningToasts,
  getInlineCategoryWorkspaceSelection,
  getInlineWorkspaceSelection,
} from '../expense-form.utils';
import { ExpenseInlineCategoryFormComponent } from '../expense-inline-category-form/expense-inline-category-form';
import { ExpenseInlinePaymentCreatedEvent, ExpenseInlinePaymentFormComponent } from '../expense-inline-payment-form/expense-inline-payment-form';

@Component({
  selector: 'app-quick-add-expense-fab',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    RouterLink,
    BudgetAdjustmentModalComponent,
    WorkspaceSwitcherComponent,
    ExpenseInlineCategoryFormComponent,
    ExpenseInlinePaymentFormComponent,
  ],
  templateUrl: './quick-add-expense-fab.html',
  styleUrl: './quick-add-expense-fab.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class QuickAddExpenseFabComponent implements OnInit {
  readonly showFabButton = input(true);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly quickAddService = inject(QuickAddExpenseService);
  private readonly categoriesService = inject(CategoriesService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);
  private readonly expensesService = inject(ExpensesService);
  private readonly budgetsService = inject(BudgetsService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly membersService = inject(WorkspaceMembersService);
  private readonly workspacesService = inject(WorkspacesService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly authState = inject(AuthStateService);
  private readonly preferencesService = inject(UserPreferencesService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly isOpen = signal(false);
  readonly loading = signal(false);
  readonly todayLocal = computed(() => getTodayInTimezone(this.preferencesService.timezone()));
  readonly categories = signal<Category[]>([]);
  readonly validPaymentMethods = signal<WorkspacePaymentMethodSummary[]>([]);
  readonly cashMethod = computed(() => this.validPaymentMethods().find((m) => m.type === 'cash') ?? null);
  readonly cards = computed(() => this.validPaymentMethods().filter((m) => m.type === 'card'));
  readonly otherMethods = computed(() => this.validPaymentMethods().filter((m) => m.type === 'other'));
  readonly workspaceParticipants = signal<WorkspaceMember[]>([]);
  readonly isSharedWorkspace = signal(false);
  readonly workspaces = this.workspaceContext.workspaces;
  readonly ownerWorkspaces = computed(() =>
    this.workspaces().filter((workspace) => workspace.owner_id === this.authState.userId()),
  );
  readonly selectedWorkspaceId = computed(() => this.workspaceContext.currentWorkspaceId() ?? '');
  readonly adjustmentModalOpen = signal(false);
  readonly adjustmentModalData = signal<AdjustmentModalData>({ workspaceId: '', month: '', categories: [] });
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
  readonly showNewCategoryForm = signal(false);
  readonly showNewCardForm = signal(false);
  readonly showNewOtherForm = signal(false);
  readonly form = this.fb.group({
    amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
    category_id: ['', Validators.required],
    date: [
      getTodayInTimezone(this.preferencesService.timezone()),
      notFutureDateValidator(this.preferencesService.timezone()),
    ],
    payment_value: [''],
    description: [''],
    paid_by_user_id: [''],
  });

  constructor() {
    effect(() => {
      const id = this.workspaceContext.currentWorkspaceId();
      if (id && this.isOpen()) this.loadOptions(id);
    });
    this.paymentMethodsService.paymentMethodCreated$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ workspaceId }) => {
        if (workspaceId === this.workspaceId && this.isOpen()) this.loadValidPaymentMethods();
      });
  }

  ngOnInit(): void {
    this.quickAddService.open$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.open());
  }

  get workspaceId(): string {
    return this.selectedWorkspaceId();
  }

  get manageCategoriesRoute(): string {
    return `/user/workspaces/${this.workspaceId}/categories`;
  }

  categoryInlineInitialWorkspaceIds(): string[] {
    return getInlineCategoryWorkspaceSelection(this.workspaceId, this.ownerWorkspaces());
  }

  paymentInlineInitialWorkspaceIds(): string[] {
    return getInlineWorkspaceSelection(this.workspaceId, this.ownerWorkspaces());
  }

  open(): void {
    this.isOpen.set(true);
    setTimeout(() => (document.getElementById('qa-amount') as HTMLElement | null)?.focus(), 0);
  }

  close(): void {
    this.isOpen.set(false);
    this.showNewCategoryForm.set(false);
    this.showNewCardForm.set(false);
    this.showNewOtherForm.set(false);
    const defaultCat = this.categories().find((c) => c.is_default);
    this.form.reset({
      amount: '',
      category_id: (defaultCat ?? this.categories()[0])?.id ?? '',
      date: getTodayInTimezone(this.preferencesService.timezone()),
      payment_value: getDefaultPaymentValue(this.validPaymentMethods()),
      description: '',
      paid_by_user_id: '',
    });
  }

  toggle(): void {
    if (this.isOpen()) this.close();
    else this.open();
  }

  onBackdropClick(event: MouseEvent): void {
    if ((event.target as HTMLElement).classList.contains('quick-add-backdrop')) this.close();
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.isOpen()) this.close();
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
    this.cdr.markForCheck();
  }

  onCategoryInlineCanceled(): void {
    this.showNewCategoryForm.set(false);
  }

  onPaymentValueChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const isNewCard = value === 'card:new';
    const isNewOther = value === 'other:new';
    this.showNewCardForm.set(isNewCard);
    this.showNewOtherForm.set(isNewOther);
    if (isNewCard || isNewOther) this.form.patchValue({ payment_value: '' });
  }

  onPaymentInstrumentCreated(event: ExpenseInlinePaymentCreatedEvent): void {
    this.paymentMethodsService.notifyCreated(this.workspaceId, event.instrument);
    this.showNewCardForm.set(false);
    this.showNewOtherForm.set(false);
    this.loadValidPaymentMethods(event.paymentValue);
    this.cdr.markForCheck();
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
    const workspaceId = this.workspaceId;
    if (!workspaceId) return;
    this.loading.set(true);
    const paymentValue = this.form.value.payment_value ?? '';
    const { paymentType, paymentInstrumentId } = parsePaymentValue(paymentValue);
    const payload = {
      amount: (this.form.value.amount ?? '').trim(),
      date: this.form.value.date ?? getTodayInTimezone(this.preferencesService.timezone()),
      category_id: this.form.value.category_id ?? '',
      payment_type: paymentType,
      payment_instrument_id: paymentInstrumentId,
      description: this.form.value.description || null,
      paid_by_user_id: this.isSharedWorkspace() ? this.form.value.paid_by_user_id || null : null,
    };
    this.expensesService
      .create(workspaceId, payload)
      .pipe(finalize(() => this.loading.set(false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
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
          this.finishSuccess();
        },
        error: () => {},
      });
  }

  closeAdjustmentModal(): void {
    this.adjustmentModalOpen.set(false);
  }

  onAdjustmentCreated(): void {
    this.adjustmentModalOpen.set(false);
    this.finishSuccess();
  }

  private finishSuccess(): void {
    this.toastService.success(this.translate.instant('quick_add.success'));
    this.quickAddService.notifyCreated(this.workspaceId);
    this.close();
  }

  private openAdjustmentModal(_warning: BudgetWarning): void {
    const date = this.form.value.date ?? getTodayInTimezone(this.preferencesService.timezone());
    this.budgetsService
      .status(this.workspaceId, date.substring(0, 7))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.adjustmentModalData.set(
            buildAdjustmentModalData(
              this.workspaceId,
              date,
              this.form.value.category_id ?? '',
              this.categories(),
              r.data,
            ),
          );
          this.adjustmentModalOpen.set(true);
          this.cdr.markForCheck();
        },
        error: () => this.finishSuccess(),
      });
  }

  private loadOptions(workspaceId: string): void {
    this.workspaceParticipants.set([]);
    this.isSharedWorkspace.set(false);
    this.workspacesService
      .getById(workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.workspaceDetail.set(r.data);
          this.cdr.markForCheck();
        },
      });
    this.categoriesService
      .listValid(workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.categories.set(r.data);
          syncCategorySelection(this.form, r.data);
        },
      });
    this.loadValidPaymentMethods();
    this.membersService
      .list(workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.workspaceParticipants.set(r.data);
          const shared = r.data.length > 1;
          this.isSharedWorkspace.set(shared);
          if (shared && !this.form.value.paid_by_user_id) {
            const ws = this.workspaces().find((w) => w.id === workspaceId);
            const owner = r.data.find((m) => m.id === ws?.owner_id);
            if (owner) this.form.patchValue({ paid_by_user_id: owner.id });
          }
        },
      });
  }

  private loadValidPaymentMethods(preferredPaymentValue?: string): void {
    if (!this.workspaceId) {
      this.validPaymentMethods.set([]);
      syncPaymentSelection(this.form, [], { fallbackToFirst: true });
      return;
    }
    this.paymentMethodsService
      .listValid(this.workspaceId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.validPaymentMethods.set(response.data);
          syncPaymentSelection(this.form, response.data, { preferredPaymentValue, fallbackToFirst: true });
          this.cdr.markForCheck();
        },
        error: () => {
          this.validPaymentMethods.set([]);
          syncPaymentSelection(this.form, [], { fallbackToFirst: true });
          this.cdr.markForCheck();
        },
      });
  }
}
