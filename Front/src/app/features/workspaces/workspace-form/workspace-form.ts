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
import { AuthStateService } from '../../../core/services/auth-state.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { WorkspaceType } from '../../../core/models/workspace.model';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { FormCardComponent } from '../../../shared/form-card/form-card';

@Component({
  selector: 'app-workspace-form',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    RouterLink,
    PageHeaderComponent,
    FormCardComponent,
  ],
  templateUrl: './workspace-form.html',
  styleUrl: './workspace-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspaceFormComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly workspacesService = inject(WorkspacesService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly authState = inject(AuthStateService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly translate = inject(TranslateService);
  private readonly fb = inject(FormBuilder);
  private readonly toastService = inject(ToastService);

  readonly loading = signal(false);
  readonly settingDefault = signal(false);

  mode: 'create' | 'edit' = 'create';
  workspaceId: string | null = null;

  readonly isInSettings = computed(() => this.router.url.startsWith('/user/settings'));
  readonly backLabelKey = computed(() =>
    this.isInSettings() ? 'workspaces.back' : 'workspaces.back_to_list',
  );
  readonly isDefault = computed(
    () => !!this.workspaceId && this.authState.defaultWorkspaceId() === this.workspaceId,
  );

  readonly workspaceTypes: WorkspaceType[] = ['personal', 'familiar', 'empresa'];
  readonly currencies = ['USD', 'EUR', 'ARS', 'MXN', 'CLP', 'COP', 'BRL'];

  readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(120)]],
    type: ['personal' as WorkspaceType, [Validators.required]],
    currency_code: ['USD', [Validators.required]],
  });

  ngOnInit(): void {
    const routeMode = this.route.snapshot.data['mode'] as 'create' | 'edit' | undefined;
    this.mode = routeMode ?? 'create';
    this.workspaceId = this.route.snapshot.paramMap.get('id');

    if (this.mode === 'edit' && this.workspaceId) {
      this.loadWorkspace(this.workspaceId);
    }
  }

  backRoute(): string {
    return this.router.url.startsWith('/user/settings')
      ? '/user/settings/workspaces'
      : '/user/workspaces';
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);

    const payload = {
      name: (this.form.value.name ?? '').trim(),
      type: this.form.value.type as WorkspaceType,
      currency_code: this.form.value.currency_code ?? 'USD',
    };

    const request$ =
      this.mode === 'create'
        ? this.workspacesService.create(payload)
        : this.workspacesService.update(this.workspaceId!, payload);

    request$
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.workspaceContext.invalidateCache();
          void this.router.navigateByUrl(this.backRoute());
        },
        error: () => this.loading.set(false),
      });
  }

  setAsDefault(): void {
    if (!this.workspaceId || this.isDefault() || this.settingDefault()) return;

    this.settingDefault.set(true);
    this.workspacesService
      .setDefault(this.workspaceId)
      .pipe(
        finalize(() => this.settingDefault.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          this.authState.setDefaultWorkspaceId(response.data.default_workspace_id ?? null);
          this.toastService.success(this.translate.instant('workspaces.default_set_success'));
        },
        error: () =>
          this.toastService.error(this.translate.instant('workspaces.default_set_error')),
      });
  }

  private loadWorkspace(id: string): void {
    this.loading.set(true);
    this.workspacesService
      .getById(id)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          if (response.data.owner_id !== this.authState.userId()) {
            void this.router.navigateByUrl('/user/workspaces');
            return;
          }
          this.form.patchValue({
            name: response.data.name,
            type: response.data.type,
            currency_code: response.data.currency_code,
          });
        },
        error: () => this.loading.set(false),
      });
  }
}
