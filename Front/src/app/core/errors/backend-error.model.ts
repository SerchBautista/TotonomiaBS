import { BackendErrorCode } from './backend-error-codes';

export type BackendErrorFieldErrors = Readonly<Record<string, readonly string[]>>;

export type BackendErrorMeta = Readonly<Record<string, unknown>>;

export interface StandardBackendErrorPayload {
  status: number;
  code: BackendErrorCode;
  message: string;
  request_id: string;
  fieldErrors?: BackendErrorFieldErrors;
  meta?: BackendErrorMeta;
}

export interface NormalizedBackendError {
  status: number;
  code: BackendErrorCode;
  message: string;
  requestId: string | null;
  fieldErrors: BackendErrorFieldErrors | null;
  meta: BackendErrorMeta | null;
  isStandardized: boolean;
  original: unknown;
}

export interface NormalizeBackendErrorOptions {
  fallbackCode?: BackendErrorCode;
  fallbackMessage?: string;
}
