import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  input,
  signal,
} from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { Router, NavigationEnd } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { filter, map } from 'rxjs';
import { WorkspaceContextService } from '../../core/services/workspace-context';
import { ModalShellComponent } from '../modal-shell/modal-shell';

const USER_ROUTE_PREFIX = '/user/';

/**
 * Reusable workspace switcher.
 *
 * Desktop (>640px): renders a labeled `<select>` that mutates the global
 * `WorkspaceContextService.currentWorkspaceId` signal directly.
 *
 * Mobile (≤640px): renders a button with the current workspace name
 * that opens a modal bottom sheet for workspace selection.
 *
 * No outputs are exposed: consumers react via signal effects
 * or the existing service-driven flows.
 *
 * Visibility is decided internally so the host template does not
 * need to wrap it with a conditional.
 */
@Component({
  selector: 'app-workspace-switcher',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [TranslateModule, ModalShellComponent],
  templateUrl: './workspace-switcher.html',
  styleUrl: './workspace-switcher.scss',
})
export class WorkspaceSwitcherComponent {
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly router = inject(Router);

  private readonly currentUrl = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map(e => e.urlAfterRedirects)
    ),
    { initialValue: this.router.url }
  );

  protected readonly isWorkspaceRoute = computed(() =>
    this.currentUrl()?.includes('/user/workspaces/') ?? false
  );

  /** Muestra el `<label>` visible a la izquierda del `<select>`. Default: `true`. */
  readonly showLabel = input<boolean>(true);

  /** Lista de workspaces disponibles (signal readonly del servicio). */
  protected readonly workspaces = this.workspaceContext.workspaces;

  /** ID del workspace actualmente seleccionado. */
  protected readonly currentWorkspaceId = this.workspaceContext.currentWorkspaceId;

  /** Workspace actualmente seleccionado (objeto completo). */
  protected readonly currentWorkspace = this.workspaceContext.selectedWorkspace;

  /** Controla la visibilidad del modal de selección de workspace en móvil. */
  protected readonly modalOpen = signal(false);

  /** `true` si estamos en una ruta de usuario, hay más de un workspace y no estamos en una ruta de workspace específico. */
  protected readonly visible = computed(
    () => (this.currentUrl()?.startsWith(USER_ROUTE_PREFIX) ?? false)
      && this.workspaces().length > 1
      && !this.isWorkspaceRoute()
  );

  protected onChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.workspaceContext.setCurrentWorkspaceId(value);
  }

  /** Abre el modal de selección de workspace (móvil). */
  protected openModal(): void {
    this.modalOpen.set(true);
  }

  /** Cierra el modal de selección de workspace (móvil). */
  protected closeModal(): void {
    this.modalOpen.set(false);
  }

  /** Selecciona un workspace y cierra el modal. */
  protected selectWorkspace(id: string): void {
    this.workspaceContext.setCurrentWorkspaceId(id);
    this.closeModal();
  }
}
