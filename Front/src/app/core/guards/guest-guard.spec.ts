import { TestBed } from '@angular/core/testing';
import { Router, UrlTree } from '@angular/router';
import { vi } from 'vitest';
import { provideRouter } from '@angular/router';
import { guestGuard } from './guest-guard';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

function runGuard(): ReturnType<typeof guestGuard> {
  return TestBed.runInInjectionContext(() => guestGuard({} as never, {} as never));
}

describe('guestGuard', () => {
  let authMock: { isLoggedIn: ReturnType<typeof vi.fn>; role: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    authMock = {
      isLoggedIn: vi.fn(),
      role: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AUTH_STATE_TOKEN, useValue: authMock },
      ],
    });
  });

  it('should allow access when the user is not logged in', () => {
    authMock.isLoggedIn.mockReturnValue(false);

    const result = runGuard();

    expect(result).toBe(true);
  });

  it('should redirect user role to /user/dashboard when already logged in', () => {
    authMock.isLoggedIn.mockReturnValue(true);
    authMock.role.mockReturnValue('user');

    const result = runGuard();
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/user/dashboard'));
  });

  it('should redirect admin role to /admin/dashboard when already logged in', () => {
    authMock.isLoggedIn.mockReturnValue(true);
    authMock.role.mockReturnValue('admin');

    const result = runGuard();
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/admin/dashboard'));
  });

  it('should redirect to /user/dashboard when logged in with null role', () => {
    authMock.isLoggedIn.mockReturnValue(true);
    authMock.role.mockReturnValue(null);

    const result = runGuard();
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/user/dashboard'));
  });
});
