import { TestBed } from '@angular/core/testing';
import { firstValueFrom, of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { PaymentMethodsService } from './payment-methods';

describe('PaymentMethodsService', () => {
  let service: PaymentMethodsService;
  let apiMock: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    apiMock = {
      get: vi.fn().mockReturnValue(of({})),
      post: vi.fn().mockReturnValue(of({})),
      patch: vi.fn().mockReturnValue(of({})),
      put: vi.fn().mockReturnValue(of({})),
      delete: vi.fn().mockReturnValue(of({})),
    };

    TestBed.configureTestingModule({
      providers: [PaymentMethodsService, { provide: API_SERVICE_TOKEN, useValue: apiMock }],
    });

    service = TestBed.inject(PaymentMethodsService);
  });

  it('should call api.get with the user-scoped path on listMine()', () => {
    service.listMine().subscribe();

    expect(apiMock.get).toHaveBeenCalledWith('/user/payment-methods');
  });

  it('should call api.post with the user-scoped path on createMine()', () => {
    const payload = {
      type: 'card' as const,
      name: 'Visa',
      card_type: 'credit' as const,
      last_4_digits: '4242',
    };

    service.createMine(payload).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/user/payment-methods', payload);
  });

  it('should call api.delete with the user-scoped path on deleteMine()', () => {
    service.deleteMine('pm-1').subscribe();

    expect(apiMock.delete).toHaveBeenCalledWith('/user/payment-methods/pm-1');
  });

  it('should call api.put with the user-scoped path on updateMine()', () => {
    const payload = {
      type: 'card' as const,
      name: 'Visa Updated',
      card_type: 'credit' as const,
      brand: 'Visa',
      last_4_digits: '4242',
    };

    service.updateMine('pm-1', payload).subscribe();

    expect(apiMock.put).toHaveBeenCalledWith('/user/payment-methods/pm-1', payload);
  });

  it('should call api.patch with workspace_ids on updateWorkspaces()', () => {
    service.updateWorkspaces('pm-1', ['ws-1']).subscribe();

    expect(apiMock.patch).toHaveBeenCalledWith('/user/payment-methods/pm-1/workspaces', {
      workspace_ids: ['ws-1'],
    });
  });

  it('should call api.get with correct path on listWorkspace()', () => {
    service.listWorkspace('ws-1').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/payment-methods');
  });

  it('should call api.get with correct path on listValid()', () => {
    service.listValid('ws-1').subscribe();

    expect(apiMock.get).toHaveBeenCalledWith('/workspaces/ws-1/payment-methods/valid');
  });

  it('should call api.post with correct path on create() (workspace scope)', () => {
    const payload = {
      type: 'card' as const,
      name: 'Visa',
      card_type: 'credit' as const,
      last_4_digits: '4242',
    };

    service.create('ws-1', payload).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/payment-methods', payload);
  });

  it('should emit created payment method event on create()', async () => {
    const response = {
      data: {
        id: 'pm-1',
        type: 'card',
        name: 'Visa',
        display_name: 'Visa',
        masked_details: '****4242',
        is_linked: true,
        is_valid_for_transactions: true,
        state: 'linked',
      },
    };
    apiMock.post.mockReturnValue(of(response));

    const createdEventPromise = firstValueFrom(service.paymentMethodCreated$);

    service
      .create('ws-1', {
        type: 'card',
        name: 'Visa',
        card_type: 'credit',
        last_4_digits: '4242',
      })
      .subscribe();

    await expect(createdEventPromise).resolves.toEqual({
      workspaceId: 'ws-1',
      method: response.data,
    });
  });

  it('should emit created payment method event on notifyCreated()', async () => {
    const method = {
      id: 'pm-2',
      workspace_id: 'ws-1',
      name: 'Transferencia',
      description: 'Banco principal',
    };

    const createdEventPromise = firstValueFrom(service.paymentMethodCreated$);

    service.notifyCreated('ws-1', method);

    await expect(createdEventPromise).resolves.toEqual({
      workspaceId: 'ws-1',
      method,
    });
  });

  it('should call api.patch with correct path on updateLink()', () => {
    service.updateLink('ws-1', 'pm-1', false).subscribe();

    expect(apiMock.patch).toHaveBeenCalledWith('/workspaces/ws-1/payment-methods/pm-1/link', {
      is_linked: false,
    });
  });

  it('should call api.post with unlink_all operation on bulkLinking(false)', () => {
    service.bulkLinking('ws-1', false).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/payment-methods/link-bulk', {
      operation: 'unlink_all',
    });
  });

  it('should call api.post with link_all operation on bulkLinking(true)', () => {
    service.bulkLinking('ws-1', true).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/payment-methods/link-bulk', {
      operation: 'link_all',
    });
  });
});
