import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { Expense, ExpenseCreatePayload, ExpenseFilters, ExpenseUpdatePayload } from '../models/expense.model';
import { BudgetWarning } from '../models/budget.model';

export interface ExpenseListResponse {
  data: Expense[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ExpenseItemResponse {
  data: Expense;
}

export interface ExpenseCreateResponse {
  data: Expense & { budget_warnings?: BudgetWarning[] };
}

@Injectable({ providedIn: 'root' })
export class ExpensesService {
  private readonly api = inject(API_SERVICE_TOKEN);

  list(workspaceId: string, filters: ExpenseFilters = {}): Observable<ExpenseListResponse> {
    const query = new URLSearchParams();
    if (filters.from) query.set('from', filters.from);
    if (filters.to) query.set('to', filters.to);
    if (filters.category_id) query.set('category_id', filters.category_id);
    if (filters.payment_type) query.set('payment_type', filters.payment_type);
    if (filters.search) query.set('search', filters.search);
    if (filters.page) query.set('page', String(filters.page));
    const qs = query.size > 0 ? `?${query.toString()}` : '';
    return this.api.get<ExpenseListResponse>(`/workspaces/${workspaceId}/expenses${qs}`);
  }

  getById(workspaceId: string, expenseId: string): Observable<ExpenseItemResponse> {
    return this.api.get<ExpenseItemResponse>(`/workspaces/${workspaceId}/expenses/${expenseId}`);
  }

  create(workspaceId: string, payload: ExpenseCreatePayload): Observable<ExpenseCreateResponse> {
    return this.api.post<ExpenseCreateResponse>(`/workspaces/${workspaceId}/expenses`, payload);
  }

  update(workspaceId: string, expenseId: string, payload: ExpenseUpdatePayload): Observable<ExpenseItemResponse> {
    return this.api.put<ExpenseItemResponse>(`/workspaces/${workspaceId}/expenses/${expenseId}`, payload);
  }

  delete(workspaceId: string, expenseId: string): Observable<{ message: string }> {
    return this.api.delete<{ message: string }>(`/workspaces/${workspaceId}/expenses/${expenseId}`);
  }

  total(workspaceId: string, filters: ExpenseFilters = {}): Observable<{ data: { total: string } }> {
    const query = new URLSearchParams();
    if (filters.from) query.set('from', filters.from);
    if (filters.to) query.set('to', filters.to);
    if (filters.category_id) query.set('category_id', filters.category_id);
    if (filters.payment_type) query.set('payment_type', filters.payment_type);
    if (filters.search) query.set('search', filters.search);
    const qs = query.size > 0 ? `?${query.toString()}` : '';
    return this.api.get<{ data: { total: string } }>(`/workspaces/${workspaceId}/expenses/total${qs}`);
  }
}
