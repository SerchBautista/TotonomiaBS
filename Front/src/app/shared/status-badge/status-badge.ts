import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  selector: 'app-status-badge',
  templateUrl: './status-badge.html',
  styleUrl: './status-badge.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class StatusBadgeComponent {
  readonly variant = input.required<'brand' | 'success' | 'warning' | 'danger'>();
  readonly label = input.required<string>();
}
