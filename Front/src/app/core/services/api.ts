import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiRequestOptions, ApiServiceInterface } from '../tokens/api-service.token';

@Injectable({
  providedIn: 'root'
})
export class ApiService implements ApiServiceInterface {
  private readonly http = inject(HttpClient);

  get<T>(path: string, options?: ApiRequestOptions) {
    return options
      ? this.http.get<T>(`${environment.apiUrl}${path}`, options)
      : this.http.get<T>(`${environment.apiUrl}${path}`);
  }

  post<T>(path: string, body: unknown, options?: ApiRequestOptions) {
    return options
      ? this.http.post<T>(`${environment.apiUrl}${path}`, body, options)
      : this.http.post<T>(`${environment.apiUrl}${path}`, body);
  }

  put<T>(path: string, body: unknown, options?: ApiRequestOptions) {
    return options
      ? this.http.put<T>(`${environment.apiUrl}${path}`, body, options)
      : this.http.put<T>(`${environment.apiUrl}${path}`, body);
  }

  patch<T>(path: string, body: unknown, options?: ApiRequestOptions) {
    return options
      ? this.http.patch<T>(`${environment.apiUrl}${path}`, body, options)
      : this.http.patch<T>(`${environment.apiUrl}${path}`, body);
  }

  delete<T>(path: string, options?: ApiRequestOptions) {
    return options
      ? this.http.delete<T>(`${environment.apiUrl}${path}`, options)
      : this.http.delete<T>(`${environment.apiUrl}${path}`);
  }
}
