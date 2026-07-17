import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

export const authGuard: CanActivateFn = (route) => {
  const authState = inject(AUTH_STATE_TOKEN);
  const router = inject(Router);

  if (authState.isLoggedIn()) {
    return true;
  }

  const loginRedirect = (route.data?.['loginRedirect'] as string) ?? '/login';
  return router.parseUrl(loginRedirect);
};
