import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { applyBackendFieldErrors, clearBackendFieldErrors } from '../../../core/errors/apply-backend-field-errors';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { UserAuthService } from '../../../core/services/user-auth';

@Component({
  selector: 'app-user-login',
  imports: [FormsModule, TranslateModule, RouterLink],
  templateUrl: './user-login.html',
  styleUrl: './user-login.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserLoginComponent {
  email = '';
  password = '';
  readonly loading = signal(false);
  readonly errorMessage = signal('');
  readonly emailUnverified = signal(false);
  readonly resendLoading = signal(false);
  readonly resendSuccess = signal(false);

  private readonly userAuthService = inject(UserAuthService);
  private readonly authApiService = inject(AuthApiService);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);

  submit(form: NgForm): void {
    if (form.invalid) {
      form.control.markAllAsTouched();
      return;
    }

    clearBackendFieldErrors(form.control);
    this.errorMessage.set('');
    this.emailUnverified.set(false);
    this.resendSuccess.set(false);
    this.loading.set(true);

    this.userAuthService
      .login(this.email, this.password)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe(({ error, emailUnverified }) => {
        if (error) {
          applyBackendFieldErrors(form.control, error);
        }

        this.errorMessage.set(this.resolveErrorMessage(error));
        this.emailUnverified.set(emailUnverified);
      });
  }

  resendVerification(form: NgForm): void {
    if (this.resendLoading()) return;

    clearBackendFieldErrors(form.control);
    this.resendLoading.set(true);
    this.resendSuccess.set(false);

    this.authApiService
      .resendVerification(this.email)
      .pipe(finalize(() => this.resendLoading.set(false)))
      .subscribe({
        next: () => {
          this.resendSuccess.set(true);
          this.errorMessage.set('');
          sessionStorage.setItem('pendingVerificationEmail', this.email);
          void this.router.navigateByUrl('/user/verify-email-pending');
        },
        error: (error: unknown) => {
          const normalizedError = ensureNormalizedBackendError(error);
          applyBackendFieldErrors(form.control, normalizedError);
          this.errorMessage.set(normalizedError.message);
        },
      });
  }

  private resolveErrorMessage(error: { code: string; message: string } | null): string {
    if (!error) {
      return '';
    }

    if (error.code === BACKEND_ERROR_CODES.authRoleMismatch) {
      return this.translate.instant('auth.errors.role_mismatch');
    }

    return error.message;
  }
}
