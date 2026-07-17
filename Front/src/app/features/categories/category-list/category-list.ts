import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { CategoriesService } from '../../../core/services/categories';
import { Category } from '../../../core/models/category.model';
import { Workspace } from '../../../core/models/workspace.model';
import { ConfirmDialogComponent } from '../../../shared/confirm-dialog/confirm-dialog';
import { IconPickerComponent } from '../../../shared/icon-picker/icon-picker';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { CategoryBadgeComponent } from '../../../shared/category-badge/category-badge';
import { ActionButtonsComponent } from '../../../shared/action-buttons/action-buttons';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { WorkspaceSelectorListComponent } from '../../../shared/workspace-selector-list/workspace-selector-list';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { skipGlobalErrorToastContext } from '../../../core/interceptors/http-request-context';
import { ApiRequestOptions } from '../../../core/tokens/api-service.token';

@Component({
  selector: 'app-category-list',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    ConfirmDialogComponent,
    IconPickerComponent,
    PageHeaderComponent,
    DataTableComponent,
    TableCellDirective,
    CategoryBadgeComponent,
    ActionButtonsComponent,
    StatusBadgeComponent,
    ModalShellComponent,
    WorkspaceSelectorListComponent,
  ],
  templateUrl: './category-list.html',
  styleUrl: './category-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoryListComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly categoriesService = inject(CategoriesService);
  private readonly translate = inject(TranslateService);
  private readonly fb = inject(FormBuilder);
  private readonly toastService = inject(ToastService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly authState = inject(AuthStateService);

  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly categories = signal<Category[]>([]);
  readonly showForm = signal(false);
  readonly ownerWorkspaces = signal<Workspace[]>([]);
  readonly selectedWorkspaceIds = signal<string[]>([]);
  readonly editSelectedWorkspaceIds = signal<string[]>([]);
  readonly managingCategory = signal<Category | null>(null);
  readonly manageSaving = signal(false);

  /** ID of the category currently being edited, or null */
  readonly editingId = signal<string | null>(null);
  readonly editSaving = signal(false);

  readonly columns = computed<TableColumn<Category>[]>(() => [
    { key: 'name', header: this.translate.instant('categories.name') },
    { key: 'badge', header: this.translate.instant('categories.icon'), width: '180px' },
    { key: 'color', header: this.translate.instant('categories.color'), width: '100px' },
    { key: 'workspaces', header: this.translate.instant('categories.workspaces_label') },
    {
      key: 'actions',
      header: this.translate.instant('expenses.actions'),
      align: 'right',
      width: '220px',
    },
  ]);

  confirmOpen = false;
  itemToDelete: string | null = null;

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(80)]],
    icon: ['tag', [Validators.required]],
    color: ['#16324f', [Validators.required]],
  });

  readonly editForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(80)]],
    icon: ['', [Validators.required]],
    color: ['#16324f', [Validators.required]],
  });

  ngOnInit(): void {
    void this.loadOwnerWorkspaces();
    this.loadCategories();
  }

  async loadOwnerWorkspaces(): Promise<void> {
    await this.workspaceContext.ensureLoaded();
    const userId = this.authState.userId();
    const workspaces = this.workspaceContext
      .workspaces()
      .filter((workspace) => workspace.owner_id === userId);

    this.ownerWorkspaces.set(workspaces);
    const autoLinked = workspaces.length === 1 ? [workspaces[0].id] : [];

    if (!this.selectedWorkspaceIds().length) {
      this.selectedWorkspaceIds.set(autoLinked);
    }
  }

  loadCategories(): void {
    this.loading.set(true);
    this.categoriesService
      .listMine()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.categories.set(r.data);
          this.loading.set(false);
        },
        error: () => {
          this.loading.set(false);
        },
      });
  }

  toggleForm(): void {
    this.showForm.set(!this.showForm());
    if (!this.showForm()) {
      this.form.reset({ icon: 'tag', color: '#16324f' });
      this.selectedWorkspaceIds.set(this.defaultWorkspaceSelection());
    }
  }

  submitCreate(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);

    this.categoriesService
      .createMine({
        name: (this.form.value.name ?? '').trim(),
        icon: this.form.value.icon ?? 'tag',
        color: this.form.value.color ?? '#16324f',
        workspace_ids: this.selectedWorkspaceIds(),
      })
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.form.reset({ icon: 'tag', color: '#16324f' });
          this.selectedWorkspaceIds.set(this.defaultWorkspaceSelection());
          this.showForm.set(false);
          this.toastService.success(this.translate.instant('categories.created_ok'));
          this.loadCategories();
        },
        error: () => {},
      });
  }

  startEdit(cat: Category): void {
    this.editingId.set(cat.id);
    this.editForm.setValue({ name: cat.name, icon: cat.icon, color: cat.color });
    this.editSelectedWorkspaceIds.set((cat.linked_workspaces ?? []).map((workspace) => workspace.id));
  }

  cancelEdit(): void {
    this.editingId.set(null);
    this.editForm.reset();
    this.editSelectedWorkspaceIds.set([]);
  }

  submitEdit(categoryId: string): void {
    if (this.editForm.invalid) {
      this.editForm.markAllAsTouched();
      return;
    }

    this.editSaving.set(true);

    this.categoriesService
      .updateMine(categoryId, {
        name: (this.editForm.value.name ?? '').trim(),
        icon: this.editForm.value.icon ?? 'tag',
        color: this.editForm.value.color ?? '#16324f',
        workspace_ids: this.editSelectedWorkspaceIds(),
      })
      .pipe(
        finalize(() => this.editSaving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (r) => {
          this.categories.update((cats) => cats.map((c) => (c.id === categoryId ? r.data : c)));
          this.editingId.set(null);
          this.editSelectedWorkspaceIds.set([]);
          this.toastService.success(this.translate.instant('categories.updated_ok'));
        },
        error: () => {},
      });
  }

  setCategoryDefault(id: string): void {
    this.categoriesService
      .setAsDefaultMine(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => {
          this.categories.update((list) =>
            list.map((c) => ({ ...c, is_default: c.id === r.data.id ? r.data.is_default : false })),
          );
          this.toastService.success(this.translate.instant('categories.set_default_ok'));
        },
        error: () => {},
      });
  }

  requestDelete(id: string): void {
    this.itemToDelete = id;
    this.confirmOpen = true;
  }

  cancelDelete(): void {
    this.confirmOpen = false;
    this.itemToDelete = null;
  }

  confirmDelete(): void {
    if (!this.itemToDelete) return;

    this.categoriesService
      .deleteMine(this.itemToDelete, this.skipGlobalToast)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.confirmOpen = false;
          this.itemToDelete = null;
          this.toastService.success(this.translate.instant('categories.deleted_ok'));
          this.loadCategories();
        },
        error: (error) => {
          this.confirmOpen = false;
          this.itemToDelete = null;
          const normalized = ensureNormalizedBackendError(error);
          this.toastService.error(normalized.message);
        },
      });
  }

  linkedWorkspaceNames(category: Category): string {
    return (category.linked_workspaces ?? []).map((workspace) => workspace.name).join(', ');
  }

  updateCreateWorkspaceSelection(workspaceIds: string[]): void {
    this.selectedWorkspaceIds.set(workspaceIds);
  }

  updateEditWorkspaceSelection(workspaceIds: string[]): void {
    this.editSelectedWorkspaceIds.set(workspaceIds);
  }

  openWorkspaceManager(category: Category): void {
    this.managingCategory.set(category);
    this.editSelectedWorkspaceIds.set((category.linked_workspaces ?? []).map((workspace) => workspace.id));
  }

  closeWorkspaceManager(): void {
    this.managingCategory.set(null);
    this.editSelectedWorkspaceIds.set([]);
  }

  saveWorkspaceManager(): void {
    const category = this.managingCategory();
    if (!category) {
      return;
    }

    this.manageSaving.set(true);
    this.categoriesService
      .updateWorkspaces(category.id, this.editSelectedWorkspaceIds())
      .pipe(
        finalize(() => this.manageSaving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.closeWorkspaceManager();
          this.toastService.success(this.translate.instant('categories.workspaces_updated_ok'));
          this.loadCategories();
        },
        error: () => this.manageSaving.set(false),
      });
  }

  private defaultWorkspaceSelection(): string[] {
    return this.ownerWorkspaces().length === 1 ? [this.ownerWorkspaces()[0].id] : [];
  }

}
