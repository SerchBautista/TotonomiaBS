export interface Category {
  id: string;
  user_id: string;
  name: string;
  icon: string;
  color: string;
  enabled?: boolean;
  is_default?: boolean;
  is_linked?: boolean;
  is_active?: boolean;
  is_active_in_workspace?: boolean;
  is_in_use_in_workspace?: boolean;
  usage_count_in_workspace?: number;
  is_valid_for_transactions?: boolean;
  linked_workspaces_count?: number;
  linked_workspaces?: LinkedWorkspace[];
  state?: string;
}

export interface LinkedWorkspace {
  id: string;
  name: string;
}

export interface CategoryCreatePayload {
  name: string;
  icon: string;
  color: string;
  workspace_ids?: string[];
}

export interface CategoryUpdatePayload {
  name?: string;
  icon?: string;
  color?: string;
  workspace_ids?: string[];
}
