import { describe, expect, it } from 'vitest';
import { resolvePageTitle } from './page-title';

describe('resolvePageTitle', () => {
  it('maps /user/dashboard to Dashboard / Overview breadcrumb', () => {
    expect(resolvePageTitle('/user/dashboard')).toEqual({
      parent: 'topbar.section.dashboard',
      child: 'topbar.section.overview',
    });
  });

  it('maps /user/expenses to Gastos (parent and child identical)', () => {
    expect(resolvePageTitle('/user/expenses')).toEqual({
      parent: 'expenses.title',
      child: 'expenses.title',
    });
  });

  it('maps /user/expenses/create to Gastos / Registrar gasto', () => {
    expect(resolvePageTitle('/user/expenses/create')).toEqual({
      parent: 'expenses.title',
      child: 'expenses.form_create_title',
    });
  });

  it('maps /user/expenses/:eid/edit to Gastos / Editar gasto', () => {
    expect(resolvePageTitle('/user/expenses/123/edit')).toEqual({
      parent: 'expenses.title',
      child: 'expenses.form_edit_title',
    });
  });

  it('maps /user/fixed-expenses to Gastos fijos', () => {
    expect(resolvePageTitle('/user/fixed-expenses')).toEqual({
      parent: 'fixed_expenses.title',
      child: 'fixed_expenses.title',
    });
  });

  it('maps /user/notifications to Notificaciones', () => {
    expect(resolvePageTitle('/user/notifications')).toEqual({
      parent: 'notifications.title',
      child: 'notifications.title',
    });
  });

  it('maps /user/pending-payments to Por pagar', () => {
    expect(resolvePageTitle('/user/pending-payments')).toEqual({
      parent: 'pending_payments.title',
      child: 'pending_payments.title',
    });
  });

  it('maps /user/profile to Perfil', () => {
    expect(resolvePageTitle('/user/profile')).toEqual({
      parent: 'profile.title',
      child: 'profile.title',
    });
  });

  it('maps /user/settings to Configuracion (parent and child identical)', () => {
    expect(resolvePageTitle('/user/settings')).toEqual({
      parent: 'settings.title',
      child: 'settings.title',
    });
  });

  it('maps /user/settings/workspaces to Configuracion / Espacios', () => {
    expect(resolvePageTitle('/user/settings/workspaces')).toEqual({
      parent: 'settings.title',
      child: 'workspaces.title',
    });
  });

  it('maps /user/settings/budgets to Configuracion / Presupuestos', () => {
    expect(resolvePageTitle('/user/settings/budgets')).toEqual({
      parent: 'settings.title',
      child: 'budgets.title',
    });
  });

  it('maps /user/workspaces/:id/members to Espacios / Miembros', () => {
    expect(resolvePageTitle('/user/workspaces/42/members')).toEqual({
      parent: 'workspaces.title',
      child: 'members.title',
    });
  });

  it('maps /user/workspaces/:id/expenses to Espacios / Gastos', () => {
    expect(resolvePageTitle('/user/workspaces/42/expenses')).toEqual({
      parent: 'workspaces.title',
      child: 'expenses.title',
    });
  });

  it('maps /user/workspaces/:id/payment-methods to Espacios / Métodos del workspace', () => {
    expect(resolvePageTitle('/user/workspaces/42/payment-methods')).toEqual({
      parent: 'workspaces.title',
      child: 'workspace_payment_methods.title',
    });
  });

  it('maps /admin/dashboard to Panel de control / Panel de administración', () => {
    expect(resolvePageTitle('/admin/dashboard')).toEqual({
      parent: 'nav.dashboard',
      child: 'admin.dashboard.title',
    });
  });

  it('falls back to a generic Dashboard title for unknown routes', () => {
    expect(resolvePageTitle('/user/totally-unknown')).toEqual({
      parent: 'topbar.section.dashboard',
      child: 'topbar.section.dashboard',
    });
  });

  it('falls back to Dashboard for the root URL', () => {
    expect(resolvePageTitle('/')).toEqual({
      parent: 'topbar.section.dashboard',
      child: 'topbar.section.overview',
    });
  });

  it('ignores query string and hash when matching the URL', () => {
    expect(resolvePageTitle('/user/dashboard?foo=1#bar')).toEqual({
      parent: 'topbar.section.dashboard',
      child: 'topbar.section.overview',
    });
  });
});
