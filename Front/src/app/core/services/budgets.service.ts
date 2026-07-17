import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { Budget, BudgetCreatePayload, BudgetStatusResponse, BudgetUpdatePayload } from '../models/budget.model';

export interface BudgetListResponse {
  data: Budget[];
}

export interface BudgetItemResponse {
  data: Budget;
}

export interface BudgetStatusApiResponse {
  data: BudgetStatusResponse;
}

@Injectable({ providedIn: 'root' })
export class BudgetsService {
  private readonly api = inject(API_SERVICE_TOKEN);

  list(workspaceId: string): Observable<BudgetListResponse> {
    return this.api.get<BudgetListResponse>(`/workspaces/${workspaceId}/budgets`);
  }

  create(workspaceId: string, payload: BudgetCreatePayload): Observable<BudgetItemResponse> {
    return this.api.post<BudgetItemResponse>(`/workspaces/${workspaceId}/budgets`, payload);
  }

  update(workspaceId: string, budgetId: string, payload: BudgetUpdatePayload): Observable<BudgetItemResponse> {
    return this.api.put<BudgetItemResponse>(`/workspaces/${workspaceId}/budgets/${budgetId}`, payload);
  }

  delete(workspaceId: string, budgetId: string): Observable<void> {
    return this.api.delete<void>(`/workspaces/${workspaceId}/budgets/${budgetId}`);
  }

  status(workspaceId: string, month?: string): Observable<BudgetStatusApiResponse> {
    const qs = month ? `?month=${month}` : '';
    return this.api.get<BudgetStatusApiResponse>(`/workspaces/${workspaceId}/budgets-status${qs}`);
  }
}
