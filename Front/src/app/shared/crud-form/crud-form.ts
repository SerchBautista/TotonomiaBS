import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormGroup, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { CrudFormMode } from '../../core/crud/crud-form-facade';

@Component({
  selector: 'app-crud-form',
  imports: [ReactiveFormsModule, RouterLink, TranslateModule],
  templateUrl: './crud-form.html',
  styleUrl: './crud-form.scss'
})
export class CrudFormComponent {
  @Input() mode: CrudFormMode = 'create';
  @Input() entityKey = '';
  @Input() titleKey = '';
  @Input() backLink = '/';
  /**
   * i18n key for the "back to list" link. Required: the consumer feature
   * must provide its own label (e.g. `administrators.back_to_list`).
   */
  @Input({ required: true }) backLabelKey!: string;
  @Input({ required: true }) formGroup!: FormGroup;
  @Input() submitting = false;
  @Input() showSubmit = true;
  @Input() disableSubmit = false;
  /**
   * i18n key for the submit button. Required: the consumer feature
   * must provide its own label (e.g. `administrators.save`).
   */
  @Input({ required: true }) submitLabelKey!: string;
  @Input() loadingLabelKey = 'auth.loading';
  @Output() submitted = new EventEmitter<void>();

  resolvedTitleKey(): string {
    if (this.titleKey.trim()) {
      return this.titleKey;
    }

    const entity = this.entityKey.trim();
    if (!entity) {
      return '';
    }

    if (this.mode === 'create') {
      return `${entity}.form_create_title`;
    }

    if (this.mode === 'edit') {
      return `${entity}.form_edit_title`;
    }

    return `${entity}.form_view_title`;
  }
}
