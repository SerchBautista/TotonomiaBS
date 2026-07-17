# Guía Técnica — Backend (Laravel API)

> **Para quién es esta guía:** desarrolladores que se incorporan al proyecto o que necesitan mantener el backend. Se asume conocimiento general de programación web, pero no experiencia previa con Laravel ni con este proyecto.

---

## 1. ¿Qué hace el backend?

El backend es una **API REST** construida con Laravel 12. Es el servidor central que consume la web. Se encarga de:

- Autenticar usuarios y gestionar sesiones con tokens
- Almacenar y procesar gastos, presupuestos y gastos fijos
- Gestionar workspaces (espacios compartidos entre usuarios)
- Procesar suscripciones y pagos con Stripe
- Enviar notificaciones push vía Firebase
- Exponer documentación de la API con Swagger

El backend es uno de dos proyectos independientes:

| Repositorio | Tecnología | Propósito |
|-------------|-----------|-----------|
| `back/` | Laravel 12 | Esta API REST |
| `Front/` | Angular 21 | Versión web |

Los dos se despliegan por separado. El backend solo habla con la base de datos, Redis, Stripe y Firebase — nunca con el frontend directamente más allá de responder requests HTTP.

**Stack:**

| Área | Tecnología | Docs oficiales |
|------|-----------|----------------|
| Framework | Laravel 12 / PHP 8.2+ | [laravel.com/docs](https://laravel.com/docs) · [php.net/docs](https://www.php.net/docs.php) |
| Autenticación | Laravel Passport (OAuth2) | [laravel.com/docs/passport](https://laravel.com/docs/passport) |
| Permisos | Spatie Laravel Permission | [spatie.be/docs/laravel-permission](https://spatie.be/docs/laravel-permission/v6/introduction) |
| Base de datos | PostgreSQL (producción) · SQLite (tests) | [postgresql.org/docs](https://www.postgresql.org/docs/) |
| Pagos | Stripe PHP SDK | [stripe.com/docs/api](https://stripe.com/docs/api) |
| Push notifications | Firebase Cloud Messaging | [firebase.google.com/docs/cloud-messaging](https://firebase.google.com/docs/cloud-messaging) |
| Documentación API | L5-Swagger (OpenAPI 3.0) | [github.com/DarkaOnLine/L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger) |
| Email | Resend | [resend.com/docs](https://resend.com/docs) |
| Almacenamiento | AWS S3 | [laravel.com/docs/filesystem](https://laravel.com/docs/filesystem) |
| Colas | Laravel Queue | [laravel.com/docs/queues](https://laravel.com/docs/queues) |

---

## 2. Requisitos previos

### PHP 8.2+

```bash
php -v  # Debe ser 8.2 o superior
```

Instala PHP desde [php.net](https://www.php.net/downloads) o usando Homebrew en Mac:

```bash
brew install php
```

### Composer

Gestor de dependencias de PHP (equivalente a `npm` para Node):

```bash
composer -V  # Verifica instalación
```

Si no lo tienes: [getcomposer.org](https://getcomposer.org/download/)

### PostgreSQL

Para desarrollo local puedes usar PostgreSQL o simplemente dejarlo en SQLite (más fácil):

```bash
# Si usas SQLite (recomendado para empezar)
touch back/database/database.sqlite
# Luego en .env: DB_CONNECTION=sqlite
```

Si prefieres PostgreSQL: [postgresql.org/download](https://www.postgresql.org/download/)

### Redis (opcional para desarrollo)

Se usa para caché y colas. En desarrollo puedes usar el driver `sync` para colas y `file` para caché (ver sección de configuración).

---

## 3. Levantar el entorno local

### Paso 1 — Copiar variables de entorno

```bash
cd back
cp .env.example .env
```

Edita `.env` con tus valores. El mínimo necesario para arrancar:

```env
APP_KEY=           # Se genera en el paso 2
APP_URL=http://localhost:8000

# Opción A: SQLite (más fácil, no necesitas instalar nada)
DB_CONNECTION=sqlite

# Opción B: PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fintech
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

# Pagos (usa 'dummy' para desarrollo sin Stripe)
PAYMENT_GATEWAY=dummy

# Colas en modo síncrono (no necesitas Redis)
QUEUE_CONNECTION=sync

# Caché en archivos (no necesitas Redis)
CACHE_STORE=file
```

### Paso 2 — Instalar dependencias y configurar

El proyecto tiene un comando `setup` que hace todo de golpe:

```bash
composer setup
```

Este comando ejecuta en orden: `composer install`, genera la `APP_KEY`, corre migraciones e instala dependencias de Node. Si prefieres hacerlo manualmente:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan passport:install   # Instala claves OAuth2 (solo primera vez)
```

### Paso 3 — Levantar el servidor

```bash
composer dev
```

Este comando levanta en paralelo: el servidor Laravel, el listener de colas, el streaming de logs (pail) y Vite. Si prefieres solo el servidor:

```bash
php artisan serve
# Disponible en http://localhost:8000
```

### Paso 4 — Verificar que funciona

```bash
curl http://localhost:8000/api/v1/auth/user/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"wrong"}'
# Debes recibir un 422 con errores de validación — eso confirma que la API responde
```

### Paso 5 — Poblar la base de datos (opcional)

```bash
php artisan db:seed
# Crea datos de ejemplo: usuarios, workspaces, categorías
```

---

## 4. Estructura del proyecto

```
back/
├── app/
│   ├── Actions/            # Lógica de negocio (una acción = un caso de uso)
│   ├── Console/            # Comandos Artisan personalizados
│   ├── Contracts/          # Interfaces (define qué debe hacer cada clase)
│   ├── DTOs/               # Objetos de transferencia de datos simples
│   ├── Events/             # Eventos del sistema (UserRegistered, UserPlanChanged)
│   ├── Http/
│   │   ├── Controllers/Api/ # Controladores de la API
│   │   ├── Middleware/      # Middleware HTTP (auth, permisos, locale)
│   │   ├── Requests/        # Validación de requests (una clase por operación)
│   │   └── Resources/       # Formato de respuesta JSON (transforman modelos)
│   ├── Jobs/               # Tareas en cola (SendPaymentReminderJob)
│   ├── Listeners/          # Reaccionan a eventos (AssignFreePlanListener)
│   ├── Models/             # Modelos Eloquent (mapean tablas de la BD)
│   ├── Notifications/      # Clases de notificación (push, email)
│   ├── OpenApi/            # Anotaciones Swagger globales
│   ├── Policies/           # Reglas de autorización por modelo
│   ├── Providers/          # Service providers (AppServiceProvider registra todo)
│   └── Services/           # Servicios de infraestructura (Stripe, archivos)
├── config/                 # Archivos de configuración (auth, database, services...)
├── database/
│   ├── factories/          # Generadores de datos falsos para tests
│   ├── migrations/         # Evolución del esquema de la BD
│   └── seeders/            # Datos iniciales
├── routes/
│   └── api.php             # Todos los endpoints de la API
├── storage/
│   └── api-docs/           # Documentación Swagger generada
├── tests/
│   ├── Feature/            # Tests de integración (request → response)
│   └── Unit/               # Tests unitarios (clases aisladas)
├── .env.example            # Plantilla de variables de entorno
├── composer.json           # Dependencias PHP
└── phpunit.xml             # Configuración de tests
```

### La carpeta `app/` en detalle

Entender qué va en cada carpeta es clave para orientarse:

| Carpeta | Qué contiene | Cuándo la tocas |
|---------|-------------|-----------------|
| `Models/` | Clases que representan tablas de BD | Al agregar campos o relaciones |
| `Http/Controllers/` | Reciben el request, orquestan la respuesta | Al agregar endpoints |
| `Http/Requests/` | Validación y autorización de entrada | Al agregar o cambiar validaciones |
| `Http/Resources/` | Transforman modelos a JSON | Al cambiar el formato de respuesta |
| `Actions/` | Lógica de negocio pura | Al agregar funcionalidad |
| `Policies/` | Reglas de quién puede hacer qué | Al cambiar permisos |
| `Services/` | Integraciones externas (Stripe, S3) | Al cambiar integraciones |
| `Events/` + `Listeners/` | Comunicación desacoplada entre partes | Al agregar efectos secundarios |

---

## 5. Arquitectura

### 5.1 Cómo se organiza el código (patrón Actions)

En lugar de meter toda la lógica en los controladores, el backend usa el **patrón Action**: cada caso de uso del negocio tiene su propia clase con un método `execute()`.

```
Request HTTP
    ↓
Middleware (autenticación, verificación de email)
    ↓
Controller (orquesta: valida → autoriza → ejecuta → responde)
    ↓
Request Class (valida los datos de entrada)
    ↓
Policy (verifica que el usuario tiene permiso)
    ↓
Action (hace el trabajo real: crea, actualiza, calcula)
    ↓
Model / Eloquent (persiste en la base de datos)
    ↓
Resource (transforma el resultado a JSON)
    ↓
Respuesta HTTP
```

Esto hace que el código sea más fácil de probar y mantener: cada pieza tiene una responsabilidad única.

### 5.2 Inyección de dependencias

El `AppServiceProvider` (`app/Providers/AppServiceProvider.php`) es donde se conectan las interfaces con sus implementaciones concretas. En lugar de hacer `new StripeGatewayService()` directamente en el código, Laravel lo resuelve automáticamente:

```php
// En AppServiceProvider: "cuando alguien pida PaymentGatewayContract, dales StripeGatewayService"
$this->app->bind(PaymentGatewayContract::class, StripeGatewayService::class);

// En el controlador: Laravel inyecta automáticamente la implementación
public function checkout(PaymentGatewayContract $gateway): JsonResponse { ... }
```

Esto permite cambiar de Stripe a otro proveedor de pagos sin tocar los controladores — solo cambia el binding en `AppServiceProvider`.

### 5.3 Recursos API (formato de respuesta)

Los `Resources` son clases que transforman un modelo Eloquent en el JSON que retorna la API. Nunca retornes modelos directamente desde los controladores:

```php
// ❌ Nunca hagas esto
return response()->json($expense);

// ✅ Siempre usa un Resource
return response()->json(['data' => new ExpenseResource($expense)]);
```

Usar Resources permite controlar exactamente qué campos se exponen, renombrar campos, formatear fechas y cargar relaciones condicionalmente sin N+1 queries.

---

## 6. Flujos importantes

### 6.1 Flujo completo de una request (ejemplo: crear gasto)

```
POST /api/v1/workspaces/{workspace}/expenses
```

**1. Middleware verifica identidad:**
- `auth:api` — Passport valida el Bearer token y carga `$request->user()`
- `verified` — Verifica que el email esté confirmado (si no, retorna 403)

**2. Laravel resuelve el modelo del workspace:**
- El `{workspace}` en la URL se convierte automáticamente en un objeto `Workspace` de la BD (Route Model Binding). Si no existe, retorna 404 automáticamente.

**3. Controller recibe el request:**
```php
// app/Http/Controllers/Api/ExpenseController.php
public function store(
    StoreExpenseRequest $request,   // Validación automática
    Workspace $workspace,           // Modelo resuelto automáticamente
    RegisterExpenseActionInterface $action,
): JsonResponse {
    $this->authorize('create', [Expense::class, $workspace]); // Llama a ExpensePolicy
    $expense = $action->execute($request->user(), $workspace, $request->validated());
    return response()->json(['data' => new ExpenseResource($expense)], 201);
}
```

**4. `StoreExpenseRequest` valida los datos:**
- Verifica tipos, formato, que la categoría exista, que el instrumento de pago pertenezca al workspace, etc.
- Si algo falla, retorna 422 con los errores campo por campo — el controller nunca llega a ejecutarse.

**5. `ExpensePolicy` verifica permisos:**
- ¿El usuario es miembro del workspace?
- ¿El dueño del workspace tiene plan premium si es un workspace compartido?
- Si no tiene permiso, retorna 403.

**6. `RegisterExpenseAction` ejecuta la lógica:**
- Crea el gasto en la BD con los datos validados
- Carga las relaciones necesarias para la respuesta

**7. `ExpenseResource` formatea la respuesta:**
- Transforma el modelo a JSON con el formato esperado por los clientes
- Retorna HTTP 201 (Created)

### 6.2 Autenticación

**Login:**

```
POST /api/v1/auth/user/login
  → UserLoginService.authenticate(email, password)
    → Auth::attempt() verifica credenciales
    → Verifica que el usuario tiene rol 'user'
    → Si email no está verificado → lanza excepción 403
    → $user->createToken('api-token')->accessToken
    → Retorna { token, user }
```

El token es un string opaco. El cliente lo guarda y lo envía en cada request posterior:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Registro:**

```
POST /api/v1/auth/register
  → RegisterUserAction.execute(name, email, password)
    → Crea usuario en BD
    → Asigna rol 'user'
    → Dispara evento UserRegistered
      → Listener: AssignFreePlanListener (asigna plan gratuito)
    → Envía email de verificación
    → Retorna token (email aún no verificado)
```

Hasta que el usuario verifica su email, el middleware `verified` bloquea los endpoints protegidos.

**Verificación de email:**

```
Usuario hace clic en link del email
  → GET /api/v1/auth/email/verify/{id}/{hash}
    → Laravel verifica la firma del URL
    → Marca email_verified_at en la BD
    → Dispara evento Verified
      → Listener: CreateDefaultWorkspaceListener (crea workspace inicial)
```

**Logout:**

```
POST /api/v1/auth/logout
  → $user->token()->revoke()  # Invalida el token en la BD
  → Retorna 200
```

### 6.3 Flujo de suscripción con Stripe

```
1. App llama POST /api/v1/subscriptions/checkout
   → StripeGatewayService.createCheckoutSession($user)
   → Crea sesión en Stripe con el precio premium
   → Retorna URL de Stripe Checkout

2. App redirige al usuario a esa URL (Stripe se encarga del formulario de pago)

3. Usuario paga en Stripe

4. Stripe llama POST /api/v1/webhooks/stripe con evento 'checkout.session.completed'
   → Valida Stripe-Signature para confirmar que viene de Stripe
   → Actualiza user.subscription_ends_at
   → Asigna rol 'premium' al usuario
   → Registra el pago en subscription_payments

5. App detecta el cambio en el perfil del usuario (GET /auth/me) y desbloquea features premium
```

Para desarrollo local, `PAYMENT_GATEWAY=dummy` devuelve una URL falsa y simula el proceso sin tocar Stripe.

---

## 7. Base de datos

### Tablas principales

| Tabla | Qué guarda |
|-------|-----------|
| `users` | Cuentas de usuario (UUID, email, contraseña hasheada, plan, workspace por defecto) |
| `workspaces` | Espacios compartidos (UUID, dueño, nombre, moneda) |
| `workspace_user` | Membresías: qué usuarios pertenecen a qué workspace y con qué rol |
| `expenses` | Gastos individuales (monto, fecha, categoría, método de pago) |
| `categories` | Categorías de gastos (nombre, ícono, color) |
| `cards` | Tarjetas de crédito/débito guardadas en el workspace |
| `other_payment_methods` | Otros métodos de pago (efectivo, transferencia) |
| `fixed_expenses` | Gastos recurrentes (frecuencia, fechas de inicio/fin) |
| `fixed_expense_occurrences` | Instancias generadas de gastos recurrentes (pendiente/pagado/vencido) |
| `budgets` | Presupuestos por categoría (monto límite, periodo) |
| `subscription_payments` | Historial de pagos de suscripción |
| `notifications` | Notificaciones in-app del usuario |
| `oauth_access_tokens` | Tokens de Passport |
| `roles`, `permissions` | Tablas de Spatie para RBAC |

### Relaciones clave

```
User
  ├── Tiene muchos workspaces donde es dueño
  ├── Pertenece a muchos workspaces (como miembro)
  ├── Tiene un workspace por defecto
  └── Tiene muchos gastos y categorías

Workspace
  ├── Pertenece a un dueño (User)
  ├── Tiene muchos miembros (User) con un rol en la tabla pivot
  ├── Tiene muchos gastos, categorías habilitadas, tarjetas y presupuestos
  └── Tiene muchos gastos fijos

Expense
  ├── Pertenece a un workspace
  ├── Fue creado por un usuario
  ├── Fue pagado por un usuario (puede ser diferente al que lo creó)
  ├── Tiene una categoría
  └── Tiene un instrumento de pago (Card u OtherPaymentMethod — polimórfico)
```

### UUIDs en todos los modelos

Todos los IDs son UUIDs (`uuid()`) en lugar de enteros auto-incrementales. Esto permite que los clientes generen IDs localmente antes de sincronizar — es clave para el soporte offline. Nunca asumas que un ID es un número.

### Soft deletes

Todos los modelos principales tienen `SoftDeletes`. Cuando se "borra" un registro, solo se marca con `deleted_at` — los datos no se eliminan físicamente. Esto protege el historial de gastos.

### Cómo crear una migración

```bash
php artisan make:migration create_nombre_tabla_table
# o para modificar
php artisan make:migration add_campo_to_tabla_table
```

Luego edita el archivo generado en `database/migrations/` y corre:

```bash
php artisan migrate
```

---

## 8. Rutas de la API

Todas las rutas están prefijadas con `/api/v1`. El archivo completo está en `routes/api.php`.

### Resumen de endpoints

| Grupo | Ruta | Descripción |
|-------|------|-------------|
| Auth | `POST /auth/user/login` | Login usuario |
| Auth | `POST /auth/register` | Registro |
| Auth | `POST /auth/logout` | Logout |
| Auth | `GET /auth/me` | Perfil del usuario actual |
| Auth | `GET /auth/email/verify/{id}/{hash}` | Verificar email |
| Auth | `POST /password/forgot` | Solicitar reset de contraseña |
| Workspaces | `GET /workspaces` | Listar workspaces del usuario |
| Workspaces | `POST /workspaces` | Crear workspace |
| Workspaces | `GET /workspaces/{id}` | Ver workspace |
| Workspaces | `PUT /workspaces/{id}` | Actualizar workspace |
| Gastos | `GET /workspaces/{id}/expenses` | Listar gastos (paginado, filtrable) |
| Gastos | `POST /workspaces/{id}/expenses` | Crear gasto |
| Gastos | `PUT /workspaces/{id}/expenses/{expId}` | Actualizar gasto |
| Gastos | `DELETE /workspaces/{id}/expenses/{expId}` | Eliminar gasto |
| Categorías | `GET/POST/PUT/DELETE /workspaces/{id}/categories` | CRUD categorías |
| Presupuestos | `GET/POST/PUT/DELETE /workspaces/{id}/budgets` | CRUD presupuestos |
| Gastos fijos | `GET/POST/PUT/DELETE /workspaces/{id}/fixed-expenses` | CRUD gastos recurrentes |
| Miembros | `GET/POST/DELETE /workspaces/{id}/members` | Gestión de miembros |
| Analytics | `GET /workspaces/{id}/analytics/summary` | Resumen de gastos |
| Analytics | `GET /workspaces/{id}/analytics/heatmap` | Mapa de calor de actividad |
| Analytics | `GET /workspaces/{id}/analytics/projection` | Proyección de gastos |
| Suscripción | `POST /subscriptions/checkout` | Iniciar pago Stripe |
| Notificaciones | `GET /notifications` | Listar notificaciones |
| Webhooks | `POST /webhooks/stripe` | Webhook de Stripe (sin auth) |

### Convenciones de rutas

- Kebab-case: `/fixed-expenses`, `/other-payment-methods`
- UUIDs en los path params: `/workspaces/550e8400-e29b-41d4-a716-446655440000`
- Query params en snake_case: `?from=2026-01-01&to=2026-12-31&category_id=...`
- Paginación: `?page=1&per_page=30`

---

## 9. Permisos y roles

### Roles del sistema

| Rol | A quién aplica | Cómo se asigna |
|-----|---------------|---------------|
| `user` | Todo usuario registrado | Automático al registrarse |
| `premium` | Usuarios con suscripción activa | Al completar pago con Stripe |
| `admin` | Administradores del sistema | Manualmente por un superadmin |

Los roles se gestionan con **Spatie Laravel Permission**. Técnicamente Spatie permite asignar múltiples roles, pero en este sistema cada usuario tiene un rol principal (`admin` o `user`). Tener ambos roles simultáneamente es un anti-patrón: dificulta los controles de autorización y rompe la jerarquía efectiva `admin` ⊇ `user`. Si un usuario termina con doble rol, deduplícalo con `php artisan users:dedupe-roles --apply`.

### Cómo se verifican los permisos

**Nivel de ruta (middleware):** protege grupos enteros de endpoints.

```php
// Usuarios con rol 'user' (o 'admin' por jerarquía efectiva admin ⊇ user)
Route::middleware(['auth:api', 'verified', 'role:user|admin,api'])->group(...);

// Solo admins
Route::middleware(['auth:api', 'role:admin,api'])->group(...);
```

**Nivel de permiso específico:** para acciones puntuales dentro de los controladores.

```php
// En las rutas
Route::post('/files/upload', ...)->middleware('api.permission:files.upload');
```

**Nivel de modelo (Policies):** para reglas que dependen del estado de los datos (¿este usuario es miembro de este workspace?).

```php
// En el controlador
$this->authorize('create', [Expense::class, $workspace]);

// En la Policy (app/Policies/ExpensePolicy.php)
public function create(User $user, Workspace $workspace): bool {
    $role = $workspace->memberRole($user->id);
    if (!in_array($role, ['owner', 'member'])) return false;
    if ($workspace->owner_id !== $user->id && !$workspace->ownerHasPremium()) return false;
    return true;
}
```

### Features según plan

Las políticas verifican el plan del usuario para ciertas acciones:
- **Plan gratuito:** un workspace, miembros limitados
- **Plan premium:** workspaces múltiples, más miembros, features avanzadas

---

## 10. Estándares de código

### Convenciones de nombres

| Tipo | Convención | Ejemplo |
|------|-----------|---------|
| Clases | PascalCase | `ExpensePolicy`, `StripeGatewayService` |
| Métodos | camelCase | `handleCheckoutCompleted()` |
| Variables | camelCase | `$workspaceId`, `$isVerified` |
| Columnas BD | snake_case | `payment_instrument_id` |
| Rutas | kebab-case | `/fixed-expenses` |
| Archivos | PascalCase (clases), snake_case (config/migrations) | `ExpenseController.php`, `config/auth.php` |

### Tipos estrictos

El código usa PHP 8.2+ con tipos estrictos en todas partes: tipos de retorno, tipos de parámetros, propiedades tipadas. Si no sabes qué tipo usar, revisa clases similares en el mismo módulo.

```php
// ✅ Así se escribe aquí
public function store(StoreExpenseRequest $request, Workspace $workspace): JsonResponse { ... }

// ❌ No hacer esto
public function store($request, $workspace) { ... }
```

### Una responsabilidad por clase

- Controllers: solo orquestan (no contienen lógica de negocio)
- Actions: solo ejecutan un caso de uso
- Services: solo se comunican con sistemas externos
- Policies: solo deciden si alguien puede hacer algo

Si una clase hace demasiadas cosas, es señal de que hay que dividirla.

### Formatear el código

```bash
./vendor/bin/pint
# Aplica automáticamente PSR-12 (el estándar de formato de PHP)
```

Corre esto antes de cada commit para mantener el código consistente.

---

## 11. Tests

Los tests están en `tests/Feature/` (tests de integración) y `tests/Unit/` (tests de clases aisladas). Usan SQLite en memoria para que sean rápidos y no necesiten una base de datos real.

### Correr los tests

```bash
composer test                                    # Todos los tests
php artisan test --filter=LoginVerificationTest  # Un solo test class
php artisan test --filter=test_login_returns_token  # Un test específico
```

### Estructura de un test de feature

```php
public function test_login_with_unverified_email_returns_403(): void
{
    // Arranjar: crear datos necesarios
    $user = User::factory()->unverified()->create();
    $user->assignRole('user');

    // Actuar: hacer la request
    $response = $this->postJson('/api/v1/auth/user/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Afirmar: verificar el resultado
    $response->assertStatus(403);
}
```

### Lo que cubren los tests actuales

- Flujo completo de autenticación (login, registro, verificación de email, reset de contraseña)
- Aislamiento entre workspaces (un usuario no puede ver datos de otro workspace)
- CRUD de gastos con validación de permisos
- Umbrales de presupuesto
- Webhooks de Stripe
- Precisión decimal en operaciones financieras

---

## 12. Tareas comunes

### Agregar un nuevo endpoint

1. Crea la clase de validación en `app/Http/Requests/`:
   ```bash
   php artisan make:request Expense/FilterExpensesRequest
   ```

2. Crea o actualiza el controller:
   ```bash
   php artisan make:controller Api/NuevoController --api
   ```

3. Agrega la ruta en `routes/api.php` dentro del grupo de middleware apropiado.

4. Si el endpoint accede a un modelo que ya tiene Policy, agrega la verificación en el controller:
   ```php
   $this->authorize('view', $modelo);
   ```

5. Crea o actualiza el Resource para formatear la respuesta.

6. Escribe un test en `tests/Feature/`.

### Agregar un nuevo campo a un modelo

1. Crea la migración:
   ```bash
   php artisan make:migration add_notes_to_expenses_table
   ```

2. En la migración:
   ```php
   public function up(): void {
       Schema::table('expenses', function (Blueprint $table) {
           $table->string('notes')->nullable()->after('description');
       });
   }
   public function down(): void {
       Schema::table('expenses', function (Blueprint $table) {
           $table->dropColumn('notes');
       });
   }
   ```

3. Corre la migración: `php artisan migrate`

4. Agrega el campo al `$fillable` del modelo.

5. Agrega el campo al Resource si debe aparecer en la respuesta.

6. Agrega la validación en el Request correspondiente.

### Regenerar la documentación Swagger

```bash
php artisan l5-swagger:generate
# Disponible en http://localhost:8000/api/documentation
```

### Ver los logs en tiempo real

```bash
php artisan pail
# o
tail -f storage/logs/laravel.log
```

### Limpiar caché (útil cuando algo se comporta raro)

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## 13. Documentación externa

| Tecnología | Recurso | URL |
|-----------|---------|-----|
| Laravel 12 | Documentación oficial | https://laravel.com/docs/12.x |
| PHP 8.2 | Referencia del lenguaje | https://www.php.net/docs.php |
| Laravel Passport | OAuth2 tokens | https://laravel.com/docs/passport |
| Spatie Permission | Roles y permisos | https://spatie.be/docs/laravel-permission/v6/introduction |
| Eloquent ORM | Modelos y queries | https://laravel.com/docs/eloquent |
| Laravel Migrations | Esquema de BD | https://laravel.com/docs/migrations |
| Laravel Policies | Autorización | https://laravel.com/docs/authorization |
| Laravel Queues | Trabajos en cola | https://laravel.com/docs/queues |
| Laravel Testing | Tests con PHPUnit | https://laravel.com/docs/testing |
| Stripe PHP SDK | Pagos y webhooks | https://stripe.com/docs/api?lang=php |
| Firebase FCM | Push notifications | https://firebase.google.com/docs/cloud-messaging |
| L5-Swagger | OpenAPI / Swagger | https://github.com/DarkaOnLine/L5-Swagger |
| Resend | Email transaccional | https://resend.com/docs |
| PHPUnit | Tests unitarios | https://phpunit.de/documentation.html |
| Laravel Pint | Formateo de código | https://laravel.com/docs/pint |

---

## 14. Archivos de referencia rápida

| Archivo | Qué contiene |
|---------|-------------|
| [`routes/api.php`](../../back/routes/api.php) | Todos los endpoints |
| [`app/Providers/AppServiceProvider.php`](../../back/app/Providers/AppServiceProvider.php) | Bindings de inyección de dependencias |
| [`app/Http/Controllers/Api/ExpenseController.php`](../../back/app/Http/Controllers/Api/ExpenseController.php) | Ejemplo de controlador completo |
| [`app/Actions/RegisterExpenseAction.php`](../../back/app/Actions/RegisterExpenseAction.php) | Ejemplo de Action |
| [`app/Http/Requests/Expense/StoreExpenseRequest.php`](../../back/app/Http/Requests/Expense/StoreExpenseRequest.php) | Ejemplo de validación |
| [`app/Http/Resources/ExpenseResource.php`](../../back/app/Http/Resources/ExpenseResource.php) | Ejemplo de Resource |
| [`app/Policies/ExpensePolicy.php`](../../back/app/Policies/ExpensePolicy.php) | Ejemplo de Policy |
| [`app/Models/User.php`](../../back/app/Models/User.php) | Modelo central con relaciones y traits |
| [`app/Models/Workspace.php`](../../back/app/Models/Workspace.php) | Modelo de workspace con multi-tenancy |
| [`app/Services/StripeGatewayService.php`](../../back/app/Services/StripeGatewayService.php) | Integración Stripe |
| [`config/auth.php`](../../back/config/auth.php) | Configuración de Passport |
| [`.env.example`](../../back/.env.example) | Variables de entorno disponibles |
| [`phpunit.xml`](../../back/phpunit.xml) | Configuración de tests |
