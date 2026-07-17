import { HttpContextToken } from '@angular/common/http';

/** Skip global 401 redirect (login, logout, auth flows that handle session locally). */
export const SKIP_GLOBAL_UNAUTHORIZED_REDIRECT = new HttpContextToken<boolean>(() => false);

/** Skip global HTTP error handling entirely (normalize + rethrow only; feature owns UX). */
export const SKIP_GLOBAL_ERROR_HANDLER = new HttpContextToken<boolean>(() => false);

/** Skip global error toast while still normalizing and rethrowing (forms, inline loadError). */
export const SKIP_GLOBAL_ERROR_TOAST = new HttpContextToken<boolean>(() => false);
