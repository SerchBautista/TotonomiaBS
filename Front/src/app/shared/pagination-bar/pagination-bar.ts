import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

@Component({
  selector: 'app-pagination-bar',
  templateUrl: './pagination-bar.html',
  styleUrl: './pagination-bar.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class PaginationBarComponent {
  readonly currentPage = input.required<number>();
  readonly lastPage = input.required<number>();

  readonly prev = output<void>();
  readonly next = output<void>();

  onPrev(): void {
    this.prev.emit();
  }

  onNext(): void {
    this.next.emit();
  }
}
