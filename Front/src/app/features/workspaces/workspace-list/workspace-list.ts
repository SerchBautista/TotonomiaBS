import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { Router } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { WorkspacesService } from '../../../core/services/workspaces';
import { Workspace } from '../../../core/models/workspace.model';
import { ConfirmDialogComponent } from '../../../shared/confirm-dialog/confirm-dialog';
import { UpgradePromptComponent } from '../../../shared/upgrade-prompt/upgrade-prompt';
import { ToastService } from '../../../core/services/toast.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { PaginationBarComponent } from '../../../shared/pagination-bar/pagination-bar';
import { EmptyStateComponent } from '../../../shared/empty-state/empty-state';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';

@Component({
  selector: 'app-workspace-list',
  imports: [
    TranslateModule,
    ConfirmDialogComponent,
    UpgradePromptComponent,
    PageHeaderComponent,
    PaginationBarComponent,
    EmptyStateComponent,
    LoadingStateComponent,
    StatusBadgeComponent,
  ],
  templateUrl: './workspace-list.html',
  styleUrl: './workspace-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspaceListComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly workspacesService = inject(WorkspacesService);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly authState = inject(AuthStateService);

  readonly defaultWorkspaceId = computed(() => this.authState.defaultWorkspaceId());
  readonly isInSettings = computed(() => this.router.url.startsWith('/user/settings'));
  readonly loading = signal(false);
  readonly settingDefault = signal(false);
  readonly workspaces = signal<Workspace[]>([]);
  readonly showUpgradePrompt = signal(false);

  readonly upgradeBenefits = [
    'Crea workspaces ilimitados',
    'Invita miembros y colabora en tiempo real',
    'Acceso completo a todas las funciones premium',
  ];

  currentPage = 1;
  lastPage = 1;
  total = 0;

  confirmOpen = false;
  itemToDelete: string | null = null;

  ngOnInit(): void {
    this.loadWorkspaces();
  }

  createRoute(): string {
    return this.router.url.startsWith('/user/settings')
      ? '/user/settings/workspaces/create'
      : '/user/workspaces/create';
  }

  requestCreate(): void {
    const userId = this.authState.userId();
    const plan = this.authState.plan();

    if (plan === 'free') {
      const ownedCount = this.workspaces().filter((ws) => ws.owner_id === userId).length;
      if (ownedCount >= 1) {
        this.showUpgradePrompt.set(true);
        return;
      }
    }

    void this.router.navigateByUrl(this.createRoute());
  }

  onUpgradeClicked(): void {
    this.showUpgradePrompt.set(false);
    void this.router.navigateByUrl('/pricing');
  }

  dismissUpgradePrompt(): void {
    this.showUpgradePrompt.set(false);
  }

  loadWorkspaces(): void {
    this.loading.set(true);

    this.workspacesService
      .list({ page: this.currentPage })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.workspaces.set(response.data);
          this.currentPage = response.meta.current_page;
          this.lastPage = response.meta.last_page;
          this.total = response.meta.total;
          this.loading.set(false);
        },
        error: () => {
          this.loading.set(false);
        },
      });
  }

  setAsDefault(id: string): void {
    if (this.settingDefault()) return;
    this.settingDefault.set(true);
    this.workspacesService
      .setDefault(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.settingDefault.set(false);
          this.authState.setDefaultWorkspaceId(response.data.default_workspace_id ?? null);
          this.toastService.success(this.translate.instant('workspaces.default_set_success'));
        },
        error: () => {
          this.settingDefault.set(false);
          this.toastService.error(this.translate.instant('workspaces.default_set_error'));
        },
      });
  }

  isOwner(ws: Workspace): boolean {
    return ws.owner_id === this.authState.userId();
  }

  openSettingsDetail(id: string): void {
    void this.router.navigate(['/user/workspaces', id, 'expenses'], {
      queryParams: { from: 'settings' },
    });
  }

  openDetail(id: string): void {
    void this.router.navigateByUrl(`/user/workspaces/${id}`);
  }

  openEdit(id: string): void {
    const base = this.router.url.startsWith('/user/settings')
      ? '/user/settings/workspaces'
      : '/user/workspaces';
    void this.router.navigateByUrl(`${base}/${id}/edit`);
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

    this.workspacesService
      .delete(this.itemToDelete)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.confirmOpen = false;
          this.itemToDelete = null;
          this.toastService.success(this.translate.instant('workspaces.deleted_ok'));
          this.currentPage = 1;
          this.loadWorkspaces();
        },
        error: () => {
          this.confirmOpen = false;
          this.itemToDelete = null;
        },
      });
  }

  prevPage(): void {
    if (this.currentPage > 1) {
      this.currentPage--;
      this.loadWorkspaces();
    }
  }

  nextPage(): void {
    if (this.currentPage < this.lastPage) {
      this.currentPage++;
      this.loadWorkspaces();
    }
  }
}
