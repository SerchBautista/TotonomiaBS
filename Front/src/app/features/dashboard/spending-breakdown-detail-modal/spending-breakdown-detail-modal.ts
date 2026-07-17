import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { DecimalPipe } from '@angular/common';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { CategorySummary } from '../../../core/models/analytics.model';
import { formatCurrency } from '../../../shared/pipes/currency-format.pipe';

@Component({
  selector: 'app-spending-breakdown-detail-modal',
  imports: [TranslateModule, DecimalPipe, ModalShellComponent],
  templateUrl: './spending-breakdown-detail-modal.html',
  styleUrl: './spending-breakdown-detail-modal.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SpendingBreakdownDetailModalComponent {
  readonly categories = input.required<CategorySummary[]>();
  readonly filter = input.required<'all' | 'others'>();
  readonly topCount = input<number>(7);
  readonly currencyCode = input<string>('USD');

  readonly closed = output<void>();

  readonly filteredCategories = computed<CategorySummary[]>(() => {
    const all = [...this.categories()].sort((a, b) => parseFloat(b.total) - parseFloat(a.total));
    if (this.filter() === 'others') {
      return all.slice(this.topCount());
    }
    return all;
  });

  readonly total = computed(() =>
    this.filteredCategories().reduce((sum, c) => sum + parseFloat(c.total), 0),
  );

  readonly displayItems = computed(() => {
    const total = this.total();
    return this.filteredCategories().map((cat) => ({
      ...cat,
      value: parseFloat(cat.total),
      percentage: total > 0 ? (parseFloat(cat.total) / total) * 100 : 0,
    }));
  });

  readonly modalTitle = computed(() => {
    if (this.filter() === 'others') {
      return 'dashboard.detail_modal_others';
    }
    return 'dashboard.detail_modal_title';
  });

  formatAmount(value: number): string {
    return formatCurrency(value, this.currencyCode(), true);
  }

  parseFloat(value: string): number {
    return parseFloat(value);
  }
}
