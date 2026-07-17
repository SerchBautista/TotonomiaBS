import { FormControl, FormGroup } from '@angular/forms';
import { Workspace } from '../../core/models/workspace.model';
import {
  applyFormTypeRules,
  buildPayload,
  defaultWorkspaceSelection,
  parseLast4FromMasked,
  resetPaymentMethodForm,
  trimOrNull,
} from './payment-method-form.utils';

describe('payment-method-form.utils', () => {
  const workspaces = [
    { id: 'ws-1', name: 'Home', owner_id: 'user-1' },
    { id: 'ws-2', name: 'Office', owner_id: 'user-1' },
  ] as Workspace[];

  function createForm(): FormGroup {
    return new FormGroup({
      type: new FormControl('card'),
      name: new FormControl('My Card'),
      card_type: new FormControl('credit'),
      brand: new FormControl('Visa'),
      last_4_digits: new FormControl('1234'),
      description: new FormControl(''),
    });
  }

  it('trims strings and returns null for empty values', () => {
    expect(trimOrNull('  hello  ')).toBe('hello');
    expect(trimOrNull('   ')).toBeNull();
    expect(trimOrNull(null)).toBeNull();
  });

  it('parses last four digits from masked card details', () => {
    expect(parseLast4FromMasked('****1234')).toBe('1234');
    expect(parseLast4FromMasked('invalid')).toBe('');
  });

  it('selects the only workspace by default', () => {
    expect(defaultWorkspaceSelection([workspaces[0]])).toEqual(['ws-1']);
    expect(defaultWorkspaceSelection(workspaces)).toEqual([]);
  });

  it('applies card-specific validators and builds card payload', () => {
    const form = createForm();
    applyFormTypeRules('card', form);

    expect(form.controls['last_4_digits'].valid).toBe(true);

    const payload = buildPayload('card', form, ['ws-1']);
    expect(payload).toMatchObject({
      type: 'card',
      name: 'My Card',
      card_type: 'credit',
      brand: 'Visa',
      last_4_digits: '1234',
      workspace_ids: ['ws-1'],
    });
  });

  it('applies other-type rules and builds other payload', () => {
    const form = createForm();
    applyFormTypeRules('other', form);
    form.patchValue({ description: 'PayPal account' });

    const payload = buildPayload('other', form, ['ws-1']);
    expect(payload).toMatchObject({
      type: 'other',
      description: 'PayPal account',
    });
    expect(payload).not.toHaveProperty('last_4_digits');
  });

  it('resets the form to card defaults', () => {
    const form = createForm();
    form.patchValue({ name: 'Changed', type: 'other' });

    resetPaymentMethodForm(form);

    expect(form.value).toMatchObject({
      type: 'card',
      name: '',
      card_type: 'credit',
      brand: '',
      last_4_digits: '',
      description: '',
    });
  });
});
