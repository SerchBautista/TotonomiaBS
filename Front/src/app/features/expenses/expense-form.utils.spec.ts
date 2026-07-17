import { FormGroup, FormControl } from '@angular/forms';
import { vi } from 'vitest';
import { BudgetStatusResponse } from '../../core/models/budget.model';
import { Category } from '../../core/models/category.model';
import { Expense } from '../../core/models/expense.model';
import { Workspace } from '../../core/models/workspace.model';
import {
  buildAdjustmentModalData,
  buildExpenseFormPatch,
  getInlineCategoryWorkspaceSelection,
  getInlineWorkspaceSelection,
  getSafeCategoryWorkspaceIds,
} from './expense-form.utils';

describe('expense-form.utils', () => {
  const workspaces = [
    { id: 'ws-1', name: 'Home', owner_id: 'user-1' },
    { id: 'ws-2', name: 'Office', owner_id: 'user-1' },
  ] as Workspace[];

  it('returns all owned workspaces when only one exists', () => {
    expect(getInlineWorkspaceSelection('ws-1', [workspaces[0]])).toEqual(['ws-1']);
  });

  it('returns selected workspace when multiple owned workspaces exist', () => {
    expect(getInlineWorkspaceSelection('ws-2', workspaces)).toEqual(['ws-2']);
    expect(getInlineWorkspaceSelection('ws-3', workspaces)).toEqual([]);
  });

  it('ensures category workspace ids always include the active workspace', () => {
    expect(getSafeCategoryWorkspaceIds(['ws-1'], 'ws-2')).toEqual(['ws-1', 'ws-2']);
    expect(getInlineCategoryWorkspaceSelection('ws-2', workspaces)).toEqual(['ws-2']);
  });

  it('builds expense form patch from an expense entity', () => {
    const expense = {
      id: 'exp-1',
      amount: '25.50',
      date: '2026-06-01',
      category_id: 'cat-1',
      payment_type: 'card',
      payment_instrument_id: 'card-1',
      description: 'Lunch',
      paid_by_user_id: 'user-1',
    } as Expense;

    expect(buildExpenseFormPatch(expense)).toEqual({
      amount: '25.50',
      date: '2026-06-01',
      category_id: 'cat-1',
      payment_value: 'card:card-1',
      description: 'Lunch',
      paid_by_user_id: 'user-1',
    });
  });

  it('builds adjustment modal data from budget status', () => {
    const categories: Category[] = [
      { id: 'cat-1', user_id: 'u1', name: 'Food', icon: 'tag', color: '#000' },
      { id: 'cat-2', user_id: 'u1', name: 'Transport', icon: 'car', color: '#111' },
    ];
    const status: BudgetStatusResponse = {
      month: '2026-06',
      general: null,
      categories: [
        {
          category_id: 'cat-1',
          category_name: 'Food',
          category_icon: 'tag',
          category_color: '#000',
          has_budget: true,
          spent: '400',
          committed: '0',
          effective_spent: '400',
          effective_budget: '300',
          over_budget: true,
        },
      ],
    };

    const data = buildAdjustmentModalData('ws-1', '2026-06-15', 'cat-1', categories, status);

    expect(data.workspaceId).toBe('ws-1');
    expect(data.month).toBe('2026-06');
    expect(data.toCategoryId).toBe('cat-1');
    expect(data.suggestedAmount).toBe(100);
  });
});
