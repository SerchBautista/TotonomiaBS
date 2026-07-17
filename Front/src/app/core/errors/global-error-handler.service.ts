import { inject, Injectable, InjectionToken, Injector } from '@angular/core';
import { environment } from '../../../environments/environment';
import { TOAST_SERVICE_TOKEN } from '../services/toast.service';
import { BackendErrorMessageResolver } from './backend-error-message.resolver';
import { NormalizedBackendError } from './backend-error.model';

export interface ErrorHandlerContext {
  url: string;
  method: string;
  skipToast: boolean;
  skipHandler: boolean;
}

@Injectable({ providedIn: 'root' })
export class GlobalErrorHandlerService {
  private readonly injector = inject(Injector);
  private readonly toastService = inject(TOAST_SERVICE_TOKEN);

  handleHttpError(error: NormalizedBackendError, context: ErrorHandlerContext): void {
    if (context.skipHandler) {
      return;
    }

    if (!environment.production) {
      console.error('[GlobalErrorHandler]', {
        requestId: error.requestId,
        code: error.code,
        url: context.url,
        method: context.method,
      });
    }

    if (this.shouldShowToast(error, context)) {
      const message = this.injector.get(BackendErrorMessageResolver).resolve(error);
      this.toastService.error(message);
    }
  }

  private shouldShowToast(error: NormalizedBackendError, context: ErrorHandlerContext): boolean {
    if (context.skipToast) {
      return false;
    }

    if (error.status === 401) {
      return false;
    }

    if (error.status === 422 && this.hasFieldErrors(error)) {
      return false;
    }

    return this.isGlobalToastStatus(error.status);
  }

  private isGlobalToastStatus(status: number): boolean {
    return (
      status === 0 ||
      status >= 500 ||
      status === 403 ||
      status === 404 ||
      status === 409 ||
      status === 429
    );
  }

  private hasFieldErrors(error: NormalizedBackendError): boolean {
    return error.fieldErrors !== null && Object.keys(error.fieldErrors).length > 0;
  }
}

export const GLOBAL_ERROR_HANDLER_TOKEN = new InjectionToken<GlobalErrorHandlerService>(
  'GLOBAL_ERROR_HANDLER_TOKEN',
  {
    providedIn: 'root',
    factory: () => inject(GlobalErrorHandlerService),
  }
);
