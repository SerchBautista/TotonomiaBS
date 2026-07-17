import { HttpContext, HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { describe, expect, it, vi } from 'vitest';
import { BACKEND_ERROR_CODES } from '../errors/backend-error-codes';
import { SKIP_GLOBAL_ERROR_HANDLER, SKIP_GLOBAL_UNAUTHORIZED_REDIRECT } from '../interceptors/http-context-tokens';
import { AuthApiService } from './auth-api.service';
import { AuthStateService } from './auth-state.service';
import { UserPreferencesService } from './user-preferences.service';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { of, throwError } from 'rxjs';

describe('AuthApiService', () => {
  let service: AuthApiService;
  let authStateMock: {
    applyLoginResponse: ReturnType<typeof vi.fn>;
    clear: ReturnType<typeof vi.fn>;
    setToken: ReturnType<typeof vi.fn>;
    setRole: ReturnType<typeof vi.fn>;
    setPlan: ReturnType<typeof vi.fn>;
    setUserId: ReturnType<typeof vi.fn>;
    setDefaultWorkspaceId: ReturnType<typeof vi.fn>;
    setEmailVerified: ReturnType<typeof vi.fn>;
    setPermissions: ReturnType<typeof vi.fn>;
  };
  let apiMock: { post: ReturnType<typeof vi.fn>; get: ReturnType<typeof vi.fn> };
  let routerSpy: { navigateByUrl: ReturnType<typeof vi.fn> };
  let preferencesMock: { loadFromBackend: ReturnType<typeof vi.fn> };
  const authRequestOptions = expect.objectContaining({ context: expect.any(HttpContext) });

  beforeEach(() => {
    authStateMock = {
      applyLoginResponse: vi.fn(),
      clear: vi.fn(),
      setToken: vi.fn(),
      setRole: vi.fn(),
      setPlan: vi.fn(),
      setUserId: vi.fn(),
      setDefaultWorkspaceId: vi.fn(),
      setEmailVerified: vi.fn(),
      setPermissions: vi.fn(),
    };
    apiMock = {
      post: vi.fn(),
      get: vi.fn(),
    };
    routerSpy = {
      navigateByUrl: vi.fn().mockResolvedValue(true),
    };
    preferencesMock = {
      loadFromBackend: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        { provide: API_SERVICE_TOKEN, useValue: apiMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: UserPreferencesService, useValue: preferencesMock },
        { provide: Router, useValue: routerSpy },
        AuthApiService,
      ],
    });

    service = TestBed.inject(AuthApiService);
  });

  describe('loginAsUser', () => {
    it('should set token and role on successful login and navigate to user dashboard', () => {
      const mockResponse = {
        message: 'OK',
        token: 'test-token',
        token_type: 'Bearer',
        user: { id: 'user-uuid-1', name: 'User', email: 'u@test.com', role: 'user' as const },
      };
      apiMock.post.mockReturnValue(of(mockResponse));

      let result: { error: unknown; emailUnverified: boolean } | undefined;
      service.loginAsUser('u@test.com', 'pass').subscribe((v) => (result = v));

      expect(apiMock.post).toHaveBeenCalledWith(
        '/auth/user/login',
        { email: 'u@test.com', password: 'pass' },
        authRequestOptions,
      );
      expect(authStateMock.applyLoginResponse).toHaveBeenCalledWith(mockResponse.user, 'test-token');
      expect(preferencesMock.loadFromBackend).toHaveBeenCalled();
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith('/user/dashboard');
      expect(result?.error).toBeNull();
      expect(result?.emailUnverified).toBe(false);
    });

    it('should return error message on failed login', () => {
      apiMock.post.mockReturnValue(throwError(() => ({
        status: 422,
        error: {
          status: 422,
          code: BACKEND_ERROR_CODES.authInvalidCredentials,
          message: 'Invalid credentials.',
          request_id: 'req-422',
        },
      })));

      let result: { error: { message: string; code: string } | null; emailUnverified: boolean } | undefined;
      service.loginAsUser('u@test.com', 'wrong').subscribe((v) => (result = v));

      expect(result?.error?.message).toBe('Invalid credentials.');
      expect(result?.error?.code).toBe(BACKEND_ERROR_CODES.authInvalidCredentials);
      expect(result?.emailUnverified).toBe(false);
    });

    it('should return emailUnverified true on 403', () => {
      apiMock.post.mockReturnValue(throwError(() => ({
        status: 403,
        error: {
          status: 403,
          code: BACKEND_ERROR_CODES.authEmailNotVerified,
          message: 'Your email is not verified.',
          request_id: 'req-403',
        },
      })));

      let result: { error: unknown; emailUnverified: boolean } | undefined;
      service.loginAsUser('u@test.com', 'pass').subscribe((v) => (result = v));

      expect(result?.emailUnverified).toBe(true);
    });

    it('should fallback to auth invalid credentials code for legacy 422 login errors', () => {
      apiMock.post.mockReturnValue(throwError(() => ({
        status: 422,
        error: {
          errors: {
            email: ['Invalid credentials'],
          },
        },
      })));

      let result: { error: { message: string; code: string } | null; emailUnverified: boolean } | undefined;
      service.loginAsUser('u@test.com', 'wrong').subscribe((v) => (result = v));

      expect(result?.error?.message).toBe('Invalid credentials');
      expect(result?.error?.code).toBe(BACKEND_ERROR_CODES.authInvalidCredentials);
      expect(result?.emailUnverified).toBe(false);
    });

    it('should return connection error message when status is 0', () => {
      apiMock.post.mockReturnValue(
        throwError(() => new HttpErrorResponse({
          status: 0,
          statusText: 'Unknown Error',
          url: 'https://totonomia-api.rockerstats.com/api/v1/auth/user/login',
        }))
      );

      let result: { error: { message: string; code: string } | null; emailUnverified: boolean } | undefined;
      service.loginAsUser('u@test.com', 'pass').subscribe((v) => (result = v));

      expect(result?.error?.message).toContain('No se pudo conectar');
      expect(result?.error?.code).toBe(BACKEND_ERROR_CODES.networkError);
    });
  });

  describe('loginAsAdmin', () => {
    it('should navigate to admin dashboard on successful login', () => {
      const mockResponse = {
        message: 'OK',
        token: 'admin-token',
        token_type: 'Bearer',
        user: { id: 'user-uuid-2', name: 'Admin', email: 'a@test.com', role: 'admin' as const },
      };
      apiMock.post.mockReturnValue(of(mockResponse));

      service.loginAsAdmin('a@test.com', 'pass').subscribe();

      expect(authStateMock.setToken).toHaveBeenCalledWith('admin-token');
      expect(authStateMock.setRole).toHaveBeenCalledWith('admin');
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith('/admin/dashboard');
    });

    it('should return normalized error on failed admin login', () => {
      apiMock.post.mockReturnValue(throwError(() => ({
        status: 422,
        error: {
          status: 422,
          code: BACKEND_ERROR_CODES.authInvalidCredentials,
          message: 'Invalid credentials.',
          request_id: 'req-admin',
        },
      })));

      let result: { error: { message: string; code: string } | null } | undefined;
      service.loginAsAdmin('a@test.com', 'wrong').subscribe((value) => (result = value));

      expect(result?.error?.message).toBe('Invalid credentials.');
      expect(result?.error?.code).toBe(BACKEND_ERROR_CODES.authInvalidCredentials);
    });

    it('should clear auth state, skip navigation, and surface a role-mismatch error when admin login returns a non-admin role', () => {
      const mockResponse = {
        message: 'OK',
        token: 'unexpected-token',
        token_type: 'Bearer',
        user: { id: 'user-uuid-3', name: 'User', email: 'u@test.com', role: 'user' as const },
      };
      apiMock.post.mockReturnValue(of(mockResponse));

      let result: { error: { code: string; message: string } | null } | undefined;
      service.loginAsAdmin('u@test.com', 'pass').subscribe((value) => (result = value));

      // The just-persisted token and role must be cleared so the roleGuard does not
      // bounce the user in a loop between the protected route and the login page.
      expect(authStateMock.clear).toHaveBeenCalled();
      // We must NOT navigate to the admin dashboard with the wrong role.
      expect(routerSpy.navigateByUrl).not.toHaveBeenCalled();
      // The error must be surfaced through the result contract with the dedicated code.
      expect(result?.error).not.toBeNull();
      expect(result?.error?.code).toBe(BACKEND_ERROR_CODES.authRoleMismatch);
    });
  });

  describe('loginAsUser role validation', () => {
    it('should allow admin role on user login and navigate to user dashboard', () => {
      const mockResponse = {
        message: 'OK',
        token: 'admin-token',
        token_type: 'Bearer',
        user: { id: 'user-uuid-4', name: 'Admin', email: 'a@test.com', role: 'admin' as const },
      };
      apiMock.post.mockReturnValue(of(mockResponse));

      let result: { error: unknown; emailUnverified: boolean; twoFactorRequired: boolean } | undefined;
      service.loginAsUser('a@test.com', 'pass').subscribe((value) => (result = value));

      expect(authStateMock.applyLoginResponse).toHaveBeenCalledWith(mockResponse.user, 'admin-token');
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith('/user/dashboard');
      expect(preferencesMock.loadFromBackend).toHaveBeenCalled();
      expect(result?.error).toBeNull();
      expect(result?.emailUnverified).toBe(false);
      expect(result?.twoFactorRequired).toBe(false);
    });

    it('should stash the 2FA session token and navigate to verify-2fa when the server requires 2FA', () => {
      const twoFactorResponse = {
        two_factor_required: true as const,
        session_token: 'two-factor-session-123',
        message: '2FA required',
      };
      apiMock.post.mockReturnValue(of(twoFactorResponse));
      const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');

      let result: { error: unknown; emailUnverified: boolean; twoFactorRequired: boolean } | undefined;
      service.loginAsUser('u@test.com', 'pass').subscribe((value) => (result = value));

      expect(setItemSpy).toHaveBeenCalledWith('two_factor_session_token', 'two-factor-session-123');
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith('/user/verify-2fa');
      expect(authStateMock.applyLoginResponse).not.toHaveBeenCalled();
      expect(preferencesMock.loadFromBackend).not.toHaveBeenCalled();
      expect(result?.error).toBeNull();
      expect(result?.emailUnverified).toBe(false);
      expect(result?.twoFactorRequired).toBe(true);

      setItemSpy.mockRestore();
    });
  });

  describe('register', () => {
    it('should instantiate without resolving Router eagerly', () => {
      TestBed.resetTestingModule();
      TestBed.configureTestingModule({
        providers: [
          { provide: API_SERVICE_TOKEN, useValue: apiMock },
          { provide: AuthStateService, useValue: authStateMock },
          { provide: UserPreferencesService, useValue: preferencesMock },
          AuthApiService,
        ],
      });

      const lazyRouterService = TestBed.inject(AuthApiService);

      expect(lazyRouterService).toBeTruthy();
    });

    it('should call POST /auth/register with payload', () => {
      apiMock.post.mockReturnValue(of(undefined));
      const payload = { name: 'Test', email: 'test@example.com', password: 'pass1234', password_confirmation: 'pass1234' };

      service.register(payload).subscribe();

      expect(apiMock.post).toHaveBeenCalledWith('/auth/register', payload, authRequestOptions);
    });
  });

  describe('resendVerification', () => {
    it('should call POST /auth/email/resend with email', () => {
      apiMock.post.mockReturnValue(of(undefined));

      service.resendVerification('test@example.com').subscribe();

      expect(apiMock.post).toHaveBeenCalledWith(
        '/auth/email/resend',
        { email: 'test@example.com' },
        authRequestOptions,
      );
    });
  });

  describe('logout', () => {
    it('should call clear and redirect on successful logout', () => {
      apiMock.post.mockReturnValue(of({}));

      service.logout();

      expect(apiMock.post).toHaveBeenCalledWith(
        '/auth/logout',
        {},
        expect.objectContaining({
          context: expect.any(HttpContext),
        })
      );
      const [, , options] = apiMock.post.mock.calls[0] as [string, unknown, { context: HttpContext }];
      expect(options.context.get(SKIP_GLOBAL_UNAUTHORIZED_REDIRECT)).toBe(true);
      expect(options.context.get(SKIP_GLOBAL_ERROR_HANDLER)).toBe(true);
      expect(authStateMock.clear).toHaveBeenCalled();
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith('/login');
    });

    it('should redirect to the provided route after logout', () => {
      apiMock.post.mockReturnValue(of({}));

      service.logout('/user/reset-password?token=abc&email=user%40example.com');

      expect(authStateMock.clear).toHaveBeenCalled();
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith('/user/reset-password?token=abc&email=user%40example.com');
    });

    it('should call clear and redirect even on logout API error', () => {
      apiMock.post.mockReturnValue(throwError(() => new Error('Network error')));

      service.logout();

      expect(authStateMock.clear).toHaveBeenCalled();
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith('/login');
    });

    it('should redirect to the provided route even when logout API fails', () => {
      apiMock.post.mockReturnValue(throwError(() => new Error('Network error')));

      service.logout('/user/reset-password?token=abc&email=user%40example.com&continueAfterLogout=1');

      expect(authStateMock.clear).toHaveBeenCalled();
      expect(routerSpy.navigateByUrl).toHaveBeenCalledWith(
        '/user/reset-password?token=abc&email=user%40example.com&continueAfterLogout=1'
      );
    });
  });
});
