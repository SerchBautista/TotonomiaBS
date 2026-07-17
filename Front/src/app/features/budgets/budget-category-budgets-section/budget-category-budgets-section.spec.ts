import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { BudgetsService } from '../../../core/services/budgets.service';
import { ToastService } from '../../../core/services/toast.service';
import { BudgetCategoryBudgetsSectionComponent } from './budget-category-budgets-section';
import { syncCategoryBudgetSelection } from '../budget-form.utils';

describe('BudgetCategoryBudgetsSectionComponent', () => {
  let fixture: ComponentFixture<BudgetCategoryBudgetsSectionComponent>;
  let component: BudgetCategoryBudgetsSectionComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BudgetCategoryBudgetsSectionComponent],
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

    fixture = TestBed.createComponent(BudgetCategoryBudgetsSectionComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('currencyCode', 'USD');
    fixture.componentRef.setInput('categoryBudgets', []);
    fixture.componentRef.setInput('categories', [
      { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000' },
    ]);
    fixture.componentRef.setInput('budgetStatus', new Map());
    fixture.detectChanges();
  });

  it('clears selected category when it is no longer valid', () => {
    component.categoryForm.patchValue({ category_id: 'cat-legacy' });

    syncCategoryBudgetSelection(component.categoryForm, [
      { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000' },
    ]);

    expect(component.categoryForm.value.category_id).toBe('');
  });

  it('shows category create form when add button is clicked', () => {
    const button = fixture.nativeElement.querySelector('button.btn.primary') as HTMLButtonElement;
    button.click();
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('label[for="cat-new-category"]')).toBeTruthy();
  });
});
