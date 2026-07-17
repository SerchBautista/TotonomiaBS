export type WorkspaceType = 'personal' | 'familiar' | 'empresa';

export type WorkspaceMemberRole = 'owner' | 'guest';

export interface WorkspaceMember {
  id: string;
  name: string;
  email: string;
  role: WorkspaceMemberRole;
  can_add_fixed_expenses: boolean;
  can_add_categories: boolean;
}

export interface InviteMemberPayload {
  email: string;
}

export interface UpdateMemberPayload {
  role?: WorkspaceMemberRole;
  can_add_fixed_expenses?: boolean;
  can_add_categories?: boolean;
}

export type WorkspacePlan = 'free' | 'premium';

export interface WorkspaceUserPermissions {
  can_add_fixed_expenses: boolean;
  can_add_categories: boolean;
}

export interface Workspace {
  id: string;
  owner_id: string;
  name: string;
  type: WorkspaceType;
  currency_code: string;
  owner_plan?: WorkspacePlan;
  members_count?: number;
  owner?: {
    id: string;
    name: string;
    email: string;
  };
  members?: WorkspaceMember[];
  current_user_permissions?: WorkspaceUserPermissions;
  created_at: string;
  updated_at: string;
}

export interface WorkspaceCreatePayload {
  name: string;
  type: WorkspaceType;
  currency_code: string;
}

export interface WorkspaceUpdatePayload {
  name?: string;
  type?: WorkspaceType;
  currency_code?: string;
}
