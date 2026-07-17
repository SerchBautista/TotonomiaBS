import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { applyBackendFieldErrors, clearBackendFieldErrors } from '../../../core/errors/apply-backend-field-errors';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { AdminAuthService } from '../../../core/services/admin-auth';

@Component({
  selector: 'app-admin-login',
  imports: [FormsModule, TranslateModule],
  templateUrl: './admin-login.html',
  styleUrl: './admin-login.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminLoginComponent {
  email = '';
  password = '';
  readonly loading = signal(false);
  readonly errorMessage = signal('');

  private readonly adminAuthService = inject(AdminAuthService);
  private readonly translate = inject(TranslateService);

  submit(form: NgForm): void {
    if (form.invalid) {
      form.control.markAllAsTouched();
      return;
    }

    clearBackendFieldErrors(form.control);
    this.errorMessage.set('');
    this.loading.set(true);

    this.adminAuthService
      .login(this.email, this.password)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe(({ error }) => {
        if (error) {
          applyBackendFieldErrors(form.control, error);
        }

        this.errorMessage.set(this.resolveErrorMessage(error));
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
