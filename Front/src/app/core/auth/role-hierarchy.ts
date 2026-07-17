export type UserRole = 'admin' | 'user';

export function effectiveRoles(role: UserRole | null): Set<UserRole> {
  if (role === 'admin') return new Set<UserRole>(['admin', 'user']);
  if (role === 'user') return new Set<UserRole>(['user']);
  return new Set<UserRole>();
}

export function isHierarchyCompliant(
  actual: UserRole | null,
  expected: UserRole,
): boolean {
  return actual === expected || (expected === 'user' && actual === 'admin');
}
