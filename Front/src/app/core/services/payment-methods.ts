import { inject, Injectable } from '@angular/core';
import { Observable, Subject, tap } from 'rxjs';
import { API_SERVICE_TOKEN, ApiRequestOptions } from '../tokens/api-service.token';
import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './api-call';
import {
  Card,
  OtherPaymentMethod,
  UserPaymentMethodSummary,
  WorkspacePaymentMethodBulkResult,
  WorkspacePaymentMethodCreatePayload,
  WorkspacePaymentMethodSummary,
} from '../models/payment-method.model';

export interface UserPaymentMethodListResponse {
  data: UserPaymentMethodSummary[];
}

export interface UserPaymentMethodItemResponse {
  data: UserPaymentMethodSummary;
}

export interface WorkspacePaymentMethodListResponse {
  data: WorkspacePaymentMethodSummary[];
}

export interface WorkspacePaymentMethodItemResponse {
  data: WorkspacePaymentMethodSummary;
}

export interface WorkspacePaymentMethodCreatedEvent {
  workspaceId: string;
  method?: WorkspacePaymentMethodSummary | Card | OtherPaymentMethod | UserPaymentMethodSummary;
}

@Injectable({ providedIn: 'root' })
export class PaymentMethodsService {
  private readonly api = inject(API_SERVICE_TOKEN);
  private readonly paymentMethodCreatedSubject = new Subject<WorkspacePaymentMethodCreatedEvent>();

  readonly paymentMethodCreated$ = this.paymentMethodCreatedSubject.asObservable();

  notifyCreated(workspaceId: string, method?: WorkspacePaymentMethodCreatedEvent['method']): void {
    queueMicrotask(() => {
      this.paymentMethodCreatedSubject.next({
        workspaceId,
        method,
      });
    });
  }

  // ---------------------------------------------------------------------
  // User-scoped endpoints (/api/v1/user/payment-methods)
  // ---------------------------------------------------------------------

  listMine(options?: ApiRequestOptions): Observable<UserPaymentMethodListResponse> {
    return apiGet<UserPaymentMethodListResponse>(this.api, '/user/payment-methods', options);
  }

  createMine(
    payload: WorkspacePaymentMethodCreatePayload,
    options?: ApiRequestOptions,
  ): Observable<UserPaymentMethodItemResponse> {
    return apiPost<UserPaymentMethodItemResponse>(this.api, '/user/payment-methods', payload, options)
      .pipe(tap(({ data }) => this.notifyCreated('', data)));
  }

  deleteMine(methodId: string, options?: ApiRequestOptions): Observable<void> {
    return apiDelete<void>(this.api, `/user/payment-methods/${methodId}`, options);
  }

  updateMine(
    methodId: string,
    payload: WorkspacePaymentMethodCreatePayload,
    options?: ApiRequestOptions,
  ): Observable<UserPaymentMethodItemResponse> {
    return apiPut<UserPaymentMethodItemResponse>(
      this.api,
      `/user/payment-methods/${methodId}`,
      payload,
      options,
    );
  }

  updateWorkspaces(
    methodId: string,
    workspaceIds: string[],
    options?: ApiRequestOptions,
  ): Observable<UserPaymentMethodItemResponse> {
    return apiPatch<UserPaymentMethodItemResponse>(
      this.api,
      `/user/payment-methods/${methodId}/workspaces`,
      {
        workspace_ids: workspaceIds,
      },
      options,
    );
  }

  // ---------------------------------------------------------------------
  // Workspace-scoped endpoints (/api/v1/workspaces/{id}/payment-methods)
  // Kept because other features (expense-form, fixed-expense-form, etc.)
  // legitimately use the workspace scope for the payment method picker
  // and bulk linking.
  // ---------------------------------------------------------------------

  listWorkspace(workspaceId: string): Observable<WorkspacePaymentMethodListResponse> {
    return this.api.get<WorkspacePaymentMethodListResponse>(
      `/workspaces/${workspaceId}/payment-methods`,
    );
  }

  create(
    workspaceId: string,
    payload: WorkspacePaymentMethodCreatePayload,
  ): Observable<WorkspacePaymentMethodItemResponse> {
    return this.api
      .post<WorkspacePaymentMethodItemResponse>(
        `/workspaces/${workspaceId}/payment-methods`,
        payload,
      )
      .pipe(
        tap(({ data }) => {
          this.notifyCreated(workspaceId, data);
        }),
      );
  }

  updateLink(workspaceId: string, methodId: string, isLinked: boolean): Observable<void> {
    return this.api.patch<void>(`/workspaces/${workspaceId}/payment-methods/${methodId}/link`, {
      is_linked: isLinked,
    });
  }

  bulkLinking(
    workspaceId: string,
    isLinked: boolean,
  ): Observable<WorkspacePaymentMethodBulkResult> {
    return this.api.post<WorkspacePaymentMethodBulkResult>(
      `/workspaces/${workspaceId}/payment-methods/link-bulk`,
      {
        operation: isLinked ? 'link_all' : 'unlink_all',
      },
    );
  }

  listValid(workspaceId: string): Observable<WorkspacePaymentMethodListResponse> {
    return this.api.get<WorkspacePaymentMethodListResponse>(
      `/workspaces/${workspaceId}/payment-methods/valid`,
    );
  }
}
