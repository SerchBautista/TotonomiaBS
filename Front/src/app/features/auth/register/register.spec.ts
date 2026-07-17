import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { RegisterComponent } from './register';
import { AuthApiService } from '../../../core/services/auth-api.service';

describe('RegisterComponent', () => {
  let fixture: ComponentFixture<RegisterComponent>;
  let component: RegisterComponent;
  let authApiMock: { register: ReturnType<typeof vi.fn> };
  let router: Router;

  beforeEach(async () => {
    sessionStorage.clear();
    authApiMock = { register: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [RegisterComponent, TranslateModule.forRoot()],
      providers: [
        provideRouter([]),
        { provide: AuthApiService, useValue: authApiMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(RegisterComponent);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
    fixture.detectChanges();
  });

  afterEach(() => {
    sessionStorage.clear();
  });

  it('should render name, email, password, and password_confirmation fields', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('#name')).toBeTruthy();
    expect(compiled.querySelector('#email')).toBeTruthy();
    expect(compiled.querySelector('#password')).toBeTruthy();
    expect(compiled.querySelector('#password_confirmation')).toBeTruthy();
  });

  it('should toggle visibility for password and password confirmation inputs', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const passwordInput = compiled.querySelector('#password') as HTMLInputElement;
    const passwordConfirmationInput = compiled.querySelector('#password_confirmation') as HTMLInputElement;
    const toggleButtons = compiled.querySelectorAll('.password-toggle');

    expect(passwordInput.type).toBe('password');
    expect(passwordConfirmationInput.type).toBe('password');

    (toggleButtons[0] as HTMLButtonElement).click();
    fixture.detectChanges();
    expect(passwordInput.type).toBe('text');
    expect((toggleButtons[0] as HTMLButtonElement).getAttribute('aria-pressed')).toBe('true');

    (toggleButtons[1] as HTMLButtonElement).click();
    fixture.detectChanges();
    expect(passwordConfirmationInput.type).toBe('text');
    expect((toggleButtons[1] as HTMLButtonElement).getAttribute('aria-pressed')).toBe('true');
  });

  it('should wire password accessibility descriptors for criteria and error', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const passwordInput = compiled.querySelector('#password') as HTMLInputElement;

    expect(passwordInput.getAttribute('aria-describedby')).toBe('register-password-criteria');

    const passwordControl = component.form.get('password');
    passwordControl?.setValue('weak');
    passwordControl?.markAsTouched();
    fixture.detectChanges();

    expect(passwordInput.getAttribute('aria-describedby')).toBe('register-password-criteria register-password-error');

    const passwordError = compiled.querySelector('#register-password-error');
    expect(passwordError).toBeTruthy();
    expect(passwordError?.getAttribute('role')).toBe('alert');
  });

  it('should update password criteria checklist while user types', () => {
    const passwordControl = component.form.get('password');
    passwordControl?.setValue('Abcd1234');
    fixture.detectChanges();

    const criteriaAfterStrongPassword = component.passwordCriteriaItems();
    expect(criteriaAfterStrongPassword.every((item) => item.met)).toBe(true);

    passwordControl?.setValue('weak');
    fixture.detectChanges();

    const criteriaAfterWeakPassword = component.passwordCriteriaItems();
    const minLengthCriterion = criteriaAfterWeakPassword.find((item) => item.key === 'auth.password_criteria.min_length');
    const uppercaseCriterion = criteriaAfterWeakPassword.find((item) => item.key === 'auth.password_criteria.uppercase');
    const lowercaseCriterion = criteriaAfterWeakPassword.find((item) => item.key === 'auth.password_criteria.lowercase');
    const numberCriterion = criteriaAfterWeakPassword.find((item) => item.key === 'auth.password_criteria.number');

    expect(minLengthCriterion?.met).toBe(false);
    expect(uppercaseCriterion?.met).toBe(false);
    expect(lowercaseCriterion?.met).toBe(true);
    expect(numberCriterion?.met).toBe(false);
  });

  it('should mark form invalid and not call API when submitted empty', () => {
    component.submit();
    expect(authApiMock.register).not.toHaveBeenCalled();
    expect(component.form.touched).toBe(true);
  });

  it('should mark form invalid and set passwordComplexity error when password is weak', () => {
    component.form.setValue({
      name: 'Test User',
      email: 'test@example.com',
      password: 'weakpass123',
      password_confirmation: 'weakpass123',
    });

    component.submit();

    expect(authApiMock.register).not.toHaveBeenCalled();
    expect(component.form.invalid).toBe(true);
    expect(component.form.get('password')?.errors?.['passwordComplexity']).toBe(true);
  });

  it('should navigate to /user/verify-email-pending on successful registration', () => {
    authApiMock.register.mockReturnValue(of(undefined));
    const navSpy = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    component.form.setValue({
      name: 'Test User',
      email: 'test@example.com',
      password: 'StrongPass123',
      password_confirmation: 'StrongPass123',
    });

    component.submit();

    expect(authApiMock.register).toHaveBeenCalled();
    expect(sessionStorage.getItem('pendingVerificationEmail')).toBe('test@example.com');
    expect(navSpy).toHaveBeenCalledWith('/user/verify-email-pending');
  });

  it('should apply server validation errors to form fields', () => {
    authApiMock.register.mockReturnValue(
      throwError(() => ({
        status: 422,
        error: {
          status: 422,
          code: 'validation_error',
          message: 'Validation failed',
          request_id: 'req-1',
          fieldErrors: { email: ['The email has already been taken.'] },
        },
      }))
    );
    component.form.setValue({
      name: 'Test',
      email: 'duplicate@example.com',
      password: 'StrongPass123',
      password_confirmation: 'StrongPass123',
    });

    component.submit();

    expect(component.form.get('email')?.errors?.['serverError']).toBe('The email has already been taken.');
  });

  it('should show fallback message when backend returns error without field errors', () => {
    authApiMock.register.mockReturnValue(
      throwError(() => ({
        status: 409,
        error: {
          status: 409,
          code: 'conflict',
          message: 'Registration is not available right now.',
          request_id: 'req-2',
        },
      }))
    );
    component.form.setValue({
      name: 'Test',
      email: 'duplicate@example.com',
      password: 'StrongPass123',
      password_confirmation: 'StrongPass123',
    });

    component.submit();

    expect(component.errorMessage()).toBe('Registration is not available right now.');
  });
});
