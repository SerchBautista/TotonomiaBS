import { Component } from '@angular/core';
import { TranslateModule, TranslateService } from '@ngx-translate/core';

@Component({
  selector: 'app-language-switcher',
  imports: [TranslateModule],
  templateUrl: './language-switcher.html',
  styleUrl: './language-switcher.scss'
})
export class LanguageSwitcherComponent {
  language = localStorage.getItem('app_lang') ?? 'es';

  constructor(private readonly translate: TranslateService) {}

  useLanguage(language: 'es' | 'en'): void {
    this.language = language;
    localStorage.setItem('app_lang', language);
    this.translate.use(language);
  }
}
