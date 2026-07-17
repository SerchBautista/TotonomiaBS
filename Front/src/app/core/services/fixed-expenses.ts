import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { FixedExpense, FixedExpenseCreatePayload, FixedExpenseUpdatePayload } from '../models/fixed-expense.model';

export interface FixedExpenseListResponse {
  data: FixedExpense[];
}

export interface FixedExpenseItemResponse {
  data: FixedExpense;
}

@Injectable({ providedIn: 'root' })
export class FixedExpensesService {
  private readonly api = inject(API_SERVICE_TOKEN);

  list(workspaceId: string): Observable<FixedExpenseListResponse> {
    return this.api.get<FixedExpenseListResponse>(`/workspaces/${workspaceId}/fixed-expenses`);
  }

  create(workspaceId: string, payload: FixedExpenseCreatePayload): Observable<FixedExpenseItemResponse> {
    return this.api.post<FixedExpenseItemResponse>(`/workspaces/${workspaceId}/fixed-expenses`, payload);
  }

  update(workspaceId: string, fixedExpenseId: string, payload: FixedExpenseUpdatePayload): Observable<FixedExpenseItemResponse> {
    return this.api.put<FixedExpenseItemResponse>(`/workspaces/${workspaceId}/fixed-expenses/${fixedExpenseId}`, payload);
  }

  delete(workspaceId: string, fixedExpenseId: string): Observable<{ message: string }> {
    return this.api.delete<{ message: string }>(`/workspaces/${workspaceId}/fixed-expenses/${fixedExpenseId}`);
  }
}
