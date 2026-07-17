import { InjectionToken } from '@angular/core';
import { UserRole } from '../auth/role-hierarchy';

export interface AuthState {
  isLoggedIn: () => boolean;
  role: () => UserRole | null;
  token: () => string | null;
  emailVerified: () => boolean;
  userId: () => string | null;
  defaultWorkspaceId: () => string | null;
  permissions: () => string[];
  hasPermission: (name: string) => boolean;
}

export const AUTH_STATE_TOKEN = new InjectionToken<AuthState>('AuthState');
