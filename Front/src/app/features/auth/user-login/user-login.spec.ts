import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';
import { provideRouter, Router } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { UserAuthService } from '../../../core/services/user-auth';
import { UserLoginComponent } from './user-login';

function createFormMock() {
  const emailControl = {
    errors: null as Record<string, unknown> | null,
    setErrors: vi.fn(function(this: { errors: Record<string, unknown> | null }, errors: Record<string, unknown> | null) {
      this.errors = errors;
    }),
    markAsTouched: vi.fn(),
  };

  return {
    invalid: false,
    control: {
      markAllAsTouched: vi.fn(),
      errors: null,
      setErrors: vi.fn(),
      get: vi.fn().mockImplementation((field: string) => field === 'email' ? emailControl : null),
    },
    emailControl,
  };
}

describe('UserLoginComponent', () => {
  let fixture: ComponentFixture<UserLoginComponent>;
  let component: UserLoginComponent;
  let userAuthMock: { login: ReturnType<typeof vi.fn> };
  let authApiMock: { resendVerification: ReturnType<typeof vi.fn> };
  let router: Router;

  beforeEach(async () => {
    userAuthMock = { login: vi.fn() };
    authApiMock = { resendVerification: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [UserLoginComponent, FormsModule, TranslateModule.forRoot()],
      providers: [
        provideRouter([]),
        { provide: UserAuthService, useValue: userAuthMock },
        { provide: AuthApiService, useValue: authApiMock },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    fixture = TestBed.createComponent(UserLoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should show login error message from normalized backend error', () => {
    userAuthMock.login.mockReturnValue(of({
      error: {
        status: 422,
        code: 'invalid_credentials',
        message: 'Invalid credentials.',
        requestId: 'req-1',
        fieldErrors: null,
        meta: null,
        isStandardized: true,
        original: null,
      },
      emailUnverified: false,
    }));

    component.email = 'user@example.com';
    component.password = 'StrongPass123';
    component.submit(createFormMock() as never);

    expect(component.errorMessage()).toBe('Invalid credentials.');
    expect(component.emailUnverified()).toBe(false);
  });

  it('should enable resend flow only when login error code marks email as unverified', () => {
    userAuthMock.login.mockReturnValue(of({
      error: {
        status: 403,
        code: 'email_not_verified',
        message: 'Your email address is not verified. Please check your inbox.',
        requestId: 'req-2',
        fieldErrors: null,
        meta: null,
        isStandardized: true,
        original: null,
      },
      emailUnverified: true,
    }));

    component.email = 'user@example.com';
    component.password = 'StrongPass123';
    component.submit(createFormMock() as never);

    expect(component.emailUnverified()).toBe(true);
    expect(component.errorMessage()).toContain('not verified');
  });

  it('should apply field errors returned by the normalized login error', () => {
    userAuthMock.login.mockReturnValue(of({
      error: {
        status: 422,
        code: 'validation_error',
        message: 'Validation failed.',
        requestId: 'req-4',
        fieldErrors: {
          email: ['Email is required.'],
        },
        meta: null,
        isStandardized: true,
        original: null,
      },
      emailUnverified: false,
    }));

    const form = createFormMock();
    component.submit(form as never);

    expect(form.emailControl.setErrors).toHaveBeenCalledWith({ serverError: 'Email is required.' });
    expect(component.errorMessage()).toBe('Validation failed.');
  });

  it('should show resend fallback message when resend request fails', () => {
    authApiMock.resendVerification.mockReturnValue(throwError(() => ({
      status: 500,
      error: {
        status: 500,
        code: 'internal_error',
        message: 'Could not resend verification email.',
        request_id: 'req-3',
      },
    })));

    component.email = 'user@example.com';
    component.resendVerification(createFormMock() as never);

    expect(component.errorMessage()).toBe('Could not resend verification email.');
  });
});
