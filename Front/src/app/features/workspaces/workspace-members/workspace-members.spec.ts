import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { WorkspaceMembersComponent } from './workspace-members';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { WorkspaceMembersService } from '../../../core/services/workspace-members.service';
import { WorkspacesService } from '../../../core/services/workspaces';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { ToastService } from '../../../core/services/toast.service';
import { Workspace, WorkspaceMember } from '../../../core/models/workspace.model';

const mockWorkspace: Workspace = {
  id: 'ws-1',
  owner_id: 'user-uuid-1',
  owner_plan: 'premium',
  name: 'Test Workspace',
  type: 'personal' as const,
  currency_code: 'USD',
  created_at: '2024-01-01',
  updated_at: '2024-01-01',
};

const mockMembers: WorkspaceMember[] = [
  {
    id: 'user-uuid-1',
    name: 'Owner User',
    email: 'owner@test.com',
    role: 'owner',
    can_add_fixed_expenses: true,
    can_add_categories: true,
  },
  {
    id: 'user-uuid-2',
    name: 'Guest User',
    email: 'guest@test.com',
    role: 'guest',
    can_add_fixed_expenses: false,
    can_add_categories: false,
  },
];

const activatedRouteMock = {
  snapshot: { paramMap: { get: (key: string) => (key === 'id' ? 'ws-1' : null) } },
};

