import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { WorkspaceContextService } from './workspace-context';
import { WorkspacesService } from './workspaces';
import { AuthStateService } from './auth-state.service';
import { STORAGE_SERVICE_TOKEN } from '../tokens/storage.token';
import { Workspace } from '../models/workspace.model';

const makeWorkspace = (id: string): Workspace => ({
  id,
  owner_id: 'user-uuid-1',
  name: `Workspace ${id}`,
  type: 'personal',
  currency_code: 'USD',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
});

describe('WorkspaceContextService — default workspace resolution', () => {
  let service: WorkspaceContextService;
  let workspacesServiceMock: { list: ReturnType<typeof vi.fn> };
  let authStateMock: { defaultWorkspaceId: ReturnType<typeof vi.fn> };
  let storageMock: { getItem: ReturnType<typeof vi.fn>; setItem: ReturnType<typeof vi.fn>; removeItem: ReturnType<typeof vi.fn> };

  const workspaceA = makeWorkspace('ws-aaa');
  const workspaceB = makeWorkspace('ws-bbb');
  const workspaces = [workspaceA, workspaceB];

  function setup(storageValue: string | null, defaultWorkspaceId: string | null): void {
    workspacesServiceMock = {
      list: vi.fn().mockReturnValue(of({ data: workspaces, meta: { current_page: 1, last_page: 1, per_page: 100, total: 2 } })),
    };
    authStateMock = {
      defaultWorkspaceId: vi.fn().mockReturnValue(defaultWorkspaceId),
    };
    storageMock = {
      getItem: vi.fn().mockReturnValue(storageValue),
      setItem: vi.fn(),
      removeItem: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        WorkspaceContextService,
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: STORAGE_SERVICE_TOKEN, useValue: storageMock },
      ],
    });

    service = TestBed.inject(WorkspaceContextService);
  }

  afterEach(() => {
    TestBed.resetTestingModule();
  });

  it('uses default_workspace_id when no localStorage value exists', async () => {
    setup(null, 'ws-bbb');

    await service.ensureLoaded();

    expect(service.currentWorkspaceId()).toBe('ws-bbb');
  });

  it('falls back to first workspace when default_workspace_id is null', async () => {
    setup(null, null);

    await service.ensureLoaded();

    expect(service.currentWorkspaceId()).toBe('ws-aaa');
  });

  it('ignores default_workspace_id when it does not match any workspace in the list', async () => {
    setup(null, 'ws-invalid-id');

    await service.ensureLoaded();

    expect(service.currentWorkspaceId()).toBe('ws-aaa');
  });

  it('uses default_workspace_id over localStorage value when both are set', async () => {
    setup('ws-aaa', 'ws-bbb');

    await service.ensureLoaded();

    expect(service.currentWorkspaceId()).toBe('ws-bbb');
  });

  it('keeps bootstrap defensive fallback observable when loading workspaces fails', async () => {
    setup(null, null);
    workspacesServiceMock.list.mockReturnValueOnce(throwError(() => ({
      status: 503,
      error: {
        status: 503,
        code: 'internal_error',
        message: 'Workspace API unavailable',
        request_id: 'req-workspaces',
      },
    })));

    const result = await service.ensureLoaded();

    expect(result).toEqual([]);
    expect(service.loaded()).toBe(false);
    expect(service.loadError()?.message).toBe('Workspace API unavailable');
  });

  it('retries loading after a defensive fallback failure', async () => {
    setup(null, null);
    workspacesServiceMock.list
      .mockReturnValueOnce(throwError(() => ({
        status: 503,
        error: {
          status: 503,
          code: 'internal_error',
          message: 'Workspace API unavailable',
          request_id: 'req-workspaces',
        },
      })))
      .mockReturnValueOnce(of({ data: workspaces, meta: { current_page: 1, last_page: 1, per_page: 100, total: 2 } }));

    await service.ensureLoaded();
    await service.ensureLoaded();

    expect(service.loaded()).toBe(true);
    expect(service.loadError()).toBeNull();
    expect(service.currentWorkspaceId()).toBe('ws-aaa');
  });
});
