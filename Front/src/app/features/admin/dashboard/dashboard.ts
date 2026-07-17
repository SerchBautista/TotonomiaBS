import {
  ChangeDetectionStrategy,
  Component,
  OnInit,
  computed,
  inject,
  signal,
} from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { catchError, of } from 'rxjs';
import { ApiService } from '../../../core/services/api';
import {
  AdminDashboardKpis,
  AdminDashboardRecentUser,
  AdminDashboardResponse,
} from '../../../core/models/admin-dashboard.model';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { SummaryHeroComponent } from '../../../shared/summary-hero/summary-hero';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';

@Component({
  selector: 'app-dashboard',
  imports: [
    DatePipe,
    DecimalPipe,
    TranslateModule,
    RouterLink,
    PageHeaderComponent,
    SectionPanelComponent,
    SummaryHeroComponent,
    DataTableComponent,
    TableCellDirective,
    StatusBadgeComponent,
    LoadingStateComponent,
  ],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardComponent implements OnInit {
  private readonly apiService = inject(ApiService);
  private readonly translate = inject(TranslateService);
  private readonly toastService = inject(ToastService);

  readonly loading = signal(false);
  readonly kpis = signal<AdminDashboardKpis | null>(null);
  readonly recentUsers = signal<AdminDashboardRecentUser[]>([]);

  readonly recentColumns = computed<TableColumn<AdminDashboardRecentUser>[]>(() => [
    { key: 'name', header: this.translate.instant('admin.users.field_name') },
    { key: 'email', header: this.translate.instant('admin.users.field_email') },
    {
      key: 'plan',
      header: this.translate.instant('admin.users.field_plan'),
      width: '140px',
    },
    {
      key: 'registered_at',
      header: this.translate.instant('admin.users.field_registered'),
      width: '160px',
    },
    {
      key: 'actions',
      header: this.translate.instant('admin.users.actions'),
      align: 'right',
      width: '120px',
    },
  ]);

  ngOnInit(): void {
    this.loading.set(true);

    this.apiService
      .get<AdminDashboardResponse>('/admin/dashboard')
      .pipe(
        catchError(() => {
          return of(null);
        }),
      )
      .subscribe((response) => {
        if (response) {
          this.kpis.set(response.data.kpis);
          this.recentUsers.set(response.data.recent_users);
        }
        this.loading.set(false);
      });
  }

  formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
      return '0';
    }
    return new Intl.NumberFormat().format(value);
  }
}
