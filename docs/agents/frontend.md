# Frontend — Angular 21 (repo: fintech-front)

## Estructura del proyecto

```
Front/
├── src/app/
│   ├── core/         # Servicios transversales y guards (api.ts, auth.ts)
│   ├── features/     # Módulos por dominio (auth/login, admin/dashboard, profile/)
│   └── shared/       # Piezas reutilizables de UI (language-switcher, etc.)
├── dist/             # Build output — no editar
└── node_modules/     # No editar
```

## Comandos

```bash
npm install           # instalar dependencias
npm start             # dev server → http://localhost:4200
npm run build         # build de producción
npm test              # tests con Vitest
```

## Convenciones de código

- TypeScript: 2-space indentation, single quotes, strict mode.
- SCSS: sin valores hardcodeados — usar siempre los custom properties de `styles.scss`.
- Nombres de carpetas de features: kebab-case.
- Clases y componentes: PascalCase.
- Archivos de prueba: `*.spec.ts` junto al archivo fuente.

## Arquitectura SOLID + Clean

Leer **obligatoriamente** [`docs/angular-solid-clean.md`](../angular-solid-clean.md) antes de generar o refactorizar código.

Reglas clave:
- **Dumb/Smart components**: los Smart consumen providers/services; los Dumb solo reciben `@Input` y emiten `@Output`.
- **InjectionToken** para Dependency Inversion (no instanciar servicios directamente en componentes).
- Fronteras estrictas entre `core`, `features` y `shared` — ningún feature importa de otro feature.
- Un componente no debe conocer los detalles de implementación del servicio que consume.

## Design System

Consultar [`docs/designs.md`](../designs.md) para tokens de color, tipografía, espaciado, radios, sombras y patrones de composición.

- **Nunca** hardcodear valores visuales (`#hex`, `px` arbitrarios) en componentes.
- **Nunca** duplicar variantes de `.btn`, `.badge` u otros componentes globales en SCSS de componentes individuales.
- Botones: siempre `.btn + variante` (`.primary`, `.secondary`, `.danger`, `.ghost`, `.accent`).

### Modales

Reglas obligatorias (detalle completo en [`docs/designs.md` §3.13, §3.13.1 y §4.4](../designs.md)):

| Caso | Qué usar |
|------|----------|
| Formulario en modal (crear/editar/gestionar) | `app-modal-shell` + `<form class="modal-form">` |
| Confirmación destructiva (eliminar) | `app-confirm-dialog` |
| Formulario en página dedicada | `app-page-header` + `app-form-card` + `.field-group` |

**No hacer:**
- Anidar `app-form-card` dentro de `app-modal-shell` (doble superficie).
- Usar `.field-group` (labels uppercase) dentro de modales — usar `.modal-form .field`.
- Cancelar con `btn ghost` en modales — usar `btn secondary sm`.
- Crear modales ad hoc con `.modal-backdrop` / `.modal-panel` en features.

**Footer:** botón primario (Guardar) primero, cancelar (`btn secondary`) después, alineados a la derecha.

**Referencia visual:** panel de `quick-add-expense-fab`; implementación de referencia: `category-list`, `payment-method-list`.

**Excepciones documentadas:** quick-add (FAB) y `fixed-expense-list` — ver `designs.md` §8.

## Testing

- Cubrir: auth flow, guards, y estados de error de API.
- Nombres de tests: descripción de comportamiento en lenguaje natural.
- No mockear servicios más de lo necesario — preferir `TestBed` con providers reales cuando sea factible.
