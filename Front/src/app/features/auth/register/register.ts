import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { applyBackendFieldErrors, clearBackendFieldErrors } from '../../../core/errors/apply-backend-field-errors';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthApiService } from '../../../core/services/auth-api.service';

export function passwordComplexityValidator(control: AbstractControl): ValidationErrors | null {
  const value = control.value;
  if (!value) return null;
  const hasUpperCase = /[A-Z]/.test(value);
  const hasLowerCase = /[a-z]/.test(value);
  const hasNumeric = /\d/.test(value);
  return (hasUpperCase && hasLowerCase && hasNumeric) ? null : { passwordComplexity: true };
}

interface PasswordCriteriaState {
  readonly minLength: boolean;
  readonly uppercase: boolean;
  readonly lowercase: boolean;
  readonly number: boolean;
}

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, TranslateModule, RouterLink],
  templateUrl: './register.html',
  styleUrl: './register.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RegisterComponent {
  private readonly authApiService = inject(AuthApiService);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);

  readonly loading = signal(false);
  readonly errorMessage = signal('');
  readonly showPassword = signal(false);
  readonly showPasswordConfirmation = signal(false);
  readonly form: FormGroup = this.fb.group(
    {
      name: ['', [Validators.required, Validators.maxLength(255)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
      password: ['', [Validators.required, Validators.minLength(8), passwordComplexityValidator]],
      password_confirmation: ['', [Validators.required]],
    },
    { validators: this.passwordsMatchValidator }
  );
  private readonly passwordValue = toSignal(this.form.get('password')!.valueChanges, {
    initialValue: this.form.get('password')?.value as string ?? '',
  });
  readonly passwordCriteria = computed<PasswordCriteriaState>(() => this.evaluatePasswordCriteria(this.passwordValue()));

  readonly passwordCriteriaItems = computed(() => {
    const criteria = this.passwordCriteria();
    return [
      { key: 'auth.password_criteria.min_length', met: criteria.minLength },
      { key: 'auth.password_criteria.uppercase', met: criteria.uppercase },
      { key: 'auth.password_criteria.lowercase', met: criteria.lowercase },
      { key: 'auth.password_criteria.number', met: criteria.number },
    ];
  });

  togglePasswordVisibility(): void {
    this.showPassword.update((value) => !value);
  }

  togglePasswordConfirmationVisibility(): void {
    this.showPasswordConfirmation.update((value) => !value);
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    clearBackendFieldErrors(this.form);
    this.errorMessage.set('');
    this.loading.set(true);

    this.authApiService
      .register(this.form.value)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => {
          sessionStorage.setItem('pendingVerificationEmail', this.form.value.email as string);
          void this.router.navigateByUrl('/user/verify-email-pending');
        },
        error: (error: unknown) => {
          const normalizedError = ensureNormalizedBackendError(error);
          const hasFieldErrors = applyBackendFieldErrors(this.form, normalizedError);

          this.errorMessage.set(hasFieldErrors ? '' : normalizedError.message);
        },
      });
  }

  private passwordsMatchValidator(group: FormGroup): Record<string, boolean> | null {
    const password = group.get('password')?.value as string;
    const confirm = group.get('password_confirmation')?.value as string;
    return password && confirm && password !== confirm ? { passwordsMismatch: true } : null;
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
