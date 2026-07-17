import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { signal } from '@angular/core';
import { of } from 'rxjs';
import { PendingPaymentListComponent } from './pending-payment-list';
import { OccurrencesService } from '../../../core/services/occurrences';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { FixedExpenseOccurrence } from '../../../core/models/fixed-expense.model';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { PaymentMethodsService } from '../../../core/services/payment-methods';

const mockPendingOccurrence: FixedExpenseOccurrence = {
  id: 'occ-1',
  due_date: '2026-03-25',
  suggested_amount: '200.00',
  status: 'pending',
  fixed_expense: {
    id: 'fe-1',
    description: 'Netflix',
    frequency: 'monthly',
    payment_type: 'card',
    payment_instrument: null,
    category: null,
  },
};

const mockOverdueOccurrence: FixedExpenseOccurrence = {
  ...mockPendingOccurrence,
  id: 'occ-2',
  due_date: '2026-03-10',
  status: 'overdue',
};

describe('PendingPaymentListComponent', () => {
  let fixture: ComponentFixture<PendingPaymentListComponent>;
  let component: PendingPaymentListComponent;
  let occurrencesServiceMock: {
    list: ReturnType<typeof vi.fn>;
    pay: ReturnType<typeof vi.fn>;
  };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
  const membersServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: [] })),
  };
  const workspacesServiceMock = {
    getById: vi.fn().mockReturnValue(
      of({
        data: {
          id: 'ws-1',
          owner_id: 'user-1',
          name: 'Test WS',
          type: 'personal',
          currency_code: 'USD',
          created_at: '2026-01-01',
          updated_at: '2026-01-01',
        },
      }),
    ),
  };
  const paymentMethodsServiceMock = {
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
  };

  beforeEach(async () => {
    occurrencesServiceMock = {
      list: vi.fn().mockReturnValue(of({ data: [mockPendingOccurrence, mockOverdueOccurrence] })),
      pay: vi.fn().mockReturnValue(of({ data: {} })),
    };

    await TestBed.configureTestingModule({
      imports: [PendingPaymentListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: OccurrencesService, useValue: occurrencesServiceMock },
        {
          provide: WorkspaceContextService,
          useValue: (() => {
            const wsId = signal<string | null>('ws-1');
            return {
              workspaces: () => [{ id: 'ws-1', name: 'Test WS' }],
              selectedWorkspace: () => ({ id: 'ws-1', name: 'Test WS', currency_code: 'USD' }),
              currentWorkspaceId: wsId.asReadonly(),
              setCurrentWorkspaceId: (id: string | null) => wsId.set(id),
              resolveWorkspaceId: vi.fn().mockResolvedValue('ws-1'),
            };
          })(),
        },
        { provide: ToastService, useValue: toastMock },
        { provide: WorkspaceMembersService, useValue: membersServiceMock },
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        { provide: PaymentMethodsService, useValue: paymentMethodsServiceMock },
        { provide: AUTH_STATE_TOKEN, useValue: { role: () => 'user' } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: { parent: null },
            queryParamMap: of({ get: () => null }),
          },
        },
      ],
    }).compileComponents();

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        common: {
          loading: 'Cargando...',
        },
        pending_payments: {
          title: 'Por pagar',
          empty: 'No tienes pagos pendientes. ¡Todo al día!',
          pending: 'Pendiente',
          overdue: 'Vencido',
          pay: 'Registrar pago',
          register_payment: 'Registrar pago',
          due: 'Vence',
          amount: 'Importe pagado',
          status: 'Estado',
          actions: 'Acciones',
        },
        fixed_expenses: {
          description: 'Descripción',
        },
        expenses: {
          category: 'Categoría',
          amount: 'Importe',
          actions: 'Acciones',
        },
      },
      true,
    );
    translate.use('es');

    fixture = TestBed.createComponent(PendingPaymentListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
    expect(component.occurrences()).toHaveLength(2);
  });

  it('should show overdue badge for overdue occurrences', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const badges = compiled.querySelectorAll('.badge.badge-danger');
    expect(badges.length).toBeGreaterThan(0);
  });

  it('should show pending badge for pending occurrences', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const badges = compiled.querySelectorAll('.badge.badge-brand');
    expect(badges.length).toBeGreaterThan(0);
  });

  it('loads valid workspace payment methods for pay flow', () => {
    expect(paymentMethodsServiceMock.listValid).toHaveBeenCalledWith('ws-1');
    expect(component.validPaymentMethods()).toHaveLength(2);
    expect(component.validPaymentMethods()[0]?.type).toBe('cash');
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
      }),
    );
  });

  it('shows the translated loading message while loading', () => {
    component.loading.set(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const loadingState = compiled.querySelector('app-loading-state');
    expect(loadingState).toBeTruthy();
    expect(loadingState?.textContent).toContain('Cargando...');
  });

  it('does not render the data table while loading to avoid duplicate loaders', () => {
    component.loading.set(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('app-data-table')).toBeNull();
    expect(compiled.querySelectorAll('.loading-spinner').length).toBe(1);
  });
});
