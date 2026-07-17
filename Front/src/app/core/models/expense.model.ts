import { Category } from './category.model';
import { PaymentInstrument, PaymentType } from './payment-method.model';

export interface Expense {
  id: string;
  workspace_id: string;
  user_id: string;
  category_id: string;
  payment_type: PaymentType;
  payment_instrument_id: string | null;
  fixed_expense_id: string | null;
  amount: string;
  date: string;
  description: string | null;
  category?: Category;
  payment_instrument?: PaymentInstrument | null;
  user?: {
    id: string;
    name: string;
  };
  paid_by_user_id: string | null;
  paid_by?: {
    id: string;
    name: string;
  };
  created_at: string;
}

export interface ExpenseCreatePayload {
  id?: string;
  category_id: string;
  payment_type: PaymentType;
  payment_instrument_id?: string | null;
  amount: string;
  date: string;
  description?: string | null;
  paid_by_user_id?: string | null;
}

export interface ExpenseUpdatePayload {
  category_id?: string;
  payment_type?: PaymentType;
  payment_instrument_id?: string | null;
  amount?: string;
  date?: string;
  description?: string | null;
  paid_by_user_id?: string | null;
}

export interface ExpenseFilters {
  from?: string;
  to?: string;
  category_id?: string;
  payment_type?: string;
  search?: string;
  page?: number;
}
