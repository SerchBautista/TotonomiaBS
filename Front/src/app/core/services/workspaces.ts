import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiRequestOptions } from '../tokens/api-service.token';
import { ApiService } from './api';
import { apiGet } from './api-call';
import { User } from '../models/user.model';
import { Workspace, WorkspaceCreatePayload, WorkspaceUpdatePayload } from '../models/workspace.model';

export interface UserItemResponse {
  data: User;
}

export interface WorkspaceListResponse {
  data: Workspace[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface WorkspaceItemResponse {
  data: Workspace;
}

@Injectable({ providedIn: 'root' })
export class WorkspacesService {
  private readonly api = inject(ApiService);

  list(params: { page?: number; per_page?: number } = {}): Observable<WorkspaceListResponse> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.per_page) query.set('per_page', String(params.per_page));
    const qs = query.size > 0 ? `?${query.toString()}` : '';
    return this.api.get<WorkspaceListResponse>(`/workspaces${qs}`);
  }

  getById(id: string, options?: ApiRequestOptions): Observable<WorkspaceItemResponse> {
    return apiGet<WorkspaceItemResponse>(this.api, `/workspaces/${id}`, options);
  }

  create(payload: WorkspaceCreatePayload): Observable<WorkspaceItemResponse> {
    return this.api.post<WorkspaceItemResponse>('/workspaces', payload);
  }

  update(id: string, payload: WorkspaceUpdatePayload): Observable<WorkspaceItemResponse> {
    return this.api.put<WorkspaceItemResponse>(`/workspaces/${id}`, payload);
  }

  delete(id: string): Observable<{ message: string }> {
    return this.api.delete<{ message: string }>(`/workspaces/${id}`);
  }

  setDefault(workspaceId: string): Observable<UserItemResponse> {
    return this.api.put<UserItemResponse>('/user/default-workspace', { workspace_id: workspaceId });
  }
}
