import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { WorkspaceListComponent } from './workspace-list';
import { WorkspacesService } from '../../../core/services/workspaces';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { ToastService } from '../../../core/services/toast.service';

const mockWorkspaceListResponse = {
  data: [
    {
      id: 'ws-1',
      owner_id: 'user-uuid-1',
      name: 'Test Workspace',
      type: 'personal' as const,
      currency_code: 'USD',
      created_at: '2024-01-01',
      updated_at: '2024-01-01',
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
};

const emptyWorkspaceListResponse = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
};

function makeAuthStateMock(overrides: { plan?: 'free' | 'premium'; userId?: string | null } = {}) {
  return {
    plan: vi.fn().mockReturnValue(overrides.plan ?? 'free'),
    userId: vi.fn().mockReturnValue(overrides.userId ?? 'user-uuid-1'),
    defaultWorkspaceId: vi.fn().mockReturnValue(null),
    setDefaultWorkspaceId: vi.fn(),
  };
}

describe('WorkspaceListComponent', () => {
  let fixture: ComponentFixture<WorkspaceListComponent>;
  let component: WorkspaceListComponent;
  let workspacesServiceMock: {
    list: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  function setup(
    authOverrides: { plan?: 'free' | 'premium'; userId?: string | null } = {},
    serviceOverrides?: {
      list?: ReturnType<typeof vi.fn>;
      delete?: ReturnType<typeof vi.fn>;
    },
  ) {
    workspacesServiceMock = {
      list: serviceOverrides?.list ?? vi.fn().mockReturnValue(of(mockWorkspaceListResponse)),
      delete: serviceOverrides?.delete ?? vi.fn().mockReturnValue(of({ message: 'deleted' })),
    };

    TestBed.configureTestingModule({
      imports: [WorkspaceListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        { provide: AuthStateService, useValue: makeAuthStateMock(authOverrides) },
        { provide: ToastService, useValue: toastMock },
      ],
    });
  }

  beforeEach(async () => {
    setup();
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceListComponent);
    component = fixture.componentInstance;
  });

  it('should create the component', () => {
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('should call workspacesService.list() on init', () => {
    fixture.detectChanges();
    expect(workspacesServiceMock.list).toHaveBeenCalledWith({ page: 1 });
  });

  it('should display workspaces when loaded', () => {
    fixture.detectChanges();
    expect(component.workspaces()).toHaveLength(1);
    expect(component.workspaces()[0].name).toBe('Test Workspace');
  });

  it('should open confirm dialog when requestDelete is called', () => {
    fixture.detectChanges();
    component.requestDelete('ws-1');
    expect(component.confirmOpen).toBe(true);
    expect(component.itemToDelete).toBe('ws-1');
  });

  it('should call workspacesService.delete() on confirmDelete', () => {
    fixture.detectChanges();
    component.requestDelete('ws-1');
    component.confirmDelete();
    expect(workspacesServiceMock.delete).toHaveBeenCalledWith('ws-1');
  });

  // -------------------------------------------------------------------------
  // 11.3 — workspace creation gate shows upgrade prompt for free user with existing workspace
  // -------------------------------------------------------------------------

  it('should show upgrade prompt for free user with owned workspace', async () => {
    TestBed.resetTestingModule();
    setup({ plan: 'free', userId: 'user-uuid-1' });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    // workspaces() already has ws-1 with owner_id: 'user-uuid-1' = userId
    component.requestCreate();

    expect(component.showUpgradePrompt()).toBe(true);
  });

  it('should not show upgrade prompt for free user with no owned workspace', async () => {
    workspacesServiceMock = {
      list: vi.fn().mockReturnValue(of(emptyWorkspaceListResponse)),
      delete: vi.fn(),
    };
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      imports: [WorkspaceListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        {
          provide: AuthStateService,
          useValue: makeAuthStateMock({ plan: 'free', userId: 'user-uuid-1' }),
        },
        { provide: ToastService, useValue: toastMock },
      ],
    });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    component.requestCreate();

    expect(component.showUpgradePrompt()).toBe(false);
    expect(navigateSpy).toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // 11.4 — workspace creation gate allows normal flow for premium user
  // -------------------------------------------------------------------------

  it('should navigate to create route for premium user even with existing workspace', async () => {
    TestBed.resetTestingModule();
    setup({ plan: 'premium', userId: 'user-uuid-1' });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    component.requestCreate();

    expect(component.showUpgradePrompt()).toBe(false);
    expect(navigateSpy).toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // Phase 5 — visual redesign coverage
  // -------------------------------------------------------------------------

  it('should stop loading when listing fails', async () => {
    TestBed.resetTestingModule();
    setup({}, { list: vi.fn().mockReturnValue(throwError(() => ({ status: 500 }))) });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.loading()).toBe(false);
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should not render pagination when only one page is available', () => {
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    expect(html.querySelector('app-pagination-bar')).toBeNull();
  });

  it('should render an empty state when there are no workspaces', async () => {
    TestBed.resetTestingModule();
    setup({}, { list: vi.fn().mockReturnValue(of(emptyWorkspaceListResponse)) });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    expect(html.querySelector('app-empty-state')).toBeTruthy();
    expect(component.workspaces()).toHaveLength(0);
  });

  it('should reset delete state when delete fails', async () => {
    TestBed.resetTestingModule();
    setup({}, { delete: vi.fn().mockReturnValue(throwError(() => ({ status: 500 }))) });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    component.requestDelete('ws-1');
    component.confirmDelete();

    expect(toastMock.error).not.toHaveBeenCalled();
  });
});
