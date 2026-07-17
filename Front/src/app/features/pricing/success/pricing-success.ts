import { ChangeDetectionStrategy, Component, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { delay, of } from 'rxjs';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { ApiService } from '../../../core/services/api';

interface SubscriptionResponse {
  plan: 'free' | 'premium';
  subscription_ends_at: string | null;
}

@Component({
  selector: 'app-pricing-success',
  standalone: true,
  templateUrl: './pricing-success.html',
  styleUrl: './pricing-success.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PricingSuccessComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authState = inject(AuthStateService);
  private readonly api = inject(ApiService);

  readonly isDummy = signal(false);
  readonly planConfirmed = signal(false);
  readonly confirmationError = signal<string | null>(null);

  ngOnInit(): void {
    const dummy = this.route.snapshot.queryParamMap.get('dummy');
    const isDummy = dummy === 'true';
    this.isDummy.set(isDummy);

    if (isDummy) {
      this.authState.setPlan('premium');
      this.planConfirmed.set(true);
    } else {
      this.confirmPlanFromApi();
    }
  }

  private confirmPlanFromApi(): void {
    this.confirmationError.set(null);

    of(null)
      .pipe(delay(3000))
      .subscribe(() => {
        this.api
          .get<SubscriptionResponse>('/user/subscription')
          .subscribe({
            next: (sub) => {
              if (sub.plan === 'premium') {
                this.authState.setPlan('premium');
                this.planConfirmed.set(true);
              }
            },
            error: (error) => {
              this.confirmationError.set(
                ensureNormalizedBackendError(error, {
                  fallbackMessage: 'No se pudo confirmar tu plan todavía.',
                }).message
              );
            },
          });
      });
  }

  goToDashboard(): void {
    this.router.navigate(['/user/dashboard']);
  }
}
