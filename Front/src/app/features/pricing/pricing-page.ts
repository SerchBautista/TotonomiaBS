import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { AuthStateService } from '../../core/services/auth-state.service';
import { SubscriptionService } from '../../core/services/subscription.service';
import { PlanCardComponent, PlanInfo } from '../../shared/plan-card/plan-card';

const FREE_PLAN: PlanInfo = {
  id: 'free',
  name: 'Free',
  price: '$0 / mes',
  features: [
    '1 workspace',
    'Gastos ilimitados',
    'Categorías básicas',
  ],
};

const PREMIUM_PLAN: PlanInfo = {
  id: 'premium',
  name: 'Premium',
  price: '$9.99 / mes',
  features: [
    'Workspaces ilimitados',
    'Gastos ilimitados',
    'Categorías personalizadas',
    'Analíticas avanzadas',
    'Miembros en workspace',
    'Soporte prioritario',
  ],
};

@Component({
  selector: 'app-pricing-page',
  standalone: true,
  imports: [PlanCardComponent, RouterLink, TranslateModule],
  templateUrl: './pricing-page.html',
  styleUrl: './pricing-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PricingPageComponent {
  private readonly router = inject(Router);
  private readonly authState = inject(AuthStateService);
  private readonly subscriptionService = inject(SubscriptionService);

  readonly freePlan = FREE_PLAN;
  readonly premiumPlan = PREMIUM_PLAN;

  readonly loading = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly showDummyBanner = signal(false);

  get currentPlan() {
    return this.authState.plan();
  }

  onUpgrade(): void {
    if (!this.authState.isLoggedIn()) {
      this.router.navigate(['/login'], { queryParams: { redirect: '/pricing' } });
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    this.subscriptionService.initiateCheckout().subscribe({
      next: (session) => {
        this.loading.set(false);
        if (session.is_dummy) {
          this.showDummyBanner.set(true);
          setTimeout(() => this.router.navigateByUrl(session.url), 1500);
        } else {
          window.location.href = session.url;
        }
      },
      error: () => {
        this.loading.set(false);
        this.errorMessage.set('No se pudo iniciar el proceso de pago. Intenta de nuevo.');
      },
    });
  }
}
