import { HttpErrorResponse } from '@angular/common/http';
import { BACKEND_ERROR_CODES } from './backend-error-codes';
import { ensureNormalizedBackendError, normalizeBackendError } from './backend-error.mapper';

describe('normalizeBackendError', () => {
  it('maps standardized backend payloads without losing contract fields', () => {
    const normalized = normalizeBackendError({
      status: 422,
      error: {
        status: 422,
        code: BACKEND_ERROR_CODES.validationError,
        message: 'Validation failed',
        request_id: 'req-123',
        fieldErrors: {
          email: ['Email is required'],
        },
        meta: {
          source: 'form-request',
        },
      },
    });

    expect(normalized).toMatchObject({
      status: 422,
      code: BACKEND_ERROR_CODES.validationError,
      message: 'Validation failed',
      requestId: 'req-123',
      isStandardized: true,
      fieldErrors: {
        email: ['Email is required'],
      },
      meta: {
        source: 'form-request',
      },
    });
  });

  it('maps legacy laravel validation payloads to validation_error', () => {
    const normalized = normalizeBackendError({
      status: 422,
      error: {
        message: 'The given data was invalid.',
        errors: {
          password: ['The password field is required.'],
        },
      },
    });

    expect(normalized).toMatchObject({
      status: 422,
      code: BACKEND_ERROR_CODES.validationError,
      message: 'The given data was invalid.',
      isStandardized: false,
      fieldErrors: {
        password: ['The password field is required.'],
      },
    });
  });

  it('uses fallbackCode for legacy domain errors while keeping legacy metadata', () => {
    const normalized = normalizeBackendError(
      {
        status: 422,
        error: {
          message: 'Insufficient funds',
          suggested_categories: [
            { category_id: 'cat-1' },
          ],
        },
      },
      {
        fallbackCode: BACKEND_ERROR_CODES.budgetAdjustmentInsufficientFunds,
      }
    );

    expect(normalized).toMatchObject({
      status: 422,
      code: BACKEND_ERROR_CODES.budgetAdjustmentInsufficientFunds,
      message: 'Insufficient funds',
      isStandardized: false,
      meta: {
        suggested_categories: [{ category_id: 'cat-1' }],
      },
    });
  });

  it('parses stringified legacy payloads and returns network fallback for status 0', () => {
    const normalized = normalizeBackendError({
      status: 0,
      error: '{"message":"Gateway timeout"}',
    });

    expect(normalized.status).toBe(0);
    expect(normalized.code).toBe(BACKEND_ERROR_CODES.networkError);
    expect(normalized.message).toBe('Gateway timeout');
    expect(normalized.isStandardized).toBe(false);
  });

  it('uses the first legacy field error as message when payload message is missing', () => {
    const normalized = normalizeBackendError({
      status: 422,
      error: {
        errors: {
          email: ['The email field is required.'],
        },
      },
    });

    expect(normalized.code).toBe(BACKEND_ERROR_CODES.validationError);
    expect(normalized.message).toBe('The email field is required.');
    expect(normalized.fieldErrors).toEqual({
      email: ['The email field is required.'],
    });
  });

  it('prefers the provided fallback message when the backend response has no usable text', () => {
    const normalized = normalizeBackendError(
      new HttpErrorResponse({
        status: 503,
        error: {},
        statusText: '',
      }),
      {
        fallbackMessage: 'Servicio temporalmente no disponible',
      }
    );

    expect(normalized.code).toBe(BACKEND_ERROR_CODES.internalError);
    expect(normalized.message).toBe('Servicio temporalmente no disponible');
  });

  it('returns network error message for status 0 with raw HttpErrorResponse message', () => {
    const normalized = normalizeBackendError(
      new HttpErrorResponse({
        status: 0,
        statusText: 'Unknown Error',
        url: 'https://totonomia-api.rockerstats.com/api/v1/auth/user/login',
      })
    );

    expect(normalized.status).toBe(0);
    expect(normalized.code).toBe(BACKEND_ERROR_CODES.networkError);
    expect(normalized.message).toBe('No se pudo conectar con la API / Could not connect to API');
  });

  it('returns default error message for non-zero status with raw HttpErrorResponse message and no fallback', () => {
    const normalized = normalizeBackendError(
      new HttpErrorResponse({
        status: 503,
        statusText: 'Service Unavailable',
        url: 'https://totonomia-api.rockerstats.com/api/v1/some-endpoint',
      })
    );

    expect(normalized.status).toBe(503);
    expect(normalized.code).toBe(BACKEND_ERROR_CODES.internalError);
    expect(normalized.message).toBe('Ha ocurrido un error inesperado / An unexpected error occurred');
  });
});

describe('ensureNormalizedBackendError', () => {
  it('returns the same normalized error instance without remapping it', () => {
    const normalizedError = {
      status: 422,
      code: BACKEND_ERROR_CODES.validationError,
      message: 'Validation failed',
      requestId: 'req-normalized',
      fieldErrors: null,
      meta: null,
      isStandardized: true,
      original: null,
    };

    expect(ensureNormalizedBackendError(normalizedError)).toBe(normalizedError);
  });
});
