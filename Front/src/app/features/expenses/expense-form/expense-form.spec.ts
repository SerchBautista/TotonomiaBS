import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter, Router } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { describe, expect, it, beforeEach } from 'vitest';
import { vi } from 'vitest';
import { BudgetAdjustmentsService } from '../../../core/services/budget-adjustments.service';
import { BudgetsService } from '../../../core/services/budgets.service';
import { CardsService } from '../../../core/services/cards.service';
import { CategoriesService } from '../../../core/services/categories';
import { ExpensesService } from '../../../core/services/expenses';
import { OtherPaymentMethodsService } from '../../../core/services/other-payment-methods.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { ToastService } from '../../../core/services/toast.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { ExpenseFormComponent } from './expense-form';

describe('ExpenseFormComponent', () => {
  let fixture: ComponentFixture<ExpenseFormComponent>;
  let component: ExpenseFormComponent;
  let categoriesServiceMock: { listValid: ReturnType<typeof vi.fn>; createMine: ReturnType<typeof vi.fn> };
  let paymentMethodsServiceMock: {
    listValid: ReturnType<typeof vi.fn>;
    paymentMethodCreated$: ReturnType<typeof of>;
    notifyCreated: ReturnType<typeof vi.fn>;
  };
  let cardsServiceMock: { list: ReturnType<typeof vi.fn>; create: ReturnType<typeof vi.fn> };
  let otherPaymentMethodsServiceMock: { list: ReturnType<typeof vi.fn>; create: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    categoriesServiceMock = {
      listValid: vi.fn((workspaceId: string) => {
        if (workspaceId === 'ws-2') {
          return of({
            data: [
              { id: 'cat-2', user_id: 'user-1', name: 'Transport', icon: 'car', color: '#00ff00', is_default: true },
            ],
          });
        }

        return of({
          data: [
            { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000', is_default: true },
          ],
        });
      }),
      createMine: vi.fn().mockReturnValue(of({
        data: { id: 'cat-new', user_id: 'user-1', name: 'Nueva', icon: 'tag', color: '#16324f' },
      })),
    };

    paymentMethodsServiceMock = {
      listValid: vi.fn().mockReturnValue(of({
        data: [
          { id: 'cash', type: 'cash', name: 'Efectivo', display_name: 'Efectivo', masked_details: null, is_linked: true, is_valid_for_transactions: true, state: 'linked' },
          { id: 'pm-card-1', type: 'card', name: 'Visa', display_name: 'Visa •••• 1234', masked_details: '1234', is_linked: true, is_valid_for_transactions: true, state: 'linked' },
          { id: 'pm-other-1', type: 'other', name: 'Transfer', display_name: 'Transfer', masked_details: null, is_linked: true, is_valid_for_transactions: true, state: 'linked' },
        ],
      })),
      paymentMethodCreated$: of(),
      notifyCreated: vi.fn(),
    };

    cardsServiceMock = {
      list: vi.fn().mockReturnValue(of({ data: [] })),
      create: vi.fn(),
    };

    otherPaymentMethodsServiceMock = {
      list: vi.fn().mockReturnValue(of({ data: [] })),
      create: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [ExpenseFormComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              data: { mode: 'create' },
              paramMap: { get: vi.fn().mockReturnValue(null) },
              parent: {
                paramMap: { get: vi.fn().mockReturnValue(null) },
              },
            },
            queryParamMap: of(new Map() as never),
          },
        },
        {
          provide: WorkspaceContextService,
          useValue: (() => {
            const wsId = signal<string | null>('ws-1');
            return {
              currentWorkspaceId: wsId.asReadonly(),
              selectedWorkspace: signal({ id: 'ws-1', owner_id: 'user-1', name: 'Workspace', type: 'personal', currency_code: 'USD', created_at: '', updated_at: '' }).asReadonly(),
              workspaces: signal([{ id: 'ws-1', owner_id: 'user-1', name: 'Workspace', type: 'personal', currency_code: 'USD', created_at: '', updated_at: '' }]).asReadonly(),
              setCurrentWorkspaceId: (id: string | null) => wsId.set(id),
              resolveWorkspaceId: vi.fn().mockResolvedValue('ws-1'),
            };
          })(),
        },
        {
          provide: ExpensesService,
          useValue: {
            create: vi.fn(),
            update: vi.fn(),
            getById: vi.fn(),
          },
        },
        { provide: CategoriesService, useValue: categoriesServiceMock },
        {
          provide: CardsService,
          useValue: cardsServiceMock,
        },
        {
          provide: OtherPaymentMethodsService,
          useValue: otherPaymentMethodsServiceMock,
        },
        { provide: PaymentMethodsService, useValue: paymentMethodsServiceMock },
        {
          provide: BudgetsService,
          useValue: {
            status: vi.fn().mockReturnValue(of({ data: { month: '2026-05', general: null, categories: [] } })),
          },
        },
        {
          provide: WorkspaceMembersService,
          useValue: {
            list: vi.fn().mockReturnValue(of({ data: [] })),
          },
        },
        {
          provide: WorkspacesService,
          useValue: {
            getById: vi.fn().mockReturnValue(
              of({
                data: {
                  id: 'ws-1',
                  owner_id: 'user-1',
                  name: 'Workspace',
                  type: 'personal',
                  currency_code: 'USD',
                  current_user_permissions: { can_add_categories: true, can_add_fixed_expenses: true },
                  created_at: '',
                  updated_at: '',
                },
              })
            ),
          },
        },
        {
          provide: ToastService,
          useValue: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
        },
        {
          provide: AuthStateService,
          useValue: { userId: vi.fn().mockReturnValue('user-1') },
        },
        {
          provide: AUTH_STATE_TOKEN,
          useValue: { role: () => 'user' },
        },
        {
          provide: UserPreferencesService,
          useValue: {
            timezone: vi.fn().mockReturnValue('UTC'),
          },
        },
        {
          provide: BudgetAdjustmentsService,
          useValue: {
            create: vi.fn(),
            available: vi.fn().mockReturnValue(of({ data: [] })),
          },
        },
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
      },
      true,
    );
    translate.use('es');

    fixture = TestBed.createComponent(ExpenseFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads categories using listValid for transactional flow', () => {
    expect(categoriesServiceMock.listValid).toHaveBeenCalledWith('ws-1');
  });

  it('loads only valid workspace payment methods for transactional flow', () => {
    expect(paymentMethodsServiceMock.listValid).toHaveBeenCalledWith('ws-1');
    expect(component.cashMethod()?.type).toBe('cash');
    expect(component.cards()).toHaveLength(1);
    expect(component.otherMethods()).toHaveLength(1);
    expect(component.form.value.payment_value).toBe('cash');
  });

  it('reassigns category when selected value becomes invalid after workspace change', () => {
    component.form.patchValue({ category_id: 'cat-1' });
    component.workspaceId = 'ws-2';

    (component as any).loadOptions();

    expect(component.form.value.category_id).toBe('cat-2');
  });

  it('preserves category selection when still valid after workspace change', () => {
    categoriesServiceMock.listValid.mockReturnValue(
      of({
        data: [
          { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000', is_default: false },
          { id: 'cat-2', user_id: 'user-1', name: 'Transport', icon: 'car', color: '#00ff00', is_default: true },
        ],
      })
    );

    component.form.patchValue({ category_id: 'cat-1' });
    component.workspaceId = 'ws-2';

    (component as any).loadOptions();

    expect(component.form.value.category_id).toBe('cat-1');
  });

  it('notifies payment method creation when a new card is created inline', () => {
    const card = {
      id: 'card-1',
      workspace_id: 'ws-1',
      name: 'Visa',
      card_type: 'credit' as const,
      brand: 'Visa',
      last_4_digits: '1234',
    };

    component.onPaymentInstrumentCreated({
      paymentValue: 'card:card-1',
      instrument: card,
    });

    expect(paymentMethodsServiceMock.notifyCreated).toHaveBeenCalledWith('ws-1', card);
  });

  it('notifies payment method creation when a new other method is created inline', () => {
    const method = {
      id: 'other-1',
      workspace_id: 'ws-1',
      name: 'Transfer',
      description: 'Banco principal',
    };

    component.onPaymentInstrumentCreated({
      paymentValue: 'other:other-1',
      instrument: method,
    });

    expect(paymentMethodsServiceMock.notifyCreated).toHaveBeenCalledWith('ws-1', method);
  });

  it('updates URL queryParam workspaceId when current workspace changes', () => {
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate');

    const workspaceContext = TestBed.inject(WorkspaceContextService) as {
      setCurrentWorkspaceId: (id: string | null) => void;
    };
    workspaceContext.setCurrentWorkspaceId('ws-2');
    fixture.detectChanges();
    fixture.detectChanges();

    expect(navigateSpy).toHaveBeenCalledWith(
      [],
      expect.objectContaining({
        queryParams: { workspaceId: 'ws-2' },
        queryParamsHandling: 'merge',
      })
    );
  });

  it('renders the page header and form card containers', () => {
    const header = fixture.nativeElement.querySelector('app-page-header .page-header__title');
    expect(header).toBeTruthy();
    expect(header.textContent.trim().length).toBeGreaterThan(0);

    const formCard = fixture.nativeElement.querySelector('app-form-card .form-card');
    expect(formCard).toBeTruthy();

    const form = fixture.nativeElement.querySelector('app-form-card form');
    expect(form).toBeTruthy();
  });

  it('exposes a reactive formTitle for the current mode', () => {
    expect(typeof component.formTitle()).toBe('string');
    expect(component.formTitle().length).toBeGreaterThan(0);
  });

  it('emits the new general warning copy when the create response includes a general budget warning', () => {
    const expensesService = TestBed.inject(ExpensesService) as unknown as { create: ReturnType<typeof vi.fn> };
    const toast = TestBed.inject(ToastService) as unknown as { warning: ReturnType<typeof vi.fn> };
    expensesService.create.mockReturnValueOnce(
      of({
        data: {
          id: 'exp-warn-general',
          amount: '75',
          budget_warnings: [
            { scope: 'general', budget_id: 'b-g', percentage: 95, alert_threshold: 80 },
          ],
        },
      } as any),
    );

    component.form.patchValue({ amount: '75', category_id: 'cat-1' });
    (component as any).submit();

    expect(toast.warning).toHaveBeenCalledWith(
      'Aviso: el presupuesto general alcanzó el monto de alerta.',
    );
  });

  it('emits the new category warning copy with the category name when a category budget warning is returned', () => {
    const expensesService = TestBed.inject(ExpensesService) as unknown as { create: ReturnType<typeof vi.fn> };
    const toast = TestBed.inject(ToastService) as unknown as { warning: ReturnType<typeof vi.fn> };
    expensesService.create.mockReturnValueOnce(
      of({
        data: {
          id: 'exp-warn-category',
          amount: '40',
          budget_warnings: [
            {
              scope: 'category',
              budget_id: 'b-c',
              category_id: 'cat-1',
              category_name: 'Food',
              percentage: 92,
              alert_threshold: 80,
            },
          ],
        },
      } as any),
    );

    component.form.patchValue({ amount: '40', category_id: 'cat-1' });
    (component as any).submit();

    expect(toast.warning).toHaveBeenCalledWith(
      'Aviso: el presupuesto de "Food" alcanzó el monto de alerta.',
    );
  });
});
