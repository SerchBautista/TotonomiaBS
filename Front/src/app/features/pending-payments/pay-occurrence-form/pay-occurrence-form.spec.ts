import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SimpleChange } from '@angular/core';
import { provideTranslateService } from '@ngx-translate/core';
import { PayOccurrenceFormComponent } from './pay-occurrence-form';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';

describe('PayOccurrenceFormComponent', () => {
  let fixture: ComponentFixture<PayOccurrenceFormComponent>;
  let component: PayOccurrenceFormComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PayOccurrenceFormComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: UserPreferencesService,
          useValue: { timezone: () => 'UTC' },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PayOccurrenceFormComponent);
    component = fixture.componentInstance;
    component.ownerId = 'user-1';
    component.occurrence = {
      id: 'occ-1',
      due_date: '2026-05-01',
      suggested_amount: '150.00',
      status: 'pending',
      fixed_expense: {
        id: 'fe-1',
        description: 'Internet',
        frequency: 'monthly',
        payment_type: 'card',
        payment_instrument: { id: 'pm-card-1', workspace_id: 'ws-1', name: 'Visa', card_type: 'credit', brand: 'Visa', last_4_digits: '1234' },
        category: null,
      },
    };
    component.paymentMethods = [
      { id: 'cash', type: 'cash', name: 'Cash', display_name: 'Cash', masked_details: null, is_linked: true, is_valid_for_transactions: true, state: 'linked' },
      { id: 'pm-card-1', type: 'card', name: 'Visa', display_name: 'Visa •••• 1234', masked_details: '1234', is_linked: true, is_valid_for_transactions: true, state: 'linked' },
      { id: 'pm-other-1', type: 'other', name: 'Transfer', display_name: 'Transfer', masked_details: null, is_linked: true, is_valid_for_transactions: true, state: 'linked' },
    ];
    component.ngOnChanges({
      occurrence: new SimpleChange(null, component.occurrence, true),
      paymentMethods: new SimpleChange([], component.paymentMethods, true),
      ownerId: new SimpleChange('', component.ownerId, true),
    });
    fixture.detectChanges();
  });

  it('prefills the occurrence payment method only when it is still valid', () => {
    expect(component.form.value.payment_value).toBe('card:pm-card-1');
  });

  it('renders only valid workspace payment methods', () => {
    const optionValues = Array.from<HTMLOptionElement>(fixture.nativeElement.querySelectorAll('select[formcontrolname="payment_value"] option'))
      .map(option => option.value);

    expect(optionValues).toEqual(['', 'cash', 'card:pm-card-1', 'other:pm-other-1']);
  });
});
