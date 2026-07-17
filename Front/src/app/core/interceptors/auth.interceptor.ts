import { inject } from '@angular/core';
import { HttpInterceptorFn } from '@angular/common/http';
import { STORAGE_SERVICE_TOKEN } from '../tokens/storage.token';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const storage = inject(STORAGE_SERVICE_TOKEN);
  const token = storage.getItem('token');
  const lang = storage.getItem('app_lang') ?? 'es';

  let headers = req.headers.set('Accept-Language', lang);

  if (token) {
    headers = headers.set('Authorization', `Bearer ${token}`);
  }

  const authReq = req.clone({ headers });

  return next(authReq);
};