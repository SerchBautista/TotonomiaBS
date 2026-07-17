import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { of, Subject, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { WorkspacePaymentMethodSummary } from '../../../core/models/payment-method.model';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { WorkspacePaymentMethodsComponent } from './workspace-payment-methods';

const workspaceMethodsResponse: WorkspacePaymentMethodSummary[] = [
  {
    id: 'card-1',
    type: 'card',
    name: 'Visa personal',
    display_name: 'Visa personal',
    masked_details: '****4242',
    is_linked: true,
    is_valid_for_transactions: true,
    state: 'linked',
  },
  {
    id: 'other-1',
    type: 'other',
    name: 'Transferencia',
    display_name: 'Transferencia',
    masked_details: null,
    is_linked: false,
    is_valid_for_transactions: false,
    state: 'not_linked',
  },
  {
    id: 'card-2',
    type: 'card',
    name: 'Mastercard vieja',
    display_name: 'Mastercard vieja',
    masked_details: '****9999',
    is_linked: true,
    is_valid_for_transactions: false,
    state: 'read_only_linked',
  },
];

const activatedRouteMock = {
  snapshot: {
    paramMap: { get: (key: string) => (key === 'id' ? 'ws-1' : null) },
    parent: { paramMap: { get: () => null } },
  },
};

describe('WorkspacePaymentMethodsComponent', () => {
  let fixture: ComponentFixture<WorkspacePaymentMethodsComponent>;
  let component: WorkspacePaymentMethodsComponent;
  let paymentMethodsMock: {
    listWorkspace: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
    updateLink: ReturnType<typeof vi.fn>;
    bulkLinking: ReturnType<typeof vi.fn>;
    listValid: ReturnType<typeof vi.fn>;
    paymentMethodCreated$: Subject<{ workspaceId: string }>;
  };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  async function setup(options?: {
    owner?: boolean;
    listResponses?: WorkspacePaymentMethodSummary[][];
  }): Promise<void> {
    TestBed.resetTestingModule();

    const responses = options?.listResponses ?? [workspaceMethodsResponse];
    const paymentMethodCreated$ = new Subject<{ workspaceId: string }>();

    paymentMethodsMock = {
      listWorkspace: vi.fn().mockReturnValue(of({ data: workspaceMethodsResponse })),
      create: vi.fn().mockReturnValue(of({ data: workspaceMethodsResponse[0] })),
      updateLink: vi.fn().mockReturnValue(of(void 0)),
      bulkLinking: vi.fn().mockReturnValue(
        of({
          operation: 'link_all',
          total: 3,
          processed: 3,
          blocked: 0,
          processed_method_ids: ['card-1', 'other-1', 'card-2'],
          blocked_method_ids: [],
        }),
      ),
      listValid: vi.fn().mockReturnValue(of({ data: [] })),
      paymentMethodCreated$,
    };

    responses.forEach((response) => {
      paymentMethodsMock.listWorkspace.mockReturnValueOnce(of({ data: response }));
    });
    paymentMethodsMock.listWorkspace.mockReturnValue(
      of({ data: responses.at(-1) ?? workspaceMethodsResponse }),
    );

    await TestBed.configureTestingModule({
      imports: [WorkspacePaymentMethodsComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ActivatedRoute, useValue: activatedRouteMock },
        { provide: PaymentMethodsService, useValue: paymentMethodsMock },
        { provide: ToastService, useValue: toastMock },
        {
          provide: WorkspaceContextService,
          useValue: {
            workspaces: () => [],
            selectedWorkspace: () => ({ id: 'ws-1', name: 'Casa', owner_id: 'user-1' }),
            currentWorkspaceId: () => 'ws-1',
            setCurrentWorkspaceId: vi.fn(),
            resolveWorkspaceId: vi.fn().mockResolvedValue('ws-1'),
            ensureLoaded: vi.fn().mockResolvedValue([]),
          },
        },
        {
          provide: AuthStateService,
          useValue: {
            userId: vi.fn().mockReturnValue(options?.owner === false ? 'user-2' : 'user-1'),
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WorkspacePaymentMethodsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  }

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should create the component and load workspace payment methods', async () => {
    await setup();
    expect(component).toBeTruthy();
    expect(paymentMethodsMock.listWorkspace).toHaveBeenCalledWith('ws-1');
  });

  it('should keep cash as a fixed method outside the draggable lists', async () => {
    const responseWithCash: WorkspacePaymentMethodSummary[] = [
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
      ...workspaceMethodsResponse,
    ];
    await setup({ listResponses: [responseWithCash] });

    expect(component.methods().some((m) => m.type === 'cash')).toBe(true);
    expect(component.cashMethod()?.id).toBe('cash');
    expect(component.usingHere().some((m) => m.type === 'cash')).toBe(false);
    expect(component.available().some((m) => m.type === 'cash')).toBe(false);
  });

  it('should map canonical states correctly', async () => {
    await setup();
    expect(component.getMethodState(workspaceMethodsResponse[0])).toBe('linked');
    expect(component.getMethodState(workspaceMethodsResponse[1])).toBe('unlinked');
    expect(component.getMethodState(workspaceMethodsResponse[2])).toBe('linked_read_only');
  });

  it('should filter methods by status and search term', async () => {
    await setup();

    component.setFilter('inactive');
    expect(component.filteredMethods().map((m) => m.id)).toEqual(['card-2']);

    component.setFilter('all');
    component.onSearchChange('trans');
    expect(component.filteredMethods().map((m) => m.id)).toEqual(['other-1']);
  });

  it('should create a card from workspace via PaymentMethodsService.create()', async () => {
    await setup({ listResponses: [workspaceMethodsResponse, workspaceMethodsResponse] });

    component.toggleCreateForm();
    component.createForm.setValue({
      type: 'card',
      name: 'Visa compartida',
      card_type: 'credit',
      brand: 'Visa',
      last_4_digits: '4242',
      description: '',
    });

    component.submitCreate();
    await fixture.whenStable();

    expect(paymentMethodsMock.create).toHaveBeenCalledWith('ws-1', {
      type: 'card',
      name: 'Visa compartida',
      card_type: 'credit',
      brand: 'Visa',
      last_4_digits: '4242',
    });
  });

  it('should create an other payment method from workspace', async () => {
    await setup({ listResponses: [workspaceMethodsResponse, workspaceMethodsResponse] });

    component.toggleCreateForm();
    component.createForm.controls.type.setValue('other');
    component.createForm.setValue({
      type: 'other',
      name: 'Transferencia nueva',
      card_type: 'credit',
      brand: '',
      last_4_digits: '',
      description: 'Banco principal',
    });

    component.submitCreate();
    await fixture.whenStable();

    expect(paymentMethodsMock.create).toHaveBeenCalledWith('ws-1', {
      type: 'other',
      name: 'Transferencia nueva',
      description: 'Banco principal',
    });
  });

  it('should toggle link and refresh the workspace methods', async () => {
    await setup({
      listResponses: [
        workspaceMethodsResponse,
        [
          workspaceMethodsResponse[0],
          {
            ...workspaceMethodsResponse[1],
            is_linked: true,
            is_valid_for_transactions: true,
            state: 'linked',
          },
          workspaceMethodsResponse[2],
        ],
      ],
    });

    component.toggleLink(workspaceMethodsResponse[1]);

    expect(paymentMethodsMock.updateLink).toHaveBeenCalledWith('ws-1', 'other-1', true);
    expect(component.methods().find((m) => m.id === 'other-1')?.is_linked).toBe(true);
  });

  it('should run bulk link and refresh the workspace methods', async () => {
    await setup({ listResponses: [workspaceMethodsResponse, workspaceMethodsResponse] });

    component.bulkLink(true);

    expect(paymentMethodsMock.bulkLinking).toHaveBeenCalledWith('ws-1', true);
    expect(paymentMethodsMock.listWorkspace).toHaveBeenCalledTimes(2);
  });

  it('should disable owner-only actions for non-owner users', async () => {
    await setup({ owner: false });

    component.toggleCreateForm();
    expect(component.showCreateForm()).toBe(false);

    component.toggleLink(workspaceMethodsResponse[1]);
    component.bulkLink(true);
    expect(paymentMethodsMock.updateLink).not.toHaveBeenCalled();
    expect(paymentMethodsMock.bulkLinking).not.toHaveBeenCalled();
  });

  it('should not show local load error toast when load returns 403', async () => {
    paymentMethodsMock = {
      listWorkspace: vi.fn().mockReturnValue(throwError(() => ({ status: 403 }))),
      create: vi.fn(),
      updateLink: vi.fn(),
      bulkLinking: vi.fn(),
      listValid: vi.fn(),
      paymentMethodCreated$: new Subject(),
    } as any;

    await TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      imports: [WorkspacePaymentMethodsComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ActivatedRoute, useValue: activatedRouteMock },
        { provide: PaymentMethodsService, useValue: paymentMethodsMock },
        { provide: ToastService, useValue: toastMock },
        {
          provide: WorkspaceContextService,
          useValue: {
            workspaces: () => [],
            selectedWorkspace: () => ({ id: 'ws-1', name: 'Casa', owner_id: 'user-1' }),
            currentWorkspaceId: () => 'ws-1',
            setCurrentWorkspaceId: vi.fn(),
            resolveWorkspaceId: vi.fn().mockResolvedValue('ws-1'),
            ensureLoaded: vi.fn().mockResolvedValue([]),
          },
        },
        { provide: AuthStateService, useValue: { userId: vi.fn().mockReturnValue('user-1') } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WorkspacePaymentMethodsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();

    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should not show local load error toast on 429', async () => {
    paymentMethodsMock = {
      listWorkspace: vi.fn().mockReturnValue(throwError(() => ({ status: 429 }))),
      create: vi.fn(),
      updateLink: vi.fn(),
      bulkLinking: vi.fn(),
      listValid: vi.fn(),
      paymentMethodCreated$: new Subject(),
    } as any;

    await TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      imports: [WorkspacePaymentMethodsComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ActivatedRoute, useValue: activatedRouteMock },
        { provide: PaymentMethodsService, useValue: paymentMethodsMock },
        { provide: ToastService, useValue: toastMock },
        {
          provide: WorkspaceContextService,
          useValue: {
            workspaces: () => [],
            selectedWorkspace: () => ({ id: 'ws-1', name: 'Casa', owner_id: 'user-1' }),
            currentWorkspaceId: () => 'ws-1',
            setCurrentWorkspaceId: vi.fn(),
            resolveWorkspaceId: vi.fn().mockResolvedValue('ws-1'),
            ensureLoaded: vi.fn().mockResolvedValue([]),
          },
        },
        { provide: AuthStateService, useValue: { userId: vi.fn().mockReturnValue('user-1') } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WorkspacePaymentMethodsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();

    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should ignore category creation events from other workspaces', async () => {
    await setup();

    const initialCalls = paymentMethodsMock.listWorkspace.mock.calls.length;
    (paymentMethodsMock.paymentMethodCreated$ as Subject<{ workspaceId: string }>).next({
      workspaceId: 'ws-2',
    });

    expect(paymentMethodsMock.listWorkspace.mock.calls.length).toBe(initialCalls);
  });

  it('should refresh workspace methods when a method is created in the same workspace', async () => {
    await setup();

    const initialCalls = paymentMethodsMock.listWorkspace.mock.calls.length;
    (paymentMethodsMock.paymentMethodCreated$ as Subject<{ workspaceId: string }>).next({
      workspaceId: 'ws-1',
    });

    expect(paymentMethodsMock.listWorkspace.mock.calls.length).toBe(initialCalls + 1);
  });
});
