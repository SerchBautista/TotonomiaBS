import { FormControl, FormGroup } from '@angular/forms';
import { Category } from '../../core/models/category.model';
import { WorkspacePaymentMethodSummary } from '../../core/models/payment-method.model';
import {
  getDefaultPaymentValue,
  hasPaymentValue,
  syncCategorySelection,
  syncPaymentSelection,
} from './fixed-expense-form.utils';

describe('fixed-expense-form.utils', () => {
  const methods: WorkspacePaymentMethodSummary[] = [
    {
      id: 'card-1',
      type: 'card',
      name: 'Visa',
      display_name: 'Visa',
      masked_details: '****1234',
      is_linked: true,
      is_valid_for_transactions: true,
      state: 'linked',
    },
    {
      id: 'other-1',
      type: 'other',
      name: 'PayPal',
      display_name: 'PayPal',
      masked_details: null,
      is_linked: true,
      is_valid_for_transactions: true,
      state: 'linked',
    },
  ];

  it('builds default payment value from the first method', () => {
    expect(getDefaultPaymentValue(methods)).toBe('card:card-1');
    expect(getDefaultPaymentValue([])).toBe('');
  });

  it('checks whether a payment value exists in available methods', () => {
    expect(hasPaymentValue('card:card-1', methods)).toBe(true);
    expect(hasPaymentValue('cash', methods)).toBe(false);
  });

  it('syncs payment selection to preferred or first available method', () => {
    const form = new FormGroup({ payment_value: new FormControl('') });

    syncPaymentSelection(form, methods, { fallbackToFirst: true });

    expect(form.value.payment_value).toBe('card:card-1');
  });

  it('keeps current payment value when still valid', () => {
    const form = new FormGroup({ payment_value: new FormControl('other:other-1') });

    syncPaymentSelection(form, methods, { fallbackToFirst: true });

    expect(form.value.payment_value).toBe('other:other-1');
  });

  it('syncs category selection to default or first category', () => {
    const form = new FormGroup({ category_id: new FormControl('') });
    const categories: Category[] = [
      { id: 'cat-1', user_id: 'u1', name: 'Food', icon: 'tag', color: '#000' },
      { id: 'cat-2', user_id: 'u1', name: 'Transport', icon: 'car', color: '#111', is_default: true },
    ];

    syncCategorySelection(form, categories);

    expect(form.value.category_id).toBe('cat-2');
  });
});
