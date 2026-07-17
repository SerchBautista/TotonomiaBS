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
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import {
  applyBackendFieldErrors,
  clearBackendFieldErrors,
} from '../../../core/errors/apply-backend-field-errors';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { ToastService } from '../../../core/services/toast.service';
import { skipGlobalErrorToastContext } from '../../../core/interceptors/http-request-context';
import { ApiRequestOptions } from '../../../core/tokens/api-service.token';
import { WorkspaceMember, Workspace } from '../../../core/models/workspace.model';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { EmptyStateComponent } from '../../../shared/empty-state/empty-state';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';

@Component({
  selector: 'app-workspace-members',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    RouterLink,
    PageHeaderComponent,
    SectionPanelComponent,
    DataTableComponent,
    TableCellDirective,
    EmptyStateComponent,
    LoadingStateComponent,
  ],
  templateUrl: './workspace-members.html',
  styleUrl: './workspace-members.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspaceMembersComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly membersService = inject(WorkspaceMembersService);
  private readonly workspacesService = inject(WorkspacesService);
  private readonly authState = inject(AuthStateService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);

  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly removingId = signal<string | null>(null);
  readonly updatingPermission = signal<{ memberId: string; field: string } | null>(null);
  readonly members = signal<WorkspaceMember[]>([]);
  readonly workspace = signal<Workspace | null>(null);

  readonly currentUserId = computed(() => this.authState.userId());

  readonly columns = computed<TableColumn<WorkspaceMember>[]>(() => [
    { key: 'name', header: this.translate.instant('members.name') },
    { key: 'email', header: this.translate.instant('members.email') },
    { key: 'role', header: this.translate.instant('members.role'), width: '160px' },
    {
      key: 'can_add_fixed_expenses',
      header: this.translate.instant('members.perm_fixed_expenses'),
      align: 'center',
      width: '160px',
    },
    { key: 'actions', header: '', align: 'right', width: '80px' },
  ]);

  readonly inviteForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
  });

  workspaceId = '';

  private readonly skipGlobalToast: ApiRequestOptions = {
    context: skipGlobalErrorToastContext(),
  };

  ngOnInit(): void {
    this.workspaceId = this.route.snapshot.paramMap.get('id') ?? '';
    if (this.workspaceId) {
      this.loadData();
    }
  }

  private loadData(): void {
    this.loading.set(true);
    this.workspacesService
      .getById(this.workspaceId, this.skipGlobalToast)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          const ws = res.data;
          if (ws.owner_id !== this.authState.userId() || ws.owner_plan !== 'premium') {
            void this.router.navigateByUrl('/user/workspaces');
            return;
          }
          this.workspace.set(ws);
          this.loadMembers();
        },
        error: (error) => {
          this.loading.set(false);
          this.toastService.error(this.formatServiceError('members.load_error', error));
        },
      });
  }

  private loadMembers(): void {
    this.membersService
      .list(this.workspaceId, this.skipGlobalToast)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => this.members.set(res.data),
        error: (error) =>
          this.toastService.error(this.formatServiceError('members.load_error', error)),
      });
  }

  isOwner(member: WorkspaceMember): boolean {
    return this.workspace()?.owner_id === member.id;
  }

  isCurrentUser(member: WorkspaceMember): boolean {
    return this.currentUserId() === member.id;
  }

  invite(): void {
    if (this.inviteForm.invalid || this.saving()) return;

    clearBackendFieldErrors(this.inviteForm);
    this.saving.set(true);
    const { email } = this.inviteForm.getRawValue();

    this.membersService
      .invite(this.workspaceId, { email: email! }, this.skipGlobalToast)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.members.update((list) => [...list, res.data]);
          this.inviteForm.reset({ email: '' });
          this.toastService.success(this.translate.instant('members.invite_success'));
        },
        error: (err) => {
          const normalizedError = ensureNormalizedBackendError(err);

          if (normalizedError.code === BACKEND_ERROR_CODES.validationError) {
            const hasFieldErrors = applyBackendFieldErrors(this.inviteForm, normalizedError);
            if (hasFieldErrors) {
              return;
            }
          }

          if (
            normalizedError.code === BACKEND_ERROR_CODES.workspaceMemberUserNotFound ||
            normalizedError.status === 404
          ) {
            this.toastService.error(this.translate.instant('members.user_not_found'));
          } else if (
            normalizedError.code === BACKEND_ERROR_CODES.workspaceMemberAlreadyMember ||
            normalizedError.status === 422
          ) {
            this.toastService.error(this.translate.instant('members.already_member'));
          } else {
            this.toastService.error(this.formatServiceError('members.load_error', normalizedError));
          }
        },
      });
  }

  removeMember(member: WorkspaceMember): void {
    if (this.removingId()) return;

    this.removingId.set(member.id);

    this.membersService
      .remove(this.workspaceId, member.id, this.skipGlobalToast)
      .pipe(
        finalize(() => this.removingId.set(null)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.members.update((list) => list.filter((m) => m.id !== member.id));
          this.toastService.success(this.translate.instant('members.removed'));
        },
        error: (error) => {
          const normalizedError = ensureNormalizedBackendError(error);

          if (normalizedError.code === BACKEND_ERROR_CODES.workspaceMemberCannotRemoveOwner) {
            this.toastService.error(this.translate.instant('members.cannot_remove_owner'));
            return;
          }

          this.toastService.error(this.formatServiceError('members.remove_error', normalizedError));
        },
      });
  }

  updatePermission(member: WorkspaceMember, field: 'can_add_fixed_expenses', value: boolean): void {
    if (this.updatingPermission()) return;

    this.members.update((list) =>
      list.map((m) => (m.id === member.id ? { ...m, [field]: value } : m)),
    );
    this.updatingPermission.set({ memberId: member.id, field });

    this.membersService
      .updateMember(this.workspaceId, member.id, { [field]: value }, this.skipGlobalToast)
      .pipe(
        finalize(() => this.updatingPermission.set(null)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.members.update((list) => list.map((m) => (m.id === res.data.id ? res.data : m)));
        },
        error: () => {
          this.members.update((list) =>
            list.map((m) => (m.id === member.id ? { ...m, [field]: !value } : m)),
          );
          this.toastService.error(this.translate.instant('members.permission_update_error'));
        },
      });
  }

  private formatServiceError(i18nKey: string, error: unknown): string {
    const baseMessage = this.translate.instant(i18nKey);
    const normalizedError = ensureNormalizedBackendError(error, { fallbackMessage: baseMessage });

    return normalizedError.message !== baseMessage
      ? `${baseMessage}: ${normalizedError.message}`
      : baseMessage;
  }
}
