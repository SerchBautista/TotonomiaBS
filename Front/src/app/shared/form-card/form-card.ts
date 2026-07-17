import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  selector: 'app-form-card',
  templateUrl: './form-card.html',
  styleUrl: './form-card.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class FormCardComponent {
  readonly title = input<string>();
}
