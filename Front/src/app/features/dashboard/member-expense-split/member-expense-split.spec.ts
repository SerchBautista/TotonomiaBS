import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { MemberExpenseSplitComponent } from './member-expense-split';
import { AnalyticsService } from '../../../core/services/analytics.service';

const mockSplitData = {
  data: {
    month: '2026-06',
    total: '1000.00',
    member_count: 2,
    fair_share: '500.00',
    members: [
      { id: '1', name: 'Alice', paid: '700.00', balance: '200.00' },
      { id: '2', name: 'Bob', paid: '300.00', balance: '-200.00' },
    ],
    settlements: [
      { from_id: '2', from_name: 'Bob', to_id: '1', to_name: 'Alice', amount: '200.00' },
    ],
  },
};

describe('MemberExpenseSplitComponent', () => {
  let component: MemberExpenseSplitComponent;
  let fixture: ComponentFixture<MemberExpenseSplitComponent>;
  let analyticsServiceMock: { memberSplit: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    vi.setSystemTime(new Date('2026-06-15T00:00:00Z'));

    analyticsServiceMock = {
      memberSplit: vi.fn().mockReturnValue(of(mockSplitData)),
    };

    await TestBed.configureTestingModule({
      imports: [MemberExpenseSplitComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: AnalyticsService, useValue: analyticsServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(MemberExpenseSplitComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('currencyCode', 'USD');
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should create the component', () => {
    expect(component).toBeTruthy();
  });

  it('should load member split data on init', () => {
    expect(analyticsServiceMock.memberSplit).toHaveBeenCalledWith('ws-1', 2026, 6);
    expect(component.splitData()).toEqual(mockSplitData.data);
  });

  it('should format the month label using the current language', () => {
    expect(component.monthLabel().toLowerCase()).toContain('junio');
  });

  it('should add accessible labels to all visible month navigation buttons', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const buttons = compiled.querySelectorAll('.month-btn');
    expect(buttons.length).toBeGreaterThanOrEqual(2);
    buttons.forEach((btn) => {
      expect(btn.getAttribute('aria-label')).toBeTruthy();
    });
  });

  it('should hide icons from assistive technology', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const icons = compiled.querySelectorAll('.month-nav i');
    icons.forEach((icon) => {
      expect(icon.getAttribute('aria-hidden')).toBe('true');
    });
  });
});