describe('WorkspaceMembersComponent', () => {
  let fixture: ComponentFixture<WorkspaceMembersComponent>;
  let component: WorkspaceMembersComponent;
  let membersMock: {
    list: ReturnType<typeof vi.fn>;
    invite: ReturnType<typeof vi.fn>;
    updateMember: ReturnType<typeof vi.fn>;
    remove: ReturnType<typeof vi.fn>;
  };
  let workspacesMock: { getById: ReturnType<typeof vi.fn> };
  let router: Router;

  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
  const authMock = { userId: vi.fn().mockReturnValue('user-uuid-1') };

  async function setup(options?: {
    workspaceGetById?: ReturnType<typeof vi.fn>;
    list?: ReturnType<typeof vi.fn>;
    invite?: ReturnType<typeof vi.fn>;
    updateMember?: ReturnType<typeof vi.fn>;
    remove?: ReturnType<typeof vi.fn>;
  }) {
    TestBed.resetTestingModule();

    membersMock = {
      list: options?.list ?? vi.fn().mockReturnValue(of({ data: mockMembers })),
      invite:
        options?.invite ??
        vi.fn().mockReturnValue(
          of({
            data: {
              id: 'user-uuid-3',
              name: 'New User',
              email: 'new@test.com',
              role: 'guest',
              can_add_fixed_expenses: false,
              can_add_categories: false,
            },
          }),
        ),
      updateMember:
        options?.updateMember ??
        vi
          .fn()
          .mockImplementation(
            (_workspaceId: string, memberId: string, payload: Record<string, boolean>) =>
              of({
                data: {
                  ...mockMembers.find((member) => member.id === memberId)!,
                  ...payload,
                },
              }),
          ),
      remove: options?.remove ?? vi.fn().mockReturnValue(of(null)),
    };

    workspacesMock = {
      getById: options?.workspaceGetById ?? vi.fn().mockReturnValue(of({ data: mockWorkspace })),
    };

    TestBed.configureTestingModule({
      imports: [WorkspaceMembersComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ActivatedRoute, useValue: activatedRouteMock },
        { provide: WorkspaceMembersService, useValue: membersMock },
        { provide: WorkspacesService, useValue: workspacesMock },
        { provide: AuthStateService, useValue: authMock },
        { provide: ToastService, useValue: toastMock },
      ],
    });

    await TestBed.compileComponents();
    router = TestBed.inject(Router);
    fixture = TestBed.createComponent(WorkspaceMembersComponent);
    component = fixture.componentInstance;
  }

  beforeEach(async () => {
    vi.clearAllMocks();
    await setup();
  });

  it('should create the component', () => {
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  const skipToastOptions = expect.objectContaining({ context: expect.anything() });

  it('should load workspace and members on init when owner premium', () => {
    fixture.detectChanges();
    expect(workspacesMock.getById).toHaveBeenCalledWith('ws-1', skipToastOptions);
    expect(membersMock.list).toHaveBeenCalledWith('ws-1', skipToastOptions);
    expect(component.members()).toHaveLength(2);
    expect(component.workspace()?.name).toBe('Test Workspace');
  });

  it('should show error toast when loading fails', async () => {
    await setup({
      workspaceGetById: vi.fn().mockReturnValue(throwError(() => ({ status: 500 }))),
    });

    fixture.detectChanges();

    expect(toastMock.error).toHaveBeenCalled();
  });

  it('should redirect to /user/workspaces when current user cannot manage members', async () => {
    await setup({
      workspaceGetById: vi.fn().mockReturnValue(
        of({
          data: {
            ...mockWorkspace,
            owner_plan: 'free',
          },
        }),
      ),
    });

    const navigateSpy = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    fixture.detectChanges();

    expect(navigateSpy).toHaveBeenCalledWith('/user/workspaces');
    expect(membersMock.list).not.toHaveBeenCalled();
  });

  it('isOwner() should return true for member with owner_id matching workspace', () => {
    fixture.detectChanges();
    expect(component.isOwner(mockMembers[0])).toBe(true);
    expect(component.isOwner(mockMembers[1])).toBe(false);
  });

  it('isCurrentUser() should return true for authenticated user', () => {
    fixture.detectChanges();
    expect(component.isCurrentUser(mockMembers[0])).toBe(true);
    expect(component.isCurrentUser(mockMembers[1])).toBe(false);
  });

  describe('invite()', () => {
    it('should add new member to list and show success toast on success', () => {
      fixture.detectChanges();
      component.inviteForm.setValue({ email: 'new@test.com' });

      component.invite();

      expect(membersMock.invite).toHaveBeenCalledWith('ws-1', { email: 'new@test.com' }, skipToastOptions);
      expect(component.members()).toHaveLength(3);
      expect(toastMock.success).toHaveBeenCalled();
    });

    it('should show user_not_found error on 404', () => {
      membersMock.invite = vi.fn().mockReturnValue(
        throwError(() => ({
          status: 404,
          error: {
            status: 404,
            code: BACKEND_ERROR_CODES.workspaceMemberUserNotFound,
            message: 'User not found',
            request_id: 'req-1',
          },
        })),
      );
      fixture.detectChanges();
      component.inviteForm.setValue({ email: 'unknown@test.com' });
      component.invite();
      expect(toastMock.error).toHaveBeenCalled();
    });

    it('should show already_member error on 422', () => {
      membersMock.invite = vi.fn().mockReturnValue(
        throwError(() => ({
          status: 422,
          error: {
            status: 422,
            code: BACKEND_ERROR_CODES.workspaceMemberAlreadyMember,
            message: 'User already belongs to workspace',
            request_id: 'req-2',
          },
        })),
      );
      fixture.detectChanges();
      component.inviteForm.setValue({ email: 'guest@test.com' });
      component.invite();
      expect(toastMock.error).toHaveBeenCalled();
    });

    it('should apply backend field errors when invite returns validation details', () => {
      membersMock.invite = vi.fn().mockReturnValue(
        throwError(() => ({
          status: 422,
          error: {
            status: 422,
            code: BACKEND_ERROR_CODES.validationError,
            message: 'Validation failed',
            request_id: 'req-3',
            fieldErrors: {
              email: ['Email format is invalid on server'],
            },
          },
        })),
      );
      fixture.detectChanges();

      component.inviteForm.setValue({ email: 'guest@test.com' });
      component.invite();

      expect(component.inviteForm.get('email')?.errors?.['serverError']).toBe(
        'Email format is invalid on server',
      );
      expect(toastMock.error).not.toHaveBeenCalledWith(
        expect.stringContaining('Validation failed'),
      );
    });

    it('should surface the normalized backend message for unexpected invite errors', () => {
      membersMock.invite = vi.fn().mockReturnValue(
        throwError(() => ({
          status: 500,
          error: {
            status: 500,
            code: BACKEND_ERROR_CODES.internalError,
            message: 'Unexpected invite failure',
            request_id: 'req-5',
          },
        })),
      );
      fixture.detectChanges();

      component.inviteForm.setValue({ email: 'guest@test.com' });
      component.invite();

      expect(toastMock.error).toHaveBeenCalledWith('members.load_error: Unexpected invite failure');
    });

    it('should not call service if form is invalid', () => {
      fixture.detectChanges();
      component.inviteForm.setValue({ email: 'not-an-email' });
      component.invite();
      expect(membersMock.invite).not.toHaveBeenCalled();
    });
  });

  describe('updatePermission()', () => {
    it('should update fixed expenses permission without categories field', () => {
      fixture.detectChanges();

      component.updatePermission(mockMembers[1], 'can_add_fixed_expenses', true);

      expect(membersMock.updateMember).toHaveBeenCalledWith('ws-1', 'user-uuid-2', {
        can_add_fixed_expenses: true,
      }, skipToastOptions);
      expect(
        component.members().find((member) => member.id === 'user-uuid-2')?.can_add_fixed_expenses,
      ).toBe(true);
    });

    it('should rollback permission change and show error toast on failure', async () => {
      await setup({
        updateMember: vi.fn().mockReturnValue(throwError(() => ({ status: 500 }))),
      });

      fixture.detectChanges();

      component.updatePermission(mockMembers[1], 'can_add_fixed_expenses', true);

      const updated = component.members().find((m) => m.id === 'user-uuid-2');
      expect(updated?.can_add_fixed_expenses).toBe(false);
      expect(toastMock.error).toHaveBeenCalled();
    });
  });

  describe('removeMember()', () => {
    it('should remove member from list and show success toast', () => {
      fixture.detectChanges();
      component.removeMember(mockMembers[1]);
      expect(membersMock.remove).toHaveBeenCalledWith('ws-1', 'user-uuid-2', skipToastOptions);
      expect(component.members()).toHaveLength(1);
      expect(toastMock.success).toHaveBeenCalled();
    });

    it('should show error toast when remove fails', () => {
      membersMock.remove = vi.fn().mockReturnValue(throwError(() => ({ status: 500 })));
      fixture.detectChanges();
      component.removeMember(mockMembers[1]);
      expect(component.members()).toHaveLength(2);
      expect(toastMock.error).toHaveBeenCalled();
    });

    it('should show dedicated message when backend prevents removing owner', () => {
      membersMock.remove = vi.fn().mockReturnValue(
        throwError(() => ({
          status: 422,
          error: {
            status: 422,
            code: BACKEND_ERROR_CODES.workspaceMemberCannotRemoveOwner,
            message: 'Owner cannot be removed',
            request_id: 'req-4',
          },
        })),
      );
      fixture.detectChanges();

      component.removeMember(mockMembers[1]);

      expect(toastMock.error).toHaveBeenCalledWith('members.cannot_remove_owner');
    });
  });

  it('should not render categories permissions column', () => {
    fixture.detectChanges();

    const native = fixture.nativeElement as HTMLElement;
    expect(native.textContent).not.toContain('members.perm_categories');
  });
});
