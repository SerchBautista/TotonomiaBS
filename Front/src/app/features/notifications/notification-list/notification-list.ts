import {
  ChangeDetectionStrategy,
  Component,
  OnInit,
  computed,
  inject,
  signal,
} from '@angular/core';
import { Router } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { DatePipe } from '@angular/common';

import { NotificationService } from '../../../core/services/notification.service';
import { AppNotification } from '../../../core/models/notification.model';
import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { EmptyStateComponent } from '../../../shared/empty-state/empty-state';
import { LoadingStateComponent } from '../../../shared/loading-state/loading-state';

@Component({
  selector: 'app-notification-list',
  imports: [
    TranslateModule,
    DatePipe,
    PageHeaderComponent,
    SectionPanelComponent,
    EmptyStateComponent,
    LoadingStateComponent,
  ],
  templateUrl: './notification-list.html',
  styleUrl: './notification-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NotificationListComponent implements OnInit {
  private readonly notificationService = inject(NotificationService);
  private readonly router = inject(Router);

  readonly notifications = signal<AppNotification[]>([]);
  readonly loading = signal(true);
  readonly loadingMore = signal(false);
  readonly currentPage = signal(1);
  readonly lastPage = signal(1);
  readonly loadError = signal<string | null>(null);

  readonly unread = computed(() => this.notifications().filter((n) => !n.read_at));
  readonly read = computed(() => this.notifications().filter((n) => !!n.read_at));
  readonly hasUnread = computed(() => this.unread().length > 0);
  readonly hasMore = computed(() => this.currentPage() < this.lastPage());

  ngOnInit(): void {
    this.load(1);
  }

  loadMore(): void {
    this.load(this.currentPage() + 1);
  }

  markAsRead(notification: AppNotification, event: Event): void {
    event.stopPropagation();
    if (notification.read_at) {
      return;
    }
    this.notificationService.markAsRead(notification.id).subscribe({
      next: () => {
        this.notifications.update((list) =>
          list.map((n) =>
            n.id === notification.id ? { ...n, read_at: new Date().toISOString() } : n,
          ),
        );
        this.notificationService.unreadCount.update((c) => Math.max(0, c - 1));
      },
    });
  }

  markAllAsRead(): void {
    if (!this.hasUnread()) {
      return;
    }
    this.notificationService.markAllAsRead().subscribe({
      next: () => {
        const now = new Date().toISOString();
        this.notifications.update((list) => list.map((n) => ({ ...n, read_at: n.read_at ?? now })));
        this.notificationService.unreadCount.set(0);
      },
    });
  }

  delete(notification: AppNotification, event: Event): void {
    event.stopPropagation();
    const wasUnread = !notification.read_at;
    this.notificationService.delete(notification.id).subscribe({
      next: () => {
        this.notifications.update((list) => list.filter((n) => n.id !== notification.id));
        if (wasUnread) {
          this.notificationService.unreadCount.update((c) => Math.max(0, c - 1));
        }
      },
    });
  }

  navigate(notification: AppNotification): void {
    if (!notification.read_at) {
      this.notificationService.markAsRead(notification.id).subscribe({
        next: () => {
          this.notifications.update((list) =>
            list.map((n) =>
              n.id === notification.id ? { ...n, read_at: new Date().toISOString() } : n,
            ),
          );
          this.notificationService.unreadCount.update((c) => Math.max(0, c - 1));
        },
      });
    }

    const { workspace_id } = notification.data;
    void this.router.navigate(['/user/workspaces', workspace_id, 'pending-payments']);
  }

  onItemKeydown(event: KeyboardEvent, notification: AppNotification): void {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      this.navigate(notification);
    }
  }

  private load(page: number): void {
    if (page === 1) {
      this.loading.set(true);
    } else {
      this.loadingMore.set(true);
    }
    this.loadError.set(null);

    this.notificationService.list(page).subscribe({
      next: (res) => {
        const items = page === 1 ? res.data : [...this.notifications(), ...res.data];
        this.notifications.set(items);
        this.currentPage.set(res.meta.current_page);
        this.lastPage.set(res.meta.last_page);
        this.notificationService.unreadCount.set(res.meta.unread_count);
        this.loading.set(false);
        this.loadingMore.set(false);
      },
      error: () => {
        this.loadError.set('notifications.load_error');
        this.loading.set(false);
        this.loadingMore.set(false);
      },
    });
  }
}
