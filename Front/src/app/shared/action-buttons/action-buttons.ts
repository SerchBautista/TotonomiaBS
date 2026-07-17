import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

@Component({
  selector: 'app-action-buttons',
  templateUrl: './action-buttons.html',
  styleUrl: './action-buttons.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class ActionButtonsComponent {
  readonly showView = input<boolean>(false);
  readonly showEdit = input<boolean>(true);
  readonly showDelete = input<boolean>(true);
  readonly viewAriaLabel = input<string>('Ver');
  readonly editAriaLabel = input<string>('Editar');
  readonly deleteAriaLabel = input<string>('Eliminar');

  readonly view = output<void>();
  readonly edit = output<void>();
  readonly delete = output<void>();

  onView(): void {
    this.view.emit();
  }

  onEdit(): void {
    this.edit.emit();
  }

  onDelete(): void {
    this.delete.emit();
  }
}
