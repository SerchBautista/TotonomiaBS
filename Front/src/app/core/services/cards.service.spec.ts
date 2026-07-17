import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { ApiService } from './api';
import { CardsService } from './cards.service';

describe('CardsService', () => {
  let service: CardsService;
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
      providers: [CardsService, { provide: ApiService, useValue: apiMock }],
    });

    service = TestBed.inject(CardsService);
  });

  it('sends workspace_ids when creating a card', () => {
    const payload = {
      name: 'Visa',
      card_type: 'credit' as const,
      last_4_digits: '4242',
      workspace_ids: ['ws-1', 'ws-2'],
    };

    service.create('ws-1', payload).subscribe();

    expect(apiMock.post).toHaveBeenCalledWith('/workspaces/ws-1/cards', payload);
  });

  it('sends workspace_ids when updating a card', () => {
    const payload = {
      name: 'Visa nueva',
      workspace_ids: ['ws-1'],
    };

    service.update('ws-1', 'card-1', payload).subscribe();

    expect(apiMock.put).toHaveBeenCalledWith('/workspaces/ws-1/cards/card-1', payload);
  });
});
