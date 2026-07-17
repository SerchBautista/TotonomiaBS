import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { BudgetCategoryScopeStatus, BudgetScopeStatus, BudgetStatusResponse } from '../../../core/models/budget.model';
import { BudgetStatusWidgetComponent } from './budget-status-widget';

function buildStatus(
  overrides: Partial<{
    general: BudgetScopeStatus | null;
    categories: BudgetCategoryScopeStatus[];
  }> = {},
): BudgetStatusResponse {
  return {
    month: '2026-05',
    general:
      overrides.general === undefined
        ? null
        : overrides.general,
    categories: overrides.categories ?? [],
  };
}

function buildGeneralScope(
  overrides: Partial<BudgetScopeStatus> = {},
): BudgetScopeStatus {
  return {
    id: 'b-general',
    budget: '1000',
    spent: '900',
    remaining: '100',
    percentage: 0.9,
    alert_threshold: 0.8,
    alert_enabled: true,
    over_threshold: true,
    committed: '0',
    effective_spent: '900',
    ...overrides,
  };
}

function buildCategoryScope(
  overrides: Partial<BudgetCategoryScopeStatus> = {},
): BudgetCategoryScopeStatus {
  return {
    category_id: 'cat-1',
    category_name: 'Food',
    category_icon: 'tag',
    category_color: '#ff0000',
    has_budget: true,
    spent: '180',
    committed: '0',
    effective_spent: '180',
    budget: '200',
    effective_budget: '200',
    percentage: 0.9,
    alert_threshold: 0.8,
    alert_enabled: true,
    over_threshold: true,
    ...overrides,
  };
}

describe('BudgetStatusWidgetComponent', () => {
  let fixture: ComponentFixture<BudgetStatusWidgetComponent>;
  let component: BudgetStatusWidgetComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BudgetStatusWidgetComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        budgets: {
          general_budget: 'Presupuesto general',
          over_threshold: 'Cerca del límite',
          over_threshold_a11y: 'El presupuesto está cerca del límite configurado.',
          over_budget: 'Presupuesto excedido',
          spent: 'gastado',
          committed: 'comprometido',
          no_budget: 'Sin presupuesto',
          no_budgets: 'No hay presupuestos configurados.',
          has_adjustments: 'Ajustes',
        },
      },
      true,
    );
    translate.use('es');

    fixture = TestBed.createComponent(BudgetStatusWidgetComponent);
    component = fixture.componentInstance;
  });

  it('exposes the general over-label as a status region with the new a11y label', () => {
    fixture.componentRef.setInput('status', buildStatus({ general: buildGeneralScope() }));
    fixture.detectChanges();

    const overLabel = fixture.nativeElement.querySelector(
      '.budget-row .over-label[role="status"]',
    ) as HTMLElement | null;

    expect(overLabel).toBeTruthy();
    expect(overLabel?.getAttribute('aria-label')).toBe(
      'El presupuesto está cerca del límite configurado.',
    );
  });

  it('exposes the category over-label as a status region with the new a11y label', () => {
    fixture.componentRef.setInput(
      'status',
      buildStatus({ categories: [buildCategoryScope()] }),
    );
    fixture.detectChanges();

    const overLabel = fixture.nativeElement.querySelector(
      '.budget-row .over-label[role="status"]',
    ) as HTMLElement | null;

    expect(overLabel).toBeTruthy();
    expect(overLabel?.getAttribute('aria-label')).toBe(
      'El presupuesto está cerca del límite configurado.',
    );
  });

  it('does not render the over-label when the budget is below the alert threshold', () => {
    fixture.componentRef.setInput(
      'status',
      buildStatus({ general: buildGeneralScope({ over_threshold: false, percentage: 0.4 }) }),
    );
    fixture.detectChanges();

    const overLabel = fixture.nativeElement.querySelector('.over-label');
    expect(overLabel).toBeNull();
  });

  it('does not expose role="status" when alert is disabled, even if the threshold is exceeded', () => {
    fixture.componentRef.setInput(
      'status',
      buildStatus({
        general: buildGeneralScope({ alert_enabled: false, over_threshold: true }),
      }),
    );
    fixture.detectChanges();

    const overLabel = fixture.nativeElement.querySelector('.over-label[role="status"]');
    expect(overLabel).toBeNull();
  });
});
