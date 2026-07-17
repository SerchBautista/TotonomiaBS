import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { FixedExpenseEditModalComponent } from './fixed-expense-edit-modal';
import { FixedExpensesService } from '../../../core/services/fixed-expenses';
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

const sampleExpense = {
  id: 'fixed-1',
  workspace_id: 'ws-1',
  user_id: 'user-1',
  category_id: 'cat-1',
  payment_type: 'cash' as const,
  payment_instrument_id: null,
  amount: '150.25',
  description: 'Internet',
  frequency: 'monthly' as const,
  next_due_date: '2026-05-30',
  alert_date: null,
  is_active: true,
  reminders_enabled: false,
  type: 'recurring' as const,
  total_installments: null,
  remaining_installments: null,
  has_paid_occurrences: false,
};

describe('FixedExpenseEditModalComponent', () => {
  let fixture: ComponentFixture<FixedExpenseEditModalComponent>;
  let component: FixedExpenseEditModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FixedExpenseEditModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: FixedExpensesService,
          useValue: { update: vi.fn().mockReturnValue(of({ data: sampleExpense })) },
        },
        { provide: ToastService, useValue: { success: vi.fn(), error: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(FixedExpenseEditModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('expense', null);
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('categories', []);
    fixture.componentRef.setInput('validPaymentMethods', paymentMethods);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('renders edit form when expense is set', () => {
    fixture.componentRef.setInput('expense', sampleExpense);
    fixture.detectChanges();

    expect(component.isOpen()).toBe(true);
    expect(fixture.nativeElement.querySelector('#fe-edit-description')).toBeTruthy();
    expect(fixture.nativeElement.querySelector('app-modal-shell .modal-panel')).toBeTruthy();
  });
});
