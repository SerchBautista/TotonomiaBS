import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

/**
 * Prevents authenticated users from accessing guest-only routes (login pages).
 * Redirects to the appropriate home based on role:
 *   - 'admin' → /admin/dashboard
 *   - 'user'  → /user/dashboard
 * If no token is present, access is granted.
 */
export const guestGuard: CanActivateFn = () => {
  const authState = inject(AUTH_STATE_TOKEN);
  const router = inject(Router);

  if (!authState.isLoggedIn()) {
    return true;
  }

  const role = authState.role();
  const redirect = role === 'admin' ? '/admin/dashboard' : '/user/dashboard';
  return router.parseUrl(redirect);
};
