import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { CrudApiService } from './crud-api';

export interface AdministratorItem {
  id: string;
  name: string;
  email: string;
  roles: string[];
  direct_permissions: string[];
  permissions: string[];
  created_at: string;
  updated_at: string;
}

export interface AdministratorListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  sort_by: string;
  sort_dir: 'asc' | 'desc';
  search: string;
}

export interface AdministratorListResponse {
  message: string;
  data: {
    items: AdministratorItem[];
  };
  meta: AdministratorListMeta;
}

export interface AdministratorOptionsResponse {
  message: string;
  data: {
    roles: string[];
    permissions: string[];
  };
}

type AdministratorWritePayload = {
  name: string;
  email: string;
  password: string | null;
  password_confirmation: string | null;
  roles: string[];
  permissions: string[];
};

@Injectable({
  providedIn: 'root'
})
export class AdminAdministratorsService extends CrudApiService<
  AdministratorItem,
  AdministratorWritePayload,
  AdministratorWritePayload
> {
  protected readonly resourcePath = '/admin/administrators';

  list(params: {
    page: number;
    perPage: number;
    sortBy: string;
    sortDir: 'asc' | 'desc';
    search: string;
  }): Observable<AdministratorListResponse> {
    return this.listRequest<AdministratorListResponse>({
      page: String(params.page),
      per_page: String(params.perPage),
      sort_by: params.sortBy,
      sort_dir: params.sortDir,
      search: params.search
    });
  }

  options(): Observable<AdministratorOptionsResponse> {
    return this.api.get<AdministratorOptionsResponse>('/admin/administrators/options');
  }
}
