import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

@Component({
  selector: 'app-empty-state',
  templateUrl: './empty-state.html',
  styleUrl: './empty-state.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class EmptyStateComponent {
  readonly icon = input<string>('fa-inbox');
  readonly title = input.required<string>();
  readonly message = input<string>();
  readonly actionLabel = input<string>();

  readonly action = output<void>();

  onAction(): void {
    this.action.emit();
  }
}
