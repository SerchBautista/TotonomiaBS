import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { of } from 'rxjs';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { FixedExpensesService } from './fixed-expenses';

describe('FixedExpensesService', () => {
  let service: FixedExpensesService;
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
        FixedExpensesService,
        { provide: API_SERVICE_TOKEN, useValue: apiMock },
      ],
    });

    service = TestBed.inject(FixedExpensesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('should call api.get with correct path on list()', () => {
    service.list('ws-1').subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/fixed-expenses');
  });

  it('should call api.post with correct path on create()', () => {
    const payload = {
      amount: '50',
      description: 'Netflix',
      frequency: 'monthly' as const,
      next_due_date: '2024-02-01',
      category_id: 'cat-1',
      payment_method_id: 'pm-1',
      payment_type: 'cash' as const,
    };
    service.create('ws-1', payload).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/fixed-expenses', payload);
  });

  it('should call api.delete with correct path on delete()', () => {
    service.delete('ws-1', 'fe-1').subscribe();
    expect(apiMock.delete).toHaveBeenCalledWith('/workspaces/ws-1/fixed-expenses/fe-1');
  });
});
