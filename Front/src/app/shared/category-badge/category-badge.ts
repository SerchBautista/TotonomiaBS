import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { contrastColor } from '../utils/contrast-color';

export interface CategoryBadgeData {
  name: string;
  color: string;
  icon?: string;
}

@Component({
  selector: 'app-category-badge',
  templateUrl: './category-badge.html',
  styleUrl: './category-badge.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class CategoryBadgeComponent {
  readonly category = input.required<CategoryBadgeData>();
  readonly showIcon = input<boolean>(true);

  readonly textColor = computed(() => contrastColor(this.category().color));
}
