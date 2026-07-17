import { HttpErrorResponse, HttpRequest } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { throwError } from 'rxjs';
import { vi } from 'vitest';
import { BACKEND_ERROR_CODES } from '../errors/backend-error-codes';
import { NormalizedBackendError } from '../errors/backend-error.model';
import { errorNormalizationInterceptor } from './error-normalization.interceptor';

describe('errorNormalizationInterceptor', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({});
  });

  it('normalizes standard backend error payloads with isStandardized true', async () => {
    const request = new HttpRequest('POST', '/api/categories', null);
    const next = vi.fn().mockReturnValue(
      throwError(() =>
        new HttpErrorResponse({
          status: 422,
          error: {
            status: 422,
            code: BACKEND_ERROR_CODES.validationError,
            message: 'Validation failed',
            request_id: 'req-123',
            fieldErrors: {
              name: ['Name is required'],
            },
          },
        })
      )
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      errorNormalizationInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 422,
      code: BACKEND_ERROR_CODES.validationError,
      message: 'Validation failed',
      requestId: 'req-123',
      isStandardized: true,
      fieldErrors: {
        name: ['Name is required'],
      },
    });
  });

  it('normalizes legacy or malformed payloads with fallbacks', async () => {
    const request = new HttpRequest('GET', '/api/legacy');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 403,
        error: {
          message: 'Forbidden',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      errorNormalizationInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 403,
      code: BACKEND_ERROR_CODES.forbidden,
      message: 'Forbidden',
      isStandardized: false,
    });
  });

  it('maps network errors with status 0 to network_error code', async () => {
    const request = new HttpRequest('GET', '/api/offline');
    const next = vi.fn().mockReturnValue(
      throwError(() =>
        new HttpErrorResponse({
          status: 0,
          statusText: 'Unknown Error',
          url: 'https://api.example.com/api/v1/offline',
        })
      )
    );

    const interceptorResult = TestBed.runInInjectionContext(() =>
      errorNormalizationInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toMatchObject({
      status: 0,
      code: BACKEND_ERROR_CODES.networkError,
      message: 'No se pudo conectar con la API / Could not connect to API',
      isStandardized: false,
    });
  });

  it('passes through already normalized errors idempotently', async () => {
    const normalizedError: NormalizedBackendError = {
      status: 409,
      code: BACKEND_ERROR_CODES.conflict,
      message: 'Resource conflict',
      requestId: 'req-existing',
      fieldErrors: null,
      meta: null,
      isStandardized: true,
      original: { status: 409 },
    };

    const request = new HttpRequest('DELETE', '/api/items/1');
    const next = vi.fn().mockReturnValue(throwError(() => normalizedError));

    const interceptorResult = TestBed.runInInjectionContext(() =>
      errorNormalizationInterceptor(request, next)
    );

    await expect(
      new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))
    ).rejects.toBe(normalizedError);
  });
});
