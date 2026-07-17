import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiRequestOptions } from '../tokens/api-service.token';
import { ApiService } from './api';
import { apiPost } from './api-call';
import { OtherPaymentMethod, OtherPaymentMethodCreatePayload, OtherPaymentMethodUpdatePayload } from '../models/payment-method.model';

export interface OtherPaymentMethodListResponse {
  data: OtherPaymentMethod[];
}

export interface OtherPaymentMethodItemResponse {
  data: OtherPaymentMethod;
}

@Injectable({ providedIn: 'root' })
export class OtherPaymentMethodsService {
  private readonly api = inject(ApiService);

  list(workspaceId: string): Observable<OtherPaymentMethodListResponse> {
    return this.api.get<OtherPaymentMethodListResponse>(`/workspaces/${workspaceId}/other-payment-methods`);
  }

  create(
    workspaceId: string,
    payload: OtherPaymentMethodCreatePayload,
    options?: ApiRequestOptions,
  ): Observable<OtherPaymentMethodItemResponse> {
    return apiPost<OtherPaymentMethodItemResponse>(
      this.api,
      `/workspaces/${workspaceId}/other-payment-methods`,
      payload,
      options,
    );
  }

  update(
    workspaceId: string,
    methodId: string,
    payload: OtherPaymentMethodUpdatePayload,
  ): Observable<OtherPaymentMethodItemResponse> {
    return this.api.put<OtherPaymentMethodItemResponse>(`/workspaces/${workspaceId}/other-payment-methods/${methodId}`, payload);
  }

  delete(workspaceId: string, methodId: string): Observable<void> {
    return this.api.delete<void>(`/workspaces/${workspaceId}/other-payment-methods/${methodId}`);
  }

  setDefault(workspaceId: string, methodId: string): Observable<OtherPaymentMethodItemResponse> {
    return this.api.patch<OtherPaymentMethodItemResponse>(`/workspaces/${workspaceId}/other-payment-methods/${methodId}/default`, {});
  }
}
