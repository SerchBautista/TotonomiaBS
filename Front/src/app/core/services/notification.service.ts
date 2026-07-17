import { Injectable, inject, signal } from '@angular/core';
import { interval } from 'rxjs';
import { ApiService } from './api';
import { AppNotification, NotificationListResponse } from '../models/notification.model';

const POLL_INTERVAL_MS = 60_000;

@Injectable({ providedIn: 'root' })
export class NotificationService {
  private readonly api = inject(ApiService);
  private pollingStarted = false;

  readonly unreadCount = signal(0);

  startPolling(): void {
    if (this.pollingStarted) return;
    this.pollingStarted = true;
    this.fetchUnreadCount();
    interval(POLL_INTERVAL_MS).subscribe(() => this.fetchUnreadCount());
  }

  fetchUnreadCount(): void {
    this.api.get<{ count: number }>('/notifications/unread-count').subscribe({
      next: (res) => this.unreadCount.set(res.count),
      error: () => {}
    });
  }

  list(page = 1) {
    return this.api.get<NotificationListResponse>(`/notifications?page=${page}`);
  }

  markAsRead(id: string) {
    return this.api.patch<{ message: string }>(`/notifications/${id}/read`, {});
  }

  markAllAsRead() {
    return this.api.patch<{ message: string }>('/notifications/read-all', {});
  }

  delete(id: string) {
    return this.api.delete<void>(`/notifications/${id}`);
  }
}
