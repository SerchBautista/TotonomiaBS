import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth-guard';
import { emailVerifiedGuard } from './core/guards/email-verified-guard';
import { guestGuard } from './core/guards/guest-guard';
import { pendingVerificationGuard } from './core/guards/pending-verification-guard';
import { roleGuard } from './core/guards/role-guard';
import { twoFactorSessionGuard } from './core/guards/two-factor-session-guard';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./features/learn/learn-hub/learn-hub').then((m) => m.LearnHubComponent),
  },
  {
    path: 'learn',
    loadComponent: () =>
      import('./features/learn/learn-hub/learn-hub').then((m) => m.LearnHubComponent),
  },
  {
    path: 'learn/registro-de-gastos',
    pathMatch: 'full',
    redirectTo: 'learn/expense-tracking',
  },
  {
    path: 'learn/presupuestos',
    pathMatch: 'full',
    redirectTo: 'learn/budgets',
  },
  {
    path: 'learn/deuda',
    pathMatch: 'full',
    redirectTo: 'learn/debt',
  },
  {
    path: 'learn/consejos',
    pathMatch: 'full',
    redirectTo: 'learn/tips',
  },
  {
    path: 'learn/feature/:slug',
    loadComponent: () =>
      import('./features/learn/learn-feature/learn-feature').then((m) => m.LearnFeatureComponent),
  },
  {
    path: 'learn/:slug',
    loadComponent: () =>
      import('./features/learn/learn-topic/learn-topic').then((m) => m.LearnTopicComponent),
  },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./features/auth/user-login/user-login').then((m) => m.UserLoginComponent),
  },
  {
    path: 'user/login',
    pathMatch: 'full',
    redirectTo: 'login',
  },
  {
    path: 'register',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./features/auth/register/register').then((m) => m.RegisterComponent),
  },
  {
    path: 'user/register',
    pathMatch: 'full',
    redirectTo: 'register',
  },
  {
    path: 'user/verify-email-pending',
    canActivate: [pendingVerificationGuard],
    loadComponent: () =>
      import('./features/auth/verify-email-pending/verify-email-pending').then(
        (m) => m.VerifyEmailPendingComponent,
      ),
  },
  {
    path: 'forgot-password',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./features/auth/forgot-password/forgot-password').then(
        (m) => m.ForgotPasswordComponent,
      ),
  },
  {
    path: 'user/forgot-password',
    pathMatch: 'full',
    redirectTo: 'forgot-password',
  },
  {
    path: 'user/reset-password',
    loadComponent: () =>
      import('./features/auth/reset-password/reset-password').then((m) => m.ResetPasswordComponent),
  },
  {
    path: 'user/verify-email',
    loadComponent: () =>
      import('./features/auth/verify-email/verify-email').then((m) => m.VerifyEmailComponent),
  },
  {
    path: 'user/verify-2fa',
    canActivate: [twoFactorSessionGuard],
    loadComponent: () =>
      import('./features/auth/two-factor-verify/two-factor-verify').then(
        (m) => m.TwoFactorVerifyComponent,
      ),
  },
  {
    path: 'user/profile',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/profile/profile/profile').then((m) => m.ProfileComponent),
  },
  {
    path: 'user/dashboard',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/dashboard/user-dashboard/user-dashboard').then(
        (m) => m.UserDashboardComponent,
      ),
  },
  {
    path: 'user/expenses',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/expenses/expense-list/expense-list').then((m) => m.ExpenseListComponent),
  },
  {
    path: 'user/expenses/create',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login', mode: 'create' },
    loadComponent: () =>
      import('./features/expenses/expense-form/expense-form').then((m) => m.ExpenseFormComponent),
  },
  {
    path: 'user/expenses/:eid/edit',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login', mode: 'edit' },
    loadComponent: () =>
      import('./features/expenses/expense-form/expense-form').then((m) => m.ExpenseFormComponent),
  },
  {
    path: 'user/fixed-expenses',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/fixed-expenses/fixed-expense-list/fixed-expense-list').then(
        (m) => m.FixedExpenseListComponent,
      ),
  },
  {
    path: 'user/notifications',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/notifications/notification-list/notification-list').then(
        (m) => m.NotificationListComponent,
      ),
  },
  {
    path: 'user/pending-payments',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/pending-payments/pending-payment-list/pending-payment-list').then(
        (m) => m.PendingPaymentListComponent,
      ),
  },
  {
    path: 'user/settings',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/settings/settings-shell/settings-shell').then(
        (m) => m.SettingsShellComponent,
      ),
    children: [
      {
        path: 'workspaces',
        loadComponent: () =>
          import('./features/workspaces/workspace-list/workspace-list').then(
            (m) => m.WorkspaceListComponent,
          ),
      },
      {
        path: 'workspaces/create',
        data: { mode: 'create' },
        loadComponent: () =>
          import('./features/workspaces/workspace-form/workspace-form').then(
            (m) => m.WorkspaceFormComponent,
          ),
      },
      {
        path: 'workspaces/:id/edit',
        data: { mode: 'edit' },
        loadComponent: () =>
          import('./features/workspaces/workspace-form/workspace-form').then(
            (m) => m.WorkspaceFormComponent,
          ),
      },
      {
        path: 'categories',
        loadComponent: () =>
          import('./features/categories/category-list/category-list').then(
            (m) => m.CategoryListComponent,
          ),
      },
      {
        path: 'category-sharing',
        pathMatch: 'full',
        redirectTo: 'categories',
      },
      {
        path: 'payment-methods',
        loadComponent: () =>
          import('./features/payment-methods/payment-method-list/payment-method-list').then(
            (m) => m.PaymentMethodListComponent,
          ),
      },
      {
        path: 'budgets',
        loadComponent: () =>
          import('./features/budgets/budget-settings/budget-settings').then(
            (m) => m.BudgetSettingsComponent,
          ),
      },
    ],
  },
  {
    path: 'user/workspaces',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/workspaces/workspace-list/workspace-list').then(
        (m) => m.WorkspaceListComponent,
      ),
  },
  {
    path: 'user/workspaces/create',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login', mode: 'create' },
    loadComponent: () =>
      import('./features/workspaces/workspace-form/workspace-form').then(
        (m) => m.WorkspaceFormComponent,
      ),
  },
  {
    path: 'user/workspaces/:id/edit',
    pathMatch: 'full',
    redirectTo: '/user/workspaces/:id/expenses',
  },
  {
    path: 'user/workspaces/:id/members',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/workspaces/workspace-members/workspace-members').then(
        (m) => m.WorkspaceMembersComponent,
      ),
  },
  {
    path: 'user/workspaces/:id/manage-categories',
    pathMatch: 'full',
    redirectTo: 'user/workspaces/:id/categories',
  },
  {
    path: 'user/workspaces/:id',
    canActivate: [authGuard, emailVerifiedGuard, roleGuard],
    data: { roles: ['user'], loginRedirect: '/login' },
    loadComponent: () =>
      import('./features/workspaces/workspace-detail/workspace-detail').then(
        (m) => m.WorkspaceDetailComponent,
      ),
    children: [
      {
        path: 'expenses',
        loadComponent: () =>
          import('./features/expenses/expense-list/expense-list').then(
            (m) => m.ExpenseListComponent,
          ),
      },
      {
        path: 'expenses/create',
        data: { mode: 'create' },
        loadComponent: () =>
          import('./features/expenses/expense-form/expense-form').then(
            (m) => m.ExpenseFormComponent,
          ),
      },
      {
        path: 'expenses/:eid/edit',
        data: { mode: 'edit' },
        loadComponent: () =>
          import('./features/expenses/expense-form/expense-form').then(
            (m) => m.ExpenseFormComponent,
          ),
      },
      {
        path: 'categories',
        loadComponent: () =>
          import('./features/workspaces/workspace-categories/workspace-categories').then(
            (m) => m.WorkspaceCategoriesComponent,
          ),
      },
      {
        path: 'category-sharing',
        pathMatch: 'full',
        redirectTo: 'categories',
      },
      {
        path: 'payment-methods',
        loadComponent: () =>
          import('./features/payment-methods/workspace-payment-methods/workspace-payment-methods').then(
            (m) => m.WorkspacePaymentMethodsComponent,
          ),
      },
      {
        path: 'fixed-expenses',
        loadComponent: () =>
          import('./features/fixed-expenses/fixed-expense-list/fixed-expense-list').then(
            (m) => m.FixedExpenseListComponent,
          ),
      },
      {
        path: 'pending-payments',
        loadComponent: () =>
          import('./features/pending-payments/pending-payment-list/pending-payment-list').then(
            (m) => m.PendingPaymentListComponent,
          ),
      },
      {
        path: 'budgets',
        loadComponent: () =>
          import('./features/budgets/budget-settings/budget-settings').then(
            (m) => m.BudgetSettingsComponent,
          ),
      },
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'expenses',
      },
    ],
  },
  {
    path: 'admin/login',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./features/auth/admin-login/admin-login').then((m) => m.AdminLoginComponent),
  },
  {
    path: 'admin/dashboard',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin'], loginRedirect: '/admin/login' },
    loadComponent: () =>
      import('./features/admin/dashboard/dashboard').then((m) => m.DashboardComponent),
  },
  {
    path: 'admin/users',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin'], loginRedirect: '/admin/login' },
    loadComponent: () =>
      import('./features/admin/users/user-list/user-list').then((m) => m.UserListComponent),
  },
  {
    path: 'admin/users/:id',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin'], loginRedirect: '/admin/login' },
    loadComponent: () =>
      import('./features/admin/users/user-detail/user-detail').then((m) => m.UserDetailComponent),
  },
  {
    path: 'admin/administrators',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin'], loginRedirect: '/admin/login' },
    loadComponent: () =>
      import('./features/admin/administrators/administrator-list/administrator-list').then(
        (m) => m.AdministratorListComponent,
      ),
  },
  {
    path: 'admin/administrators/create',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin'], loginRedirect: '/admin/login', mode: 'create' },
    loadComponent: () =>
      import('./features/admin/administrators/administrator-form/administrator-form').then(
        (m) => m.AdministratorFormComponent,
      ),
  },
  {
    path: 'admin/administrators/:id',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin'], loginRedirect: '/admin/login', mode: 'view' },
    loadComponent: () =>
      import('./features/admin/administrators/administrator-form/administrator-form').then(
        (m) => m.AdministratorFormComponent,
      ),
  },
  {
    path: 'admin/administrators/:id/edit',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin'], loginRedirect: '/admin/login', mode: 'edit' },
    loadComponent: () =>
      import('./features/admin/administrators/administrator-form/administrator-form').then(
        (m) => m.AdministratorFormComponent,
      ),
  },
  {
    path: 'pricing',
    loadComponent: () =>
      import('./features/pricing/pricing-page').then((m) => m.PricingPageComponent),
  },
  {
    path: 'pricing/success',
    loadComponent: () =>
      import('./features/pricing/success/pricing-success').then((m) => m.PricingSuccessComponent),
  },
  {
    path: 'profile',
    pathMatch: 'full',
    redirectTo: 'user/profile',
  },
  {
    path: '**',
    redirectTo: '',
  },
];
