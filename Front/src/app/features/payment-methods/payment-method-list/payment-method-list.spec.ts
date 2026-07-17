import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { BACKEND_ERROR_CODES } from '../../../core/errors/backend-error-codes';
import { UserPaymentMethodSummary } from '../../../core/models/payment-method.model';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { PaymentMethodCreateModalComponent } from '../payment-method-create-modal/payment-method-create-modal';
import { PaymentMethodEditModalComponent } from '../payment-method-edit-modal/payment-method-edit-modal';
import { PaymentMethodListComponent } from './payment-method-list';

const cardMethod: UserPaymentMethodSummary = {
  id: 'card-1',
  type: 'card',
  name: 'Visa personal',
  display_name: 'Visa personal',
  masked_details: '****4242',
  linked_workspaces_count: 2,
  linked_workspaces: [
    { id: 'ws-1', name: 'Casa' },
    { id: 'ws-2', name: 'Negocio' },
  ],
};

const otherMethod: UserPaymentMethodSummary = {
  id: 'other-1',
  type: 'other',
  name: 'Transferencia',
  display_name: 'Transferencia',
  masked_details: null,
  linked_workspaces_count: 1,
  linked_workspaces: [{ id: 'ws-1', name: 'Casa' }],
};

const initialMethods: UserPaymentMethodSummary[] = [cardMethod, otherMethod];

