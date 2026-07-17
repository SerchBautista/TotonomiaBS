import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormControl, FormGroup } from '@angular/forms';
import { FormsModule } from '@angular/forms';
import { TranslateLoader, TranslateModule, TranslateService, TranslationObject } from '@ngx-translate/core';
import { Observable, of, Subject } from 'rxjs';
import { vi } from 'vitest';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { AdminAuthService } from '../../../core/services/admin-auth';
import { AdminLoginComponent } from './admin-login';

class StubTranslateLoader implements TranslateLoader {
  getTranslation(): Observable<TranslationObject> {
    return of({
      auth: {
        errors: {
          role_mismatch: 'Your account does not have permission to access this area.',
        },
      },
    });
  }
}

function createValidFormMock() {
  const control = new FormGroup({
    email: new FormControl('admin@example.com'),
    password: new FormControl('StrongPass123'),
  });

  return {
    invalid: false,
    control,
  };
}

function createInvalidFormMock() {
  return {
    invalid: true,
    control: {
      markAllAsTouched: vi.fn(),
      errors: null,
      setErrors: vi.fn(),
      get: vi.fn().mockReturnValue(null),
    },
  };
}

describe('AdminLoginComponent', () => {
  let fixture: ComponentFixture<AdminLoginComponent>;
  let component: AdminLoginComponent;
  let adminAuthMock: { login: ReturnType<typeof vi.fn> };
  let translate: TranslateService;

  beforeEach(async () => {
    adminAuthMock = { login: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [
        AdminLoginComponent,
        FormsModule,
        TranslateModule.forRoot({
          loader: { provide: TranslateLoader, useClass: StubTranslateLoader },
        }),
      ],
      providers: [
        { provide: AdminAuthService, useValue: adminAuthMock },
      ],
    }).compileComponents();

    translate = TestBed.inject(TranslateService);
    translate.use('en');

    fixture = TestBed.createComponent(AdminLoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should skip submit and mark controls touched when form is invalid', () => {
    const form = createInvalidFormMock();

    component.submit(form as never);

    expect(form.control.markAllAsTouched).toHaveBeenCalled();
    expect(adminAuthMock.login).not.toHaveBeenCalled();
  });

  it('should submit valid credentials and clear previous UI errors', () => {
    adminAuthMock.login.mockReturnValue(of({ error: null }));
    const form = createValidFormMock();
    form.control.get('email')?.setErrors({ serverError: 'Previous server error' });
    component.errorMessage.set('Previous error');
    component.email = 'admin@example.com';
    component.password = 'StrongPass123';

    component.submit(form as never);

    expect(adminAuthMock.login).toHaveBeenCalledWith('admin@example.com', 'StrongPass123');
    expect(form.control.get('email')?.errors).toBeNull();
    expect(component.errorMessage()).toBe('');
    expect(component.loading()).toBe(false);
  });

  it('should show normalized backend message and apply field errors on failed admin login', () => {
    adminAuthMock.login.mockReturnValue(of({
      error: {
        status: 422,
        code: 'validation_error',
        message: 'Validation failed.',
        requestId: 'req-admin',
        fieldErrors: {
          email: ['Admin email is required.'],
        },
        meta: null,
        isStandardized: true,
        original: null,
      },
    }));

    const form = createValidFormMock();
    component.email = 'admin@example.com';
    component.password = 'StrongPass123';
    component.submit(form as never);

    expect(form.control.get('email')?.errors).toEqual({ serverError: 'Admin email is required.' });
    expect(component.errorMessage()).toBe('Validation failed.');
  });

  it('should keep loading state in sync while request is in flight', () => {
    const login$ = new Subject<{ error: null }>();
    adminAuthMock.login.mockReturnValue(login$.asObservable());
    const form = createValidFormMock();
    component.email = 'admin@example.com';
    component.password = 'StrongPass123';

    component.submit(form as never);

    expect(component.loading()).toBe(true);

    login$.next({ error: null });
    login$.complete();

    expect(component.loading()).toBe(false);
  });

  it('should show the translated role-mismatch message when the service returns auth_role_mismatch', () => {
    adminAuthMock.login.mockReturnValue(of({
      error: {
        status: 403,
        code: BACKEND_ERROR_CODES.authRoleMismatch,
        message: 'Expected role admin, got user on /auth/admin/login',
        requestId: null,
        fieldErrors: null,
        meta: { endpoint: '/auth/admin/login', expectedRole: 'admin', actualRole: 'user' },
        isStandardized: false,
        original: null,
      },
    }));

    const form = createValidFormMock();
    component.email = 'admin@example.com';
    component.password = 'StrongPass123';
    component.submit(form as never);

    expect(component.errorMessage()).toBe('Your account does not have permission to access this area.');
    // The internal mismatch message should NOT leak to the user.
    expect(component.errorMessage()).not.toContain('Expected role');
  });
});
