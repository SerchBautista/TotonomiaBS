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
import {
  PaymentType,
  WorkspacePaymentMethodCreatePayload,
  WorkspacePaymentMethodState,
  WorkspacePaymentMethodSummary,
} from '../../../core/models/payment-method.model';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { EmptyStateComponent } from '../../../shared/empty-state/empty-state';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';

@Component({
  selector: 'app-workspace-payment-methods',
  imports: [
    ReactiveFormsModule,
    TranslateModule,
    DragDropModule,
    PageHeaderComponent,
    SectionPanelComponent,
    StatusBadgeComponent,
    ModalShellComponent,
    LoadingStateComponent,
    EmptyStateComponent,
  ],
  templateUrl: './workspace-payment-methods.html',
  styleUrl: './workspace-payment-methods.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspacePaymentMethodsComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);
  private readonly paymentMethodsService = inject(PaymentMethodsService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly authState = inject(AuthStateService);
  private readonly route = inject(ActivatedRoute);

  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly rowLoadingId = signal<string | null>(null);
  readonly bulkLoading = signal<'link' | 'unlink' | null>(null);
  readonly showCreateForm = signal(false);
  readonly searchUsing = signal('');
  readonly searchAvailable = signal('');
  readonly search = signal('');
  readonly filter = signal<'all' | 'linked' | 'unlinked' | 'inactive'>('all');
  readonly methods = signal<WorkspacePaymentMethodSummary[]>([]);
  readonly currentWorkspace = this.workspaceContext.selectedWorkspace;

  readonly createForm = this.fb.group({
    type: ['card' as 'card' | 'other', [Validators.required]],
    name: ['', [Validators.required, Validators.maxLength(100)]],
    card_type: ['credit' as 'credit' | 'debit'],
    brand: ['', [Validators.maxLength(50)]],
    last_4_digits: ['', [Validators.pattern(/^\d{4}$/)]],
    description: ['', [Validators.maxLength(1000)]],
  });

  readonly isWorkspaceOwner = computed(() => {
    const workspace = this.currentWorkspace();
    const userId = this.authState.userId();
    return !!workspace && !!userId && workspace.owner_id === userId;
  });
  readonly cashMethod = computed(
    () => this.methods().find((method) => method.type === 'cash' && this.getMethodState(method) === 'linked') ?? null,
  );
  readonly usingHere = computed(() =>
    this.filteredBySearch(
      this.methods().filter(
        (method) => method.type !== 'cash' && this.getMethodState(method) === 'linked',
      ),
      this.searchUsing(),
    ),
  );
  readonly available = computed(() =>
    this.filteredBySearch(
      this.methods().filter(
        (method) => method.type !== 'cash' && this.getMethodState(method) !== 'linked',
      ),
      this.searchAvailable(),
    ),
  );
  readonly filteredMethods = computed(() => {
    const query = this.search().trim().toLowerCase();
    const currentFilter = this.filter();

    return this.methods().filter((method) => {
      if (method.type === 'cash') {
        return false;
      }

      if (
        query &&
        !`${method.display_name} ${method.masked_details ?? ''}`.toLowerCase().includes(query)
      ) {
        return false;
      }

      const state = this.getMethodState(method);
      if (currentFilter === 'linked') return state === 'linked';
      if (currentFilter === 'unlinked') return state === 'unlinked';
      if (currentFilter === 'inactive') return state === 'linked_read_only';
      return true;
    });
  });

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
      this.toastService.error(this.translate.instant('workspace_payment_methods.load_error'));
      return;
    }

    this.workspaceContext.setCurrentWorkspaceId(this.workspaceId);
    await this.workspaceContext.ensureLoaded();

    this.createForm.controls.type.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((type) => this.applyCreateFormTypeRules(type ?? 'card'));

    this.applyCreateFormTypeRules(this.createForm.controls.type.value ?? 'card');

    this.paymentMethodsService.paymentMethodCreated$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ workspaceId }) => {
        if (workspaceId === this.workspaceId) {
          this.loadMethods();
        }
      });

    this.loadMethods();
  }

  onDrop(event: CdkDragDrop<WorkspacePaymentMethodSummary[]>): void {
    if (event.previousContainer === event.container) {
      return;
    }

    const method = event.item.data as WorkspacePaymentMethodSummary;
    const shouldLink = event.container.id === 'using-payment-methods';
    this.updateMethodUsage(method, shouldLink);
  }

  updateMethodUsage(method: WorkspacePaymentMethodSummary, shouldLink: boolean): void {
    if (method.type === 'cash' || this.rowLoadingId() || !this.isWorkspaceOwner()) {
      return;
    }

    this.rowLoadingId.set(method.id);
    this.paymentMethodsService
      .updateLink(this.workspaceId, method.id, shouldLink)
      .pipe(
        finalize(() => this.rowLoadingId.set(null)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.toastService.success(
            this.translate.instant(
              shouldLink
                ? 'workspace_payment_methods.use_here_success'
                : 'workspace_payment_methods.remove_from_here_success',
            ),
          );
          this.loadMethods();
        },
        error: () => {},
      });
  }

  toggleCreateForm(): void {
    if (!this.isWorkspaceOwner()) return;
    this.showCreateForm.set(!this.showCreateForm());
    if (!this.showCreateForm()) {
      this.resetCreateForm();
    }
  }

  submitCreate(): void {
    if (!this.isWorkspaceOwner()) {
      this.toastService.warning(this.translate.instant('workspace_payment_methods.owner_only'));
      return;
    }

    if (this.createForm.invalid) {
      this.createForm.markAllAsTouched();
      return;
    }

    const type = this.createForm.controls.type.value ?? 'card';
    const payload: WorkspacePaymentMethodCreatePayload = {
      type,
      name: (this.createForm.controls.name.value ?? '').trim(),
      ...(type === 'card'
        ? {
            card_type: this.createForm.controls.card_type.value ?? 'credit',
            brand: this.trimOrNull(this.createForm.controls.brand.value),
            last_4_digits: this.trimOrNull(this.createForm.controls.last_4_digits.value),
          }
        : {
            description: this.trimOrNull(this.createForm.controls.description.value),
          }),
    };

    this.saving.set(true);
    this.paymentMethodsService
      .create(this.workspaceId, payload)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.resetCreateForm();
          this.showCreateForm.set(false);
          this.toastService.success(this.translate.instant('workspace_payment_methods.create_success'));
          this.loadMethods();
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

  onSearchChange(value: string): void {
    this.search.set(value);
  }

  setFilter(filter: 'all' | 'linked' | 'unlinked' | 'inactive'): void {
    this.filter.set(filter);
  }

  getMethodTypeLabel(type: PaymentType): string {
    return this.translate.instant(`payment.${type}`);
  }

  getMethodState(method: WorkspacePaymentMethodSummary): WorkspacePaymentMethodState {
    const backendState = (method.state ?? '').toLowerCase();

    if (backendState === 'linked' || backendState === 'ligado') return 'linked';
    if (backendState === 'not_linked' || backendState === 'unlinked' || backendState === 'no_ligado') {
      return 'unlinked';
    }
    if (backendState === 'read_only_linked' || backendState === 'linked_read_only') {
      return 'linked_read_only';
    }

    if (!method.is_linked) return 'unlinked';
    return method.is_valid_for_transactions ? 'linked' : 'linked_read_only';
  }

  statusVariant(method: WorkspacePaymentMethodSummary): 'warning' | 'brand' {
    return this.getMethodState(method) === 'linked_read_only' ? 'warning' : 'brand';
  }

  trackByMethod(_: number, method: WorkspacePaymentMethodSummary): string {
    return method.id;
  }

  toggleLink(method: WorkspacePaymentMethodSummary): void {
    this.updateMethodUsage(method, !method.is_linked);
  }

  bulkLink(isLinked: boolean): void {
    if (!this.isWorkspaceOwner()) {
      return;
    }

    this.bulkLoading.set(isLinked ? 'link' : 'unlink');
    this.paymentMethodsService
      .bulkLinking(this.workspaceId, isLinked)
      .pipe(
        finalize(() => this.bulkLoading.set(null)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => this.loadMethods(),
        error: () => {},
      });
  }

  private loadMethods(): void {
    if (!this.workspaceId) return;

    this.loading.set(true);
    this.paymentMethodsService
      .listWorkspace(this.workspaceId)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => this.methods.set(response.data),
        error: () => this.loading.set(false),
      });
  }

  private filteredBySearch(
    methods: WorkspacePaymentMethodSummary[],
    query: string,
  ): WorkspacePaymentMethodSummary[] {
    const normalized = query.trim().toLowerCase();
    if (!normalized) {
      return methods;
    }

    return methods.filter((method) =>
      `${method.display_name} ${method.masked_details ?? ''}`.toLowerCase().includes(normalized),
    );
  }

  private applyCreateFormTypeRules(type: 'card' | 'other'): void {
    const cardTypeControl = this.createForm.controls.card_type;
    const brandControl = this.createForm.controls.brand;
    const last4Control = this.createForm.controls.last_4_digits;
    const descriptionControl = this.createForm.controls.description;

    if (type === 'card') {
      cardTypeControl.setValidators([Validators.required]);
      last4Control.setValidators([Validators.required, Validators.pattern(/^\d{4}$/)]);
      descriptionControl.clearValidators();
    } else {
      cardTypeControl.clearValidators();
      last4Control.clearValidators();
      descriptionControl.setValidators([Validators.maxLength(1000)]);
      cardTypeControl.setValue('credit', { emitEvent: false });
      brandControl.setValue('', { emitEvent: false });
      last4Control.setValue('', { emitEvent: false });
    }

    cardTypeControl.updateValueAndValidity({ emitEvent: false });
    brandControl.updateValueAndValidity({ emitEvent: false });
    last4Control.updateValueAndValidity({ emitEvent: false });
    descriptionControl.updateValueAndValidity({ emitEvent: false });
  }

  private resetCreateForm(): void {
    this.createForm.reset({
      type: 'card',
      name: '',
      card_type: 'credit',
      brand: '',
      last_4_digits: '',
      description: '',
    });
    this.applyCreateFormTypeRules('card');
  }

  private trimOrNull(value: string | null | undefined): string | null {
    const trimmed = value?.trim() ?? '';
    return trimmed ? trimmed : null;
  }
}
