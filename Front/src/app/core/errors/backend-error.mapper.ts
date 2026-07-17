import { HttpErrorResponse } from '@angular/common/http';
import { BACKEND_ERROR_CODES } from './backend-error-codes';
import {
  BackendErrorFieldErrors,
  BackendErrorMeta,
  NormalizeBackendErrorOptions,
  NormalizedBackendError,
  StandardBackendErrorPayload,
} from './backend-error.model';

export const DEFAULT_ERROR_MESSAGE = 'Ha ocurrido un error inesperado / An unexpected error occurred';
export const NETWORK_ERROR_MESSAGE = 'No se pudo conectar con la API / Could not connect to API';
const GENERIC_HTTP_FAILURE_MESSAGE_PREFIX = 'Http failure response for';

type HttpErrorLike = {
  status?: unknown;
  message?: unknown;
  statusText?: unknown;
  error?: unknown;
};

type LooseRecord = Record<string, unknown>;

export function normalizeBackendError(
  error: unknown,
  options: NormalizeBackendErrorOptions = {}
): NormalizedBackendError {
  const httpError = toHttpErrorLike(error);
  const status = normalizeStatus(httpError.status);
  const payload = parsePayload(httpError.error);

  if (isStandardBackendErrorPayload(payload)) {
    return {
      status: payload.status,
      code: payload.code,
      message: payload.message,
      requestId: payload.request_id,
      fieldErrors: payload.fieldErrors ?? null,
      meta: payload.meta ?? null,
      isStandardized: true,
      original: error,
    };
  }

  const fieldErrors = extractFieldErrors(payload);
  const meta = extractLegacyMeta(payload);

  return {
    status,
    code: resolveLegacyCode(status, fieldErrors, options.fallbackCode),
    message: resolveLegacyMessage(status, payload, httpError, fieldErrors, options.fallbackMessage),
    requestId: extractRequestId(payload),
    fieldErrors,
    meta,
    isStandardized: false,
    original: error,
  };
}

export function ensureNormalizedBackendError(
  error: unknown,
  options: NormalizeBackendErrorOptions = {}
): NormalizedBackendError {
  return isNormalizedBackendError(error) ? error : normalizeBackendError(error, options);
}

export function isNormalizedBackendError(error: unknown): error is NormalizedBackendError {
  if (!isRecord(error)) {
    return false;
  }

  return (
    typeof error['status'] === 'number'
    && typeof error['code'] === 'string'
    && typeof error['message'] === 'string'
    && typeof error['isStandardized'] === 'boolean'
    && 'requestId' in error
    && 'fieldErrors' in error
    && 'meta' in error
    && 'original' in error
  );
}

export function isStandardBackendErrorPayload(payload: unknown): payload is StandardBackendErrorPayload {
  if (!isRecord(payload)) {
    return false;
  }

  return (
    typeof payload['status'] === 'number'
    && typeof payload['code'] === 'string'
    && typeof payload['message'] === 'string'
    && typeof payload['request_id'] === 'string'
  );
}

function toHttpErrorLike(error: unknown): HttpErrorLike {
  if (error instanceof HttpErrorResponse) {
    return {
      status: error.status,
      message: error.message,
      statusText: error.statusText,
      error: error.error,
    };
  }

  if (isRecord(error)) {
    return {
      status: error['status'],
      message: error['message'],
      statusText: error['statusText'],
      error: error['error'],
    };
  }

  return {};
}

function parsePayload(payload: unknown): unknown {
  if (typeof payload !== 'string') {
    return payload ?? null;
  }

  const trimmedPayload = payload.trim();
  if (!trimmedPayload) {
    return null;
  }

  try {
    return JSON.parse(trimmedPayload) as unknown;
  } catch {
    return trimmedPayload;
  }
}

function normalizeStatus(status: unknown): number {
  return typeof status === 'number' && Number.isFinite(status) ? status : 0;
}

