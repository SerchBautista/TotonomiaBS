import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { of } from 'rxjs';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { ExpensesService } from './expenses';

describe('ExpensesService', () => {
  let service: ExpensesService;
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
        ExpensesService,
        { provide: API_SERVICE_TOKEN, useValue: apiMock },
      ],
    });

    service = TestBed.inject(ExpensesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('should call api.get with correct path on list()', () => {
    service.list('ws-1').subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/expenses');
  });

  it('should call api.get with filters as query params', () => {
    service.list('ws-1', { from: '2024-01-01', to: '2024-01-31', page: 2 }).subscribe();
    expect(apiMock.get).toHaveBeenCalledWith(
      expect.stringContaining('/workspaces/ws-1/expenses?')
    );
  });

  it('should call api.get with correct path on getById()', () => {
    service.getById('ws-1', 'exp-1').subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/expenses/exp-1');
  });

  it('should call api.post with correct path on create()', () => {
    const payload = { amount: '100', date: '2024-01-01', category_id: 'cat-1', payment_method_id: 'pm-1', payment_type: 'cash' as const };
    service.create('ws-1', payload).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/expenses', payload);
  });

  it('should call api.put with correct path on update()', () => {
    const payload = { amount: '200' };
    service.update('ws-1', 'exp-1', payload).subscribe();
    expect(apiMock.put).toHaveBeenCalledWith('/workspaces/ws-1/expenses/exp-1', payload);
  });

  it('should call api.delete with correct path on delete()', () => {
    service.delete('ws-1', 'exp-1').subscribe();
    expect(apiMock.delete).toHaveBeenCalledWith('/workspaces/ws-1/expenses/exp-1');
  });
});
