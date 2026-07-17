import { HttpContext } from '@angular/common/http';
import { inject, Injectable, Injector } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, map, Observable, of, tap } from 'rxjs';
import { isHierarchyCompliant, UserRole } from '../auth/role-hierarchy';
import { BACKEND_ERROR_CODES } from '../errors/backend-error-codes';
import { NormalizedBackendError } from '../errors/backend-error.model';
import { normalizeBackendError } from '../errors/backend-error.mapper';
import {
  SKIP_GLOBAL_ERROR_HANDLER,
  SKIP_GLOBAL_UNAUTHORIZED_REDIRECT,
} from '../interceptors/http-context-tokens';
import { skipGlobalErrorHandlerContext } from '../interceptors/http-request-context';
import { User, UserPlan } from '../models/user.model';
import { AuthStateService } from './auth-state.service';
import { API_SERVICE_TOKEN, ApiRequestOptions } from '../tokens/api-service.token';
import { UserPreferencesService } from './user-preferences.service';

interface LoginResponse {
  message: string;
  token: string;
  token_type: string;
  user: {
    id: string;
    name: string;
    email: string;
    role: UserRole;
    plan?: UserPlan;
    default_workspace_id?: string | null;
    two_factor_enabled?: boolean;
    permissions?: string[];
  };
}

export interface TwoFactorRequiredResponse {
  two_factor_required: true;
  session_token: string;
  message: string;
}

export interface VerifyTwoFactorResponse {
  message: string;
  token: string;
  token_type: string;
  user: User;
}

export interface ToggleTwoFactorResponse {
  message: string;
  data: { two_factor_enabled: boolean };
}

export interface ResendTwoFactorResponse {
  message: string;
  session_token: string;
}

type LoginApiResponse = LoginResponse | TwoFactorRequiredResponse;

