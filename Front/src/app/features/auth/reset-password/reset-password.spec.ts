import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, provideRouter, Router } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { ResetPasswordComponent } from './reset-password';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { BACKEND_FIELD_ERROR_KEY } from '../../../core/errors/apply-backend-field-errors';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';

function createActivatedRouteMock(params: Record<string, string>) {
  return {
    snapshot: {
      queryParams: params,
      queryParamMap: {
        get: (key: string) => params[key] ?? null,
      },
    },
  };
}

function createFormMock(invalid = false) {
  const controls = new Map<string, {
    errors: Record<string, unknown> | null;
    setErrors: ReturnType<typeof vi.fn>;
    markAsTouched: ReturnType<typeof vi.fn>;
  }>();

  const getControl = (field: string) => {
    if (!controls.has(field)) {
      controls.set(field, {
        errors: null,
        setErrors: vi.fn(function(this: { errors: Record<string, unknown> | null }, errors: Record<string, unknown> | null) {
          this.errors = errors;
        }),
        markAsTouched: vi.fn(),
      });
    }

    return controls.get(field)!;
  };

  return {
    invalid,
    control: {
      markAllAsTouched: vi.fn(),
      get: vi.fn().mockImplementation((field: string) => getControl(field)),
    },
    getControl,
  };
}

