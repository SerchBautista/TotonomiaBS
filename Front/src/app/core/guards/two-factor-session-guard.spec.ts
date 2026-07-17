import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { twoFactorSessionGuard } from './two-factor-session-guard';

describe('twoFactorSessionGuard', () => {
  let router: Router;

  beforeEach(() => {
    sessionStorage.removeItem('two_factor_session_token');

    TestBed.configureTestingModule({
      providers: [provideRouter([])],
    });

    router = TestBed.inject(Router);
  });

  it('should allow activation when session token exists', () => {
    sessionStorage.setItem('two_factor_session_token', 'test-token');

    const result = TestBed.runInInjectionContext(() =>
      twoFactorSessionGuard({} as never, {} as never)
    );

    expect(result).toBe(true);
  });

  it('should redirect to login when session token is missing', () => {
    const parseUrlSpy = vi.spyOn(router, 'parseUrl');

    const result = TestBed.runInInjectionContext(() =>
      twoFactorSessionGuard({} as never, {} as never)
    );

    expect(parseUrlSpy).toHaveBeenCalledWith('/login');
    expect(result).not.toBe(true);
  });
});
