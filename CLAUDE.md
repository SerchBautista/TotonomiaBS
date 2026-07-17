# FinTech Project

> Este monorepo local agrupa dos repositorios independientes en producción.
> Las reglas de esta sección aplican a los dos. Las especificaciones por repo están en:
>
> - [`docs/agents/frontend.md`](docs/agents/frontend.md) — Angular 21
> - [`docs/agents/backend.md`](docs/agents/backend.md) — Laravel 12

---

## Stack Tecnológico

| Repo | Tecnología | Carpeta local |
|---|---|---|
| fintech-front | Angular 21 | `Front/` |
| fintech-back | Laravel 12 | `back/` |

**Infraestructura compartida**
- Base de datos: PostgreSQL (producción) / SQLite (desarrollo local)
- Auth: Laravel Passport
- Push Notifications: Firebase Cloud Messaging (FCM)
- Pagos: Stripe
- Documentación API: Swagger/OpenAPI (L5-Swagger)
- Permisos: Spatie Laravel Permission
- Contenedores: Docker + Docker Compose

---

## Comandos principales

### Backend
```bash
cd back && composer install
cp .env.example .env && php artisan key:generate
php artisan migrate && php artisan passport:install
php artisan serve
```

### Frontend
```bash
cd Front && npm install && npm start
```

### Docker
```bash
docker-compose up -d
docker-compose -f docker-compose.prod.yml up -d
```

---

## Restricciones

**No realizar commits ni crear PRs directamente desde esta sesión.** Para hacer cambios al código:
- Revisar los cambios con `git diff`
- Pedir al usuario que confirme antes de ejecutar comandos de commit/push
- Excepción: operaciones de solo lectura (`git status`, `git log`, `git diff`)

---

## Design System

Consultar la guía correspondiente según el repo en el que se trabaje:

- **Web** → [`docs/designs.md`](docs/designs.md): tokens de color, tipografía, espaciado, radios, sombras y componentes globales (`.btn`, `.badge`, `.card-surface`, `.field-group`).

Reglas universales:
- **Nunca** hardcodear valores visuales (`#hex`, `px` arbitrarios, colores literales en Dart).
- **Nunca** duplicar variantes de componentes globales en estilos de componentes individuales.

---

## Documentación de referencia

| Documento | Propósito |
|---|---|
| [`docs/agents/frontend.md`](docs/agents/frontend.md) | Guía completa para el repo Angular |
| [`docs/agents/backend.md`](docs/agents/backend.md) | Guía completa para el repo Laravel |
| [`docs/angular-solid-clean.md`](docs/angular-solid-clean.md) | SOLID + Clean Architecture en Angular |
| [`docs/laravel-solid-clean.md`](docs/laravel-solid-clean.md) | SOLID + Clean Architecture en Laravel |
| [`docs/designs.md`](docs/designs.md) | Design system web |
