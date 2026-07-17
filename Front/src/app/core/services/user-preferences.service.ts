import { inject, Injectable, signal } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { Observable } from 'rxjs';
import { ApiService } from './api';

export interface UserPreferences {
  theme: 'dark' | 'light';
  locale: 'es' | 'en';
  timezone: string;
}

const STORAGE_KEYS = {
  theme: 'fintech-theme',
  locale: 'app_lang',
  timezone: 'fintech-timezone',
};

const DEFAULTS: UserPreferences = {
  theme: 'dark',
  locale: 'es',
  timezone: 'UTC',
};

@Injectable({
  providedIn: 'root',
})
export class UserPreferencesService {
  private readonly apiService = inject(ApiService);
  private readonly translate = inject(TranslateService);

  readonly theme = signal<UserPreferences['theme']>(this.loadFromStorage('theme'));
  readonly locale = signal<UserPreferences['locale']>(this.loadFromStorage('locale'));
  readonly timezone = signal<string>(this.loadFromStorage('timezone'));

  private loadFromStorage<K extends keyof UserPreferences>(key: K): UserPreferences[K] {
    if (typeof window === 'undefined') {
      return DEFAULTS[key];
    }
    const value = window.localStorage.getItem(STORAGE_KEYS[key]);
    if (key === 'theme' && (value === 'dark' || value === 'light')) {
      return value as UserPreferences[K];
    }
    if (key === 'locale' && (value === 'es' || value === 'en')) {
      return value as UserPreferences[K];
    }
    if (key === 'timezone' && value) {
      return value as UserPreferences[K];
    }
    return DEFAULTS[key];
  }

  private saveToStorage<K extends keyof UserPreferences>(key: K, value: UserPreferences[K]): void {
    if (typeof window === 'undefined') {
      return;
    }
    window.localStorage.setItem(STORAGE_KEYS[key], value);
  }

  applyTheme(theme: 'dark' | 'light'): void {
    this.theme.set(theme);
    this.saveToStorage('theme', theme);
    if (typeof document !== 'undefined') {
      document.body.dataset['theme'] = theme;
      document.documentElement.style.colorScheme = theme;
    }
  }

  applyLocale(locale: 'es' | 'en'): void {
    this.locale.set(locale);
    this.saveToStorage('locale', locale);
    this.translate.use(locale);
  }

  applyTimezone(timezone: string): void {
    this.timezone.set(timezone);
    this.saveToStorage('timezone', timezone);
  }

  applyAll(preferences: Partial<UserPreferences>): void {
    if (preferences.theme) {
      this.applyTheme(preferences.theme);
    }
    if (preferences.locale) {
      this.applyLocale(preferences.locale);
    }
    if (preferences.timezone) {
      this.applyTimezone(preferences.timezone);
    }
  }

  loadFromBackend(): void {
    this.apiService
      .get<{ data: { user: UserPreferences } }>('/user/profile')
      .subscribe({
        next: (response) => {
          const prefs = response.data.user;
          this.applyAll({
            theme: prefs.theme,
            locale: prefs.locale,
            timezone: prefs.timezone,
          });
        },
        error: () => {
          // Si falla, mantener las preferencias del localStorage
        },
      });
  }

  saveToBackend(preferences: UserPreferences): Observable<{ message: string; data: { user: UserPreferences } }> {
    return this.apiService
      .put<{ message: string; data: { user: UserPreferences } }>('/user/profile', preferences);
  }

  getAvailableTimezones(): string[] {
    if (typeof Intl !== 'undefined' && 'supportedValuesOf' in Intl) {
      try {
        return (Intl as unknown as { supportedValuesOf(key: string): string[] }).supportedValuesOf('timeZone');
      } catch {
        return this.fallbackTimezones();
      }
    }
    return this.fallbackTimezones();
  }

  private fallbackTimezones(): string[] {
    return [
      'UTC',
      'America/Mexico_City',
      'America/Bogota',
      'America/Lima',
      'America/Santiago',
      'America/Buenos_Aires',
      'America/Sao_Paulo',
      'America/New_York',
      'America/Chicago',
      'America/Denver',
      'America/Los_Angeles',
      'Europe/Madrid',
      'Europe/London',
      'Europe/Paris',
      'Europe/Berlin',
      'Asia/Tokyo',
      'Asia/Shanghai',
      'Asia/Dubai',
      'Australia/Sydney',
    ];
  }
}
