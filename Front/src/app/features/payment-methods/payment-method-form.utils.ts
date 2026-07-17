import { FormGroup, Validators } from '@angular/forms';
import {
  PaymentType,
  WorkspacePaymentMethodCreatePayload,
} from '../../core/models/payment-method.model';
import { Workspace } from '../../core/models/workspace.model';

export type PaymentMethodType = Extract<PaymentType, 'card' | 'other'>;

export function trimOrNull(value: string | null | undefined): string | null {
  const trimmed = value?.trim() ?? '';
  return trimmed ? trimmed : null;
}

export function parseLast4FromMasked(masked: string | null | undefined): string {
  const last4Match = /\*\*\*\*(\d{4})/.exec(masked ?? '');
  return last4Match ? last4Match[1] : '';
}

export function defaultWorkspaceSelection(ownerWorkspaces: Workspace[]): string[] {
  return ownerWorkspaces.length === 1 ? [ownerWorkspaces[0].id] : [];
}

export function applyFormTypeRules(type: PaymentMethodType, form: FormGroup): void {
  const cardTypeControl = form.controls['card_type'];
  const last4Control = form.controls['last_4_digits'];
  const descriptionControl = form.controls['description'];
  const brandControl = form.controls['brand'];

  if (type === 'card') {
    cardTypeControl.setValidators([Validators.required]);
    last4Control.setValidators([Validators.required, Validators.pattern(/^\d{4}$/)]);
    descriptionControl.clearValidators();
  } else {
    cardTypeControl.clearValidators();
    last4Control.clearValidators();
    descriptionControl.setValidators([Validators.maxLength(1000)]);
    cardTypeControl.setValue('credit', { emitEvent: false });
    brandControl.setValue('', { emitEvent: false });
    last4Control.setValue('', { emitEvent: false });
  }

  cardTypeControl.updateValueAndValidity({ emitEvent: false });
  last4Control.updateValueAndValidity({ emitEvent: false });
  descriptionControl.updateValueAndValidity({ emitEvent: false });
}

export function buildPayload(
  type: PaymentMethodType,
  form: FormGroup,
  workspaceIds: string[],
): WorkspacePaymentMethodCreatePayload {
  return {
    type,
    name: (form.controls['name'].value ?? '').trim(),
    workspace_ids: workspaceIds,
    ...(type === 'card'
      ? {
          card_type: form.controls['card_type'].value ?? 'credit',
          brand: trimOrNull(form.controls['brand'].value),
          last_4_digits: trimOrNull(form.controls['last_4_digits'].value),
        }
      : {
          description: trimOrNull(form.controls['description'].value),
        }),
  };
}

export function resetPaymentMethodForm(form: FormGroup): void {
  form.reset({
    type: 'card',
    name: '',
    card_type: 'credit',
    brand: '',
    last_4_digits: '',
    description: '',
  });
  applyFormTypeRules('card', form);
}
