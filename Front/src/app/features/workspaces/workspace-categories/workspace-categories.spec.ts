import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, Subject, throwError } from 'rxjs';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { Category } from '../../../core/models/category.model';
import { CategoriesService } from '../../../core/services/categories';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { WorkspaceCategoriesComponent } from './workspace-categories';

const mockCategories: Category[] = [
  {
    id: 'cat-1',
    user_id: 'user-1',
    name: 'Food',
    icon: 'tag',
    color: '#111111',
    state: 'linked',
    is_linked: true,
    is_active_in_workspace: true,
    is_in_use_in_workspace: true,
    usage_count_in_workspace: 4,
    is_valid_for_transactions: true,
  },
  {
    id: 'cat-2',
    user_id: 'user-1',
    name: 'Transport',
    icon: 'car',
    color: '#222222',
    state: 'unlinked',
    is_linked: false,
    is_active_in_workspace: true,
    is_in_use_in_workspace: false,
    usage_count_in_workspace: 0,
    is_valid_for_transactions: false,
  },
  {
    id: 'cat-3',
    user_id: 'user-1',
    name: 'Health',
    icon: 'heart',
    color: '#333333',
    state: 'linked_read_only',
    is_linked: true,
    is_active_in_workspace: true,
    is_in_use_in_workspace: false,
    usage_count_in_workspace: 1,
    is_valid_for_transactions: false,
  },
];

const linkedCat2Categories: Category[] = [
  mockCategories[0],
  {
    ...mockCategories[1],
    state: 'linked',
    is_linked: true,
    is_valid_for_transactions: true,
  },
  mockCategories[2],
];

const unlinkedCat1Categories: Category[] = [
  {
    ...mockCategories[0],
    state: 'unlinked',
    is_linked: false,
    is_valid_for_transactions: false,
  },
  mockCategories[1],
  mockCategories[2],
];

const activatedRouteMock = {
  snapshot: {
    paramMap: { get: (key: string) => (key === 'id' ? 'ws-1' : null) },
    parent: {
      paramMap: { get: () => null },
    },
  },
};

