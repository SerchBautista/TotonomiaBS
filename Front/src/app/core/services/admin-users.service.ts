import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiRequestOptions } from '../tokens/api-service.token';
import { apiGet, apiPost } from './api-call';
import { CrudApiService, CrudQueryValue } from './crud-api';
import {
  AdminUserDetail,
  AdminUserDetailResponse,
  AdminUserListItem,
  AdminUserListParams,
  AdminUserListResponse
} from '../models/admin-user.model';

@Injectable({
  providedIn: 'root'
})
export class AdminUsersService extends CrudApiService<
  AdminUserListItem,
  never,
  never
> {
  protected readonly resourcePath = '/admin/users';

  list(params: AdminUserListParams): Observable<AdminUserListResponse> {
    const queryParams: Record<string, CrudQueryValue> = {
      page: String(params.page),
      per_page: String(params.perPage),
      sort_by: params.sortBy,
      sort_dir: params.sortDir,
      search: params.search
    };

    if (params.plan) {
      queryParams['plan'] = params.plan;
    }

    if (params.emailVerified) {
      queryParams['email_verified'] = params.emailVerified;
    }

    return this.listRequest<AdminUserListResponse>(queryParams);
  }

  get(id: string, options?: ApiRequestOptions): Observable<AdminUserDetailResponse> {
    return apiGet<AdminUserDetailResponse>(this.api, `${this.resourcePath}/${id}`, options);
  }

  assignPlan(userId: string, plan: string, options?: ApiRequestOptions): Observable<AdminUserDetailResponse> {
    return apiPost<AdminUserDetailResponse>(
      this.api,
      `${this.resourcePath}/${userId}/plan`,
      { plan },
      options,
    );
  }
}
