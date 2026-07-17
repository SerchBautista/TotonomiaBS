import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { DatePipe } from '@angular/common';
import { catchError, forkJoin, map, of } from 'rxjs';

import { CurrencyFormatPipe } from '../../../shared/pipes/currency-format.pipe';
import { ensureNormalizedBackendError } from '../../../core/errors/backend-error.mapper';
import { ApiService } from '../../../core/services/api';
import { AuthApiService } from '../../../core/services/auth-api.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { UserPreferencesService } from '../../../core/services/user-preferences.service';

import { PageHeaderComponent } from '../../../shared/page-header/page-header';
import { SectionPanelComponent } from '../../../shared/section-panel/section-panel';
import { DataTableComponent, TableColumn } from '../../../shared/data-table/data-table';
import { TableCellDirective } from '../../../shared/data-table/table-cell.directive';
import { StatusBadgeComponent } from '../../../shared/status-badge/status-badge';
import { ModalShellComponent } from '../../../shared/modal-shell/modal-shell';

interface ProfileUser {
  id: string;
  name: string;
  email: string;
  role: string;
  plan: 'free' | 'premium';
  theme: 'dark' | 'light';
  locale: 'es' | 'en';
  timezone: string;
  two_factor_enabled: boolean;
}

interface ProfileResponse {
  message: string;
  data: { user: ProfileUser };
}

interface Payment {
  date: string;
  amount: number;
  currency: string;
  status: string;
  gateway: 'stripe' | 'dummy';
  invoice_url: string | null;
}

interface SubscriptionResponse {
  plan: 'free' | 'premium';
  subscription_ends_at: string | null;
  payments: Payment[];
}

const EMPTY_SUBSCRIPTION: SubscriptionResponse = {
  plan: 'free',
  subscription_ends_at: null,
  payments: [],
};

