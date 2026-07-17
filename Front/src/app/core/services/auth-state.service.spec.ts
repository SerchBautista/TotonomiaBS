import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';
import { vi } from 'vitest';
import { AuthStateService } from './auth-state.service';
import { TokenService } from './token.service';

describe('AuthStateService', () => {
  let service: AuthStateService;
  let tokenMock: {
    getToken: ReturnType<typeof vi.fn>;
    setToken: ReturnType<typeof vi.fn>;
    removeToken: ReturnType<typeof vi.fn>;
    getRole: ReturnType<typeof vi.fn>;
    setRole: ReturnType<typeof vi.fn>;
    removeRole: ReturnType<typeof vi.fn>;
    getPlan: ReturnType<typeof vi.fn>;
    setPlan: ReturnType<typeof vi.fn>;
    removePlan: ReturnType<typeof vi.fn>;
    getUserId: ReturnType<typeof vi.fn>;
    setUserId: ReturnType<typeof vi.fn>;
    removeUserId: ReturnType<typeof vi.fn>;
    getDefaultWorkspaceId: ReturnType<typeof vi.fn>;
    setDefaultWorkspaceId: ReturnType<typeof vi.fn>;
    removeDefaultWorkspaceId: ReturnType<typeof vi.fn>;
    getEmailVerified: ReturnType<typeof vi.fn>;
    setEmailVerified: ReturnType<typeof vi.fn>;
    removeEmailVerified: ReturnType<typeof vi.fn>;
    getPermissions: ReturnType<typeof vi.fn>;
    setPermissions: ReturnType<typeof vi.fn>;
    removePermissions: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    tokenMock = {
      getToken: vi.fn().mockReturnValue(null),
      setToken: vi.fn(),
      removeToken: vi.fn(),
      getRole: vi.fn().mockReturnValue(null),
      setRole: vi.fn(),
      removeRole: vi.fn(),
      getPlan: vi.fn().mockReturnValue(null),
      setPlan: vi.fn(),
      removePlan: vi.fn(),
      getUserId: vi.fn().mockReturnValue(null),
      setUserId: vi.fn(),
      removeUserId: vi.fn(),
      getDefaultWorkspaceId: vi.fn().mockReturnValue(null),
      setDefaultWorkspaceId: vi.fn(),
      removeDefaultWorkspaceId: vi.fn(),
      getEmailVerified: vi.fn().mockReturnValue(false),
      setEmailVerified: vi.fn(),
      removeEmailVerified: vi.fn(),
      getPermissions: vi.fn().mockReturnValue([]),
      setPermissions: vi.fn(),
      removePermissions: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        AuthStateService,
        { provide: TokenService, useValue: tokenMock },
      ],
    });

    service = TestBed.inject(AuthStateService);
  });

  it('should initialize with null token and null role', () => {
    expect(service.token()).toBeNull();
    expect(service.role()).toBeNull();
    expect(service.isLoggedIn()).toBe(false);
  });

  it('should reflect stored token from TokenService on init', () => {
    tokenMock.getToken.mockReturnValue('existing-token');
    tokenMock.getRole.mockReturnValue('user');

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        AuthStateService,
        { provide: TokenService, useValue: tokenMock },
      ],
    });

    const freshService = TestBed.inject(AuthStateService);

    expect(freshService.token()).toBe('existing-token');
    expect(freshService.role()).toBe('user');
    expect(freshService.isLoggedIn()).toBe(true);
  });

  it('should update signals and storage on setToken', () => {
    service.setToken('new-token');

    expect(service.token()).toBe('new-token');
    expect(service.isLoggedIn()).toBe(true);
    expect(tokenMock.setToken).toHaveBeenCalledWith('new-token');
  });

  it('should update signals and storage on setRole', () => {
    service.setRole('admin');

    expect(service.role()).toBe('admin');
    expect(tokenMock.setRole).toHaveBeenCalledWith('admin');
  });

  it('should not persist null role on setRole', () => {
    service.setRole(null);

    expect(service.role()).toBeNull();
    expect(tokenMock.setRole).not.toHaveBeenCalled();
  });

  it('should clear signals and storage on clear()', () => {
    service.setToken('token');
    service.setRole('user');

    service.clear();

    expect(service.token()).toBeNull();
    expect(service.role()).toBeNull();
    expect(service.isLoggedIn()).toBe(false);
    expect(tokenMock.removeToken).toHaveBeenCalled();
    expect(tokenMock.removeRole).toHaveBeenCalled();
    expect(tokenMock.removeEmailVerified).toHaveBeenCalled();
  });

  it('should return admin role when stored role is admin', () => {
    tokenMock.getRole.mockReturnValue('admin');

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        AuthStateService,
        { provide: TokenService, useValue: tokenMock },
      ],
    });

    const freshService = TestBed.inject(AuthStateService);
    expect(freshService.role()).toBe('admin');
  });

  it('should return null role when stored role is unknown value', () => {
    tokenMock.getRole.mockReturnValue('superadmin');

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        AuthStateService,
        { provide: TokenService, useValue: tokenMock },
      ],
    });

    const freshService = TestBed.inject(AuthStateService);
    expect(freshService.role()).toBeNull();
  });

  // -------------------------------------------------------------------------
  // 11.1 — AuthStateService exposes plan$ correctly for free and premium users
  // -------------------------------------------------------------------------

  it('should default plan to free when no plan stored', () => {
    tokenMock.getPlan.mockReturnValue(null);
    expect(service.plan()).toBe('free');
  });

  it('should return premium plan when premium is stored', () => {
    tokenMock.getPlan.mockReturnValue('premium');

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        AuthStateService,
        { provide: TokenService, useValue: tokenMock },
      ],
    });

    const freshService = TestBed.inject(AuthStateService);
    expect(freshService.plan()).toBe('premium');
  });

  it('should update plan signal and storage on setPlan', () => {
    service.setPlan('premium');

    expect(service.plan()).toBe('premium');
    expect(tokenMock.setPlan).toHaveBeenCalledWith('premium');
  });

  it('plan$ observable should emit free by default', async () => {
    const value = await firstValueFrom(service.plan$);
    expect(value).toBe('free');
  });

  it('plan$ observable should emit updated value after setPlan', async () => {
    service.setPlan('premium');
    const value = await firstValueFrom(service.plan$);
    expect(value).toBe('premium');
  });

  it('should reset plan to free on clear()', () => {
    service.setPlan('premium');
    service.clear();
    expect(service.plan()).toBe('free');
    expect(tokenMock.removePlan).toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // Permissions — hasPermission() and permissions state
  // -------------------------------------------------------------------------

  it('should return empty permissions array by default', () => {
    expect(service.permissions()).toEqual([]);
  });

  it('should return false from hasPermission when no permissions are set', () => {
    expect(service.hasPermission('users.assign-plan')).toBe(false);
  });

  it('should return true from hasPermission when the permission is present', () => {
    service.setPermissions(['users.assign-plan', 'admin.dashboard']);
    expect(service.hasPermission('users.assign-plan')).toBe(true);
    expect(service.hasPermission('admin.dashboard')).toBe(true);
  });

  it('should return false from hasPermission when the permission is not in the list', () => {
    service.setPermissions(['admin.dashboard']);
    expect(service.hasPermission('users.assign-plan')).toBe(false);
  });

  it('should update permissions signal and storage on setPermissions', () => {
    service.setPermissions(['users.assign-plan']);
    expect(service.permissions()).toEqual(['users.assign-plan']);
    expect(tokenMock.setPermissions).toHaveBeenCalledWith(['users.assign-plan']);
  });

  it('should read permissions from TokenService on init', () => {
    tokenMock.getPermissions.mockReturnValue(['users.assign-plan']);

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        AuthStateService,
        { provide: TokenService, useValue: tokenMock },
      ],
    });

    const freshService = TestBed.inject(AuthStateService);
    expect(freshService.permissions()).toEqual(['users.assign-plan']);
    expect(freshService.hasPermission('users.assign-plan')).toBe(true);
  });

  it('should set permissions on applyLoginResponse', () => {
    service.applyLoginResponse(
      {
        id: 'user-1',
        role: 'admin',
        plan: 'premium',
        permissions: ['users.assign-plan', 'admin.dashboard'],
      },
      'test-token',
    );

    expect(service.permissions()).toEqual(['users.assign-plan', 'admin.dashboard']);
    expect(service.hasPermission('users.assign-plan')).toBe(true);
    expect(tokenMock.setPermissions).toHaveBeenCalledWith(['users.assign-plan', 'admin.dashboard']);
  });

  it('should default to empty permissions on applyLoginResponse when not provided', () => {
    service.applyLoginResponse(
      { id: 'user-1', role: 'user' },
      'test-token',
    );

    expect(service.permissions()).toEqual([]);
    expect(service.hasPermission('users.assign-plan')).toBe(false);
  });

  it('should reset permissions to empty array on clear()', () => {
    service.setPermissions(['users.assign-plan']);
    service.clear();
    expect(service.permissions()).toEqual([]);
    expect(service.hasPermission('users.assign-plan')).toBe(false);
    expect(tokenMock.removePermissions).toHaveBeenCalled();
  });
});
