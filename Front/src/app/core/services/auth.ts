import { Injectable, computed, inject } from '@angular/core';
import { AuthState } from '../tokens/auth-state.token';
import { AuthStateService } from './auth-state.service';
import { AuthApiService } from './auth-api.service';
import type { UserRole } from '../auth/role-hierarchy';

export type { UserRole } from '../auth/role-hierarchy';

/**
 * Thin facade kept for backward compatibility.
 * Delegates all state to AuthStateService and all API calls to AuthApiService.
 * Prefer injecting AUTH_STATE_TOKEN or the individual services directly.
 */
@Injectable({
  providedIn: 'root'
})
export class AuthService implements AuthState {
  private readonly authState = inject(AuthStateService);
  private readonly authApi = inject(AuthApiService);

  readonly isLoggedIn = computed(() => this.authState.isLoggedIn());

  token(): string | null {
    return this.authState.token();
  }

  role(): UserRole | null {
    return this.authState.role();
  }

  emailVerified(): boolean {
    return this.authState.emailVerified();
  }

  userId(): string | null {
    return this.authState.userId();
  }

  defaultWorkspaceId(): string | null {
    return this.authState.defaultWorkspaceId();
  }

  permissions(): string[] {
    return this.authState.permissions();
  }

  hasPermission(name: string): boolean {
    return this.authState.hasPermission(name);
  }

  login(email: string, password: string) {
    return this.authApi.loginAsUser(email, password);
  }

  loginAsUser(email: string, password: string) {
    return this.authApi.loginAsUser(email, password);
  }

  loginAsAdmin(email: string, password: string) {
    return this.authApi.loginAsAdmin(email, password);
  }

  logout(): void {
    this.authApi.logout();
  }
}
