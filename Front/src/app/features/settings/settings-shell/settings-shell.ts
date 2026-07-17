import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterOutlet, NavigationEnd } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { filter } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';

interface SettingsModule {
  id: string;
  icon: string;
  labelKey: string;
  descKey: string;
  path: string;
}

@Component({
  selector: 'app-settings-shell',
  imports: [RouterLink, RouterOutlet, TranslateModule, PageHeaderComponent, SectionPanelComponent],
  templateUrl: './settings-shell.html',
  styleUrl: './settings-shell.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SettingsShellComponent {
  private readonly router = inject(Router);

  readonly modules: SettingsModule[] = [
    {
      id: 'workspaces',
      icon: 'fa-layer-group',
      labelKey: 'settings.workspaces',
      descKey: 'settings.workspaces_desc',
      path: 'workspaces',
    },
    {
      id: 'categories',
      icon: 'fa-tags',
      labelKey: 'settings.categories',
      descKey: 'settings.categories_desc',
      path: 'categories',
    },
    {
      id: 'payment-methods',
      icon: 'fa-dollar-sign',
      labelKey: 'settings.payment_methods',
      descKey: 'settings.payment_methods_desc',
      path: 'payment-methods',
    },
    {
      id: 'budgets',
      icon: 'fa-chart-pie',
      labelKey: 'settings.budgets',
      descKey: 'settings.budgets_desc',
      path: 'budgets',
    },
  ];

  readonly isOverview = signal(this.checkIsOverview());
  readonly activeModule = signal<SettingsModule | null>(this.getActiveModule());

  constructor() {
    this.router.events
      .pipe(
        filter((e): e is NavigationEnd => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => {
        this.isOverview.set(this.checkIsOverview());
        this.activeModule.set(this.getActiveModule());
      });
  }

  private checkIsOverview(): boolean {
    const url = this.router.url.split('?')[0];
    return url === '/user/settings' || url === '/user/settings/';
  }

  private getActiveModule(): SettingsModule | null {
    const url = this.router.url.split('?')[0];
    return this.modules.find((m) => url.startsWith(`/user/settings/${m.path}`)) ?? null;
  }

  navigateTo(path: string): void {
    void this.router.navigate(['/user/settings', path]);
  }
}
