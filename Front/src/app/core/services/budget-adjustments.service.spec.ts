import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { BudgetAdjustment } from '../models/budget-adjustment.model';
import { ApiService } from './api';
import { BudgetAdjustmentsService } from './budget-adjustments.service';

describe('BudgetAdjustmentsService', () => {
  let service: BudgetAdjustmentsService;
  let apiMock: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    apiMock = {
      get: vi.fn(() => of({ data: [] })),
      post: vi.fn(() => of({ data: { id: 'adj-1' } })),
      delete: vi.fn(() => of(undefined)),
    };

    TestBed.configureTestingModule({
      providers: [
        BudgetAdjustmentsService,
        { provide: ApiService, useValue: apiMock },
      ],
    });

    service = TestBed.inject(BudgetAdjustmentsService);
  });

  it('lists adjustments with optional month and category filters', () => {
    service.list('ws-1', '2026-06', 'cat-1').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith(
      '/workspaces/ws-1/budget-adjustments?month=2026-06&category_id=cat-1',
    );
  });

  it('creates an adjustment through the API', () => {
    const payload = {
      month: '2026-06',
      from_category_id: 'cat-1',
      to_category_id: 'cat-2',
      amount: '50',
      reason: 'Rebalance',
    };
    service.create('ws-1', payload).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/budget-adjustments', payload);
  });

  it('loads available categories for adjustments', () => {
    service.available('ws-1', '2026-06', 'cat-1').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith(
      '/workspaces/ws-1/budget-adjustments/available?month=2026-06&exclude_category_id=cat-1',
    );
  });
});
