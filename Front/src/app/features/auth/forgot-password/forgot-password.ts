import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { applyBackendFieldErrors, clearBackendFieldErrors } from '../../../core/errors/apply-backend-field-errors';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthApiService } from '../../../core/services/auth-api.service';

@Component({
  selector: 'app-forgot-password',
  imports: [FormsModule, TranslateModule],
  templateUrl: './forgot-password.html',
  styleUrl: './forgot-password.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForgotPasswordComponent {
  private readonly authApiService = inject(AuthApiService);

  readonly loading = signal(false);
  readonly submitted = signal(false);
  readonly errorMessage = signal('');

  email = '';

  submit(form: NgForm): void {
    if (this.loading()) return;

    if (form.invalid) {
      form.control.markAllAsTouched();
      return;
    }

    clearBackendFieldErrors(form.control);
    this.loading.set(true);
    this.errorMessage.set('');

    this.authApiService
      .forgotPassword(this.email)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => {
          this.submitted.set(true);
        },
        error: (error: unknown) => {
          const normalizedError = ensureNormalizedBackendError(error);
          const hasFieldErrors = applyBackendFieldErrors(form.control, normalizedError);

          this.errorMessage.set(hasFieldErrors ? '' : normalizedError.message);
        },
      });
  }
}
