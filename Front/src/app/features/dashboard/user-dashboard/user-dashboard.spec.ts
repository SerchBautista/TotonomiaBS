import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { computed, signal } from '@angular/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { UserDashboardComponent } from './user-dashboard';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { BudgetsService } from '../../../core/services/budgets.service';
import { AnalyticsService } from '../../../core/services/analytics.service';
import { QuickAddExpenseService } from '../../../core/services/quick-add-expense.service';
import { ToastService } from '../../../core/services/toast.service';
import { AUTH_STATE_TOKEN } from '../../../core/tokens/auth-state.token';

const mockBudgetStatus = {
  data: {
    month: '2026-06',
    general: null,
    categories: [],
  },
};

const mockHeatmap = {
  data: [],
};

const mockSummary = {
  data: {
    total: '0.00',
    period: { from: '2026-06-01', to: '2026-06-30' },
    by_category: [],
    by_payment_method: [],
  },
};

describe('UserDashboardComponent', () => {
  let fixture: ComponentFixture<UserDashboardComponent>;
  let component: UserDashboardComponent;
  let budgetsServiceMock: { status: ReturnType<typeof vi.fn> };
  let analyticsServiceMock: {
    heatmap: ReturnType<typeof vi.fn>;
    summary: ReturnType<typeof vi.fn>;
  };
  let toastServiceMock: { error: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    vi.setSystemTime(new Date('2026-06-15T00:00:00Z'));

    budgetsServiceMock = {
      status: vi.fn().mockReturnValue(of(mockBudgetStatus)),
    };
    analyticsServiceMock = {
      heatmap: vi.fn().mockReturnValue(of(mockHeatmap)),
      summary: vi.fn().mockReturnValue(of(mockSummary)),
    };
    toastServiceMock = { error: vi.fn() };

    const wsId = signal<string | null>('ws-1');

    await TestBed.configureTestingModule({
      imports: [UserDashboardComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: WorkspaceContextService,
          useValue: {
            workspaces: computed(() => [{ id: wsId() ?? 'ws-1', name: 'Test WS' }]),
            selectedWorkspace: computed(() => ({
              id: wsId() ?? 'ws-1',
              name: 'Test WS',
              currency_code: 'USD',
            })),
            currentWorkspaceId: wsId.asReadonly(),
            setCurrentWorkspaceId: (id: string | null) => wsId.set(id),
            ensureLoaded: vi.fn().mockResolvedValue([]),
          },
        },
        { provide: BudgetsService, useValue: budgetsServiceMock },
        { provide: AnalyticsService, useValue: analyticsServiceMock },
        { provide: QuickAddExpenseService, useValue: { open$: of() } },
        {
          provide: ToastService,
          useValue: toastServiceMock,
        },
        { provide: AUTH_STATE_TOKEN, useValue: { role: () => 'user' } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(UserDashboardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should create the component', () => {
    expect(component).toBeTruthy();
  });

  it('resets budgetStatus and heatmapData and reloads when current workspace changes', async () => {
    const workspaceContext = (fixture.componentInstance as any).workspaceContext;
    workspaceContext.setCurrentWorkspaceId('ws-2');
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(workspaceContext.currentWorkspaceId()).toBe('ws-2');

    const budgetCalls = budgetsServiceMock.status.mock.calls;
    expect(budgetCalls.some((call: any) => call[0] === 'ws-2')).toBe(true);

    const heatmapCalls = analyticsServiceMock.heatmap.mock.calls;
    expect(heatmapCalls.some((call: any) => call[0] === 'ws-2')).toBe(true);

    const summaryCalls = analyticsServiceMock.summary.mock.calls;
    expect(summaryCalls.some((call: any) => call[0] === 'ws-2')).toBe(true);
  });

  it('should load summary on initial render', () => {
    expect(analyticsServiceMock.summary).toHaveBeenCalledWith('ws-1', '2026-06-01', '2026-06-30');
  });

  it('should reload summary and heatmap when the user changes the dashboard month', () => {
    analyticsServiceMock.heatmap.mockClear();
    analyticsServiceMock.summary.mockClear();

    component.changeMonth(-1);

    expect(analyticsServiceMock.heatmap).toHaveBeenCalledWith('ws-1', 2026, 5);
    expect(analyticsServiceMock.summary).toHaveBeenCalledWith('ws-1', '2026-05-01', '2026-05-31');
  });

  it('should reload summary and heatmap when the user resets the dashboard month', () => {
    component.changeMonth(-1);
    analyticsServiceMock.heatmap.mockClear();
    analyticsServiceMock.summary.mockClear();

    component.resetHeatmapMonth();

    expect(analyticsServiceMock.heatmap).toHaveBeenCalledWith('ws-1', 2026, 6);
    expect(analyticsServiceMock.summary).toHaveBeenCalledWith('ws-1', '2026-06-01', '2026-06-30');
  });

  it('should not reload summary or heatmap when the user changes the budget month', () => {
    analyticsServiceMock.heatmap.mockClear();
    analyticsServiceMock.summary.mockClear();

    component.changeBudgetMonth(-1);

    expect(budgetsServiceMock.status).toHaveBeenCalledWith('ws-1', '2026-05');
    expect(analyticsServiceMock.heatmap).not.toHaveBeenCalled();
    expect(analyticsServiceMock.summary).not.toHaveBeenCalled();
  });

  it('should open the detail modal with the correct filter when breakdown emits openDetail', () => {
    component.onBreakdownDetailOpen({ filter: 'others' });

    expect(component.detailModalOpen()).toBe(true);
    expect(component.detailModalFilter()).toBe('others');

    component.closeBreakdownDetail();

    expect(component.detailModalOpen()).toBe(false);
  });

  it('should set summaryLoading to false when summary request fails', () => {
    analyticsServiceMock.summary.mockReturnValueOnce(throwError(() => new Error('network')));

    component.changeMonth(1);

    expect(component.summaryLoading()).toBe(false);
    expect(toastServiceMock.error).not.toHaveBeenCalled();
  });

  it('should set heatmapLoading to false when heatmap request fails', () => {
    analyticsServiceMock.heatmap.mockReturnValueOnce(throwError(() => new Error('network')));

    component.changeMonth(1);

    expect(component.heatmapLoading()).toBe(false);
    expect(toastServiceMock.error).not.toHaveBeenCalled();
  });

  it('should build the summary "to" date from local year/month components without UTC drift', () => {
    component.changeMonth(-1);

    expect(analyticsServiceMock.summary).toHaveBeenCalledWith('ws-1', '2026-05-01', '2026-05-31');
  });

  it('should format month labels using the current translation language', () => {
    expect(component.monthLabel.toLowerCase()).toContain('junio');
    expect(component.budgetMonthLabel.toLowerCase()).toContain('junio');
  });
});
