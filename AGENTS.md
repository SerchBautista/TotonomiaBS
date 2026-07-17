# Repository Guidelines

> Este monorepo local agrupa dos repositorios independientes en producción.
> Las reglas de esta sección aplican a los dos. Las especificaciones por repo están en:
>
> - [`docs/agents/frontend.md`](docs/agents/frontend.md) — Angular 21
> - [`docs/agents/backend.md`](docs/agents/backend.md) — Laravel 12

---

## Estructura del monorepo local

```
FinTech/
├── Front/      → repo: fintech-front   (Angular 21 SPA)
└── back/       → repo: fintech-back    (Laravel 12 API)
```

---

## Reglas generales

### Commits y Pull Requests

- No crear commits ni PRs a menos que el usuario lo pida explícitamente.
- Usar Conventional Commits: `feat(back): add expense endpoint`, `fix(front): correct auth guard redirect`.
- El scope indica el repo afectado: `front`, `back`.
- Los PRs deben incluir: resumen, áreas tocadas, evidencia de tests (comando + resultado) e issue vinculado. UI changes requieren screenshots.

### Seguridad

- **Nunca** commitear secretos, claves o archivos `.env`.
- Revisar con cuidado cualquier cambio en auth (tokens, roles, permisos) e incluir tests de ruta negativa.
- Validar input siempre en el borde del sistema (API, formularios), no en capas internas.

### Arquitectura

- Adherirse estrictamente a principios SOLID y Clean Architecture en los dos repos.
- Ver las guías específicas por repo para patrones, ejemplos y restricciones.
- No mezclar lógica de negocio con lógica de presentación ni con acceso a datos.

### Testing

- Todo cambio de comportamiento debe ir acompañado de tests.
- Los nombres de los tests deben describir el comportamiento esperado, no la implementación.
- Cubrir siempre el happy path y al menos un caso de error relevante.

### Calidad de código

- No hardcodear valores visuales, URLs, credenciales ni identificadores mágicos.
- Preferir editar archivos existentes antes de crear nuevos.
- No añadir abstracciones ni generalizaciones que el cambio actual no requiera.
- Formatear el código antes de abrir un PR (ver comandos en cada guía de repo).

---

## Lectura obligatoria antes de trabajar en un repo

| Repo | Guía de agente | Guía de arquitectura |
|---|---|---|
| Frontend | [`docs/agents/frontend.md`](docs/agents/frontend.md) | [`docs/angular-solid-clean.md`](docs/angular-solid-clean.md) |
| Backend | [`docs/agents/backend.md`](docs/agents/backend.md) | [`docs/laravel-solid-clean.md`](docs/laravel-solid-clean.md) |

---

## Orquestación (Cursor y OpenCode)

Este monorepo usa el mismo modelo de orquestador + subagentes en ambas herramientas:

| Rol | OpenCode | Cursor |
|---|---|---|
| Orquestador | `.opencode/prompts/orchestrator.md` (`default_agent`) | `.cursor/rules/orchestrator.mdc` (`alwaysApply: true`) |
| Subagentes | `.opencode/agents/*.md` | `.cursor/agents/*.md` |
| Delegación | herramienta `task` con permisos en `opencode.json` | herramienta `Task` con `subagent_type` |

Subagentes disponibles: `front-coder`, `back-coder`, `architect`, `security`, `tester`, `ux`, `review`.

OpenCode aplica restricciones duras (`edit: deny` en el orquestador). En Cursor las reglas son orientación; el orquestador debe delegar con `Task()` y no implementar directo salvo tareas triviales o autorización explícita del usuario.

---

## OpenCode Skills

- La fuente canónica de skills de este proyecto es `skills/<skill-name>/SKILL.md`.
- Los directorios `.agents/skills` y `.opencode/skills` quedan como legado y no deben usarse para nuevas integraciones del proyecto.
- Antes de actuar, revisar si algún skill aplica y usar la herramienta `skill` cuando corresponda.
- No saltarse workflows requeridos por el skill aplicable.

### Nota para orquestadores: descubrimiento de skills

La herramienta `skill` de OpenCode puede no listar todos los skills que existen físicamente en `skills/<skill-name>/SKILL.md`. Si un skill aparece documentado en esta guía pero la herramienta `skill` no lo carga, el orquestador debe:

1. Verificar que el directorio `skills/<skill-name>/` exista.
2. Leer directamente `skills/<skill-name>/SKILL.md` usando la herramienta de lectura de archivos.
3. Aplicar el workflow que ese skill defina.

Para un índice completo, ver `skills/README.md`.

### Nota para orquestadores: uso de workflows de especificación

Para features o cambios significativos, los skills `spec-driven-development` y `planning-and-task-breakdown` son la referencia recomendada. Sin embargo, **el orquestador debe preguntar al usuario** si desea seguir el flujo completo de especificación y planificación antes de imponerlo. Si el usuario prefiere avanzar con un enfoque más directo (por ejemplo, una propuesta breve o openspec), el orquestador puede omitir el workflow completo y actuar según el contexto y la solicitud explícita del usuario.


### Skills locales del proyecto

- Core workflow: `spec-driven-development`, `planning-and-task-breakdown`, `incremental-implementation`, `test-driven-development`, `debugging-and-error-recovery`, `code-review-and-quality`, `code-simplification`, `shipping-and-launch`
- Discovery y contexto: `idea-refine`, `context-engineering`, `source-driven-development`, `documentation-and-adrs`
- Diseño e interfaces: `api-and-interface-design`, `frontend-ui-engineering`, `frontend-design`
- Especialistas por stack: `angular-architect`, `laravel-specialist`
- Calidad y operación: `performance-optimization`, `security-and-hardening`, `browser-testing-with-devtools`, `ci-cd-and-automation`, `git-workflow-and-versioning`, `deprecation-and-migration`

### Skills globales no versionados en este repo

- `context7-mcp`: usarlo cuando el usuario pregunte por librerías, frameworks, SDKs, APIs o CLIs y se necesite documentación actual.
- `find-skills`: usarlo cuando el usuario quiera descubrir o instalar nuevas skills.

### Relación entre skills y slash commands

- Los skills no aparecen automáticamente al escribir `/`.
- Para exponer un workflow en el menú slash, se debe crear un comando en `.opencode/command/*.md`.
- Los comandos slash de este proyecto son atajos hacia los skills; la lógica fuente sigue viviendo en `skills/`.

### Mapeo de intención a skill

- Discovery o clarificación inicial: `idea-refine`
- Feature o nueva funcionalidad: `spec-driven-development`, `planning-and-task-breakdown`, `incremental-implementation`, `test-driven-development`
- Planeación o desglose: `planning-and-task-breakdown`
- Bug o comportamiento inesperado: `debugging-and-error-recovery`
- Code review: `code-review-and-quality`
- Refactor o simplificación: `code-simplification`
- Diseño de API o interfaz: `api-and-interface-design`
- UI o frontend: `frontend-ui-engineering`, `frontend-design`
- Angular: `angular-architect`
- Laravel: `laravel-specialist`

### Lifecycle implícito

- EXPLORE: `idea-refine`
- DEFINE: `spec-driven-development`
- PLAN: `planning-and-task-breakdown`
- BUILD: `incremental-implementation` + `test-driven-development`
- VERIFY: `debugging-and-error-recovery`
- REVIEW: `code-review-and-quality`
- SHIP: `shipping-and-launch`
