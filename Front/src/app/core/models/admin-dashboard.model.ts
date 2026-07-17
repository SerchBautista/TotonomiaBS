export interface AdminDashboardKpis {
  users_total: number;
  users_registered_today: number;
  users_registered_week: number;
  email_pending_verification: number;
  premium_active_total: number;
}

export interface AdminDashboardRecentUser {
  id: string;
  name: string;
  email: string;
  plan: string;
  registered_at: string;
}

export interface AdminDashboardResponse {
  message: string;
  data: {
    kpis: AdminDashboardKpis;
    recent_users: AdminDashboardRecentUser[];
  };
}
