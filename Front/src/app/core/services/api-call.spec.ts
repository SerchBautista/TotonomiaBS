import { HttpContext } from '@angular/common/http';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { ApiServiceInterface } from '../tokens/api-service.token';
import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './api-call';

describe('api-call helpers', () => {
  const api = {
    get: vi.fn(() => of({ ok: true })),
    post: vi.fn(() => of({ ok: true })),
    put: vi.fn(() => of({ ok: true })),
    patch: vi.fn(() => of({ ok: true })),
    delete: vi.fn(() => of(undefined)),
  } as unknown as ApiServiceInterface;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('delegates GET to the API service without options', () => {
    apiGet(api, '/items').subscribe();

    expect(api.get).toHaveBeenCalledWith('/items');
  });

  it('delegates GET to the API service with options', () => {
    const context = new HttpContext();
    apiGet(api, '/items', { context }).subscribe();

    expect(api.get).toHaveBeenCalledWith('/items', { context });
  });

  it('delegates POST to the API service', () => {
    const body = { name: 'test' };
    apiPost(api, '/items', body).subscribe();

    expect(api.post).toHaveBeenCalledWith('/items', body);
  });

  it('delegates PUT, PATCH and DELETE to the API service', () => {
    apiPut(api, '/items/1', { name: 'x' }).subscribe();
    apiPatch(api, '/items/1', { name: 'y' }).subscribe();
    apiDelete(api, '/items/1').subscribe();

    expect(api.put).toHaveBeenCalledWith('/items/1', { name: 'x' });
    expect(api.patch).toHaveBeenCalledWith('/items/1', { name: 'y' });
    expect(api.delete).toHaveBeenCalledWith('/items/1');
  });
});
