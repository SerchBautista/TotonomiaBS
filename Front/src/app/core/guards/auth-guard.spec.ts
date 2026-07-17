import { TestBed } from '@angular/core/testing';
import { Router, ActivatedRouteSnapshot, UrlTree } from '@angular/router';
import { vi } from 'vitest';
import { provideRouter } from '@angular/router';
import { authGuard } from './auth-guard';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

function runGuard(routeData: Record<string, unknown> = {}): ReturnType<typeof authGuard> {
  const route = { data: routeData } as unknown as ActivatedRouteSnapshot;
  return TestBed.runInInjectionContext(() => authGuard(route, {} as never));
}

describe('authGuard', () => {
  let authMock: { isLoggedIn: ReturnType<typeof vi.fn>; role: ReturnType<typeof vi.fn>; token: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    authMock = {
      isLoggedIn: vi.fn(),
      role: vi.fn(),
      token: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AUTH_STATE_TOKEN, useValue: authMock },
      ],
    });
  });

  it('should allow access when user is logged in', () => {
    authMock.isLoggedIn.mockReturnValue(true);

    const result = runGuard();

    expect(result).toBe(true);
  });

  it('should redirect to /login when user is not logged in', () => {
    authMock.isLoggedIn.mockReturnValue(false);

    const result = runGuard();
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/login'));
  });

  it('should redirect to custom loginRedirect route when provided', () => {
    authMock.isLoggedIn.mockReturnValue(false);

    const result = runGuard({ loginRedirect: '/admin/login' });
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/admin/login'));
  });
});
