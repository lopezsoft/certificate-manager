# Guía de Integración Frontend — Angular 18

**Proyecto:** Certificate Manager v2  
**Fecha:** 2026-04-20  
**Backend:** Laravel 10.x — API REST JSON  
**Versión API:** `/api/v2`

---

## 1. Configuración Inicial

### 1.1 Variables de Entorno Angular

```typescript
// environments/environment.ts
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api',
  wompiPublicKey: 'pub_test_XXXXXXXXX',
  wompiScriptUrl: 'https://checkout.wompi.co/widget.js',
};

// environments/environment.prod.ts
export const environment = {
  production: true,
  apiUrl: 'https://tu-dominio.com/api',
  wompiPublicKey: 'pub_ntest_XXXXXXXXX',
  wompiScriptUrl: 'https://checkout.wompi.co/widget.js',
};
```

### 1.2 Interceptor de Autenticación

```typescript
// core/interceptors/auth.interceptor.ts
import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from '../services/auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = inject(AuthService).getToken();
  if (token) {
    req = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }
  return next(req);
};
```

---

## 2. Flujo de Precios y Cotización

### Endpoint
```
GET /api/v2/pricing?quantity={n}&vigencia={1|2}
```
> No requiere autenticación.

### Modelo de Respuesta

```typescript
interface PricingResponse {
  quantity: number;
  vigencia: number;
  tier: 'RANGO_1' | 'RANGO_2' | 'RANGO_3';
  unit_price: number;       // COP sin IVA
  subtotal: number;         // COP sin IVA
  tax_amount: number;       // IVA 19%
  total_amount: number;     // COP con IVA
  currency: 'COP';
}
```

### Uso en Componente

```typescript
// features/pricing/pricing.component.ts
@Component({ /* ... */ })
export class PricingComponent {
  private http = inject(HttpClient);
  private apiUrl = inject(ENVIRONMENT).apiUrl;

  calculatePrice(quantity: number, vigencia: 1 | 2): Observable<PricingResponse> {
    return this.http.get<PricingResponse>(`${this.apiUrl}/v2/pricing`, {
      params: { quantity, vigencia }
    });
  }
}
```

---

## 3. Flujo de Compra con WOMPI

### 3.1 Crear Orden

```
POST /api/v2/orders
Authorization: Bearer {token}
Content-Type: application/json

Body: { "quantity": 5, "vigencia": 1 }
```

**Respuesta:**
```typescript
interface CreateOrderResponse {
  data: {
    id: number;
    quantity: number;
    vigencia: number;
    unit_price: number;
    total_amount: number;
    total_in_cents: number;    // ← usar para WOMPI (centavos)
    wompi_reference: string;   // referencia única del comercio
    status: 'PENDING';
    currency: 'COP';
  };
}
```

### 3.2 Widget WOMPI (checkout embebido)

```html
<!-- En el template del componente de pago -->
<form>
  <script
    src="https://checkout.wompi.co/widget.js"
    data-render="button"
    [attr.data-public-key]="wompiPublicKey"
    [attr.data-currency]="'COP'"
    [attr.data-amount-in-cents]="order.total_in_cents"
    [attr.data-reference]="order.wompi_reference"
    [attr.data-signature:integrity]="integritySignature"
    data-redirect-url="https://tu-dominio.com/payment/result">
  </script>
</form>
```

> **Importante:** La `signature:integrity` se genera en el backend. Solicítala en:
> ```
> GET /api/v2/orders/{id}/integrity-signature
> ```

### 3.3 Pago vía API (sin widget)

```
POST /api/v2/orders/{id}/pay
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "payment_source_id": "tok_test_XXXX",   // token de tarjeta
  "acceptance_token": "eyJ..."             // obtener de GET /api/v2/acceptance-token
}
```

**Respuesta:**
```typescript
interface PayOrderResponse {
  data: {
    transaction_id: string;
    status: 'PENDING' | 'APPROVED' | 'DECLINED' | 'ERROR';
    wompi_reference: string;
    redirect_url?: string;
  };
}
```

### 3.4 Obtener Acceptance Token

```
GET /api/v2/acceptance-token
Authorization: Bearer {token}
```

```typescript
interface AcceptanceTokenResponse {
  data: {
    acceptance_token: string;
    permalink: string;        // URL términos y condiciones
    type: string;
  };
}
```

---

## 4. Flujo de Solicitud de Certificado v2 (ANDES SCD)

### 4.1 Crear Solicitud

```
POST /api/v2/certificate-request
Authorization: Bearer {token}
Content-Type: multipart/form-data

Campos:
  type_organization_id   int       (1=PJ, 2=PN)
  legal_representative   string    nombre completo
  document_number        string    número de documento
  identity_document_id   int       (1=CC, 2=CE, 3=NIT)
  city_id                int       ID de ciudad interna
  address                string
  phone                  string    (opcional)
  mobile                 string
  email                  string
  formato                int       (2=físico, 3=PKCS10, 4=virtual)
  vigencia               int       (1 o 2 años)
  files[]                File[]    documentos de soporte (ZIP, PDF, imágenes)
```

**Respuestas posibles:**

| HTTP | Significado |
|------|-------------|
| 201  | Solicitud creada, proceder con validación de identidad |
| 402  | Sin cupo ni items disponibles — debe comprar primero |
| 422  | Error de validación de campos |

### 4.2 Flujo de Validación de Identidad ANDES

#### Paso 1 — Iniciar validación

```
POST /api/v2/andes/identity/start
Authorization: Bearer {token}
Content-Type: application/json

Body: { "certificate_request_id": 42 }
```

