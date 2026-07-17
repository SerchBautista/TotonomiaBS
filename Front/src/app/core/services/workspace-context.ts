import { computed, inject, Injectable, signal } from '@angular/core';
import { firstValueFrom, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { ensureNormalizedBackendError } from '../errors/backend-error.mapper';
import { NormalizedBackendError } from '../errors/backend-error.model';
import { Workspace } from '../models/workspace.model';
import { STORAGE_SERVICE_TOKEN } from '../tokens/storage.token';
import { AuthStateService } from './auth-state.service';
import { WorkspacesService } from './workspaces';

const STORAGE_KEY = 'activeWorkspaceId';

@Injectable({ providedIn: 'root' })
export class WorkspaceContextService {
  private readonly workspacesService = inject(WorkspacesService);
  private readonly storage = inject(STORAGE_SERVICE_TOKEN);
  private readonly authState = inject(AuthStateService);

  private readonly workspacesState = signal<Workspace[]>([]);
  private readonly currentWorkspaceIdState = signal<string | null>(this.storage.getItem(STORAGE_KEY));
  private readonly loadedState = signal(false);
  private readonly loadErrorState = signal<NormalizedBackendError | null>(null);
  private loadingPromise: Promise<Workspace[]> | null = null;

  readonly workspaces = this.workspacesState.asReadonly();
  readonly currentWorkspaceId = this.currentWorkspaceIdState.asReadonly();
  readonly loaded = this.loadedState.asReadonly();
  readonly loadError = this.loadErrorState.asReadonly();
  readonly selectedWorkspace = computed(
    () => this.workspacesState().find((workspace) => workspace.id === this.currentWorkspaceIdState()) ?? null
  );

  async ensureLoaded(): Promise<Workspace[]> {
    if (this.loadedState()) {
      return this.workspacesState();
    }

    if (this.loadingPromise) {
      return this.loadingPromise;
    }

    this.loadErrorState.set(null);
    this.loadingPromise = firstValueFrom(
      this.workspacesService.list({ page: 1, per_page: 100 }).pipe(
        map((response) => ({
          workspaces: response.data,
          error: null as NormalizedBackendError | null,
        })),
        catchError((error) => of({
          workspaces: [] as Workspace[],
          error: ensureNormalizedBackendError(error, {
            fallbackMessage: 'No fue posible cargar los espacios de trabajo',
          }),
        }))
      )
    ).then(({ workspaces, error }) => {
      this.loadingPromise = null;

      if (error) {
        this.workspacesState.set([]);
        this.loadedState.set(false);
        this.loadErrorState.set(error);
        return workspaces;
      }

      this.workspacesState.set(workspaces);
      this.loadedState.set(true);

      const currentId = this.currentWorkspaceIdState();
      const defaultId = this.authState.defaultWorkspaceId();
      const isDefaultValid = defaultId && workspaces.some((w) => w.id === defaultId);
      const currentIsValid = currentId && workspaces.some((w) => w.id === currentId);

      const fallbackId = isDefaultValid ? defaultId : currentIsValid ? currentId : workspaces[0]?.id;
      if (fallbackId && fallbackId !== currentId) {
        this.setCurrentWorkspaceId(fallbackId);
      }

      return workspaces;
    });

    return this.loadingPromise;
  }

  async resolveWorkspaceId(preferredId?: string | null): Promise<string> {
    const workspaces = await this.ensureLoaded();
    const nextId = preferredId ?? this.currentWorkspaceIdState() ?? workspaces[0]?.id ?? '';

    if (nextId) {
      this.setCurrentWorkspaceId(nextId);
    }

    return nextId;
  }

  invalidateCache(): void {
    this.loadedState.set(false);
    this.loadingPromise = null;
  }

  setCurrentWorkspaceId(workspaceId: string | null): void {
    this.currentWorkspaceIdState.set(workspaceId);

    if (workspaceId) {
      this.storage.setItem(STORAGE_KEY, workspaceId);
    } else {
      this.storage.removeItem(STORAGE_KEY);
    }
  }
}
