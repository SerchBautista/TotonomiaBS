import { FormGroup } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { applyBackendFieldErrors } from '../../core/errors/apply-backend-field-errors';
import { ensureNormalizedBackendError } from '../../core/errors/backend-error.mapper';
import { BudgetStatusResponse, BudgetWarning } from '../../core/models/budget.model';
import { Category } from '../../core/models/category.model';
import { Expense } from '../../core/models/expense.model';
import { buildPaymentValue } from '../../core/models/payment-method.model';
import { ToastService } from '../../core/services/toast.service';
import { Workspace } from '../../core/models/workspace.model';
import { AdjustmentModalData } from '../budgets/budget-adjustment-modal/budget-adjustment-modal';

export function getInlineWorkspaceSelection(
  workspaceId: string,
  ownerWorkspaces: Workspace[],
): string[] {
  const ownedWorkspaceIds = ownerWorkspaces.map((workspace) => workspace.id);

  if (ownedWorkspaceIds.length === 1) {
    return ownedWorkspaceIds;
  }

  return ownedWorkspaceIds.includes(workspaceId) ? [workspaceId] : [];
}

export function getSafeCategoryWorkspaceIds(
  workspaceIds: readonly string[],
  workspaceId: string,
): string[] {
  const ids = new Set(workspaceIds.filter(Boolean));

  if (workspaceId) {
    ids.add(workspaceId);
  }

  return Array.from(ids);
}

export function getInlineCategoryWorkspaceSelection(
  workspaceId: string,
  ownerWorkspaces: Workspace[],
): string[] {
  return getSafeCategoryWorkspaceIds(
    getInlineWorkspaceSelection(workspaceId, ownerWorkspaces),
    workspaceId,
  );
}

export function handleInlineFormError(
  error: unknown,
  form: FormGroup,
  fallbackKey: string,
  deps: { translate: TranslateService; toastService: ToastService },
): void {
  if (applyBackendFieldErrors(form, error)) {
    return;
  }

  const normalized = ensureNormalizedBackendError(error);
  deps.toastService.error(normalized.message || deps.translate.instant(fallbackKey));
}

export function emitBudgetWarningToasts(
  warnings: BudgetWarning[] | undefined,
  translate: TranslateService,
  toastService: ToastService,
): BudgetWarning | undefined {
  let overBudgetWarning: BudgetWarning | undefined;

  for (const warning of warnings ?? []) {
    const key =
      warning.scope === 'general' ? 'budgets.warning_general' : 'budgets.warning_category';
    toastService.warning(translate.instant(key, { name: warning.category_name }));
    if (warning.scope === 'category' && warning.over_budget) {
      overBudgetWarning = warning;
    }
  }

  return overBudgetWarning;
}

export function buildAdjustmentModalData(
  workspaceId: string,
  formDate: string,
  categoryId: string,
  categories: Category[],
  status: BudgetStatusResponse,
): AdjustmentModalData {
  const month = formDate.substring(0, 7);
  const validCategoryIds = new Set(categories.map((category) => category.id));
  const validStatusCategories = status.categories.filter((category) =>
    validCategoryIds.has(category.category_id),
  );
  const toCategory = validStatusCategories.find((category) => category.category_id === categoryId);
  const effectiveBudget = toCategory?.effective_budget
    ? parseFloat(toCategory.effective_budget)
    : 0;
  const spent = toCategory ? parseFloat(toCategory.spent) : 0;

  return {
    workspaceId,
    month,
    categories: validStatusCategories,
    toCategoryId: categoryId,
    suggestedAmount: Math.max(0, spent - effectiveBudget),
  };
}

export function buildExpenseFormPatch(expense: Expense): Record<string, string> {
  const paymentInstrumentId =
    expense.payment_instrument_id ?? expense.payment_instrument?.id ?? null;

  return {
    amount: expense.amount,
    date: expense.date,
    category_id: expense.category_id ?? expense.category?.id ?? '',
    payment_value: buildPaymentValue(expense.payment_type, paymentInstrumentId),
    description: expense.description ?? '',
    paid_by_user_id: expense.paid_by_user_id ?? '',
  };
}