**Respuesta:**
```typescript
interface StartValidationResponse {
  data: {
    validation_id: number;
    token: string;                              // token de sesión ANDES
    validation_type: 'PhoneSelection' | 'ShowExam';
    questions?: Array<{                         // solo si ShowExam
      id: string;
      text: string;
      options: string[];
    }>;
  };
}
```

#### Paso 2a — Verificar OTP (si `validation_type === 'PhoneSelection'`)

```
POST /api/v2/andes/identity/verify-otp
Authorization: Bearer {token}
Content-Type: application/json

Body: { "token": "...", "otp_code": "123456" }
```

#### Paso 2b — Responder cuestionario (si `validation_type === 'ShowExam'`)

```
POST /api/v2/andes/identity/verify-questions
Authorization: Bearer {token}
Content-Type: application/json

Body: {
  "token": "...",
  "answers": [
    { "question_id": "Q1", "answer": "opcion_a" },
    { "question_id": "Q2", "answer": "opcion_c" }
  ]
}
```

#### Acciones adicionales

| Acción | Endpoint | Body |
|--------|----------|------|
| Reenviar OTP | `POST /v2/andes/identity/resend-otp` | `{ token, method: "SMS"\|"VOICE" }` |
| Cambiar a cuestionario | `POST /v2/andes/identity/bypass` | `{ token }` |
| Consultar estado | `GET /v2/andes/identity/status?token=...` | — |

**Estado final de identidad:**
```typescript
type AndesEstado = -1 | 0 | 1 | 2;
// -1 = No encontrado
//  0 = En curso
//  1 = Validado ✅ → proceder
//  2 = Fallido  ❌ → reintentar
```

---

## 5. Gestión de Cupos (Vista Admin)

> Solo accesible con token de usuario `type_id = 1` (Admin LOPEZSOFT).

### 5.1 Listar cupos por empresa

```
GET /api/v2/admin/quotas?company_id={id}
Authorization: Bearer {admin_token}
```

### 5.2 Asignar cupo POSTPAID

```
POST /api/v2/admin/quotas
Authorization: Bearer {admin_token}
Content-Type: application/json

Body:
{
  "company_id": 10,
  "allocated_quantity": 50,
  "period_start": "2026-05-01",
  "period_end": "2026-05-31",
  "notes": "Cupo mensual mayo 2026"
}
```

**Respuesta:**
```typescript
interface QuotaResponse {
  data: {
    id: number;
    company_id: number;
    allocated_quantity: number;
    used_quantity: number;
    remaining: number;
    period_start: string;
    period_end: string;
    status: 'ACTIVE' | 'EXHAUSTED' | 'EXPIRED';
    billing_type: 'POSTPAID';
  };
}
```

---

## 6. Health Check

```
GET /api/v2/health
Authorization: Bearer {token}
```

```typescript
interface HealthResponse {
  status: 'healthy' | 'degraded';
  services: {
    andes_id:  { status: 'up' | 'down'; latency_ms?: number };
    andes_pki: { status: 'up' | 'down'; message?: string };
    wompi:     { status: 'up' | 'down' };
  };
  cached: boolean;
  checked_at: string;
}
```

---

## 7. Manejo de Errores

Todos los errores siguen el formato:

```typescript
interface ApiError {
  success: false;
  message: string;
  errors?: Record<string, string[]>;  // solo en 422
}
```

| HTTP | Caso |
|------|------|
| 400  | Error de lógica de negocio (ej. cupo ya expirado) |
| 401  | Token inválido o expirado — redirigir a login |
| 402  | Sin cupo — redirigir a flujo de compra |
| 403  | Sin permisos de administrador |
| 422  | Error de validación — mostrar `errors` en formulario |
| 429  | Rate limit — mostrar mensaje de espera |
| 503  | API de ANDES no disponible — mostrar mensaje de mantenimiento |

### Interceptor de errores sugerido

```typescript
// core/interceptors/error.interceptor.ts
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        inject(AuthService).logout();
        inject(Router).navigate(['/login']);
      }
      if (error.status === 429) {
        inject(ToastService).warn('Demasiados intentos. Espera un momento.');
      }
      return throwError(() => error);
    })
  );
};
```

---

## 8. Secuencia Completa de Compra + Emisión

```
1. GET  /v2/pricing?quantity=5&vigencia=1       → Cotización
2. POST /v2/orders                               → Crear orden (PENDING)
3. GET  /v2/acceptance-token                     → Token aceptación T&C
4. [Widget WOMPI o POST /v2/orders/{id}/pay]     → Pagar
5. Escuchar webhook evento payment.approved      → Orden PAID, items creados
6. POST /v2/certificate-request                  → Crear solicitud
7. POST /v2/andes/identity/start                 → Iniciar validación identidad
8. POST /v2/andes/identity/verify-otp            → Verificar OTP
   (o POST /v2/andes/identity/verify-questions)
9. Escuchar webhook andes.certificate_emitted    → Certificado emitido 🎉
   (o polling: GET /v2/certificate-request/{id})
```

---

## 9. Rate Limits a considerar

| Endpoint | Límite |
|----------|--------|
| `/v2/andes/identity/start` | 3 req / 10 min |
| `/v2/andes/identity/verify-otp` | 5 req / 10 min |
| `/v2/andes/identity/resend-otp` | 2 req / 5 min |
| `/v2/andes/identity/bypass` | 2 req / 10 min |
| Endpoints admin trigger | 1 req / 5 min |

---

*Documento generado el 2026-04-20. Para dudas de integración, revisar los contratos en `routes/api-v2.php` y los controladores en `app/Http/Controllers/V2/`.*

