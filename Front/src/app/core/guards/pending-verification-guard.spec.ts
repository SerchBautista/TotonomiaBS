import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { provideRouter } from '@angular/router';
import { vi } from 'vitest';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';
import { pendingVerificationGuard } from './pending-verification-guard';

function runGuard(): ReturnType<typeof pendingVerificationGuard> {
  return TestBed.runInInjectionContext(() => pendingVerificationGuard({} as never, {} as never));
}

describe('pendingVerificationGuard', () => {
  let authMock: { isLoggedIn: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    sessionStorage.clear();

    authMock = {
      isLoggedIn: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AUTH_STATE_TOKEN, useValue: authMock },
      ],
    });
  });

  afterEach(() => {
    sessionStorage.clear();
  });

  it('should allow access when the user is logged in', () => {
    authMock.isLoggedIn.mockReturnValue(true);

    const result = runGuard();

    expect(result).toBe(true);
  });

  it('should allow access when there is a pending verification email in session storage', () => {
    authMock.isLoggedIn.mockReturnValue(false);
    sessionStorage.setItem('pendingVerificationEmail', 'test@example.com');

    const result = runGuard();

    expect(result).toBe(true);
  });

  it('should redirect to /login when the user is logged out and has no pending verification email', () => {
    authMock.isLoggedIn.mockReturnValue(false);

    const result = runGuard();
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/login'));
  });
});
