import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { TranslateModule } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { VerifyEmailPendingComponent } from './verify-email-pending';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';

describe('VerifyEmailPendingComponent', () => {
  let fixture: ComponentFixture<VerifyEmailPendingComponent>;
  let component: VerifyEmailPendingComponent;
  let authApiMock: { resendVerification: ReturnType<typeof vi.fn> };
  let authStateMock: { isLoggedIn: () => boolean; role: () => null; token: () => null; emailVerified: () => boolean };

  beforeEach(async () => {
    authApiMock = { resendVerification: vi.fn() };
    authStateMock = {
      isLoggedIn: () => true,
      role: () => null,
      token: () => null,
      emailVerified: () => false,
    };

    await TestBed.configureTestingModule({
      imports: [VerifyEmailPendingComponent, TranslateModule.forRoot()],
      providers: [
        { provide: AuthApiService, useValue: authApiMock },
        { provide: AUTH_STATE_TOKEN, useValue: authStateMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(VerifyEmailPendingComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should render the resend button', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const button = compiled.querySelector('button');
    expect(button).toBeTruthy();
  });

  it('should call resendVerification and show success on click', () => {
    authApiMock.resendVerification.mockReturnValue(of(undefined));

    component.resend();

    expect(authApiMock.resendVerification).toHaveBeenCalled();
    expect(component.resentSuccess()).toBe(true);
  });

  it('should disable button while loading', () => {
    authApiMock.resendVerification.mockReturnValue(of(undefined));
    component.loading.set(true);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    expect(button.disabled).toBe(true);
  });

  it('should show normalized fallback message when resend fails', () => {
    authApiMock.resendVerification.mockReturnValue(throwError(() => ({
      status: 500,
      error: {
        status: 500,
        code: 'internal_error',
        message: 'Could not resend verification email.',
        request_id: 'req-resend',
      },
    })));

    component.resend();

    expect(component.resentSuccess()).toBe(false);
    expect(component.errorMessage()).toBe('Could not resend verification email.');
  });
});
