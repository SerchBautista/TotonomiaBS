import { inject, Injectable, Injector } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { DEFAULT_ERROR_MESSAGE } from './backend-error.mapper';
import { NormalizedBackendError } from './backend-error.model';

@Injectable({ providedIn: 'root' })
export class BackendErrorMessageResolver {
  private readonly injector = inject(Injector);

  resolve(error: NormalizedBackendError): string {
    if (error.isStandardized) {
      return error.message;
    }

    const codeTranslation = this.resolveTranslation(`errors.codes.${error.code}`);
    if (codeTranslation) {
      return codeTranslation;
    }

    const httpTranslation = this.resolveTranslation(`errors.http.${error.status}`);
    if (httpTranslation) {
      return httpTranslation;
    }

    return error.message.trim() || DEFAULT_ERROR_MESSAGE;
  }

  private resolveTranslation(key: string): string | null {
    const translated = this.injector.get(TranslateService).instant(key);
    return translated !== key ? translated : null;
  }
}
