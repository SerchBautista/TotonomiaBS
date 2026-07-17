import { BudgetAdjustment } from './budget-adjustment.model';
import { Category } from './category.model';

export interface Budget {
  id: string;
  workspace_id: string;
  category_id: string | null;
  category?: Category;
  amount: string;
  effective_from: string;
  alert_threshold: string;
  alert_enabled: boolean;
  created_at: string;
  updated_at: string;
}

export interface BudgetCreatePayload {
  category_id?: string | null;
  amount: string;
  alert_threshold?: number;
  alert_enabled?: boolean;
}

export interface BudgetUpdatePayload {
  amount?: string;
  alert_threshold?: number;
  alert_enabled?: boolean;
}

export interface BudgetWarning {
  scope: 'general' | 'category';
  category_id?: string;
  category_name?: string;
  budget_id: string;
  percentage: number;
  alert_threshold: number;
  over_budget?: boolean;
}

export interface BudgetScopeStatus {
  id: string;
  budget: string;
  spent: string;
  remaining: string;
  percentage: number;
  alert_threshold: number;
  alert_enabled: boolean;
  over_threshold: boolean;
  committed: string;
  effective_spent: string;
}

export interface BudgetCategoryScopeStatus {
  category_id: string;
  category_name: string | null;
  category_icon: string | null;
  category_color: string | null;
  has_budget: boolean;
  spent: string;
  committed: string;
  effective_spent: string;
  base_budget?: string;
  adjustments_in?: string;
  adjustments_out?: string;
  effective_budget?: string;
  remaining?: string;
  percentage?: number;
  alert_threshold?: number;
  alert_enabled?: boolean;
  over_threshold?: boolean;
  over_budget?: boolean;
  adjustments?: BudgetAdjustment[];
  id?: string;
  budget?: string;
}

export interface BudgetStatusResponse {
  month: string;
  general: BudgetScopeStatus | null;
  categories: BudgetCategoryScopeStatus[];
}
