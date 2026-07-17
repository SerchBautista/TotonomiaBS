import { Category } from './category.model';
import { PaymentInstrument, PaymentType } from './payment-method.model';

export type FixedExpenseFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly';
export type OccurrenceStatus = 'pending' | 'paid' | 'overdue';
export type FixedExpenseType = 'recurring' | 'installment';

export interface FixedExpense {
  id: string;
  workspace_id: string;
  user_id: string;
  category_id: string;
  payment_type: PaymentType;
  payment_instrument_id: string | null;
  amount: string;
  description: string;
  frequency: FixedExpenseFrequency;
  next_due_date: string;
  alert_date: string | null;
  is_active: boolean;
  reminders_enabled: boolean;
  type: FixedExpenseType;
  total_installments: number | null;
  remaining_installments: number | null;
  has_paid_occurrences: boolean;
  category?: Category;
  payment_instrument?: PaymentInstrument | null;
}

export interface FixedExpenseCreatePayload {
  category_id: string;
  payment_type: PaymentType;
  payment_instrument_id?: string | null;
  amount: string;
  description: string;
  frequency: FixedExpenseFrequency;
  next_due_date: string;
  alert_date?: string | null;
  reminders_enabled?: boolean;
  type?: FixedExpenseType;
  total_installments?: number | null;
  remaining_installments?: number | null;
}

export interface FixedExpenseOccurrence {
  id: string;
  due_date: string;
  suggested_amount: string;
  status: OccurrenceStatus;
  fixed_expense: {
    id: string;
    description: string;
    frequency: FixedExpenseFrequency;
    payment_type: PaymentType;
    payment_instrument: PaymentInstrument | null;
    category: Category | null;
  } | null;
}

export interface FixedExpenseUpdatePayload {
  category_id?: string;
  payment_type?: PaymentType;
  payment_instrument_id?: string | null;
  amount?: string;
  description?: string | null;
  frequency?: FixedExpenseFrequency;
  next_due_date?: string;
  alert_date?: string | null;
  reminders_enabled?: boolean;
  type?: FixedExpenseType;
  total_installments?: number | null;
  remaining_installments?: number | null;
}

export interface PayOccurrencePayload {
  amount: string;
  payment_type: PaymentType;
  payment_instrument_id?: string | null;
  paid_at: string;
  paid_by_user_id?: string | null;
}
