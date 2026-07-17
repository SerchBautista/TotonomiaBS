import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

const TWO_FACTOR_SESSION_STORAGE_KEY = 'two_factor_session_token';

export const twoFactorSessionGuard: CanActivateFn = () => {
  const router = inject(Router);
  const sessionToken = sessionStorage.getItem(TWO_FACTOR_SESSION_STORAGE_KEY);

  if (!sessionToken) {
    return router.parseUrl('/login');
  }

  return true;
};
