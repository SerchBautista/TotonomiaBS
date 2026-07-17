import { TestBed } from '@angular/core/testing';
import { Router, ActivatedRouteSnapshot } from '@angular/router';
import { vi } from 'vitest';
import { provideRouter } from '@angular/router';
import { roleGuard } from './role-guard';
import { AUTH_STATE_TOKEN } from '../tokens/auth-state.token';

function runGuard(routeData: Record<string, unknown> = {}): ReturnType<typeof roleGuard> {
  const route = { data: routeData } as unknown as ActivatedRouteSnapshot;
  return TestBed.runInInjectionContext(() => roleGuard(route, {} as never));
}

describe('roleGuard', () => {
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

  it('should allow access when user has the required role', () => {
    authMock.role.mockReturnValue('admin');

    const result = runGuard({ roles: ['admin'] });

    expect(result).toBe(true);
  });

  it('should allow admin to access user-only routes', () => {
    authMock.role.mockReturnValue('admin');

    const result = runGuard({ roles: ['user'], loginRedirect: '/login' });

    expect(result).toBe(true);
  });

  it('should allow regular user to access user-only routes', () => {
    authMock.role.mockReturnValue('user');

    const result = runGuard({ roles: ['user'], loginRedirect: '/login' });

    expect(result).toBe(true);
  });

  it('should allow access when user matches one of multiple required roles', () => {
    authMock.role.mockReturnValue('user');

    const result = runGuard({ roles: ['admin', 'user'] });

    expect(result).toBe(true);
  });

  it('should redirect when user does not have the required role', () => {
    authMock.role.mockReturnValue('user');

    const result = runGuard({ roles: ['admin'] });
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/login'));
  });

  it('should redirect to custom loginRedirect when role does not match', () => {
    authMock.role.mockReturnValue('user');

    const result = runGuard({ roles: ['admin'], loginRedirect: '/admin/login' });
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/admin/login'));
  });

  it('should redirect when user role is null', () => {
    authMock.role.mockReturnValue(null);

    const result = runGuard({ roles: ['admin'] });
    const router = TestBed.inject(Router);

    expect(result).toEqual(router.parseUrl('/login'));
  });
});
