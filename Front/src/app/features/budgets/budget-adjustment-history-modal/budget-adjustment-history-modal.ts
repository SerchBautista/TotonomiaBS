import { ChangeDetectionStrategy, Component, DestroyRef, inject, input, output, signal } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { BudgetAdjustmentsService } from '../../../core/services/budget-adjustments.service';
import { BudgetAdjustment } from '../../../core/models/budget-adjustment.model';
import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';
import { TranslateService } from '@ngx-translate/core';
import { computed } from '@angular/core';

@Component({
  selector: 'app-budget-adjustment-history-modal',
  imports: [
    TranslateModule,
    CurrencyFormatPipe,
    ModalShellComponent,
    DataTableComponent,
    TableCellDirective,
    StatusBadgeComponent,
    LoadingStateComponent,
  ],
  templateUrl: './budget-adjustment-history-modal.html',
  styleUrl: './budget-adjustment-history-modal.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BudgetAdjustmentHistoryModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly adjustmentsService = inject(BudgetAdjustmentsService);
  private readonly translate = inject(TranslateService);

  readonly open = input.required<boolean>();
  readonly workspaceId = input.required<string>();
  readonly workspaceName = input<string>('');
  readonly categoryId = input.required<string>();
  readonly categoryName = input<string>('');
  readonly closed = output<void>();

  readonly loading = signal(false);
  readonly adjustments = signal<BudgetAdjustment[]>([]);

  readonly selectedMonth = signal(this.getCurrentMonth());
  readonly availableMonths = signal(this.generateLast12Months());

  readonly columns = computed<TableColumn<BudgetAdjustment>[]>(() => [
    { key: 'date', header: this.translate.instant('budgets.history_date'), width: '140px' },
    { key: 'direction', header: this.translate.instant('budgets.history_direction'), width: '100px' },
    { key: 'category', header: this.translate.instant('budgets.history_category') },
    { key: 'amount', header: this.translate.instant('budgets.history_amount'), align: 'right', width: '140px' },
    { key: 'reason', header: this.translate.instant('budgets.history_reason') },
  ]);

  onMonthChange(): void {
    this.loadAdjustments();
  }

  loadAdjustments(): void {
    const wsId = this.workspaceId();
    const catId = this.categoryId();
    const month = this.selectedMonth();
    if (!wsId || !catId || !month) return;

    this.loading.set(true);
    this.adjustmentsService.list(wsId, month, catId)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (r) => this.adjustments.set(r.data),
        error: () => this.adjustments.set([]),
      });
  }

  private getCurrentMonth(): string {
    return new Date().toISOString().slice(0, 7);
  }

  private generateLast12Months(): string[] {
    const now = new Date();
    const months: string[] = [];
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      months.push(d.toISOString().slice(0, 7));
    }
    return months;
  }

  getDirection(adjustment: BudgetAdjustment): 'in' | 'out' {
    return adjustment.to_category_id === this.categoryId() ? 'in' : 'out';
  }

  getCounterCategory(adjustment: BudgetAdjustment): string {
    if (this.getDirection(adjustment) === 'in') {
      return adjustment.from_category?.name ?? adjustment.from_category_id ?? '';
    }
    return adjustment.to_category?.name ?? adjustment.to_category_id ?? '';
  }

  formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
  }

  formatMonth(monthStr: string): string {
    const [year, month] = monthStr.split('-');
    const d = new Date(parseInt(year), parseInt(month) - 1, 1);
    return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  }

  close(): void {
    this.adjustments.set([]);
    this.closed.emit();
  }
}
