# Principios SOLID y CLEAN en Angular (Frontend)

Este documento es una guía de referencia rápida para humanos y agentes de IA sobre cómo aplicar los principios SOLID y Clean Architecture en el frontend de este proyecto (Angular 21).

## 1. Principios SOLID en Angular

### S - Single Responsibility Principle (SRP)
Un componente, servicio o módulo debe tener una sola razón para cambiar.
- **Componentes Tontos (Dumb):** En `src/app/shared`, deben recibir `@Input()` y emitir `@Output()`, pero no llamar APIs ni manejar lógica de negocio.
- **Componentes Inteligentes (Smart):** Ubicados en `features` (ej. `auth/login`), se encargan de unir la vista con los servicios de estado o peticiones.
- **Servicios Centralizados:** Las llamadas HTTP se delegan a `core/api.ts` o servicios de dominio.

### O - Open/Closed Principle (OCP)
Abierto para extensión, cerrado para modificación.
- **Proyección de Contenido:** Usa `<ng-content>` en componentes genéricos (como tarjetas o modales) en lugar de inyectar múltiples `@Input()` booleanos.
- **Directivas:** Usa directivas (`@Directive`) para agregar comportamiento a elementos del DOM sin modificar los componentes base.

### L - Liskov Substitution Principle (LSP)
Las subclases (o implementaciones de interfaces) deben ser sustituibles sin romper la app.
- **Modelos consistentes:** Si tienes una interfaz `User` y una clase extendida `Admin`, cualquier componente que acepte un `User` debe procesar correctamente un `Admin` (TypeScript tipado fuerte).

### I - Interface Segregation Principle (ISP)
Ningún cliente debería verse obligado a depender de métodos que no utiliza.
- **Interfaces pequeñas y precisas:** En lugar de exportar una gran interfaz `AppConfig` usada por todos, exporta pequeñas como `AuthConfig` o `UiConfig`.
- **Servicios segregados:** Divide un "UserService" gigante en "UserAuthService" y "UserProfileService".

### D - Dependency Inversion Principle (DIP)
Depende de abstracciones, no de clases concretas.
- **Inyección de Dependencias (DI):** Angular es excelente en esto gracias a sus constructores y el sistema de Providers.

#### Ejemplo Práctico de DIP en Angular (Uso de InjectionTokens):
**1. La Interfaz y el Token:**
```typescript
import { InjectionToken } from '@angular/core';

export interface Logger {
  log(message: string): void;
}

export const LOGGER_TOKEN = new InjectionToken<Logger>('Logger');
```

**2. Las Implementaciones:**
```typescript
import { Injectable } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class ConsoleLogger implements Logger {
  log(message: string): void { console.log('Console:', message); }
}

@Injectable({ providedIn: 'root' })
export class ServerLogger implements Logger {
  log(message: string): void { /* Enviar al backend */ }
}
```

**3. Inyección en el Componente:**
```typescript
import { Component, Inject } from '@angular/core';
import { LOGGER_TOKEN, Logger } from './logger.token';

@Component({
  selector: 'app-dashboard',
  template: `<button (click)="action()">Action</button>`
})
export class DashboardComponent {
  constructor(@Inject(LOGGER_TOKEN) private logger: Logger) {}

  action() {
    this.logger.log('Botón clickeado');
  }
}
```

**4. El Binding (en AppModule o Component Providers):**
```typescript
import { NgModule } from '@angular/core';
import { LOGGER_TOKEN } from './logger.token';
import { ConsoleLogger } from './console.logger'; // o ServerLogger

@NgModule({
  providers: [
    { provide: LOGGER_TOKEN, useClass: ConsoleLogger } // Fácil de cambiar
  ]
})
export class AppModule { }
```

---

## 2. Principios CLEAN en Angular

- **Estructura Estricta:**
  - `core/`: Servicios singletons (API, Auth), Guards, Interceptors. (Ej. `core/api.ts`).
  - `features/`: Módulos o componentes standalone organizados por dominio funcional (ej. `auth/login`, `admin/dashboard`).
  - `shared/`: Componentes UI reutilizables (ej. `language-switcher`, botones), pipes, directivas.
- **Testing (Vitest):**
  - Mantener los tests unitarios (`*.spec.ts`) junto a sus componentes.
  - Probar flujos de auth, guards y estados de error HTTP. Aislar servicios externos mockeándolos (Dependency Injection).
- **Estilo de Código:**
  - 2 espacios de indentación, comillas simples.
  - Archivos: kebab-case (`user-profile.component.ts`). Clases: PascalCase (`UserProfileComponent`).
- **Separación de Preocupaciones:** Mantener las plantillas (`.html`) y estilos (`.scss`) separados de la lógica del componente (`.ts`), evitando escribir lógica compleja en el HTML.
