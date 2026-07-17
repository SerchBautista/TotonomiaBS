import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  selector: 'app-summary-hero',
  templateUrl: './summary-hero.html',
  styleUrl: './summary-hero.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class SummaryHeroComponent {
  readonly label = input.required<string>();
  readonly value = input.required<string>();
  readonly variant = input<'navy' | 'surface'>('navy');
}
