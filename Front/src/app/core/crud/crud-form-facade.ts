import { inject, signal } from '@angular/core';
import { FormGroup } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { finalize, Observable } from 'rxjs';
import { ToastService } from '../services/toast.service';

export type CrudFormMode = 'create' | 'view' | 'edit';

export interface CrudEntityService<TItem, TCreatePayload, TUpdatePayload> {
  getById(id: string): Observable<{ data: { item: TItem } }>;
  create(payload: TCreatePayload): Observable<unknown>;
  update(id: string, payload: TUpdatePayload): Observable<unknown>;
}

export abstract class CrudFormFacade<TItem, TCreatePayload, TUpdatePayload> {
  protected readonly route = inject(ActivatedRoute);
  protected readonly router = inject(Router);
  protected readonly translate = inject(TranslateService);
  protected readonly toastService = inject(ToastService);

  readonly loading = signal(false);

  mode: CrudFormMode = 'create';
  entityId: string | null = null;

  protected abstract readonly form: FormGroup;
  protected abstract readonly crudService: CrudEntityService<TItem, TCreatePayload, TUpdatePayload>;
  protected abstract readonly loadErrorKey: string;
  protected abstract readonly saveErrorKey: string;
  protected abstract readonly successRoute: string;

  protected abstract mapItemToForm(item: TItem): void;
  protected abstract buildCreatePayload(): TCreatePayload;
  protected abstract buildUpdatePayload(): TUpdatePayload;

  protected initCrudForm(): void {
    const routeMode = this.route.snapshot.data['mode'] as CrudFormMode | undefined;
    this.mode = routeMode ?? 'create';

    const routeId = this.route.snapshot.paramMap.get('id');
    this.entityId = routeId ?? null;

    if (this.mode === 'view') {
      this.form.disable();
    }

    if (this.mode !== 'create' && this.entityId) {
      this.loadEntity(this.entityId);
    }
  }

  protected submitCrud(): void {
    if (this.mode === 'view') {
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const request$ =
      this.mode === 'create'
        ? this.crudService.create(this.buildCreatePayload())
        : this.entityId
          ? this.crudService.update(this.entityId, this.buildUpdatePayload())
          : null;

    if (!request$) {
      return;
    }

    this.loading.set(true);

    request$
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => this.onSaveSuccess(),
        error: () => this.toastService.error(this.translate.instant(this.saveErrorKey))
      });
  }

  protected onSaveSuccess(): void {
    void this.router.navigateByUrl(this.successRoute);
  }

  private loadEntity(id: string): void {
    this.loading.set(true);

    this.crudService
      .getById(id)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: (response) => this.mapItemToForm(response.data.item),
        error: () => this.toastService.error(this.translate.instant(this.loadErrorKey))
      });
  }
}
