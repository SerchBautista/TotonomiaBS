import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, ActivatedRoute, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { UserDetailComponent } from './user-detail';
import { AdminUsersService } from '../../../../core/services/admin-users.service';
import { AuthStateService } from '../../../../core/services/auth-state.service';
import { ToastService } from '../../../../core/services/toast.service';

describe('UserDetailComponent', () => {
  let fixture: ComponentFixture<UserDetailComponent>;
  let usersServiceMock: { get: ReturnType<typeof vi.fn>; assignPlan: ReturnType<typeof vi.fn> };
  let toastServiceMock: { error: ReturnType<typeof vi.fn>; success: ReturnType<typeof vi.fn> };
  let authStateMock: { hasPermission: ReturnType<typeof vi.fn>; permissions: ReturnType<typeof vi.fn> };

  const mockUser = {
    id: '1',
    name: 'Alice',
    email: 'alice@example.com',
    plan: 'premium',
    subscription_ends_at: '2027-01-01T00:00:00Z',
    email_verified_at: '2026-01-01T00:00:00Z',
    has_active_subscription: true,
    registered_at: '2026-06-01T00:00:00Z',
    workspaces_owned_count: 3,
  };

  beforeEach(async () => {
    usersServiceMock = {
      get: vi.fn().mockReturnValue(of({ message: 'ok', data: { item: mockUser } })),
      assignPlan: vi.fn().mockReturnValue(of({ message: 'ok', data: { item: { ...mockUser, plan: 'free' } } })),
    };

    toastServiceMock = {
      error: vi.fn(),
      success: vi.fn(),
    };

    authStateMock = {
      hasPermission: vi.fn().mockReturnValue(false),
      permissions: vi.fn().mockReturnValue([]),
    };

    await TestBed.configureTestingModule({
      imports: [UserDetailComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: AdminUsersService, useValue: usersServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
        { provide: AuthStateService, useValue: authStateMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: { paramMap: { get: vi.fn().mockReturnValue('1') } },
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(UserDetailComponent);
  });

  beforeEach(() => {
    vi.clearAllMocks();
    usersServiceMock.get.mockReturnValue(of({ message: 'ok', data: { item: mockUser } }));
    usersServiceMock.assignPlan.mockReturnValue(of({ message: 'ok', data: { item: { ...mockUser, plan: 'free' } } }));
    authStateMock.hasPermission.mockReturnValue(false);
  });

  it('should create the component', () => {
    fixture.detectChanges();
    expect(fixture.componentInstance).toBeTruthy();
  });

  it('should load user details on init', () => {
    fixture.detectChanges();

    expect(usersServiceMock.get).toHaveBeenCalledWith('1', expect.any(Object));
    expect(fixture.componentInstance.user()).toEqual(mockUser);
  });

  it('should display user info in the template', () => {
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Alice');
    expect(compiled.textContent).toContain('alice@example.com');
    expect(compiled.textContent).toContain('premium');
    expect(compiled.textContent).toContain('3');
  });

  it('should display verified date when email is verified', () => {
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('2026');
  });

  it('should stop loading when service fails', () => {
    usersServiceMock.get.mockReturnValue(throwError(() => ({ status: 500 })));
    fixture.detectChanges();

    expect(toastServiceMock.error).not.toHaveBeenCalled();
    expect(fixture.componentInstance.loading()).toBe(false);
  });

  it('should render the page header with the user name and email', () => {
    fixture.detectChanges();

    const header = fixture.nativeElement.querySelector('app-page-header');
    expect(header).toBeTruthy();
    expect(header.textContent).toContain('Alice');
    expect(header.textContent).toContain('alice@example.com');
  });

  // -------------------------------------------------------------------------
  // Phase 5 — L-2: 404 should redirect back to the user list with a toast
  // -------------------------------------------------------------------------

  it('should redirect to /admin/users and show a toast when the user is not found', () => {
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    usersServiceMock.get.mockReturnValue(throwError(() => ({ status: 404 })));

    fixture.detectChanges();

    expect(toastServiceMock.error).toHaveBeenCalledTimes(1);
    expect(navigateSpy).toHaveBeenCalledWith('/admin/users');
    expect(fixture.componentInstance.loading()).toBe(false);
  });

  // -------------------------------------------------------------------------
  // H-003 Part 2 — Assign plan button visibility and behavior
  // -------------------------------------------------------------------------

  it('should NOT show the assign plan button when user lacks the permission', () => {
    authStateMock.hasPermission.mockReturnValue(false);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const assignBtn = compiled.querySelector('button.btn.primary');
    expect(assignBtn).toBeNull();
  });

  it('should show the assign plan button when user has users.assign-plan permission', () => {
    authStateMock.hasPermission.mockReturnValue(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const assignBtn = compiled.querySelector('button.btn.primary');
    expect(assignBtn).toBeTruthy();
    expect(assignBtn?.textContent).toContain('admin.users.assign_plan');
  });

  it('should open the modal when clicking the assign plan button', () => {
    authStateMock.hasPermission.mockReturnValue(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const assignBtn = compiled.querySelector('button.btn.primary') as HTMLButtonElement;
    assignBtn.click();

    expect(fixture.componentInstance.assignPlanModalOpen()).toBe(true);
  });

  it('should call assignPlan service and reload user on confirm', () => {
    authStateMock.hasPermission.mockReturnValue(true);
    fixture.detectChanges();

    fixture.componentInstance.selectPlan('free');
    fixture.componentInstance.confirmAssignPlan();

    expect(usersServiceMock.assignPlan).toHaveBeenCalledWith('1', 'free', expect.any(Object));
    expect(toastServiceMock.success).toHaveBeenCalled();
    expect(fixture.componentInstance.assignPlanModalOpen()).toBe(false);
    // Should reload user after assign
    expect(usersServiceMock.get).toHaveBeenCalledTimes(2); // initial + reload
  });

  it('should show error toast on assignPlan 403', () => {
    usersServiceMock.assignPlan.mockReturnValue(throwError(() => ({ status: 403 })));
    fixture.detectChanges();

    fixture.componentInstance.selectPlan('premium');
    fixture.componentInstance.confirmAssignPlan();

    expect(toastServiceMock.error).toHaveBeenCalledTimes(1);
    expect(fixture.componentInstance.assigningPlan()).toBe(false);
  });

  it('should show error toast on assignPlan 404', () => {
    usersServiceMock.assignPlan.mockReturnValue(throwError(() => ({ status: 404 })));
    fixture.detectChanges();

    fixture.componentInstance.confirmAssignPlan();

    expect(toastServiceMock.error).toHaveBeenCalledTimes(1);
  });

  it('should show error toast on assignPlan 422', () => {
    usersServiceMock.assignPlan.mockReturnValue(throwError(() => ({ status: 422 })));
    fixture.detectChanges();

    fixture.componentInstance.confirmAssignPlan();

    expect(toastServiceMock.error).toHaveBeenCalledTimes(1);
  });
});
