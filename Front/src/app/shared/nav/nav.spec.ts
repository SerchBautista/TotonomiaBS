import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { AUTH_STATE_TOKEN } from '../../core/tokens/auth-state.token';
import { NavComponent } from './nav';

const COLLAPSED_STORAGE_KEY = 'totonomia:sidebar:collapsed';

describe('NavComponent (sidebar)', () => {
  let fixture: ComponentFixture<NavComponent>;
  let router: Router;

  const setupAt = async (
    url: string,
    role: 'user' | 'admin' = 'user'
  ): Promise<void> => {
    await TestBed.configureTestingModule({
      imports: [NavComponent],
      providers: [
        provideRouter([
          { path: 'user/dashboard', children: [] },
          { path: 'user/expenses', children: [] },
          { path: 'user/fixed-expenses', children: [] },
          { path: 'user/pending-payments', children: [] },
          { path: 'user/settings', children: [] },
          { path: 'admin/dashboard', children: [] },
          { path: 'admin/administrators', children: [] },
        ]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: AUTH_STATE_TOKEN,
          useValue: {
            isLoggedIn: () => true,
            role: () => role,
          },
        },
      ],
    }).compileComponents();

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        app: {
          title: 'Totonomía',
          tagline: 'Toto el guardián de tu economía',
        },
        nav: {
          dashboard_home: 'Dashboard',
          dashboard: 'Panel',
          users: 'Usuarios',
          administrators: 'Administradores',
          expenses: 'Gastos',
          fixed_expenses: 'Gastos fijos',
          pending_payments: 'Por pagar',
          settings: 'Configuración',
          collapse: 'Colapsar menú',
          expand: 'Expandir menú',
          go_to_admin: 'Ir a Administradores',
          go_to_user: 'Ir a Usuario',
        },
      },
      true
    );
    translate.use('es');

    router = TestBed.inject(Router);
    await router.navigateByUrl(url);
    fixture = TestBed.createComponent(NavComponent);
    fixture.detectChanges();
  };

  afterEach(() => {
    localStorage.removeItem(COLLAPSED_STORAGE_KEY);
  });

  it('renders the five primary navigation items with their labels', async () => {
    await setupAt('/user/dashboard');

    const links = fixture.nativeElement.querySelectorAll(
      '.sidebar__link'
    ) as NodeListOf<HTMLAnchorElement>;
    const labels = Array.from(links).map(
      (link) => link.querySelector('.sidebar__label')?.textContent?.trim()
    );

    expect(links).toHaveLength(5);
    expect(labels).toEqual([
      'Dashboard',
      'Gastos',
      'Gastos fijos',
      'Por pagar',
      'Configuración',
    ]);
  });

  it('marks aria-current="page" on the link that matches the active route', async () => {
    await setupAt('/user/expenses');

    const links = Array.from(
      fixture.nativeElement.querySelectorAll('.sidebar__link')
    ) as HTMLAnchorElement[];

    const activeLink = links.find(
      (link) => link.getAttribute('aria-current') === 'page'
    );
    expect(activeLink).toBeDefined();
    expect(activeLink?.getAttribute('href')).toBe('/user/expenses');

    const inactiveLinks = links.filter(
      (link) => link.getAttribute('aria-current') !== 'page'
    );
    expect(inactiveLinks.length).toBe(4);
  });

  it('applies the active class to the link of the active route', async () => {
    await setupAt('/user/fixed-expenses');

    const links = Array.from(
      fixture.nativeElement.querySelectorAll('.sidebar__link')
    ) as HTMLAnchorElement[];

    const activeLinks = links.filter((link) =>
      link.classList.contains('sidebar__link--active')
    );
    expect(activeLinks).toHaveLength(1);
    expect(activeLinks[0].getAttribute('href')).toBe('/user/fixed-expenses');
  });

  describe('collapsible state', () => {
    it('starts expanded and reflects that in aria-expanded', async () => {
      await setupAt('/user/dashboard');

      const sidebar = fixture.nativeElement.querySelector('.sidebar') as HTMLElement;
      const toggle = fixture.nativeElement.querySelector(
        '.sidebar__toggle'
      ) as HTMLButtonElement;

      expect(sidebar.classList.contains('sidebar--collapsed')).toBe(false);
      expect(toggle.getAttribute('aria-expanded')).toBe('true');
    });

    it('hides all labels visually and from assistive tech when collapsed', async () => {
      await setupAt('/user/dashboard');

      const toggle = fixture.nativeElement.querySelector(
        '.sidebar__toggle'
      ) as HTMLButtonElement;
      toggle.click();
      fixture.detectChanges();

      const sidebar = fixture.nativeElement.querySelector('.sidebar') as HTMLElement;
      const labels = Array.from(
        fixture.nativeElement.querySelectorAll('.sidebar__label')
      ) as HTMLElement[];

      expect(sidebar.classList.contains('sidebar--collapsed')).toBe(true);
      expect(toggle.getAttribute('aria-expanded')).toBe('false');
      expect(labels.length).toBeGreaterThan(0);
      labels.forEach((label) => {
        const style = window.getComputedStyle(label);
        expect(style.display).toBe('none');
        expect(label.getAttribute('aria-hidden')).toBe('true');
      });
    });

    it('toggles between expanded and collapsed on each click and persists the preference', async () => {
      await setupAt('/user/dashboard');

      const toggle = fixture.nativeElement.querySelector(
        '.sidebar__toggle'
      ) as HTMLButtonElement;
      const sidebar = fixture.nativeElement.querySelector('.sidebar') as HTMLElement;

      toggle.click();
      fixture.detectChanges();
      expect(sidebar.classList.contains('sidebar--collapsed')).toBe(true);
      expect(localStorage.getItem(COLLAPSED_STORAGE_KEY)).toBe('true');

      toggle.click();
      fixture.detectChanges();
      expect(sidebar.classList.contains('sidebar--collapsed')).toBe(false);
      expect(localStorage.getItem(COLLAPSED_STORAGE_KEY)).toBe('false');
    });

    it('hydrates the collapsed state from localStorage on initialization', async () => {
      localStorage.setItem(COLLAPSED_STORAGE_KEY, 'true');

      await setupAt('/user/dashboard');

      const sidebar = fixture.nativeElement.querySelector('.sidebar') as HTMLElement;
      const toggle = fixture.nativeElement.querySelector(
        '.sidebar__toggle'
      ) as HTMLButtonElement;

      expect(sidebar.classList.contains('sidebar--collapsed')).toBe(true);
      expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('keeps the brand-mark visible and hides the wordmark text when collapsed', async () => {
      await setupAt('/user/dashboard');

      const toggle = fixture.nativeElement.querySelector(
        '.sidebar__toggle'
      ) as HTMLButtonElement;
      toggle.click();
      fixture.detectChanges();

      const brandMark = fixture.nativeElement.querySelector(
        '.sidebar__brand-mark'
      ) as HTMLElement;
      const brandText = fixture.nativeElement.querySelector(
        '.sidebar__brand-text'
      ) as HTMLElement;
      const wordmark = brandText?.querySelector('strong');

      expect(brandMark).toBeTruthy();
      expect(brandText).toBeTruthy();
      expect(window.getComputedStyle(brandText).display).toBe('none');
      expect(wordmark?.textContent?.trim()).toBe('Totonomía');
    });
  });

  describe('menu by route context', () => {
    it('shows user menu for an admin on /user/dashboard', async () => {
      await setupAt('/user/dashboard', 'admin');

      const links = fixture.nativeElement.querySelectorAll(
        '.sidebar__link'
      ) as NodeListOf<HTMLAnchorElement>;
      const labels = Array.from(links).map(
        (link) => link.querySelector('.sidebar__label')?.textContent?.trim()
      );

      expect(links).toHaveLength(6);
      expect(labels).toEqual([
        'Dashboard',
        'Gastos',
        'Gastos fijos',
        'Por pagar',
        'Configuración',
        'Ir a Administradores',
      ]);
    });

    it('shows admin menu for an admin on /admin/dashboard', async () => {
      await setupAt('/admin/dashboard', 'admin');

      const links = fixture.nativeElement.querySelectorAll(
        '.sidebar__link'
      ) as NodeListOf<HTMLAnchorElement>;
      const labels = Array.from(links).map(
        (link) => link.querySelector('.sidebar__label')?.textContent?.trim()
      );

      expect(links).toHaveLength(4);
      expect(labels).toEqual(['Panel', 'Usuarios', 'Administradores', 'Ir a Usuario']);
    });

    it('shows user menu and hides admin menu for a regular user on /user/dashboard', async () => {
      await setupAt('/user/dashboard', 'user');

      const links = fixture.nativeElement.querySelectorAll(
        '.sidebar__link'
      ) as NodeListOf<HTMLAnchorElement>;

      expect(links).toHaveLength(5);
      expect(
        Array.from(links).some((link) =>
          link.getAttribute('href')?.startsWith('/admin/')
        )
      ).toBe(false);
    });

    it('does not show admin menu for a regular user on /admin/dashboard', async () => {
      await setupAt('/admin/dashboard', 'user');

      const links = fixture.nativeElement.querySelectorAll(
        '.sidebar__link'
      ) as NodeListOf<HTMLAnchorElement>;

      expect(links).toHaveLength(0);
    });

    it('renders the bottom navigation for an admin on a user route', async () => {
      await setupAt('/user/dashboard', 'admin');

      const bottomNav = fixture.nativeElement.querySelector('.bottom-nav');
      const bottomLinks = bottomNav?.querySelectorAll('a') ?? [];

      expect(bottomNav).toBeTruthy();
      expect(bottomLinks.length).toBe(5);
    });

    it('does not render the bottom navigation for an admin on an admin route', async () => {
      await setupAt('/admin/dashboard', 'admin');

      const bottomNav = fixture.nativeElement.querySelector('.bottom-nav');

      expect(bottomNav).toBeNull();
    });
  });

  describe('cross-links for admin users', () => {
    it('shows "Go to Administrators" link in user menu when user is admin', async () => {
      await setupAt('/user/dashboard', 'admin');

      const crossLink = fixture.nativeElement.querySelector(
        '.sidebar__link--cross'
      ) as HTMLAnchorElement;

      expect(crossLink).toBeTruthy();
      expect(crossLink.getAttribute('href')).toBe('/admin/dashboard');
      expect(crossLink.querySelector('.sidebar__label')?.textContent?.trim()).toBe(
        'Ir a Administradores'
      );
    });

    it('shows "Go to User" link in admin menu when user is admin', async () => {
      await setupAt('/admin/dashboard', 'admin');

      const crossLink = fixture.nativeElement.querySelector(
        '.sidebar__link--cross'
      ) as HTMLAnchorElement;

      expect(crossLink).toBeTruthy();
      expect(crossLink.getAttribute('href')).toBe('/user/dashboard');
      expect(crossLink.querySelector('.sidebar__label')?.textContent?.trim()).toBe(
        'Ir a Usuario'
      );
    });

    it('does not show cross-link in user menu for a regular user', async () => {
      await setupAt('/user/dashboard', 'user');

      const crossLink = fixture.nativeElement.querySelector(
        '.sidebar__link--cross'
      );

      expect(crossLink).toBeNull();
    });

    it('does not show cross-link in admin menu for a regular user', async () => {
      await setupAt('/admin/dashboard', 'user');

      const crossLink = fixture.nativeElement.querySelector(
        '.sidebar__link--cross'
      );

      expect(crossLink).toBeNull();
    });

    it('renders a divider before the cross-link in user menu', async () => {
      await setupAt('/user/dashboard', 'admin');

      const divider = fixture.nativeElement.querySelector(
        '.sidebar__divider'
      ) as HTMLElement;

      expect(divider).toBeTruthy();
      expect(divider.getAttribute('role')).toBe('separator');
    });

    it('renders a divider before the cross-link in admin menu', async () => {
      await setupAt('/admin/dashboard', 'admin');

      const divider = fixture.nativeElement.querySelector(
        '.sidebar__divider'
      ) as HTMLElement;

      expect(divider).toBeTruthy();
      expect(divider.getAttribute('role')).toBe('separator');
    });

    it('does not render a divider for a regular user', async () => {
      await setupAt('/user/dashboard', 'user');

      const divider = fixture.nativeElement.querySelector(
        '.sidebar__divider'
      );

      expect(divider).toBeNull();
    });
  });
});
