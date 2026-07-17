# Skills del proyecto

Este directorio contiene el catálogo completo de skills disponibles para los agentes que trabajan en este monorepo.

La fuente canónica de cada skill es su archivo `SKILL.md` dentro de su respectivo directorio:

```
skills/<skill-name>/SKILL.md
```

> **Nota para orquestadores:** Si la herramienta `skill` de OpenCode no lista un skill documentado aquí, leer directamente el archivo `skills/<skill-name>/SKILL.md` usando la herramienta de lectura de archivos y aplicar el workflow que defina.

## Índice de skills

| Skill | Descripción | Ruta |
|---|---|---|
| `angular-architect` | Use when building Angular 17+ applications with standalone components or signals. Invoke for enterprise apps, RxJS patterns, NgRx state management, performance optimization, advanced routing. | `skills/angular-architect/SKILL.md` |
| `api-and-interface-design` | Guides stable API and interface design. Use when designing APIs, module boundaries, or any public interface. Use when creating REST or GraphQL endpoints, defining type contracts between modules, or establishing boundaries between frontend and backend. | `skills/api-and-interface-design/SKILL.md` |
| `browser-testing-with-devtools` | Tests in real browsers. Use when building or debugging anything that runs in a browser. Use when you need to inspect the DOM, capture console errors, analyze network requests, profile performance, or verify visual output with real runtime data via Chrome DevTools MCP. | `skills/browser-testing-with-devtools/SKILL.md` |
| `ci-cd-and-automation` | Automates CI/CD pipeline setup. Use when setting up or modifying build and deployment pipelines. Use when you need to automate quality gates, configure test runners in CI, or establish deployment strategies. | `skills/ci-cd-and-automation/SKILL.md` |
| `code-review-and-quality` | Conducts multi-axis code review. Use before merging any change. Use when reviewing code written by yourself, another agent, or a human. Use when you need to assess code quality across multiple dimensions before it enters the main branch. | `skills/code-review-and-quality/SKILL.md` |
| `code-simplification` | Simplifies code for clarity. Use when refactoring code for clarity without changing behavior. Use when code works but is harder to read, maintain, or extend than it should be. Use when reviewing code that has accumulated unnecessary complexity. | `skills/code-simplification/SKILL.md` |
| `context-engineering` | Optimizes agent context setup. Use when starting a new session, when agent output quality degrades, when switching between tasks, or when you need to configure rules files and context for a project. | `skills/context-engineering/SKILL.md` |
| `debugging-and-error-recovery` | Guides systematic root-cause debugging. Use when tests fail, builds break, behavior doesn't match expectations, or you encounter any unexpected error. Use when you need a systematic approach to finding and fixing the root cause rather than guessing. | `skills/debugging-and-error-recovery/SKILL.md` |
| `deprecation-and-migration` | Manages deprecation and migration. Use when removing old systems, APIs, or features. Use when migrating users from one implementation to another. Use when deciding whether to maintain or sunset existing code. | `skills/deprecation-and-migration/SKILL.md` |
| `documentation-and-adrs` | Records decisions and documentation. Use when making architectural decisions, changing public APIs, shipping features, or when you need to record context that future engineers and agents will need to understand the codebase. | `skills/documentation-and-adrs/SKILL.md` |
| `frontend-design` | Create distinctive, production-grade frontend interfaces with high design quality. Use this skill when the user asks to build web components, pages, artifacts, posters, or applications (examples include websites, landing pages, dashboards, React components, HTML/CSS layouts, or when styling/beautifying any web UI). Generates creative, polished code and UI design that avoids generic AI aesthetics. | `skills/frontend-design/SKILL.md` |
| `frontend-ui-engineering` | Builds production-quality UIs. Use when building or modifying user-facing interfaces. Use when creating components, implementing layouts, managing state, or when the output needs to look and feel production-quality rather than AI-generated. | `skills/frontend-ui-engineering/SKILL.md` |
| `git-workflow-and-versioning` | Structures git workflow practices. Use when making any code change. Use when committing, branching, resolving conflicts, or when you need to organize work across multiple parallel streams. | `skills/git-workflow-and-versioning/SKILL.md` |
| `idea-refine` | Refines ideas iteratively. Refine ideas through structured divergent and convergent thinking. Use "idea-refine" or "ideate" to trigger. | `skills/idea-refine/SKILL.md` |
| `incremental-implementation` | Delivers changes incrementally. Use when implementing any feature or change that touches more than one file. Use when you're about to write a large amount of code at once, or when a task feels too big to land in one step. | `skills/incremental-implementation/SKILL.md` |
| `laravel-specialist` | Use when building Laravel 10+ applications requiring Eloquent ORM, API resources, or queue systems. Invoke for Laravel models, Livewire components, Sanctum authentication, Horizon queues. | `skills/laravel-specialist/SKILL.md` |
| `openspec-apply-change` | Implement tasks from an OpenSpec change. Use when the user wants to start implementing, continue implementation, or work through tasks. | `skills/openspec-apply-change/SKILL.md` |
| `openspec-archive-change` | Archive a completed change in the experimental workflow. Use when the user wants to finalize and archive a change after implementation is complete. | `skills/openspec-archive-change/SKILL.md` |
| `openspec-explore` | Enter explore mode - a thinking partner for exploring ideas, investigating problems, and clarifying requirements. Use when the user wants to think through something before or during a change. | `skills/openspec-explore/SKILL.md` |
| `openspec-propose` | Propose a new change with all artifacts generated in one step. Use when the user wants to quickly describe what they want to build and get a complete proposal with design, specs, and tasks ready for implementation. | `skills/openspec-propose/SKILL.md` |
| `performance-optimization` | Optimizes application performance. Use when performance requirements exist, when you suspect performance regressions, or when Core Web Vitals or load times need improvement. Use when profiling reveals bottlenecks that need fixing. | `skills/performance-optimization/SKILL.md` |
| `planning-and-task-breakdown` | Breaks work into ordered tasks. Use when you have a spec or clear requirements and need to break work into implementable tasks. Use when a task feels too large to start, when you need to estimate scope, or when parallel work is possible. | `skills/planning-and-task-breakdown/SKILL.md` |
| `security-and-hardening` | Hardens code against vulnerabilities. Use when handling user input, authentication, data storage, or external integrations. Use when building any feature that accepts untrusted data, manages user sessions, or interacts with third-party services. | `skills/security-and-hardening/SKILL.md` |
| `shipping-and-launch` | Prepares production launches. Use when preparing to deploy to production. Use when you need a pre-launch checklist, when setting up monitoring, when planning a staged rollout, or when you need a rollback strategy. | `skills/shipping-and-launch/SKILL.md` |
| `source-driven-development` | Grounds every implementation decision in official documentation. Use when you want authoritative, source-cited code free from outdated patterns. Use when building with any framework or library where correctness matters. | `skills/source-driven-development/SKILL.md` |
| `spec-driven-development` | Creates specs before coding. Use when starting a new project, feature, or significant change and no specification exists yet. Use when requirements are unclear, ambiguous, or only exist as a vague idea. | `skills/spec-driven-development/SKILL.md` |
| `test-driven-development` | Drives development with tests. Use when implementing any logic, fixing any bug, or changing any behavior. Use when you need to prove that code works, when a bug report arrives, or when you're about to modify existing functionality. | `skills/test-driven-development/SKILL.md` |
| `using-agent-skills` | Discovers and invokes agent skills. Use when starting a session or when you need to discover which skill applies to the current task. This is the meta-skill that governs how all other skills are discovered and invoked. | `skills/using-agent-skills/SKILL.md` |

## Skills destacados por fase de trabajo

| Fase | Skills recomendados |
|---|---|
| Exploración y clarificación | `idea-refine`, `context-engineering` |
| Definición de especificación | `spec-driven-development` |
| Planificación y desglose | `planning-and-task-breakdown` |
| Implementación | `incremental-implementation`, `test-driven-development` |
| Verificación | `debugging-and-error-recovery` |
| Revisión | `code-review-and-quality` |
| Despliegue | `shipping-and-launch` |

## Nota sobre flujos de especificación

Para features o cambios significativos, los skills `spec-driven-development` y `planning-and-task-breakdown` son la referencia recomendada. Sin embargo, el orquestador debe preguntar al usuario si desea seguir el flujo completo de especificación y planificación antes de imponerlo. Si el usuario prefiere avanzar con un enfoque más directo, el orquestador puede omitir el workflow completo y actuar según el contexto y la solicitud explícita del usuario.
