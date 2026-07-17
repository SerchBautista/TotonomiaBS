import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { TokenService } from './token.service';
import { STORAGE_SERVICE_TOKEN, StorageService } from '../tokens/storage.token';

describe('TokenService', () => {
  let service: TokenService;
  let storageMock: {
    getItem: ReturnType<typeof vi.fn>;
    setItem: ReturnType<typeof vi.fn>;
    removeItem: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    storageMock = {
      getItem: vi.fn().mockReturnValue(null),
      setItem: vi.fn(),
      removeItem: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        TokenService,
        { provide: STORAGE_SERVICE_TOKEN, useValue: storageMock as StorageService },
      ],
    });

    service = TestBed.inject(TokenService);
  });

  describe('getToken', () => {
    it('should return null when no token stored', () => {
      storageMock.getItem.mockReturnValue(null);
      expect(service.getToken()).toBeNull();
      expect(storageMock.getItem).toHaveBeenCalledWith('token');
    });

    it('should return the stored token', () => {
      storageMock.getItem.mockReturnValue('my-token');
      expect(service.getToken()).toBe('my-token');
    });
  });

  describe('setToken', () => {
    it('should persist token via StorageService', () => {
      service.setToken('abc123');
      expect(storageMock.setItem).toHaveBeenCalledWith('token', 'abc123');
    });
  });

  describe('removeToken', () => {
    it('should remove token via StorageService', () => {
      service.removeToken();
      expect(storageMock.removeItem).toHaveBeenCalledWith('token');
    });
  });

  describe('getRole', () => {
    it('should return null when no role stored', () => {
      storageMock.getItem.mockReturnValue(null);
      expect(service.getRole()).toBeNull();
      expect(storageMock.getItem).toHaveBeenCalledWith('role');
    });

    it('should return the stored role', () => {
      storageMock.getItem.mockReturnValue('admin');
      expect(service.getRole()).toBe('admin');
    });
  });

  describe('setRole', () => {
    it('should persist role via StorageService', () => {
      service.setRole('user');
      expect(storageMock.setItem).toHaveBeenCalledWith('role', 'user');
    });
  });

  describe('removeRole', () => {
    it('should remove role via StorageService', () => {
      service.removeRole();
      expect(storageMock.removeItem).toHaveBeenCalledWith('role');
    });
  });
});
