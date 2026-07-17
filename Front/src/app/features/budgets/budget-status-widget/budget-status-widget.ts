import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { TranslateModule } from '@ngx-translate/core';
import { BudgetCategoryScopeStatus, BudgetScopeStatus, BudgetStatusResponse } from '../../../core/models/budget.model';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';

@Component({
  selector: 'app-budget-status-widget',
  imports: [TranslateModule, CurrencyFormatPipe, DecimalPipe],
  templateUrl: './budget-status-widget.html',
  styleUrl: './budget-status-widget.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class BudgetStatusWidgetComponent {
  readonly status = input.required<BudgetStatusResponse>();
  readonly currencyCode = input<string>('USD');

  progressColor(scope: BudgetScopeStatus | BudgetCategoryScopeStatus): string {
    const pct = scope.percentage ?? 0;
    const effectiveSpent = parseFloat(scope.effective_spent || scope.spent);
    const budget = parseFloat(scope.budget || '0');
    const threshold = scope.alert_threshold ?? 0;
    if (budget > 0 && effectiveSpent >= budget) return 'over';
    if (scope.alert_enabled && threshold > 0 && effectiveSpent >= budget * threshold) return 'warning';
    return 'ok';
  }

  private getBudget(scope: BudgetScopeStatus | BudgetCategoryScopeStatus): number {
    const cat = scope as BudgetCategoryScopeStatus;
    return parseFloat(cat.effective_budget || scope.budget || '0');
  }

  progressPercent(scope: BudgetScopeStatus | BudgetCategoryScopeStatus): number {
    const spent = parseFloat(scope.spent);
    const budget = this.getBudget(scope);
    if (budget <= 0) return 0;
    return Math.min((spent / budget) * 100, 100);
  }

  committedPercent(scope: BudgetScopeStatus | BudgetCategoryScopeStatus): number {
    const committed = parseFloat(scope.committed || '0');
    const budget = this.getBudget(scope);
    if (budget <= 0) return 0;
    return Math.min((committed / budget) * 100, 100);
  }

  effectivePercent(cat: BudgetCategoryScopeStatus): number {
    const effectiveSpent = parseFloat(cat.effective_spent || cat.spent);
    const effectiveBudget = parseFloat(cat.effective_budget || cat.budget || '0');
    if (effectiveBudget <= 0) return 0;
    return effectiveSpent / effectiveBudget;
  }

  simpleSpentPercent(cat: BudgetCategoryScopeStatus): number {
    const spent = parseFloat(cat.spent);
    const effectiveBudget = parseFloat(cat.effective_budget || cat.budget || '0');
    if (effectiveBudget <= 0) return 100;
    return Math.min((spent / effectiveBudget) * 100, 100);
  }

  getEffectiveBudget(cat: BudgetCategoryScopeStatus): string {
    return cat.effective_budget || cat.budget || '0';
  }

  hasAdjustments(cat: BudgetCategoryScopeStatus): boolean {
    return parseFloat(cat.adjustments_in ?? '0') > 0 || parseFloat(cat.adjustments_out ?? '0') > 0;
  }
}