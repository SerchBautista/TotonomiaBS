import { HttpRequest, HttpResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom, of } from 'rxjs';
import { vi } from 'vitest';
import { STORAGE_SERVICE_TOKEN } from '../tokens/storage.token';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  let storageMock: {
    getItem: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    storageMock = {
      getItem: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [{ provide: STORAGE_SERVICE_TOKEN, useValue: storageMock }],
    });
  });

  async function runInterceptor(request: HttpRequest<unknown>): Promise<HttpRequest<unknown>> {
    let capturedRequest: HttpRequest<unknown> | undefined;
    const next = vi.fn((req: HttpRequest<unknown>) => {
      capturedRequest = req;
      return of(new HttpResponse({ status: 200 }));
    });

    const result = TestBed.runInInjectionContext(() => authInterceptor(request, next));
    await firstValueFrom(result);

    return capturedRequest!;
  }

  it('attaches Bearer token and Accept-Language when storage has token and lang', async () => {
    storageMock.getItem.mockImplementation((key: string) => {
      if (key === 'token') return 'abc-token';
      if (key === 'app_lang') return 'en';
      return null;
    });

    const modified = await runInterceptor(new HttpRequest('GET', '/secure'));

    expect(modified.headers.get('Authorization')).toBe('Bearer abc-token');
    expect(modified.headers.get('Accept-Language')).toBe('en');
  });

  it('does not attach Authorization when no token is stored', async () => {
    storageMock.getItem.mockImplementation((key: string) => {
      if (key === 'app_lang') return 'es';
      return null;
    });

    const modified = await runInterceptor(new HttpRequest('GET', '/public'));

    expect(modified.headers.has('Authorization')).toBe(false);
    expect(modified.headers.get('Accept-Language')).toBe('es');
  });

  it('defaults Accept-Language to es when app_lang is missing', async () => {
    storageMock.getItem.mockReturnValue(null);

    const modified = await runInterceptor(new HttpRequest('GET', '/public'));

    expect(modified.headers.get('Accept-Language')).toBe('es');
  });
});
