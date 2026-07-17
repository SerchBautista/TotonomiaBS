import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { FormGroup, ReactiveFormsModule } from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-budget-form-fields',
  imports: [ReactiveFormsModule, TranslateModule],
  templateUrl: './budget-form-fields.html',
  styleUrl: '../budget-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BudgetFormFieldsComponent {
  readonly form = input.required<FormGroup>();
  readonly idPrefix = input.required<string>();
}
