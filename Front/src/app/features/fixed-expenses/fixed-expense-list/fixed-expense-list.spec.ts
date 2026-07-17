import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { signal } from '@angular/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { FixedExpenseListComponent } from './fixed-expense-list';
import { FixedExpensesService } from '../../../core/services/fixed-expenses';
import { CategoriesService } from '../../../core/services/categories';
import { CardsService } from '../../../core/services/cards.service';
import { OtherPaymentMethodsService } from '../../../core/services/other-payment-methods.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { ToastService } from '../../../core/services/toast.service';
import { FixedExpenseCreateModalComponent } from '../fixed-expense-create-modal/fixed-expense-create-modal';

describe('FixedExpenseListComponent', () => {
  let fixture: ComponentFixture<FixedExpenseListComponent>;
  let component: FixedExpenseListComponent;
  let fixedExpensesServiceMock: { list: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    fixedExpensesServiceMock = {
      list: vi.fn().mockReturnValue(
        of({
          data: [
            {
              id: 'fixed-1',
              workspace_id: 'ws-1',
              user_id: 'user-1',
              category_id: 'cat-1',
              payment_type: 'cash',
              payment_instrument_id: null,
              amount: '150.25',
              description: 'Internet',
              frequency: 'monthly',
              next_due_date: '2026-05-30',
              alert_date: null,
              is_active: true,
              reminders_enabled: false,
              type: 'recurring',
              total_installments: null,
              remaining_installments: null,
              has_paid_occurrences: false,
            },
            {
              id: 'fixed-2',
              workspace_id: 'ws-1',
              user_id: 'user-1',
              category_id: 'cat-2',
              payment_type: 'cash',
              payment_instrument_id: null,
              amount: '49.75',
              description: 'Streaming',
              frequency: 'monthly',
              next_due_date: '2026-05-15',
              alert_date: null,
              is_active: true,
              reminders_enabled: false,
              type: 'recurring',
              total_installments: null,
              remaining_installments: null,
              has_paid_occurrences: false,
            },
          ],
        }),
      ),
    };

    await TestBed.configureTestingModule({
      imports: [FixedExpenseListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: FixedExpensesService,
          useValue: {
            ...fixedExpensesServiceMock,
            create: vi.fn(),
            update: vi.fn(),
            delete: vi.fn(),
          },
        },
        {
          provide: CategoriesService,
          useValue: { listValid: vi.fn().mockReturnValue(of({ data: [] })) },
        },
        {
          provide: CardsService,
          useValue: { list: vi.fn().mockReturnValue(of({ data: [] })), create: vi.fn() },
        },
        {
          provide: OtherPaymentMethodsService,
          useValue: { list: vi.fn().mockReturnValue(of({ data: [] })), create: vi.fn() },
        },
        {
          provide: PaymentMethodsService,
          useValue: {
            listValid: vi.fn().mockReturnValue(
              of({
                data: [
                  {
                    id: 'cash',
                    type: 'cash',
                    name: 'Efectivo',
                    display_name: 'Efectivo',
                    masked_details: null,
                    is_linked: true,
                    is_valid_for_transactions: true,
                    state: 'linked',
                  },
                  {
                    id: 'pm-card-1',
                    type: 'card',
                    name: 'Visa',
                    display_name: 'Visa •••• 1234',
                    masked_details: '1234',
                    is_linked: true,
                    is_valid_for_transactions: true,
                    state: 'linked',
                  },
                ],
              }),
            ),
            paymentMethodCreated$: of(),
            notifyCreated: vi.fn(),
          },
        },
        {
          provide: WorkspaceContextService,
          useValue: (() => {
            const wsId = signal<string | null>('ws-1');
            return {
              workspaces: () => [],
              selectedWorkspace: () => ({ id: 'ws-1', name: 'Workspace', currency_code: 'USD' }),
              currentWorkspaceId: wsId.asReadonly(),
              setCurrentWorkspaceId: (id: string | null) => wsId.set(id),
              resolveWorkspaceId: vi.fn().mockResolvedValue('ws-1'),
            };
          })(),
        },
        { provide: ToastService, useValue: { success: vi.fn(), error: vi.fn(), info: vi.fn() } },
        { provide: AUTH_STATE_TOKEN, useValue: { role: () => 'user' } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              parent: {
                paramMap: { get: vi.fn().mockReturnValue('ws-1') },
              },
            },
            queryParamMap: of({ get: () => null }),
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(FixedExpenseListComponent);
    component = fixture.componentInstance;
  });

  it('should calculate monthly total from loaded fixed expenses', () => {
    fixture.detectChanges();

    expect(component.monthlyTotalAmount()).toBe(200);
  });

  it('should ignore invalid amounts when calculating monthly total', () => {
    component.fixedExpenses.set([
      {
        id: 'fixed-1',
        workspace_id: 'ws-1',
        user_id: 'user-1',
        category_id: 'cat-1',
        payment_type: 'cash',
        payment_instrument_id: null,
        amount: '100',
        description: 'Internet',
        frequency: 'monthly',
        next_due_date: '2026-05-30',
        alert_date: null,
        is_active: true,
        reminders_enabled: false,
        type: 'recurring',
        total_installments: null,
        remaining_installments: null,
        has_paid_occurrences: false,
      },
      {
        id: 'fixed-2',
        workspace_id: 'ws-1',
        user_id: 'user-1',
        category_id: 'cat-2',
        payment_type: 'cash',
        payment_instrument_id: null,
        amount: 'not-a-number',
        description: 'Invalid',
        frequency: 'monthly',
        next_due_date: '2026-05-30',
        alert_date: null,
        is_active: true,
        reminders_enabled: false,
        type: 'recurring',
        total_installments: null,
        remaining_installments: null,
        has_paid_occurrences: false,
      },
    ]);

    expect(component.monthlyTotalAmount()).toBe(100);
  });

  it('loads valid workspace payment methods for create modal', () => {
    fixture.detectChanges();

    component.toggleForm();
    fixture.detectChanges();

    const createModal = fixture.debugElement.query(
      (el) => el.componentInstance instanceof FixedExpenseCreateModalComponent,
    )?.componentInstance as FixedExpenseCreateModalComponent;

    expect(component.validPaymentMethods().some((m) => m.type === 'cash')).toBe(true);
    expect(createModal.cashMethod()?.type).toBe('cash');
    expect(createModal.cards()).toHaveLength(1);
    expect(createModal.form.value.payment_value).toBe('cash');
  });

  it('shows create modal shell when showForm is true', () => {
    fixture.detectChanges();

    component.toggleForm();
    fixture.detectChanges();

    const shells = fixture.nativeElement.querySelectorAll('app-modal-shell');
    expect(shells.length).toBeGreaterThanOrEqual(1);
    expect(fixture.nativeElement.querySelector('.modal-panel')).toBeTruthy();
    expect(fixture.nativeElement.innerHTML).not.toMatch(
      /<div[^>]*class="modal-backdrop"[^>]*>\s*<div[^>]*class="modal-panel"/,
    );

    const cancelButtons = fixture.nativeElement.querySelectorAll(
      'app-modal-shell .modal-panel__footer .btn.secondary',
    ) as NodeListOf<HTMLButtonElement>;
    expect(cancelButtons.length).toBeGreaterThan(0);
    expect(cancelButtons[0].classList.contains('ghost')).toBe(false);
  });

  it('shows edit modal shell with edit title when editing', () => {
    fixture.detectChanges();

    const expense = component.fixedExpenses()[0];
    component.startEdit(expense);
    fixture.detectChanges();

    const editShells = fixture.nativeElement.querySelectorAll(
      'app-modal-shell',
    ) as NodeListOf<HTMLElement>;
    const editShell = Array.from(editShells).find((shell) =>
      shell.textContent?.includes('fixed_expenses.edit'),
    );
    expect(editShell).toBeTruthy();
    expect(fixture.nativeElement.querySelector('#fe-edit-description')).toBeTruthy();
  });

  it('updates URL queryParam workspaceId when current workspace changes', async () => {
    TestBed.resetTestingModule();
    const wsId = signal<string | null>('ws-1');
    await TestBed.configureTestingModule({
      imports: [FixedExpenseListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: FixedExpensesService,
          useValue: {
            list: vi.fn().mockReturnValue(of({ data: [] })),
            create: vi.fn(),
            update: vi.fn(),
            delete: vi.fn(),
          },
        },
        {
          provide: CategoriesService,
          useValue: { listValid: vi.fn().mockReturnValue(of({ data: [] })) },
        },
        {
          provide: CardsService,
          useValue: { list: vi.fn().mockReturnValue(of({ data: [] })), create: vi.fn() },
        },
        {
          provide: OtherPaymentMethodsService,
          useValue: { list: vi.fn().mockReturnValue(of({ data: [] })), create: vi.fn() },
        },
        {
          provide: PaymentMethodsService,
          useValue: {
            listValid: vi.fn().mockReturnValue(
              of({
                data: [
                  {
                    id: 'cash',
                    type: 'cash',
                    name: 'Efectivo',
                    display_name: 'Efectivo',
                    masked_details: null,
                    is_linked: true,
                    is_valid_for_transactions: true,
                    state: 'linked',
                  },
                  {
                    id: 'pm-card-1',
                    type: 'card',
                    name: 'Visa',
                    display_name: 'Visa •••• 1234',
                    masked_details: '1234',
                    is_linked: true,
                    is_valid_for_transactions: true,
                    state: 'linked',
                  },
                ],
              }),
            ),
            paymentMethodCreated$: of(),
            notifyCreated: vi.fn(),
          },
        },
        {
          provide: WorkspaceContextService,
          useValue: {
            workspaces: () => [],
            selectedWorkspace: () => ({ id: 'ws-1', name: 'Workspace', currency_code: 'USD' }),
            currentWorkspaceId: wsId.asReadonly(),
            setCurrentWorkspaceId: (id: string | null) => wsId.set(id),
            resolveWorkspaceId: vi.fn().mockResolvedValue('ws-1'),
          },
        },
        { provide: ToastService, useValue: { success: vi.fn(), error: vi.fn(), info: vi.fn() } },
        { provide: AUTH_STATE_TOKEN, useValue: { role: () => 'user' } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              parent: {
                paramMap: { get: vi.fn().mockReturnValue(null) },
              },
            },
            queryParamMap: of({ get: () => null }),
          },
        },
      ],
    }).compileComponents();

    const localFixture = TestBed.createComponent(FixedExpenseListComponent);
    localFixture.detectChanges();

    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate');

    const workspaceContext = TestBed.inject(WorkspaceContextService) as {
      setCurrentWorkspaceId: (id: string | null) => void;
    };
    workspaceContext.setCurrentWorkspaceId('ws-2');
    localFixture.detectChanges();
    localFixture.detectChanges();

    expect(navigateSpy).toHaveBeenCalledWith(
      [],
      expect.objectContaining({
        queryParams: { workspaceId: 'ws-2' },
        queryParamsHandling: 'merge',
      }),
    );
  });
});
