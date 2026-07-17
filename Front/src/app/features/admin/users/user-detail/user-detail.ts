import { ChangeDetectionStrategy, Component, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { finalize } from 'rxjs';
import { AdminUsersService } from '../../../../core/services/admin-users.service';
import { AuthStateService } from '../../../../core/services/auth-state.service';
import { AdminUserDetail } from '../../../../core/models/admin-user.model';
import { UserPlan } from '../../../../core/models/user.model';
import { ensureNormalizedBackendError } from '../../../../core/errors/backend-error.mapper';
import { skipGlobalErrorToastContext } from '../../../../core/interceptors/http-request-context';
import { ToastService } from '../../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../../shared/section-panel/section-panel';
import { StatusBadgeComponent } from '../../../../shared/status-badge/status-badge';
import { LoadingStateComponent } from '../../../../shared/loading-state/loading-state';
import { ModalShellComponent } from '../../../../shared/modal-shell/modal-shell';

@Component({
  selector: 'app-user-detail',
  imports: [
    DatePipe,
    TranslateModule,
    RouterLink,
    PageHeaderComponent,
    SectionPanelComponent,
    StatusBadgeComponent,
    LoadingStateComponent,
    ModalShellComponent,
  ],
  templateUrl: './user-detail.html',
  styleUrl: './user-detail.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserDetailComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly usersService = inject(AdminUsersService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  readonly authState = inject(AuthStateService);

  readonly loading = signal(false);
  readonly user = signal<AdminUserDetail | null>(null);
  readonly assignPlanModalOpen = signal(false);
  readonly assigningPlan = signal(false);
  readonly selectedPlan = signal<UserPlan>('free');

  readonly availablePlans: UserPlan[] = ['free', 'premium'];

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.loadUser(id);
    }
  }

  openAssignPlanModal(): void {
    const currentPlan = this.user()?.plan as UserPlan ?? 'free';
    this.selectedPlan.set(currentPlan);
    this.assignPlanModalOpen.set(true);
  }

  closeAssignPlanModal(): void {
    this.assignPlanModalOpen.set(false);
  }

  selectPlan(plan: UserPlan): void {
    this.selectedPlan.set(plan);
  }

  confirmAssignPlan(): void {
    const currentUser = this.user();
    if (!currentUser) return;

    const plan = this.selectedPlan();
    this.assigningPlan.set(true);

    this.usersService
      .assignPlan(currentUser.id, plan, { context: skipGlobalErrorToastContext() })
      .pipe(
        finalize(() => this.assigningPlan.set(false)),
      )
      .subscribe({
        next: () => {
          this.toastService.success(this.translate.instant('admin.users.plan_assigned'));
          this.assignPlanModalOpen.set(false);
          this.loadUser(currentUser.id);
        },
        error: (error) => {
          const normalizedError = ensureNormalizedBackendError(error);

          if (normalizedError.status === 403) {
            this.toastService.error(this.translate.instant('admin.users.assign_plan_forbidden'));
          } else if (normalizedError.status === 404) {
            this.toastService.error(this.translate.instant('admin.users.not_found'));
          } else if (normalizedError.status === 422) {
            this.toastService.error(this.translate.instant('admin.users.assign_plan_invalid'));
          } else {
            this.toastService.error(this.translate.instant('admin.users.assign_plan_error'));
          }
        },
      });
  }

  private loadUser(id: string): void {
    this.loading.set(true);

    this.usersService.get(id, { context: skipGlobalErrorToastContext() }).subscribe({
      next: (response) => {
        this.user.set(response.data.item);
        this.loading.set(false);
      },
      error: (error) => {
        this.loading.set(false);
        const normalizedError = ensureNormalizedBackendError(error);

        if (normalizedError.status === 404) {
          this.toastService.error(this.translate.instant('admin.users.not_found'));
          void this.router.navigateByUrl('/admin/users');
        }
      },
    });
  }
}
