import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { provideTranslateService } from '@ngx-translate/core';
import { BudgetFormFieldsComponent } from './budget-form-fields';

describe('BudgetFormFieldsComponent', () => {
  let fixture: ComponentFixture<BudgetFormFieldsComponent>;
  let formGroup: FormGroup;

  beforeEach(async () => {
    formGroup = new FormGroup({
      amount: new FormControl('', Validators.required),
      alert_threshold: new FormControl(0),
      alert_enabled: new FormControl(true),
    });

    await TestBed.configureTestingModule({
      imports: [BudgetFormFieldsComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    fixture = TestBed.createComponent(BudgetFormFieldsComponent);
    fixture.componentRef.setInput('form', formGroup);
    fixture.componentRef.setInput('idPrefix', 'general');
    fixture.detectChanges();
  });

  it('renders budget amount and alert fields bound to the form', () => {
    const amountInput = fixture.nativeElement.querySelector(
      '#general-amount',
    ) as HTMLInputElement | null;
    const thresholdInput = fixture.nativeElement.querySelector(
      '#general-alert',
    ) as HTMLInputElement | null;

    expect(amountInput).toBeTruthy();
    expect(thresholdInput).toBeTruthy();

    amountInput!.value = '500';
    amountInput!.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    expect(formGroup.value.amount).toBe(500);
  });
});
