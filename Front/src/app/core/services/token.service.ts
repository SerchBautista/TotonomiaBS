import { inject, Injectable } from '@angular/core';
import { STORAGE_SERVICE_TOKEN } from '../tokens/storage.token';

@Injectable({
  providedIn: 'root'
})
export class TokenService {
  private readonly storage = inject(STORAGE_SERVICE_TOKEN);

  getToken(): string | null {
    return this.storage.getItem('token');
  }

  setToken(token: string): void {
    this.storage.setItem('token', token);
  }

  removeToken(): void {
    this.storage.removeItem('token');
  }

  getRole(): string | null {
    return this.storage.getItem('role');
  }

  setRole(role: string): void {
    this.storage.setItem('role', role);
  }

  removeRole(): void {
    this.storage.removeItem('role');
  }

  getPlan(): string | null {
    return this.storage.getItem('plan');
  }

  setPlan(plan: string): void {
    this.storage.setItem('plan', plan);
  }

  removePlan(): void {
    this.storage.removeItem('plan');
  }

  getUserId(): string | null {
    return this.storage.getItem('userId');
  }

  setUserId(id: string): void {
    this.storage.setItem('userId', id);
  }

  removeUserId(): void {
    this.storage.removeItem('userId');
  }

  getDefaultWorkspaceId(): string | null {
    return this.storage.getItem('defaultWorkspaceId');
  }

  setDefaultWorkspaceId(id: string): void {
    this.storage.setItem('defaultWorkspaceId', id);
  }

  removeDefaultWorkspaceId(): void {
    this.storage.removeItem('defaultWorkspaceId');
  }

  getEmailVerified(): boolean {
    return this.storage.getItem('emailVerified') === 'true';
  }

  setEmailVerified(verified: boolean): void {
    this.storage.setItem('emailVerified', String(verified));
  }

  removeEmailVerified(): void {
    this.storage.removeItem('emailVerified');
  }

  getPermissions(): string[] {
    const raw = this.storage.getItem('permissions');
    if (!raw) return [];
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }

  setPermissions(permissions: string[]): void {
    this.storage.setItem('permissions', JSON.stringify(permissions));
  }

  removePermissions(): void {
    this.storage.removeItem('permissions');
  }
}
