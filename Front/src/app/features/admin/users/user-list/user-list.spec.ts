import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { UserListComponent } from './user-list';
import { AdminUsersService } from '../../../../core/services/admin-users.service';
import { ToastService } from '../../../../core/services/toast.service';

describe('UserListComponent', () => {
  let fixture: ComponentFixture<UserListComponent>;
  let usersServiceMock: { list: ReturnType<typeof vi.fn> };
  let toastServiceMock: { error: ReturnType<typeof vi.fn>; success: ReturnType<typeof vi.fn> };

  const mockResponse = {
    message: 'ok',
    data: {
      items: [
        {
          id: '1',
          name: 'Alice',
          email: 'alice@example.com',
          plan: 'premium',
          subscription_ends_at: '2027-01-01T00:00:00Z',
          email_verified_at: '2026-01-01T00:00:00Z',
          has_active_subscription: true,
          registered_at: '2026-06-01T00:00:00Z',
        },
        {
          id: '2',
          name: 'Bob',
          email: 'bob@example.com',
          plan: 'free',
          subscription_ends_at: null,
          email_verified_at: null,
          has_active_subscription: false,
          registered_at: '2026-06-10T00:00:00Z',
        },
      ],
    },
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 2,
      sort_by: 'registered_at',
      sort_dir: 'desc' as const,
      search: '',
    },
  };

  beforeEach(async () => {
    usersServiceMock = {
      list: vi.fn().mockReturnValue(of(mockResponse)),
    };

    toastServiceMock = {
      error: vi.fn(),
      success: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [UserListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: AdminUsersService, useValue: usersServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(UserListComponent);
  });

  it('should create the component', () => {
    fixture.detectChanges();
    expect(fixture.componentInstance).toBeTruthy();
  });

  it('should load users on init', () => {
    fixture.detectChanges();

    expect(usersServiceMock.list).toHaveBeenCalledTimes(1);
    expect(fixture.componentInstance.rows().length).toBe(2);
  });

  it('should map user data to table rows', () => {
    fixture.detectChanges();

    const rows = fixture.componentInstance.rows();
    expect(rows[0]['name']).toBe('Alice');
    expect(rows[0]['email']).toBe('alice@example.com');
    expect(rows[0]['plan']).toBe('premium');
    expect(rows[1]['name']).toBe('Bob');
    expect(rows[1]['plan']).toBe('free');
  });

  it('should update meta from response', () => {
    fixture.detectChanges();

    expect(fixture.componentInstance.meta.total).toBe(2);
    expect(fixture.componentInstance.meta.current_page).toBe(1);
    expect(fixture.componentInstance.meta.last_page).toBe(1);
  });

  it('should display users in the template', () => {
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Alice');
    expect(compiled.textContent).toContain('alice@example.com');
    expect(compiled.textContent).toContain('Bob');
    expect(compiled.textContent).toContain('bob@example.com');
  });

  it('should call service with search query when search is applied', () => {
    fixture.detectChanges();
    usersServiceMock.list.mockClear();

    fixture.componentInstance.search.set('Alice');
    fixture.componentInstance.applySearch();

    expect(usersServiceMock.list).toHaveBeenCalledWith(
      expect.objectContaining({ search: '%Alice%' }),
    );
  });

  it('should call service with plan filter when changed', () => {
    fixture.detectChanges();
    usersServiceMock.list.mockClear();

    const event = { target: { value: 'premium' } } as unknown as Event;
    fixture.componentInstance.onPlanFilterChange(event);

    expect(usersServiceMock.list).toHaveBeenCalledWith(
      expect.objectContaining({ plan: 'premium' }),
    );
  });

  it('should reset page to 1 when search changes', () => {
    fixture.detectChanges();
    usersServiceMock.list.mockClear();

    fixture.componentInstance.meta.current_page = 3;
    fixture.componentInstance.search.set('test');
    fixture.componentInstance.applySearch();

    expect(usersServiceMock.list).toHaveBeenCalledWith(expect.objectContaining({ page: 1 }));
  });

  it('should stop loading when service fails', () => {
    usersServiceMock.list.mockReturnValue(throwError(() => ({ status: 500 })));
    fixture.detectChanges();

    expect(fixture.componentInstance.loading()).toBe(false);
    expect(toastServiceMock.error).not.toHaveBeenCalled();
  });

  it('should navigate to user detail when view action is clicked', () => {
    fixture.detectChanges();
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    fixture.componentInstance.onActionClicked({
      action: 'view',
      row: { id: '1' } as Record<string, unknown>,
    });

    expect(navigateSpy).toHaveBeenCalledWith('/admin/users/1');
  });
});
