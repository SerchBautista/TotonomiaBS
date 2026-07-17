export function isPublicLayoutRoute(url: string): boolean {
  const path = url.split('?')[0].split('#')[0];

  if (path === '/' || path === '/learn' || path === '/pricing' || path === '/pricing/success') {
    return true;
  }

  return path.startsWith('/learn/');
}

export function isPublicAuthRoute(url: string): boolean {
  const path = url.split('?')[0].split('#')[0];
  return (
    path === '/login' ||
    path === '/register' ||
    path === '/forgot-password' ||
    path === '/user/reset-password' ||
    path === '/user/verify-email' ||
    path === '/admin/login'
  );
}

export function usesPublicShell(url: string): boolean {
  return isPublicLayoutRoute(url) || isPublicAuthRoute(url);
}

/** Public pages that use the integrated landing layout (header inside 1120px container). */
export function isLandingRoute(url: string): boolean {
  return usesPublicShell(url);
}
