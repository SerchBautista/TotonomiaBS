import { FormGroup } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { BACKEND_ERROR_CODES } from '../../core/errors/backend-error-codes';
import { ensureNormalizedBackendError } from '../../core/errors/backend-error.mapper';
import { applyBackendFieldErrors } from '../../core/errors/apply-backend-field-errors';
import { ToastService } from '../../core/services/toast.service';

export function handlePaymentMethodServiceError(
  i18nKey: string,
  error: unknown,
  deps: {
    translate: TranslateService;
    toastService: ToastService;
    form?: FormGroup;
  },
): void {
  const normalized = ensureNormalizedBackendError(error, {
    fallbackMessage: deps.translate.instant(i18nKey),
  });

  if (normalized.status === 429) {
    deps.toastService.warning(deps.translate.instant('common.rate_limited'));
    return;
  }

  if (
    normalized.code === BACKEND_ERROR_CODES.authEmailNotVerified ||
    normalized.code === 'email_not_verified'
  ) {
    deps.toastService.warning(deps.translate.instant('payment_methods.error_email_not_verified'));
    return;
  }

  if (
    normalized.code === 'user_has_no_default_workspace' ||
    normalized.code === 'user_payment_method_not_found' ||
    normalized.code === 'payment_method_not_found'
  ) {
    deps.toastService.warning(
      normalized.message || deps.translate.instant('payment_methods.error_not_found'),
    );
    return;
  }

  if (normalized.code === 'payment_method_in_use') {
    deps.toastService.warning(
      normalized.message || deps.translate.instant('payment_methods.error_in_use'),
    );
    return;
  }

  if (normalized.status === 404) {
    deps.toastService.warning(deps.translate.instant('payment_methods.error_not_found'));
    return;
  }

  if (normalized.status === 422) {
    if (deps.form) {
      applyBackendFieldErrors(deps.form, error);
    }
    deps.toastService.warning(
      normalized.message || deps.translate.instant('payment_methods.error_validation'),
    );
    return;
  }

  if (normalized.status === 409) {
    deps.toastService.warning(normalized.message || deps.translate.instant(i18nKey));
    return;
  }

  const base = deps.translate.instant(i18nKey);
  deps.toastService.error(
    normalized.message && normalized.message !== base ? `${base}: ${normalized.message}` : base,
  );
}
