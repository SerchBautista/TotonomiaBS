import { signal } from '@angular/core';
import { By } from '@angular/platform-browser';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { of, Subject, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { BudgetAdjustmentsService } from '../../../core/services/budget-adjustments.service';
import { BudgetsService } from '../../../core/services/budgets.service';
import { CardsService } from '../../../core/services/cards.service';
import { CategoriesService } from '../../../core/services/categories';
import { ExpensesService } from '../../../core/services/expenses';
import { OtherPaymentMethodsService } from '../../../core/services/other-payment-methods.service';
import { QuickAddExpenseService } from '../../../core/services/quick-add-expense.service';
import { ToastService } from '../../../core/services/toast.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { ExpenseInlineCategoryFormComponent } from '../expense-inline-category-form/expense-inline-category-form';
import { ExpenseInlinePaymentFormComponent } from '../expense-inline-payment-form/expense-inline-payment-form';
import { QuickAddExpenseFabComponent } from './quick-add-expense-fab';

type WorkspaceOption = { id: string; name: string; owner_id: string };

const mockCategories = [
  {
    id: 'cat-1',
    name: 'Food',
    icon: null,
    color: '#ff0000',
    user_id: 'user-1',
    enabled: true,
    is_default: false,
  },
];

const mockCards = [
  {
    id: 'card-1',
    workspace_id: 'ws-1',
    name: 'Visa',
    card_type: 'credit' as const,
    brand: 'Visa',
    last_4_digits: '1234',
    is_default: false,
  },
];

const mockOtherMethods = [
  { id: 'other-1', workspace_id: 'ws-1', name: 'PayPal', description: null, is_default: false },
];

const mockWorkspaceDetail = {
  id: 'ws-1',
  owner_id: 'owner-2',
  name: 'Test WS',
  type: 'personal' as const,
  currency_code: 'USD',
  current_user_permissions: {
    can_add_fixed_expenses: true,
    can_add_categories: false,
  },
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
};

const mockMembers = [
  {
    id: 'user-1',
    name: 'Owner',
    email: 'owner@test.dev',
    role: 'owner' as const,
    can_add_fixed_expenses: true,
    can_add_categories: true,
  },
];

describe('QuickAddExpenseFabComponent', () => {
  let fixture: ComponentFixture<QuickAddExpenseFabComponent>;
  let component: QuickAddExpenseFabComponent;

  const toastMock = { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() };
  const workspaceId = signal<string | null>('ws-1');
  const workspaceList = signal<WorkspaceOption[]>([
    { id: 'ws-1', name: 'Test WS', owner_id: 'user-1' },
  ]);
  const openSubject = new Subject<void>();

  const workspaceContextMock = {
    currentWorkspaceId: workspaceId.asReadonly(),
    selectedWorkspace: signal({ id: 'ws-1', name: 'Test WS' }).asReadonly(),
    workspaces: workspaceList.asReadonly(),
    setCurrentWorkspaceId: vi.fn((id: string | null) => workspaceId.set(id)),
    ensureLoaded: vi.fn().mockResolvedValue([]),
  };

  const authStateMock = {
    userId: vi.fn().mockReturnValue('user-1'),
  };

  const preferencesMock = {
    theme: signal<'dark' | 'light'>('dark').asReadonly(),
    locale: signal<'es' | 'en'>('es').asReadonly(),
    timezone: signal('UTC').asReadonly(),
    applyTheme: vi.fn(),
    applyLocale: vi.fn(),
    applyTimezone: vi.fn(),
    applyAll: vi.fn(),
    loadFromBackend: vi.fn(),
    saveToBackend: vi.fn(),
    getAvailableTimezones: vi.fn().mockReturnValue(['UTC']),
  };

  const quickAddServiceMock = {
    open$: openSubject.asObservable(),
    notifyCreated: vi.fn(),
  };

  let categoriesMock: { listValid: ReturnType<typeof vi.fn>; createMine: ReturnType<typeof vi.fn> };
  let cardsMock: { list: ReturnType<typeof vi.fn>; create: ReturnType<typeof vi.fn> };
  let otherMethodsMock: { list: ReturnType<typeof vi.fn>; create: ReturnType<typeof vi.fn> };
  let expensesMock: { create: ReturnType<typeof vi.fn> };
  let budgetsMock: { status: ReturnType<typeof vi.fn> };
  let workspacesMock: { getById: ReturnType<typeof vi.fn> };
  let membersMock: { list: ReturnType<typeof vi.fn> };
  let budgetAdjustmentsMock: {
    create: ReturnType<typeof vi.fn>;
    available: ReturnType<typeof vi.fn>;
  };
  let paymentMethodsMock: {
    listValid: ReturnType<typeof vi.fn>;
    paymentMethodCreated$: ReturnType<typeof of>;
    notifyCreated: ReturnType<typeof vi.fn>;
  };

  async function setup(
    overrides: {
      categories?: ReturnType<typeof vi.fn>;
      cards?: ReturnType<typeof vi.fn>;
      expenses?: ReturnType<typeof vi.fn>;
      otherMethods?: ReturnType<typeof vi.fn>;
      workspaces?: WorkspaceOption[];
      members?: ReturnType<typeof vi.fn>;
    } = {},
  ) {
    categoriesMock = {
      listValid: overrides.categories ?? vi.fn().mockReturnValue(of({ data: mockCategories })),
      createMine: vi.fn().mockReturnValue(of({ data: mockCategories[0] })),
    };
    cardsMock = {
      list: overrides.cards ?? vi.fn().mockReturnValue(of({ data: mockCards })),
      create: vi.fn(),
    };
    otherMethodsMock = {
      list: overrides.otherMethods ?? vi.fn().mockReturnValue(of({ data: mockOtherMethods })),
      create: vi.fn(),
    };
    paymentMethodsMock = {
      listValid: vi.fn().mockReturnValue(
        of({
          data: [
            {
              id: 'cash',
              type: 'cash',
              name: 'Cash',
              display_name: 'Cash',
              masked_details: null,
              is_linked: true,
              is_valid_for_transactions: true,
              state: 'linked',
            },
            {
              id: 'card-1',
              type: 'card',
              name: 'Visa',
              display_name: 'Visa •••• 1234',
              masked_details: '1234',
              is_linked: true,
              is_valid_for_transactions: true,
              state: 'linked',
            },
            {
              id: 'other-1',
              type: 'other',
              name: 'PayPal',
              display_name: 'PayPal',
              masked_details: null,
              is_linked: true,
              is_valid_for_transactions: true,
              state: 'linked',
            },
          ],
        }),
      ),
      paymentMethodCreated$: of(),
      notifyCreated: vi.fn(),
    };
    expensesMock = {
      create:
        overrides.expenses ??
        vi.fn().mockReturnValue(of({ data: { id: 'exp-1', amount: '100', budget_warnings: [] } })),
    };
    budgetsMock = {
      status: vi
        .fn()
        .mockReturnValue(of({ data: { month: '2024-01', general: null, categories: [] } })),
    };
    workspacesMock = {
      getById: vi.fn().mockReturnValue(of({ data: mockWorkspaceDetail })),
    };
    membersMock = {
      list: overrides.members ?? vi.fn().mockReturnValue(of({ data: mockMembers })),
    };
    budgetAdjustmentsMock = {
      create: vi.fn(),
      available: vi.fn().mockReturnValue(of({ data: [] })),
    };

    workspaceId.set('ws-1');
    workspaceList.set(
      overrides.workspaces ?? [{ id: 'ws-1', name: 'Test WS', owner_id: 'user-1' }],
    );

    await TestBed.configureTestingModule({
      imports: [QuickAddExpenseFabComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: CategoriesService, useValue: categoriesMock },
        { provide: CardsService, useValue: cardsMock },
        { provide: OtherPaymentMethodsService, useValue: otherMethodsMock },
        { provide: PaymentMethodsService, useValue: paymentMethodsMock },
        { provide: ExpensesService, useValue: expensesMock },
        { provide: BudgetsService, useValue: budgetsMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: WorkspaceMembersService, useValue: membersMock },
        { provide: WorkspacesService, useValue: workspacesMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: AUTH_STATE_TOKEN, useValue: { role: () => 'user' } },
        { provide: UserPreferencesService, useValue: preferencesMock },
        { provide: QuickAddExpenseService, useValue: quickAddServiceMock },
        { provide: BudgetAdjustmentsService, useValue: budgetAdjustmentsMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        budgets: {
          warning_general: 'Aviso: el presupuesto general alcanzó el monto de alerta.',
          warning_category:
            'Aviso: el presupuesto de "{{name}}" alcanzó el monto de alerta.',
        },
        quick_add: {
          success: 'Gasto registrado correctamente.',
        },
      },
      true,
    );
    translate.use('es');

    fixture = TestBed.createComponent(QuickAddExpenseFabComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  }

  beforeEach(() => {
    vi.clearAllMocks();
    TestBed.resetTestingModule();
    authStateMock.userId.mockReturnValue('user-1');
    workspaceContextMock.setCurrentWorkspaceId.mockImplementation((id: string | null) =>
      workspaceId.set(id),
    );
  });

  it('shows FAB button and hides overlay when closed', async () => {
    await setup();
    const fab = fixture.nativeElement.querySelector('.quick-add-fab');
    const modal = fixture.nativeElement.querySelector('.quick-add-backdrop');
    expect(fab).toBeTruthy();
    expect(modal).toBeNull();
  });

  it('hides FAB button when showFabButton is false but opens panel via service', async () => {
    await setup();
    fixture.componentRef.setInput('showFabButton', false);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('.quick-add-fab')).toBeNull();

    openSubject.next();
    fixture.detectChanges();

    expect(component.isOpen()).toBe(true);
    expect(fixture.nativeElement.querySelector('.quick-add-backdrop')).toBeTruthy();
  });

  it('opens overlay and loads data on FAB click', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    expect(categoriesMock.listValid).toHaveBeenCalledWith('ws-1');
    expect(paymentMethodsMock.listValid).toHaveBeenCalledWith('ws-1');
    expect(workspacesMock.getById).toHaveBeenCalledWith('ws-1');
    expect(membersMock.list).toHaveBeenCalledWith('ws-1');
    expect(component.isOpen()).toBe(true);
    expect(fixture.nativeElement.querySelector('.quick-add-backdrop')).toBeTruthy();
  });

  it('does not submit when amount is empty', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    component.form.patchValue({ amount: '', category_id: 'cat-1' });
    component.submit();

    expect(expensesMock.create).not.toHaveBeenCalled();
    expect(component.form.controls.amount.touched).toBe(true);
  });

  it('does not submit when category_id is empty', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    component.form.patchValue({ amount: '50', category_id: '' });
    component.submit();

    expect(expensesMock.create).not.toHaveBeenCalled();
    expect(component.form.controls.category_id.touched).toBe(true);
  });

  it('submits with valid workspace payment method and closes overlay on success', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    component.form.patchValue({
      amount: '100',
      category_id: 'cat-1',
      payment_value: 'card:card-1',
    });
    component.submit();

    expect(expensesMock.create).toHaveBeenCalledWith(
      'ws-1',
      expect.objectContaining({ payment_type: 'card', payment_instrument_id: 'card-1' }),
    );
    expect(component.isOpen()).toBe(false);
    expect(toastMock.success).toHaveBeenCalled();
    expect(quickAddServiceMock.notifyCreated).toHaveBeenCalledWith('ws-1');
  });

  it('keeps overlay open without local error toast on HTTP error', async () => {
    await setup({ expenses: vi.fn().mockReturnValue(throwError(() => new Error('server error'))) });
    component.open();
    fixture.detectChanges();

    component.form.patchValue({ amount: '100', category_id: 'cat-1' });
    component.submit();

    expect(component.isOpen()).toBe(true);
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('shows the new copy in the warning toast when a general budget warning is returned', async () => {
    const createWithWarning = vi.fn().mockReturnValue(
      of({
        data: {
          id: 'exp-2',
          amount: '200',
          budget_warnings: [
            { scope: 'general', budget_id: 'b-1', percentage: 95, alert_threshold: 80 },
          ],
        },
      }),
    );

    await setup({ expenses: createWithWarning });
    component.open();
    fixture.detectChanges();

    component.form.patchValue({ amount: '200', category_id: 'cat-1' });
    component.submit();

    expect(toastMock.warning).toHaveBeenCalledWith(
      'Aviso: el presupuesto general alcanzó el monto de alerta.',
    );
    expect(toastMock.success).toHaveBeenCalled();
  });

  it('shows the category warning toast with the category name when a category budget warning is returned', async () => {
    const createWithCategoryWarning = vi.fn().mockReturnValue(
      of({
        data: {
          id: 'exp-3',
          amount: '50',
          budget_warnings: [
            {
              scope: 'category',
              budget_id: 'b-2',
              category_id: 'cat-1',
              category_name: 'Food',
              percentage: 92,
              alert_threshold: 80,
            },
          ],
        },
      }),
    );

    await setup({ expenses: createWithCategoryWarning });
    component.open();
    fixture.detectChanges();

    component.form.patchValue({ amount: '50', category_id: 'cat-1' });
    component.submit();

    expect(toastMock.warning).toHaveBeenCalledWith(
      'Aviso: el presupuesto de "Food" alcanzó el monto de alerta.',
    );
  });

  it('does not emit any budget warning toast when the response has no warnings', async () => {
    const createWithoutWarnings = vi.fn().mockReturnValue(
      of({ data: { id: 'exp-4', amount: '10', budget_warnings: [] } }),
    );

    await setup({ expenses: createWithoutWarnings });
    component.open();
    fixture.detectChanges();

    component.form.patchValue({ amount: '10', category_id: 'cat-1' });
    component.submit();

    expect(toastMock.warning).not.toHaveBeenCalled();
    expect(toastMock.success).toHaveBeenCalled();
  });

  it('closes overlay and resets form when close is invoked', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    component.form.patchValue({ amount: '75', category_id: 'cat-1' });
    component.close();

    expect(component.isOpen()).toBe(false);
    expect(component.form.value.amount).toBe('');
  });

  it('shows empty state when no categories are available', async () => {
    authStateMock.userId.mockReturnValue('user-2');
    await setup({ categories: vi.fn().mockReturnValue(of({ data: [] })) });
    component.open();
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(component.categories()).toHaveLength(0);
    expect(fixture.nativeElement.querySelector('.empty-state-hint')).toBeTruthy();
  });

  it('pre-selects the default category id when categories include one with is_default: true', async () => {
    const defaultCat = {
      id: 'cat-default',
      name: 'Default Cat',
      icon: null,
      color: '#00ff00',
      user_id: 'user-1',
      enabled: true,
      is_default: true,
    };
    const otherCat = {
      id: 'cat-other',
      name: 'Other Cat',
      icon: null,
      color: '#0000ff',
      user_id: 'user-1',
      enabled: true,
      is_default: false,
    };

    await setup({ categories: vi.fn().mockReturnValue(of({ data: [otherCat, defaultCat] })) });
    component.open();
    fixture.detectChanges();

    expect(component.form.value.category_id).toBe('cat-default');
  });

  it('pre-selects the first valid payment method (cash) by default', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    expect(component.form.value.payment_value).toBe('cash');
  });

  it('reloads data every time open() is called', async () => {
    await setup();

    component.open();
    fixture.detectChanges();
    await fixture.whenStable();

    categoriesMock.listValid.mockClear();
    paymentMethodsMock.listValid.mockClear();

    component.close();
    fixture.detectChanges();
    await fixture.whenStable();
    component.open();
    fixture.detectChanges();
    await fixture.whenStable();

    expect(categoriesMock.listValid).toHaveBeenCalledTimes(1);
    expect(paymentMethodsMock.listValid).toHaveBeenCalledTimes(1);
  });

  it('includes cash when valid workspace payment methods return it', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    const options = Array.from<HTMLOptionElement>(
      fixture.nativeElement.querySelectorAll('#qa-payment option'),
    ).map((option) => option.value);
    expect(options).toContain('cash');
  });

  it('notifies payment method creation when creating a new card inline', async () => {
    await setup();
    component.open();
    component.showNewCardForm.set(true);
    fixture.detectChanges();
    cardsMock.create.mockReturnValue(of({ data: mockCards[0] }));

    const paymentForm = fixture.debugElement.query(
      By.directive(ExpenseInlinePaymentFormComponent),
    ).componentInstance as ExpenseInlinePaymentFormComponent;
    paymentForm.cardForm.patchValue({
      name: 'Visa',
      card_type: 'credit',
      brand: 'Visa',
      last_4_digits: '1234',
    });
    paymentForm.submit();

    expect(paymentMethodsMock.notifyCreated).toHaveBeenCalledWith('ws-1', mockCards[0]);
  });

  it('notifies payment method creation when creating a new other method inline', async () => {
    await setup();
    component.open();
    component.showNewOtherForm.set(true);
    fixture.detectChanges();
    otherMethodsMock.create.mockReturnValue(of({ data: mockOtherMethods[0] }));

    const paymentForm = fixture.debugElement.query(
      By.directive(ExpenseInlinePaymentFormComponent),
    ).componentInstance as ExpenseInlinePaymentFormComponent;
    paymentForm.otherForm.patchValue({
      name: 'PayPal',
      description: '',
    });
    paymentForm.submit();

    expect(paymentMethodsMock.notifyCreated).toHaveBeenCalledWith('ws-1', mockOtherMethods[0]);
  });

  it('always includes the current workspace in inline category creation payload', async () => {
    await setup();
    component.open();
    component.showNewCategoryForm.set(true);
    fixture.detectChanges();

    const categoryForm = fixture.debugElement.query(
      By.directive(ExpenseInlineCategoryFormComponent),
    ).componentInstance as ExpenseInlineCategoryFormComponent;
    categoryForm.updateWorkspaceSelection(['ws-2']);
    categoryForm.form.patchValue({
      name: 'Nueva categoría',
      icon: 'tag',
      color: '#16324f',
    });
    categoryForm.submit();

    expect(categoriesMock.createMine).toHaveBeenCalledWith(
      expect.objectContaining({
        workspace_ids: ['ws-2', 'ws-1'],
      }),
      expect.any(Object),
    );
  });

  it('keeps the current workspace in inline category selection updates', async () => {
    await setup();
    component.open();
    component.showNewCategoryForm.set(true);
    fixture.detectChanges();

    const categoryForm = fixture.debugElement.query(
      By.directive(ExpenseInlineCategoryFormComponent),
    ).componentInstance as ExpenseInlineCategoryFormComponent;
    categoryForm.updateWorkspaceSelection(['ws-2']);

    expect(categoryForm.workspaceIds()).toEqual(['ws-2', 'ws-1']);
  });

  it('renders the custom modal panel with title, form and action buttons when open', async () => {
    await setup();
    component.open();
    fixture.detectChanges();

    const modal = fixture.nativeElement.querySelector('.quick-add-panel');
    const title = fixture.nativeElement.querySelector('.quick-add-header h3');
    const formCard = fixture.nativeElement.querySelector('app-form-card');
    const form = fixture.nativeElement.querySelector('.quick-add-panel form');
    const actions = fixture.nativeElement.querySelector('.quick-add-actions');

    expect(modal).toBeTruthy();
    expect(title).toBeTruthy();
    expect(title.textContent.trim().length).toBeGreaterThan(0);
    expect(formCard).toBeNull();
    expect(form).toBeTruthy();
    expect(actions).toBeTruthy();
  });

  it('closes the modal when the cancel button is clicked', async () => {
    await setup();
    component.open();
    fixture.detectChanges();
    expect(component.isOpen()).toBe(true);

    const cancelButton = fixture.nativeElement.querySelector(
      '.quick-add-actions .btn.secondary',
    ) as HTMLButtonElement;
    expect(cancelButton).toBeTruthy();
    cancelButton.click();

    expect(component.isOpen()).toBe(false);
  });
});
