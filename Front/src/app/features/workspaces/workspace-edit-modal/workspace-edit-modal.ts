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
import { WorkspacesService } from '../../../core/services/workspaces';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { Workspace, WorkspaceType } from '../../../core/models/workspace.model';
import { ToastService } from '../../../core/services/toast.service';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';

@Component({
  selector: 'app-workspace-edit-modal',
  imports: [ReactiveFormsModule, TranslateModule, ModalShellComponent],
  templateUrl: './workspace-edit-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspaceEditModalComponent {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly translate = inject(TranslateService);
  private readonly workspacesService = inject(WorkspacesService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly toastService = inject(ToastService);

  readonly open = input.required<boolean>();
  readonly workspace = input.required<Workspace | null>();
  readonly closed = output<void>();
  readonly saved = output<Workspace>();

  readonly loading = signal(false);

  readonly workspaceTypes: WorkspaceType[] = ['personal', 'familiar', 'empresa'];
  readonly currencies = ['USD', 'EUR', 'ARS', 'MXN', 'CLP', 'COP', 'BRL'];

  readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(120)]],
    type: ['personal' as WorkspaceType, [Validators.required]],
    currency_code: ['USD', [Validators.required]],
  });

  constructor() {
    effect(() => {
      const ws = this.workspace();
      if (this.open() && ws) {
        this.form.patchValue({
          name: ws.name,
          type: ws.type,
          currency_code: ws.currency_code,
        });
      }
    });
  }

  submit(): void {
    const ws = this.workspace();
    if (!ws || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);

    const payload = {
      name: (this.form.value.name ?? '').trim(),
      type: this.form.value.type as WorkspaceType,
      currency_code: this.form.value.currency_code ?? 'USD',
    };

    this.workspacesService
      .update(ws.id, payload)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          this.workspaceContext.invalidateCache();
          this.saved.emit(response.data);
          this.close();
        },
        error: () => this.loading.set(false),
      });
  }

  close(): void {
    this.closed.emit();
  }
}
