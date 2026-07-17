import {
  ApplicationConfig,
  inject,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners
} from '@angular/core';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { provideTranslateHttpLoader } from '@ngx-translate/http-loader';
import { routes } from './app.routes';
import { langParamInterceptor } from './core/interceptors/lang-param.interceptor';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { errorNormalizationInterceptor } from './core/interceptors/error-normalization.interceptor';
import { unauthorizedInterceptor } from './core/interceptors/unauthorized.interceptor';
import { globalErrorHandlerInterceptor } from './core/interceptors/global-error-handler.interceptor';
import { STORAGE_SERVICE_TOKEN, BrowserStorageService } from './core/tokens/storage.token';
import { API_SERVICE_TOKEN } from './core/tokens/api-service.token';
import { AUTH_STATE_TOKEN } from './core/tokens/auth-state.token';
import { ApiService } from './core/services/api';
import { AuthStateService } from './core/services/auth-state.service';
import { provideToastr } from 'ngx-toastr';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideHttpClient(withInterceptors([
      langParamInterceptor,
      authInterceptor,
      errorNormalizationInterceptor,
      unauthorizedInterceptor,
      globalErrorHandlerInterceptor,
    ])),
    provideRouter(routes),
    ...provideTranslateService({
      fallbackLang: 'es',
      lang: 'es',
    }),
    ...provideTranslateHttpLoader({
      prefix: '/i18n/',
      suffix: '.json',
      useHttpBackend: true,
    }),
    provideAppInitializer(() => {
      const translate = inject(TranslateService);
      const selectedLang = localStorage.getItem('app_lang') ?? 'es';
      translate.setFallbackLang('es');
      translate.use(selectedLang);
    }),
    { provide: STORAGE_SERVICE_TOKEN, useClass: BrowserStorageService },
    { provide: API_SERVICE_TOKEN, useClass: ApiService },
    { provide: AUTH_STATE_TOKEN, useExisting: AuthStateService },
    provideAnimations(),
    provideToastr(),
  ]
};
