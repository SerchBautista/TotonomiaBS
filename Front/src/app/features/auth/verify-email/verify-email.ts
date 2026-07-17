import { ChangeDetectionStrategy, Component, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthApiService } from '../../../core/services/auth-api.service';

type VerifyState = 'loading' | 'success' | 'error' | 'invalid';

@Component({
  selector: 'app-verify-email',
  imports: [TranslateModule, RouterLink],
  templateUrl: './verify-email.html',
  styleUrl: './verify-email.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VerifyEmailComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authApiService = inject(AuthApiService);

  readonly state = signal<VerifyState>('loading');
  readonly errorMessage = signal('');

  ngOnInit(): void {
    const params = this.route.snapshot.queryParamMap;
    const id = params.get('id');
    const hash = params.get('hash');
    const expires = params.get('expires');
    const signature = params.get('signature');

    if (!id || !hash || !expires || !signature) {
      this.state.set('invalid');
      return;
    }

    this.authApiService.verifyEmail(id, hash, expires, signature).subscribe({
      next: () => {
        this.state.set('success');
        setTimeout(() => void this.router.navigateByUrl('/login'), 3000);
      },
      error: (err: unknown) => {
        const normalizedError = ensureNormalizedBackendError(err);

        if (normalizedError.code === BACKEND_ERROR_CODES.emailVerificationInvalid) {
          this.state.set('invalid');
          return;
        }

        this.errorMessage.set(normalizedError.message);
        this.state.set('error');
      },
    });
  }
}
