import { FixedExpenseFrequency, FixedExpenseType } from '../../core/models/fixed-expense.model';

export const FREQUENCIES: FixedExpenseFrequency[] = ['daily', 'weekly', 'monthly', 'yearly'];

export const EXPENSE_TYPES: { value: FixedExpenseType; labelKey: string }[] = [
  { value: 'recurring', labelKey: 'fixed_expenses.type_recurring' },
  { value: 'installment', labelKey: 'fixed_expenses.type_installment' },
];