describe('PaymentMethodListComponent (user scope)', () => {
  let fixture: ComponentFixture<PaymentMethodListComponent>;
  let component: PaymentMethodListComponent;
  let paymentMethodsMock: {
    listMine: ReturnType<typeof vi.fn>;
    createMine: ReturnType<typeof vi.fn>;
    deleteMine: ReturnType<typeof vi.fn>;
    updateMine: ReturnType<typeof vi.fn>;
    updateWorkspaces: ReturnType<typeof vi.fn>;
  };

  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
  const authStateMock = { userId: vi.fn().mockReturnValue('user-1') };
  const workspaceContextMock = {
    ensureLoaded: vi.fn().mockResolvedValue([]),
    workspaces: vi.fn().mockReturnValue([{ id: 'ws-1', name: 'Casa', owner_id: 'user-1' }]),
  };

  function getCreateModal(): PaymentMethodCreateModalComponent {
    return fixture.debugElement.query(
      (el) => el.componentInstance instanceof PaymentMethodCreateModalComponent,
    )?.componentInstance as PaymentMethodCreateModalComponent;
  }

  function getEditModal(): PaymentMethodEditModalComponent {
    return fixture.debugElement.query(
      (el) => el.componentInstance instanceof PaymentMethodEditModalComponent,
    )?.componentInstance as PaymentMethodEditModalComponent;
  }

  async function setup(overrides?: Partial<typeof paymentMethodsMock>): Promise<void> {
    TestBed.resetTestingModule();

    paymentMethodsMock = {
      listMine: vi.fn().mockReturnValue(of({ data: initialMethods })),
      createMine: vi.fn().mockReturnValue(of({ data: cardMethod })),
      deleteMine: vi.fn().mockReturnValue(of(void 0)),
      updateMine: vi.fn().mockReturnValue(of({ data: cardMethod })),
      updateWorkspaces: vi.fn().mockReturnValue(of({ data: cardMethod })),
      ...overrides,
    };

    await TestBed.configureTestingModule({
      imports: [PaymentMethodListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: PaymentMethodsService, useValue: paymentMethodsMock },
        { provide: ToastService, useValue: toastMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PaymentMethodListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  }

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should create the component', async () => {
    await setup();
    expect(component).toBeTruthy();
  });

  it('should load user payment methods on init via listMine()', async () => {
    await setup();
    expect(paymentMethodsMock.listMine).toHaveBeenCalledTimes(1);
    expect(component.methods()).toEqual(initialMethods);
  });

  it('should filter methods by search term (name or last 4)', async () => {
    await setup();

    component.onSearchChange('visa');
    expect(component.filteredMethods().map((m) => m.id)).toEqual(['card-1']);

    component.onSearchChange('4242');
    expect(component.filteredMethods().map((m) => m.id)).toEqual(['card-1']);

    component.onSearchChange('transfer');
    expect(component.filteredMethods().map((m) => m.id)).toEqual(['other-1']);

    component.onSearchChange('');
    expect(component.filteredMethods()).toEqual(initialMethods);
  });

  it('should render table rows for the loaded methods', async () => {
    await setup();
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    const rows = html.querySelectorAll('tbody tr');
    expect(rows.length).toBe(2);
    expect(html.textContent).toContain('Visa personal');
    expect(html.textContent).toContain('Transferencia');
  });

  it('should open and close the create modal via toggleCreateForm()', async () => {
    await setup();

    expect(component.showForm()).toBe(false);
    component.toggleCreateForm();
    expect(component.showForm()).toBe(true);
    component.toggleCreateForm();
    expect(component.showForm()).toBe(false);
  });

  it('should create a card via createMine() and refresh the list', async () => {
    await setup();

    component.toggleCreateForm();
    fixture.detectChanges();
    const createModal = getCreateModal();
    createModal.form.setValue({
      type: 'card',
      name: 'Visa compartida',
      card_type: 'credit',
      brand: 'Visa',
      last_4_digits: '4242',
      description: '',
    });

    createModal.submitCreate();
    await fixture.whenStable();

    expect(paymentMethodsMock.createMine).toHaveBeenCalledWith(
      {
        type: 'card',
        name: 'Visa compartida',
        card_type: 'credit',
        brand: 'Visa',
        last_4_digits: '4242',
        workspace_ids: ['ws-1'],
      },
      expect.any(Object),
    );
    expect(paymentMethodsMock.listMine).toHaveBeenCalledTimes(2);
    expect(component.showForm()).toBe(false);
  });

  it('should create an other payment method via createMine()', async () => {
    await setup();

    component.toggleCreateForm();
    fixture.detectChanges();
    const createModal = getCreateModal();
    createModal.form.controls.type.setValue('other');
    createModal.form.setValue({
      type: 'other',
      name: 'Transferencia nueva',
      card_type: 'credit',
      brand: '',
      last_4_digits: '',
      description: 'Banco principal',
    });

    createModal.submitCreate();
    await fixture.whenStable();

    expect(paymentMethodsMock.createMine).toHaveBeenCalledWith(
      {
        type: 'other',
        name: 'Transferencia nueva',
        description: 'Banco principal',
        workspace_ids: ['ws-1'],
      },
      expect.any(Object),
    );
  });

  it('should not call createMine() when the form is invalid', async () => {
    await setup();

    component.toggleCreateForm();
    fixture.detectChanges();
    const createModal = getCreateModal();
    createModal.form.setValue({
      type: 'card',
      name: '',
      card_type: 'credit',
      brand: '',
      last_4_digits: 'abcd',
      description: '',
    });

    createModal.submitCreate();
    expect(paymentMethodsMock.createMine).not.toHaveBeenCalled();
    expect(component.showForm()).toBe(true);
  });

  it('should delete a method and refresh the list after confirmation', async () => {
    await setup();

    component.requestDelete(cardMethod);
    expect(component.confirmOpen).toBe(true);
    expect(component.methodToDelete?.id).toBe('card-1');

    component.confirmDelete();
    await fixture.whenStable();

    expect(paymentMethodsMock.deleteMine).toHaveBeenCalledWith('card-1', expect.any(Object));
    expect(paymentMethodsMock.listMine).toHaveBeenCalledTimes(2);
    expect(toastMock.success).toHaveBeenCalled();
  });

  it('should cancel delete and not call the service', async () => {
    await setup();

    component.requestDelete(cardMethod);
    component.cancelDelete();
    expect(component.confirmOpen).toBe(false);
    expect(component.methodToDelete).toBeNull();
    expect(paymentMethodsMock.deleteMine).not.toHaveBeenCalled();
  });

  it('should not allow deleting the cash method', async () => {
    const cashMethod: UserPaymentMethodSummary = {
      id: 'cash',
      type: 'cash',
      name: 'Efectivo',
      display_name: 'Efectivo',
      masked_details: null,
    };
    await setup({
      listMine: vi.fn().mockReturnValue(of({ data: [cashMethod] })),
    });

    expect(component.canDelete(cashMethod)).toBe(false);
  });

  it('should show a friendly warning when delete returns 409 payment_method_in_use', async () => {
    await setup({
      deleteMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 409,
          error: {
            status: 409,
            code: 'payment_method_in_use',
            message: 'El método de pago está en uso.',
            request_id: 'req-pm-1',
          },
        })),
      ),
    });

    component.requestDelete(cardMethod);
    component.confirmDelete();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalledWith('El método de pago está en uso.');
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should show a friendly warning when create returns 409 user_has_no_default_workspace', async () => {
    await setup({
      createMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 409,
          error: {
            status: 409,
            code: 'user_has_no_default_workspace',
            message: 'Necesitas un workspace por defecto.',
            request_id: 'req-pm-2',
          },
        })),
      ),
    });

    component.toggleCreateForm();
    fixture.detectChanges();
    const createModal = getCreateModal();
    createModal.form.setValue({
      type: 'card',
      name: 'Tarjeta',
      card_type: 'credit',
      brand: '',
      last_4_digits: '1234',
      description: '',
    });

    createModal.submitCreate();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalledWith('Necesitas un workspace por defecto.');
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should show a friendly warning when create returns 403 email_not_verified', async () => {
    await setup({
      createMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 403,
          error: {
            status: 403,
            code: BACKEND_ERROR_CODES.authEmailNotVerified,
            message: 'Verifica tu correo.',
            request_id: 'req-pm-3',
          },
        })),
      ),
    });

    component.toggleCreateForm();
    fixture.detectChanges();
    const createModal = getCreateModal();
    createModal.form.setValue({
      type: 'card',
      name: 'Tarjeta',
      card_type: 'credit',
      brand: '',
      last_4_digits: '1234',
      description: '',
    });

    createModal.submitCreate();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalled();
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('should show a friendly warning on 429 rate limit', async () => {
    await setup({
      listMine: vi
        .fn()
        .mockReturnValue(throwError(() => ({ status: 429, error: { code: 'too_many_requests' } }))),
    });
    toastMock.warning.mockClear();

    component.ngOnInit();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalled();
  });

  // ------------------------------------------------------------------
  // Edit flow
  // ------------------------------------------------------------------

  it('should expose an edit button on non-cash rows', async () => {
    await setup();
    expect(component.canEdit(cardMethod)).toBe(true);
    expect(component.canEdit(otherMethod)).toBe(true);
  });

  it('should not allow editing the cash method', async () => {
    const cashMethod: UserPaymentMethodSummary = {
      id: 'cash',
      type: 'cash',
      name: 'Efectivo',
      display_name: 'Efectivo',
      masked_details: null,
    };
    await setup();
    expect(component.canEdit(cashMethod)).toBe(false);
  });

  it('startEdit() should open the edit modal with pre-filled values for a card', async () => {
    await setup();
    expect(component.editingMethod()).toBeNull();

    component.startEdit(cardMethod);
    fixture.detectChanges();
    const editModal = getEditModal();

    expect(component.editingMethod()?.id).toBe('card-1');
    expect(editModal.form.controls.name.value).toBe('Visa personal');
    expect(editModal.form.controls.type.value).toBe('card');
    expect(editModal.form.controls.last_4_digits.value).toBe('4242');
  });

  it('startEdit() should pre-fill non-card type and clear card fields for an other method', async () => {
    await setup();
    component.startEdit(otherMethod);
    fixture.detectChanges();
    const editModal = getEditModal();

    expect(component.editingMethod()?.id).toBe('other-1');
    expect(editModal.form.controls.name.value).toBe('Transferencia');
    expect(editModal.form.controls.type.value).toBe('other');
  });

  it('cancelEdit() should close the edit modal and reset the form', async () => {
    await setup();
    component.startEdit(cardMethod);
    fixture.detectChanges();
    expect(component.editingMethod()).not.toBeNull();

    component.onEditClosed();

    expect(component.editingMethod()).toBeNull();
  });

  it('submitEdit() should call updateMine() with PUT semantics and refresh the list', async () => {
    await setup();
    component.startEdit(cardMethod);
    fixture.detectChanges();
    const editModal = getEditModal();
    editModal.form.controls.name.setValue('Visa Plus');
    editModal.form.controls.brand.setValue('Visa Gold');
    editModal.form.controls.card_type.setValue('debit');

    editModal.submitEdit();
    await fixture.whenStable();

    expect(paymentMethodsMock.updateMine).toHaveBeenCalledWith(
      'card-1',
      {
        type: 'card',
        name: 'Visa Plus',
        card_type: 'debit',
        brand: 'Visa Gold',
        last_4_digits: '4242',
        workspace_ids: ['ws-1', 'ws-2'],
      },
      expect.any(Object),
    );
    expect(paymentMethodsMock.listMine).toHaveBeenCalledTimes(2);
    expect(component.editingMethod()).toBeNull();
  });

  it('submitEdit() should not call updateMine() when the form is invalid', async () => {
    await setup();
    component.startEdit(cardMethod);
    fixture.detectChanges();
    const editModal = getEditModal();
    editModal.form.controls.name.setValue('');
    editModal.form.controls.last_4_digits.setValue('abcd');

    editModal.submitEdit();

    expect(paymentMethodsMock.updateMine).not.toHaveBeenCalled();
    expect(component.editingMethod()).not.toBeNull();
  });

  it('submitEdit() should show a warning toast on 422 validation', async () => {
    await setup({
      updateMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 422,
          error: {
            status: 422,
            code: 'validation_error',
            message: 'Los datos enviados no son válidos.',
            request_id: 'req-pm-4',
          },
        })),
      ),
    });

    component.startEdit(cardMethod);
    fixture.detectChanges();
    getEditModal().submitEdit();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalled();
    expect(toastMock.error).not.toHaveBeenCalled();
    expect(component.editingMethod()).not.toBeNull();
  });

  it('submitEdit() should show a warning toast on 404 user_payment_method_not_found', async () => {
    await setup({
      updateMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 404,
          error: {
            status: 404,
            code: 'user_payment_method_not_found',
            message: 'No se encontró el método de pago.',
            request_id: 'req-pm-5',
          },
        })),
      ),
    });

    component.startEdit(cardMethod);
    fixture.detectChanges();
    getEditModal().submitEdit();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalledWith('No se encontró el método de pago.');
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('submitEdit() should show a friendly warning on 429 rate limit', async () => {
    await setup({
      updateMine: vi
        .fn()
        .mockReturnValue(throwError(() => ({ status: 429, error: { code: 'too_many_requests' } }))),
    });

    component.startEdit(cardMethod);
    fixture.detectChanges();
    getEditModal().submitEdit();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalled();
  });

  it('submitEdit() should show a friendly warning on 403 email_not_verified', async () => {
    await setup({
      updateMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 403,
          error: {
            status: 403,
            code: BACKEND_ERROR_CODES.authEmailNotVerified,
            message: 'Verifica tu correo.',
            request_id: 'req-pm-6',
          },
        })),
      ),
    });

    component.startEdit(cardMethod);
    fixture.detectChanges();
    getEditModal().submitEdit();
    await fixture.whenStable();

    expect(toastMock.warning).toHaveBeenCalled();
    expect(toastMock.error).not.toHaveBeenCalled();
  });

  it('submitEdit() should apply field-level errors to the edit form on 422', async () => {
    await setup({
      updateMine: vi.fn().mockReturnValue(
        throwError(() => ({
          status: 422,
          error: {
            status: 422,
            code: 'validation_error',
            message: 'Datos inválidos.',
            request_id: 'req-pm-7',
            fieldErrors: {
              name: ['El nombre es obligatorio.'],
              last_4_digits: ['Solo 4 dígitos.'],
            },
          },
        })),
      ),
    });

    component.startEdit(cardMethod);
    fixture.detectChanges();
    const editModal = getEditModal();
    editModal.submitEdit();
    await fixture.whenStable();

    expect(editModal.form.controls.name.errors?.['serverError']).toBe('El nombre es obligatorio.');
    expect(editModal.form.controls.last_4_digits.errors?.['serverError']).toBe('Solo 4 dígitos.');
  });
});
