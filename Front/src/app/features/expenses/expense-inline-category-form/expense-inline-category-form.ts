import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { CategoriesService } from '../../../core/services/categories';
import { Category } from '../../../core/models/category.model';
import { Workspace } from '../../../core/models/workspace.model';
import { ToastService } from '../../../core/services/toast.service';
import { IconPickerComponent } from '../../../shared/icon-picker/icon-picker';
import { WorkspaceSelectorListComponent } from '../../../shared/workspace-selector-list/workspace-selector-list';
import { skipGlobalErrorToastContext } from '../../../core/interceptors/http-request-context';
import { ApiRequestOptions } from '../../../core/tokens/api-service.token';
import {
  getSafeCategoryWorkspaceIds,
  handleInlineFormError,
} from '../expense-form.utils';

@Component({
  selector: 'app-expense-inline-category-form',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    IconPickerComponent,
    WorkspaceSelectorListComponent,
  ],
  templateUrl: './expense-inline-category-form.html',
  styleUrl: '../expense-inline-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ExpenseInlineCategoryFormComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly categoriesService = inject(CategoriesService);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly fb = inject(FormBuilder);

  readonly workspaceId = input.required<string>();
  readonly ownerWorkspaces = input.required<Workspace[]>();
  readonly canSelectAdditionalWorkspaces = input.required<boolean>();
  readonly initialWorkspaceIds = input.required<string[]>();

  readonly created = output<Category>();
  readonly canceled = output<void>();

  readonly saving = signal(false);
  readonly workspaceIds = signal<string[]>([]);

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(80)]],
    icon: ['tag', [Validators.required]],
    color: ['#16324f', [Validators.required]],
  });

  constructor() {
    effect(() => {
      this.workspaceIds.set(
        getSafeCategoryWorkspaceIds(this.initialWorkspaceIds(), this.workspaceId()),
      );
    });
  }

  updateWorkspaceSelection(workspaceIds: string[]): void {
    this.workspaceIds.set(
      getSafeCategoryWorkspaceIds(workspaceIds, this.workspaceId()),
    );
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);

    this.categoriesService
      .createMine(
        {
          name: (this.form.value.name ?? '').trim(),
          icon: this.form.value.icon ?? 'tag',
          color: this.form.value.color ?? '#16324f',
          workspace_ids: this.workspaceIds(),
        },
        this.skipGlobalToast,
      )
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.toastService.success(this.translate.instant('categories.created_ok'));
          this.form.reset({ icon: 'tag', color: '#16324f' });
          this.created.emit(r.data);
        },
        error: (err) =>
          handleInlineFormError(err, this.form, 'categories.save_error', {
            translate: this.translate,
            toastService: this.toastService,
          }),
      });
  }

  cancel(): void {
    this.form.reset({ icon: 'tag', color: '#16324f' });
    this.canceled.emit();
  }
}
