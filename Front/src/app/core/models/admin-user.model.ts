export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  sort_by: string;
  sort_dir: 'asc' | 'desc';
  search: string;
}

export interface AdminUserListItem {
  id: string;
  name: string;
  email: string;
  plan: string;
  subscription_ends_at: string | null;
  email_verified_at: string | null;
  has_active_subscription: boolean;
  registered_at: string;
}

export interface AdminUserDetail extends AdminUserListItem {
  workspaces_owned_count: number;
}

export interface AdminUserListResponse {
  message: string;
  data: {
    items: AdminUserListItem[];
  };
  meta: PaginationMeta;
}

export interface AdminUserDetailResponse {
  message: string;
  data: {
    item: AdminUserDetail;
  };
}

export interface AdminUserListParams {
  page: number;
  perPage: number;
  sortBy: string;
  sortDir: 'asc' | 'desc';
  search: string;
  plan?: string;
  emailVerified?: string;
}
