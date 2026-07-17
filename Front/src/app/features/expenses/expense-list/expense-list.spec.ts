import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { signal } from '@angular/core';
import { of } from 'rxjs';
import { ExpenseListComponent } from './expense-list';
import { ExpensesService } from '../../../core/services/expenses';
import { CategoriesService } from '../../../core/services/categories';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { ToastService } from '../../../core/services/toast.service';

const mockExpenseListResponse = {
  data: [
    {
      id: 'exp-1',
      workspace_id: 'ws-1',
      user_id: 'user-uuid-1',
      category_id: 'cat-1',
      payment_method_id: 'pm-1',
      fixed_expense_id: null,
      amount: '100.00',
      date: '2024-01-15',
      description: 'Lunch',
      payment_type: 'cash' as const,
      payment_instrument_id: null,
      paid_by_user_id: null,
      category: { id: 'cat-1', workspace_id: 'ws-1', name: 'Food', icon: 'tag', color: '#ff0000' },
      created_at: '2024-01-15',
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
};

const mockCategoryListResponse = {
  data: [
    { id: 'cat-1', workspace_id: 'ws-1', name: 'Food', icon: 'tag', color: '#ff0000' },
  ],
};

describe('ExpenseListComponent', () => {
  let fixture: ComponentFixture<ExpenseListComponent>;
  let component: ExpenseListComponent;
  let expensesServiceMock: {
    list: ReturnType<typeof vi.fn>;
    total: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };
  let categoriesServiceMock: { listValid: ReturnType<typeof vi.fn> };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  beforeEach(async () => {
    expensesServiceMock = {
      list: vi.fn().mockReturnValue(of(mockExpenseListResponse)),
      total: vi.fn().mockReturnValue(of({ data: { total: '100.00' } })),
      delete: vi.fn().mockReturnValue(of({ message: 'deleted' })),
    };
    categoriesServiceMock = {
      listValid: vi.fn().mockReturnValue(of(mockCategoryListResponse)),
    };

    await TestBed.configureTestingModule({
      imports: [ExpenseListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ExpensesService, useValue: expensesServiceMock },
        { provide: CategoriesService, useValue: categoriesServiceMock },
        {
          provide: WorkspaceContextService,
          useValue: (() => {
            const wsId = signal<string | null>(null);
            return {
              workspaces: () => [],
              selectedWorkspace: () => ({ id: 'ws-1', name: 'Workspace', currency_code: 'USD' }),
              currentWorkspaceId: wsId.asReadonly(),
              setCurrentWorkspaceId: (id: string | null) => wsId.set(id),
              resolveWorkspaceId: vi.fn(),
            };
          })(),
        },
        { provide: ToastService, useValue: toastMock },
        { provide: AUTH_STATE_TOKEN, useValue: { role: () => 'user' } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: vi.fn().mockReturnValue(null) },
              parent: {
                paramMap: { get: vi.fn().mockReturnValue('ws-1') },
              },
              data: {},
            },
            queryParamMap: of({ get: () => null }),
          },
        },
        {
          provide: UserPreferencesService,
          useValue: {
            theme: vi.fn().mockReturnValue('dark'),
            locale: vi.fn().mockReturnValue('es'),
            timezone: vi.fn().mockReturnValue('UTC'),
            applyTheme: vi.fn(),
            applyLocale: vi.fn(),
            applyTimezone: vi.fn(),
            applyAll: vi.fn(),
            loadFromBackend: vi.fn(),
            saveToBackend: vi.fn().mockReturnValue(of({ message: '', data: { user: { theme: 'dark', locale: 'es', timezone: 'UTC' } } })),
            getAvailableTimezones: vi.fn().mockReturnValue(['UTC', 'America/Mexico_City']),
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ExpenseListComponent);
    component = fixture.componentInstance;
  });

  it('should create the component', () => {
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('should load expenses and categories on init', () => {
    fixture.detectChanges();
    expect(expensesServiceMock.list).toHaveBeenCalled();
    expect(categoriesServiceMock.listValid).toHaveBeenCalled();
    expect(component.expenses()).toHaveLength(1);
    expect(component.categories()).toHaveLength(1);
  });

  it('should set default date filters to current month', () => {
    fixture.detectChanges();
    const filterFrom = component.filterFrom();
    const filterTo = component.filterTo();
    const today = component.today();

    expect(filterTo).toBe(today);
    expect(filterFrom).toMatch(/^\d{4}-\d{2}-01$/);
    expect(filterFrom <= filterTo).toBe(true);
    expect(filterTo <= today).toBe(true);
  });

  it('should call expensesService.delete() on confirmDelete', () => {
    fixture.detectChanges();
    component.requestDelete('exp-1');
    component.confirmDelete();
    expect(expensesServiceMock.delete).toHaveBeenCalledWith('ws-1', 'exp-1');
  });

  it('should clear category filter when selected category is not valid anymore', () => {
    categoriesServiceMock.listValid.mockReturnValueOnce(of(mockCategoryListResponse));
    fixture.detectChanges();

    component.filterCategory.set('cat-legacy');
    component.loadCategories();

    expect(component.filterCategory()).toBe('');
  });

  it('updates URL queryParam workspaceId when current workspace changes', async () => {
    TestBed.resetTestingModule();
    const wsId = signal<string | null>('ws-1');
    await TestBed.configureTestingModule({
      imports: [ExpenseListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ExpensesService, useValue: expensesServiceMock },
        { provide: CategoriesService, useValue: categoriesServiceMock },
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
        { provide: ToastService, useValue: toastMock },
        { provide: AUTH_STATE_TOKEN, useValue: { role: () => 'user' } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: vi.fn().mockReturnValue(null) },
              parent: {
                paramMap: { get: vi.fn().mockReturnValue(null) },
              },
              data: {},
            },
            queryParamMap: of({ get: () => null }),
          },
        },
        {
          provide: UserPreferencesService,
          useValue: {
            theme: vi.fn().mockReturnValue('dark'),
            locale: vi.fn().mockReturnValue('es'),
            timezone: vi.fn().mockReturnValue('UTC'),
            applyTheme: vi.fn(),
            applyLocale: vi.fn(),
            applyTimezone: vi.fn(),
            applyAll: vi.fn(),
            loadFromBackend: vi.fn(),
            saveToBackend: vi.fn().mockReturnValue(of({ message: '', data: { user: { theme: 'dark', locale: 'es', timezone: 'UTC' } } })),
            getAvailableTimezones: vi.fn().mockReturnValue(['UTC', 'America/Mexico_City']),
          },
        },
      ],
    }).compileComponents();

    const localFixture = TestBed.createComponent(ExpenseListComponent);
    const localComponent = localFixture.componentInstance;
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
      })
    );
  });

  it('renders the page header with the workspace subtitle', () => {
    fixture.detectChanges();
    const header = fixture.nativeElement.querySelector('app-page-header .page-header__title');
    expect(header).toBeTruthy();
    const subtitle = fixture.nativeElement.querySelector('app-page-header .page-header__subtitle');
    expect(subtitle?.textContent).toContain('Workspace');
  });

  it('renders the page filters container with the filter fields', () => {
    fixture.detectChanges();
    const filters = fixture.nativeElement.querySelector('app-page-filters .page-filters');
    expect(filters).toBeTruthy();
    const fields = fixture.nativeElement.querySelectorAll('app-page-filters .filter-field');
    expect(fields.length).toBe(5);
  });

  it('renders the summary hero only when total is known', () => {
    fixture.detectChanges();
    const hero = fixture.nativeElement.querySelector('app-summary-hero .summary-hero');
    expect(hero).toBeTruthy();

    component.totalAmount.set(null);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('app-summary-hero')).toBeNull();
  });

  it('renders the data table with the column headers', () => {
    fixture.detectChanges();
    const headers = fixture.nativeElement.querySelectorAll('app-data-table thead th');
    expect(headers.length).toBe(6);
  });

  it('renders the category badge inside the category cell template', () => {
    fixture.detectChanges();
    fixture.detectChanges();
    const badge = fixture.nativeElement.querySelector('app-data-table .category-badge');
    expect(badge).toBeTruthy();
    expect(badge.textContent).toContain('Food');
  });

  it('renders the action buttons in the actions column', () => {
    fixture.detectChanges();
    fixture.detectChanges();
    const actionButtons = fixture.nativeElement.querySelectorAll(
      'app-data-table .action-buttons .icon-btn'
    );
    expect(actionButtons.length).toBe(2);
  });

  it('navigates to the edit route when the action buttons emit edit', () => {
    fixture.detectChanges();
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate');
    component.navigateToEdit('exp-1');
    expect(navigateSpy).toHaveBeenCalledWith(['/user/workspaces', 'ws-1', 'expenses', 'exp-1', 'edit']);
  });

  it('hides the pagination bar when there is only one page', () => {
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('app-pagination-bar')).toBeNull();
  });

  it('shows the pagination bar when there are multiple pages', () => {
    expensesServiceMock.list.mockReturnValue(of({
      data: [mockExpenseListResponse.data[0]],
      meta: { current_page: 1, last_page: 3, per_page: 15, total: 30 },
    }));
    component.loadExpenses();
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('app-pagination-bar')).toBeTruthy();
  });
});
