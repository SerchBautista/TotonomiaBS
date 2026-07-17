import { Component, EventEmitter, Input, Output } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';

export interface TableColumn {
  key: string;
  label: string;
  sortable?: boolean;
}

@Component({
  selector: 'app-server-table',
  imports: [TranslateModule],
  templateUrl: './server-table.html',
  styleUrl: './server-table.scss',
})
export class ServerTableComponent {
  @Input() columns: TableColumn[] = [];
  @Input() rows: Record<string, unknown>[] = [];
  @Input() loading = false;
  @Input() currentPage = 1;
  @Input() lastPage = 1;
  @Input() total = 0;
  @Input() perPage = 10;
  @Input() perPageOptions: number[] = [10, 25, 50, 100];
  @Input() sortBy = 'created_at';
  @Input() sortDir: 'asc' | 'desc' = 'desc';
  @Input() emptyLabel = '';
  @Input() deleteButtonVariant: 'danger' | 'warning' = 'danger';
  /**
   * i18n key used for the actions column header. Empty by default; the
   * consumer feature must provide its own label (e.g. `administrators.actions`).
   */
  @Input() actionsLabelKey = '';
  /** i18n key for the "view" row action button. */
  @Input() viewLabelKey = '';
  /** i18n key for the "edit" row action button. */
  @Input() editLabelKey = '';
  /** i18n key for the "delete" row action button. */
  @Input() deleteLabelKey = '';
  /** i18n key for the "rows per page" select label. */
  @Input() perPageLabelKey = '';
  /** i18n key for the previous page button. */
  @Input() prevLabelKey = '';
  /** i18n key for the next page button. */
  @Input() nextLabelKey = '';
  /** Optional aria-label for the table. */
  @Input() ariaLabel = '';

  @Output() sortChanged = new EventEmitter<{ sortBy: string; sortDir: 'asc' | 'desc' }>();
  @Output() pageChanged = new EventEmitter<number>();
  @Output() perPageChanged = new EventEmitter<number>();
  @Output() actionClicked = new EventEmitter<{
    action: 'view' | 'edit' | 'delete';
    row: Record<string, unknown>;
  }>();

  onSort(column: TableColumn): void {
    if (!column.sortable) {
      return;
    }

    const nextSortDir = this.sortBy === column.key && this.sortDir === 'asc' ? 'desc' : 'asc';
    this.sortChanged.emit({ sortBy: column.key, sortDir: nextSortDir });
  }

  onPerPageChange(event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);
    this.perPageChanged.emit(value);
  }

  pageLabel(): string {
    return `${this.currentPage} / ${this.lastPage}`;
  }
}
