import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

const PENDING_VERIFICATION_EMAIL_KEY = 'pendingVerificationEmail';

export const pendingVerificationGuard: CanActivateFn = () => {
  const authState = inject(AUTH_STATE_TOKEN);
  const router = inject(Router);

  if (authState.isLoggedIn()) {
    return true;
  }

  if (sessionStorage.getItem(PENDING_VERIFICATION_EMAIL_KEY)) {
    return true;
  }

  return router.parseUrl('/login');
};
