import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { UserPreferencesService } from './user-preferences.service';
import { TranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { ApiService } from './api';

describe('UserPreferencesService', () => {
  let service: UserPreferencesService;
  let apiMock: { get: ReturnType<typeof vi.fn>; put: ReturnType<typeof vi.fn> };
  let translateMock: { use: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    apiMock = {
      get: vi.fn(),
      put: vi.fn(),
    };
    translateMock = {
      use: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        { provide: ApiService, useValue: apiMock },
        { provide: TranslateService, useValue: translateMock },
        UserPreferencesService,
      ],
    });

    service = TestBed.inject(UserPreferencesService);
  });

  afterEach(() => {
    localStorage.clear();
  });

  it('should load defaults when localStorage is empty', () => {
    expect(service.theme()).toBe('dark');
    expect(service.locale()).toBe('es');
    expect(service.timezone()).toBe('UTC');
  });

  it('should apply theme and persist to localStorage', () => {
    service.applyTheme('light');
    expect(service.theme()).toBe('light');
    expect(localStorage.getItem('fintech-theme')).toBe('light');
  });

  it('should apply locale and call translate.use', () => {
    service.applyLocale('en');
    expect(service.locale()).toBe('en');
    expect(translateMock.use).toHaveBeenCalledWith('en');
    expect(localStorage.getItem('app_lang')).toBe('en');
  });

  it('should apply timezone and persist to localStorage', () => {
    service.applyTimezone('America/Mexico_City');
    expect(service.timezone()).toBe('America/Mexico_City');
    expect(localStorage.getItem('fintech-timezone')).toBe('America/Mexico_City');
  });

  it('should load preferences from backend and apply them', () => {
    apiMock.get.mockReturnValue(
      of({
        data: {
          user: {
            theme: 'light',
            locale: 'en',
            timezone: 'America/Bogota',
          },
        },
      })
    );

    service.loadFromBackend();

    expect(apiMock.get).toHaveBeenCalledWith('/user/profile');
    expect(service.theme()).toBe('light');
    expect(service.locale()).toBe('en');
    expect(service.timezone()).toBe('America/Bogota');
  });

  it('should return an observable when saving to backend', () => {
    const mockResponse = {
      message: 'Preferencias actualizadas correctamente',
      data: {
        user: {
          theme: 'dark',
          locale: 'es',
          timezone: 'UTC',
        },
      },
    };
    apiMock.put.mockReturnValue(of(mockResponse));

    const observable = service.saveToBackend({ theme: 'dark', locale: 'es', timezone: 'UTC' });
    let result: typeof mockResponse | undefined;
    observable.subscribe((v) => (result = v));

    expect(apiMock.put).toHaveBeenCalledWith('/user/profile', {
      theme: 'dark',
      locale: 'es',
      timezone: 'UTC',
    });
    expect(result).toEqual(mockResponse);
  });

  it('should return a non-empty list of timezones', () => {
    const timezones = service.getAvailableTimezones();
    expect(timezones.length).toBeGreaterThan(0);
  });
});
