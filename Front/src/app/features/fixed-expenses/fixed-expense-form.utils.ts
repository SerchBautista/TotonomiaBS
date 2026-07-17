import { FormGroup } from '@angular/forms';
import { Category } from '../../core/models/category.model';
import {
  buildPaymentValue,
  WorkspacePaymentMethodSummary,
} from '../../core/models/payment-method.model';

export function getDefaultPaymentValue(
  methods: WorkspacePaymentMethodSummary[],
): string {
  const method = methods[0];
  return method ? buildPaymentValue(method.type, method.id) : '';
}

export function hasPaymentValue(
  value: string,
  methods: WorkspacePaymentMethodSummary[],
): boolean {
  return methods.some((method) => buildPaymentValue(method.type, method.id) === value);
}

export function syncPaymentSelection(
  form: FormGroup,
  methods: WorkspacePaymentMethodSummary[],
  options?: { preferredPaymentValue?: string; fallbackToFirst?: boolean },
): void {
  const currentPaymentValue = form.value.payment_value ?? '';
  const nextPaymentValue =
    options?.preferredPaymentValue && hasPaymentValue(options.preferredPaymentValue, methods)
      ? options.preferredPaymentValue
      : hasPaymentValue(currentPaymentValue, methods)
        ? currentPaymentValue
        : options?.fallbackToFirst
          ? getDefaultPaymentValue(methods)
          : '';

  if (currentPaymentValue !== nextPaymentValue) {
    form.patchValue({ payment_value: nextPaymentValue });
  }
}

export function syncCategorySelection(form: FormGroup, categories: Category[]): void {
  const currentCategoryId = form.value.category_id ?? '';
  const isCurrentValid =
    !!currentCategoryId && categories.some((category) => category.id === currentCategoryId);

  if (isCurrentValid) {
    return;
  }

  const defaultCategory = categories.find((category) => category.is_default);
  form.patchValue({ category_id: (defaultCategory ?? categories[0])?.id ?? '' });
}
