import {
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  inject,
  signal
} from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { TranslateModule } from '@ngx-translate/core';
import { AUTH_STATE_TOKEN } from '../../core/tokens/auth-state.token';
import { effectiveRoles } from '../../core/auth/role-hierarchy';

const COLLAPSED_STORAGE_KEY = 'totonomia:sidebar:collapsed';
const USER_ROUTE_PREFIX = '/user/';
const ADMIN_ROUTE_PREFIX = '/admin/';

interface SidebarItem {
  readonly link: string;
  readonly icon: string;
  readonly labelKey: string;
}

@Component({
  selector: 'app-nav',
  imports: [RouterLink, RouterLinkActive, TranslateModule],
  templateUrl: './nav.html',
  styleUrl: './nav.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class NavComponent {
  readonly authService = inject(AUTH_STATE_TOKEN);
  private readonly router = inject(Router);

  readonly collapsed = signal<boolean>(this.loadCollapsed());

  private readonly persistEffect = effect(() => {
    const value = this.collapsed();
    this.saveCollapsed(value);
  });

  readonly currentSection = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map((e) => this.resolveSection(e.urlAfterRedirects)),
      startWith(this.resolveSection(this.router.url))
    ),
    { initialValue: this.resolveSection(this.router.url) }
  );

  private readonly effectiveRoleSet = computed(() => effectiveRoles(this.authService.role()));

  readonly isAdmin = computed(() => this.effectiveRoleSet().has('admin'));

  readonly showUserMenu = computed(
    () => this.currentSection() === 'user' && this.effectiveRoleSet().has('user')
  );

  readonly showAdminMenu = computed(
    () => this.currentSection() === 'admin' && this.effectiveRoleSet().has('admin')
  );

  readonly userItems: readonly SidebarItem[] = [
    { link: '/user/dashboard', icon: 'fa-chart-pie', labelKey: 'nav.dashboard_home' },
    { link: '/user/expenses', icon: 'fa-coins', labelKey: 'nav.expenses' },
    { link: '/user/fixed-expenses', icon: 'fa-calendar', labelKey: 'nav.fixed_expenses' },
    { link: '/user/pending-payments', icon: 'fa-hand-holding-dollar', labelKey: 'nav.pending_payments' },
    { link: '/user/settings', icon: 'fa-gear', labelKey: 'nav.settings' }
  ];

  readonly adminItems: readonly SidebarItem[] = [
    { link: '/admin/dashboard', icon: 'fa-gauge', labelKey: 'nav.dashboard' },
    { link: '/admin/users', icon: 'fa-users', labelKey: 'nav.users' },
    { link: '/admin/administrators', icon: 'fa-users-cog', labelKey: 'nav.administrators' }
  ];

  readonly crossLinkToAdmin: SidebarItem = {
    link: '/admin/dashboard',
    icon: 'fa-right-to-bracket',
    labelKey: 'nav.go_to_admin'
  };

  readonly crossLinkToUser: SidebarItem = {
    link: '/user/dashboard',
    icon: 'fa-right-to-bracket',
    labelKey: 'nav.go_to_user'
  };

  toggleCollapsed(): void {
    this.collapsed.update((value) => !value);
  }

  private resolveSection(url: string): 'user' | 'admin' | null {
    if (url.startsWith(USER_ROUTE_PREFIX)) {
      return 'user';
    }
    if (url.startsWith(ADMIN_ROUTE_PREFIX)) {
      return 'admin';
    }
    return null;
  }

  private loadCollapsed(): boolean {
    if (typeof window === 'undefined') {
      return false;
    }
    return window.localStorage.getItem(COLLAPSED_STORAGE_KEY) === 'true';
  }

  private saveCollapsed(value: boolean): void {
    if (typeof window === 'undefined') {
      return;
    }
    window.localStorage.setItem(COLLAPSED_STORAGE_KEY, String(value));
  }
}
