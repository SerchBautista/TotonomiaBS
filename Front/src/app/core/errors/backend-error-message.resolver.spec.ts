import { TestBed } from '@angular/core/testing';
import { TranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { BACKEND_ERROR_CODES } from './backend-error-codes';
import { DEFAULT_ERROR_MESSAGE } from './backend-error.mapper';
import { NormalizedBackendError } from './backend-error.model';
import { BackendErrorMessageResolver } from './backend-error-message.resolver';

describe('BackendErrorMessageResolver', () => {
  let resolver: BackendErrorMessageResolver;
  let translateInstant: ReturnType<typeof vi.fn>;

  const createError = (overrides: Partial<NormalizedBackendError> = {}): NormalizedBackendError => ({
    status: 500,
    code: BACKEND_ERROR_CODES.internalError,
    message: 'Server exploded',
    requestId: null,
    fieldErrors: null,
    meta: null,
    isStandardized: false,
    original: {},
    ...overrides,
  });

  beforeEach(() => {
    translateInstant = vi.fn((key: string) => key);

    TestBed.configureTestingModule({
      providers: [
        BackendErrorMessageResolver,
        {
          provide: TranslateService,
          useValue: { instant: translateInstant },
        },
      ],
    });

    resolver = TestBed.inject(BackendErrorMessageResolver);
  });

  it('returns standardized backend message with highest priority', () => {
    const message = resolver.resolve(
      createError({
        isStandardized: true,
        message: 'The given data was invalid.',
        code: BACKEND_ERROR_CODES.validationError,
      })
    );

    expect(message).toBe('The given data was invalid.');
    expect(translateInstant).not.toHaveBeenCalled();
  });

  it('uses errors.codes translation when payload is not standardized', () => {
    translateInstant.mockImplementation((key: string) =>
      key === `errors.codes.${BACKEND_ERROR_CODES.forbidden}` ? 'Acceso denegado' : key
    );

    const message = resolver.resolve(
      createError({
        status: 403,
        code: BACKEND_ERROR_CODES.forbidden,
        message: 'Forbidden',
      })
    );

    expect(message).toBe('Acceso denegado');
    expect(translateInstant).toHaveBeenCalledWith(`errors.codes.${BACKEND_ERROR_CODES.forbidden}`);
  });

  it('falls back to errors.http translation when code translation is missing', () => {
    translateInstant.mockImplementation((key: string) =>
      key === 'errors.http.404' ? 'Recurso no encontrado' : key
    );

    const message = resolver.resolve(
      createError({
        status: 404,
        code: 'custom_unknown_code',
        message: 'Not found',
      })
    );

    expect(message).toBe('Recurso no encontrado');
    expect(translateInstant).toHaveBeenCalledWith('errors.codes.custom_unknown_code');
    expect(translateInstant).toHaveBeenCalledWith('errors.http.404');
  });

  it('falls back to error.message when no translation exists', () => {
    const message = resolver.resolve(
      createError({
        status: 418,
        code: 'teapot',
        message: 'I am a teapot',
      })
    );

    expect(message).toBe('I am a teapot');
  });

  it('falls back to mapper default when message is empty and no translation exists', () => {
    const message = resolver.resolve(
      createError({
        status: 418,
        code: 'teapot',
        message: '   ',
      })
    );

    expect(message).toBe(DEFAULT_ERROR_MESSAGE);
  });
});
