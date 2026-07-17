# Totonomia — FinTech Personal

Monorepo local que agrupa dos repositorios independientes:

- **`Front/`** — Aplicación web SPA construida con Angular 21.
- **`back/`** — API REST construida con Laravel 12.

El proyecto es una aplicación FinTech personal para el control de gastos, presupuestos, gastos fijos y suscripciones, con autenticación de usuarios, workspaces compartidos y pagos integrados.

---

## Tabla de contenidos

1. [Descripción general](#descripción-general)
2. [Stack tecnológico](#stack-tecnológico)
3. [Instalación y ejecución](#instalación-y-ejecución)
   - [Requisitos previos](#requisitos-previos)
   - [Levantar con Docker Compose](#levantar-con-docker-compose)
   - [Levantar backend localmente](#levantar-backend-localmente)
   - [Levantar frontend localmente](#levantar-frontend-localmente)
4. [Estructura del proyecto](#estructura-del-proyecto)
5. [Funcionalidades principales](#funcionalidades-principales)
6. [Configuración de email (Resend)](#configuración-de-email-resend)
7. [Documentación de referencia](#documentación-de-referencia)

---

## Descripción general

Totonomia permite a los usuarios registrar y categorizar sus gastos, definir presupuestos por categoría, programar gastos fijos, gestionar métodos de pago y realizar un seguimiento de sus suscripciones. La arquitectura separa claramente el frontend (Angular) del backend (Laravel API), comunicándose exclusivamente a través de una API REST documentada con Swagger/OpenAPI.

El backend se encarga de:

- Autenticar usuarios y gestionar sesiones con tokens OAuth2 (Laravel Passport).
- Administrar workspaces (espacios compartidos entre usuarios) y permisos (Spatie Laravel Permission).
- Procesar gastos, presupuestos, gastos fijos, tarjetas y métodos de pago.
- Gestionar suscripciones y pagos con Stripe.
- Enviar notificaciones push vía Firebase Cloud Messaging (FCM).
- Exponer documentación de la API con L5-Swagger.

El frontend es una SPA que consume la API REST y ofrece una experiencia de usuario responsive con HMR en desarrollo.

---

## Stack tecnológico

### Frontend (`Front/`)

| Área | Tecnología | Documentación |
|---|---|---|
| Framework | Angular 21 / TypeScript 5.9 | [angular.dev](https://angular.dev) |
| Estado | Angular Signals | [angular.dev/guide/signals](https://angular.dev/guide/signals) |
| HTTP | Angular HttpClient + RxJS | [angular.dev/guide/http](https://angular.dev/guide/http) |
| Formularios | Angular Reactive Forms | [angular.dev/guide/forms/reactive-forms](https://angular.dev/guide/forms/reactive-forms) |
| Router | Angular Router | [angular.dev/guide/routing](https://angular.dev/guide/routing) |
| i18n | ngx-translate | [github.com/ngx-translate/core](https://github.com/ngx-translate/core) |
| Notificaciones UI | ngx-toastr | [github.com/scttcper/ngx-toastr](https://github.com/scttcper/ngx-toastr) |
| Tests | Vitest | [vitest.dev](https://vitest.dev) |
| Build | Vite (vía @angular/build) | [angular.dev/tools/cli/build](https://angular.dev/tools/cli/build) |

### Backend (`back/`)

| Área | Tecnología | Documentación |
|---|---|---|
| Framework | Laravel 12 / PHP 8.3 | [laravel.com/docs](https://laravel.com/docs) |
| Autenticación | Laravel Passport (OAuth2) | [laravel.com/docs/passport](https://laravel.com/docs/passport) |
| Permisos | Spatie Laravel Permission | [spatie.be/docs/laravel-permission](https://spatie.be/docs/laravel-permission/v6/introduction) |
| Base de datos | PostgreSQL (prod/dev) · SQLite (tests) | [postgresql.org/docs](https://www.postgresql.org/docs/) |
| Pagos | Stripe PHP SDK | [stripe.com/docs/api](https://stripe.com/docs/api) |
| Push notifications | Firebase Cloud Messaging | [firebase.google.com/docs/cloud-messaging](https://firebase.google.com/docs/cloud-messaging) |
| Documentación API | L5-Swagger (OpenAPI 3.0) | [github.com/DarkaOnLine/L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger) |
| Email | Resend | [resend.com/docs](https://resend.com/docs) |
| Almacenamiento | AWS S3 | [laravel.com/docs/filesystem](https://laravel.com/docs/filesystem) |
| Colas | Laravel Queue | [laravel.com/docs/queues](https://laravel.com/docs/queues) |

### Infraestructura compartida

- **Base de datos:** PostgreSQL 15
- **Contenedores:** Docker + Docker Compose

---

## Instalación y ejecución

### Requisitos previos

- **Backend:** PHP 8.3+, Composer, PostgreSQL (o SQLite para pruebas rápidas).
- **Frontend:** Node.js 20.19+ o 22.12+, npm.
- **Docker:** Docker Engine + Docker Compose (opcional pero recomendado para levantar todo el stack de una sola vez).

> Para Node se recomienda usar `nvm` para manejar versiones:
> ```bash
> nvm install 22
> nvm use 22
> ```

---

### Levantar con Docker Compose

El repositorio incluye un `docker-compose.yml` de desarrollo que levanta el frontend, backend, worker de colas y base de datos PostgreSQL.

```bash
# Desde la raíz del monorepo
docker compose up -d
```

Servicios expuestos:

| Servicio | URL local | Puerto host → contenedor |
|---|---|---|
| Frontend (Angular) | http://localhost:4300 | `4300:4200` |
| Backend (Laravel) | http://localhost:8100 | `8100:8000` |
| Base de datos PostgreSQL | localhost:5433 | `5433:5432` |

El contenedor del backend solo ejecuta `composer install`, genera la `APP_KEY` y levanta el servidor interno de Laravel. **No ejecuta migraciones ni instala Passport automáticamente**, por lo que debes completar esos pasos manualmente después de que los contenedores estén saludables.

El `.env` se genera automáticamente desde `.env.example` la primera vez que arranca el contenedor, y el `docker-compose.yml` ajusta las variables de conexión para que apunten al servicio `db`:

```env
DB_HOST=db
DB_DATABASE=strapp
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

> **Importante:** dentro de Docker Compose, `DB_HOST` debe ser el nombre del servicio de la base de datos (`db`), no `172.17.0.1` ni `localhost`. Si tu `.env` local tiene otros valores, el contenedor los sobrescribe automáticamente al arrancar; solo intervendrás manualmente si decides levantar el backend fuera de Docker.

Pasos manuales obligatorios después de `docker compose up -d`:

```bash
# 1. Verifica que todos los servicios estén saludables
#    (espera a que `db` aparezca como healthy)
docker compose ps

# 2. Ejecuta las migraciones
docker compose exec backend php artisan migrate --force

# 3. Generar las claves de encriptación de Passport (solo si no existen)
#    Las migraciones de Passport ya existen en el repo, así que no es necesario
#    (ni recomendable) ejecutar `passport:install`.
docker compose exec backend sh -c "[ -f storage/oauth-private.key ] || php artisan passport:keys"

# 4. Sembrar datos de prueba (incluye clientes OAuth y usuarios de prueba)
docker compose exec backend php artisan db:seed
```

El servicio `queue-worker` también se levanta automáticamente, por lo que no es necesario iniciar un listener de colas por separado.

El frontend ejecuta `npm install` y `npm start` con host `0.0.0.0`.

---

### Levantar backend localmente

La forma más rápida de levantar el backend local es usar el atajo de Composer:

```bash
cd back
composer setup
```

Este comando ejecuta en orden: `composer install`, copia `.env.example` a `.env`, genera la `APP_KEY`, corre las migraciones, instala dependencias de Node y compila los assets.

Si prefieres el flujo manual:

```bash
cd back
composer install
cp .env.example .env
php artisan key:generate

# Opción A: con PostgreSQL (configurar DB_* en .env)
php artisan migrate
[ -f storage/oauth-private.key ] || php artisan passport:keys

# Opción B: con SQLite (rápido para pruebas)
touch database/database.sqlite
# En .env: DB_CONNECTION=sqlite
php artisan migrate
[ -f storage/oauth-private.key ] || php artisan passport:keys

# Sembrar datos de prueba (incluye clientes OAuth y usuarios de prueba)
php artisan db:seed

# Levantar servidor de desarrollo
php artisan serve
```

Variables de entorno mínimas recomendadas para desarrollo local sin Stripe ni Redis:

```env
PAYMENT_GATEWAY=dummy
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

> **Nota sobre SQLite:** si usas `DB_CONNECTION=sqlite`, no podrás usar `QUEUE_CONNECTION=database` ni `CACHE_STORE=database` porque SQLite no admite escrituras concurrentes en esos escenarios. Usa `QUEUE_CONNECTION=sync` y `CACHE_STORE=file` en su lugar.

El backend quedará disponible en `http://localhost:8000`.

---

### Levantar frontend localmente

```bash
cd Front
npm install
npm start
```

La aplicación web estará disponible en `http://localhost:4200`.

Por defecto el frontend apunta a `http://localhost:8100/api/v1` (configurado en `src/environments/environment.ts`), que es la URL del backend cuando se levanta con Docker Compose. Si levantaste el backend localmente en `http://localhost:8000`, cambia el valor a:

```typescript
apiUrl: 'http://localhost:8000/api/v1'
```

---

## Estructura del proyecto

```
Totonomia/
├── Front/                     # Aplicación web Angular 21
│   ├── src/
│   │   ├── app/
│   │   │   ├── core/          # Servicios, interceptores, guards, estado global
│   │   │   ├── features/      # Módulos de negocio lazy-loaded
│   │   │   │   ├── auth/      # Login, registro, verificación de email
│   │   │   │   ├── expenses/  # Gastos
│   │   │   │   ├── budgets/   # Presupuestos
│   │   │   │   ├── fixed-expenses/ # Gastos fijos
│   │   │   │   ├── profile/   # Perfil de usuario
│   │   │   │   └── ...
│   │   │   └── shared/        # Componentes reutilizables
│   │   ├── environments/      # Configuración por ambiente
│   │   └── styles/            # Variables y estilos globales
│   └── Dockerfile
│
├── back/                      # API REST Laravel 12
│   ├── app/
│   │   ├── Http/Controllers/Api/  # Controladores API
│   │   ├── Services/          # Lógica de negocio
│   │   ├── Actions/           # Acciones reutilizables
│   │   ├── Notifications/     # Notificaciones push/email
│   │   └── OpenApi/           # Definiciones Swagger
│   ├── database/
│   │   ├── factories/         # Generadores de datos de prueba
│   │   ├── migrations/        # Migraciones
│   │   └── seeders/           # Seeders
│   ├── routes/                # Definición de rutas
│   ├── tests/                 # Tests PHPUnit (Feature/Unit)
│   └── Dockerfile
│
├── docker-compose.yml         # Desarrollo con Docker
├── docs/                      # Documentación técnica y guías de agente
└── README.md                  # Este archivo
```

---

## Funcionalidades principales

- **Autenticación y autorización**
  - Registro, login y logout de usuarios.
  - Verificación de correo electrónico.
  - Recuperación y reseteo de contraseña.
  - Roles y permisos con Spatie Laravel Permission (`user`, `admin`).

- **Workspaces**
  - Espacios compartidos entre usuarios para organizar finanzas personales o familiares.
  - Membresías con diferentes roles dentro del workspace.

- **Gastos**
  - Registro de gastos con categoría, monto, fecha y método de pago.
  - Historial y filtros.

- **Presupuestos**
  - Definición de presupuestos por categoría.
  - Seguimiento del gasto vs. presupuesto.

- **Gastos fijos**
  - Programación de pagos recurrentes.
  - Generación automática de ocurrencias.

- **Métodos de pago**
  - Tarjetas de crédito/débito.
  - Otros métodos de pago personalizados.

- **Suscripciones y pagos**
  - Integración con Stripe para pagos recurrentes.
  - Planes premium con desbloqueo de features.

- **Notificaciones**
  - Notificaciones push vía Firebase Cloud Messaging.
  - Notificaciones in-app y por email.

- **Documentación API**
  - Swagger/OpenAPI generado automáticamente con L5-Swagger.

---


## Configuración de email (Resend)

Para que el sistema pueda enviar correos de verificación al registrar un usuario, así como para la verificación en dos pasos (2FA), se utiliza **Resend** como proveedor de email transaccional.

### Variables de entorno requeridas

| Variable | Descripción | Valor por defecto |
|---|---|---|
| `MAIL_MAILER` | Driver de mail de Laravel | `log` (cambiar a `resend`) |
| `RESEND_API_KEY` | API key de Resend | — |
| `MAIL_FROM_ADDRESS` | Dirección de correo remitente | `hello@example.com` |
| `MAIL_FROM_NAME` | Nombre del remitente | `${APP_NAME}` |

### Configuración

1. Genera una API key en [resend.com](https://resend.com) y añádela a tu `.env` como `RESEND_API_KEY`.
2. Cambia `MAIL_MAILER` a `resend` en tu `.env`.
3. (Opcional) Personaliza `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME`.

> **Nota:** el paquete `resend/resend-laravel` registra automáticamente el transport `resend` en Laravel. No es necesario modificar `config/mail.php`.

> **Nota de seguridad:** no commitees `.env` ni valores reales de `RESEND_API_KEY`. Usa `.env.example` como referencia.

---

## Documentación de referencia

| Documento | Propósito |
|---|---|
| [`docs/agents/frontend.md`](docs/agents/frontend.md) | Guía completa para el repo Angular |
| [`docs/agents/backend.md`](docs/agents/backend.md) | Guía completa para el repo Laravel |
| [`docs/angular-solid-clean.md`](docs/angular-solid-clean.md) | SOLID + Clean Architecture en Angular |
| [`docs/laravel-solid-clean.md`](docs/laravel-solid-clean.md) | SOLID + Clean Architecture en Laravel |
| [`docs/designs.md`](docs/designs.md) | Design system web |
| [`docs/technical-guides/frontend-guide.md`](docs/technical-guides/frontend-guide.md) | Guía técnica detallada del frontend |
| [`docs/technical-guides/backend-guide.md`](docs/technical-guides/backend-guide.md) | Guía técnica detallada del backend |


## Documentacion del proyecto

- Slides: https://docs.google.com/presentation/d/1sTlZIAuaUq2kgZpnpd8Q0YnajvC3pP5PqxPAvT-Rx_w/edit?slide=id.g3f554e0f4d9_0_5#slide=id.g3f554e0f4d9_0_5
- Video: https://drive.google.com/file/d/12RPhRt_AOUwSC2WEbRlpO61ZuGhNmoVy/view?usp=drive_link
