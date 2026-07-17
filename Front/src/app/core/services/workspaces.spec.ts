import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { of } from 'rxjs';
import { WorkspacesService } from './workspaces';
import { ApiService } from './api';

describe('WorkspacesService', () => {
  let service: WorkspacesService;
  let apiMock: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn>; put: ReturnType<typeof vi.fn>; delete: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    apiMock = {
      get: vi.fn().mockReturnValue(of({})),
      post: vi.fn().mockReturnValue(of({})),
      put: vi.fn().mockReturnValue(of({})),
      delete: vi.fn().mockReturnValue(of({})),
    };

    TestBed.configureTestingModule({
      providers: [
        WorkspacesService,
        { provide: ApiService, useValue: apiMock },
      ],
    });

    service = TestBed.inject(WorkspacesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('should call api.get with /workspaces path on list()', () => {
    service.list().subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces');
  });

  it('should call api.get with page query param', () => {
    service.list({ page: 2 }).subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces?page=2');
  });

  it('should call api.get with /workspaces/:id on getById()', () => {
    service.getById('abc-123').subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/abc-123');
  });

  it('should call api.post with /workspaces on create()', () => {
    const payload = { name: 'My WS', type: 'personal' as const, currency_code: 'USD' };
    service.create(payload).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/workspaces', payload);
  });

  it('should call api.put with /workspaces/:id on update()', () => {
    const payload = { name: 'Updated' };
    service.update('abc-123', payload).subscribe();
    expect(apiMock.put).toHaveBeenCalledWith('/workspaces/abc-123', payload);
  });

  it('should call api.delete with /workspaces/:id on delete()', () => {
    service.delete('abc-123').subscribe();
    expect(apiMock.delete).toHaveBeenCalledWith('/workspaces/abc-123');
  });

  it('should call api.put with /user/default-workspace on setDefault()', () => {
    service.setDefault('abc-123').subscribe();
    expect(apiMock.put).toHaveBeenCalledWith('/user/default-workspace', { workspace_id: 'abc-123' });
  });
});
