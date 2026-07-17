import { computed, inject, Injectable, signal } from '@angular/core';
import { toObservable } from '@angular/core/rxjs-interop';
import { Observable } from 'rxjs';
import { UserRole } from '../auth/role-hierarchy';
import { UserPlan } from '../models/user.model';
import { TokenService } from './token.service';

interface LoginStateUser {
  id: string;
  role: UserRole | null;
  plan?: UserPlan;
  default_workspace_id?: string | null;
  permissions?: string[];
}

@Injectable({
  providedIn: 'root'
})
export class AuthStateService {
  private readonly tokenSvc = inject(TokenService);

  private readonly tokenState = signal<string | null>(this.tokenSvc.getToken());
  private readonly roleState = signal<UserRole | null>(this.readRole());
  private readonly planState = signal<UserPlan>(this.readPlan());
  private readonly userIdState = signal<string | null>(this.tokenSvc.getUserId());
  private readonly defaultWorkspaceIdState = signal<string | null>(this.tokenSvc.getDefaultWorkspaceId());
  private readonly emailVerifiedState = signal<boolean>(this.tokenSvc.getEmailVerified());
  private readonly permissionsState = signal<string[]>(this.tokenSvc.getPermissions());

  readonly isLoggedIn = computed(() => !!this.tokenState());
  readonly emailVerified = computed(() => this.emailVerifiedState());
  readonly plan$: Observable<UserPlan> = toObservable(this.planState);

  token(): string | null {
    return this.tokenState();
  }

  role(): UserRole | null {
    return this.roleState();
  }

  plan(): UserPlan {
    return this.planState();
  }

  userId(): string | null {
    return this.userIdState();
  }

  defaultWorkspaceId(): string | null {
    return this.defaultWorkspaceIdState();
  }

  permissions(): string[] {
    return this.permissionsState();
  }

  hasPermission(name: string): boolean {
    return this.permissionsState().includes(name);
  }

  setToken(token: string): void {
    this.tokenSvc.setToken(token);
    this.tokenState.set(token);
  }

  setRole(role: UserRole | null): void {
    if (role) {
      this.tokenSvc.setRole(role);
    }
    this.roleState.set(role);
  }

  setPlan(plan: UserPlan): void {
    this.tokenSvc.setPlan(plan);
    this.planState.set(plan);
  }

  setUserId(id: string): void {
    this.tokenSvc.setUserId(id);
    this.userIdState.set(id);
  }

  setDefaultWorkspaceId(id: string | null): void {
    if (id) {
      this.tokenSvc.setDefaultWorkspaceId(id);
    } else {
      this.tokenSvc.removeDefaultWorkspaceId();
    }
    this.defaultWorkspaceIdState.set(id);
  }

  setEmailVerified(verified: boolean): void {
    this.tokenSvc.setEmailVerified(verified);
    this.emailVerifiedState.set(verified);
  }

  setPermissions(permissions: string[]): void {
    this.tokenSvc.setPermissions(permissions);
    this.permissionsState.set(permissions);
  }

  applyLoginResponse(user: LoginStateUser, token: string): void {
    this.setToken(token);
    this.setRole(user.role ?? 'user');
    this.setPlan(user.plan ?? 'free');
    this.setUserId(user.id);
    this.setDefaultWorkspaceId(user.default_workspace_id ?? null);
    this.setEmailVerified(true);
    this.setPermissions(user.permissions ?? []);
  }

  clear(): void {
    this.tokenSvc.removeToken();
    this.tokenSvc.removeRole();
    this.tokenSvc.removePlan();
    this.tokenSvc.removeUserId();
    this.tokenSvc.removeDefaultWorkspaceId();
    this.tokenSvc.removeEmailVerified();
    this.tokenSvc.removePermissions();
    this.tokenState.set(null);
    this.roleState.set(null);
    this.planState.set('free');
    this.userIdState.set(null);
    this.defaultWorkspaceIdState.set(null);
    this.emailVerifiedState.set(false);
    this.permissionsState.set([]);
  }

  private readRole(): UserRole | null {
    const role = this.tokenSvc.getRole();
    return role === 'admin' || role === 'user' ? role : null;
  }

  private readPlan(): UserPlan {
    const plan = this.tokenSvc.getPlan();
    return plan === 'premium' ? 'premium' : 'free';
  }
}
