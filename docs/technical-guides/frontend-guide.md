# Guía Técnica — Frontend (Angular Web)

> **Para quién es esta guía:** desarrolladores que se incorporan al proyecto o que necesitan mantener la app web. Se asume conocimiento general de TypeScript y HTML, pero no experiencia previa con Angular ni con este proyecto.

---

## 1. ¿Qué hace el frontend?

El frontend es una **Single Page Application (SPA)** construida con Angular 21. Es la versión web de Totonomia — la misma funcionalidad pero en el navegador. Se conecta al backend exclusivamente a través de la API REST.

Es uno de los dos proyectos independientes:

| Repositorio | Tecnología | Propósito |
|-------------|-----------|-----------|
| `Front/` | Angular 21 | Esta app web (SPA) |
| `back/` | Laravel 12 | API REST compartida |

**Stack:**

| Área | Tecnología | Docs oficiales |
|------|-----------|----------------|
| Framework | Angular 21 / TypeScript 5.9 | [angular.dev](https://angular.dev) · [typescriptlang.org](https://www.typescriptlang.org/docs/) |
| Estado | Angular Signals | [angular.dev/guide/signals](https://angular.dev/guide/signals) |
| HTTP | Angular HttpClient + RxJS | [angular.dev/guide/http](https://angular.dev/guide/http) |
| Formularios | Angular Reactive Forms | [angular.dev/guide/forms/reactive-forms](https://angular.dev/guide/forms/reactive-forms) |
| Rutas | Angular Router | [angular.dev/guide/routing](https://angular.dev/guide/routing) |
| i18n | ngx-translate | [github.com/ngx-translate/core](https://github.com/ngx-translate/core) |
| Toasts | ngx-toastr | [github.com/scttcper/ngx-toastr](https://github.com/scttcper/ngx-toastr) |
| Tests | Vitest | [vitest.dev](https://vitest.dev) |
| Build | Vite (via @angular/build) | [angular.dev/tools/cli/build](https://angular.dev/tools/cli/build) |

---

## 2. Requisitos previos

### Node.js 20.19+ o 22.12+

```bash
node -v   # Debe ser 20.19+ o 22.12+
npm -v    # Viene incluido con Node
```

Instala Node desde [nodejs.org](https://nodejs.org) o usando `nvm` (recomendado para manejar versiones):

```bash
nvm install 22
nvm use 22
```

> **Importante:** Si el build falla con errores de compilación, verifica que estás usando la versión correcta de Node. El proyecto está configurado para Node 22 en el entorno local. Usa `nvm use` para activar la versión correcta antes de ejecutar `npm run build`:
>
> ```bash
> source ~/.nvm/nvm.sh && nvm use 22 && npm run build
> ```

### Angular CLI (opcional pero útil)

```bash
npm install -g @angular/cli
ng version  # Verifica instalación
```

Sin Angular CLI también funciona usando los scripts de `npm` directamente.

---

## 3. Levantar el entorno local

### Paso 1 — Instalar dependencias

```bash
cd Front
npm install
```

### Paso 2 — Verificar la URL del backend

El archivo `src/environments/environment.ts` define hacia dónde apuntan las llamadas a la API:

```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8100/api/v1'
};
```

Si tu backend corre en un puerto diferente, cámbialo aquí. Para producción se usa `environment.prod.ts` con una ruta relativa (`/api/v1`) asumiendo un reverse proxy.

### Paso 3 — Levantar el servidor de desarrollo

```bash
npm start
# App disponible en http://localhost:4200
```

El servidor tiene **Hot Module Replacement (HMR)**: los cambios en el código se reflejan en el navegador automáticamente sin recargar la página completa.

### Paso 4 — Verificar que funciona

Abre `http://localhost:4200`. Debes ver la pantalla de login. Si el backend está corriendo, podrás autenticarte; si no, verás errores de red en la consola del navegador — eso es esperado.

---

## 4. Estructura del proyecto

```
Front/
├── src/
│   ├── app/
│   │   ├── core/                   # Infraestructura: nunca lazy-loaded
│   │   │   ├── guards/             # Protección de rutas (auth, rol, email)
│   │   │   ├── interceptors/       # Modifican todas las requests HTTP
│   │   │   ├── services/           # Servicios de API y estado global
│   │   │   ├── models/             # Interfaces TypeScript de las entidades
│   │   │   └── tokens/             # Injection tokens para abstracción
│   │   │
│   │   ├── features/               # Módulos de negocio (lazy-loaded)
│   │   │   ├── auth/               # Login, registro, verificación de email
│   │   │   ├── dashboard/          # Resumen, analytics, heatmap
│   │   │   ├── expenses/           # Lista, formulario y quick-add de gastos
│   │   │   ├── workspaces/         # Gestión de workspaces y miembros
│   │   │   ├── categories/         # Categorías de gastos
│   │   │   ├── budgets/            # Presupuestos
│   │   │   ├── fixed-expenses/     # Gastos recurrentes
│   │   │   ├── pending-payments/   # Pagos compartidos pendientes
│   │   │   ├── payment-methods/    # Tarjetas y métodos de pago
│   │   │   ├── notifications/      # Notificaciones del usuario
│   │   │   ├── settings/           # Configuración (shell con sub-rutas)
│   │   │   ├── profile/            # Perfil de usuario
│   │   │   ├── pricing/            # Planes y suscripción
│   │   │   └── admin/              # Panel de administración
│   │   │
│   │   ├── shared/                 # Componentes reutilizables entre features
│   │   │   ├── nav/                # Barra de navegación
│   │   │   ├── confirm-dialog/     # Modal de confirmación genérico
│   │   │   ├── crud-form/          # Formulario CRUD genérico
│   │   │   ├── server-table/       # Tabla con paginación del servidor
│   │   │   ├── pipes/              # Pipes custom (formateo de moneda)
│   │   │   └── language-switcher/  # Selector de idioma
│   │   │
│   │   ├── app.ts                  # Componente raíz (tema, logout, fab)
│   │   ├── app.routes.ts           # Definición completa de rutas
│   │   └── app.config.ts           # Providers globales (interceptores, i18n)
│   │
│   ├── environments/
│   │   ├── environment.ts          # Desarrollo (apiUrl local)
│   │   └── environment.prod.ts     # Producción (apiUrl relativa)
│   │
│   ├── styles.scss                 # Design system global (variables, utilidades)
│   └── index.html                  # HTML base
│
├── public/
│   └── i18n/                       # Traducciones (en.json, es.json)
│
├── angular.json                    # Configuración del Angular CLI
├── tsconfig.json                   # Configuración de TypeScript
└── package.json                    # Dependencias y scripts
```

### Estructura interna de cada feature

Todos los features siguen exactamente la misma convención:

```
features/expenses/
├── expense-list/
│   ├── expense-list.ts       # Componente (lógica)
│   ├── expense-list.html     # Template
│   ├── expense-list.scss     # Estilos del componente
│   └── expense-list.spec.ts  # Tests
├── expense-form/
│   ├── expense-form.ts
│   ├── expense-form.html
│   └── expense-form.scss
└── quick-add/
    ├── quick-add-expense-fab.ts
    └── ...
```

> **Nota:** los archivos de componentes se llaman `nombre.ts`, no `nombre.component.ts`. Es una convención del proyecto.

---

## 5. Arquitectura

### 5.1 Standalone Components (sin NgModule)

Angular 21 usa **standalone components** — no hay `NgModule`. Cada componente declara explícitamente sus dependencias en el array `imports`:

```typescript
@Component({
  selector: 'app-expense-list',
  imports: [TranslateModule, RouterLink, CurrencyFormatPipe, ConfirmDialogComponent],
  templateUrl: './expense-list.html',
  styleUrl: './expense-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ExpenseListComponent { ... }
```

Si un componente no aparece en `imports`, no puede usarse en el template. Este error es el más común al agregar un nuevo componente.

### 5.2 Estado con Angular Signals

En lugar de NgRx u otras librerías de estado, el proyecto usa **Angular Signals** — la forma moderna de manejar estado reactivo en Angular.

Un signal es una variable que notifica a la UI cuando cambia:

```typescript
// Declarar
readonly loading = signal(false);
readonly expenses = signal<Expense[]>([]);

// Leer (en el template o en el código)
this.loading()          // → false
this.expenses()         // → []

// Actualizar
this.loading.set(true);
this.expenses.set(response.data);
```

**Computed signals** — valores derivados que se actualizan automáticamente:

```typescript
readonly selectedWorkspace = computed(
  () => this.workspaces().find(w => w.id === this.currentWorkspaceId()) ?? null
);
```

**Effects** — efectos secundarios que se ejecutan cuando cambia un signal:

```typescript
private readonly syncThemeEffect = effect(() => {
  document.body.dataset['theme'] = this.theme(); // Cambia el tema cuando el signal cambia
});
```

### 5.3 Servicios para estado global

El estado que se comparte entre componentes vive en servicios:

| Servicio | Qué gestiona |
|---------|-------------|
| `AuthStateService` | Token, rol, plan, userId — sincronizado con localStorage |
| `WorkspaceContextService` | Workspace actual y lista de workspaces |
| `QuickAddExpenseService` | Estado del modal de quick-add |
| `NotificationService` | Conteo de notificaciones no leídas (polling) |

### 5.4 Inyección de dependencias con `inject()`

En Angular moderno no se usa el constructor para inyectar dependencias — se usa la función `inject()`:

```typescript
// ✅ Forma moderna (Angular 14+)
private readonly expensesService = inject(ExpensesService);
private readonly router = inject(Router);
private readonly toastService = inject(ToastrService);

// ❌ Forma antigua (evitar)
constructor(private expensesService: ExpensesService) {}
```

### 5.5 Componentes Smart vs Dumb

**Smart components (contenedores):** obtienen datos, tienen lógica de negocio.
- Ejemplos: `ExpenseListComponent`, `DashboardComponent`, `WorkspaceDetailComponent`

**Dumb components (presentacionales):** solo reciben datos via `@Input()` y emiten eventos via `@Output()`.
- Ejemplos: `ConfirmDialogComponent`, `CurrencyFormatPipe`, cualquier componente en `shared/`

```typescript
// Dumb component — no sabe nada del mundo exterior
@Component({ selector: 'app-confirm-dialog', ... })
export class ConfirmDialogComponent {
  @Input() title = '';
  @Input() message = '';
  @Output() confirmed = new EventEmitter<void>();
  @Output() canceled = new EventEmitter<void>();
}
```

---

## 6. Rutas y navegación

### Estructura de rutas

Todas las rutas están en `src/app/app.routes.ts`. Están divididas en tres grupos:

**Rutas públicas (solo para usuarios no autenticados):**

| Ruta | Componente |
|------|-----------|
| `/login` | UserLoginComponent |
| `/register` | RegisterComponent |
| `/forgot-password` | ForgotPasswordComponent |
| `/user/reset-password` | ResetPasswordComponent |
| `/user/verify-email` | VerifyEmailComponent |
| `/admin/login` | AdminLoginComponent |

**Rutas de usuario (requieren auth + email verificado + rol `user`):**

| Ruta | Componente |
|------|-----------|
| `/user/dashboard` | UserDashboardComponent |
| `/user/expenses` | ExpenseListComponent |
| `/user/expenses/create` | ExpenseFormComponent |
| `/user/workspaces` | WorkspaceListComponent |
| `/user/workspaces/:id` | WorkspaceDetailComponent (con sub-rutas) |
| `/user/settings` | SettingsShellComponent (con sub-rutas) |
| `/user/notifications` | NotificationListComponent |
| `/user/profile` | UserProfileComponent |

**Rutas de admin (requieren auth + rol `admin`):**

| Ruta | Componente |
|------|-----------|
| `/admin/dashboard` | DashboardComponent |
| `/admin/administrators` | AdministratorListComponent |

### Guards (protección de rutas)

Los guards son funciones que deciden si el usuario puede acceder a una ruta. Están en `src/app/core/guards/`.

| Guard | Qué verifica | Si falla... |
|-------|-------------|------------|
| `authGuard` | ¿Está logueado? | Redirige a `/login` |
| `guestGuard` | ¿NO está logueado? | Redirige a `/user/dashboard` o `/admin/dashboard` |
| `emailVerifiedGuard` | ¿Email verificado? | Redirige a `/user/verify-email-pending` |
| `roleGuard` | ¿Tiene el rol correcto? | Redirige según configuración de la ruta |

Ejemplo de cómo se aplican en una ruta:

```typescript
{
  path: 'user/expenses',
  canActivate: [authGuard, emailVerifiedGuard, roleGuard],
  data: { roles: ['user'] },
  loadComponent: () => import('./features/expenses/expense-list').then(m => m.ExpenseListComponent)
}
```

### Lazy loading

Todos los features usan `loadComponent()` — el código de cada feature **no se descarga hasta que el usuario navega a esa ruta**. Esto hace que la app cargue más rápido inicialmente.

### Navegar desde código

```typescript
private readonly router = inject(Router);

// Navegar
this.router.navigate(['/user/expenses']);
this.router.navigate(['/user/workspaces', workspaceId]);

// Navegar con query params
this.router.navigate(['/user/expenses'], { queryParams: { from: '2026-01-01' } });
```

Desde el template:

```html
<a [routerLink]="['/user/expenses']">Ver gastos</a>
<a [routerLink]="['/user/workspaces', workspace.id]">Abrir workspace</a>
```

---

## 7. Llamadas HTTP a la API

### ApiService — el servicio base

`src/app/core/services/api.ts` es el wrapper de `HttpClient`. Todos los servicios de feature lo usan en lugar de inyectar `HttpClient` directamente:

```typescript
// ✅ Así se hace
private readonly api = inject(ApiService);

// En un método del servicio
list(workspaceId: string): Observable<ExpenseListResponse> {
  return this.api.get<ExpenseListResponse>(`/workspaces/${workspaceId}/expenses`);
}
```

`ApiService` antepone automáticamente la `apiUrl` del entorno a todos los paths.

### Interceptores HTTP

Cada request pasa por estos interceptores antes de llegar al servidor (configurados en `app.config.ts`):

1. **`authInterceptor`** — agrega `Authorization: Bearer <token>` y el header `Accept-Language` automáticamente en cada request.

2. **`unauthorizedInterceptor`** — si el servidor responde con 401 (token vencido o inválido), limpia el estado de auth y redirige al login. El componente que hizo la request no necesita manejar este caso.

### Patrón de un servicio de feature

```typescript
@Injectable({ providedIn: 'root' })
export class ExpensesService {
  private readonly api = inject(ApiService);

  list(workspaceId: string, filters: ExpenseFilters = {}): Observable<ExpenseListResponse> {
    const query = new URLSearchParams();
    if (filters.from) query.set('from', filters.from);
    if (filters.to) query.set('to', filters.to);
    const qs = query.size > 0 ? `?${query.toString()}` : '';
    return this.api.get<ExpenseListResponse>(`/workspaces/${workspaceId}/expenses${qs}`);
  }

  create(workspaceId: string, data: CreateExpensePayload): Observable<{ data: Expense }> {
    return this.api.post(`/workspaces/${workspaceId}/expenses`, data);
  }

  update(workspaceId: string, id: string, data: Partial<CreateExpensePayload>): Observable<{ data: Expense }> {
    return this.api.put(`/workspaces/${workspaceId}/expenses/${id}`, data);
  }

  delete(workspaceId: string, id: string): Observable<void> {
    return this.api.delete(`/workspaces/${workspaceId}/expenses/${id}`);
  }
}
```

### Cómo subscribirse en un componente

```typescript
loadExpenses(): void {
  this.loading.set(true);
  this.expensesService.list(this.workspaceId)
    .pipe(finalize(() => this.loading.set(false)))
    .subscribe({
      next: (response) => this.expenses.set(response.data),
      error: () => this.toastService.error(this.translate.instant('expenses.load_error'))
    });
}
```

Siempre usa `finalize()` para desactivar el estado de loading aunque falle la request.

### Limpieza automática de subscriptions

Usa `takeUntilDestroyed()` para que Angular limpie las subscriptions automáticamente cuando el componente se destruye — sin necesidad de `ngOnDestroy` manual:

```typescript
private readonly destroyRef = inject(DestroyRef);

// En el constructor o ngOnInit
this.expensesService.list(workspaceId)
  .pipe(takeUntilDestroyed(this.destroyRef))
  .subscribe({ ... });
```

---

## 8. Flujos importantes

### 8.1 Autenticación

```
Usuario llena email + contraseña → submit

  → AuthApiService.loginAsUser(email, password)
    → POST /auth/user/login

    Respuesta OK:
      → AuthStateService guarda token, rol, plan, userId en signals + localStorage
      → Router navega a /user/dashboard
      → WorkspaceContextService carga la lista de workspaces
      → NotificationService inicia polling de notificaciones

    Respuesta 403 (email no verificado):
      → Se muestra botón para reenviar verificación

    Respuesta error:
      → Se muestra toast con mensaje de error traducido
```

**Estado de auth disponible globalmente:**

```typescript
private readonly authState = inject(AuthStateService);

this.authState.isLoggedIn()   // boolean
this.authState.plan()         // 'free' | 'premium'
this.authState.role()         // 'user' | 'admin'
this.authState.userId()       // string
```

### 8.2 Crear un gasto

```
1. Usuario navega a /user/expenses/create
2. ExpenseFormComponent carga categorías, tarjetas y otros métodos de pago del workspace
3. Usuario llena el form: monto, fecha, categoría, método de pago
4. (Opcional) Puede crear una nueva tarjeta inline sin salir del form
5. (Opcional) Puede marcar el gasto como pagado por otro miembro del workspace
6. Submit → ExpensesService.create(workspaceId, payload)
   → POST /workspaces/:id/expenses
7. Si el backend retorna budget_warnings → toast de advertencia
8. Toast de éxito + navegación de vuelta a la lista
```

### 8.3 Notificaciones (polling)

La app consulta el conteo de notificaciones no leídas cada 60 segundos:

```
App init (usuario logueado)
  → NotificationService.startPolling()
    → cada 60s: GET /notifications/unread-count
    → actualiza unreadCount signal
    → badge en la barra de navegación se actualiza automáticamente

Usuario va a /user/notifications
  → Carga lista completa
  → Puede marcar individual o todas como leídas
  → unreadCount signal se resetea a 0
```

### 8.4 Cambio de tema (dark/light)

```
Usuario cambia el toggle de tema
  → ThemeService.toggle()
    → actualiza theme signal ('dark' | 'light')
    → effect() en app.ts detecta el cambio
    → document.body.dataset['theme'] = 'light' (o 'dark')
    → Los CSS custom properties del selector [data-theme='light'] se activan
    → Guarda preferencia en localStorage
```

---

## 9. Formularios

Todos los formularios usan **Reactive Forms** — no template-driven forms.

### Crear un formulario

```typescript
private readonly fb = inject(FormBuilder);

readonly form = this.fb.group({
  amount:      ['', [Validators.required, Validators.pattern(/^\d+(\.\d{1,2})?$/)]],
  date:        [new Date().toISOString().split('T')[0], [Validators.required]],
  category_id: ['', [Validators.required]],
  description: [''],
});
```

### Enviar el formulario

```typescript
submit(): void {
  if (this.form.invalid) {
    this.form.markAllAsTouched(); // Muestra todos los errores al usuario
    return;
  }

  this.loading.set(true);
  this.expensesService.create(this.workspaceId, this.form.value)
    .pipe(finalize(() => this.loading.set(false)))
    .subscribe({
      next: () => this.router.navigate(['/user/expenses']),
      error: () => this.toastService.error('Error al guardar')
    });
}
```

### Mostrar errores en el template

```html
<input formControlName="amount" type="number" />
@if (form.get('amount')?.invalid && form.get('amount')?.touched) {
  @if (form.get('amount')?.hasError('required')) {
    <span class="error">El monto es requerido</span>
  }
  @if (form.get('amount')?.hasError('pattern')) {
    <span class="error">Ingresa un monto válido</span>
  }
}
```

### Filtros reactivos con debounce

Los filtros de búsqueda/fecha usan el patrón signal → observable → debounce para no hacer una request por cada tecla:

```typescript
// Signal del filtro
readonly filterFrom = signal('');

// En el constructor: escucha cambios con debounce
constructor() {
  toObservable(this.filterFrom).pipe(
    skip(1),                        // Ignora el valor inicial
    debounceTime(400),              // Espera 400ms después de que el usuario deja de escribir
    distinctUntilChanged(),         // Solo si el valor cambió
    takeUntilDestroyed()
  ).subscribe(() => this.resetAndLoad());
}
```

---

## 10. Estilos y design system

### Nunca hardcodees valores visuales

Todo el design system está definido como **CSS custom properties** en `src/styles.scss`. Siempre usa variables, nunca valores literales:

```scss
// ❌ Nunca
.card { background: #1a1f2e; padding: 16px; border-radius: 12px; }

// ✅ Siempre
.card { background: var(--color-surface); padding: var(--space-4); border-radius: var(--radius-md); }
```

### Variables disponibles

**Colores:**
```scss
--color-brand-700      // Morado principal (#7c3aed)
--color-accent         // Cian (#22d3ee)
--color-success        // Verde (#34d399)
--color-danger         // Rojo (#fb7185)
--color-warning        // Amarillo (#fbbf24)
--color-text           // Texto principal
--color-text-muted     // Texto secundario
--color-surface        // Superficie de tarjetas
--color-surface-alt    // Superficie alternativa
--color-bg             // Fondo de la app
```

**Espaciado:**
```scss
--space-1  // 4px
--space-2  // 8px
--space-3  // 12px
--space-4  // 16px
--space-5  // 20px
--space-6  // 24px
--space-8  // 32px
```

**Border radius:**
```scss
--radius-sm   // 8px
--radius-md   // 12px
--radius-lg   // 16px
--radius-xl   // 20px
--radius-full // 999px (pastilla)
```

**Z-index:**
```scss
--z-sidebar    // 100
--z-topbar     // 200
--z-bottom-nav // 800
--z-fab        // 900
--z-dialog     // 1000
```

### Tema dark/light

Los colores del tema claro se definen en el selector `body[data-theme='light']`. Solo cambia las variables de color — el componente no necesita saber en qué tema está.

### Clases globales de utilidad

Definidas en `styles.scss`, disponibles en todos los componentes:

```html
<!-- Botones -->
<button class="btn primary">Guardar</button>
<button class="btn ghost">Cancelar</button>
<button class="btn danger">Eliminar</button>

<!-- Layout -->
<div class="app-shell">
  <aside class="sidebar">...</aside>
  <main class="app-body">
    <header class="topbar">...</header>
    <section class="content">...</section>
  </main>
</div>
```

Consulta [`docs/designs.md`](../designs.md) para ver el catálogo completo del design system.

---

## 11. Internacionalización (i18n)

La app soporta **español e inglés** usando `ngx-translate`. Las traducciones están en `public/i18n/es.json` y `public/i18n/en.json`.

### Usar traducciones en templates

```html
<!-- Pipe -->
<h1>{{ 'expenses.title' | translate }}</h1>

<!-- Con parámetros -->
<p>{{ 'expenses.count' | translate: { count: expenses().length } }}</p>
```

### Usar traducciones en código TypeScript

```typescript
private readonly translate = inject(TranslateService);

const message = this.translate.instant('expenses.save_error');
this.toastService.error(message);
```

### Agregar una nueva traducción

1. Abre `public/i18n/es.json` y `public/i18n/en.json`
2. Agrega la clave en ambos archivos:

```json
// es.json
{ "expenses": { "new_key": "Texto en español" } }

// en.json
{ "expenses": { "new_key": "Text in English" } }
```

3. Úsala en el template o código con la misma clave: `'expenses.new_key'`

---

## 12. Estándares de código

### Convenciones de nombres

| Tipo | Convención | Ejemplo |
|------|-----------|---------|
| Componentes | kebab-case (archivo), PascalCase (clase) | `expense-list.ts` → `ExpenseListComponent` |
| Servicios | kebab-case (archivo), PascalCase (clase) | `expenses.ts` → `ExpensesService` |
| Guards | `nombre-guard.ts` | `auth-guard.ts` |
| Pipes | `nombre.pipe.ts` | `currency-format.pipe.ts` |
| Interfaces/modelos | `entidad.model.ts` | `expense.model.ts` |
| Signals | camelCase | `loading`, `expenses`, `filterFrom` |
| Computed | camelCase | `selectedWorkspace`, `isOwner` |
| Effects | `private readonly nombreEffect = effect(...)` | `syncThemeEffect` |

### TypeScript estricto

El proyecto tiene `"strict": true` en `tsconfig.json`. Esto significa:
- No se permite `any` implícito
- Todas las variables deben estar tipadas
- Los templates también se validan estrictamente

Si TypeScript lanza un error de tipo, **resuélvelo correctamente** — no uses `as any` como atajo.

### ChangeDetection.OnPush

Todos los componentes usan `ChangeDetectionStrategy.OnPush`. Esto significa que Angular solo revisa si la UI necesita actualizarse cuando:
- Cambia una propiedad `@Input()`
- Se dispara un evento `@Output()`
- Cambia un signal que el template lee

Como consecuencia: **actualiza el estado a través de signals** (`signal.set()`), no mutando objetos directamente. Si mutas un array sin llamar `set()`, la UI no se actualizará.

```typescript
// ❌ No funciona con OnPush
this.expenses().push(newExpense);

// ✅ Correcto
this.expenses.update(prev => [...prev, newExpense]);
// o
this.expenses.set([...this.expenses(), newExpense]);
```

---

## 13. Tests

Los tests usan **Vitest** (no Jasmine/Karma) y están en archivos `.spec.ts` junto al código que prueban.

### Correr los tests

```bash
npm test           # Todos los tests
npm test -- --watch  # Modo watch (re-corre al guardar)
```

### Estructura de un test

```typescript
import { describe, it, expect, beforeEach } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { AuthStateService } from './auth-state.service';

describe('AuthStateService', () => {
  let service: AuthStateService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [AuthStateService]
    });
    service = TestBed.inject(AuthStateService);
  });

  it('should be logged in after setting token', () => {
    service.setToken('test-token');
    expect(service.isLoggedIn()).toBe(true);
  });
});
```

### Qué se testea actualmente

- Servicios de auth (estado, login, logout)
- Guards (auth, rol, email verification)
- Componentes críticos (expense-list, workspace-list)

---

## 14. Build y despliegue

### Build de producción

```bash
npm run build
```

Genera los archivos en `dist/Front/browser/`. Las optimizaciones incluyen:
- Tree-shaking (elimina código no usado)
- Minificación
- Hashing de archivos para cache-busting
- Separación de bundles por lazy route

### Variables de entorno en producción

No hay `.env` en Angular — las variables se embeben en el código al momento del build. Para cambiar `apiUrl` en producción, edita `src/environments/environment.prod.ts` y haz el build.

### Presupuestos de bundle (límites de tamaño)

En `angular.json` hay límites configurados:
- Bundle inicial: advertencia en 500kB, error en 1MB
- Estilos por componente: advertencia en 4kB, error en 8kB

Si el build falla por exceder estos límites, revisa qué estás importando — probablemente hay una librería muy pesada que puede eliminarse o cargarse de forma lazy.

### Dockerfile

El proyecto incluye un `Dockerfile` con build multi-etapa:
1. Etapa de build: imagen Node, corre `npm run build`
2. Etapa de runtime: Nginx sirve los archivos estáticos y hace proxy de `/api/v1` al backend

---

## 15. Tareas comunes

### Agregar un nuevo feature

1. Crea la carpeta en `src/app/features/nuevo-feature/`
2. Crea el componente:
   ```bash
   # Manual o con el CLI
   ng generate component features/nuevo-feature/nuevo-feature --standalone
   ```
3. Agrega la ruta en `app.routes.ts` con lazy loading:
   ```typescript
   {
     path: 'user/nuevo-feature',
     canActivate: [authGuard, emailVerifiedGuard, roleGuard],
     data: { roles: ['user'] },
     loadComponent: () => import('./features/nuevo-feature/nuevo-feature')
       .then(m => m.NuevoFeatureComponent)
   }
   ```
4. Agrega las traducciones en `public/i18n/es.json` y `en.json`
5. Crea el servicio en `src/app/core/services/nuevo-feature.ts`

### Agregar un nuevo campo a un formulario

1. Agrega el control al `FormGroup` en el componente
2. Agrega el input en el template con `formControlName`
3. Agrega la validación si aplica
4. Agrega la clave de traducción para el label y los mensajes de error
5. Incluye el campo en el payload del servicio

### Agregar una nueva traducción

1. Agrega la clave en `public/i18n/es.json`
2. Agrega la clave en `public/i18n/en.json`
3. Úsala con el pipe `translate` o `translate.instant()`

---

## 16. Documentación externa

| Tecnología | Recurso | URL |
|-----------|---------|-----|
| Angular 21 | Documentación oficial | https://angular.dev |
| Angular Signals | Guía de signals | https://angular.dev/guide/signals |
| Angular Router | Routing y guards | https://angular.dev/guide/routing |
| Angular Reactive Forms | Formularios reactivos | https://angular.dev/guide/forms/reactive-forms |
| Angular HttpClient | Llamadas HTTP | https://angular.dev/guide/http |
| RxJS | Operadores y observables | https://rxjs.dev/api |
| TypeScript | Referencia del lenguaje | https://www.typescriptlang.org/docs/ |
| ngx-translate | i18n y traducciones | https://github.com/ngx-translate/core |
| ngx-toastr | Notificaciones toast | https://github.com/scttcper/ngx-toastr |
| Vitest | Testing | https://vitest.dev |
| Angular CLI | Comandos del CLI | https://angular.dev/tools/cli |
| CSS Custom Properties | Variables CSS | https://developer.mozilla.org/en-US/docs/Web/CSS/--* |

---

## 17. Archivos de referencia rápida

| Archivo | Qué contiene |
|---------|-------------|
| [`src/app/app.ts`](../../Front/src/app/app.ts) | Componente raíz (tema, auth, logout) |
| [`src/app/app.routes.ts`](../../Front/src/app/app.routes.ts) | Todas las rutas de la app |
| [`src/app/app.config.ts`](../../Front/src/app/app.config.ts) | Providers globales e interceptores |
| [`src/app/core/services/api.ts`](../../Front/src/app/core/services/api.ts) | Wrapper de HttpClient |
| [`src/app/core/services/auth-state.service.ts`](../../Front/src/app/core/services/auth-state.service.ts) | Estado de autenticación |
| [`src/app/core/interceptors/`](../../Front/src/app/core/interceptors/) | Auth + 401 handler |
| [`src/app/core/guards/`](../../Front/src/app/core/guards/) | Guards de rutas |
| [`src/app/features/expenses/`](../../Front/src/app/features/expenses/) | Feature completo de ejemplo |
| [`src/environments/environment.ts`](../../Front/src/environments/environment.ts) | Config de desarrollo |
| [`src/styles.scss`](../../Front/src/styles.scss) | Design system global |
| [`public/i18n/es.json`](../../Front/public/i18n/es.json) | Traducciones en español |
| [`angular.json`](../../Front/angular.json) | Config del Angular CLI y build |
| [`tsconfig.json`](../../Front/tsconfig.json) | Config de TypeScript |
