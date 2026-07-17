import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { AuthApiService } from './auth-api.service';
import { AuthStateService } from './auth-state.service';
import { AuthService } from './auth';

describe('AuthService', () => {
  let service: AuthService;
  let authStateMock: {
    isLoggedIn: ReturnType<typeof vi.fn>;
    token: ReturnType<typeof vi.fn>;
    role: ReturnType<typeof vi.fn>;
    emailVerified: ReturnType<typeof vi.fn>;
    userId: ReturnType<typeof vi.fn>;
    defaultWorkspaceId: ReturnType<typeof vi.fn>;
  };
  let authApiMock: {
    loginAsUser: ReturnType<typeof vi.fn>;
    loginAsAdmin: ReturnType<typeof vi.fn>;
    logout: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    authStateMock = {
      isLoggedIn: vi.fn().mockReturnValue(true),
      token: vi.fn().mockReturnValue('stored-token'),
      role: vi.fn().mockReturnValue('user'),
      emailVerified: vi.fn().mockReturnValue(true),
      userId: vi.fn().mockReturnValue('user-1'),
      defaultWorkspaceId: vi.fn().mockReturnValue('ws-1'),
    };
    authApiMock = {
      loginAsUser: vi.fn().mockReturnValue(of({ token: 'new-token' })),
      loginAsAdmin: vi.fn().mockReturnValue(of({ token: 'admin-token' })),
      logout: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        AuthService,
        { provide: AuthStateService, useValue: authStateMock },
        { provide: AuthApiService, useValue: authApiMock },
      ],
    });

    service = TestBed.inject(AuthService);
  });

  it('delegates login to AuthApiService', () => {
    service.login('user@example.com', 'secret').subscribe();

    expect(authApiMock.loginAsUser).toHaveBeenCalledWith('user@example.com', 'secret');
  });

  it('delegates loginAsAdmin to AuthApiService', () => {
    service.loginAsAdmin('admin@example.com', 'secret').subscribe();

    expect(authApiMock.loginAsAdmin).toHaveBeenCalledWith('admin@example.com', 'secret');
  });

  it('calls authApi.logout on logout', () => {
    service.logout();

    expect(authApiMock.logout).toHaveBeenCalledOnce();
  });

  it('reflects AuthStateService isLoggedIn through the facade', () => {
    authStateMock.isLoggedIn.mockReturnValue(false);

    expect(service.isLoggedIn()).toBe(false);
    expect(authStateMock.isLoggedIn).toHaveBeenCalled();
  });

  it('delegates token and role accessors to AuthStateService', () => {
    expect(service.token()).toBe('stored-token');
    expect(service.role()).toBe('user');
    expect(service.emailVerified()).toBe(true);
    expect(service.userId()).toBe('user-1');
    expect(service.defaultWorkspaceId()).toBe('ws-1');
  });
});