describe('WorkspaceCategoriesComponent', () => {
  let fixture: ComponentFixture<WorkspaceCategoriesComponent>;
  let component: WorkspaceCategoriesComponent;
  let categoriesMock: {
    list: ReturnType<typeof vi.fn>;
    updateLink: ReturnType<typeof vi.fn>;
    createMine: ReturnType<typeof vi.fn>;
    categoryCreated$: Subject<{ workspaceId: string }>;
  };
  let workspaceContextMock: {
    ensureLoaded: ReturnType<typeof vi.fn>;
    selectedWorkspace: ReturnType<typeof vi.fn>;
    currentWorkspaceId: ReturnType<typeof vi.fn>;
    setCurrentWorkspaceId: ReturnType<typeof vi.fn>;
  };
  let authStateMock: {
    userId: ReturnType<typeof vi.fn>;
  };

  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  async function setup(
    overrides?: Partial<typeof categoriesMock>,
    options?: { userId?: string; selectedWorkspaceId?: string; selectedWorkspaceOwnerId?: string },
  ) {
    TestBed.resetTestingModule();

    categoriesMock = {
      list: vi.fn().mockReturnValue(of({ data: mockCategories })),
      updateLink: vi.fn().mockReturnValue(of(void 0)),
      createMine: vi.fn().mockReturnValue(of({ data: mockCategories[0] })),
      categoryCreated$: new Subject(),
      ...overrides,
    };

    workspaceContextMock = {
      ensureLoaded: vi.fn().mockResolvedValue([]),
      selectedWorkspace: vi.fn().mockReturnValue({
        id: options?.selectedWorkspaceId ?? 'ws-1',
        owner_id: options?.selectedWorkspaceOwnerId ?? 'user-1',
      }),
      currentWorkspaceId: vi.fn().mockReturnValue('ws-ctx-1'),
      setCurrentWorkspaceId: vi.fn(),
    };

    authStateMock = {
      userId: vi.fn().mockReturnValue(options?.userId ?? 'user-1'),
    };

    await TestBed.configureTestingModule({
      imports: [WorkspaceCategoriesComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ActivatedRoute, useValue: activatedRouteMock },
        { provide: CategoriesService, useValue: categoriesMock },
        { provide: ToastService, useValue: toastMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: AuthStateService, useValue: authStateMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WorkspaceCategoriesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  }

  beforeEach(async () => {
    vi.clearAllMocks();
    await setup();
  });

  it('should split categories into using here and available groups', async () => {
    await fixture.whenStable();
    fixture.detectChanges();

    expect(component.usingHere().map((item) => item.id)).toEqual(['cat-1']);
    expect(component.available().map((item) => item.id)).toEqual(['cat-2', 'cat-3']);

    const html = fixture.nativeElement as HTMLElement;
    const text = html.textContent ?? '';
    expect(text).toContain('workspace_categories.available_badge');
    expect(text).toContain('workspace_categories.history_badge');
    expect(html.querySelector('.workspace-help')).toBeTruthy();
    expect(html.querySelectorAll('.workspace-help li')).toHaveLength(3);
  });

  it('should treat read_only_linked API state as history category', () => {
    const category = {
      ...mockCategories[1],
      state: 'read_only_linked',
      is_linked: false,
      is_valid_for_transactions: false,
      is_in_use_in_workspace: true,
    };

    expect(component.getCategoryState(category)).toBe('linked_read_only');
  });

  it('should filter using-here and available lists by column search', () => {
    component.setUsingSearch('food');
    expect(component.usingHere().map((item) => item.id)).toEqual(['cat-1']);

    component.setAvailableSearch('trans');
    expect(component.available().map((item) => item.id)).toEqual(['cat-2']);
  });

  it('should update state and availability when linking a category', async () => {
    await setup({
      list: vi
        .fn()
        .mockReturnValueOnce(of({ data: mockCategories }))
        .mockReturnValueOnce(of({ data: linkedCat2Categories })),
    });

    component.updateCategoryUsage(mockCategories[1], true);

    expect(categoriesMock.updateLink).toHaveBeenCalledWith('ws-1', 'cat-2', true);
    const updatedCategory = component.categories().find((item) => item.id === 'cat-2');
    expect(updatedCategory?.is_linked).toBe(true);
    expect(updatedCategory?.is_valid_for_transactions).toBe(true);
    expect(updatedCategory?.state).toBe('linked');
    expect(categoriesMock.list).toHaveBeenCalledTimes(2);
  });

  it('should update state and availability when unlinking a category', async () => {
    await setup({
      list: vi
        .fn()
        .mockReturnValueOnce(of({ data: mockCategories }))
        .mockReturnValueOnce(of({ data: unlinkedCat1Categories })),
    });

    component.updateCategoryUsage(mockCategories[0], false);

    expect(categoriesMock.updateLink).toHaveBeenCalledWith('ws-1', 'cat-1', false);
    const updatedCategory = component.categories().find((item) => item.id === 'cat-1');
    expect(updatedCategory?.is_linked).toBe(false);
    expect(updatedCategory?.is_valid_for_transactions).toBe(false);
    expect(updatedCategory?.state).toBe('unlinked');
    expect(categoriesMock.list).toHaveBeenCalledTimes(2);
  });

  it('should not show local toast when unlink fails', async () => {
    await setup({
      updateLink: vi.fn().mockReturnValue(throwError(() => ({ status: 409 }))),
    });

    component.updateCategoryUsage(mockCategories[0], false);

    expect(toastMock.error).not.toHaveBeenCalled();
    expect(toastMock.warning).not.toHaveBeenCalled();
  });

  it('should resolve workspace id from context when route param is missing', async () => {
    const routeWithoutId = {
      snapshot: {
        paramMap: { get: () => null },
        parent: { paramMap: { get: () => null } },
      },
    };

    TestBed.resetTestingModule();

    categoriesMock = {
      list: vi.fn().mockReturnValue(of({ data: mockCategories })),
      updateLink: vi.fn().mockReturnValue(of(void 0)),
      createMine: vi.fn().mockReturnValue(of({ data: mockCategories[0] })),
      categoryCreated$: new Subject(),
    };

    workspaceContextMock = {
      ensureLoaded: vi.fn().mockResolvedValue([]),
      selectedWorkspace: vi.fn().mockReturnValue({ id: 'ws-ctx-99', owner_id: 'user-1' }),
      currentWorkspaceId: vi.fn().mockReturnValue('ws-ctx-99'),
      setCurrentWorkspaceId: vi.fn(),
    };

    authStateMock = {
      userId: vi.fn().mockReturnValue('user-1'),
    };

    await TestBed.configureTestingModule({
      imports: [WorkspaceCategoriesComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ActivatedRoute, useValue: routeWithoutId },
        { provide: CategoriesService, useValue: categoriesMock },
        { provide: ToastService, useValue: toastMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: AuthStateService, useValue: authStateMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WorkspaceCategoriesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();

    expect(workspaceContextMock.ensureLoaded).toHaveBeenCalled();
    expect(workspaceContextMock.setCurrentWorkspaceId).toHaveBeenCalledWith('ws-ctx-99');
    expect(component.workspaceId).toBe('ws-ctx-99');
  });

  it('should not show local toast when link fails with backend detail', async () => {
    await setup({
      updateLink: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 500,
          error: {
            status: 500,
            code: BACKEND_ERROR_CODES.internalError,
            message: 'Endpoint not found',
            request_id: 'req-1',
          },
        })),
      ),
    });

    component.updateCategoryUsage(mockCategories[1], true);

    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should not show local toast when backend rejects category creation', async () => {
    await setup({
      createMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 403,
          error: {
            status: 403,
            code: BACKEND_ERROR_CODES.workspaceCategoryOwnerOnly,
            message: 'Only owner can create categories',
            request_id: 'req-2',
          },
        })),
      ),
    });

    component.toggleCreateForm();
    component.createForm.setValue({ name: 'Nueva categoría', icon: 'tag', color: '#16324f' });
    component.submitCreate();

    expect(component.forbidden()).toBe(false);
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should create category from workspace view', () => {
    component.toggleCreateForm();
    component.createForm.setValue({ name: 'New category', icon: 'tag', color: '#16324f' });

    component.submitCreate();

    expect(categoriesMock.createMine).toHaveBeenCalledWith({
      name: 'New category',
      icon: 'tag',
      color: '#16324f',
      workspace_ids: ['ws-1'],
    });
  });

  it('should refresh workspace categories when a category is created from another flow in the same workspace', () => {
    expect(categoriesMock.list).toHaveBeenCalledTimes(1);

    categoriesMock.categoryCreated$.next({ workspaceId: 'ws-1' });

    expect(categoriesMock.list).toHaveBeenCalledTimes(2);
  });

  it('should ignore category creation events from other workspaces', () => {
    expect(categoriesMock.list).toHaveBeenCalledTimes(1);

    categoriesMock.categoryCreated$.next({ workspaceId: 'ws-2' });

    expect(categoriesMock.list).toHaveBeenCalledTimes(1);
  });

  it('should block admin actions when user is not workspace owner', async () => {
    await setup(undefined, { userId: 'user-2' });

    component.updateCategoryUsage(mockCategories[1], true);
    component.toggleCreateForm();

    expect(categoriesMock.updateLink).not.toHaveBeenCalled();
    expect(component.showForm()).toBe(false);
  });

  it('should render the owner-only notice and hide management actions for non-owner users', async () => {
    await setup(undefined, { userId: 'user-2' });
    await fixture.whenStable();
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    expect(html.textContent).toContain('workspace_categories.owner_only');

    const createButton = html.querySelector(
      'app-page-header .btn.primary',
    ) as HTMLButtonElement | null;
    expect(createButton).toBeNull();

    const bulkButtons = Array.from(
      html.querySelectorAll('.bulk-buttons button'),
    ) as HTMLButtonElement[];
    expect(bulkButtons.length).toBe(0);
  });

  it('should not render legacy table row actions', async () => {
    await fixture.whenStable();
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    const rowButtons = Array.from(
      html.querySelectorAll('tbody .row-actions button'),
    ) as HTMLButtonElement[];

    expect(rowButtons.length).toBe(0);
    expect(html.textContent).not.toContain('Desactivar');
    expect(html.textContent).not.toContain('Reactivar');
  });

  it('should not render usage column', async () => {
    await fixture.whenStable();
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    const headers = Array.from(html.querySelectorAll('thead th')).map(
      (cell) => cell.textContent?.trim() ?? '',
    );

    expect(headers).not.toContain('Uso');
  });

  it('should keep only the create action in the page header', async () => {
    await fixture.whenStable();
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    const headerActions = Array.from(html.querySelectorAll('app-page-header .btn'));

    expect(headerActions).toHaveLength(1);
    expect(html.querySelector('app-page-header a[routerLink]')).toBeNull();
  });
});
