import { FormBuilder } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { Category } from '../../core/models/category.model';
import { ToastService } from '../../core/services/toast.service';
import {
  createBudgetFormGroup,
  createCategoryBudgetFormGroup,
  isThresholdInvalid,
  syncCategoryBudgetSelection,
} from './budget-form.utils';

describe('budget-form.utils', () => {
  const translate = { instant: vi.fn((key: string) => key) } as unknown as TranslateService;
  const toast = {
    error: vi.fn(),
    success: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  } as unknown as ToastService;

  it('reports invalid threshold when it exceeds the budget amount', () => {
    const invalid = isThresholdInvalid(toast, translate, 100, 150);

    expect(invalid).toBe(true);
    expect(toast.error).toHaveBeenCalledWith('budgets.alert_exceeds_amount');
  });

  it('accepts threshold within budget amount', () => {
    expect(isThresholdInvalid(toast, translate, 100, 80)).toBe(false);
  });

  it('creates budget form groups with required validators', () => {
    const fb = new FormBuilder();
    const general = createBudgetFormGroup(fb);
    const category = createCategoryBudgetFormGroup(fb);

    expect(general.controls['amount'].hasError('required')).toBe(true);
    expect(category.controls['category_id'].hasError('required')).toBe(true);
  });

  it('clears invalid category selection', () => {
    const fb = new FormBuilder();
    const form = createCategoryBudgetFormGroup(fb);
    form.patchValue({ category_id: 'missing' });

    const categories: Category[] = [
      { id: 'cat-1', user_id: 'u1', name: 'Food', icon: 'tag', color: '#000' },
    ];
    syncCategoryBudgetSelection(form, categories);

    expect(form.value.category_id).toBe('');
  });
});
