import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { of } from 'rxjs';
import { OccurrencesService } from './occurrences';
import { ApiService } from './api';

describe('OccurrencesService', () => {
  let service: OccurrencesService;
  let apiMock: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn>; put: ReturnType<typeof vi.fn>; delete: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    apiMock = {
      get: vi.fn().mockReturnValue(of({ data: [] })),
      post: vi.fn().mockReturnValue(of({ data: {} })),
      put: vi.fn().mockReturnValue(of({})),
      delete: vi.fn().mockReturnValue(of({})),
    };

    TestBed.configureTestingModule({
      providers: [
        OccurrencesService,
        { provide: ApiService, useValue: apiMock },
      ],
    });

    service = TestBed.inject(OccurrencesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('should call api.get with correct path on list()', () => {
    service.list('ws-1').subscribe();
    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/occurrences');
  });

  it('should call api.post with correct path on pay()', () => {
    const payload = {
      amount: '200.00',
      payment_type: 'cash' as const,
      paid_at: '2026-03-25',
    };
    service.pay('occ-1', payload).subscribe();
    expect(apiMock.post).toHaveBeenCalledWith('/occurrences/occ-1/pay', payload);
  });
});
