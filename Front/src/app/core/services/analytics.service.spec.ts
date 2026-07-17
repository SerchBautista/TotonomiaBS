import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { ApiService } from './api';
import { AnalyticsService } from './analytics.service';

describe('AnalyticsService', () => {
  let service: AnalyticsService;
  let apiMock: { get: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    apiMock = {
      get: vi.fn(() => of({ data: {} })),
    };

    TestBed.configureTestingModule({
      providers: [AnalyticsService, { provide: ApiService, useValue: apiMock }],
    });

    service = TestBed.inject(AnalyticsService);
  });

  it('loads projection data for a workspace', () => {
    service.projection('ws-1').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/analytics/projection');
  });

  it('loads heatmap data with optional year and month filters', () => {
    service.heatmap('ws-1', 2026, 6).subscribe();

    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/analytics/heatmap?year=2026&month=6');
  });

  it('loads summary data with optional date range', () => {
    service.summary('ws-1', '2026-06-01', '2026-06-30').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith(
      '/workspaces/ws-1/analytics/summary?from=2026-06-01&to=2026-06-30',
    );
  });
});
