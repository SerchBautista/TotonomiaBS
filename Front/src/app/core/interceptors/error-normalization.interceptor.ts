import { HttpInterceptorFn } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import { ensureNormalizedBackendError } from '../errors/backend-error.mapper';

export const errorNormalizationInterceptor: HttpInterceptorFn = (req, next) => {
  return next(req).pipe(
    catchError((error) => {
      const normalizedError = ensureNormalizedBackendError(error);
      return throwError(() => normalizedError);
    })
  );
};
