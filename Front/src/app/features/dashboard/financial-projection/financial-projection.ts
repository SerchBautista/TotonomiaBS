import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { ProjectionData } from '../../../core/models/analytics.model';
import { formatCurrency } from '../../../shared/pipes/currency-format.pipe';

@Component({
  selector: 'app-financial-projection',
  imports: [TranslateModule],
  templateUrl: './financial-projection.html',
  styleUrl: './financial-projection.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FinancialProjectionComponent {
  readonly projection = input.required<ProjectionData>();
  readonly currencyCode = input<string>('USD');

  readonly progressPct = computed(() => {
    const p = this.projection();
    return Math.min((p.days_elapsed / p.days_in_month) * 100, 100);
  });

  readonly spentPct = computed(() => {
    const p = this.projection();
    const max = Math.max(p.projected_total, p.current_month_total, 1);
    return Math.min((p.current_month_total / max) * 100, 100);
  });

  readonly projectedPct = computed(() => {
    const p = this.projection();
    const max = Math.max(p.projected_total, p.current_month_total, 1);
    return Math.min((p.projected_total / max) * 100, 100);
  });

  readonly daysRemaining = computed(() => {
    const p = this.projection();
    return p.days_in_month - p.days_elapsed;
  });

  readonly remainingProjected = computed(() => {
    const p = this.projection();
    return Math.max(p.projected_total - p.current_month_total, 0);
  });

  formatAmount(value: number): string {
    return formatCurrency(value, this.currencyCode(), true);
  }
}
