import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CardsService } from '../../../core/services/cards.service';
import { OtherPaymentMethodsService } from '../../../core/services/other-payment-methods.service';
import { ToastService } from '../../../core/services/toast.service';
import { ExpenseInlinePaymentFormComponent } from './expense-inline-payment-form';

describe('ExpenseInlinePaymentFormComponent', () => {
  let fixture: ComponentFixture<ExpenseInlinePaymentFormComponent>;
  let cardsServiceMock: { create: ReturnType<typeof vi.fn> };
  let otherPaymentMethodsServiceMock: { create: ReturnType<typeof vi.fn> };

  const workspace = {
    id: 'ws-1',
    owner_id: 'user-1',
    name: 'Workspace',
    type: 'personal' as const,
    currency_code: 'USD',
    created_at: '',
    updated_at: '',
  };

  beforeEach(async () => {
    cardsServiceMock = { create: vi.fn() };
    otherPaymentMethodsServiceMock = { create: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [ExpenseInlinePaymentFormComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: CardsService, useValue: cardsServiceMock },
        { provide: OtherPaymentMethodsService, useValue: otherPaymentMethodsServiceMock },
        {
          provide: ToastService,
          useValue: { success: vi.fn(), error: vi.fn() },
        },
      ],
    }).compileComponents();
  });

  it('emits created with card payment value when card mode submits successfully', () => {
    const card = {
      id: 'card-1',
      workspace_id: 'ws-1',
      name: 'Visa',
      card_type: 'credit' as const,
      brand: 'Visa',
      last_4_digits: '1234',
    };
    cardsServiceMock.create.mockReturnValue(of({ data: card }));

    fixture = TestBed.createComponent(ExpenseInlinePaymentFormComponent);
    fixture.componentRef.setInput('mode', 'card');
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('ownerWorkspaces', [workspace]);
    fixture.componentRef.setInput('initialWorkspaceIds', ['ws-1']);
    fixture.detectChanges();

    const createdSpy = vi.fn();
    fixture.componentInstance.created.subscribe(createdSpy);

    fixture.componentInstance.cardForm.patchValue({
      name: 'Visa',
      card_type: 'credit',
      brand: 'Visa',
      last_4_digits: '1234',
    });
    fixture.componentInstance.submit();

    expect(cardsServiceMock.create).toHaveBeenCalledWith(
      'ws-1',
      expect.objectContaining({ name: 'Visa', workspace_ids: ['ws-1'] }),
      expect.any(Object),
    );
    expect(createdSpy).toHaveBeenCalledWith({
      paymentValue: 'card:card-1',
      instrument: card,
    });
  });

  it('emits created with other payment value when other mode submits successfully', () => {
    const method = {
      id: 'other-1',
      workspace_id: 'ws-1',
      name: 'Transfer',
      description: 'Banco principal',
    };
    otherPaymentMethodsServiceMock.create.mockReturnValue(of({ data: method }));

    fixture = TestBed.createComponent(ExpenseInlinePaymentFormComponent);
    fixture.componentRef.setInput('mode', 'other');
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('ownerWorkspaces', [workspace]);
    fixture.componentRef.setInput('initialWorkspaceIds', ['ws-1']);
    fixture.detectChanges();

    const createdSpy = vi.fn();
    fixture.componentInstance.created.subscribe(createdSpy);

    fixture.componentInstance.otherForm.patchValue({
      name: 'Transfer',
      description: 'Banco principal',
    });
    fixture.componentInstance.submit();

    expect(otherPaymentMethodsServiceMock.create).toHaveBeenCalledWith(
      'ws-1',
      expect.objectContaining({ name: 'Transfer', workspace_ids: ['ws-1'] }),
      expect.any(Object),
    );
    expect(createdSpy).toHaveBeenCalledWith({
      paymentValue: 'other:other-1',
      instrument: method,
    });
  });
});
