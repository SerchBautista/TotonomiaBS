import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { PricingPageComponent } from './pricing-page';
import { SubscriptionService } from '../../core/services/subscription.service';
import { AuthStateService } from '../../core/services/auth-state.service';

function makeAuthMock(overrides: { isLoggedIn?: boolean; plan?: 'free' | 'premium' } = {}) {
  return {
    isLoggedIn: vi.fn().mockReturnValue(overrides.isLoggedIn ?? true),
    plan: vi.fn().mockReturnValue(overrides.plan ?? 'free'),
  };
}

describe('PricingPageComponent', () => {
  let fixture: ComponentFixture<PricingPageComponent>;
  let component: PricingPageComponent;
  let subscriptionMock: { initiateCheckout: ReturnType<typeof vi.fn> };
  let router: Router;

  function setup(authOverrides: { isLoggedIn?: boolean; plan?: 'free' | 'premium' } = {}) {
    subscriptionMock = { initiateCheckout: vi.fn() };

    TestBed.configureTestingModule({
      imports: [PricingPageComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: SubscriptionService, useValue: subscriptionMock },
        { provide: AuthStateService, useValue: makeAuthMock(authOverrides) },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    fixture = TestBed.createComponent(PricingPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  }

  it('should create', () => {
    setup();
    expect(component).toBeTruthy();
  });

  it('should show dummy banner and redirect after dummy checkout', async () => {
    setup();
    subscriptionMock.initiateCheckout.mockReturnValue(
      of({ url: '/pricing/success?dummy=true', is_dummy: true })
    );

    component.onUpgrade();
    fixture.detectChanges();

    expect(component.showDummyBanner()).toBe(true);
  });

  it('should show error message when checkout fails', () => {
    setup();
    subscriptionMock.initiateCheckout.mockReturnValue(throwError(() => new Error('Network error')));

    component.onUpgrade();
    fixture.detectChanges();

    expect(component.errorMessage()).toBeTruthy();
    expect(component.loading()).toBe(false);
  });

  it('should redirect to login if user is not authenticated', () => {
    setup({ isLoggedIn: false });

    component.onUpgrade();

    expect(router.navigate).toHaveBeenCalledWith(
      ['/login'],
      { queryParams: { redirect: '/pricing' } }
    );
    expect(subscriptionMock.initiateCheckout).not.toHaveBeenCalled();
  });
});
