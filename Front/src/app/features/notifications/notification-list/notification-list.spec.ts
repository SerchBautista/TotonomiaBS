import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { Subject, of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { NotificationService } from '../../../core/services/notification.service';
import { AppNotification, NotificationListResponse } from '../../../core/models/notification.model';
import { NotificationListComponent } from './notification-list';

function buildNotification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: overrides.id ?? 'n-1',
    data: {
      type: 'fixed_expense_processed',
      title: 'Netflix',
      amount: '15.99',
      due_date: '2026-06-20',
      fixed_expense_id: 'fx-1',
      occurrence_id: 'oc-1',
      workspace_id: 'ws-1',
      ...((overrides as any).data ?? {}),
    },
    read_at: overrides.read_at ?? null,
    created_at: overrides.created_at ?? '2026-06-10T12:00:00Z',
  };
}

function buildResponse(
  notifications: AppNotification[],
  overrides: Partial<NotificationListResponse['meta']> = {},
): NotificationListResponse {
  return {
    data: notifications,
    meta: {
      current_page: overrides.current_page ?? 1,
      last_page: overrides.last_page ?? 1,
      total: overrides.total ?? notifications.length,
      unread_count: overrides.unread_count ?? notifications.filter((n) => !n.read_at).length,
    },
  };
}

