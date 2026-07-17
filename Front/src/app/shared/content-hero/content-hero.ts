import { ChangeDetectionStrategy, Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-content-hero',
  imports: [RouterLink],
  templateUrl: './content-hero.html',
  styleUrl: './content-hero.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ContentHeroComponent {
  @Input() eyebrow = '';
  @Input({ required: true }) title!: string;
  @Input({ required: true }) lead!: string;
  @Input() primaryCta?: string;
  @Input() primaryCtaLink?: string;
  @Input() secondaryCta?: string;
  @Input() secondaryCtaLink?: string;
}
