export const BACKEND_ERROR_CODES = {
  unknownError: 'unknown_error',
  networkError: 'network_error',
  unauthenticated: 'unauthenticated',
  forbidden: 'forbidden',
  validationError: 'validation_error',
  notFound: 'not_found',
  conflict: 'conflict',
  internalError: 'internal_error',
  authInvalidCredentials: 'invalid_credentials',
  authEmailNotVerified: 'email_not_verified',
  authRoleMismatch: 'auth_role_mismatch',
  emailVerificationInvalid: 'email_verification_invalid',
  passwordResetInvalidToken: 'password_reset_invalid_token',
  workspaceNotFound: 'workspace_not_found',
  categoryNotFound: 'category_not_found',
  workspaceCategoryOwnerOnly: 'workspace_category_owner_only',
  paymentMethodNotFound: 'payment_method_not_found',
  paymentMethodConflict: 'payment_method_conflict',
  workspaceMemberNotFound: 'workspace_member_not_found',
  workspaceMemberUserNotFound: 'workspace_member_user_not_found',
  workspaceMemberAlreadyMember: 'workspace_member_already_member',
  workspaceMemberCannotRemoveOwner: 'workspace_member_cannot_remove_owner',
  budgetAdjustmentInsufficientFunds: 'budget_adjustment_insufficient_funds',
  twoFactorInvalidOtpCode: 'invalid_otp_code',
  twoFactorOtpCodeExpired: 'otp_code_expired',
  twoFactorInvalidSession: 'invalid_session',
  twoFactorLocked: 'two_factor_locked',
  twoFactorResendCooldown: 'resend_cooldown',
  twoFactorInvalidPassword: 'invalid_password',
} as const;

export type KnownBackendErrorCode = (typeof BACKEND_ERROR_CODES)[keyof typeof BACKEND_ERROR_CODES];

export type BackendErrorCode = KnownBackendErrorCode | (string & {});

const knownBackendErrorCodeSet = new Set<string>(Object.values(BACKEND_ERROR_CODES));

export function isKnownBackendErrorCode(code: string): code is KnownBackendErrorCode {
  return knownBackendErrorCodeSet.has(code);
}
