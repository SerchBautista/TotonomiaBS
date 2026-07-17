import { ChangeDetectionStrategy, Component, inject, Input } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { AUTH_STATE_TOKEN } from '../../core/tokens/auth-state.token';
import { LanguageSwitcherComponent } from '../language-switcher/language-switcher';

@Component({
  selector: 'app-public-shell',
  imports: [RouterLink, RouterLinkActive, TranslateModule, LanguageSwitcherComponent],
  templateUrl: './public-shell.html',
  styleUrl: './public-shell.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PublicShellComponent {
  @Input() variant: 'default' | 'landing' = 'default';

  readonly authService = inject(AUTH_STATE_TOKEN);
}
