import {
  AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  inject,
  OnDestroy,
  OnInit,
  signal,
  viewChildren,
} from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';

type VerifyState = 'idle' | 'verifying' | 'success' | 'error' | 'locked' | 'expired';

const OTP_LENGTH = 6;
const EXPIRY_SECONDS = 300; // 5 minutes
const RESEND_COOLDOWN_SECONDS = 60;
const SESSION_STORAGE_KEY = 'two_factor_session_token';

@Component({
  selector: 'app-two-factor-verify',
  imports: [TranslateModule, RouterLink],
  templateUrl: './two-factor-verify.html',
  styleUrl: './two-factor-verify.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwoFactorVerifyComponent implements OnInit, AfterViewInit, OnDestroy {
  private readonly authApiService = inject(AuthApiService);
  // Escritura de estado post-login: requiere la implementación concreta.
  private readonly authState = inject(AuthStateService);
  private readonly preferencesService = inject(UserPreferencesService);
  private readonly router = inject(Router);

  readonly otpDigits = signal<string[]>(Array(OTP_LENGTH).fill(''));
  readonly state = signal<VerifyState>('idle');
  readonly errorMessage = signal('');
  readonly expiryCountdown = signal(EXPIRY_SECONDS);
  readonly resendCooldown = signal(RESEND_COOLDOWN_SECONDS);
  readonly resendLoading = signal(false);
  readonly resendSuccess = signal(false);
  readonly lockRetryAfter = signal(0);

  readonly otpInputs = viewChildren<ElementRef<HTMLInputElement>>('otpInput');

  private expiryInterval: ReturnType<typeof setInterval> | null = null;
  private cooldownInterval: ReturnType<typeof setInterval> | null = null;
  private lockInterval: ReturnType<typeof setInterval> | null = null;

  ngOnInit(): void {
    const sessionToken = sessionStorage.getItem(SESSION_STORAGE_KEY);
    if (!sessionToken) {
      void this.router.navigateByUrl('/login');
      return;
    }

    this.startExpiryTimer();
    this.startResendCooldown();
  }

  ngAfterViewInit(): void {
    this.focusInput(0);
  }

  ngOnDestroy(): void {
    this.clearAllIntervals();
  }

  onDigitInput(index: number, event: Event): void {
    const input = event.target as HTMLInputElement;
    const value = input.value;

    if (!/^\d*$/.test(value)) {
      input.value = this.otpDigits()[index] ?? '';
      return;
    }

    const digit = value.slice(-1);
    const digits = [...this.otpDigits()];
    digits[index] = digit;
    this.otpDigits.set(digits);

    if (digit && index < OTP_LENGTH - 1) {
      this.focusInput(index + 1);
    }

    if (digits.every((d) => d !== '')) {
      this.submitCode();
    }
  }

  onDigitKeydown(index: number, event: KeyboardEvent): void {
    if (event.key === 'Backspace') {
      const digits = [...this.otpDigits()];
      if (digits[index] === '' && index > 0) {
        this.focusInput(index - 1);
        digits[index - 1] = '';
        this.otpDigits.set(digits);
      } else {
        digits[index] = '';
        this.otpDigits.set(digits);
      }
    }

    if (event.key === 'ArrowLeft' && index > 0) {
      this.focusInput(index - 1);
    }

    if (event.key === 'ArrowRight' && index < OTP_LENGTH - 1) {
      this.focusInput(index + 1);
    }
  }

  onPaste(event: ClipboardEvent): void {
    event.preventDefault();
    const pastedData = event.clipboardData?.getData('text')?.trim() ?? '';
    if (!/^\d+$/.test(pastedData)) return;

    const digits = pastedData.slice(0, OTP_LENGTH).split('');
    const fullDigits = Array(OTP_LENGTH).fill('');
    digits.forEach((d, i) => {
      fullDigits[i] = d;
    });
    this.otpDigits.set(fullDigits);

    const nextEmpty = fullDigits.findIndex((d) => d === '');
    if (nextEmpty === -1) {
      this.focusInput(OTP_LENGTH - 1);
      this.submitCode();
    } else {
      this.focusInput(nextEmpty);
    }
  }

  resendCode(): void {
    if (this.resendLoading() || this.resendCooldown() > 0) return;

    const sessionToken = sessionStorage.getItem(SESSION_STORAGE_KEY);
    if (!sessionToken) {
      void this.router.navigateByUrl('/login');
      return;
    }

    this.resendLoading.set(true);
    this.resendSuccess.set(false);
    this.errorMessage.set('');

    this.authApiService
      .resendTwoFactorCode(sessionToken)
      .pipe(finalize(() => this.resendLoading.set(false)))
      .subscribe({
        next: (response) => {
          sessionStorage.setItem(SESSION_STORAGE_KEY, response.session_token);
          this.resendSuccess.set(true);
          this.state.set('idle');
          this.clearOtpDigits();
          this.startExpiryTimer();
          this.startResendCooldown();
          this.focusInput(0);
        },
        error: (error: unknown) => {
          const normalizedError = ensureNormalizedBackendError(error);

          if (normalizedError.code === BACKEND_ERROR_CODES.twoFactorInvalidSession) {
            this.clearSessionAndRedirect();
            return;
          }

          if (normalizedError.code === BACKEND_ERROR_CODES.twoFactorResendCooldown) {
            const retryAfter = this.extractRetryAfter(normalizedError);
            this.resendCooldown.set(retryAfter);
            this.startResendCooldownInterval();
            return;
          }

          this.errorMessage.set(normalizedError.message);
        },
      });
  }

  get formattedExpiry(): string {
    const total = this.expiryCountdown();
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
  }

  get isInputDisabled(): boolean {
    const s = this.state();
    return s === 'verifying' || s === 'success' || s === 'locked';
  }

  private submitCode(): void {
    if (this.state() === 'verifying' || this.state() === 'success') return;

    const sessionToken = sessionStorage.getItem(SESSION_STORAGE_KEY);
    if (!sessionToken) {
      void this.router.navigateByUrl('/login');
      return;
    }

    const code = this.otpDigits().join('');
    if (code.length !== OTP_LENGTH) return;

    this.state.set('verifying');
    this.errorMessage.set('');

    this.authApiService
      .verifyTwoFactorCode(sessionToken, code)
      .pipe(finalize(() => {
        if (this.state() === 'verifying') {
          this.state.set('idle');
        }
      }))
      .subscribe({
        next: (response) => {
          this.state.set('success');
          sessionStorage.removeItem(SESSION_STORAGE_KEY);
          this.clearAllIntervals();

          this.authState.applyLoginResponse(response.user, response.token);

          this.preferencesService.loadFromBackend();
          void this.router.navigateByUrl('/user/dashboard');
        },
        error: (error: unknown) => {
          const normalizedError = ensureNormalizedBackendError(error);
          this.handleError(normalizedError);
        },
      });
  }

  private handleError(error: { code: string; message: string; meta: Record<string, unknown> | null }): void {
    switch (error.code) {
      case BACKEND_ERROR_CODES.twoFactorInvalidOtpCode:
        this.state.set('error');
        this.errorMessage.set(error.message);
        this.clearOtpDigits();
        this.focusInput(0);
        break;

      case BACKEND_ERROR_CODES.twoFactorOtpCodeExpired:
        this.state.set('expired');
        this.errorMessage.set(error.message);
        this.clearOtpDigits();
        this.expiryCountdown.set(0);
        break;

      case BACKEND_ERROR_CODES.twoFactorLocked: {
        this.state.set('locked');
        this.errorMessage.set(error.message);
        const retryAfter = this.extractRetryAfter(error);
        this.lockRetryAfter.set(retryAfter);
        this.startLockTimer(retryAfter);
        break;
      }

      case BACKEND_ERROR_CODES.twoFactorInvalidSession:
        this.clearSessionAndRedirect();
        break;

      default:
        this.state.set('error');
        this.errorMessage.set(error.message);
        this.clearOtpDigits();
        this.focusInput(0);
        break;
    }
  }

  private extractRetryAfter(error: { meta: Record<string, unknown> | null }): number {
    if (error.meta && typeof error.meta['retry_after'] === 'number') {
      return error.meta['retry_after'];
    }
    return 60;
  }

  private clearOtpDigits(): void {
    this.otpDigits.set(Array(OTP_LENGTH).fill(''));
    const inputs = this.otpInputs();
    inputs.forEach((ref) => {
      if (ref?.nativeElement) {
        ref.nativeElement.value = '';
      }
    });
  }

  private focusInput(index: number): void {
    const inputs = this.otpInputs();
    if (inputs[index]?.nativeElement) {
      inputs[index].nativeElement.focus();
    }
  }

  private startExpiryTimer(): void {
    this.clearExpiryInterval();
    this.expiryCountdown.set(EXPIRY_SECONDS);

    this.expiryInterval = setInterval(() => {
      const current = this.expiryCountdown();
      if (current <= 1) {
        this.expiryCountdown.set(0);
        this.clearExpiryInterval();
        if (this.state() !== 'success' && this.state() !== 'locked') {
          this.state.set('expired');
        }
      } else {
        this.expiryCountdown.set(current - 1);
      }
    }, 1000);
  }

  private startResendCooldown(): void {
    this.resendCooldown.set(RESEND_COOLDOWN_SECONDS);
    this.startResendCooldownInterval();
  }

  private startResendCooldownInterval(): void {
    this.clearCooldownInterval();

    this.cooldownInterval = setInterval(() => {
      const current = this.resendCooldown();
      if (current <= 1) {
        this.resendCooldown.set(0);
        this.clearCooldownInterval();
      } else {
        this.resendCooldown.set(current - 1);
      }
    }, 1000);
  }

  private startLockTimer(seconds: number): void {
    this.clearLockInterval();
    this.lockRetryAfter.set(seconds);

    this.lockInterval = setInterval(() => {
      const current = this.lockRetryAfter();
      if (current <= 1) {
        this.lockRetryAfter.set(0);
        this.clearLockInterval();
        this.state.set('idle');
        this.errorMessage.set('');
        this.clearOtpDigits();
        this.focusInput(0);
      } else {
        this.lockRetryAfter.set(current - 1);
      }
    }, 1000);
  }

  private clearExpiryInterval(): void {
    if (this.expiryInterval) {
      clearInterval(this.expiryInterval);
      this.expiryInterval = null;
    }
  }

  private clearCooldownInterval(): void {
    if (this.cooldownInterval) {
      clearInterval(this.cooldownInterval);
      this.cooldownInterval = null;
    }
  }

  private clearLockInterval(): void {
    if (this.lockInterval) {
      clearInterval(this.lockInterval);
      this.lockInterval = null;
    }
  }

  private clearAllIntervals(): void {
    this.clearExpiryInterval();
    this.clearCooldownInterval();
    this.clearLockInterval();
  }

  private clearSessionAndRedirect(): void {
    sessionStorage.removeItem(SESSION_STORAGE_KEY);
    void this.router.navigateByUrl('/login');
  }
}