@Component({
  selector: 'app-profile',
  imports: [
    TranslateModule,
    FormsModule,
    RouterLink,
    DatePipe,
    CurrencyFormatPipe,
    PageHeaderComponent,
    SectionPanelComponent,
    DataTableComponent,
    TableCellDirective,
    StatusBadgeComponent,
    ModalShellComponent,
  ],
  templateUrl: './profile.html',
  styleUrl: './profile.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProfileComponent implements OnInit {
  private readonly apiService = inject(ApiService);
  private readonly authApiService = inject(AuthApiService);
  private readonly authState = inject(AuthStateService);
  private readonly preferencesService = inject(UserPreferencesService);
  private readonly translate = inject(TranslateService);

  readonly profile = signal<ProfileUser | null>(null);
  readonly subscription = signal<SubscriptionResponse>(EMPTY_SUBSCRIPTION);

  readonly theme = signal<'dark' | 'light'>('dark');
  readonly locale = signal<'es' | 'en'>('es');
  readonly timezone = signal<string>('UTC');
  readonly timezoneQuery = signal<string>('');
  readonly password = signal<string>('');

  readonly saving = signal<boolean>(false);
  readonly saveStatus = signal<'idle' | 'success' | 'error'>('idle');
  readonly saveMessage = signal<string | null>(null);
  readonly profileLoadError = signal<string | null>(null);
  readonly subscriptionLoadError = signal<string | null>(null);

  readonly showPasswordModal = signal(false);
  readonly twoFactorToggleLoading = signal(false);
  readonly twoFactorToggleError = signal<string | null>(null);
  readonly twoFactorSuccessMessage = signal<string | null>(null);
  private readonly pendingTwoFactorEnabled = signal(false);

  readonly allTimezones = signal<string[]>(this.preferencesService.getAvailableTimezones());

  readonly loadMessage = computed(() => {
    const messages = [this.profileLoadError(), this.subscriptionLoadError()].filter(
      (message): message is string => Boolean(message),
    );
    if (!messages.length) {
      return null;
    }
    return Array.from(new Set(messages)).join(' ');
  });

  readonly filteredTimezones = computed(() => {
    const query = this.timezoneQuery().toLowerCase();
    if (!query) {
      return this.allTimezones();
    }
    return this.allTimezones().filter((tz) => tz.toLowerCase().includes(query));
  });

  readonly paymentColumns = computed<TableColumn<Payment>[]>(() => [
    { key: 'date', header: this.translate.instant('profile.payment_date'), width: '130px' },
    {
      key: 'amount',
      header: this.translate.instant('profile.payment_amount'),
      align: 'right',
      width: '160px',
    },
    { key: 'status', header: this.translate.instant('profile.payment_status'), width: '120px' },
    { key: 'gateway', header: this.translate.instant('profile.payment_gateway'), width: '120px' },
    { key: 'invoice', header: this.translate.instant('profile.payment_invoice'), align: 'right' },
  ]);

  ngOnInit(): void {
    forkJoin({
      profileResult: this.apiService.get<ProfileResponse>('/user/profile').pipe(
        map((res) => ({
          profile: res.data.user,
          error: null as string | null,
        })),
        catchError((error) =>
          of({
            profile: null as ProfileUser | null,
            error: ensureNormalizedBackendError(error, {
              fallbackMessage: 'No fue posible cargar el perfil',
            }).message,
          }),
        ),
      ),
      subscriptionResult: this.apiService.get<SubscriptionResponse>('/user/subscription').pipe(
        map((subscription) => ({
          subscription,
          error: null as string | null,
        })),
        catchError((error) =>
          of({
            subscription: EMPTY_SUBSCRIPTION,
            error: ensureNormalizedBackendError(error, {
              fallbackMessage: 'No fue posible cargar la suscripción',
            }).message,
          }),
        ),
      ),
    }).subscribe(({ profileResult, subscriptionResult }) => {
      const { profile, error: profileError } = profileResult;
      const { subscription, error: subscriptionError } = subscriptionResult;

      this.profileLoadError.set(profileError);
      this.subscriptionLoadError.set(subscriptionError);
      this.profile.set(profile);
      this.subscription.set(subscription);

      if (!subscriptionError) {
        this.authState.setPlan(subscription.plan);
      }

      if (profile) {
        this.theme.set(profile.theme);
        this.locale.set(profile.locale);
        this.timezone.set(profile.timezone);
      }
    });
  }

  onSavePreferences(): void {
    this.saving.set(true);
    this.saveStatus.set('idle');
    this.saveMessage.set(null);
    this.preferencesService
      .saveToBackend({
        theme: this.theme(),
        locale: this.locale(),
        timezone: this.timezone(),
      })
      .subscribe({
        next: (response) => {
          this.saving.set(false);
          this.saveStatus.set('success');
          this.saveMessage.set(response.message ?? 'Preferencias guardadas correctamente');
          if (typeof window !== 'undefined') {
            window.location.reload();
          }
        },
        error: (error) => {
          this.saving.set(false);
          this.saveStatus.set('error');
          this.saveMessage.set(
            ensureNormalizedBackendError(error, {
              fallbackMessage: 'Error al guardar preferencias',
            }).message,
          );
        },
      });
  }

  onToggleTwoFactor(): void {
    const currentProfile = this.profile();
    if (!currentProfile) {
      return;
    }

    this.pendingTwoFactorEnabled.set(!currentProfile.two_factor_enabled);
    this.twoFactorToggleError.set(null);
    this.twoFactorSuccessMessage.set(null);
    this.password.set('');
    this.showPasswordModal.set(true);
  }

  onPasswordConfirm(password: string): void {
    if (!password) {
      return;
    }
    this.twoFactorToggleLoading.set(true);
    this.twoFactorToggleError.set(null);

    this.authApiService.toggleTwoFactor(this.pendingTwoFactorEnabled(), password).subscribe({
      next: (response) => {
        this.twoFactorToggleLoading.set(false);
        this.showPasswordModal.set(false);
        this.twoFactorToggleError.set(null);
        this.pendingTwoFactorEnabled.set(false);
        this.password.set('');

        const currentProfile = this.profile();
        if (currentProfile) {
          this.profile.set({
            ...currentProfile,
            two_factor_enabled: response.data.two_factor_enabled,
          });
        }

        this.twoFactorSuccessMessage.set(
          response.data.two_factor_enabled
            ? this.translate.instant('profile.security.2fa_enabled_success')
            : this.translate.instant('profile.security.2fa_disabled_success'),
        );
      },
      error: (error) => {
        this.twoFactorToggleLoading.set(false);
        const normalizedError = ensureNormalizedBackendError(error);

        if (normalizedError.fieldErrors?.['password']) {
          this.twoFactorToggleError.set(normalizedError.fieldErrors['password'][0]);
        } else {
          this.twoFactorToggleError.set(normalizedError.message);
        }
      },
    });
  }

  onPasswordCancel(): void {
    this.showPasswordModal.set(false);
    this.twoFactorToggleError.set(null);
    this.twoFactorToggleLoading.set(false);
    this.pendingTwoFactorEnabled.set(false);
    this.password.set('');
  }

  submitPasswordForm(): void {
    this.onPasswordConfirm(this.password());
  }
}
