export type PaymentType = 'cash' | 'card' | 'other';
export type CardType = 'credit' | 'debit';
export type WorkspacePaymentMethodState = 'unlinked' | 'linked' | 'linked_read_only';

export interface LinkedPaymentMethodWorkspace {
  id: string;
  name: string;
}

export interface UserPaymentMethodSummary {
  id: string;
  type: PaymentType;
  name: string;
  display_name: string;
  masked_details: string | null;
  linked_workspaces_count?: number;
  linked_workspaces?: LinkedPaymentMethodWorkspace[];
}

export interface WorkspacePaymentMethodSummary {
  id: string;
  type: PaymentType;
  name: string;
  display_name: string;
  masked_details: string | null;
  is_linked: boolean;
  is_valid_for_transactions: boolean;
  state: string;
}

export interface WorkspacePaymentMethodCreatePayload {
  type: Exclude<PaymentType, 'cash'>;
  name: string;
  card_type?: CardType;
  brand?: string | null;
  last_4_digits?: string | null;
  description?: string | null;
  workspace_ids?: string[];
}

export interface WorkspacePaymentMethodBulkResult {
  operation: 'link_all' | 'unlink_all';
  total: number;
  processed: number;
  blocked: number;
  processed_method_ids: string[];
  blocked_method_ids: string[];
}

export interface Card {
  id: string;
  workspace_id: string;
  name: string;
  card_type: CardType;
  brand: string | null;
  last_4_digits: string | null;
  is_default?: boolean;
  created_at?: string;
}

export interface CardCreatePayload {
  name: string;
  card_type: CardType;
  brand?: string | null;
  last_4_digits?: string | null;
  workspace_ids?: string[];
}

export interface CardUpdatePayload {
  name?: string;
  card_type?: CardType;
  brand?: string | null;
  last_4_digits?: string | null;
  workspace_ids?: string[];
}

export interface OtherPaymentMethod {
  id: string;
  workspace_id: string;
  name: string;
  description: string | null;
  is_default?: boolean;
  created_at?: string;
}

export interface OtherPaymentMethodCreatePayload {
  name: string;
  description?: string | null;
  workspace_ids?: string[];
}

export interface OtherPaymentMethodUpdatePayload {
  name?: string;
  description?: string | null;
  workspace_ids?: string[];
}

export type PaymentInstrument = Card | OtherPaymentMethod;

export function isCard(instrument: PaymentInstrument): instrument is Card {
  return 'card_type' in instrument;
}

export function parsePaymentValue(value: string): { paymentType: PaymentType; paymentInstrumentId: string | null } {
  if (value === 'cash') {
    return { paymentType: 'cash', paymentInstrumentId: null };
  }

  if (value.startsWith('card:')) {
    return { paymentType: 'card', paymentInstrumentId: value.slice(5) };
  }

  if (value.startsWith('other:')) {
    return { paymentType: 'other', paymentInstrumentId: value.slice(6) };
  }

  return { paymentType: 'cash', paymentInstrumentId: null };
}

export function buildPaymentValue(paymentType: PaymentType, paymentInstrumentId: string | null): string {
  if (paymentType === 'cash') return 'cash';
  if (paymentType === 'card' && paymentInstrumentId) return `card:${paymentInstrumentId}`;
  if (paymentType === 'other' && paymentInstrumentId) return `other:${paymentInstrumentId}`;
  return '';
}