describe('ResetPasswordComponent', () => {
  let fixture: ComponentFixture<ResetPasswordComponent>;
  let component: ResetPasswordComponent;
  let authApiMock: { resetPassword: ReturnType<typeof vi.fn>; logout: ReturnType<typeof vi.fn> };
  let router: Router;
  let authStateMock: { isLoggedIn: ReturnType<typeof vi.fn>; role: ReturnType<typeof vi.fn>; token: ReturnType<typeof vi.fn>; emailVerified: ReturnType<typeof vi.fn> };

  async function setup(
    queryParams: Record<string, string> = { token: 'abc', email: 'user@example.com' },
    loggedIn = false,
    role: 'user' | 'admin' = 'user'
  ) {
    authApiMock = { resetPassword: vi.fn(), logout: vi.fn() };
    authStateMock = {
      isLoggedIn: vi.fn().mockReturnValue(loggedIn),
      role: vi.fn().mockReturnValue(loggedIn ? role : null),
      token: vi.fn().mockReturnValue(loggedIn ? 'session-token' : null),
      emailVerified: vi.fn().mockReturnValue(loggedIn),
    };

    await TestBed.configureTestingModule({
      imports: [ResetPasswordComponent, FormsModule, TranslateModule.forRoot()],
      providers: [
        provideRouter([]),
        { provide: AuthApiService, useValue: authApiMock },
        { provide: ActivatedRoute, useValue: createActivatedRouteMock(queryParams) },
        { provide: AUTH_STATE_TOKEN, useValue: authStateMock },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);

    fixture = TestBed.createComponent(ResetPasswordComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  }

  it('should show invalid state when token or email is missing', async () => {
    await setup({});

    expect(component.state()).toBe('invalid');
  });

  it('should show form state when token and email are present', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });

    expect(component.state()).toBe('form');
    expect(component.showActiveSessionInterstitial()).toBe(false);
  });

  it('should show interstitial when there is an active session', async () => {
    await setup({ token: 'abc', email: 'user@example.com' }, true);

    expect(component.state()).toBe('form');
    expect(component.showActiveSessionInterstitial()).toBe(true);
  });

  it('should keep session and navigate away when canceling the interstitial', async () => {
    await setup({ token: 'abc', email: 'user@example.com' }, true);

    component.cancelActiveSessionReset();

    expect(authApiMock.logout).not.toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/user/dashboard');
  });

  it('should navigate admin users to admin dashboard when canceling the interstitial', async () => {
    await setup({ token: 'abc', email: 'admin@example.com' }, true, 'admin');

    component.cancelActiveSessionReset();

    expect(authApiMock.logout).not.toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/admin/dashboard');
  });

  it('should logout and preserve only allowed reset params before continuing reset', async () => {
    await setup({ token: 'abc', email: 'user@example.com', foo: 'bar' }, true);

    component.continueAfterLogout();

    expect(authApiMock.logout).toHaveBeenCalledWith(
      '/user/reset-password?token=abc&email=user@example.com'
    );
  });

  it('should ignore internal or arbitrary params when building reset continuation url', async () => {
    await setup({ token: 'abc', email: 'user@example.com', continueAfterLogout: '1', redirect: '/admin' }, true);

    component.continueAfterLogout();

    expect(authApiMock.logout).toHaveBeenCalledWith(
      '/user/reset-password?token=abc&email=user@example.com'
    );
  });

  it('should set success state after successful reset', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });
    authApiMock.resetPassword.mockReturnValue(of(undefined));
    const form = createFormMock();

    component.password = 'StrongPass123';
    component.passwordConfirmation = 'StrongPass123';
    component.submit(form as never);

    expect(component.state()).toBe('success');
  });

  it('should keep form state and apply field errors on validation response', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });
    const form = createFormMock();
    authApiMock.resetPassword.mockReturnValue(
      throwError(() => ({
        status: 422,
        error: {
          status: 422,
          code: 'validation_error',
          message: 'Validation failed',
          request_id: 'req-1',
          fieldErrors: {
            password: ['Password must be at least 8 characters.'],
          },
        },
      }))
    );

    component.password = 'StrongPass123';
    component.passwordConfirmation = 'StrongPass123';
    component.submit(form as never);

    expect(component.state()).toBe('form');
    expect(form.getControl('password').setErrors).toHaveBeenCalledWith({
      [BACKEND_FIELD_ERROR_KEY]: 'Password must be at least 8 characters.',
    });
  });

  it('should switch to invalid state when backend returns invalid reset token code', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });
    const form = createFormMock();
    authApiMock.resetPassword.mockReturnValue(
      throwError(() => ({
        status: 422,
        error: {
          status: 422,
          code: BACKEND_ERROR_CODES.passwordResetInvalidToken,
          message: 'This password reset token is invalid or has expired.',
          request_id: 'req-token',
        },
      }))
    );

    component.password = 'StrongPass123';
    component.passwordConfirmation = 'StrongPass123';
    component.submit(form as never);

    expect(component.state()).toBe('invalid');
  });

  it('should set error state when backend returns non-validation error', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });
    const form = createFormMock();
    authApiMock.resetPassword.mockReturnValue(
      throwError(() => ({
        status: 500,
        error: {
          status: 500,
          code: 'internal_error',
          message: 'Unexpected reset error.',
          request_id: 'req-500',
        },
      }))
    );

    component.password = 'StrongPass123';
    component.passwordConfirmation = 'StrongPass123';
    component.submit(form as never);

    expect(component.state()).toBe('error');
    expect(component.errorMessage()).toBe('Unexpected reset error.');
  });

  it('should mark form touched and skip API when form is invalid', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });
    const form = createFormMock(true);

    component.submit(form as never);

    expect(form.control.markAllAsTouched).toHaveBeenCalled();
    expect(authApiMock.resetPassword).not.toHaveBeenCalled();
  });

  it('should be invalid when a weak password is provided due to pattern validation', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });
    
    const compiled = fixture.nativeElement as HTMLElement;
    const passwordInput = compiled.querySelector('#password') as HTMLInputElement;
    const confirmInput = compiled.querySelector('#password-confirmation') as HTMLInputElement;
    
    passwordInput.value = 'weakpass123';
    passwordInput.dispatchEvent(new Event('input'));
    confirmInput.value = 'weakpass123';
    confirmInput.dispatchEvent(new Event('input'));
    
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    
    const formElement = compiled.querySelector('form') as HTMLFormElement;
    formElement.dispatchEvent(new Event('submit', { cancelable: true }));
    
    fixture.detectChanges();
    await fixture.whenStable();

    expect(authApiMock.resetPassword).not.toHaveBeenCalled();
    
    const errorElement = compiled.querySelector('.error');
    expect(errorElement).toBeTruthy();
    expect(errorElement?.textContent).not.toContain('auth.validation.password_complexity');
  });

  it('should toggle password visibility on reset password and confirmation inputs', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });

    const compiled = fixture.nativeElement as HTMLElement;
    const passwordInput = compiled.querySelector('#password') as HTMLInputElement;
    const confirmationInput = compiled.querySelector('#password-confirmation') as HTMLInputElement;
    const toggleButtons = compiled.querySelectorAll('.password-toggle');

    expect(passwordInput.type).toBe('password');
    expect(confirmationInput.type).toBe('password');

    (toggleButtons[0] as HTMLButtonElement).click();
    fixture.detectChanges();

    expect(passwordInput.type).toBe('text');
    expect((toggleButtons[0] as HTMLButtonElement).getAttribute('aria-pressed')).toBe('true');

    (toggleButtons[1] as HTMLButtonElement).click();
    fixture.detectChanges();

    expect(confirmationInput.type).toBe('text');
    expect((toggleButtons[1] as HTMLButtonElement).getAttribute('aria-pressed')).toBe('true');
  });

  it('should wire password accessibility descriptors for criteria and error', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });

    const compiled = fixture.nativeElement as HTMLElement;
    const passwordInput = compiled.querySelector('#password') as HTMLInputElement;

    expect(passwordInput.getAttribute('aria-describedby')).toBe('reset-password-criteria');

    passwordInput.value = 'weak';
    passwordInput.dispatchEvent(new Event('input'));
    passwordInput.dispatchEvent(new Event('blur'));
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(passwordInput.getAttribute('aria-describedby')).toBe('reset-password-criteria reset-password-error');

    const passwordError = compiled.querySelector('#reset-password-error');
    expect(passwordError).toBeTruthy();
    expect(passwordError?.getAttribute('role')).toBe('alert');
  });

  it('should expose checklist criteria status for typed password', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });

    component.password = 'Abcd1234';
    expect(component.getPasswordCriteriaItems().every((criterion) => criterion.met)).toBe(true);

    component.password = 'weak';
    const criteria = component.getPasswordCriteriaItems();
    expect(criteria.find((item) => item.key === 'auth.password_criteria.min_length')?.met).toBe(false);
    expect(criteria.find((item) => item.key === 'auth.password_criteria.uppercase')?.met).toBe(false);
    expect(criteria.find((item) => item.key === 'auth.password_criteria.lowercase')?.met).toBe(true);
    expect(criteria.find((item) => item.key === 'auth.password_criteria.number')?.met).toBe(false);
  });

  it('should not submit while loading', async () => {
    await setup({ token: 'abc', email: 'user@example.com' });
    const form = createFormMock();
    component.state.set('loading');
    component.submit(form as never);

    expect(authApiMock.resetPassword).not.toHaveBeenCalled();
  });

  it('should not submit while active session interstitial is shown', async () => {
    await setup({ token: 'abc', email: 'user@example.com' }, true);
    const form = createFormMock();
    component.submit(form as never);

    expect(authApiMock.resetPassword).not.toHaveBeenCalled();
  });
});