export function isTwoFactorRequiredResponse(
  response: LoginApiResponse
): response is TwoFactorRequiredResponse {
  return 'two_factor_required' in response && response.two_factor_required === true;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface UserLoginResult {
  error: NormalizedBackendError | null;
  emailUnverified: boolean;
  twoFactorRequired: boolean;
}

export interface AdminLoginResult {
  error: NormalizedBackendError | null;
}

@Injectable({
  providedIn: 'root'
})
export class AuthApiService {
  private readonly apiService = inject(API_SERVICE_TOKEN);
  private readonly authState = inject(AuthStateService);
  private readonly preferencesService = inject(UserPreferencesService);
  private readonly injector = inject(Injector);
  private readonly authRequestOptions: ApiRequestOptions = {
    context: skipGlobalErrorHandlerContext(),
  };

  loginAsUser(email: string, password: string): Observable<UserLoginResult> {
    return this.apiService
      .post<LoginApiResponse>('/auth/user/login', { email, password }, this.authRequestOptions)
      .pipe(
        tap((response) => {
          if (isTwoFactorRequiredResponse(response)) {
            sessionStorage.setItem('two_factor_session_token', response.session_token);
            void this.navigateByUrl('/user/verify-2fa');
            return;
          }

          this.authState.applyLoginResponse(response.user, response.token);
          this.preferencesService.loadFromBackend();
          void this.navigateByUrl('/user/dashboard');
        }),
        map((response) => ({
          error: null,
          emailUnverified: false,
          twoFactorRequired: isTwoFactorRequiredResponse(response),
        })),
        catchError((error) => {
          const normalizedError = this.normalizeAuthLoginError(error);

          return of({
            error: normalizedError,
            emailUnverified: normalizedError.code === BACKEND_ERROR_CODES.authEmailNotVerified,
            twoFactorRequired: false,
          });
        })
      );
  }

  loginAsAdmin(email: string, password: string): Observable<AdminLoginResult> {
    return this.loginWithEndpoint(
      email,
      password,
      '/auth/admin/login',
      '/admin/dashboard',
      'admin',
    ).pipe(
      catchError((error) => of({ error: this.normalizeAuthLoginError(error) }))
    );
  }

  register(payload: RegisterPayload): Observable<void> {
    return this.apiService.post<void>('/auth/register', payload, this.authRequestOptions);
  }

  resendVerification(email: string): Observable<void> {
    return this.apiService.post<void>('/auth/email/resend', { email }, this.authRequestOptions);
  }

  verifyEmail(id: string, hash: string, expires: string, signature: string): Observable<void> {
    const params = new URLSearchParams({ expires, signature }).toString();
    return this.apiService.get<void>(
      `/auth/email/verify/${id}/${hash}?${params}`,
      this.authRequestOptions,
    );
  }

  forgotPassword(email: string): Observable<void> {
    return this.apiService.post<void>('/auth/password/forgot', { email }, this.authRequestOptions);
  }

  resetPassword(token: string, email: string, password: string, passwordConfirmation: string): Observable<void> {
    return this.apiService.post<void>(
      '/auth/password/reset',
      {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      },
      this.authRequestOptions,
    );
  }

  verifyTwoFactorCode(sessionToken: string, code: string): Observable<VerifyTwoFactorResponse> {
    return this.apiService.post<VerifyTwoFactorResponse>(
      '/auth/user/verify-2fa',
      {
        session_token: sessionToken,
        code,
      },
      this.authRequestOptions,
    );
  }

  resendTwoFactorCode(sessionToken: string): Observable<ResendTwoFactorResponse> {
    return this.apiService.post<ResendTwoFactorResponse>(
      '/auth/user/resend-2fa',
      {
        session_token: sessionToken,
      },
      this.authRequestOptions,
    );
  }

  toggleTwoFactor(enabled: boolean, password: string): Observable<ToggleTwoFactorResponse> {
    return this.apiService.put<ToggleTwoFactorResponse>(
      '/user/two-factor',
      {
        enabled,
        password,
      },
      this.authRequestOptions,
    );
  }

  logout(redirectUrl = '/login'): void {
    this.apiService.post('/auth/logout', {}, {
      context: new HttpContext()
        .set(SKIP_GLOBAL_UNAUTHORIZED_REDIRECT, true)
        .set(SKIP_GLOBAL_ERROR_HANDLER, true),
    }).subscribe({
      complete: () => this.clearAndRedirect(redirectUrl),
      error: () => this.clearAndRedirect(redirectUrl),
    });
  }

  private loginWithEndpoint(
    email: string,
    password: string,
    endpoint: string,
    successRoute: string,
    expectedRole: 'admin' | 'user',
  ): Observable<AdminLoginResult> {
    return this.apiService
      .post<LoginResponse>(endpoint, { email, password }, this.authRequestOptions)
      .pipe(
        tap((response) => {
          this.authState.setToken(response.token);
          this.authState.setRole(this.normalizeRole(response.user.role));
          this.authState.setPlan(response.user.plan ?? 'free');
          this.authState.setUserId(response.user.id);
          this.authState.setDefaultWorkspaceId(response.user.default_workspace_id ?? null);
          this.authState.setEmailVerified(true);
          this.authState.setPermissions(response.user.permissions ?? []);
        }),
        tap((response) => {
          // Defensive validation: detect role mismatches that violate the hierarchy
          // (admin ⊇ user is allowed; any other mismatch is unexpected and must abort).
          // Examples:
          //   expectedRole='user',  actualRole='admin' → OK (hierarchy).
          //   expectedRole='user',  actualRole='user'  → OK (normal case).
          //   expectedRole='user',  actualRole='premium' → MISMATCH (not handled).
          //   expectedRole='admin', actualRole='user'  → MISMATCH (unidirectional).
          const normalizedRole = this.normalizeRole(response.user.role);

          if (!isHierarchyCompliant(normalizedRole, expectedRole)) {
            const actualRole = response.user.role ?? 'null';
            console.error(
              `[AuthApi] role mismatch on ${endpoint}: expected ${expectedRole}, got ${actualRole}`,
            );
            this.authState.clear();
            throw this.buildRoleMismatchError(endpoint, expectedRole, actualRole);
          }
        }),
        tap(() => {
          void this.navigateByUrl(successRoute);
        }),
        map(() => ({ error: null }))
      );
  }

  private normalizeRole(role: string | null): UserRole | null {
    return role === 'admin' || role === 'user' ? role : null;
  }

  private buildRoleMismatchError(
    endpoint: string,
    expectedRole: 'admin' | 'user',
    actualRole: string,
  ): NormalizedBackendError {
    return {
      status: 403,
      code: BACKEND_ERROR_CODES.authRoleMismatch,
      message: `Expected role ${expectedRole}, got ${actualRole} on ${endpoint}`,
      requestId: null,
      fieldErrors: null,
      meta: { endpoint, expectedRole, actualRole },
      isStandardized: false,
      original: null,
    };
  }

  private normalizeAuthLoginError(error: unknown): NormalizedBackendError {
    if (this.isRoleMismatchError(error)) {
      return error;
    }

    const normalizedError = normalizeBackendError(error);

    if (
      !normalizedError.isStandardized
      && normalizedError.status === 422
      && normalizedError.code === BACKEND_ERROR_CODES.validationError
    ) {
      return {
        ...normalizedError,
        code: BACKEND_ERROR_CODES.authInvalidCredentials,
      };
    }

    return normalizedError;
  }

  private isRoleMismatchError(error: unknown): error is NormalizedBackendError {
    return (
      typeof error === 'object'
      && error !== null
      && 'code' in error
      && (error as { code: unknown }).code === BACKEND_ERROR_CODES.authRoleMismatch
    );
  }

  private clearAndRedirect(redirectUrl: string): void {
    this.authState.clear();
    void this.navigateByUrl(redirectUrl);
  }

  private navigateByUrl(url: string): Promise<boolean> {
    return this.injector.get(Router).navigateByUrl(url);
  }
}
