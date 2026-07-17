import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { BudgetAdjustment } from '../../../core/models/budget-adjustment.model';
import { BudgetAdjustmentsService } from '../../../core/services/budget-adjustments.service';
import { BudgetAdjustmentHistoryModalComponent } from './budget-adjustment-history-modal';

const mockAdjustments = {
  data: [
    {
      id: 'adj-1',
      workspace_id: 'ws-1',
      user_id: 'user-1',
      month: '2026-06',
      from_category_id: 'cat-2',
      to_category_id: 'cat-1',
      amount: '100.00',
      reason: 'Rebalance',
      created_at: '2026-06-01T10:00:00Z',
      updated_at: '2026-06-01T10:00:00Z',
      from_category: { id: 'cat-2', name: 'Transport' },
      to_category: { id: 'cat-1', name: 'Food' },
    },
  ] as BudgetAdjustment[],
};

describe('BudgetAdjustmentHistoryModalComponent', () => {
  let fixture: ComponentFixture<BudgetAdjustmentHistoryModalComponent>;
  let component: BudgetAdjustmentHistoryModalComponent;
  let adjustmentsServiceMock: {
    list: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    adjustmentsServiceMock = {
      list: vi.fn().mockReturnValue(of(mockAdjustments)),
    };

    await TestBed.configureTestingModule({
      imports: [BudgetAdjustmentHistoryModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: BudgetAdjustmentsService, useValue: adjustmentsServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(BudgetAdjustmentHistoryModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('categoryId', 'cat-1');
    fixture.componentRef.setInput('categoryName', 'Food');
    fixture.detectChanges();
  });

  it('determines adjustment direction relative to the selected category', () => {
    const adjustment = mockAdjustments.data[0];

    expect(component.getDirection(adjustment)).toBe('in');
    expect(component.getCounterCategory(adjustment)).toBe('Transport');
  });

  it('loads adjustments when onMonthChange is called', () => {
    component.onMonthChange();

    expect(adjustmentsServiceMock.list).toHaveBeenCalledWith(
      'ws-1',
      expect.stringMatching(/^\d{4}-\d{2}$/),
      'cat-1',
    );
  });

  it('clears adjustments and emits closed when close is called', () => {
    component.adjustments.set(mockAdjustments.data);
    const spy = vi.fn();
    component.closed.subscribe(spy);

    component.close();

    expect(component.adjustments()).toEqual([]);
    expect(spy).toHaveBeenCalledOnce();
  });

  it('formats dates for display', () => {
    const formatted = component.formatDate('2026-06-01T10:00:00Z');
    expect(formatted).toBeTruthy();
    expect(formatted).toMatch(/2026/);
  });
});
