import { inject, Injector } from '@angular/core';
import { HttpInterceptorFn } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import { ensureNormalizedBackendError } from '../errors/backend-error.mapper';
import { GlobalErrorHandlerService } from '../errors/global-error-handler.service';
import { SKIP_GLOBAL_ERROR_HANDLER, SKIP_GLOBAL_ERROR_TOAST } from './http-context-tokens';
import { isTranslationAssetRequest } from './http-request.utils';

export const globalErrorHandlerInterceptor: HttpInterceptorFn = (req, next) => {
  if (isTranslationAssetRequest(req.url)) {
    return next(req);
  }

  const injector = inject(Injector);

  return next(req).pipe(
    catchError((error) => {
      const normalizedError = ensureNormalizedBackendError(error);

      if (!req.context.get(SKIP_GLOBAL_ERROR_HANDLER)) {
        injector.get(GlobalErrorHandlerService).handleHttpError(normalizedError, {
          url: req.url,
          method: req.method,
          skipToast: req.context.get(SKIP_GLOBAL_ERROR_TOAST),
          skipHandler: false,
        });
      }

      return throwError(() => normalizedError);
    })
  );
};