function resolveLegacyCode(
  status: number,
  fieldErrors: BackendErrorFieldErrors | null,
  fallbackCode?: string
): string {
  if (fallbackCode) {
    return fallbackCode;
  }

  if (status === 0) {
    return BACKEND_ERROR_CODES.networkError;
  }

  if (status === 401) {
    return BACKEND_ERROR_CODES.unauthenticated;
  }

  if (status === 403) {
    return BACKEND_ERROR_CODES.forbidden;
  }

  if (status === 404) {
    return BACKEND_ERROR_CODES.notFound;
  }

  if (status === 409) {
    return BACKEND_ERROR_CODES.conflict;
  }

  if (status === 422 && fieldErrors) {
    return BACKEND_ERROR_CODES.validationError;
  }

  if (status >= 500) {
    return BACKEND_ERROR_CODES.internalError;
  }

  return BACKEND_ERROR_CODES.unknownError;
}

function resolveLegacyMessage(
  status: number,
  payload: unknown,
  httpError: HttpErrorLike,
  fieldErrors: BackendErrorFieldErrors | null,
  fallbackMessage?: string
): string {
  const payloadMessage = extractPayloadMessage(payload);
  if (payloadMessage) {
    return payloadMessage;
  }

  const fieldErrorMessage = getFirstFieldErrorMessage(fieldErrors);
  if (fieldErrorMessage) {
    return fieldErrorMessage;
  }

  if (typeof payload === 'string' && payload.trim()) {
    return payload.trim();
  }

  if (
    typeof httpError.message === 'string'
    && httpError.message.startsWith(GENERIC_HTTP_FAILURE_MESSAGE_PREFIX)
  ) {
    return fallbackMessage ?? (status === 0 ? NETWORK_ERROR_MESSAGE : DEFAULT_ERROR_MESSAGE);
  }

  if (typeof httpError.message === 'string' && httpError.message.trim()) {
    return httpError.message.trim();
  }

  if (typeof httpError.statusText === 'string' && httpError.statusText.trim()) {
    return httpError.statusText.trim();
  }

  if (status === 0) {
    return NETWORK_ERROR_MESSAGE;
  }

  return fallbackMessage ?? DEFAULT_ERROR_MESSAGE;
}

function extractPayloadMessage(payload: unknown): string | null {
  if (!isRecord(payload)) {
    return null;
  }

  const message = payload['message'];
  return typeof message === 'string' && message.trim() ? message.trim() : null;
}

function extractRequestId(payload: unknown): string | null {
  if (!isRecord(payload)) {
    return null;
  }

  const requestId = payload['request_id'];
  return typeof requestId === 'string' && requestId.trim() ? requestId.trim() : null;
}

function extractFieldErrors(payload: unknown): BackendErrorFieldErrors | null {
  if (!isRecord(payload)) {
    return null;
  }

  const candidate = isRecord(payload['fieldErrors'])
    ? payload['fieldErrors']
    : isRecord(payload['errors'])
      ? payload['errors']
      : null;

  if (!candidate) {
    return null;
  }

  const normalizedEntries = Object.entries(candidate)
    .map(([field, value]) => [field, normalizeFieldErrorMessages(value)] as const)
    .filter(([, messages]) => messages.length > 0);

  if (!normalizedEntries.length) {
    return null;
  }

  return Object.freeze(
    Object.fromEntries(
      normalizedEntries.map(([field, messages]) => [field, Object.freeze(messages)])
    )
  ) as BackendErrorFieldErrors;
}

function normalizeFieldErrorMessages(value: unknown): string[] {
  if (typeof value === 'string') {
    return value.trim() ? [value.trim()] : [];
  }

  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .filter((message): message is string => typeof message === 'string' && message.trim().length > 0)
    .map((message) => message.trim());
}

function getFirstFieldErrorMessage(fieldErrors: BackendErrorFieldErrors | null): string | null {
  if (!fieldErrors) {
    return null;
  }

  const firstGroup = Object.values(fieldErrors)[0];
  return firstGroup?.[0] ?? null;
}

function extractLegacyMeta(payload: unknown): BackendErrorMeta | null {
  if (!isRecord(payload)) {
    return null;
  }

  if (isRecord(payload['meta'])) {
    return payload['meta'] as BackendErrorMeta;
  }

  const ignoredKeys = new Set(['status', 'code', 'message', 'request_id', 'fieldErrors', 'errors']);
  const entries = Object.entries(payload).filter(([key]) => !ignoredKeys.has(key));

  if (!entries.length) {
    return null;
  }

  return Object.freeze(Object.fromEntries(entries));
}

function isRecord(value: unknown): value is LooseRecord {
  return typeof value === 'object' && value !== null;
}
