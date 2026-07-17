import { ChangeDetectionStrategy, Component } from '@angular/core';

@Component({
  selector: 'app-page-filters',
  templateUrl: './page-filters.html',
  styleUrl: './page-filters.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class PageFiltersComponent {}
