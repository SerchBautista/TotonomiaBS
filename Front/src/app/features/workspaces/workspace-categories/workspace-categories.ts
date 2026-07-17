import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { CdkDragDrop, DragDropModule } from '@angular/cdk/drag-drop';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { Category } from '../../../core/models/category.model';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { CategoriesService } from '../../../core/services/categories';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { EmptyStateComponent } from '../../../shared/empty-state/empty-state';
import { IconPickerComponent } from '../../../shared/icon-picker/icon-picker';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';

type CategoryStateKey = 'unlinked' | 'linked' | 'linked_read_only';

@Component({
  selector: 'app-workspace-categories',
  imports: [
    TranslateModule,
    ReactiveFormsModule,
    DragDropModule,
    PageHeaderComponent,
    SectionPanelComponent,
    StatusBadgeComponent,
    EmptyStateComponent,
    LoadingStateComponent,
    ModalShellComponent,
    IconPickerComponent,
  ],
  templateUrl: './workspace-categories.html',
  styleUrl: './workspace-categories.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspaceCategoriesComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly categoriesService = inject(CategoriesService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly route = inject(ActivatedRoute);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly authState = inject(AuthStateService);
  private readonly fb = inject(FormBuilder);

  readonly loading = signal(false);
  readonly rowLoadingId = signal<string | null>(null);
  readonly categories = signal<Category[]>([]);
  readonly forbidden = signal(false);
  readonly searchUsing = signal('');
  readonly searchAvailable = signal('');
  readonly saving = signal(false);
  readonly showForm = signal(false);
  readonly createForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(80)]],
    icon: ['tag', [Validators.required]],
    color: ['#16324f', [Validators.required]],
  });

  readonly isWorkspaceOwner = computed(() => {
    const workspace = this.workspaceContext.selectedWorkspace();
    const userId = this.authState.userId();
    return !!workspace && !!userId && workspace.owner_id === userId;
  });

  readonly usingHere = computed(() =>
    this.filteredBySearch(
      this.categories().filter((category) => this.getCategoryState(category) === 'linked'),
      this.searchUsing(),
    ),
  );
  readonly available = computed(() =>
    this.filteredBySearch(
      this.categories().filter((category) => this.getCategoryState(category) !== 'linked'),
      this.searchAvailable(),
    ),
  );

  workspaceId = '';

  async ngOnInit(): Promise<void> {
    this.workspaceId =
      this.route.snapshot.paramMap.get('id') ?? this.route.snapshot.parent?.paramMap.get('id') ?? '';

    if (!this.workspaceId) {
      await this.workspaceContext.ensureLoaded();
      this.workspaceId =
        this.workspaceContext.selectedWorkspace()?.id ?? this.workspaceContext.currentWorkspaceId() ?? '';
    }

    if (!this.workspaceId) {
      this.toastService.error(this.translate.instant('workspace_categories.load_error'));
      return;
    }

    this.workspaceContext.setCurrentWorkspaceId(this.workspaceId);
    await this.workspaceContext.ensureLoaded();

    this.categoriesService.categoryCreated$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ workspaceId }) => {
        if (workspaceId === this.workspaceId) {
          this.loadCategories();
        }
      });

    this.loadCategories();
  }

  onDrop(event: CdkDragDrop<Category[]>): void {
    if (event.previousContainer === event.container) {
      return;
    }

    const category = event.item.data as Category;
    const shouldLink = event.container.id === 'using-here';
    this.updateCategoryUsage(category, shouldLink);
  }

  updateCategoryUsage(category: Category, shouldLink: boolean): void {
    if (this.rowLoadingId() || this.forbidden() || !this.isWorkspaceOwner()) {
      return;
    }

    this.rowLoadingId.set(category.id);
    this.categoriesService
      .updateLink(this.workspaceId, category.id, shouldLink)
      .pipe(
        finalize(() => this.rowLoadingId.set(null)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.toastService.success(
            this.translate.instant(
              shouldLink
                ? 'workspace_categories.use_here_success'
                : 'workspace_categories.remove_from_here_success',
            ),
          );
          this.loadCategories();
        },
        error: () => {},
      });
  }

  toggleCreateForm(): void {
    if (!this.isWorkspaceOwner()) {
      return;
    }

    this.showForm.set(!this.showForm());
    if (!this.showForm()) {
      this.createForm.reset({ icon: 'tag', color: '#16324f' });
    }
  }

  submitCreate(): void {
    if (!this.isWorkspaceOwner()) {
      this.toastService.warning(this.translate.instant('workspace_categories.owner_only'));
      return;
    }

    if (this.createForm.invalid) {
      this.createForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.categoriesService
      .createMine({
        name: (this.createForm.value.name ?? '').trim(),
        icon: this.createForm.value.icon ?? 'tag',
        color: this.createForm.value.color ?? '#16324f',
        workspace_ids: [this.workspaceId],
      })
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.createForm.reset({ icon: 'tag', color: '#16324f' });
          this.showForm.set(false);
          this.toastService.success(this.translate.instant('workspace_categories.create_success'));
          this.loadCategories();
        },
        error: () => this.saving.set(false),
      });
  }

  setUsingSearch(value: string): void {
    this.searchUsing.set(value);
  }

  setAvailableSearch(value: string): void {
    this.searchAvailable.set(value);
  }

  stateVariant(category: Category): 'success' | 'warning' | 'brand' {
    return this.getCategoryState(category) === 'linked_read_only' ? 'warning' : 'brand';
  }

  trackByCategory(_: number, category: Category): string {
    return category.id;
  }

  private loadCategories(): void {
    this.loading.set(true);
    this.categoriesService
      .list(this.workspaceId)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.forbidden.set(false);
          this.categories.set(res.data);
        },
        error: () => {},
      });
  }

  private filteredBySearch(categories: Category[], query: string): Category[] {
    const normalized = query.trim().toLowerCase();
    if (!normalized) {
      return categories;
    }

    return categories.filter((category) => category.name.toLowerCase().includes(normalized));
  }

  getCategoryState(category: Category): CategoryStateKey {
    const backendState = (category.state ?? '').toLowerCase();

    if (backendState === 'linked' || backendState === 'ligada') {
      return 'linked';
    }

    if (
      backendState === 'linked_read_only' ||
      backendState === 'read_only_linked' ||
      backendState === 'read_only' ||
      backendState === 'readonly' ||
      backendState === 'solo_consulta_movimientos_ligados'
    ) {
      return 'linked_read_only';
    }

    if (backendState === 'not_linked' || backendState === 'unlinked' || backendState === 'no_ligado') {
      return 'unlinked';
    }

    if (category.is_linked && category.is_valid_for_transactions !== false) {
      return 'linked';
    }

    if (category.is_in_use_in_workspace && !category.is_linked) {
      return 'linked_read_only';
    }

    if (category.is_linked && category.is_valid_for_transactions === false) {
      return 'linked_read_only';
    }

    return 'unlinked';
  }
}
