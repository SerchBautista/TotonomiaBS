import {
  ChangeDetectionStrategy,
  Component,
  inject,
  OnDestroy,
  OnInit,
  signal,
} from '@angular/core';
import { Router } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { AdminUsersService } from '../../../../core/services/admin-users.service';
import { PaginationMeta } from '../../../../core/models/admin-user.model';
import { ServerTableComponent, TableColumn } from '../../../../shared/server-table/server-table';
import { PageHeaderComponent } from '../../../../shared/page-header/page-header';
import { PageFiltersComponent } from '../../../../shared/page-filters/page-filters';
import { ToastService } from '../../../../core/services/toast.service';

@Component({
  selector: 'app-user-list',
  imports: [TranslateModule, ServerTableComponent, PageHeaderComponent, PageFiltersComponent],
  templateUrl: './user-list.html',
  styleUrl: './user-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserListComponent implements OnInit, OnDestroy {
  private readonly usersService = inject(AdminUsersService);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);

  readonly loading = signal(false);
  readonly rows = signal<Record<string, unknown>[]>([]);
  readonly search = signal('');
  readonly planFilter = signal('');
  readonly emailVerifiedFilter = signal('');

  readonly columns: TableColumn[] = [
    { key: 'name', label: 'admin.users.field_name', sortable: true },
    { key: 'email', label: 'admin.users.field_email', sortable: true },
    { key: 'plan', label: 'admin.users.field_plan', sortable: true },
    { key: 'subscription_ends_at', label: 'admin.users.field_subscription_ends', sortable: true },
    { key: 'email_verified_at', label: 'admin.users.field_email_verified', sortable: true },
    { key: 'registered_at', label: 'admin.users.field_registered', sortable: true },
  ];

  meta: PaginationMeta = {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    sort_by: 'registered_at',
    sort_dir: 'desc',
    search: '',
  };

  private searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly searchDebounceMs = 400;

  ngOnInit(): void {
    this.loadUsers();
  }

  ngOnDestroy(): void {
    this.clearSearchDebounce();
  }

  loadUsers(): void {
    this.loading.set(true);

    this.usersService
      .list({
        page: this.meta.current_page,
        perPage: this.meta.per_page,
        sortBy: this.meta.sort_by,
        sortDir: this.meta.sort_dir,
        search: this.buildSearchQuery(this.search()),
        plan: this.planFilter() || undefined,
        emailVerified: this.emailVerifiedFilter() || undefined,
      })
      .subscribe({
        next: (response) => {
          this.meta = response.meta;
          this.rows.set(
            response.data.items.map((item) => ({
              ...item,
              subscription_ends_at: item.subscription_ends_at
                ? new Date(item.subscription_ends_at).toLocaleDateString()
                : this.translate.instant('admin.users.no_subscription'),
              email_verified_at: item.email_verified_at
                ? new Date(item.email_verified_at).toLocaleDateString()
                : this.translate.instant('admin.users.pending_verification'),
              registered_at: new Date(item.registered_at).toLocaleDateString(),
            })),
          );
          this.loading.set(false);
        },
        error: () => {
          this.loading.set(false);
        },
      });
  }

  onSearchInput(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
    this.scheduleSearch();
  }

  onSearchEnter(event: Event): void {
    event.preventDefault();
    this.applySearch();
  }

  applySearch(): void {
    this.search.set(this.search().trim());
    this.clearSearchDebounce();
    this.executeSearch();
  }

  onPlanFilterChange(event: Event): void {
    this.planFilter.set((event.target as HTMLSelectElement).value);
    this.meta.current_page = 1;
    this.loadUsers();
  }

  onEmailVerifiedFilterChange(event: Event): void {
    this.emailVerifiedFilter.set((event.target as HTMLSelectElement).value);
    this.meta.current_page = 1;
    this.loadUsers();
  }

  onSortChanged(sort: { sortBy: string; sortDir: 'asc' | 'desc' }): void {
    this.meta.sort_by = sort.sortBy;
    this.meta.sort_dir = sort.sortDir;
    this.meta.current_page = 1;
    this.loadUsers();
  }

  onPageChanged(page: number): void {
    this.meta.current_page = page;
    this.loadUsers();
  }

  onPerPageChanged(perPage: number): void {
    this.meta.per_page = perPage;
    this.meta.current_page = 1;
    this.loadUsers();
  }

  onActionClicked(event: {
    action: 'view' | 'edit' | 'delete';
    row: Record<string, unknown>;
  }): void {
    const userId = event.row['id'] as string;

    if (event.action === 'view') {
      void this.router.navigateByUrl(`/admin/users/${userId}`);
      return;
    }
  }

  private scheduleSearch(): void {
    this.clearSearchDebounce();
    this.searchDebounceTimer = setTimeout(() => {
      this.searchDebounceTimer = null;
      this.executeSearch();
    }, this.searchDebounceMs);
  }

  private clearSearchDebounce(): void {
    if (this.searchDebounceTimer) {
      clearTimeout(this.searchDebounceTimer);
      this.searchDebounceTimer = null;
    }
  }

  private executeSearch(): void {
    this.meta.current_page = 1;
    this.loadUsers();
  }

  private buildSearchQuery(term: string): string {
    const normalized = term.trim();
    if (!normalized) {
      return '';
    }

    return normalized.includes('%') ? normalized : `%${normalized}%`;
  }
}
