import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter } from '@angular/router';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { BudgetAdjustmentsService } from '../../../core/services/budget-adjustments.service';
import { BudgetsService } from '../../../core/services/budgets.service';
import { CategoriesService } from '../../../core/services/categories';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { BudgetSettingsComponent } from './budget-settings';

describe('BudgetSettingsComponent', () => {
  let fixture: ComponentFixture<BudgetSettingsComponent>;
  let component: BudgetSettingsComponent;
  let categoriesServiceMock: { listValid: ReturnType<typeof vi.fn> };
  let budgetsServiceMock: {
    list: ReturnType<typeof vi.fn>;
    status: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    categoriesServiceMock = {
      listValid: vi.fn().mockReturnValue(
        of({
          data: [
            { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000' },
            { id: 'cat-2', user_id: 'user-1', name: 'Transport', icon: 'car', color: '#00ff00' },
          ],
        }),
      ),
    };

    budgetsServiceMock = {
      list: vi.fn().mockReturnValue(of({ data: [] })),
      status: vi.fn().mockReturnValue(
        of({
          data: {
            month: '2026-05',
            general: null,
            categories: [
              {
                category_id: 'cat-1',
                category_name: 'Food',
                category_icon: 'tag',
                category_color: '#ff0000',
                has_budget: true,
                spent: '50',
                committed: '0',
                effective_spent: '50',
              },
            ],
          },
        }),
      ),
      create: vi.fn(),
      update: vi.fn(),
      delete: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [BudgetSettingsComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              parent: {
                paramMap: { get: vi.fn().mockReturnValue('ws-1') },
              },
            },
          },
        },
        { provide: CategoriesService, useValue: categoriesServiceMock },
        { provide: BudgetsService, useValue: budgetsServiceMock },
        {
          provide: BudgetAdjustmentsService,
          useValue: {
            list: vi.fn(),
            create: vi.fn(),
            available: vi.fn().mockReturnValue(of({ data: [] })),
          },
        },
        {
          provide: ToastService,
          useValue: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
        },
        {
          provide: WorkspaceContextService,
          useValue: {
            ensureLoaded: vi.fn().mockResolvedValue([]),
            selectedWorkspace: signal({
              id: 'ws-1',
              owner_id: 'user-1',
              name: 'Workspace',
              type: 'personal',
              currency_code: 'USD',
              created_at: '',
              updated_at: '',
            }).asReadonly(),
            workspaces: signal([
              {
                id: 'ws-1',
                owner_id: 'user-1',
                name: 'Workspace',
                type: 'personal',
                currency_code: 'USD',
                created_at: '',
                updated_at: '',
              },
            ]).asReadonly(),
          },
        },
      ],
    }).compileComponents();

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        budgets: {
          alert_threshold: 'Monto de alerta',
          amount: 'Monto',
          alert_enabled: 'Activar alerta',
          save: 'Guardar',
          cancel: 'Cancelar',
          category: 'Categoría',
          select_category: 'Selecciona una categoría',
          set_budget: 'Establecer presupuesto',
          add_category_budget: 'Agregar presupuesto de categoría',
          adjust: 'Ajustar',
          general_budget: 'Presupuesto general',
          category_budgets: 'Presupuestos por categoría',
          no_category_budgets: 'Sin presupuestos por categoría',
        },
        common: {
          edit: 'Editar',
          delete: 'Eliminar',
          loading: 'Cargando',
        },
        expenses: {
          actions: 'Acciones',
        },
      },
      true,
    );
    translate.use('es');

    fixture = TestBed.createComponent(BudgetSettingsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads categories through listValid for transactional budget flow', () => {
    expect(categoriesServiceMock.listValid).toHaveBeenCalledWith('ws-1');
  });

  it('filters budget status categories using current valid categories in openAdjustmentModal', () => {
    component.categories.set([
      { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000' },
    ]);
    budgetsServiceMock.status.mockReturnValue(
      of({
        data: {
          month: '2026-05',
          general: null,
          categories: [
            {
              category_id: 'cat-1',
              category_name: 'Food',
              category_icon: 'tag',
              category_color: '#ff0000',
              has_budget: true,
              spent: '50',
              committed: '0',
              effective_spent: '50',
            },
            {
              category_id: 'cat-legacy',
              category_name: 'Legacy',
              category_icon: 'tag',
              category_color: '#000000',
              has_budget: true,
              spent: '25',
              committed: '0',
              effective_spent: '25',
            },
          ],
        },
      }),
    );

    component.openAdjustmentModal();

    expect(component.adjustmentModalOpen()).toBe(true);
    expect(component.adjustmentModalData().categories).toHaveLength(1);
    expect(component.adjustmentModalData().categories[0].category_id).toBe('cat-1');
  });

  it('renders the unified "Monto de alerta" label in the general budget form', () => {
    const setBudgetButton = fixture.nativeElement.querySelector(
      'app-budget-general-section button.btn.primary',
    ) as HTMLButtonElement;
    setBudgetButton.click();
    fixture.detectChanges();

    const generalAlertLabel = fixture.nativeElement.querySelector('label[for="general-alert"]');
    expect(generalAlertLabel).toBeTruthy();
    expect(generalAlertLabel.textContent).toContain('Monto de alerta');
  });

  it('renders the unified "Monto de alerta" label in the category budget form', () => {
    const addCategoryButton = fixture.nativeElement.querySelector(
      'app-budget-category-budgets-section button.btn.primary',
    ) as HTMLButtonElement;
    addCategoryButton.click();
    fixture.detectChanges();

    const categoryAlertLabel = fixture.nativeElement.querySelector('label[for="cat-new-alert"]');
    expect(categoryAlertLabel).toBeTruthy();
    expect(categoryAlertLabel.textContent).toContain('Monto de alerta');
  });

  it('does not render any legacy "Alerta de workspace" or "Alerta de categoría" string in the template', () => {
    const setBudgetButton = fixture.nativeElement.querySelector(
      'app-budget-general-section button.btn.primary',
    ) as HTMLButtonElement;
    const addCategoryButton = fixture.nativeElement.querySelector(
      'app-budget-category-budgets-section button.btn.primary',
    ) as HTMLButtonElement;
    setBudgetButton.click();
    addCategoryButton.click();
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).not.toContain('Alerta de workspace');
    expect(text).not.toContain('Alerta de categoría');
    expect(text).not.toContain('umbral de alerta');
  });

  it('applies optimistic budget updates from child section events', () => {
    const createdBudget = {
      id: 'budget-1',
      workspace_id: 'ws-1',
      category_id: null,
      amount: '100',
      effective_from: '2026-06-01',
      alert_threshold: '50',
      alert_enabled: true,
      created_at: '',
      updated_at: '',
    };

    component.onBudgetChanged({ action: 'created', budget: createdBudget });
    expect(component.generalBudget()?.id).toBe('budget-1');

    const updatedBudget = { ...createdBudget, amount: '200' };
    component.onBudgetChanged({ action: 'updated', budget: updatedBudget });
    expect(component.generalBudget()?.amount).toBe('200');

    component.onBudgetChanged({ action: 'deleted', budgetId: 'budget-1' });
    expect(component.generalBudget()).toBeUndefined();
  });
});
