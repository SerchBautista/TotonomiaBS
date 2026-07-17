import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { DashboardComponent } from './dashboard';
import { ApiService } from '../../../core/services/api';
import { ToastService } from '../../../core/services/toast.service';

describe('DashboardComponent (admin)', () => {
  let fixture: ComponentFixture<DashboardComponent>;
  let apiMock: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  const mockResponse = {
    message: 'ok',
    data: {
      kpis: {
        users_total: 150,
        users_registered_today: 5,
        users_registered_week: 23,
        email_pending_verification: 8,
        premium_active_total: 42,
      },
      recent_users: [
        {
          id: '1',
          name: 'Alice',
          email: 'alice@example.com',
          plan: 'premium',
          registered_at: '2026-06-15T10:00:00Z',
        },
        {
          id: '2',
          name: 'Bob',
          email: 'bob@example.com',
          plan: 'free',
          registered_at: '2026-06-14T08:00:00Z',
        },
      ],
    },
  };

  beforeEach(async () => {
    apiMock = {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ApiService, useValue: apiMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
  });

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should create the component', () => {
    apiMock.get.mockReturnValue(of(mockResponse));
    fixture.detectChanges();
    expect(fixture.componentInstance).toBeTruthy();
  });

  it('should load KPIs on init', () => {
    apiMock.get.mockReturnValue(of(mockResponse));
    fixture.detectChanges();

    expect(apiMock.get).toHaveBeenCalledWith('/admin/dashboard');
    expect(fixture.componentInstance.kpis()).toEqual(mockResponse.data.kpis);
  });

  it('should load recent users on init', () => {
    apiMock.get.mockReturnValue(of(mockResponse));
    fixture.detectChanges();

    expect(fixture.componentInstance.recentUsers()).toEqual(mockResponse.data.recent_users);
    expect(fixture.componentInstance.recentUsers().length).toBe(2);
  });

  it('should display KPI values in the template', () => {
    apiMock.get.mockReturnValue(of(mockResponse));
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('150');
    expect(compiled.textContent).toContain('5');
    expect(compiled.textContent).toContain('23');
    expect(compiled.textContent).toContain('8');
    expect(compiled.textContent).toContain('42');
  });

  it('should display recent users in the table', () => {
    apiMock.get.mockReturnValue(of(mockResponse));
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Alice');
    expect(compiled.textContent).toContain('alice@example.com');
    expect(compiled.textContent).toContain('Bob');
    expect(compiled.textContent).toContain('bob@example.com');
  });

  it('should handle API error gracefully', () => {
    apiMock.get.mockReturnValue(of(null));
    fixture.detectChanges();

    expect(fixture.componentInstance.kpis()).toBeNull();
    expect(fixture.componentInstance.recentUsers()).toEqual([]);
  });

  it('should stop loading when the dashboard request fails', () => {
    apiMock.get.mockReturnValue(throwError(() => ({ status: 500 })));
    fixture.detectChanges();

    expect(toastMock.error).not.toHaveBeenCalled();
    expect(fixture.componentInstance.loading()).toBe(false);
    expect(fixture.componentInstance.kpis()).toBeNull();
  });
});
