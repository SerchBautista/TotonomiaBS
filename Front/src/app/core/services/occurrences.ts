import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api';
import { FixedExpenseOccurrence, PayOccurrencePayload } from '../models/fixed-expense.model';
import { Expense } from '../models/expense.model';

export interface OccurrenceListResponse {
  data: FixedExpenseOccurrence[];
}

export interface PayOccurrenceResponse {
  data: Expense;
}

@Injectable({ providedIn: 'root' })
export class OccurrencesService {
  private readonly api = inject(ApiService);

  list(workspaceId: string): Observable<OccurrenceListResponse> {
    return this.api.get<OccurrenceListResponse>(`/workspaces/${workspaceId}/occurrences`);
  }

  pay(occurrenceId: string, payload: PayOccurrencePayload): Observable<PayOccurrenceResponse> {
    return this.api.post<PayOccurrenceResponse>(`/occurrences/${occurrenceId}/pay`, payload);
  }
}
