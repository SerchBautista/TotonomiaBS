import { UserRole } from '../auth/role-hierarchy';

export type UserPlan = 'free' | 'premium';

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole | null;
  plan: UserPlan;
  default_workspace_id?: string | null;
  two_factor_enabled?: boolean;
  permissions?: string[];
}
