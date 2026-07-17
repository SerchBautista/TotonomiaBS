# Principios SOLID y CLEAN en Laravel (Backend)

Este documento es una guía de referencia rápida para humanos y agentes de IA sobre cómo aplicar los principios SOLID y Clean Architecture en el backend de este proyecto (Laravel 12 API).

## 1. Principios SOLID en Laravel

### S - Single Responsibility Principle (SRP)
Una clase, módulo o función debe tener una, y solo una, razón para cambiar.
- **Controladores delgados:** Ubicados en `app/Http/Controllers/Api/`. Solo reciben requests, delegan a servicios/actions y retornan responses.
- **Validación:** Usar `FormRequests` (`app/Http/Requests`) en lugar de validar en el controlador.
- **Lógica de negocio:** Usar clases Action o Services dedicadas (`app/Actions` o `app/Services`).

### O - Open/Closed Principle (OCP)
Abierto para extensión, cerrado para modificación.
- **Eventos y Listeners:** En lugar de agregar más código a un servicio (ej. enviar email al crear usuario), dispara un evento (`UserRegistered`) y crea Listeners separados.

### L - Liskov Substitution Principle (LSP)
Las subclases deben poder sustituir a sus clases base sin romper el programa.
- **Interfaces consistentes:** Si tienes diferentes pasarelas de pago (`PaymentGateway`), todas deben devolver el mismo tipo de respuesta y lanzar las mismas excepciones base.

### I - Interface Segregation Principle (ISP)
Ningún cliente debería verse obligado a depender de métodos que no utiliza.
- **Interfaces pequeñas:** Crea interfaces específicas (ej. `UserReaderInterface` y `UserWriterInterface`) en lugar de repositorios gigantes (`UserRepositoryInterface` con 30 métodos).

### D - Dependency Inversion Principle (DIP)
Los módulos de alto nivel no deben depender de los de bajo nivel. Ambos deben depender de abstracciones.
- **Uso del Service Container:** Inyectar interfaces en lugar de instanciar clases concretas.

#### Ejemplo Práctico de DIP en Laravel:
**1. El Contrato (Interfaz):**
```php
namespace App\Contracts;

interface PaymentServiceInterface 
{
    public function charge(float $amount): bool;
}
```

**2. Las Implementaciones (Clases Concretas):**
```php
namespace App\Services;
use App\Contracts\PaymentServiceInterface;

class StripePaymentService implements PaymentServiceInterface 
{
    public function charge(float $amount): bool { return true; }
}

class PaypalPaymentService implements PaymentServiceInterface 
{
    public function charge(float $amount): bool { return true; }
}
```

**3. Inyección en el Controlador:**
```php
namespace App\Http\Controllers\Api;

use App\Contracts\PaymentServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private PaymentServiceInterface $paymentService;

    // Se inyecta la interfaz, no la clase concreta
    public function __construct(PaymentServiceInterface $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function process(Request $request)
    {
        $result = $this->paymentService->charge($request->amount);
        return response()->json(['success' => $result]);
    }
}
```

**4. El Binding (Service Provider):**
```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PaymentServiceInterface;
use App\Services\StripePaymentService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Intercambiar de Stripe a PayPal requiere cambiar solo esta línea
        $this->app->bind(PaymentServiceInterface::class, StripePaymentService::class);
    }
}
```

---

## 2. Principios CLEAN en Laravel

- **Estándares:** Aplica PSR-12. (Herramienta: `./vendor/bin/pint`).
- **Tests (Feature & Unit):** 
  - Las reglas de negocio se prueban en aislamiento (`tests/Unit`).
  - Los endpoints de la API se prueban simulando peticiones HTTP reales (`tests/Feature`).
  - Los nombres de los tests deben describir el comportamiento (ej. `test_admin_can_create_user_and_receive_token`).
- **Nomenclatura:** Clases en PascalCase, métodos en camelCase.
- **Independencia de Frameworks (Clean Architecture):** La lógica central del dominio no debe depender de HTTP (Requests/Responses) ni de Eloquent directamente si es posible evitarlo, manteniendo las dependencias hacia adentro (Controlador -> Action/Servicio -> Modelo).
