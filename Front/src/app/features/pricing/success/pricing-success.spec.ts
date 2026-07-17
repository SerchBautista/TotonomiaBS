import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { ApiService } from '../../../core/services/api';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { PricingSuccessComponent } from './pricing-success';

describe('PricingSuccessComponent', () => {
  let fixture: ComponentFixture<PricingSuccessComponent>;
  let component: PricingSuccessComponent;
  let apiMock: { get: ReturnType<typeof vi.fn> };
  let authStateMock: { setPlan: ReturnType<typeof vi.fn> };
  let routerMock: { navigate: ReturnType<typeof vi.fn> };

  function setup(queryParams: Record<string, string> = {}) {
    apiMock = { get: vi.fn() };
    authStateMock = { setPlan: vi.fn() };
    routerMock = { navigate: vi.fn() };

    TestBed.configureTestingModule({
      imports: [PricingSuccessComponent],
      providers: [
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              queryParamMap: {
                get: (key: string) => queryParams[key] ?? null,
              },
            },
          },
        },
        { provide: Router, useValue: routerMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: ApiService, useValue: apiMock },
      ],
    });

    fixture = TestBed.createComponent(PricingSuccessComponent);
    component = fixture.componentInstance;
  }

  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.runOnlyPendingTimers();
    vi.useRealTimers();
    TestBed.resetTestingModule();
  });

  it('marks the premium plan immediately for dummy sessions', () => {
    setup({ dummy: 'true' });

    fixture.detectChanges();

    expect(component.isDummy()).toBe(true);
    expect(component.planConfirmed()).toBe(true);
    expect(authStateMock.setPlan).toHaveBeenCalledWith('premium');
    expect(apiMock.get).not.toHaveBeenCalled();
  });

  it('confirms the premium plan after the subscription poll succeeds', () => {
    setup();
    apiMock.get.mockReturnValue(of({ plan: 'premium', subscription_ends_at: null }));

    fixture.detectChanges();
    vi.advanceTimersByTime(3000);

    expect(apiMock.get).toHaveBeenCalledWith('/user/subscription');
    expect(component.planConfirmed()).toBe(true);
    expect(component.confirmationError()).toBeNull();
    expect(authStateMock.setPlan).toHaveBeenCalledWith('premium');
  });

  it('exposes a normalized confirmation error instead of silently falling back to free', () => {
    setup();
    apiMock.get.mockReturnValue(throwError(() => ({ status: 503, error: {} })));

    fixture.detectChanges();
    vi.advanceTimersByTime(3000);

    expect(component.planConfirmed()).toBe(false);
    expect(component.confirmationError()).toBe('No se pudo confirmar tu plan todavía.');
    expect(authStateMock.setPlan).not.toHaveBeenCalled();
  });
});
