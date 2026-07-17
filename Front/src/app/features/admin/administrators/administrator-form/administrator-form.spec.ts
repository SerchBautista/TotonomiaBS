import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { AdministratorFormComponent } from './administrator-form';
import {
  AdminAdministratorsService,
  AdministratorItem,
} from '../../../../core/services/admin-administrators';
import { ToastService } from '../../../../core/services/toast.service';

const mockItem: AdministratorItem = {
  id: 'user-uuid-1',
  name: 'Admin User',
  email: 'admin@test.com',
  roles: ['admin'],
  direct_permissions: ['view-dashboard'],
  permissions: ['view-dashboard'],
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
};

const mockOptions = {
  data: {
    roles: ['admin', 'support'],
    permissions: ['view-dashboard', 'edit-users'],
  },
};

describe('AdministratorFormComponent', () => {
  let fixture: ComponentFixture<AdministratorFormComponent>;
  let component: AdministratorFormComponent;
  let adminsMock: {
    options: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    getById: ReturnType<typeof vi.fn>;
  };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  async function setup(options?: {
    mode?: 'create' | 'edit' | 'view';
    id?: string | null;
    optionsResponse?: ReturnType<typeof vi.fn>;
    createResponse?: ReturnType<typeof vi.fn>;
    updateResponse?: ReturnType<typeof vi.fn>;
  }) {
    TestBed.resetTestingModule();

    adminsMock = {
      options: options?.optionsResponse ?? vi.fn().mockReturnValue(of(mockOptions)),
      create: options?.createResponse ?? vi.fn().mockReturnValue(of({ data: mockItem })),
      update: options?.updateResponse ?? vi.fn().mockReturnValue(of({ data: mockItem })),
      getById: vi.fn().mockReturnValue(of({ data: { item: mockItem } })),
    };

    await TestBed.configureTestingModule({
      imports: [AdministratorFormComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: AdminAdministratorsService, useValue: adminsMock },
        { provide: ToastService, useValue: toastMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: {
                get: (key: string) => (key === 'id' ? (options?.id ?? null) : null),
              },
              data: { mode: options?.mode ?? 'create' },
            },
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AdministratorFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  }

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should create the component in create mode and load options', async () => {
    await setup();
    expect(component).toBeTruthy();
    expect(component.mode).toBe('create');
    expect(adminsMock.options).toHaveBeenCalled();
    expect(component.availableRoles()).toEqual(['admin', 'support']);
    expect(component.availablePermissions()).toEqual(['view-dashboard', 'edit-users']);
  });

  it('should block submit when admin role is missing', async () => {
    await setup();
    setFormValue(component, {
      name: 'Test',
      email: 'test@example.com',
      password: 'password123',
      password_confirmation: 'password123',
      roles: ['support'],
      permissions: [],
    });
    component.submit();
    expect(toastMock.error).toHaveBeenCalled();
    expect(adminsMock.create).not.toHaveBeenCalled();
  });

  it('should not show local toast when options endpoint fails', async () => {
    await setup({ optionsResponse: vi.fn().mockReturnValue(throwError(() => ({ status: 500 }))) });
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should call create with the expected payload in create mode', async () => {
    await setup();
    setFormValue(component, {
      name: 'New Admin',
      email: 'new@example.com',
      password: 'password123',
      password_confirmation: 'password123',
      roles: ['admin'],
      permissions: ['view-dashboard'],
    });
    component.submit();
    expect(adminsMock.create).toHaveBeenCalledWith({
      name: 'New Admin',
      email: 'new@example.com',
      password: 'password123',
      password_confirmation: 'password123',
      roles: ['admin'],
      permissions: ['view-dashboard'],
    });
  });

  it('should toggle role and prevent removing admin', async () => {
    await setup();
    component.toggleRole('admin', false);
    expect(component.hasRole('admin')).toBe(true);
    expect(toastMock.error).toHaveBeenCalled();
  });

  it('should toggle role and add support when allowed', async () => {
    await setup();
    component.toggleRole('support', true);
    expect(component.hasRole('support')).toBe(true);
  });

  // -------------------------------------------------------------------------
  // Phase 5 — L-3: view-mode submit gating and admin-role rollback guard
  // -------------------------------------------------------------------------

  it('should not call create or update when submit() is invoked in view mode', async () => {
    await setup({ mode: 'view', id: 'user-uuid-1' });

    expect(component.mode).toBe('view');
    expect((component as unknown as { form: { disabled: boolean } }).form.disabled).toBe(true);

    component.submit();

    expect(adminsMock.create).not.toHaveBeenCalled();
    expect(adminsMock.update).not.toHaveBeenCalled();
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should reject toggleRole("admin", false) client-side: show toast and keep admin role', async () => {
    await setup();

    expect(component.hasRole('admin')).toBe(true);

    component.toggleRole('admin', false);

    expect(toastMock.error).toHaveBeenCalledTimes(1);
    expect(component.hasRole('admin')).toBe(true);
  });
});

function setFormValue(
  component: AdministratorFormComponent,
  value: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles: string[];
    permissions: string[];
  },
): void {
  (component as unknown as { form: { setValue: (v: typeof value) => void } }).form.setValue(value);
}
