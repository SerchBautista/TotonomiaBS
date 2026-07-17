export interface NotificationData {
  type: string;
  title: string;
  amount: string;
  due_date: string;
  fixed_expense_id: string;
  occurrence_id: string;
  workspace_id: string;
}

export interface AppNotification {
  id: string;
  data: NotificationData;
  read_at: string | null;
  created_at: string;
}

export interface NotificationListResponse {
  data: AppNotification[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    unread_count: number;
  };
}
