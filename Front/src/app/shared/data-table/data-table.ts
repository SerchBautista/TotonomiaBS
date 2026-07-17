import { NgTemplateOutlet } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  contentChildren,
  input,
  output,
  TemplateRef,
} from '@angular/core';
import { TableCellDirective } from './table-cell.directive';

export interface TableColumn<T = unknown> {
  key: string;
  header: string;
  width?: string;
  align?: 'left' | 'right' | 'center';
  cellTemplate?: TemplateRef<{ $implicit: T }>;
}

@Component({
  selector: 'app-data-table',
  templateUrl: './data-table.html',
  styleUrl: './data-table.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
  imports: [NgTemplateOutlet],
})
export class DataTableComponent<T = unknown> {
  readonly columns = input.required<TableColumn<T>[]>();
  readonly rows = input.required<T[]>();
  readonly loading = input<boolean>(false);
  readonly emptyMessage = input<string>('');
  readonly emptyActionLabel = input<string>();
  readonly ariaLabel = input<string>();

  readonly emptyAction = output<void>();

  readonly cellTemplates = contentChildren(TableCellDirective, { descendants: true });

  cellTemplateFor(column: TableColumn<T>): TemplateRef<{ $implicit: T }> | undefined {
    if (column.cellTemplate) {
      return column.cellTemplate;
    }

    return this.cellTemplates().find((directive) => directive.appTableCell() === column.key)
      ?.template;
  }

  onEmptyAction(): void {
    this.emptyAction.emit();
  }

  cellValue(row: T, column: TableColumn<T>): unknown {
    return (row as Record<string, unknown>)[column.key];
  }
}
