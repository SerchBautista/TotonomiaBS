import { inject, Injectable } from '@angular/core';
import { Observable, Subject, tap } from 'rxjs';
import { API_SERVICE_TOKEN, ApiRequestOptions } from '../tokens/api-service.token';
import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './api-call';
import { Category, CategoryCreatePayload, CategoryUpdatePayload } from '../models/category.model';

export interface CategoryListResponse {
  data: Category[];
}

export interface CategoryItemResponse {
  data: Category;
}

export interface CategorySharingBulkResult {
  processed: number;
  blocked: number;
  processed_category_ids: string[];
  blocked_category_ids: string[];
}

export interface CategoryCreatedEvent {
  workspaceId: string;
  category: Category;
}

export interface UpdateCategoryWorkspacesPayload {
  workspace_ids: string[];
}

@Injectable({ providedIn: 'root' })
export class CategoriesService {
  private readonly api = inject(API_SERVICE_TOKEN);
  private readonly categoryCreatedSubject = new Subject<CategoryCreatedEvent>();

  readonly categoryCreated$ = this.categoryCreatedSubject.asObservable();

  // ---------------------------------------------------------------------
  // User-scoped endpoints (/api/v1/user/categories)
  // ---------------------------------------------------------------------

  listMine(): Observable<CategoryListResponse> {
    return this.api.get<CategoryListResponse>('/user/categories');
  }

  createMine(
    payload: CategoryCreatePayload,
    options?: ApiRequestOptions,
  ): Observable<CategoryItemResponse> {
    return apiPost<CategoryItemResponse>(this.api, '/user/categories', payload, options).pipe(
      tap(({ data }) => {
        const workspaceIds = Array.from(
          new Set((payload.workspace_ids ?? []).filter((workspaceId): workspaceId is string => !!workspaceId)),
        );

        queueMicrotask(() => {
          workspaceIds.forEach((workspaceId) => {
            this.categoryCreatedSubject.next({
              workspaceId,
              category: data,
            });
          });
        });
      }),
    );
  }

  updateMine(categoryId: string, payload: CategoryUpdatePayload): Observable<CategoryItemResponse> {
    return this.api.put<CategoryItemResponse>(`/user/categories/${categoryId}`, payload);
  }

  updateWorkspaces(
    categoryId: string,
    workspaceIds: string[],
  ): Observable<CategoryItemResponse> {
    return this.api.patch<CategoryItemResponse>(`/user/categories/${categoryId}/workspaces`, {
      workspace_ids: workspaceIds,
    });
  }

  deleteMine(categoryId: string, options?: ApiRequestOptions): Observable<{ message: string }> {
    return apiDelete<{ message: string }>(this.api, `/user/categories/${categoryId}`, options);
  }

  setAsDefaultMine(categoryId: string): Observable<CategoryItemResponse> {
    return this.api.patch<CategoryItemResponse>(`/user/categories/${categoryId}/default`, {});
  }

  // ---------------------------------------------------------------------
  // Workspace-scoped endpoints (/api/v1/workspaces/{id}/categories)
  // Kept because other features (expense-form, fixed-expense-form, etc.)
  // legitimately use the workspace scope for the category picker.
  // ---------------------------------------------------------------------

  list(workspaceId: string): Observable<CategoryListResponse> {
    return this.api.get<CategoryListResponse>(`/workspaces/${workspaceId}/categories`);
  }

  listValid(workspaceId: string): Observable<CategoryListResponse> {
    return this.api.get<CategoryListResponse>(`/workspaces/${workspaceId}/categories/valid`);
  }

  create(workspaceId: string, payload: CategoryCreatePayload): Observable<CategoryItemResponse> {
    return this.api
      .post<CategoryItemResponse>(`/workspaces/${workspaceId}/categories`, payload)
      .pipe(
        tap(({ data }) => {
          queueMicrotask(() => {
            this.categoryCreatedSubject.next({
              workspaceId,
              category: data,
            });
          });
        }),
      );
  }

  update(
    workspaceId: string,
    categoryId: string,
    payload: CategoryUpdatePayload,
  ): Observable<CategoryItemResponse> {
    return this.api.put<CategoryItemResponse>(
      `/workspaces/${workspaceId}/categories/${categoryId}`,
      payload,
    );
  }

  delete(workspaceId: string, categoryId: string): Observable<{ message: string }> {
    return this.api.delete<{ message: string }>(
      `/workspaces/${workspaceId}/categories/${categoryId}`,
    );
  }

  assignCategory(workspaceId: string, categoryId: string): Observable<void> {
    return this.api.post<void>(`/workspaces/${workspaceId}/categories/${categoryId}/assign`, {});
  }

  unassignCategory(workspaceId: string, categoryId: string): Observable<void> {
    return this.api.delete<void>(`/workspaces/${workspaceId}/categories/${categoryId}/assign`);
  }

  setDefault(workspaceId: string, categoryId: string): Observable<CategoryItemResponse> {
    return this.api.patch<CategoryItemResponse>(
      `/workspaces/${workspaceId}/categories/${categoryId}/default`,
      {},
    );
  }

  updateLink(workspaceId: string, categoryId: string, isLinked: boolean): Observable<void> {
    return this.api.patch<void>(`/workspaces/${workspaceId}/categories/${categoryId}/link`, {
      is_linked: isLinked,
    });
  }

  updateActivation(workspaceId: string, categoryId: string, isActive: boolean): Observable<void> {
    return this.api.patch<void>(`/workspaces/${workspaceId}/categories/${categoryId}/activation`, {
      is_active: isActive,
    });
  }

  bulkLinking(workspaceId: string, isLinked: boolean): Observable<CategorySharingBulkResult> {
    return this.api.post<CategorySharingBulkResult>(
      `/workspaces/${workspaceId}/categories/link-bulk`,
      {
        operation: isLinked ? 'link_all' : 'unlink_all',
      },
    );
  }
}
