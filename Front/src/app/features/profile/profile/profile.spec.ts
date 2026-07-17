import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiService } from '../../../core/services/api';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';
import { ProfileComponent } from './profile';

describe('ProfileComponent', () => {
  let fixture: ComponentFixture<ProfileComponent>;
  let component: ProfileComponent;
  let apiMock: { get: ReturnType<typeof vi.fn> };
  let preferencesMock: {
    saveToBackend: ReturnType<typeof vi.fn>;
    getAvailableTimezones: ReturnType<typeof vi.fn>;
  };
  let authStateMock: { setPlan: ReturnType<typeof vi.fn> };
  let authApiMock: { toggleTwoFactor: ReturnType<typeof vi.fn> };

  function mockProfileAndSubscriptionCalls(
    profileOverrides: Partial<{
      theme: 'dark' | 'light';
      locale: 'es' | 'en';
      timezone: string;
      two_factor_enabled: boolean;
    }> = {},
  ) {
    apiMock.get.mockImplementation((path: string) => {
      if (path === '/user/profile') {
        return of({
          data: {
            user: {
              id: '1',
              name: 'Test',
              email: 'test@example.com',
              role: 'user',
              plan: 'free',
              theme: profileOverrides.theme ?? 'dark',
              locale: profileOverrides.locale ?? 'es',
              timezone: profileOverrides.timezone ?? 'UTC',
              two_factor_enabled: profileOverrides.two_factor_enabled ?? false,
            },
          },
        });
      }

      return of({
        plan: 'free',
        subscription_ends_at: null,
        payments: [],
      });
    });
  }

  beforeEach(() => {
    vi.clearAllMocks();

    apiMock = { get: vi.fn() };
    preferencesMock = {
      saveToBackend: vi.fn().mockReturnValue(
        of({
          message: 'Preferencias actualizadas correctamente',
          data: { user: { theme: 'light', locale: 'en', timezone: 'America/Bogota' } },
        }),
      ),
      getAvailableTimezones: vi
        .fn()
        .mockReturnValue(['UTC', 'America/Mexico_City', 'America/Bogota']),
    };
    authStateMock = { setPlan: vi.fn() };
    authApiMock = {
      toggleTwoFactor: vi
        .fn()
        .mockReturnValue(of({ message: 'OK', data: { two_factor_enabled: true } })),
    };

    TestBed.configureTestingModule({
      imports: [ProfileComponent, TranslateModule.forRoot()],
      providers: [
        provideRouter([]),
        { provide: ApiService, useValue: apiMock },
        { provide: AuthApiService, useValue: authApiMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: UserPreferencesService, useValue: preferencesMock },
      ],
    });

    fixture = TestBed.createComponent(ProfileComponent);
    component = fixture.componentInstance;
  });

  it('should load profile and subscription on init', () => {
    mockProfileAndSubscriptionCalls({
      theme: 'light',
      locale: 'en',
      timezone: 'America/Mexico_City',
    });

    fixture.detectChanges();

    expect(component.profile()?.name).toBe('Test');
    expect(component.theme()).toBe('light');
    expect(component.locale()).toBe('en');
    expect(component.timezone()).toBe('America/Mexico_City');
    expect(authStateMock.setPlan).toHaveBeenCalledWith('free');
  });

  it('should expose load error when subscription fetch fails without overwriting auth plan', () => {
    apiMock.get.mockImplementation((path: string) => {
      if (path === '/user/profile') {
        return of({
          data: {
            user: {
              id: '1',
              name: 'Test',
              email: 'test@example.com',
              role: 'user',
              plan: 'premium',
              theme: 'dark',
              locale: 'es',
              timezone: 'UTC',
              two_factor_enabled: false,
            },
          },
        });
      }

      return throwError(() => ({
        status: 503,
        error: {
          status: 503,
          code: 'internal_error',
          message: 'Subscription service unavailable',
          request_id: 'req-subscription',
        },
      }));
    });

    fixture.detectChanges();

    expect(component.profile()?.email).toBe('test@example.com');
    expect(component.subscription().payments).toEqual([]);
    expect(component.loadMessage()).toBe('Subscription service unavailable');
    expect(authStateMock.setPlan).not.toHaveBeenCalled();
  });

  it('should save preferences when button is clicked', () => {
    mockProfileAndSubscriptionCalls();

    fixture.detectChanges();
    component.theme.set('light');
    component.locale.set('en');
    component.timezone.set('America/Bogota');
    component.onSavePreferences();

    expect(preferencesMock.saveToBackend).toHaveBeenCalledWith({
      theme: 'light',
      locale: 'en',
      timezone: 'America/Bogota',
    });
    expect(component.saveStatus()).toBe('success');
    expect(component.saveMessage()).toBe('Preferencias actualizadas correctamente');
  });

  it('should filter timezones based on query', () => {
    mockProfileAndSubscriptionCalls();

    fixture.detectChanges();
    component.timezoneQuery.set('Mexico');

    expect(component.filteredTimezones()).toContain('America/Mexico_City');
    expect(component.filteredTimezones()).not.toContain('UTC');
  });

  it('should show normalized error message when saving preferences fails', () => {
    mockProfileAndSubscriptionCalls();
    preferencesMock.saveToBackend.mockReturnValue(
      throwError(() => ({
        status: 500,
        code: 'internal_error',
        message: 'No fue posible guardar las preferencias',
        requestId: 'req-1',
        fieldErrors: null,
        meta: null,
        isStandardized: true,
        original: null,
      })),
    );

    fixture.detectChanges();
    component.onSavePreferences();

    expect(component.saveStatus()).toBe('error');
    expect(component.saveMessage()).toBe('No fue posible guardar las preferencias');
  });

  it('should open password modal when toggle is requested', () => {
    mockProfileAndSubscriptionCalls({ two_factor_enabled: false });

    fixture.detectChanges();
    component.onToggleTwoFactor();

    expect(component.showPasswordModal()).toBe(true);
    expect(component.twoFactorToggleError()).toBeNull();
    expect(component.password()).toBe('');
  });

  it('should call toggleTwoFactor with pending state and password', () => {
    mockProfileAndSubscriptionCalls({ two_factor_enabled: false });

    fixture.detectChanges();
    component.onToggleTwoFactor();
    component.password.set('password123');
    component.onPasswordConfirm(component.password());

    expect(authApiMock.toggleTwoFactor).toHaveBeenCalledWith(true, 'password123');
  });

  it('should ignore empty password submissions', () => {
    mockProfileAndSubscriptionCalls({ two_factor_enabled: false });

    fixture.detectChanges();
    component.onToggleTwoFactor();
    component.onPasswordConfirm('');

    expect(authApiMock.toggleTwoFactor).not.toHaveBeenCalled();
  });

  it('should update profile state and success message after enabling 2fa', () => {
    mockProfileAndSubscriptionCalls({ two_factor_enabled: false });

    fixture.detectChanges();
    component.onToggleTwoFactor();
    component.onPasswordConfirm('password123');

    expect(component.profile()?.two_factor_enabled).toBe(true);
    expect(component.showPasswordModal()).toBe(false);
    expect(component.password()).toBe('');
    expect(component.twoFactorSuccessMessage()).toBe('profile.security.2fa_enabled_success');
  });

  it('should keep modal open and show password error on invalid password', () => {
    mockProfileAndSubscriptionCalls({ two_factor_enabled: true });
    authApiMock.toggleTwoFactor.mockReturnValue(
      throwError(() => ({
        status: 422,
        code: 'invalid_password',
        message: 'Invalid password',
        requestId: 'req-2',
        fieldErrors: { password: ['La contraseña es incorrecta'] },
        meta: null,
        isStandardized: true,
        original: null,
      })),
    );

    fixture.detectChanges();
    component.onToggleTwoFactor();
    component.onPasswordConfirm('wrong-password');

    expect(component.showPasswordModal()).toBe(true);
    expect(component.twoFactorToggleError()).toBe('La contraseña es incorrecta');
  });

  it('should reset toggle state when password modal is cancelled', () => {
    mockProfileAndSubscriptionCalls({ two_factor_enabled: false });

    fixture.detectChanges();
    component.onToggleTwoFactor();
    component.password.set('something');
    component.twoFactorToggleError.set('Some error');
    component.twoFactorToggleLoading.set(true);

    component.onPasswordCancel();

    expect(component.showPasswordModal()).toBe(false);
    expect(component.twoFactorToggleError()).toBeNull();
    expect(component.twoFactorToggleLoading()).toBe(false);
    expect(component.password()).toBe('');
  });

  it('should render the redesigned page header', () => {
    mockProfileAndSubscriptionCalls();
    fixture.detectChanges();

    const header = fixture.nativeElement.querySelector('app-page-header .page-header__title');
    expect(header?.textContent?.trim()).toBe('profile.title');
  });
});
