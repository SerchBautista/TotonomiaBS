import { inject, Injector } from '@angular/core';
import { HttpInterceptorFn } from '@angular/common/http';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { ensureNormalizedBackendError } from '../errors/backend-error.mapper';
import { AuthStateService } from '../services/auth-state.service';
import { SKIP_GLOBAL_UNAUTHORIZED_REDIRECT } from './http-context-tokens';

export const unauthorizedInterceptor: HttpInterceptorFn = (req, next) => {
  const authState = inject(AuthStateService);
  const injector = inject(Injector);

  return next(req).pipe(
    catchError((error) => {
      const normalizedError = ensureNormalizedBackendError(error);

      if (
        normalizedError.status === 401
        && authState.isLoggedIn()
        && !req.context.get(SKIP_GLOBAL_UNAUTHORIZED_REDIRECT)
      ) {
        authState.clear();
        void injector.get(Router).navigateByUrl('/login');
      }

      return throwError(() => normalizedError);
    })
  );
};
