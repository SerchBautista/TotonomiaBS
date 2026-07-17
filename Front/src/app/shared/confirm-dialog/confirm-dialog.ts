import { Component, EventEmitter, Input, Output } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';

let nextConfirmDialogId = 0;

@Component({
  selector: 'app-confirm-dialog',
  imports: [TranslateModule],
  templateUrl: './confirm-dialog.html',
  styleUrl: './confirm-dialog.scss',
})
export class ConfirmDialogComponent {
  @Input() open = false;
  @Input() title = '';
  @Input() message = '';
  @Input() confirmLabel = '';
  @Input() cancelLabel = '';

  @Output() confirmed = new EventEmitter<void>();
  @Output() canceled = new EventEmitter<void>();

  readonly titleId = `confirm-dialog-title-${++nextConfirmDialogId}`;

  onBackdropClick(event: MouseEvent): void {
    if (event.target === event.currentTarget) {
      this.canceled.emit();
    }
  }

  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      this.canceled.emit();
    }
  }
}
