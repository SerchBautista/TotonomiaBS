import { ChangeDetectionStrategy, Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { applyBackendFieldErrors, clearBackendFieldErrors } from '../../../core/errors/apply-backend-field-errors';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';

type ResetState = 'form' | 'loading' | 'success' | 'error' | 'invalid';

interface ResetPasswordQueryParams {
  token: string;
  email: string;
}

interface PasswordCriteriaState {
  readonly minLength: boolean;
  readonly uppercase: boolean;
  readonly lowercase: boolean;
  readonly number: boolean;
}

@Component({
  selector: 'app-reset-password',
  imports: [FormsModule, RouterLink, TranslateModule],
  templateUrl: './reset-password.html',
  styleUrl: './reset-password.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ResetPasswordComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authApiService = inject(AuthApiService);
  private readonly authState = inject(AUTH_STATE_TOKEN);

  readonly state = signal<ResetState>('form');
  readonly errorMessage = signal('');
  readonly showPassword = signal(false);
  readonly showPasswordConfirmation = signal(false);
  readonly cancelRedirectUrl = computed(() => this.authState.role() === 'admin' ? '/admin/dashboard' : '/user/dashboard');
  readonly showActiveSessionInterstitial = computed(() => this.state() === 'form' && this.authState.isLoggedIn());

  password = '';
  passwordConfirmation = '';

  private token = '';
  private email = '';

  ngOnInit(): void {
    const params = this.route.snapshot.queryParamMap;
    this.token = params.get('token') ?? '';
    this.email = params.get('email') ?? '';

    if (!this.token || !this.email) {
      this.state.set('invalid');
    }
  }

  togglePasswordVisibility(): void {
    this.showPassword.update((value) => !value);
  }

  togglePasswordConfirmationVisibility(): void {
    this.showPasswordConfirmation.update((value) => !value);
  }

  getPasswordCriteriaItems(): ReadonlyArray<{ key: string; met: boolean }> {
    const criteria = this.evaluatePasswordCriteria(this.password);

    return [
      { key: 'auth.password_criteria.min_length', met: criteria.minLength },
      { key: 'auth.password_criteria.uppercase', met: criteria.uppercase },
      { key: 'auth.password_criteria.lowercase', met: criteria.lowercase },
      { key: 'auth.password_criteria.number', met: criteria.number },
    ];
  }

  cancelActiveSessionReset(): void {
    void this.router.navigateByUrl(this.cancelRedirectUrl());
  }

  continueAfterLogout(): void {
    this.authApiService.logout(this.router.createUrlTree(['/user/reset-password'], {
      queryParams: this.getResetContinuationQueryParams(),
    }).toString());
  }

  submit(form: NgForm): void {
    if (this.state() === 'loading' || this.showActiveSessionInterstitial()) return;

    if (form.invalid) {
      form.control.markAllAsTouched();
      return;
    }

    clearBackendFieldErrors(form.control);
    this.state.set('loading');
    this.errorMessage.set('');

    this.authApiService
      .resetPassword(this.token, this.email, this.password, this.passwordConfirmation)
      .pipe(finalize(() => {
        if (this.state() === 'loading') {
          this.state.set('form');
        }
      }))
      .subscribe({
        next: () => {
          this.state.set('success');
          setTimeout(() => void this.router.navigateByUrl('/login'), 3000);
        },
        error: (err: unknown) => {
          const normalizedError = ensureNormalizedBackendError(err);

          if (normalizedError.code === BACKEND_ERROR_CODES.validationError) {
            const hasFieldErrors = applyBackendFieldErrors(form.control, normalizedError);

            if (hasFieldErrors) {
              this.state.set('form');
              return;
            }
          }

          if (normalizedError.code === BACKEND_ERROR_CODES.passwordResetInvalidToken) {
            this.state.set('invalid');
            return;
          }

          this.errorMessage.set(normalizedError.message);
          this.state.set('error');
        },
      });
  }

  private getResetContinuationQueryParams(): ResetPasswordQueryParams {
    return {
      token: this.token,
      email: this.email,
    };
  }

  private evaluatePasswordCriteria(password: string | null | undefined): PasswordCriteriaState {
    const normalizedPassword = password ?? '';

    return {
      minLength: normalizedPassword.length >= 8,
      uppercase: /[A-Z]/.test(normalizedPassword),
      lowercase: /[a-z]/.test(normalizedPassword),
      number: /\d/.test(normalizedPassword),
    };
  }
}
