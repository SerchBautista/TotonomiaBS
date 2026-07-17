import { FormControl, FormGroup } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { ToastService } from '../../core/services/toast.service';
import { handlePaymentMethodServiceError } from './payment-method-error.handler';

describe('handlePaymentMethodServiceError', () => {
  const translate = { instant: vi.fn((key: string) => key) } as unknown as TranslateService;
  const toastService = {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  } as unknown as ToastService;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows rate limit warning for 429 responses', () => {
    handlePaymentMethodServiceError('payment_methods.error_save', { status: 429 }, {
      translate,
      toastService,
    });

    expect(toastService.warning).toHaveBeenCalledWith('common.rate_limited');
  });

  it('shows email verification warning for unverified users', () => {
    handlePaymentMethodServiceError(
      'payment_methods.error_save',
      {
        status: 403,
        error: {
          status: 403,
          code: 'email_not_verified',
          message: 'Verify email',
          request_id: 'req-1',
        },
      },
      { translate, toastService },
    );

    expect(toastService.warning).toHaveBeenCalledWith('payment_methods.error_email_not_verified');
  });

  it('applies field errors and shows validation warning for 422 responses', () => {
    const form = new FormGroup({ name: new FormControl('') });

    handlePaymentMethodServiceError(
      'payment_methods.error_save',
      {
        status: 422,
        error: {
          message: 'Validation failed',
          errors: { name: ['Required'] },
        },
      },
      { translate, toastService, form },
    );

    expect(form.controls['name'].errors).toEqual({ serverError: 'Required' });
    expect(toastService.warning).toHaveBeenCalledWith('Validation failed');
  });

  it('shows generic error toast for unexpected failures', () => {
    handlePaymentMethodServiceError(
      'payment_methods.error_save',
      { status: 500, error: { message: 'Server down' } },
      { translate, toastService },
    );

    expect(toastService.error).toHaveBeenCalledWith('payment_methods.error_save: Server down');
  });
});
