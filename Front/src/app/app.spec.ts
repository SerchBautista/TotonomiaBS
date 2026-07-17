import { Component, input } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { signal } from '@angular/core';
import { vi } from 'vitest';
import { AUTH_STATE_TOKEN } from './core/tokens/auth-state.token';
import { AuthApiService } from './core/services/auth-api.service';
import { NotificationService } from './core/services/notification.service';
import { UserPreferencesService } from './core/services/user-preferences.service';
import { App } from './app';
import { QuickAddExpenseFabComponent } from './features/expenses/quick-add/quick-add-expense-fab';
import { WorkspaceContextService } from './core/services/workspace-context';

@Component({
  selector: 'app-quick-add-expense-fab',
  template: '',
  standalone: true,
})
class QuickAddExpenseFabStubComponent {
  readonly showFabButton = input(true);
}

describe('App', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [
        provideRouter([
          { path: 'user/dashboard', children: [] },
          { path: 'user/expenses', children: [] },
          { path: 'user/expenses/create', children: [] },
          { path: 'user/fixed-expenses', children: [] },
          { path: 'user/pending-payments', children: [] },
          { path: 'user/notifications', children: [] },
          { path: 'user/profile', children: [] },
          { path: 'user/settings', children: [] },
          { path: 'user/settings/categories', children: [] },
          { path: 'user/workspaces', children: [] },
          { path: 'user/workspaces/:id', children: [] },
          { path: 'user/workspaces/:id/expenses', children: [] },
          { path: 'admin/dashboard', children: [] },
        ]),
        provideTranslateService({
          fallbackLang: 'es',
          lang: 'es',
        }),
        {
          provide: AUTH_STATE_TOKEN,
          useValue: {
            isLoggedIn: () => false,
            role: () => null,
            token: () => null,
          },
        },
        {
          provide: AuthApiService,
          useValue: {
            logout: () => undefined,
          },
        },
        {
          provide: WorkspaceContextService,
          useValue: {
            workspaces: () => [],
            currentWorkspaceId: () => null,
            selectedWorkspace: () => null,
            setCurrentWorkspaceId: vi.fn(),
            ensureLoaded: () => Promise.resolve([]),
          },
        },
        {
          provide: NotificationService,
          useValue: {
            unreadCount: () => 0,
            startPolling: () => undefined,
          },
        },
        {
          provide: UserPreferencesService,
          useValue: {
            theme: () => 'dark' as const,
            locale: () => 'es' as const,
            applyTheme: () => undefined,
            applyLocale: () => undefined,
            loadFromBackend: () => undefined,
          },
        },
      ],
    })
      .overrideComponent(App, {
        remove: { imports: [QuickAddExpenseFabComponent] },
        add: { imports: [QuickAddExpenseFabStubComponent] },
      })
      .compileComponents();

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        app: {
          title: 'Totonomía',
          tagline: 'Toto el guardián de tu economía',
        },
        nav: {
          profile: 'Mi perfil',
        },
        auth: {
          logout: 'Cerrar sesión',
        },
        notifications: {
          title: 'Notificaciones',
        },
        topbar: {
          section: {
            dashboard: 'Dashboard',
            overview: 'Resumen',
          },
        },
        expenses: {
          title: 'Gastos',
          form_create_title: 'Registrar gasto',
          form_edit_title: 'Editar gasto',
        },
        learn: {
          nav: {
            learn: 'Aprende',
            pricing: 'Precios',
            login: 'Entrar',
            register: 'Crear cuenta',
          },
        },
      },
      true,
    );
    translate.use('es');
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(App);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it('should render public shell brand for guests', async () => {
    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('.public-shell__brand strong')?.textContent).toContain(
      'Totonomía',
    );
  });

  it('does not render the legacy topbar-brand container', async () => {
    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('.topbar-brand')).toBeNull();
  });

  describe('topbar breadcrumb', () => {
    const setupAuthed = async (url: string): Promise<void> => {
      const authState = TestBed.inject(AUTH_STATE_TOKEN) as {
        isLoggedIn: () => boolean;
        role: () => string | null;
        token: () => string | null;
      };
      authState.isLoggedIn = () => true;
      authState.role = () => 'user';
      authState.token = () => 'test-token';

      const notification = TestBed.inject(NotificationService) as {
        unreadCount: () => number;
        startPolling: () => void;
      };
      notification.unreadCount = () => 0;
      notification.startPolling = () => undefined;

      const router = TestBed.inject(Router);
      await router.navigateByUrl(url);
    };

    it('shows the Dashboard parent for /user/dashboard', async () => {
      await setupAuthed('/user/dashboard');

      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const title = compiled.querySelector('.topbar-page-title__parent')?.textContent?.trim();
      expect(title).toBe('Dashboard');
    });

    it('shows Gastos for /user/expenses', async () => {
      await setupAuthed('/user/expenses');

      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const title = compiled.querySelector('.topbar-page-title__parent')?.textContent?.trim();
      expect(title).toBe('Gastos');
    });

    it('shows Gastos for /user/expenses/create', async () => {
      await setupAuthed('/user/expenses/create');

      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const title = compiled.querySelector('.topbar-page-title__parent')?.textContent?.trim();
      expect(title).toBe('Gastos');
    });
  });

  describe('topbar actions', () => {
    const setupAuthed = async (): Promise<void> => {
      const authState = TestBed.inject(AUTH_STATE_TOKEN) as {
        isLoggedIn: () => boolean;
        role: () => string | null;
        token: () => string | null;
      };
      authState.isLoggedIn = () => true;
      authState.role = () => 'user';
      authState.token = () => 'test-token';

      const router = TestBed.inject(Router);
      await router.navigateByUrl('/user/dashboard');
    };

    it('uses the floating-card style and renders three icon buttons', async () => {
      await setupAuthed();
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const topbar = compiled.querySelector('header.topbar') as HTMLElement | null;
      expect(topbar).toBeTruthy();
      expect(topbar?.getAttribute('data-topbar-style')).toBe('floating-card');

      const actions = compiled.querySelector('.topbar-actions') as HTMLElement | null;
      expect(actions).toBeTruthy();
      const iconButtons = actions?.querySelectorAll('.topbar-icon-btn') ?? [];
      expect(iconButtons.length).toBe(3);
      iconButtons.forEach((btn) => {
        expect(btn.querySelector('i.fas')).toBeTruthy();
      });

      const ghostButtons = actions?.querySelectorAll('.btn.ghost') ?? [];
      expect(ghostButtons.length).toBe(0);
    });

    it('keeps the notification badge slot when unread count is positive', async () => {
      const notification = TestBed.inject(NotificationService) as {
        unreadCount: () => number;
        startPolling: () => void;
      };
      notification.unreadCount = () => 3;
      notification.startPolling = () => undefined;

      await setupAuthed();
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const badge = compiled.querySelector('.notification-badge');
      expect(badge).toBeTruthy();
      expect(badge?.textContent?.trim()).toBe('3');
    });
  });

  describe('quick-add expense FAB visibility', () => {
    const setupAuthedAt = async (url: string): Promise<void> => {
      const authState = TestBed.inject(AUTH_STATE_TOKEN) as {
        isLoggedIn: () => boolean;
        role: () => string | null;
        token: () => string | null;
      };
      authState.isLoggedIn = () => true;
      authState.role = () => 'user';
      authState.token = () => 'test-token';

      const router = TestBed.inject(Router);
      await router.navigateByUrl(url);
    };

    const fabElement = (
      fixture: ReturnType<typeof TestBed.createComponent<App>>,
    ): HTMLElement | null => {
      const compiled = fixture.nativeElement as HTMLElement;
      return compiled.querySelector('app-quick-add-expense-fab');
    };

    it('renders the FAB on /user/dashboard', async () => {
      await setupAuthedAt('/user/dashboard');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeTruthy();
    });

    it('renders the FAB on /user/expenses', async () => {
      await setupAuthedAt('/user/expenses');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeTruthy();
    });

    it('renders the FAB on /user/expenses/create', async () => {
      await setupAuthedAt('/user/expenses/create');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeTruthy();
    });

    it('renders the FAB on /user/fixed-expenses', async () => {
      await setupAuthedAt('/user/fixed-expenses');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeTruthy();
    });

    it('renders the FAB on /user/pending-payments', async () => {
      await setupAuthedAt('/user/pending-payments');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeTruthy();
    });

    it('hides the FAB on /user/settings', async () => {
      await setupAuthedAt('/user/settings');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeNull();
    });

    it('hides the FAB on child routes of /user/settings', async () => {
      await setupAuthedAt('/user/settings/categories');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeNull();
    });

    it('hides the FAB on /user/profile', async () => {
      await setupAuthedAt('/user/profile');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeNull();
    });

    it('hides the FAB on /user/notifications', async () => {
      await setupAuthedAt('/user/notifications');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeNull();
    });

    it('hides the FAB on /admin/dashboard', async () => {
      await setupAuthedAt('/admin/dashboard');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeNull();
    });

    it('mounts quick-add on /user/workspaces (FAB hidden via showFabButton)', async () => {
      await setupAuthedAt('/user/workspaces');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeTruthy();
      expect(fixture.componentInstance.showFabButton()).toBe(false);
      expect(fixture.componentInstance.showQuickAdd()).toBe(true);
    });

    it('mounts quick-add on /user/workspaces/:id/expenses for expense-list modal', async () => {
      await setupAuthedAt('/user/workspaces/ws-1/expenses');
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fabElement(fixture)).toBeTruthy();
      expect(fixture.componentInstance.showFabButton()).toBe(false);
      expect(fixture.componentInstance.showQuickAdd()).toBe(true);
    });
  });

  describe('workspace switcher in topbar', () => {
    const setupAuthedWithWorkspaces = async (
      options: {
        role?: string;
        url?: string;
        workspaces?: { id: string; name: string }[];
      } = {},
    ): Promise<void> => {
      const authState = TestBed.inject(AUTH_STATE_TOKEN) as {
        isLoggedIn: () => boolean;
        role: () => string | null;
        token: () => string | null;
      };
      authState.isLoggedIn = () => true;
      authState.role = () => options.role ?? 'user';
      authState.token = () => 'test-token';

      const workspaces = signal(
        options.workspaces ?? [
          { id: 'ws-1', name: 'Casa' },
          { id: 'ws-2', name: 'Negocio' },
        ],
      );
      const currentId = signal<string | null>('ws-1');

      const workspaceContext = TestBed.inject(WorkspaceContextService) as any;
      workspaceContext.workspaces = () => workspaces();
      workspaceContext.currentWorkspaceId = () => currentId();
      workspaceContext.setCurrentWorkspaceId = (id: string | null) => currentId.set(id);

      const router = TestBed.inject(Router);
      await router.navigateByUrl(options.url ?? '/user/dashboard');
    };

    it('renders switcher in topbar when role is user and >1 workspaces', async () => {
      await setupAuthedWithWorkspaces();
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const actions = compiled.querySelector('.topbar-actions');
      expect(actions).toBeTruthy();
      expect(actions?.querySelector('#workspace-switcher')).toBeTruthy();
    });

    it('does not render switcher when only one workspace', async () => {
      await setupAuthedWithWorkspaces({ workspaces: [{ id: 'ws-1', name: 'Casa' }] });
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const actions = compiled.querySelector('.topbar-actions');
      expect(actions?.querySelector('#workspace-switcher')).toBeFalsy();
    });

    it('does not render switcher on admin routes', async () => {
      await setupAuthedWithWorkspaces({ role: 'admin', url: '/admin/dashboard' });
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const actions = compiled.querySelector('.topbar-actions');
      expect(actions?.querySelector('#workspace-switcher')).toBeFalsy();
    });

    it('renders switcher for an admin on a user route', async () => {
      await setupAuthedWithWorkspaces({ role: 'admin' });
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const actions = compiled.querySelector('.topbar-actions');
      expect(actions?.querySelector('#workspace-switcher')).toBeTruthy();
    });

    it('calls workspaceContext.setCurrentWorkspaceId on change', async () => {
      await setupAuthedWithWorkspaces();
      const fixture = TestBed.createComponent(App);
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      const compiled = fixture.nativeElement as HTMLElement;
      const select = compiled.querySelector('#workspace-switcher') as HTMLSelectElement;
      expect(select).toBeTruthy();

      const workspaceContext = TestBed.inject(WorkspaceContextService) as {
        setCurrentWorkspaceId: (id: string | null) => void;
      };
      const spy = vi.spyOn(workspaceContext, 'setCurrentWorkspaceId');

      select.value = 'ws-2';
      select.dispatchEvent(new Event('change'));
      fixture.detectChanges();

      expect(spy).toHaveBeenCalledWith('ws-2');
    });
  });
});
