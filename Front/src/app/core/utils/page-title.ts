// Mapeo de URL -> breadcrumb de 2 niveles para el topbar.
// Cada entrada resuelve un par (parent, child):
//   - parent: titulo del modulo/seccion (suele ser el primer segmento logico).
//   - child:  titulo concreto de la pagina actual.
// El `child` se traduce via las claves i18n existentes siempre que es posible
// (p. ej. `expenses.title`, `nav.dashboard_home`). Solo se anaden claves nuevas
// (`topbar.section.*`) para casos donde el titulo contextual difiere del de la
// navegacion o para el fallback generico del topbar.

export interface PageTitle {
  /** Clave i18n del primer nivel del breadcrumb. */
  readonly parent: string;
  /** Clave i18n del titulo concreto de la pagina (h1). */
  readonly child: string;
}

interface PatternEntry {
  readonly pattern: RegExp;
  readonly resolve: (match: RegExpMatchArray) => PageTitle;
}

const DASHBOARD_PARENT = 'topbar.section.dashboard';
const DASHBOARD_CHILD = 'topbar.section.overview';
const GENERIC_DASHBOARD = 'topbar.section.dashboard';

function exact(path: string, value: PageTitle): PageTitle | null {
  return value;
}

const PATTERNS: readonly PatternEntry[] = [
  // User area -----------------------------------------------------------
  {
    pattern: /^\/user\/dashboard\/?$/,
    resolve: () => ({ parent: DASHBOARD_PARENT, child: DASHBOARD_CHILD }),
  },
  {
    pattern: /^\/user\/expenses\/?$/,
    resolve: () => ({ parent: 'expenses.title', child: 'expenses.title' }),
  },
  {
    pattern: /^\/user\/expenses\/(create|[^/]+\/edit)\/?$/,
    resolve: (m) => {
      const isCreate = m[1] === 'create';
      return {
        parent: 'expenses.title',
        child: isCreate ? 'expenses.form_create_title' : 'expenses.form_edit_title',
      };
    },
  },
  {
    pattern: /^\/user\/fixed-expenses\/?$/,
    resolve: () => ({ parent: 'fixed_expenses.title', child: 'fixed_expenses.title' }),
  },
  {
    pattern: /^\/user\/notifications\/?$/,
    resolve: () => ({ parent: 'notifications.title', child: 'notifications.title' }),
  },
  {
    pattern: /^\/user\/pending-payments\/?$/,
    resolve: () => ({ parent: 'pending_payments.title', child: 'pending_payments.title' }),
  },
  {
    pattern: /^\/user\/profile\/?$/,
    resolve: () => ({ parent: 'profile.title', child: 'profile.title' }),
  },
  // Settings (parent + sub-seccion) ------------------------------------
  {
    pattern: /^\/user\/settings\/?$/,
    resolve: () => ({ parent: 'settings.title', child: 'settings.title' }),
  },
  {
    pattern: /^\/user\/settings\/workspaces\/?$/,
    resolve: () => ({ parent: 'settings.title', child: 'workspaces.title' }),
  },
  {
    pattern: /^\/user\/settings\/workspaces\/(create|[^/]+\/edit)\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'workspaces.form_create_title' }),
  },
  {
    pattern: /^\/user\/settings\/categories\/?$/,
    resolve: () => ({ parent: 'settings.title', child: 'categories.title' }),
  },
  {
    pattern: /^\/user\/settings\/payment-methods\/?$/,
    resolve: () => ({ parent: 'settings.title', child: 'payment_methods.title' }),
  },
  {
    pattern: /^\/user\/settings\/budgets\/?$/,
    resolve: () => ({ parent: 'settings.title', child: 'budgets.title' }),
  },
  // Standalone workspaces (lista fuera de settings) -------------------
  {
    pattern: /^\/user\/workspaces\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'workspaces.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/create\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'workspaces.form_create_title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/members\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'members.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/expenses\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'expenses.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/categories\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'workspace_categories.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/payment-methods\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'workspace_payment_methods.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/fixed-expenses\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'fixed_expenses.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/pending-payments\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'pending_payments.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/budgets\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'budgets.title' }),
  },
  {
    pattern: /^\/user\/workspaces\/[^/]+\/?$/,
    resolve: () => ({ parent: 'workspaces.title', child: 'workspaces.title' }),
  },
  // Admin area ----------------------------------------------------------
  {
    pattern: /^\/admin\/dashboard\/?$/,
    resolve: () => ({ parent: 'nav.dashboard', child: 'admin.dashboard.title' }),
  },
  {
    pattern: /^\/admin\/users\/?$/,
    resolve: () => ({ parent: 'admin.users.title', child: 'admin.users.title' }),
  },
  {
    pattern: /^\/admin\/users\/[^/]+\/?$/,
    resolve: () => ({ parent: 'admin.users.title', child: 'admin.users.field_name' }),
  },
  {
    pattern: /^\/admin\/administrators\/?$/,
    resolve: () => ({ parent: 'administrators.title', child: 'administrators.title' }),
  },
  {
    pattern: /^\/admin\/administrators\/(create|[^/]+(\/edit)?)\/?$/,
    resolve: (m) => {
      const isCreate = m[1] === 'create';
      const isEdit = /\/edit$/.test(m[1] ?? '');
      if (isCreate) {
        return { parent: 'administrators.title', child: 'administrators.form_create_title' };
      }
      if (isEdit) {
        return { parent: 'administrators.title', child: 'administrators.form_edit_title' };
      }
      return { parent: 'administrators.title', child: 'administrators.form_view_title' };
    },
  },
];

/** Normaliza la URL para emparejar: ignora query string y hash. */
export function normalizeUrl(url: string): string {
  return url.split('?')[0].split('#')[0];
}

export function resolvePageTitle(url: string): PageTitle {
  const path = normalizeUrl(url);
  if (path === '' || path === '/') {
    return { parent: GENERIC_DASHBOARD, child: DASHBOARD_CHILD };
  }
  for (const entry of PATTERNS) {
    const match = path.match(entry.pattern);
    if (match) {
      return entry.resolve(match);
    }
  }
  return { parent: GENERIC_DASHBOARD, child: GENERIC_DASHBOARD };
}
