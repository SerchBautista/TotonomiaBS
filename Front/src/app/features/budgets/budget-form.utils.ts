import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { Category } from '../../core/models/category.model';
import { Budget } from '../../core/models/budget.model';
import { ToastService } from '../../core/services/toast.service';

export function isThresholdInvalid(
  toast: ToastService,
  translate: TranslateService,
  amount: unknown,
  threshold: unknown,
): boolean {
  const amt = parseFloat(String(amount ?? 0));
  const thr = parseFloat(String(threshold ?? 0));
  if (thr > amt) {
    toast.error(translate.instant('budgets.alert_exceeds_amount'));
    return true;
  }
  return false;
}

export function createBudgetFormGroup(fb: FormBuilder): FormGroup {
  return fb.group({
    amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
    alert_threshold: [0, [Validators.required, Validators.min(0)]],
    alert_enabled: [true],
  });
}

export function createCategoryBudgetFormGroup(fb: FormBuilder): FormGroup {
  return fb.group({
    category_id: ['', [Validators.required]],
    amount: ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
    alert_threshold: [0, [Validators.required, Validators.min(0)]],
    alert_enabled: [true],
  });
}

export function syncCategoryBudgetSelection(form: FormGroup, categories: Category[]): void {
  const selectedCategoryId = form.value.category_id ?? '';
  if (!selectedCategoryId) {
    return;
  }

  const isValid = categories.some((category) => category.id === selectedCategoryId);
  if (isValid) {
    return;
  }

  form.patchValue({ category_id: '' });
}

export interface BudgetChangeEvent {
  action: 'created' | 'updated' | 'deleted';
  budget?: Budget;
  budgetId?: string;
}
