import { Injectable, InjectionToken } from '@angular/core';

export interface StorageService {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

@Injectable({ providedIn: 'root' })
export class BrowserStorageService implements StorageService {
  getItem(key: string): string | null {
    return localStorage.getItem(key);
  }

  setItem(key: string, value: string): void {
    localStorage.setItem(key, value);
  }

  removeItem(key: string): void {
    localStorage.removeItem(key);
  }
}

export const STORAGE_SERVICE_TOKEN = new InjectionToken<StorageService>('StorageService');
