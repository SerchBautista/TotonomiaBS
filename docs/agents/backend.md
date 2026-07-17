# Backend — Laravel 12 (repo: fintech-back)

## Estructura del proyecto

```
back/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/   # Controladores versionados (/api/v1/...)
│   │   └── Requests/          # FormRequests para validación
│   ├── Actions/               # Lógica de negocio (un caso de uso = una Action)
│   └── Models/
├── routes/api.php             # Rutas API con middleware de auth/roles
├── tests/
│   ├── Feature/               # Tests de endpoints y autorización
│   └── Unit/                  # Tests de lógica aislada
└── vendor/                    # No editar
```

## Comandos

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan passport:install
php artisan serve                      # API en http://localhost:8000
composer test                          # limpia config + corre php artisan test
./vendor/bin/pint                      # formatter PSR-12 — ejecutar antes de PR
```

## Stack y dependencias clave

- **Auth**: Laravel Passport (Bearer tokens). Requiere HTTPS en producción.
- **Permisos**: Spatie Laravel Permission (roles y permisos en middleware de rutas).
- **Documentación API**: L5-Swagger / OpenAPI — mantener anotaciones actualizadas.
- **Pagos**: Stripe.
- **Push Notifications**: Firebase Cloud Messaging (FCM) — credenciales en `.env`.
- **Base de datos**: PostgreSQL en producción, SQLite en desarrollo local (`back/database/database.sqlite`).

## Convenciones de código

- PHP: PSR-12, 4-space indentation.
- Controladores, Resources, FormRequests: PascalCase.
- Métodos y variables: camelCase.
- Rutas API siempre bajo `/api/v1`.
- Reglas de auth y permisos en middleware de ruta, nunca dentro del controlador.

## Arquitectura SOLID + Clean

Leer **obligatoriamente** [`docs/laravel-solid-clean.md`](../laravel-solid-clean.md) antes de generar o refactorizar código.

Reglas clave:
- **Thin controllers**: el controlador valida, delega a una Action/Service, y devuelve la respuesta.
- **FormRequests**: toda validación de input vive en FormRequest, no en el controlador.
- **Actions/Services**: cada caso de uso es una clase con un único método público `execute()`.
- **Service Container / Providers**: usar inyección de dependencias; no instanciar clases directamente.
- Los modelos Eloquent no contienen lógica de negocio.

## API response envelopes

Tres familias de respuesta JSON; elegir según el tipo de endpoint:

| Familia | Formato | Cuándo | `$wrap` en Resource |
|---------|---------|--------|---------------------|
| **Entity CRUD** | `{ "data": { ... } }` o `{ "data": [ ... ] }` | Modelos Eloquent (Card, Category, Expense…) | default (`data`) |
| **Operation result** | JSON plano en la raíz | Checkout, subscription status, bulk ops | `$wrap = null` |
| **Composite admin** | `{ "message", "data", "meta?" }` | Profile, sync, admin con mensaje | `data` manual vía `->additional()` o `->resolve()` |

**Regla crítica:** nunca `response()->json(['data' => SomeResource::collection(...)])` — Laravel envuelve la collection otra vez y produce `data.data`. Devolver la collection directamente (`CategoryResource::collection(...)`) o usar `->additional(['meta' => ...])`.

**Patrón de referencia (operation result flat):**

```
CheckoutSession VO → CheckoutSessionResource ($wrap = null) ← InitiateCheckoutAction::execute()
SubscriptionController::checkout → return new CheckoutSessionResource($action->execute($user));
```

Entity CRUD gold standard: `CardController` → `Card` model → `CardResource` / `::collection()`.

## Manejo de errores backend

Leer esta sección **obligatoriamente** antes de trabajar en manejo de errores del repo `back/`.

Reglas clave:
- El punto de entrada del manejo global de errores está en `back/bootstrap/app.php`; revisar ese archivo antes de cambiar renderizado, contexto de logs o decisión JSON para `api/*`.
- El contrato estándar de payload vive en `back/app/Support/Api/ApiErrorResponse.php`; usarlo como fuente de verdad antes de agregar respuestas manuales.
- Usar un contrato estándar de error con: `status`, `code`, `message`, `request_id` y, solo cuando aplique, `fieldErrors` y `meta`.
- **422 de FormRequest / framework**: usar `code = validation_error` y exponer `fieldErrors`.
- **422 de dominio**: no mezclar `fieldErrors`; responder con `status`, `code`, `message`, `request_id` y `meta` solo si aporta contexto útil.
- Cubrir y documentar de forma consistente al menos estas categorías: `401`, `403`, `404`, `409`, `422`, `500`.
- Los mensajes deben salir de traducciones Laravel (`__()`, `lang/api.php`, `validation.php`); evitar hardcodes en controladores, actions o excepciones.
- `request_id` debe ser consistente entre payload y logs para trazabilidad.
- Preferir renderer global / respuesta estandarizada sobre JSON manual disperso.
- OpenAPI / L5-Swagger debe reflejar el comportamiento real de cada error expuesto por la API.
- Regla práctica para frontend: consumir `code` como contrato estable; `message` es fallback seguro para mostrar al usuario.
- Todo cambio de comportamiento debe incluir tests de happy path y al menos un error path relevante.

## Testing

- Feature tests: comportamiento del endpoint + reglas de autorización (quién puede y quién no).
- Unit tests: lógica aislada (Actions, Services, helpers).
- Nombres de métodos: `test_regular_user_can_login_and_receive_token` (snake_case descriptivo).
- Incluir siempre negative-path tests para cambios de auth o permisos.
