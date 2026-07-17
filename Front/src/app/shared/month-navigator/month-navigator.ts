import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

@Component({
  selector: 'app-month-navigator',
  templateUrl: './month-navigator.html',
  styleUrl: './month-navigator.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class MonthNavigatorComponent {
  readonly label = input.required<string>();
  readonly nextDisabled = input<boolean>(false);
  readonly showToday = input<boolean>(true);
  readonly previousLabel = input<string>('Mes anterior');
  readonly nextLabel = input<string>('Mes siguiente');
  readonly todayLabel = input<string>('Hoy');

  readonly previous = output<void>();
  readonly next = output<void>();
  readonly today = output<void>();

  onPrevious(): void {
    this.previous.emit();
  }

  onNext(): void {
    this.next.emit();
  }

  onToday(): void {
    this.today.emit();
  }
}
