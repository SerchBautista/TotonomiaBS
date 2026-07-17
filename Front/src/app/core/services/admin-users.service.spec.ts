import { TestBed } from '@angular/core/testing';
import { describe, expect, it, vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { AdminUsersService } from './admin-users.service';
import { ApiService } from './api';

describe('AdminUsersService', () => {
  let service: AdminUsersService;
  let apiMock: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    apiMock = {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      patch: vi.fn(),
      delete: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        AdminUsersService,
        { provide: ApiService, useValue: apiMock },
      ],
    });

    service = TestBed.inject(AdminUsersService);
  });

  describe('assignPlan', () => {
    it('should POST to /admin/users/{userId}/plan with the plan in the body', () => {
      const mockResponse = {
        message: 'ok',
        data: { item: { id: 'user-1', plan: 'premium' } },
      };
      apiMock.post.mockReturnValue(of(mockResponse));

      let result: unknown;
      service.assignPlan('user-1', 'premium').subscribe((v) => (result = v));

      expect(apiMock.post).toHaveBeenCalledWith(
        '/admin/users/user-1/plan',
        { plan: 'premium' },
      );
      expect(result).toEqual(mockResponse);
    });

    it('should pass options when provided', () => {
      apiMock.post.mockReturnValue(of({ message: 'ok', data: { item: {} } }));
      const options = { context: {} as any };

      service.assignPlan('user-1', 'free', options).subscribe();

      expect(apiMock.post).toHaveBeenCalledWith(
        '/admin/users/user-1/plan',
        { plan: 'free' },
        options,
      );
    });

    it('should propagate 403 errors', () => {
      apiMock.post.mockReturnValue(throwError(() => ({ status: 403 })));

      let error: unknown;
      service.assignPlan('user-1', 'premium').subscribe({
        error: (e) => (error = e),
      });

      expect(error).toEqual({ status: 403 });
    });

    it('should propagate 404 errors', () => {
      apiMock.post.mockReturnValue(throwError(() => ({ status: 404 })));

      let error: unknown;
      service.assignPlan('nonexistent', 'premium').subscribe({
        error: (e) => (error = e),
      });

      expect(error).toEqual({ status: 404 });
    });

    it('should propagate 422 errors', () => {
      apiMock.post.mockReturnValue(throwError(() => ({ status: 422 })));

      let error: unknown;
      service.assignPlan('user-1', 'invalid_plan').subscribe({
        error: (e) => (error = e),
      });

      expect(error).toEqual({ status: 422 });
    });
  });
});
