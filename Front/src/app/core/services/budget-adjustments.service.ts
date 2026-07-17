import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiRequestOptions } from '../tokens/api-service.token';
import { ApiService } from './api';
import { apiGet, apiPost } from './api-call';
import {
  AvailableCategoriesResponse,
  BudgetAdjustmentCreatePayload,
  BudgetAdjustmentItemResponse,
  BudgetAdjustmentListResponse,
} from '../models/budget-adjustment.model';

@Injectable({ providedIn: 'root' })
export class BudgetAdjustmentsService {
  private readonly api = inject(ApiService);

  list(workspaceId: string, month?: string, categoryId?: string): Observable<BudgetAdjustmentListResponse> {
    const params = new URLSearchParams();
    if (month) params.set('month', month);
    if (categoryId) params.set('category_id', categoryId);
    const qs = params.toString() ? `?${params.toString()}` : '';
    return this.api.get<BudgetAdjustmentListResponse>(`/workspaces/${workspaceId}/budget-adjustments${qs}`);
  }

  create(
    workspaceId: string,
    payload: BudgetAdjustmentCreatePayload,
    options?: ApiRequestOptions,
  ): Observable<BudgetAdjustmentItemResponse> {
    return apiPost<BudgetAdjustmentItemResponse>(
      this.api,
      `/workspaces/${workspaceId}/budget-adjustments`,
      payload,
      options,
    );
  }

  delete(workspaceId: string, adjustmentId: string): Observable<void> {
    return this.api.delete<void>(`/workspaces/${workspaceId}/budget-adjustments/${adjustmentId}`);
  }

  available(
    workspaceId: string,
    month: string,
    excludeCategoryId?: string,
    options?: ApiRequestOptions,
  ): Observable<AvailableCategoriesResponse> {
    let qs = `?month=${month}`;
    if (excludeCategoryId) {
      qs += `&exclude_category_id=${excludeCategoryId}`;
    }
    return apiGet<AvailableCategoriesResponse>(
      this.api,
      `/workspaces/${workspaceId}/budget-adjustments/available${qs}`,
      options,
    );
  }
}