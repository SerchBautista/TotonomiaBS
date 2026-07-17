import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { ForgotPasswordComponent } from './forgot-password';
import { AuthApiService } from '../../../core/services/auth-api.service';

function createFormMock(invalid = false) {
  const emailControl = {
    errors: null as Record<string, unknown> | null,
    setErrors: vi.fn(function(this: { errors: Record<string, unknown> | null }, errors: Record<string, unknown> | null) {
      this.errors = errors;
    }),
    markAsTouched: vi.fn(),
  };

  return {
    invalid,
    control: {
      markAllAsTouched: vi.fn(),
      setErrors: vi.fn(),
      errors: null,
      get: vi.fn().mockImplementation((field: string) => field === 'email' ? emailControl : null),
    },
    emailControl,
  };
}

describe('ForgotPasswordComponent', () => {
  let fixture: ComponentFixture<ForgotPasswordComponent>;
  let component: ForgotPasswordComponent;
  let authApiMock: { forgotPassword: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    authApiMock = { forgotPassword: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [ForgotPasswordComponent, FormsModule, TranslateModule.forRoot()],
      providers: [
        { provide: AuthApiService, useValue: authApiMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ForgotPasswordComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should render the email form initially', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('form')).toBeTruthy();
    expect(compiled.querySelector('input[type="email"]')).toBeTruthy();
  });

  it('should show confirmation state after successful submission', () => {
    authApiMock.forgotPassword.mockReturnValue(of(undefined));
    const form = createFormMock();

    component.email = 'user@example.com';
    component.submit(form as never);

    expect(component.submitted()).toBe(true);
    expect(authApiMock.forgotPassword).toHaveBeenCalledWith('user@example.com');
  });

  it('should set errorMessage and not show confirmation on network error', () => {
    authApiMock.forgotPassword.mockReturnValue(throwError(() => ({
      status: 500,
      error: {
        status: 500,
        code: 'internal_error',
        message: 'Could not send reset email.',
        request_id: 'req-forgot',
      },
    })));
    const form = createFormMock();

    component.email = 'user@example.com';
    component.submit(form as never);

    expect(component.submitted()).toBe(false);
    expect(component.errorMessage()).toBe('Could not send reset email.');
  });

  it('should apply field errors from backend validation response', () => {
    authApiMock.forgotPassword.mockReturnValue(throwError(() => ({
      status: 422,
      error: {
        status: 422,
        code: 'validation_error',
        message: 'Validation failed',
        request_id: 'req-forgot-2',
        fieldErrors: {
          email: ['Email is required.'],
        },
      },
    })));
    const form = createFormMock();

    component.email = 'user@example.com';
    component.submit(form as never);

    expect(form.emailControl.setErrors).toHaveBeenCalledWith({ serverError: 'Email is required.' });
    expect(component.errorMessage()).toBe('');
  });

  it('should mark form touched and skip API when form is invalid', () => {
    const form = createFormMock(true);

    component.submit(form as never);

    expect(form.control.markAllAsTouched).toHaveBeenCalled();
    expect(authApiMock.forgotPassword).not.toHaveBeenCalled();
  });

  it('should not call API while loading', () => {
    const form = createFormMock();
    component.loading.set(true);
    component.submit(form as never);

    expect(authApiMock.forgotPassword).not.toHaveBeenCalled();
  });
});
