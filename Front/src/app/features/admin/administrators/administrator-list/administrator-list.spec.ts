import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { AdministratorListComponent } from './administrator-list';
import {
  AdminAdministratorsService,
  AdministratorListResponse,
} from '../../../../core/services/admin-administrators';
import { ToastService } from '../../../../core/services/toast.service';

const mockListResponse: AdministratorListResponse = {
  message: 'ok',
  data: {
    items: [
      {
        id: 'user-uuid-1',
        name: 'Admin User',
        email: 'admin@test.com',
        roles: ['admin'],
        direct_permissions: [],
        permissions: ['admin'],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
      },
    ],
  },
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 1,
    sort_by: 'created_at',
    sort_dir: 'desc',
    search: '',
  },
};

describe('AdministratorListComponent', () => {
  let fixture: ComponentFixture<AdministratorListComponent>;
  let adminsMock: { list: ReturnType<typeof vi.fn>; delete: ReturnType<typeof vi.fn> };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  beforeEach(async () => {
    adminsMock = {
      list: vi.fn().mockReturnValue(of(mockListResponse)),
      delete: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [AdministratorListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: AdminAdministratorsService, useValue: adminsMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AdministratorListComponent);
  });

  it('should create the component', () => {
    fixture.detectChanges();
    expect(fixture.componentInstance).toBeTruthy();
  });

  it('should load administrators on init', () => {
    fixture.detectChanges();

    expect(adminsMock.list).toHaveBeenCalled();
    expect(fixture.componentInstance.rows().length).toBe(1);
  });

  it('should set loading to false after loading', () => {
    fixture.detectChanges();
    expect(fixture.componentInstance.loading()).toBe(false);
  });

  it('should have correct columns defined', () => {
    const columns = fixture.componentInstance.columns;
    expect(columns.map((c) => c.key)).toEqual([
      'id',
      'name',
      'email',
      'roles_display',
      'created_at',
    ]);
  });

  it('should format roles_display from roles array', () => {
    fixture.detectChanges();
    const row = fixture.componentInstance.rows()[0];
    expect(row['roles_display']).toBe('admin');
  });

  it('should stop loading when listing fails', () => {
    adminsMock.list.mockReturnValue(throwError(() => ({ status: 500 })));
    fixture.detectChanges();

    expect(fixture.componentInstance.loading()).toBe(false);
    expect(toastMock.error).not.toHaveBeenCalled();
  });
});
