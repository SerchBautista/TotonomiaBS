import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiRequestOptions } from '../tokens/api-service.token';
import { ApiService } from './api';
import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from './api-call';
import { Card, CardCreatePayload, CardUpdatePayload } from '../models/payment-method.model';

export interface CardListResponse {
  data: Card[];
}

export interface CardItemResponse {
  data: Card;
}

@Injectable({ providedIn: 'root' })
export class CardsService {
  private readonly api = inject(ApiService);

  list(workspaceId: string): Observable<CardListResponse> {
    return this.api.get<CardListResponse>(`/workspaces/${workspaceId}/cards`);
  }

  create(
    workspaceId: string,
    payload: CardCreatePayload,
    options?: ApiRequestOptions,
  ): Observable<CardItemResponse> {
    return apiPost<CardItemResponse>(this.api, `/workspaces/${workspaceId}/cards`, payload, options);
  }

  update(
    workspaceId: string,
    cardId: string,
    payload: CardUpdatePayload,
  ): Observable<CardItemResponse> {
    return this.api.put<CardItemResponse>(`/workspaces/${workspaceId}/cards/${cardId}`, payload);
  }

  delete(workspaceId: string, cardId: string): Observable<void> {
    return this.api.delete<void>(`/workspaces/${workspaceId}/cards/${cardId}`);
  }

  setDefault(workspaceId: string, cardId: string): Observable<CardItemResponse> {
    return this.api.patch<CardItemResponse>(`/workspaces/${workspaceId}/cards/${cardId}/default`, {});
  }
}
