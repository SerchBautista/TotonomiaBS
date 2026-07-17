import { HttpParams, HttpRequest, HttpResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom, of } from 'rxjs';
import { vi } from 'vitest';
import { environment } from '../../../environments/environment';
import { STORAGE_SERVICE_TOKEN } from '../tokens/storage.token';
import { langParamInterceptor } from './lang-param.interceptor';

describe('langParamInterceptor', () => {
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

  async function runInterceptor(
    request: HttpRequest<unknown>,
    passthrough = false,
  ): Promise<{ request: HttpRequest<unknown>; next: ReturnType<typeof vi.fn> }> {
    let capturedRequest: HttpRequest<unknown> | undefined;
    const next = vi.fn((req: HttpRequest<unknown>) => {
      if (!passthrough) {
        capturedRequest = req;
      }
      return of(new HttpResponse({ status: 200 }));
    });

    const result = TestBed.runInInjectionContext(() => langParamInterceptor(request, next));
    await firstValueFrom(result);

    return { request: capturedRequest ?? request, next };
  }

  it('appends lang query param for API URLs', async () => {
    storageMock.getItem.mockReturnValue('en');

    const { request: modified } = await runInterceptor(
      new HttpRequest('GET', `${environment.apiUrl}/items`),
    );

    expect(modified.params.get('lang')).toBe('en');
  });

  it('skips non-API URLs', async () => {
    storageMock.getItem.mockReturnValue('en');
    const original = new HttpRequest('GET', '/assets/i18n/es.json');

    const { request: modified, next } = await runInterceptor(original, true);

    expect(modified.params.has('lang')).toBe(false);
    expect(next).toHaveBeenCalledWith(original);
  });

  it('skips when lang query param is already set', async () => {
    storageMock.getItem.mockReturnValue('en');
    const original = new HttpRequest('GET', `${environment.apiUrl}/items`, {
      params: new HttpParams().set('lang', 'es'),
    });

    const { request: modified, next } = await runInterceptor(original, true);

    expect(modified.params.get('lang')).toBe('es');
    expect(next).toHaveBeenCalledWith(original);
  });

  it('defaults lang to es when app_lang is missing', async () => {
    storageMock.getItem.mockReturnValue(null);

    const { request: modified } = await runInterceptor(
      new HttpRequest('GET', `${environment.apiUrl}/items`),
    );

    expect(modified.params.get('lang')).toBe('es');
  });
});
