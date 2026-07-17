import { inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api';

export type CrudQueryValue = string | number | boolean | null | undefined;
export type CrudQueryParams = Record<string, CrudQueryValue>;

export interface CrudItemResponse<TItem> {
  message: string;
  data: {
    item: TItem;
  };
}

export abstract class CrudApiService<TItem, TCreatePayload, TUpdatePayload> {
  protected readonly api = inject(ApiService);
  protected abstract readonly resourcePath: string;

  getById(id: string): Observable<CrudItemResponse<TItem>> {
    return this.api.get<CrudItemResponse<TItem>>(`${this.resourcePath}/${id}`);
  }

  create(payload: TCreatePayload): Observable<CrudItemResponse<TItem>> {
    return this.api.post<CrudItemResponse<TItem>>(this.resourcePath, this.serializeCreatePayload(payload));
  }

  update(id: string, payload: TUpdatePayload): Observable<CrudItemResponse<TItem>> {
    return this.sendUpdateRequest(`${this.resourcePath}/${id}`, this.serializeUpdatePayload(payload));
  }

  delete(id: string): Observable<{ message: string }> {
    return this.api.delete<{ message: string }>(`${this.resourcePath}/${id}`);
  }

  protected listRequest<TResponse>(params: CrudQueryParams): Observable<TResponse> {
    const query = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
      if (value === null || value === undefined) {
        continue;
      }
      query.set(key, String(value));
    }

    const path = query.size > 0 ? `${this.resourcePath}?${query.toString()}` : this.resourcePath;
    return this.api.get<TResponse>(path);
  }

  protected serializeCreatePayload(payload: TCreatePayload): unknown {
    return payload;
  }

  protected serializeUpdatePayload(payload: TUpdatePayload): unknown {
    return payload;
  }

  protected sendUpdateRequest(path: string, payload: unknown): Observable<CrudItemResponse<TItem>> {
    return this.api.put<CrudItemResponse<TItem>>(path, payload);
  }
}
