import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { effectiveRoles, UserRole } from '../auth/role-hierarchy';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

/**
 * Jerarquía efectiva de roles: admin ⊇ user (unidireccional).
 * Un admin satisface cualquier check que pida 'user'.
 * Un user NO satisface checks que pidan 'admin'.
 */

export const roleGuard: CanActivateFn = (route) => {
  const authState = inject(AUTH_STATE_TOKEN);
  const router = inject(Router);

  const expectedRoles = (route.data?.['roles'] as UserRole[]) ?? [];
  const owned = effectiveRoles(authState.role());

  if (expectedRoles.some((r) => owned.has(r))) {
    return true;
  }

  const loginRedirect = (route.data?.['loginRedirect'] as string) ?? '/login';
  return router.parseUrl(loginRedirect);
};
