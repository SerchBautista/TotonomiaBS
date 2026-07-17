import {
  ChangeDetectionStrategy,
  Component,
  OnInit,
  computed,
  effect,
  inject,
} from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { filter, map, startWith } from 'rxjs';
import { TranslateModule } from '@ngx-translate/core';
import { AUTH_STATE_TOKEN } from './core/tokens/auth-state.token';
import { AuthApiService } from './core/services/auth-api.service';
import { NavComponent } from './shared/nav/nav';
import { PublicShellComponent } from './shared/public-shell/public-shell';
import { WorkspaceContextService } from './core/services/workspace-context';
import { QuickAddExpenseFabComponent } from './features/expenses/quick-add/quick-add-expense-fab';
import { WorkspaceSwitcherComponent } from './shared/workspace-switcher/workspace-switcher';
import { NotificationService } from './core/services/notification.service';
import { UserPreferencesService } from './core/services/user-preferences.service';
import { isLandingRoute, usesPublicShell } from './core/utils/public-layout';
import { resolvePageTitle } from './core/utils/page-title';

const USER_ROUTE_PREFIX = '/user/';

const QUICK_ADD_HIDDEN_ROUTE_PREFIXES = [
  '/user/settings',
  '/user/profile',
  '/user/notifications',
  '/admin',
];

const FAB_BUTTON_HIDDEN_ROUTE_PREFIXES = [
  ...QUICK_ADD_HIDDEN_ROUTE_PREFIXES,
  '/user/workspaces',
];

function isQuickAddHiddenRoute(url: string): boolean {
  return QUICK_ADD_HIDDEN_ROUTE_PREFIXES.some((prefix) => url.startsWith(prefix));
}

function isFabButtonHiddenRoute(url: string): boolean {
  return FAB_BUTTON_HIDDEN_ROUTE_PREFIXES.some((prefix) => url.startsWith(prefix));
}

@Component({
  selector: 'app-root',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    TranslateModule,
    NavComponent,
    PublicShellComponent,
    QuickAddExpenseFabComponent,
    WorkspaceSwitcherComponent,
  ],
  host: {
    '[attr.data-theme]': 'preferencesService.theme()',
  },
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App implements OnInit {
  readonly authService = inject(AUTH_STATE_TOKEN);
  readonly notificationService = inject(NotificationService);
  readonly preferencesService = inject(UserPreferencesService);
  private readonly authApiService = inject(AuthApiService);
  private readonly workspaceContext = inject(WorkspaceContextService);
  private readonly router = inject(Router);

  readonly showPublicShell = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map((e) => usesPublicShell(e.urlAfterRedirects)),
      startWith(usesPublicShell(this.router.url)),
    ),
    { initialValue: usesPublicShell(this.router.url) },
  );

  readonly showLandingShell = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map((e) => isLandingRoute(e.urlAfterRedirects)),
      startWith(isLandingRoute(this.router.url)),
    ),
    { initialValue: isLandingRoute(this.router.url) },
  );

  readonly isUserContextRoute = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map(
        (e) => this.authService.isLoggedIn() && e.urlAfterRedirects.startsWith(USER_ROUTE_PREFIX),
      ),
      startWith(this.authService.isLoggedIn() && this.router.url.startsWith(USER_ROUTE_PREFIX)),
    ),
    {
      initialValue: this.authService.isLoggedIn() && this.router.url.startsWith(USER_ROUTE_PREFIX),
    },
  );

  readonly currentUrl = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map((e) => e.urlAfterRedirects),
      startWith(this.router.url),
    ),
    { initialValue: this.router.url },
  );

  readonly showQuickAdd = computed(
    () => this.isUserContextRoute() && !isQuickAddHiddenRoute(this.currentUrl()),
  );

  readonly showFabButton = computed(
    () => this.showQuickAdd() && !isFabButtonHiddenRoute(this.currentUrl()),
  );

  private userContextInitialized = false;

  private readonly userContextInitEffect = effect(() => {
    if (!this.isUserContextRoute() || this.userContextInitialized) {
      return;
    }
    this.userContextInitialized = true;
    void this.workspaceContext.ensureLoaded();
    this.notificationService.startPolling();
    this.preferencesService.loadFromBackend();
  });

  readonly pageTitle = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map((e) => resolvePageTitle(e.urlAfterRedirects)),
      startWith(resolvePageTitle(this.router.url)),
    ),
    { initialValue: resolvePageTitle(this.router.url) },
  );

  private readonly syncPreferencesEffect = effect(() => {
    const theme = this.preferencesService.theme();
    if (typeof document !== 'undefined') {
      document.body.dataset['theme'] = theme;
      document.documentElement.style.colorScheme = theme;
    }
  });

  ngOnInit(): void {
    this.preferencesService.applyTheme(this.preferencesService.theme());
    this.preferencesService.applyLocale(this.preferencesService.locale());
  }

  setTheme(theme: 'dark' | 'light'): void {
    this.preferencesService.applyTheme(theme);
  }

  logout(): void {
    this.authApiService.logout();
  }
}
