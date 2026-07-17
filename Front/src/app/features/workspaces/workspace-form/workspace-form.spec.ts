import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { WorkspaceFormComponent } from './workspace-form';
import { WorkspacesService } from '../../../core/services/workspaces';
import { STORAGE_SERVICE_TOKEN } from '../../../core/tokens/storage.token';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { AuthStateService } from '../../../core/services/auth-state.service';

const storageMock = {
  getItem: vi.fn().mockReturnValue(null),
  setItem: vi.fn(),
  removeItem: vi.fn(),
};

const toastMock = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

const workspaceContextMock = {
  invalidateCache: vi.fn(),
};

const authStateMock = {
  defaultWorkspaceId: vi.fn().mockReturnValue(null),
  userId: vi.fn().mockReturnValue('user-uuid-1'),
  setDefaultWorkspaceId: vi.fn(),
};

const mockWorkspace = {
  id: 'ws-1',
  owner_id: 'user-uuid-1',
  name: 'My Workspace',
  type: 'personal' as const,
  currency_code: 'USD',
  created_at: '2024-01-01',
  updated_at: '2024-01-01',
};

describe('WorkspaceFormComponent — create mode', () => {
  let fixture: ComponentFixture<WorkspaceFormComponent>;
  let component: WorkspaceFormComponent;
  let workspacesServiceMock: {
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    getById: ReturnType<typeof vi.fn>;
  };
  let router: Router;

  beforeEach(async () => {
    workspacesServiceMock = {
      create: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
      update: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
      getById: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
    };

    await TestBed.configureTestingModule({
      imports: [WorkspaceFormComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: STORAGE_SERVICE_TOKEN, useValue: storageMock },
        { provide: ToastService, useValue: toastMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: vi.fn().mockReturnValue(null) },
              data: { mode: 'create' },
            },
          },
        },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    fixture = TestBed.createComponent(WorkspaceFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create in create mode', () => {
    expect(component).toBeTruthy();
    expect(component.mode).toBe('create');
  });

  it('should not call getById in create mode', () => {
    expect(workspacesServiceMock.getById).not.toHaveBeenCalled();
  });

  it('should call workspacesService.create() on valid submit in create mode', () => {
    component.form.setValue({ name: 'New WS', type: 'personal', currency_code: 'USD' });
    component.submit();
    expect(workspacesServiceMock.create).toHaveBeenCalledWith({
      name: 'New WS',
      type: 'personal',
      currency_code: 'USD',
    });
  });

  it('should not submit when form is invalid', () => {
    component.form.setValue({ name: '', type: 'personal', currency_code: 'USD' });
    component.submit();
    expect(workspacesServiceMock.create).not.toHaveBeenCalled();
  });

  it('should stop loading when create fails', () => {
    workspacesServiceMock.create.mockReturnValueOnce(throwError(() => ({ status: 500 })));
    component.form.setValue({ name: 'New WS', type: 'personal', currency_code: 'USD' });
    component.submit();
    expect(component.loading()).toBe(false);
    expect(toastMock.error).not.toHaveBeenCalled();
  });
});

describe('WorkspaceFormComponent — edit mode', () => {
  let fixture: ComponentFixture<WorkspaceFormComponent>;
  let component: WorkspaceFormComponent;
  let workspacesServiceMock: {
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    getById: ReturnType<typeof vi.fn>;
  };
  let router: Router;

  beforeEach(async () => {
    workspacesServiceMock = {
      create: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
      update: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
      getById: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
    };

    await TestBed.configureTestingModule({
      imports: [WorkspaceFormComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: STORAGE_SERVICE_TOKEN, useValue: storageMock },
        { provide: ToastService, useValue: toastMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: vi.fn().mockReturnValue('ws-1') },
              data: { mode: 'edit' },
            },
          },
        },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    fixture = TestBed.createComponent(WorkspaceFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create in edit mode and call getById', () => {
    expect(component).toBeTruthy();
    expect(component.mode).toBe('edit');
    expect(workspacesServiceMock.getById).toHaveBeenCalledWith('ws-1');
  });

  it('should call workspacesService.update() on valid submit in edit mode', () => {
    component.form.setValue({ name: 'Updated WS', type: 'familiar', currency_code: 'EUR' });
    component.submit();
    expect(workspacesServiceMock.update).toHaveBeenCalledWith('ws-1', {
      name: 'Updated WS',
      type: 'familiar',
      currency_code: 'EUR',
    });
  });
});

describe('WorkspaceFormComponent — settings edit back label', () => {
  beforeEach(async () => {
    const workspacesServiceMock = {
      create: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
      update: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
      getById: vi.fn().mockReturnValue(of({ data: mockWorkspace })),
    };

    await TestBed.configureTestingModule({
      imports: [WorkspaceFormComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: STORAGE_SERVICE_TOKEN, useValue: storageMock },
        { provide: ToastService, useValue: toastMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: vi.fn().mockReturnValue('ws-1') },
              data: { mode: 'edit' },
            },
          },
        },
      ],
    }).compileComponents();

    const settingsRouter = TestBed.inject(Router);
    Object.defineProperty(settingsRouter, 'url', {
      value: '/user/settings/workspaces/ws-1/edit',
      configurable: true,
    });
  });

  it('should use back label for settings edit route', () => {
    const settingsFixture = TestBed.createComponent(WorkspaceFormComponent);
    settingsFixture.detectChanges();
    expect(settingsFixture.componentInstance.backLabelKey()).toBe('workspaces.back');
  });
});
