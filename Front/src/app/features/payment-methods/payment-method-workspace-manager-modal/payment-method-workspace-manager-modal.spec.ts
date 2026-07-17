import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { PaymentMethodWorkspaceManagerModalComponent } from './payment-method-workspace-manager-modal';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';
import { UserPaymentMethodSummary } from '../../../core/models/payment-method.model';

const ownerWorkspaces = [
  { id: 'ws-1', name: 'Casa', owner_id: 'user-1' },
  { id: 'ws-2', name: 'Negocio', owner_id: 'user-1' },
];

const cardMethod: UserPaymentMethodSummary = {
  id: 'card-1',
  type: 'card',
  name: 'Visa personal',
  display_name: 'Visa personal',
  masked_details: '****4242',
  linked_workspaces_count: 2,
  linked_workspaces: [
    { id: 'ws-1', name: 'Casa' },
    { id: 'ws-2', name: 'Negocio' },
  ],
};

describe('PaymentMethodWorkspaceManagerModalComponent', () => {
  let fixture: ComponentFixture<PaymentMethodWorkspaceManagerModalComponent>;
  let component: PaymentMethodWorkspaceManagerModalComponent;
  let paymentMethodsMock: { updateWorkspaces: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    paymentMethodsMock = {
      updateWorkspaces: vi.fn().mockReturnValue(of({ data: cardMethod })),
    };

    await TestBed.configureTestingModule({
      imports: [PaymentMethodWorkspaceManagerModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: PaymentMethodsService, useValue: paymentMethodsMock },
        { provide: ToastService, useValue: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PaymentMethodWorkspaceManagerModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('method', null);
    fixture.componentRef.setInput('ownerWorkspaces', ownerWorkspaces);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('renders workspace selector when method is set', () => {
    fixture.componentRef.setInput('method', cardMethod);
    fixture.detectChanges();

    expect(component.isOpen()).toBe(true);
    expect(fixture.nativeElement.querySelector('app-workspace-selector-list')).toBeTruthy();
    expect(component.selectedWorkspaceIds()).toEqual(['ws-1', 'ws-2']);
  });

  it('calls updateWorkspaces on save', async () => {
    fixture.componentRef.setInput('method', cardMethod);
    fixture.detectChanges();

    component.save();
    await fixture.whenStable();

    expect(paymentMethodsMock.updateWorkspaces).toHaveBeenCalledWith(
      'card-1',
      ['ws-1', 'ws-2'],
      expect.any(Object),
    );
  });
});
