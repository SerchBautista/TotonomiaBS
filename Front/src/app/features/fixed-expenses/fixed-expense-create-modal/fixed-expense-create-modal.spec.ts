import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { FixedExpenseCreateModalComponent } from './fixed-expense-create-modal';
import { FixedExpensesService } from '../../../core/services/fixed-expenses';
import { CardsService } from '../../../core/services/cards.service';
import { OtherPaymentMethodsService } from '../../../core/services/other-payment-methods.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';

const paymentMethods = [
  {
    id: 'cash',
    type: 'cash' as const,
    name: 'Efectivo',
    display_name: 'Efectivo',
    masked_details: null,
    is_linked: true,
    is_valid_for_transactions: true,
    state: 'linked' as const,
  },
];

describe('FixedExpenseCreateModalComponent', () => {
  let fixture: ComponentFixture<FixedExpenseCreateModalComponent>;
  let component: FixedExpenseCreateModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FixedExpenseCreateModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: FixedExpensesService,
          useValue: { create: vi.fn().mockReturnValue(of({ data: {} })) },
        },
        {
          provide: CardsService,
          useValue: { create: vi.fn() },
        },
        {
          provide: OtherPaymentMethodsService,
          useValue: { create: vi.fn() },
        },
        {
          provide: PaymentMethodsService,
          useValue: { notifyCreated: vi.fn() },
        },
        { provide: ToastService, useValue: { success: vi.fn(), error: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(FixedExpenseCreateModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('open', false);
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('categories', []);
    fixture.componentRef.setInput('validPaymentMethods', paymentMethods);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('renders create form when open', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('#fe-description')).toBeTruthy();
    expect(fixture.nativeElement.querySelector('app-modal-shell .modal-panel')).toBeTruthy();
  });
});
