import { effectiveRoles, isHierarchyCompliant } from './role-hierarchy';

describe('role-hierarchy', () => {
  it('expands admin role to include user capabilities', () => {
    expect(effectiveRoles('admin')).toEqual(new Set(['admin', 'user']));
    expect(effectiveRoles('user')).toEqual(new Set(['user']));
    expect(effectiveRoles(null)).toEqual(new Set());
  });

  it('checks hierarchy compliance for admin acting as user', () => {
    expect(isHierarchyCompliant('admin', 'user')).toBe(true);
    expect(isHierarchyCompliant('user', 'admin')).toBe(false);
    expect(isHierarchyCompliant('user', 'user')).toBe(true);
    expect(isHierarchyCompliant(null, 'user')).toBe(false);
  });
});
