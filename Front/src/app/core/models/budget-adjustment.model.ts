import { Category } from './category.model';

export interface BudgetAdjustment {
  id: string;
  workspace_id: string;
  month: string;
  from_category_id: string;
  from_category?: Category;
  to_category_id: string;
  to_category?: Category;
  amount: string;
  reason: string | null;
  user_id: string;
  created_at: string;
  updated_at: string;
}

export interface BudgetAdjustmentCreatePayload {
  from_category_id: string;
  to_category_id: string;
  amount: string;
  month: string;
  reason?: string | null;
}

export interface AvailableCategory {
  category_id: string;
  category_name: string | null;
  category_icon: string | null;
  category_color: string | null;
  base_budget: string;
  effective_budget: string;
  spent: string;
  available: string;
}

export interface BudgetAdjustmentListResponse {
  data: BudgetAdjustment[];
}

export interface BudgetAdjustmentItemResponse {
  data: BudgetAdjustment;
}

export interface AvailableCategoriesResponse {
  data: AvailableCategory[];
}