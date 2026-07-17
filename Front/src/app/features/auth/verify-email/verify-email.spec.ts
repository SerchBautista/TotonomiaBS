import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter, Router } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { throwError, of } from 'rxjs';
import { vi } from 'vitest';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { VerifyEmailComponent } from './verify-email';

function createActivatedRouteMock(params: Record<string, string>) {
  return {
    snapshot: {
      queryParamMap: {
        get: (key: string) => params[key] ?? null,
      },
    },
  };
}

describe('VerifyEmailComponent', () => {
  let fixture: ComponentFixture<VerifyEmailComponent>;
  let component: VerifyEmailComponent;
  let authApiMock: { verifyEmail: ReturnType<typeof vi.fn> };
  let router: Router;

  beforeEach(async () => {
    authApiMock = {
      verifyEmail: vi.fn().mockReturnValue(of(undefined)),
    };

    await TestBed.configureTestingModule({
      imports: [VerifyEmailComponent, TranslateModule.forRoot()],
      providers: [
        provideRouter([]),
        { provide: AuthApiService, useValue: authApiMock },
        {
          provide: ActivatedRoute,
          useValue: createActivatedRouteMock({
            id: '1',
            hash: 'hash',
            expires: '123',
            signature: 'signature',
          }),
        },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    fixture = TestBed.createComponent(VerifyEmailComponent);
    component = fixture.componentInstance;
  });

  it('shows invalid state when required params are missing', async () => {
    TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      imports: [VerifyEmailComponent, TranslateModule.forRoot()],
      providers: [
        provideRouter([]),
        { provide: AuthApiService, useValue: authApiMock },
        { provide: ActivatedRoute, useValue: createActivatedRouteMock({}) },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(VerifyEmailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.state()).toBe('invalid');
  });

  it('shows invalid state when backend reports invalid verification link code', () => {
    authApiMock.verifyEmail.mockReturnValue(
      throwError(() => ({
        status: 403,
        error: {
          status: 403,
          code: BACKEND_ERROR_CODES.emailVerificationInvalid,
          message: 'Enlace inválido',
          request_id: 'req-1',
        },
      }))
    );

    fixture.detectChanges();

    expect(component.state()).toBe('invalid');
  });

  it('shows fallback message for other verification errors', () => {
    authApiMock.verifyEmail.mockReturnValue(
      throwError(() => ({
        status: 500,
        error: {
          status: 500,
          code: 'internal_error',
          message: 'No se pudo completar la verificación',
          request_id: 'req-2',
        },
      }))
    );

    fixture.detectChanges();

    expect(component.state()).toBe('error');
    expect(component.errorMessage()).toBe('No se pudo completar la verificación');
  });
});
