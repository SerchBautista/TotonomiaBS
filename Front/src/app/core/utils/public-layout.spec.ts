import { describe, expect, it } from 'vitest';
import {
  isLandingRoute,
  isPublicAuthRoute,
  isPublicLayoutRoute,
  usesPublicShell,
} from './public-layout';

describe('public-layout', () => {
  it('treats learn and pricing routes as public layout routes', () => {
    expect(isPublicLayoutRoute('/')).toBe(true);
    expect(isPublicLayoutRoute('/learn')).toBe(true);
    expect(isPublicLayoutRoute('/learn/expense-tracking')).toBe(true);
    expect(isPublicLayoutRoute('/pricing')).toBe(true);
    expect(isPublicLayoutRoute('/pricing/success')).toBe(true);
    expect(isPublicLayoutRoute('/login')).toBe(false);
    expect(isPublicLayoutRoute('/user/dashboard')).toBe(false);
  });

  it('treats all public shell routes as landing routes', () => {
    expect(isLandingRoute('/')).toBe(true);
    expect(isLandingRoute('/learn')).toBe(true);
    expect(isLandingRoute('/learn/expense-tracking')).toBe(true);
    expect(isLandingRoute('/pricing')).toBe(true);
    expect(isLandingRoute('/pricing/success')).toBe(true);
    expect(isLandingRoute('/login')).toBe(true);
    expect(isLandingRoute('/register')).toBe(true);
    expect(isLandingRoute('/forgot-password')).toBe(true);
    expect(isLandingRoute('/user/reset-password')).toBe(true);
    expect(isLandingRoute('/user/verify-email')).toBe(true);
    expect(isLandingRoute('/admin/login')).toBe(true);
    expect(isLandingRoute('/user/dashboard')).toBe(false);
    expect(isLandingRoute('/user/verify-email-pending')).toBe(false);
  });

  it('treats auth routes as public shell routes', () => {
    expect(isPublicAuthRoute('/login')).toBe(true);
    expect(isPublicAuthRoute('/register')).toBe(true);
    expect(isPublicAuthRoute('/forgot-password')).toBe(true);
    expect(usesPublicShell('/login')).toBe(true);
    expect(usesPublicShell('/learn/budgets')).toBe(true);
    expect(isLandingRoute('/login')).toBe(true);
  });
});
