import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { of, throwError } from 'rxjs';
import { TwoFactorVerifyComponent } from './two-factor-verify';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';

describe('TwoFactorVerifyComponent', () => {
  let component: TwoFactorVerifyComponent;
  let fixture: ComponentFixture<TwoFactorVerifyComponent>;
  let router: Router;
  let authApiService: {
    verifyTwoFactorCode: ReturnType<typeof vi.fn>;
    resendTwoFactorCode: ReturnType<typeof vi.fn>;
  };
  let authStateMock: { applyLoginResponse: ReturnType<typeof vi.fn> };
  let preferencesService: { loadFromBackend: ReturnType<typeof vi.fn> };

  function createStandardizedError(code: string, message: string, meta: Record<string, unknown> | null = null) {
    return {
      status: code === 'two_factor_locked' ? 429 : 422,
      code,
      message,
      requestId: 'req-test',
      fieldErrors: null,
      meta,
      isStandardized: true,
      original: null,
    };
  }

  function fillOtp(code: string): void {
    code.split('').forEach((digit, index) => {
      component.onDigitInput(index, {
        target: { value: digit },
      } as unknown as Event);
    });
  }

  beforeEach(() => {
    vi.useFakeTimers();

    authApiService = {
      verifyTwoFactorCode: vi.fn().mockReturnValue(of({
        message: 'Verified',
        token: 'test-token',
        token_type: 'Bearer',
        user: {
          id: '1',
          name: 'Test',
          email: 'test@test.com',
          role: 'user',
          plan: 'free',
          default_workspace_id: null,
        },
      })),
      resendTwoFactorCode: vi.fn().mockReturnValue(of({
        message: 'Sent',
        session_token: 'new-token',
      })),
    };

    authStateMock = {
      applyLoginResponse: vi.fn(),
    };

    preferencesService = {
      loadFromBackend: vi.fn(),
    };

    sessionStorage.setItem('two_factor_session_token', 'test-session-token');

    TestBed.configureTestingModule({
      imports: [TwoFactorVerifyComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: AuthApiService, useValue: authApiService },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: UserPreferencesService, useValue: preferencesService },
      ],
    });

    fixture = TestBed.createComponent(TwoFactorVerifyComponent);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.useRealTimers();
    sessionStorage.clear();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should redirect to login if no session token exists on init', () => {
    sessionStorage.removeItem('two_factor_session_token');
    const navigateSpy = vi.spyOn(router, 'navigateByUrl');

    const newFixture = TestBed.createComponent(TwoFactorVerifyComponent);
    newFixture.detectChanges();

    expect(navigateSpy).toHaveBeenCalledWith('/login');
  });

  it('should auto submit when the sixth digit is entered', () => {
    fillOtp('123456');

    expect(authApiService.verifyTwoFactorCode).toHaveBeenCalledWith('test-session-token', '123456');
  });

  it('should navigate to dashboard and store auth state after successful verification', () => {
    const navigateSpy = vi.spyOn(router, 'navigateByUrl');

    fillOtp('123456');

    expect(authStateMock.applyLoginResponse).toHaveBeenCalledWith(
      expect.objectContaining({ id: '1', role: 'user' }),
      'test-token'
    );
    expect(preferencesService.loadFromBackend).toHaveBeenCalled();
    expect(navigateSpy).toHaveBeenCalledWith('/user/dashboard');
    expect(sessionStorage.getItem('two_factor_session_token')).toBeNull();
    expect(component.state()).toBe('success');
  });

  it('should show error and clear digits when otp is invalid', () => {
    authApiService.verifyTwoFactorCode.mockReturnValueOnce(
      throwError(() => createStandardizedError('invalid_otp_code', 'Código inválido'))
    );

    fillOtp('654321');

    expect(component.state()).toBe('error');
    expect(component.errorMessage()).toBe('Código inválido');
    expect(component.otpDigits()).toEqual(['', '', '', '', '', '']);
  });

  it('should lock verification when backend returns locked error', () => {
    authApiService.verifyTwoFactorCode.mockReturnValueOnce(
      throwError(() => createStandardizedError('two_factor_locked', 'Bloqueado', { retry_after: 120 }))
    );

    fillOtp('654321');

    expect(component.state()).toBe('locked');
    expect(component.lockRetryAfter()).toBe(120);
    expect(component.isInputDisabled).toBe(true);
  });

  it('should show expired state when otp has expired', () => {
    authApiService.verifyTwoFactorCode.mockReturnValueOnce(
      throwError(() => createStandardizedError('otp_code_expired', 'Expirado'))
    );

    fillOtp('654321');

    expect(component.state()).toBe('expired');
    expect(component.expiryCountdown()).toBe(0);
    expect(component.otpDigits()).toEqual(['', '', '', '', '', '']);
  });

  it('should redirect to login when verify returns invalid session', () => {
    const navigateSpy = vi.spyOn(router, 'navigateByUrl');
    authApiService.verifyTwoFactorCode.mockReturnValueOnce(
      throwError(() => createStandardizedError('invalid_session', 'Sesión inválida'))
    );

    fillOtp('654321');

    expect(sessionStorage.getItem('two_factor_session_token')).toBeNull();
    expect(navigateSpy).toHaveBeenCalledWith('/login');
  });

  it('should resend code successfully and replace session token', () => {
    component.resendCooldown.set(0);

    component.resendCode();

    expect(authApiService.resendTwoFactorCode).toHaveBeenCalledWith('test-session-token');
    expect(sessionStorage.getItem('two_factor_session_token')).toBe('new-token');
    expect(component.resendSuccess()).toBe(true);
    expect(component.state()).toBe('idle');
    expect(component.resendCooldown()).toBe(60);
  });

  it('should redirect to login when resend finds invalid session', () => {
    const navigateSpy = vi.spyOn(router, 'navigateByUrl');
    component.resendCooldown.set(0);
    authApiService.resendTwoFactorCode.mockReturnValueOnce(
      throwError(() => createStandardizedError('invalid_session', 'Sesión inválida'))
    );

    component.resendCode();

    expect(sessionStorage.getItem('two_factor_session_token')).toBeNull();
    expect(navigateSpy).toHaveBeenCalledWith('/login');
  });

  it('should initialize with idle state and empty otp digits', () => {
    expect(component.state()).toBe('idle');
    expect(component.otpDigits()).toEqual(['', '', '', '', '', '']);
  });
});
