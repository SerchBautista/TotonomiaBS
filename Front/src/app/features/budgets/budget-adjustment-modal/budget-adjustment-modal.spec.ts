import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { AvailableCategory } from '../../../core/models/budget-adjustment.model';
import { BudgetCategoryScopeStatus } from '../../../core/models/budget.model';
import { BudgetAdjustmentsService } from '../../../core/services/budget-adjustments.service';
import { ToastService } from '../../../core/services/toast.service';
import { BudgetAdjustmentModalComponent } from './budget-adjustment-modal';

describe('BudgetAdjustmentModalComponent', () => {
  let fixture: ComponentFixture<BudgetAdjustmentModalComponent>;
  let component: BudgetAdjustmentModalComponent;
  let budgetAdjustmentsServiceMock: {
    create: ReturnType<typeof vi.fn>;
    available: ReturnType<typeof vi.fn>;
  };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  const categories: BudgetCategoryScopeStatus[] = [
    {
      category_id: 'cat-1',
      category_name: 'Food',
      category_icon: 'tag',
      category_color: '#111111',
      has_budget: true,
      spent: '60',
      committed: '0',
      effective_spent: '60',
      budget: '100',
      effective_budget: '100',
    },
    {
      category_id: 'cat-2',
      category_name: 'Transport',
      category_icon: 'car',
      category_color: '#222222',
      has_budget: true,
      spent: '20',
      committed: '0',
      effective_spent: '20',
      budget: '80',
      effective_budget: '80',
    },
  ];

  const suggestedCategories: AvailableCategory[] = [
    {
      category_id: 'cat-3',
      category_name: 'Health',
      category_icon: 'heart',
      category_color: '#333333',
      base_budget: '50',
      effective_budget: '75',
      spent: '20',
      available: '55',
    },
  ];

  beforeEach(async () => {
    vi.clearAllMocks();

    budgetAdjustmentsServiceMock = {
      create: vi.fn().mockReturnValue(of({ data: { id: 'adj-1' } })),
      available: vi.fn().mockReturnValue(of({ data: [] })),
    };

    await TestBed.configureTestingModule({
      imports: [BudgetAdjustmentModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: BudgetAdjustmentsService, useValue: budgetAdjustmentsServiceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(BudgetAdjustmentModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('data', {
      workspaceId: 'ws-1',
      month: '2026-05',
      categories,
      toCategoryId: 'cat-2',
    });
    fixture.detectChanges();
  });

  it('should show suggested categories from normalized metadata on insufficient funds', () => {
    budgetAdjustmentsServiceMock.create.mockReturnValueOnce(throwError(() => ({
      status: 422,
      error: {
        status: 422,
        code: BACKEND_ERROR_CODES.budgetAdjustmentInsufficientFunds,
        message: 'Insufficient funds in selected category',
        request_id: 'req-1',
        meta: {
          suggested_categories: suggestedCategories,
        },
      },
    })));

    component.form.setValue({
      from_category_id: 'cat-1',
      to_category_id: 'cat-2',
      amount: '20.00',
      reason: '',
    });

    component.submit();

    expect(component.showSuggestions()).toBe(true);
    expect(component.suggestedCategories()).toEqual(suggestedCategories);
    expect(toastMock.error).toHaveBeenCalledWith('Insufficient funds in selected category');
  });

  it('should emit the created adjustment, show success feedback and close on successful submit', () => {
    const adjustmentCreatedSpy = vi.spyOn(component.adjustmentCreated, 'emit');
    const closedSpy = vi.spyOn(component.closed, 'emit');

    component.form.setValue({
      from_category_id: 'cat-1',
      to_category_id: 'cat-2',
      amount: '20.00',
      reason: 'Rebalance',
    });

    component.submit();

    expect(budgetAdjustmentsServiceMock.create).toHaveBeenCalledWith('ws-1', {
      from_category_id: 'cat-1',
      to_category_id: 'cat-2',
      amount: '20.00',
      month: '2026-05',
      reason: 'Rebalance',
    }, expect.any(Object));
    expect(adjustmentCreatedSpy).toHaveBeenCalledWith({ id: 'adj-1' });
    expect(closedSpy).toHaveBeenCalled();
    expect(toastMock.success).toHaveBeenCalledWith('budgets.adjustment_created');
  });

  it('should load alternative categories when the selected source has no available funds', () => {
    fixture.componentRef.setInput('data', {
      workspaceId: 'ws-1',
      month: '2026-05',
      categories: [
        {
          ...categories[0],
          spent: '100',
          effective_budget: '100',
        },
        categories[1],
      ],
      toCategoryId: 'cat-2',
    });
    fixture.detectChanges();
    budgetAdjustmentsServiceMock.available.mockReturnValueOnce(of({ data: suggestedCategories }));
    component.form.patchValue({ from_category_id: 'cat-1', to_category_id: 'cat-2' });

    component.onFromCategoryChange();

    expect(budgetAdjustmentsServiceMock.available).toHaveBeenCalledWith('ws-1', '2026-05', 'cat-2', expect.any(Object));
    expect(component.suggestedCategories()).toEqual(suggestedCategories);
    expect(component.showSuggestions()).toBe(true);
  });

  it('should surface normalized feedback when suggestion loading fails', () => {
    fixture.componentRef.setInput('data', {
      workspaceId: 'ws-1',
      month: '2026-05',
      categories: [
        {
          ...categories[0],
          spent: '100',
          effective_budget: '100',
        },
        categories[1],
      ],
      toCategoryId: 'cat-2',
    });
    fixture.detectChanges();
    budgetAdjustmentsServiceMock.available.mockReturnValueOnce(throwError(() => ({
      status: 500,
      error: {
        status: 500,
        code: BACKEND_ERROR_CODES.internalError,
        message: 'Suggestion service unavailable',
        request_id: 'req-suggestions',
      },
    })));
    component.form.patchValue({ from_category_id: 'cat-1', to_category_id: 'cat-2' });

    component.onFromCategoryChange();

    expect(component.showSuggestions()).toBe(false);
    expect(component.suggestedCategories()).toEqual([]);
    expect(toastMock.error).toHaveBeenCalledWith(expect.stringContaining('Suggestion service unavailable'));
  });

  it('should fallback to normalized message for non-contract create errors', () => {
    budgetAdjustmentsServiceMock.create.mockReturnValueOnce(throwError(() => ({
      status: 500,
      error: {
        status: 500,
        code: BACKEND_ERROR_CODES.internalError,
        message: 'Budget service unavailable',
        request_id: 'req-2',
      },
    })));

    component.form.setValue({
      from_category_id: 'cat-1',
      to_category_id: 'cat-2',
      amount: '20.00',
      reason: '',
    });

    component.submit();

    expect(component.showSuggestions()).toBe(false);
    expect(toastMock.error).toHaveBeenCalledWith(expect.stringContaining('Budget service unavailable'));
  });
});
