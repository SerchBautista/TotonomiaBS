import { inject } from '@angular/core';
import { HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { STORAGE_SERVICE_TOKEN } from '../tokens/storage.token';

export const langParamInterceptor: HttpInterceptorFn = (req, next) => {
  const storage = inject(STORAGE_SERVICE_TOKEN);
  const language = storage.getItem('app_lang') ?? 'es';

  if (!req.url.startsWith(environment.apiUrl) || req.params.has('lang')) {
    return next(req);
  }

  return next(
    req.clone({
      params: req.params.set('lang', language)
    })
  );
};
