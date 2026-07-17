export interface MemberSplitMember {
  id: string;
  name: string;
  paid: string;
  balance: string;
}

export interface MemberSplitSettlement {
  from_id: string;
  from_name: string;
  to_id: string;
  to_name: string;
  amount: string;
}

export interface MemberSplitData {
  month: string;
  total: string;
  member_count: number;
  fair_share: string;
  members: MemberSplitMember[];
  settlements: MemberSplitSettlement[];
}

export interface MemberSplitResponse {
  data: MemberSplitData;
}

export interface HeatmapDay {
  date: string;
  total: string;
  count: number;
}

export interface HeatmapResponse {
  data: HeatmapDay[];
}

export interface ProjectionData {
  current_month_total: number;
  days_elapsed: number;
  days_in_month: number;
  daily_average: number;
  projected_total: number;
}

export interface ProjectionResponse {
  data: ProjectionData;
}

export interface CategorySummary {
  id: string;
  name: string;
  icon: string | null;
  color: string | null;
  total: string;
  count: number;
}

export interface PaymentMethodSummary {
  id: string | null;
  name: string;
  type: 'card' | 'other' | 'cash';
  total: string;
  count: number;
}

export interface SummaryData {
  total: string;
  period: { from: string; to: string };
  by_category: CategorySummary[];
  by_payment_method: PaymentMethodSummary[];
}

export interface SummaryResponse {
  data: SummaryData;
}
