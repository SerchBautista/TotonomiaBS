import { HttpContext } from '@angular/common/http';
import { InjectionToken } from '@angular/core';
import { Observable } from 'rxjs';

export interface ApiRequestOptions {
  context?: HttpContext;
}

export interface ApiServiceInterface {
  get<T>(path: string, options?: ApiRequestOptions): Observable<T>;
  post<T>(path: string, body: unknown, options?: ApiRequestOptions): Observable<T>;
  put<T>(path: string, body: unknown, options?: ApiRequestOptions): Observable<T>;
  patch<T>(path: string, body: unknown, options?: ApiRequestOptions): Observable<T>;
  delete<T>(path: string, options?: ApiRequestOptions): Observable<T>;
}

export const API_SERVICE_TOKEN = new InjectionToken<ApiServiceInterface>('ApiServiceInterface');
