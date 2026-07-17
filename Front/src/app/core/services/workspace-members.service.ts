import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiRequestOptions } from '../tokens/api-service.token';
import { ApiService } from './api';
import { apiDelete, apiGet, apiPost, apiPut } from './api-call';
import { InviteMemberPayload, UpdateMemberPayload, WorkspaceMember } from '../models/workspace.model';

export interface WorkspaceMemberListResponse {
  data: WorkspaceMember[];
}

export interface WorkspaceMemberItemResponse {
  data: WorkspaceMember;
}

@Injectable({ providedIn: 'root' })
export class WorkspaceMembersService {
  private readonly api = inject(ApiService);

  list(workspaceId: string, options?: ApiRequestOptions): Observable<WorkspaceMemberListResponse> {
    return apiGet<WorkspaceMemberListResponse>(this.api, `/workspaces/${workspaceId}/members`, options);
  }

  invite(
    workspaceId: string,
    payload: InviteMemberPayload,
    options?: ApiRequestOptions,
  ): Observable<WorkspaceMemberItemResponse> {
    return apiPost<WorkspaceMemberItemResponse>(
      this.api,
      `/workspaces/${workspaceId}/members`,
      payload,
      options,
    );
  }

  updateMember(
    workspaceId: string,
    userId: string,
    payload: UpdateMemberPayload,
    options?: ApiRequestOptions,
  ): Observable<WorkspaceMemberItemResponse> {
    return apiPut<WorkspaceMemberItemResponse>(
      this.api,
      `/workspaces/${workspaceId}/members/${userId}`,
      payload,
      options,
    );
  }

  remove(workspaceId: string, userId: string, options?: ApiRequestOptions): Observable<void> {
    return apiDelete<void>(this.api, `/workspaces/${workspaceId}/members/${userId}`, options);
  }
}
