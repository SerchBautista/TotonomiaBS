import { HttpContext } from '@angular/common/http';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiService } from './api';

describe('ApiService', () => {
  let service: ApiService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [ApiService, provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(ApiService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('performs GET requests against the configured API base URL', async () => {
    const promise = firstValueFrom(service.get<{ ok: boolean }>('/items'));

    const req = httpMock.expectOne(`${environment.apiUrl}/items`);
    expect(req.request.method).toBe('GET');
    req.flush({ ok: true });

    await expect(promise).resolves.toEqual({ ok: true });
  });

  it('performs POST requests with a JSON body against the configured API base URL', async () => {
    const body = { name: 'test' };
    const promise = firstValueFrom(service.post<{ id: string }>('/items', body));

    const req = httpMock.expectOne(`${environment.apiUrl}/items`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual(body);
    req.flush({ id: 'item-1' });

    await expect(promise).resolves.toEqual({ id: 'item-1' });
  });

  it('forwards optional request options to HttpClient', async () => {
    const context = new HttpContext();
    const promise = firstValueFrom(service.get('/secure', { context }));

    const req = httpMock.expectOne(`${environment.apiUrl}/secure`);
    expect(req.request.context).toBe(context);
    req.flush({});

    await expect(promise).resolves.toEqual({});
  });

  it('propagates HTTP errors to subscribers', async () => {
    const promise = firstValueFrom(service.get('/items'));

    const req = httpMock.expectOne(`${environment.apiUrl}/items`);
    req.flush('Server error', { status: 500, statusText: 'Internal Server Error' });

    await expect(promise).rejects.toMatchObject({ status: 500 });
  });
});
