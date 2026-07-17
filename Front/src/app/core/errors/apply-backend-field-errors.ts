import { AbstractControl, FormArray, FormGroup } from '@angular/forms';
import { ensureNormalizedBackendError } from './backend-error.mapper';

export const BACKEND_FIELD_ERROR_KEY = 'serverError';

export function applyBackendFieldErrors(form: AbstractControl, error: unknown): boolean {
  clearBackendFieldErrors(form);

  const normalizedError = ensureNormalizedBackendError(error);
  if (!normalizedError.fieldErrors) {
    return false;
  }

  let hasAppliedErrors = false;

  for (const [field, messages] of Object.entries(normalizedError.fieldErrors)) {
    const control = form.get(field);
    const firstMessage = messages[0];

    if (!control || !firstMessage) {
      continue;
    }

    control.setErrors({
      ...(control.errors ?? {}),
      [BACKEND_FIELD_ERROR_KEY]: firstMessage,
    });
    control.markAsTouched();
    hasAppliedErrors = true;
  }

  return hasAppliedErrors;
}

export function clearBackendFieldErrors(control: AbstractControl): void {
  clearControlBackendFieldError(control);

  if (control instanceof FormGroup) {
    Object.values(control.controls).forEach((childControl) => clearBackendFieldErrors(childControl));
  }

  if (control instanceof FormArray) {
    control.controls.forEach((childControl) => clearBackendFieldErrors(childControl));
  }
}

function clearControlBackendFieldError(control: AbstractControl): void {
  if (!control.errors?.[BACKEND_FIELD_ERROR_KEY]) {
    return;
  }

  const { [BACKEND_FIELD_ERROR_KEY]: _, ...remainingErrors } = control.errors;
  control.setErrors(Object.keys(remainingErrors).length ? remainingErrors : null);
}
