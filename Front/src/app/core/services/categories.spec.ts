import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { firstValueFrom, of } from 'rxjs';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { CategoriesService } from './categories';

describe('CategoriesService', () => {
  let service: CategoriesService;
  let apiMock: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    apiMock = {
      get: vi.fn().mockReturnValue(of({})),
      post: vi.fn().mockReturnValue(of({})),
      put: vi.fn().mockReturnValue(of({})),
      patch: vi.fn().mockReturnValue(of({})),
      delete: vi.fn().mockReturnValue(of({})),
    };

    TestBed.configureTestingModule({
      providers: [CategoriesService, { provide: API_SERVICE_TOKEN, useValue: apiMock }],
    });

    service = TestBed.inject(CategoriesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('should call api.get with correct path on list()', () => {
    service.list('ws-1').subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/categories');
  });

  it('should call api.get with the user-scoped path on listMine()', () => {
    service.listMine().subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/user/categories');
  });

  it('should call api.post with the user-scoped path on createMine()', () => {
    const payload = { name: 'Food', icon: 'tag', color: '#ff0000' };
    service.createMine(payload).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/user/categories', payload);
  });

  it('should emit created category events for every workspace on createMine()', async () => {
    const response = {
      data: { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000' },
    };
    apiMock.post.mockReturnValue(of(response));

    const received: Array<{ workspaceId: string; category: typeof response.data }> = [];
    const subscription = service.categoryCreated$.subscribe((event) => received.push(event));

    service
      .createMine({
        name: 'Food',
        icon: 'tag',
        color: '#ff0000',
        workspace_ids: ['ws-1', 'ws-2', 'ws-1'],
      })
      .subscribe();

    await new Promise<void>((resolve) => queueMicrotask(() => resolve()));
    subscription.unsubscribe();

    expect(received).toEqual([
      { workspaceId: 'ws-1', category: response.data },
      { workspaceId: 'ws-2', category: response.data },
    ]);
  });

  it('should call api.put with the user-scoped path on updateMine()', () => {
    const payload = { name: 'Food Updated', icon: 'fork', color: '#00ff00' };
    service.updateMine('cat-1', payload).subscribe();
    expect(apiMock.put).toHaveBeenCalledWith('/user/categories/cat-1', payload);
  });

  it('should call api.patch with workspace_ids on updateWorkspaces()', () => {
    service.updateWorkspaces('cat-1', ['ws-1', 'ws-2']).subscribe();
    expect(apiMock.patch).toHaveBeenCalledWith('/user/categories/cat-1/workspaces', {
      workspace_ids: ['ws-1', 'ws-2'],
    });
  });

  it('should call api.delete with the user-scoped path on deleteMine()', () => {
    service.deleteMine('cat-1').subscribe();
    expect(apiMock.delete).toHaveBeenCalledWith('/user/categories/cat-1');
  });

  it('should call api.patch with the user-scoped default path on setAsDefaultMine()', () => {
    service.setAsDefaultMine('cat-1').subscribe();
    expect(apiMock.patch).toHaveBeenCalledWith('/user/categories/cat-1/default', {});
  });

  it('should call api.get with correct path on listValid()', () => {
    service.listValid('ws-1').subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/categories/valid');
  });

  it('should call api.post with correct path on create()', () => {
    const payload = { name: 'Food', icon: 'tag', color: '#ff0000' };
    service.create('ws-1', payload).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/categories', payload);
  });

  it('should emit created category event on create()', async () => {
    const response = {
      data: { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000' },
    };
    apiMock.post.mockReturnValue(of(response));

    const createdEventPromise = firstValueFrom(service.categoryCreated$);

    service.create('ws-1', { name: 'Food', icon: 'tag', color: '#ff0000' }).subscribe();

    await expect(createdEventPromise).resolves.toEqual({
      workspaceId: 'ws-1',
      category: response.data,
    });
  });

  it('should call api.delete with correct path on delete()', () => {
    service.delete('ws-1', 'cat-1').subscribe();
    expect(apiMock.delete).toHaveBeenCalledWith('/workspaces/ws-1/categories/cat-1');
  });

  it('should call api.put with correct path on update()', () => {
    const payload = { name: 'Food Updated', icon: 'fork', color: '#00ff00' };
    service.update('ws-1', 'cat-1', payload).subscribe();
    expect(apiMock.put).toHaveBeenCalledWith('/workspaces/ws-1/categories/cat-1', payload);
  });

  it('should call api.post with correct path on assignCategory()', () => {
    service.assignCategory('ws-1', 'cat-1').subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/categories/cat-1/assign', {});
  });

  it('should call api.delete with correct path on unassignCategory()', () => {
    service.unassignCategory('ws-1', 'cat-1').subscribe();
    expect(apiMock.delete).toHaveBeenCalledWith('/workspaces/ws-1/categories/cat-1/assign');
  });

  it('should call api.patch with correct path on updateLink()', () => {
    service.updateLink('ws-1', 'cat-1', true).subscribe();
    expect(apiMock.patch).toHaveBeenCalledWith('/workspaces/ws-1/categories/cat-1/link', {
      is_linked: true,
    });
  });

  it('should call api.patch with correct path on updateActivation()', () => {
    service.updateActivation('ws-1', 'cat-1', false).subscribe();
    expect(apiMock.patch).toHaveBeenCalledWith('/workspaces/ws-1/categories/cat-1/activation', {
      is_active: false,
    });
  });

  it('should call api.post with correct path on bulkLinking()', () => {
    service.bulkLinking('ws-1', false).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/categories/link-bulk', {
      operation: 'unlink_all',
    });
  });

  it('should call api.post with link_all operation on bulkLinking(true)', () => {
    service.bulkLinking('ws-1', true).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/categories/link-bulk', {
      operation: 'link_all',
    });
  });
});
