import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import {
  ActivatedRoute,
  Router,
  RouterLink,
  RouterLinkActive,
  RouterOutlet,
} from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { WorkspacesService } from '../../../core/services/workspaces';
import { Workspace } from '../../../core/models/workspace.model';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';
import { WorkspaceEditModalComponent } from '../workspace-edit-modal/workspace-edit-modal';

@Component({
  selector: 'app-workspace-detail',
  imports: [
    TranslateModule,
    RouterLink,
    RouterLinkActive,
    RouterOutlet,
    PageHeaderComponent,
    SectionPanelComponent,
    StatusBadgeComponent,
    LoadingStateComponent,
    WorkspaceEditModalComponent,
  ],
  templateUrl: './workspace-detail.html',
  styleUrl: './workspace-detail.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspaceDetailComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly workspacesService = inject(WorkspacesService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly toastService = inject(ToastService);
  private readonly authState = inject(AuthStateService);

  readonly loading = signal(false);
  readonly workspace = signal<Workspace | null>(null);
  readonly isOwner = computed(() => this.workspace()?.owner_id === this.authState.userId());
  readonly canManageMembers = computed(
    () => this.isOwner() && this.workspace()?.owner_plan === 'premium',
  );
  readonly backLink = signal('/user/workspaces');
  readonly fromSettings = signal(false);
  readonly editModalOpen = signal(false);
  readonly backLabelKey = computed(() =>
    this.fromSettings() ? 'workspaces.back' : 'workspaces.back_to_list',
  );

  workspaceId = '';

  ngOnInit(): void {
    this.workspaceId = this.route.snapshot.paramMap.get('id') ?? '';
    if (this.route.snapshot.queryParamMap.get('from') === 'settings') {
      this.backLink.set('/user/settings/workspaces');
      this.fromSettings.set(true);
    }
    if (!this.workspaceId) {
      void this.router.navigateByUrl('/user/settings/workspaces');
      return;
    }
    this.workspaceContext.setCurrentWorkspaceId(this.workspaceId);
    this.loadWorkspace();
  }

  openEditModal(): void {
    this.editModalOpen.set(true);
  }

  closeEditModal(): void {
    this.editModalOpen.set(false);
  }

  onWorkspaceSaved(): void {
    this.editModalOpen.set(false);
    this.loadWorkspace();
  }

  loadWorkspace(): void {
    this.loading.set(true);
    this.workspacesService
      .getById(this.workspaceId)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => this.workspace.set(response.data),
        error: () => this.loading.set(false),
      });
  }
}
