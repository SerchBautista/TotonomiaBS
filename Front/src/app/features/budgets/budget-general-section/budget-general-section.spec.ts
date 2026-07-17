import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { BudgetsService } from '../../../core/services/budgets.service';
import { ToastService } from '../../../core/services/toast.service';
import { BudgetGeneralSectionComponent } from './budget-general-section';

describe('BudgetGeneralSectionComponent', () => {
  let fixture: ComponentFixture<BudgetGeneralSectionComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BudgetGeneralSectionComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: BudgetsService,
          useValue: { create: vi.fn(), update: vi.fn(), delete: vi.fn() },
        },
        {
          provide: ToastService,
          useValue: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(BudgetGeneralSectionComponent);
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('currencyCode', 'USD');
    fixture.detectChanges();
  });

  it('shows create form when set budget is clicked', () => {
    const button = fixture.nativeElement.querySelector('button.btn.primary') as HTMLButtonElement;
    button.click();
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('label[for="general-amount"]')).toBeTruthy();
  });
});
