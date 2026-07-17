import { HttpContext, HttpRequest } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { throwError } from 'rxjs';
import { vi } from 'vitest';
import { BACKEND_ERROR_CODES } from '../errors/backend-error-codes';
import { BackendErrorMessageResolver } from '../errors/backend-error-message.resolver';
import { GlobalErrorHandlerService } from '../errors/global-error-handler.service';
import { TOAST_SERVICE_TOKEN } from '../services/toast.service';
import { SKIP_GLOBAL_ERROR_HANDLER, SKIP_GLOBAL_ERROR_TOAST } from './http-context-tokens';
import { globalErrorHandlerInterceptor } from './global-error-handler.interceptor';

describe('globalErrorHandlerInterceptor', () => {
  let toastError: ReturnType<typeof vi.fn>;
  let handleHttpErrorSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    toastError = vi.fn();

    TestBed.configureTestingModule({
      providers: [
        GlobalErrorHandlerService,
        {
          provide: TOAST_SERVICE_TOKEN,
          useValue: { error: toastError },
        },
        {
          provide: BackendErrorMessageResolver,
          useValue: { resolve: vi.fn().mockReturnValue('Resolved error message') },
        },
      ],
    });

    handleHttpErrorSpy = vi.spyOn(TestBed.inject(GlobalErrorHandlerService), 'handleHttpError');
  });

  afterEach(() => {
    handleHttpErrorSpy.mockRestore();
  });

  it('shows toast and rethrows normalized 500 errors', async () => {
    const request = new HttpRequest('GET', '/api/items');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 500,
        error: {
          status: 500,
          code: BACKEND_ERROR_CODES.internalError,
          message: 'Internal server error',
          request_id: 'req-500',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 500,
      code: BACKEND_ERROR_CODES.internalError,
      isStandardized: true,
    });
    expect(handleHttpErrorSpy).toHaveBeenCalledWith(
      expect.objectContaining({ status: 500 }),
      expect.objectContaining({ url: '/api/items', method: 'GET', skipToast: false, skipHandler: false })
    );
    expect(toastError).toHaveBeenCalledWith('Resolved error message');
  });

  it('shows toast and rethrows normalized network errors', async () => {
    const request = new HttpRequest('GET', '/api/items');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 0,
        error: null,
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 0,
      code: BACKEND_ERROR_CODES.networkError,
    });
    expect(toastError).toHaveBeenCalledWith('Resolved error message');
  });

  it('does not show toast for 401 errors but still rethrows', async () => {
    const request = new HttpRequest('GET', '/secure');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 401,
        error: {
          message: 'Unauthenticated',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 401,
      code: BACKEND_ERROR_CODES.unauthenticated,
    });
    expect(handleHttpErrorSpy).toHaveBeenCalled();
    expect(toastError).not.toHaveBeenCalled();
  });

  it('does not show toast for 422 errors with field errors but still rethrows', async () => {
    const request = new HttpRequest('POST', '/api/items', null);
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 422,
        error: {
          message: 'Validation failed',
          errors: {
            email: ['Email is required'],
          },
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 422,
      code: BACKEND_ERROR_CODES.validationError,
      fieldErrors: {
        email: ['Email is required'],
      },
    });
    expect(handleHttpErrorSpy).toHaveBeenCalled();
    expect(toastError).not.toHaveBeenCalled();
  });

  it('shows toast for 404 errors and still rethrows', async () => {
    const request = new HttpRequest('GET', '/api/missing');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 404,
        error: {
          message: 'Not found',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 404,
      code: BACKEND_ERROR_CODES.notFound,
    });
    expect(handleHttpErrorSpy).toHaveBeenCalled();
    expect(toastError).toHaveBeenCalledWith('Resolved error message');
  });

  it.each([
    { status: 403, code: BACKEND_ERROR_CODES.forbidden, path: '/api/forbidden' },
    { status: 409, code: BACKEND_ERROR_CODES.conflict, path: '/api/conflict' },
    { status: 429, code: BACKEND_ERROR_CODES.unknownError, path: '/api/rate-limited' },
  ])('shows toast for $status errors and still rethrows', async ({ status, code, path }) => {
    const request = new HttpRequest('GET', path);
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status,
        error: {
          message: `HTTP ${status}`,
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status,
      code,
    });
    expect(handleHttpErrorSpy).toHaveBeenCalled();
    expect(toastError).toHaveBeenCalledWith('Resolved error message');
  });

  it('skips global handling when SKIP_GLOBAL_ERROR_HANDLER is set', async () => {
    const request = new HttpRequest('GET', '/api/items', null, {
      context: new HttpContext().set(SKIP_GLOBAL_ERROR_HANDLER, true),
    });
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 500,
        error: {
          message: 'Internal server error',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 500,
      code: BACKEND_ERROR_CODES.internalError,
    });
    expect(handleHttpErrorSpy).not.toHaveBeenCalled();
    expect(toastError).not.toHaveBeenCalled();
  });

  it('bypasses translation asset requests without invoking global handler', async () => {
    const request = new HttpRequest('GET', '/i18n/es.json');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 500,
        error: {
          message: 'Internal server error',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 500,
    });
    expect(handleHttpErrorSpy).not.toHaveBeenCalled();
    expect(toastError).not.toHaveBeenCalled();
  });

  it('skips toast but still delegates handling when SKIP_GLOBAL_ERROR_TOAST is set', async () => {
    const request = new HttpRequest('GET', '/api/items', null, {
      context: new HttpContext().set(SKIP_GLOBAL_ERROR_TOAST, true),
    });
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 500,
        error: {
          message: 'Internal server error',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      globalErrorHandlerInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 500,
      code: BACKEND_ERROR_CODES.internalError,
    });
    expect(handleHttpErrorSpy).toHaveBeenCalledWith(
      expect.objectContaining({ status: 500 }),
      expect.objectContaining({ skipToast: true, skipHandler: false })
    );
    expect(toastError).not.toHaveBeenCalled();
  });
});
