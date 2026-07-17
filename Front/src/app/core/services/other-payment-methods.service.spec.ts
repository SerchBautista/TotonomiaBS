import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiService } from './api';
import { OtherPaymentMethodsService } from './other-payment-methods.service';

describe('OtherPaymentMethodsService', () => {
  let service: OtherPaymentMethodsService;
  let apiMock: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    apiMock = {
      get: vi.fn().mockReturnValue(of({})),
      post: vi.fn().mockReturnValue(of({})),
      put: vi.fn().mockReturnValue(of({})),
      patch: vi.fn().mockReturnValue(of({})),
      delete: vi.fn().mockReturnValue(of({})),
    };

    TestBed.configureTestingModule({
      providers: [OtherPaymentMethodsService, { provide: ApiService, useValue: apiMock }],
    });

    service = TestBed.inject(OtherPaymentMethodsService);
  });

  it('sends workspace_ids when creating another payment method', () => {
    const payload = {
      name: 'Transferencia',
      description: 'Banco',
      workspace_ids: ['ws-1'],
    };

    service.create('ws-1', payload).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/other-payment-methods', payload);
  });

  it('sends workspace_ids when updating another payment method', () => {
    const payload = {
      name: 'Transferencia nueva',
      workspace_ids: ['ws-1', 'ws-2'],
    };

    service.update('ws-1', 'other-1', payload).subscribe();

    expect(apiMock.put).toHaveBeenCalledWith(
      '/workspaces/ws-1/other-payment-methods/other-1',
      payload,
    );
  });
});
