import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api';
import { HeatmapResponse, MemberSplitResponse, ProjectionResponse, SummaryResponse } from '../models/analytics.model';

@Injectable({ providedIn: 'root' })
export class AnalyticsService {
  private readonly api = inject(ApiService);

  heatmap(workspaceId: string, year?: number, month?: number): Observable<HeatmapResponse> {
    const query = new URLSearchParams();
    if (year) query.set('year', String(year));
    if (month) query.set('month', String(month));
    const qs = query.size > 0 ? `?${query.toString()}` : '';
    return this.api.get<HeatmapResponse>(`/workspaces/${workspaceId}/analytics/heatmap${qs}`);
  }

  projection(workspaceId: string): Observable<ProjectionResponse> {
    return this.api.get<ProjectionResponse>(`/workspaces/${workspaceId}/analytics/projection`);
  }

  memberSplit(workspaceId: string, year?: number, month?: number): Observable<MemberSplitResponse> {
    const query = new URLSearchParams();
    if (year) query.set('year', String(year));
    if (month) query.set('month', String(month));
    const qs = query.size > 0 ? `?${query.toString()}` : '';
    return this.api.get<MemberSplitResponse>(`/workspaces/${workspaceId}/analytics/member-split${qs}`);
  }

  summary(workspaceId: string, from?: string, to?: string): Observable<SummaryResponse> {
    const query = new URLSearchParams();
    if (from) query.set('from', from);
    if (to) query.set('to', to);
    const qs = query.size > 0 ? `?${query.toString()}` : '';
    return this.api.get<SummaryResponse>(`/workspaces/${workspaceId}/analytics/summary${qs}`);
  }
}
