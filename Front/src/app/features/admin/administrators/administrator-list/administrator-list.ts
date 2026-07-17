import {
  ChangeDetectionStrategy,
  Component,
  inject,
  OnDestroy,
  OnInit,
  signal,
} from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import {
  AdminAdministratorsService,
  AdministratorListMeta,
} from '../../../../core/services/admin-administrators';
import { ConfirmDialogComponent } from '../../../../shared/confirm-dialog/confirm-dialog';
import { ServerTableComponent, TableColumn } from '../../../../shared/server-table/server-table';
import { PageHeaderComponent } from '../../../../shared/page-header/page-header';
import { PageFiltersComponent } from '../../../../shared/page-filters/page-filters';
import { ToastService } from '../../../../core/services/toast.service';

@Component({
  selector: 'app-administrator-list',
  imports: [
    TranslateModule,
    RouterLink,
    ServerTableComponent,
    ConfirmDialogComponent,
    PageHeaderComponent,
    PageFiltersComponent,
  ],
  templateUrl: './administrator-list.html',
  styleUrl: './administrator-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdministratorListComponent implements OnInit, OnDestroy {
  readonly loading = signal(false);
  readonly rows = signal<Record<string, unknown>[]>([]);
  readonly search = signal('');
  readonly columns: TableColumn[] = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'roles_display', label: 'Roles', sortable: false },
    { key: 'created_at', label: 'Created', sortable: true },
  ];

  meta: AdministratorListMeta = {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    sort_by: 'created_at',
    sort_dir: 'desc',
    search: '',
  };

  confirmOpen = false;
  itemToDelete: { id: string } | null = null;
  private readonly toastService = inject(ToastService);
  private searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly searchDebounceMs = 400;

  constructor(
    private readonly administratorsService: AdminAdministratorsService,
    private readonly router: Router,
    private readonly translate: TranslateService,
  ) {}

  ngOnInit(): void {
    this.loadAdministrators();
  }

  ngOnDestroy(): void {
    this.clearSearchDebounce();
  }

  loadAdministrators(): void {
    this.loading.set(true);

    this.administratorsService
      .list({
        page: this.meta.current_page,
        perPage: this.meta.per_page,
        sortBy: this.meta.sort_by,
        sortDir: this.meta.sort_dir,
        search: this.buildSearchQuery(this.search()),
      })
      .subscribe({
        next: (response) => {
          this.meta = response.meta;
          this.rows.set(
            response.data.items.map((item) => ({
              ...item,
              roles_display: item.roles.join(', '),
              created_at: new Date(item.created_at).toLocaleDateString(),
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

  onSortChanged(sort: { sortBy: string; sortDir: 'asc' | 'desc' }): void {
    this.meta.sort_by = sort.sortBy;
    this.meta.sort_dir = sort.sortDir;
    this.meta.current_page = 1;
    this.loadAdministrators();
  }

  onPageChanged(page: number): void {
    this.meta.current_page = page;
    this.loadAdministrators();
  }

  onPerPageChanged(perPage: number): void {
    this.meta.per_page = perPage;
    this.meta.current_page = 1;
    this.loadAdministrators();
  }

  onActionClicked(event: {
    action: 'view' | 'edit' | 'delete';
    row: Record<string, unknown>;
  }): void {
    const administratorId = event.row['id'] as string;

    if (event.action === 'view') {
      void this.router.navigateByUrl(`/admin/administrators/${administratorId}`);
      return;
    }

    if (event.action === 'edit') {
      void this.router.navigateByUrl(`/admin/administrators/${administratorId}/edit`);
      return;
    }

    this.itemToDelete = { id: administratorId };
    this.confirmOpen = true;
  }

  cancelDelete(): void {
    this.confirmOpen = false;
    this.itemToDelete = null;
  }

  confirmDelete(): void {
    if (!this.itemToDelete) {
      return;
    }

    this.administratorsService.delete(this.itemToDelete.id).subscribe({
      next: () => {
        this.confirmOpen = false;
        this.itemToDelete = null;
        this.toastService.success(this.translate.instant('administrators.deleted_ok'));
        this.loadAdministrators();
      },
      error: () => {
        this.confirmOpen = false;
        this.itemToDelete = null;
      },
    });
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
    this.loadAdministrators();
  }

  private buildSearchQuery(term: string): string {
    const normalized = term.trim();
    if (!normalized) {
      return '';
    }

    return normalized.includes('%') ? normalized : `%${normalized}%`;
  }
}
