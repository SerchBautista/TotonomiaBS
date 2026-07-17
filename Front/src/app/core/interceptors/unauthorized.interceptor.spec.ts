import { HttpContext, HttpRequest } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { throwError } from 'rxjs';
import { vi } from 'vitest';
import { BACKEND_ERROR_CODES } from '../errors/backend-error-codes';
import { AuthStateService } from '../services/auth-state.service';
import { SKIP_GLOBAL_UNAUTHORIZED_REDIRECT } from './http-context-tokens';
import { unauthorizedInterceptor } from './unauthorized.interceptor';

describe('unauthorizedInterceptor', () => {
  let authStateMock: {
    isLoggedIn: ReturnType<typeof vi.fn>;
    clear: ReturnType<typeof vi.fn>;
  };
  let routerMock: {
    navigateByUrl: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    authStateMock = {
      isLoggedIn: vi.fn().mockReturnValue(true),
      clear: vi.fn(),
    };
    routerMock = {
      navigateByUrl: vi.fn().mockResolvedValue(true),
    };

    TestBed.configureTestingModule({
      providers: [
        { provide: AuthStateService, useValue: authStateMock },
        { provide: Router, useValue: routerMock },
      ],
    });
  });

  it('clears auth state and rethrows normalized 401 errors', async () => {
    const request = new HttpRequest('GET', '/secure');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 401,
        error: {
          message: 'Unauthenticated',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() => unauthorizedInterceptor(request, next));

    await expect(new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))).rejects.toMatchObject({
      status: 401,
      code: BACKEND_ERROR_CODES.unauthenticated,
      message: 'Unauthenticated',
      isStandardized: false,
    });
    expect(authStateMock.clear).toHaveBeenCalled();
    expect(routerMock.navigateByUrl).toHaveBeenCalledWith('/login');
  });

  it('does not redirect when user is already logged out', async () => {
    authStateMock.isLoggedIn.mockReturnValue(false);
    const request = new HttpRequest('GET', '/secure');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 401,
        error: {
          status: 401,
          code: BACKEND_ERROR_CODES.unauthenticated,
          message: 'Unauthenticated',
          request_id: 'req-401',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() => unauthorizedInterceptor(request, next));

    await expect(new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))).rejects.toMatchObject({
      code: BACKEND_ERROR_CODES.unauthenticated,
      isStandardized: true,
      requestId: 'req-401',
    });
    expect(authStateMock.clear).not.toHaveBeenCalled();
    expect(routerMock.navigateByUrl).not.toHaveBeenCalled();
  });

  it('rethrows normalized 401 errors without redirect when request skips global handling', async () => {
    const request = new HttpRequest('POST', '/auth/logout', null, {
      context: new HttpContext().set(SKIP_GLOBAL_UNAUTHORIZED_REDIRECT, true),
    });
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 401,
        error: {
          message: 'Unauthenticated',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() => unauthorizedInterceptor(request, next));

    await expect(new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))).rejects.toMatchObject({
      status: 401,
      code: BACKEND_ERROR_CODES.unauthenticated,
      message: 'Unauthenticated',
    });
    expect(authStateMock.clear).not.toHaveBeenCalled();
    expect(routerMock.navigateByUrl).not.toHaveBeenCalled();
  });

  it('rethrows non-401 errors without clearing the session', async () => {
    const request = new HttpRequest('GET', '/secure');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 403,
        error: {
          message: 'Forbidden',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() => unauthorizedInterceptor(request, next));

    await expect(new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))).rejects.toMatchObject({
      status: 403,
      code: BACKEND_ERROR_CODES.forbidden,
      message: 'Forbidden',
    });
    expect(authStateMock.clear).not.toHaveBeenCalled();
    expect(routerMock.navigateByUrl).not.toHaveBeenCalled();
  });

  it('does not resolve Router when redirect is not needed', async () => {
    TestBed.resetTestingModule();
    authStateMock = {
      isLoggedIn: vi.fn().mockReturnValue(false),
      clear: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        { provide: AuthStateService, useValue: authStateMock },
        {
          provide: Router,
          useFactory: () => {
            throw new Error('Router should not be resolved');
          },
        },
      ],
    });

    const request = new HttpRequest('GET', '/secure');
    const next = vi.fn().mockReturnValue(
      throwError(() => ({
        status: 403,
        error: {
          message: 'Forbidden',
        },
      }))
    );

    const interceptorResult = TestBed.runInInjectionContext(() => unauthorizedInterceptor(request, next));

    await expect(new Promise((resolve, reject) => interceptorResult.subscribe({ next: resolve, error: reject }))).rejects.toMatchObject({
      status: 403,
      code: BACKEND_ERROR_CODES.forbidden,
      message: 'Forbidden',
    });
  });
});
