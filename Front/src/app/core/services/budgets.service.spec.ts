import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { BudgetsService } from './budgets.service';

describe('BudgetsService', () => {
  let service: BudgetsService;
  let apiMock: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    apiMock = {
      get: vi.fn(() => of({ data: [] })),
      post: vi.fn(() => of({ data: { id: 'b-1' } })),
      put: vi.fn(() => of({ data: { id: 'b-1' } })),
      delete: vi.fn(() => of(undefined)),
    };

    TestBed.configureTestingModule({
      providers: [BudgetsService, { provide: API_SERVICE_TOKEN, useValue: apiMock }],
    });

    service = TestBed.inject(BudgetsService);
  });

  it('lists budgets for a workspace', () => {
    service.list('ws-1').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/budgets');
  });

  it('creates a budget for a workspace', () => {
    const payload = { amount: '100', alert_threshold: 80, alert_enabled: true };
    service.create('ws-1', payload).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/budgets', payload);
  });

  it('requests budget status with optional month filter', () => {
    service.status('ws-1', '2026-06').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/budgets-status?month=2026-06');
  });

  it('deletes a budget by id', () => {
    service.delete('ws-1', 'b-1').subscribe();

    expect(apiMock.delete).toHaveBeenCalledWith('/workspaces/ws-1/budgets/b-1');
  });
});
