import { HttpContext } from '@angular/common/http';
import { SKIP_GLOBAL_ERROR_HANDLER, SKIP_GLOBAL_ERROR_TOAST } from './http-context-tokens';

export function skipGlobalErrorToastContext(): HttpContext {
  return new HttpContext().set(SKIP_GLOBAL_ERROR_TOAST, true);
}

export function skipGlobalErrorHandlerContext(): HttpContext {
  return new HttpContext().set(SKIP_GLOBAL_ERROR_HANDLER, true);
}
