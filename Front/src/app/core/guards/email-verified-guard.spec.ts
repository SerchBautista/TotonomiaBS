import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { provideRouter } from '@angular/router';
import { ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { vi } from 'vitest';
import { emailVerifiedGuard } from './email-verified-guard';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

function runGuard(
  isLoggedIn: boolean,
  emailVerified: boolean
): ReturnType<typeof emailVerifiedGuard> {
  const authStateMock = {
    isLoggedIn: () => isLoggedIn,
    emailVerified: () => emailVerified,
    role: () => null as null,
    token: () => null as null,
  };

  TestBed.overrideProvider(AUTH_STATE_TOKEN, { useValue: authStateMock });

  return TestBed.runInInjectionContext(() =>
    emailVerifiedGuard({} as ActivatedRouteSnapshot, {} as RouterStateSnapshot)
  );
}

describe('emailVerifiedGuard', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AUTH_STATE_TOKEN, useValue: {} },
      ],
    });
  });

  it('should allow access when logged in and email verified', () => {
    const result = runGuard(true, true);
    expect(result).toBe(true);
  });

  it('should redirect to /user/verify-email-pending when logged in but email not verified', () => {
    const result = runGuard(true, false);
    const router = TestBed.inject(Router);
    expect(result).toEqual(router.parseUrl('/user/verify-email-pending'));
  });

  it('should redirect to /login when not logged in', () => {
    const result = runGuard(false, false);
    const router = TestBed.inject(Router);
    expect(result).toEqual(router.parseUrl('/login'));
  });
});
