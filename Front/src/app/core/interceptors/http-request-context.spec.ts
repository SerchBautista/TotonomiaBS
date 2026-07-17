import { SKIP_GLOBAL_ERROR_HANDLER, SKIP_GLOBAL_ERROR_TOAST } from './http-context-tokens';
import {
  skipGlobalErrorHandlerContext,
  skipGlobalErrorToastContext,
} from './http-request-context';

describe('http-request-context', () => {
  it('builds HttpContext that skips global error toasts', () => {
    const context = skipGlobalErrorToastContext();

    expect(context.get(SKIP_GLOBAL_ERROR_TOAST)).toBe(true);
    expect(context.get(SKIP_GLOBAL_ERROR_HANDLER)).toBe(false);
  });

  it('builds HttpContext that skips the global error handler', () => {
    const context = skipGlobalErrorHandlerContext();

    expect(context.get(SKIP_GLOBAL_ERROR_HANDLER)).toBe(true);
  });
});
