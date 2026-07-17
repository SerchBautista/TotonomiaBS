import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { PaymentMethodEditModalComponent } from './payment-method-edit-modal';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';
import { UserPaymentMethodSummary } from '../../../core/models/payment-method.model';

const ownerWorkspaces = [{ id: 'ws-1', name: 'Casa', owner_id: 'user-1' }];

const cardMethod: UserPaymentMethodSummary = {
  id: 'card-1',
  type: 'card',
  name: 'Visa personal',
  display_name: 'Visa personal',
  masked_details: '****4242',
  linked_workspaces_count: 1,
  linked_workspaces: [{ id: 'ws-1', name: 'Casa' }],
};

describe('PaymentMethodEditModalComponent', () => {
  let fixture: ComponentFixture<PaymentMethodEditModalComponent>;
  let component: PaymentMethodEditModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PaymentMethodEditModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: PaymentMethodsService,
          useValue: { updateMine: vi.fn().mockReturnValue(of({ data: cardMethod })) },
        },
        { provide: ToastService, useValue: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PaymentMethodEditModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('method', null);
    fixture.componentRef.setInput('ownerWorkspaces', ownerWorkspaces);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('renders edit form when method is set', () => {
    fixture.componentRef.setInput('method', cardMethod);
    fixture.detectChanges();

    expect(component.isOpen()).toBe(true);
    expect(fixture.nativeElement.querySelector('#pm-edit-name')).toBeTruthy();
    expect(component.form.controls.name.value).toBe('Visa personal');
    expect(component.form.controls.last_4_digits.value).toBe('4242');
  });
});
