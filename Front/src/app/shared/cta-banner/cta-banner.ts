import { ChangeDetectionStrategy, Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-cta-banner',
  imports: [RouterLink],
  templateUrl: './cta-banner.html',
  styleUrl: './cta-banner.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CtaBannerComponent {
  @Input({ required: true }) title!: string;
  @Input({ required: true }) subtitle!: string;
  @Input({ required: true }) primaryCta!: string;
  @Input() primaryCtaLink = '/register';
  @Input({ required: true }) secondaryCta!: string;
  @Input() secondaryCtaLink = '/pricing';
}
