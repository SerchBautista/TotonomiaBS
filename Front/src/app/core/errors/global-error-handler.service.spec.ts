import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { environment } from '../../../environments/environment';
import { BACKEND_ERROR_CODES } from './backend-error-codes';
import { NormalizedBackendError } from './backend-error.model';
import { BackendErrorMessageResolver } from './backend-error-message.resolver';
import {
  ErrorHandlerContext,
  GlobalErrorHandlerService,
} from './global-error-handler.service';
import { TOAST_SERVICE_TOKEN } from '../services/toast.service';

describe('GlobalErrorHandlerService', () => {
  let service: GlobalErrorHandlerService;
  let toastError: ReturnType<typeof vi.fn>;
  let resolveMessage: ReturnType<typeof vi.fn>;
  let consoleErrorSpy: ReturnType<typeof vi.spyOn>;

  const baseContext: ErrorHandlerContext = {
    url: '/api/items',
    method: 'GET',
    skipToast: false,
    skipHandler: false,
  };

  const createError = (overrides: Partial<NormalizedBackendError> = {}): NormalizedBackendError => ({
    status: 500,
    code: BACKEND_ERROR_CODES.internalError,
    message: 'Internal server error',
    requestId: 'req-500',
    fieldErrors: null,
    meta: null,
    isStandardized: true,
    original: {},
    ...overrides,
  });

  beforeEach(() => {
    environment.production = false;
    toastError = vi.fn();
    resolveMessage = vi.fn().mockReturnValue('Resolved error message');
    consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined);

    TestBed.configureTestingModule({
      providers: [
        GlobalErrorHandlerService,
        {
          provide: TOAST_SERVICE_TOKEN,
          useValue: { error: toastError },
        },
        {
          provide: BackendErrorMessageResolver,
          useValue: { resolve: resolveMessage },
        },
      ],
    });

    service = TestBed.inject(GlobalErrorHandlerService);
  });

  afterEach(() => {
    consoleErrorSpy.mockRestore();
  });

  it('shows toast for server errors with resolved message', () => {
    service.handleHttpError(createError({ status: 500 }), baseContext);

    expect(resolveMessage).toHaveBeenCalledTimes(1);
    expect(toastError).toHaveBeenCalledWith('Resolved error message');
  });

  it('does not show toast for unauthorized errors', () => {
    service.handleHttpError(
      createError({
        status: 401,
        code: BACKEND_ERROR_CODES.unauthenticated,
        message: 'Unauthenticated',
      }),
      baseContext
    );

    expect(toastError).not.toHaveBeenCalled();
    expect(resolveMessage).not.toHaveBeenCalled();
  });

  it('does not show toast for validation errors with field errors', () => {
    service.handleHttpError(
      createError({
        status: 422,
        code: BACKEND_ERROR_CODES.validationError,
        message: 'Validation failed',
        fieldErrors: {
          email: ['Email is required'],
        },
      }),
      baseContext
    );

    expect(toastError).not.toHaveBeenCalled();
    expect(resolveMessage).not.toHaveBeenCalled();
  });

  it.each([403, 404, 409, 429])('shows toast for HTTP %i errors', (status) => {
    service.handleHttpError(createError({ status }), baseContext);

    expect(resolveMessage).toHaveBeenCalledTimes(1);
    expect(toastError).toHaveBeenCalledWith('Resolved error message');
  });

  it('does not show toast when skipToast is set', () => {
    service.handleHttpError(createError({ status: 500 }), {
      ...baseContext,
      skipToast: true,
    });

    expect(toastError).not.toHaveBeenCalled();
    expect(resolveMessage).not.toHaveBeenCalled();
  });

  it('does nothing when skipHandler is set', () => {
    service.handleHttpError(createError({ status: 500 }), {
      ...baseContext,
      skipHandler: true,
    });

    expect(consoleErrorSpy).not.toHaveBeenCalled();
    expect(toastError).not.toHaveBeenCalled();
    expect(resolveMessage).not.toHaveBeenCalled();
  });

  it('logs request correlation data in non-production builds', () => {
    service.handleHttpError(createError({ status: 500, requestId: 'req-abc' }), baseContext);

    expect(consoleErrorSpy).toHaveBeenCalledWith('[GlobalErrorHandler]', {
      requestId: 'req-abc',
      code: BACKEND_ERROR_CODES.internalError,
      url: '/api/items',
      method: 'GET',
    });
  });

  it('does not log in production builds', () => {
    environment.production = true;

    service.handleHttpError(createError({ status: 500 }), baseContext);

    expect(consoleErrorSpy).not.toHaveBeenCalled();
    expect(toastError).toHaveBeenCalledWith('Resolved error message');
  });
});
