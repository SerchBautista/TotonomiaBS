import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthApiService } from '../../../core/services/auth-api.service';

const RESEND_COOLDOWN_SECONDS = 30;

@Component({
  selector: 'app-verify-email-pending',
  imports: [TranslateModule],
  templateUrl: './verify-email-pending.html',
  styleUrl: './verify-email-pending.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VerifyEmailPendingComponent {
  private readonly authApiService = inject(AuthApiService);

  readonly loading = signal(false);
  readonly resentSuccess = signal(false);
  readonly errorMessage = signal('');
  readonly cooldown = signal(0);

  get email(): string {
    // email stored after registration redirect
    return sessionStorage.getItem('pendingVerificationEmail') ?? '';
  }

  resend(): void {
    if (this.loading() || this.cooldown() > 0) return;

    this.loading.set(true);
    this.resentSuccess.set(false);
    this.errorMessage.set('');

    this.authApiService
      .resendVerification(this.email)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => {
          this.resentSuccess.set(true);
          this.startCooldown();
        },
        error: (error: unknown) => {
          this.resentSuccess.set(false);
          this.errorMessage.set(ensureNormalizedBackendError(error).message);
        },
      });
  }

  private startCooldown(): void {
    this.cooldown.set(RESEND_COOLDOWN_SECONDS);
    const interval = setInterval(() => {
      const current = this.cooldown();
      if (current <= 1) {
        this.cooldown.set(0);
        clearInterval(interval);
      } else {
        this.cooldown.set(current - 1);
      }
    }, 1000);
  }
}
