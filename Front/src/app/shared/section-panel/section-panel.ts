import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  selector: 'app-section-panel',
  templateUrl: './section-panel.html',
  styleUrl: './section-panel.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class SectionPanelComponent {
  readonly title = input<string>();
  readonly withHover = input<boolean>(false);
  readonly noPadding = input<boolean>(false);
}