describe('NotificationListComponent', () => {
  let fixture: ComponentFixture<NotificationListComponent>;
  let component: NotificationListComponent;
  let listSpy: ReturnType<typeof vi.fn>;
  let markAsReadSpy: ReturnType<typeof vi.fn>;
  let markAllAsReadSpy: ReturnType<typeof vi.fn>;
  let deleteSpy: ReturnType<typeof vi.fn>;
  let unreadCountSignal: { set: ReturnType<typeof vi.fn>; update: ReturnType<typeof vi.fn> };

  function configureListMock(response: NotificationListResponse | Error) {
    if (response instanceof Error) {
      listSpy.mockReturnValue(throwError(() => response));
    } else {
      listSpy.mockReturnValue(of(response));
    }
  }

  beforeEach(() => {
    vi.clearAllMocks();

    listSpy = vi.fn();
    markAsReadSpy = vi.fn().mockReturnValue(of({ message: 'ok' }));
    markAllAsReadSpy = vi.fn().mockReturnValue(of({ message: 'ok' }));
    deleteSpy = vi.fn().mockReturnValue(of(undefined));
    unreadCountSignal = { set: vi.fn(), update: vi.fn() };

    TestBed.configureTestingModule({
      imports: [NotificationListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: NotificationService,
          useValue: {
            list: listSpy,
            markAsRead: markAsReadSpy,
            markAllAsRead: markAllAsReadSpy,
            delete: deleteSpy,
            unreadCount: unreadCountSignal,
          },
        },
      ],
    });

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        notifications: {
          title: 'Notificaciones',
          loading: 'Cargando...',
          load_error: 'No pudimos cargar tus notificaciones.',
          empty: 'Aún no tienes notificaciones.',
          unread: 'Sin leer',
          read: 'Leídas',
          new_payment: 'Nuevo pago',
          mark_all_read: 'Marcar todo como leído',
          mark_read: 'Marcar como leído',
          delete: 'Eliminar',
          load_more: 'Cargar más',
          amount_due: '${{ amount }} · Vence {{ date }}',
          amount_only: '${{ amount }}',
        },
      },
      true,
    );
    translate.use('es');

    fixture = TestBed.createComponent(NotificationListComponent);
    component = fixture.componentInstance;
  });

  it('should load the first page on init and expose notifications', () => {
    const notif = buildNotification();
    configureListMock(buildResponse([notif], { unread_count: 1 }));

    fixture.detectChanges();

    expect(listSpy).toHaveBeenCalledWith(1);
    expect(component.notifications().length).toBe(1);
    expect(component.unread().length).toBe(1);
    expect(component.read().length).toBe(0);
    expect(component.hasUnread()).toBe(true);
    expect(component.loading()).toBe(false);
    expect(unreadCountSignal.set).toHaveBeenCalledWith(1);
  });

  it('should split notifications into unread and read groups', () => {
    const unread = buildNotification({ id: 'u-1' });
    const read = buildNotification({ id: 'r-1', read_at: '2026-06-09T10:00:00Z' });
    configureListMock(buildResponse([unread, read], { unread_count: 1 }));

    fixture.detectChanges();

    expect(component.unread().map((n) => n.id)).toEqual(['u-1']);
    expect(component.read().map((n) => n.id)).toEqual(['r-1']);
  });

  it('should set loadError when the request fails', () => {
    listSpy.mockReturnValue(new Subject());
    configureListMock(new Error('boom'));

    fixture.detectChanges();

    expect(component.loadError()).toBe('notifications.load_error');
    expect(component.loading()).toBe(false);
  });

  it('should call markAsRead service method when marking a single notification', () => {
    const notif = buildNotification();
    configureListMock(buildResponse([notif], { unread_count: 1 }));

    fixture.detectChanges();
    component.markAsRead(notif, new Event('click'));

    expect(markAsReadSpy).toHaveBeenCalledWith(notif.id);
  });

  it('should not call markAsRead when the notification is already read', () => {
    const notif = buildNotification({ id: 'r-1', read_at: '2026-06-09T10:00:00Z' });
    configureListMock(buildResponse([notif], { unread_count: 0 }));

    fixture.detectChanges();
    component.markAsRead(notif, new Event('click'));

    expect(markAsReadSpy).not.toHaveBeenCalled();
  });

  it('should mark all notifications as read', () => {
    const u1 = buildNotification({ id: 'u-1' });
    const u2 = buildNotification({ id: 'u-2' });
    configureListMock(buildResponse([u1, u2], { unread_count: 2 }));

    fixture.detectChanges();
    component.markAllAsRead();

    expect(markAllAsReadSpy).toHaveBeenCalledOnce();
    expect(unreadCountSignal.set).toHaveBeenCalledWith(0);
  });

  it('should delete a notification and decrease unread count when it was unread', () => {
    const notif = buildNotification();
    configureListMock(buildResponse([notif], { unread_count: 1 }));

    fixture.detectChanges();
    component.delete(notif, new Event('click'));

    expect(deleteSpy).toHaveBeenCalledWith(notif.id);
  });

  it('should expose hasMore flag when there is a next page', () => {
    configureListMock(
      buildResponse([buildNotification()], { current_page: 1, last_page: 3, unread_count: 1 }),
    );

    fixture.detectChanges();

    expect(component.hasMore()).toBe(true);
  });

  it('should render empty state when there are no notifications', () => {
    configureListMock(buildResponse([]));

    fixture.detectChanges();

    const emptyState = fixture.nativeElement.querySelector('app-empty-state');
    expect(emptyState).toBeTruthy();
  });

  it('should render loading state while loading', () => {
    listSpy.mockReturnValue(new Subject());

    fixture.detectChanges();

    const loading = fixture.nativeElement.querySelector('app-loading-state');
    expect(loading).toBeTruthy();
  });

  it('should render due date when notification has due_date', () => {
    const notif = buildNotification({ id: 'with-date' });
    configureListMock(buildResponse([notif], { unread_count: 1 }));

    fixture.detectChanges();

    const desc = fixture.nativeElement.querySelector('.notification-item__desc');
    expect(desc.textContent).toContain('Vence');
  });

  it('should NOT render due date when notification has empty due_date', () => {
    const notif = buildNotification({ id: 'no-date', data: { due_date: '' } } as any);
    configureListMock(buildResponse([notif], { unread_count: 1 }));

    fixture.detectChanges();

    const desc = fixture.nativeElement.querySelector('.notification-item__desc');
    expect(desc.textContent).not.toContain('Vence');
    expect(desc.textContent).toContain('15.99');
  });
});
