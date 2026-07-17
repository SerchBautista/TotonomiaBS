import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

export const emailVerifiedGuard: CanActivateFn = () => {
  const authState = inject(AUTH_STATE_TOKEN);
  const router = inject(Router);

  if (!authState.isLoggedIn()) {
    return router.parseUrl('/login');
  }

  if (!authState.emailVerified()) {
    return router.parseUrl('/user/verify-email-pending');
  }

  return true;
};
