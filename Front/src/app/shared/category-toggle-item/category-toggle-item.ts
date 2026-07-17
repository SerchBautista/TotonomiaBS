import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { Category } from '../../core/models/category.model';

@Component({
  selector: 'app-category-toggle-item',
  imports: [TranslateModule],
  templateUrl: './category-toggle-item.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class CategoryToggleItemComponent {
  readonly category = input.required<Category>();
  readonly enabled = input.required<boolean>();
  readonly toggling = input<boolean>(false);
  readonly toggled = output<boolean>();

  toggle(): void {
    this.toggled.emit(!this.enabled());
  }
}
