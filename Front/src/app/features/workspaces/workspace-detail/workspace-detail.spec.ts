import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { WorkspaceDetailComponent } from './workspace-detail';
import { WorkspacesService } from '../../../core/services/workspaces';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { ToastService } from '../../../core/services/toast.service';
import { Workspace } from '../../../core/models/workspace.model';

const baseMockWorkspace: Workspace = {
  id: 'ws-1',
  owner_id: 'user-uuid-1',
  name: 'Detail Workspace',
  type: 'personal',
  currency_code: 'USD',
  created_at: '2024-01-01',
  updated_at: '2024-01-01',
};

function makeAuthStateMock(overrides: { userId?: string | null } = {}) {
  return {
    userId: vi.fn().mockReturnValue(overrides.userId ?? 'user-uuid-1'),
  };
}

function makeWorkspaceMock(overrides: Partial<Workspace> = {}): Workspace {
  return { ...baseMockWorkspace, ...overrides };
}

describe('WorkspaceDetailComponent', () => {
  let fixture: ComponentFixture<WorkspaceDetailComponent>;
  let component: WorkspaceDetailComponent;
  let workspacesServiceMock: { getById: ReturnType<typeof vi.fn> };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  function setup(
    id: string | null,
    options: { userId?: string | null; workspace?: Workspace } = {},
  ) {
    const workspace = options.workspace ?? baseMockWorkspace;
    workspacesServiceMock = {
      getById: vi.fn().mockReturnValue(of({ data: workspace })),
    };

    TestBed.configureTestingModule({
      imports: [WorkspaceDetailComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        {
          provide: WorkspaceContextService,
          useValue: {
            setCurrentWorkspaceId: vi.fn(),
          },
        },
        { provide: AuthStateService, useValue: makeAuthStateMock({ userId: options.userId }) },
        { provide: ToastService, useValue: toastMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: vi.fn().mockReturnValue(id) },
              queryParamMap: { get: vi.fn().mockReturnValue(null) },
              data: {},
            },
          },
        },
      ],
    });
  }

  it('should create the component', async () => {
    setup('ws-1');
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('should call workspacesService.getById() on init', async () => {
    setup('ws-1');
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(workspacesServiceMock.getById).toHaveBeenCalledWith('ws-1');
  });

  it('should show the consolidated categories tab and hide the legacy sharing route', async () => {
    setup('ws-1');
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const links = Array.from(
      fixture.nativeElement.querySelectorAll('nav.tabs a'),
    ) as HTMLAnchorElement[];
    const hrefs = links.map((link) => link.getAttribute('href') ?? '');

    expect(hrefs.some((href) => href.includes('/categories'))).toBe(true);
    expect(hrefs.some((href) => href.includes('/category-sharing'))).toBe(false);
  });

  it('should redirect to /user/settings/workspaces if no id param', async () => {
    setup(null);
    await TestBed.compileComponents();
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(navigateSpy).toHaveBeenCalledWith('/user/settings/workspaces');
    expect(workspacesServiceMock.getById).not.toHaveBeenCalled();
  });

  it('should stop loading when workspace load fails', async () => {
    setup('ws-1');
    workspacesServiceMock.getById.mockReturnValue(throwError(() => ({ status: 500 })));
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(toastMock.error).not.toHaveBeenCalled();
    expect(component.loading()).toBe(false);
  });

  it('should render the page header with the workspace name', async () => {
    setup('ws-1');
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const header = fixture.nativeElement.querySelector('app-page-header');
    expect(header).toBeTruthy();
    expect(header.textContent).toContain('Detail Workspace');
  });

  // -------------------------------------------------------------------------
  // Phase 5 — M-1: owner-only actions gating
  // -------------------------------------------------------------------------

  it('should not show the Edit button when current user is not the workspace owner', async () => {
    setup('ws-1', {
      userId: 'different-user-uuid',
      workspace: makeWorkspaceMock({ owner_id: 'user-uuid-1' }),
    });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.isOwner()).toBe(false);

    const buttons = Array.from(
      fixture.nativeElement.querySelectorAll('app-page-header button'),
    ) as HTMLButtonElement[];
    const editButton = buttons.find((btn) => btn.textContent?.includes('Editar'));

    expect(editButton).toBeUndefined();
  });

  it('should show the Edit button for workspace owner and open the edit modal on click', async () => {
    setup('ws-1', {
      userId: 'user-uuid-1',
      workspace: makeWorkspaceMock({ owner_id: 'user-uuid-1' }),
    });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.isOwner()).toBe(true);
    expect(component.editModalOpen()).toBe(false);

    const editButton = fixture.nativeElement.querySelector(
      'app-page-header button .fa-pen',
    )?.closest('button') as HTMLButtonElement | null;
    expect(editButton).toBeTruthy();

    component.openEditModal();
    fixture.detectChanges();

    expect(component.editModalOpen()).toBe(true);
    expect(fixture.nativeElement.querySelector('app-workspace-edit-modal')).toBeTruthy();
  });

  it('should not show the Manage members link when current user is not the workspace owner', async () => {
    setup('ws-1', {
      userId: 'different-user-uuid',
      workspace: makeWorkspaceMock({ owner_id: 'user-uuid-1', owner_plan: 'premium' }),
    });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.isOwner()).toBe(false);
    expect(component.canManageMembers()).toBe(false);

    const membersHref = `/user/workspaces/${component.workspaceId}/members`;
    const links = Array.from(
      fixture.nativeElement.querySelectorAll('app-page-header a'),
    ) as HTMLAnchorElement[];
    const hrefs = links.map((link) => link.getAttribute('href') ?? '');

    expect(hrefs.some((href) => href === membersHref)).toBe(false);
  });

  it('should not show the Manage members link when owner is on a free plan', async () => {
    setup('ws-1', {
      userId: 'user-uuid-1',
      workspace: makeWorkspaceMock({ owner_id: 'user-uuid-1', owner_plan: 'free' }),
    });
    await TestBed.compileComponents();
    fixture = TestBed.createComponent(WorkspaceDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.isOwner()).toBe(true);
    expect(component.canManageMembers()).toBe(false);

    const membersHref = `/user/workspaces/${component.workspaceId}/members`;
    const links = Array.from(
      fixture.nativeElement.querySelectorAll('app-page-header a'),
    ) as HTMLAnchorElement[];
    const hrefs = links.map((link) => link.getAttribute('href') ?? '');

    expect(hrefs.some((href) => href === membersHref)).toBe(false);
  });
});
