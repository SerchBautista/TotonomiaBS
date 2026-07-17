import { Observable } from 'rxjs';
import { ApiRequestOptions, ApiServiceInterface } from '../tokens/api-service.token';

export function apiGet<T>(api: ApiServiceInterface, path: string, options?: ApiRequestOptions): Observable<T> {
  return options !== undefined ? api.get<T>(path, options) : api.get<T>(path);
}

export function apiPost<T>(
  api: ApiServiceInterface,
  path: string,
  body: unknown,
  options?: ApiRequestOptions,
): Observable<T> {
  return options !== undefined ? api.post<T>(path, body, options) : api.post<T>(path, body);
}

export function apiPut<T>(
  api: ApiServiceInterface,
  path: string,
  body: unknown,
  options?: ApiRequestOptions,
): Observable<T> {
  return options !== undefined ? api.put<T>(path, body, options) : api.put<T>(path, body);
}

export function apiPatch<T>(
  api: ApiServiceInterface,
  path: string,
  body: unknown,
  options?: ApiRequestOptions,
): Observable<T> {
  return options !== undefined ? api.patch<T>(path, body, options) : api.patch<T>(path, body);
}

export function apiDelete<T>(api: ApiServiceInterface, path: string, options?: ApiRequestOptions): Observable<T> {
  return options !== undefined ? api.delete<T>(path, options) : api.delete<T>(path);
}
