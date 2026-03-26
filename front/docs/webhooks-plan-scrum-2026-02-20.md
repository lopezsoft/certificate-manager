# Plan de Trabajo SCRUM — Módulo de Webhooks
> **Creado:** 2026-02-20  
> **Proyecto:** Certificate Manager — Frontend Angular 18  
> **Feature branch:** `feature/webhooks-management`  
> **Documentación base:** `docs/webhooks-frontend.md`

---

## Tabla de Contenidos

1. [Visión General](#1-visión-general)
2. [Arquitectura del Módulo](#2-arquitectura-del-módulo)
3. [Product Backlog](#3-product-backlog)
4. [Sprints](#4-sprints)
5. [Convenciones y Principios](#5-convenciones-y-principios)
6. [Diagrama de Componentes](#6-diagrama-de-componentes)

---

## 1. Visión General

El módulo de Webhooks permite a los **usuarios autenticados** de una compañía configurar endpoints externos para recibir notificaciones automáticas ante eventos del sistema (creación de solicitudes, cambios de estado, etc.).

**Objetivo:** Implementar la interfaz completa de gestión de webhooks dentro del módulo `settings`, respetando la arquitectura existente (herencia de `BaseComponent`/`FormComponent`, servicio `HttpResponsesService`, `MessagesService`, `DebugService`) y los estilos/patrones de UI/UX actuales (Bootstrap 5, Vuexy, ng-bootstrap, ng-select).

**Criterios de aceptación generales:**
- Accesible desde `/settings/webhooks` para **todos los roles** (sin restricción de rol).
- Ítem de menú añadido dentro del submenú de Ajustes, visible para todos los usuarios autenticados.
- Totalmente responsivo (mobile-first).
- Todos los flujos de negocio documentados en `webhooks-frontend.md` correctamente implementados.
- Sin `console.log` directos — usar `DebugService`.
- Cobertura de errores HTTP mediante el `ErrorInterceptor` existente.

---

## 2. Arquitectura del Módulo

Se respetan estrictamente las **convenciones de ubicación de archivos** del proyecto. Los artefactos de dominio (interfaces, modelos, servicios, enums, pipes) viven en sus carpetas compartidas globales; la carpeta del módulo contiene **únicamente los componentes**.

### Archivos nuevos y su ubicación exacta

```
src/
│
├── @core/
│   └── pipes/                                           # Pipes globales del template
│       ├── webhook-health.pipe.ts                       ← NUEVO (Sprint 1)
│       └── pipes.module.ts                             ← registrar WebhookHealthPipe
│
└── app/
    │
    ├── interfaces/                                      # Interfaces TypeScript globales
    │   ├── webhook-endpoint.interface.ts               ← NUEVO (Sprint 0)
    │   ├── webhook-delivery.interface.ts               ← NUEVO (Sprint 0)
    │   └── index.ts                                    ← re-exportar ambas interfaces
    │
    ├── models/                                          # Modelos de entidad de dominio
    │   └── webhook.model.ts                            ← NUEVO (Sprint 0)
    │
    ├── common/
    │   └── enums/
    │       └── WebhookStatus.ts                        ← NUEVO (Sprint 0)
    │
    ├── services/                                        # Servicios HTTP por dominio
    │   └── webhooks/                                   ← NUEVA carpeta (patrón companies/)
    │       ├── webhooks.service.ts                     ← NUEVO (Sprint 1)
    │       └── index.ts                               ← exportar WebhooksService
    │
    └── settings/
        └── webhooks/                                   ← Solo componentes del módulo
            ├── webhooks-routing.module.ts              ← NUEVO (Sprint 0)
            ├── webhooks.module.ts                     ← NUEVO (Sprint 0)
            │
            ├── webhooks-list/                          # Sprint 2
            │   ├── webhooks-list.component.ts          # extends BaseComponent
            │   ├── webhooks-list.component.html
            │   └── webhooks-list.component.scss
            │
            ├── webhook-form/                           # Sprint 2
            │   ├── webhook-form.component.ts           # extends FormComponent
            │   ├── webhook-form.component.html
            │   └── webhook-form.component.scss
            │
            ├── webhook-secret-modal/                   # Sprint 3
            │   ├── webhook-secret-modal.component.ts
            │   ├── webhook-secret-modal.component.html
            │   └── webhook-secret-modal.component.scss
            │
            └── webhook-deliveries/                    # Sprint 3
                ├── webhook-deliveries.component.ts    # extends BaseComponent
                ├── webhook-deliveries.component.html
                └── webhook-deliveries.component.scss
```

**Archivos existentes a modificar:**
```
src/app/interfaces/index.ts              ← añadir exports de las nuevas interfaces
src/app/settings/settings-routing.module.ts  ← registrar ruta 'webhooks'
src/app/settings/settings.module.ts     ← no cambia (WebhooksModule es lazy)
src/app/menu/menu.ts                    ← añadir ítem de menú 'Webhooks' en Ajustes
src/@core/pipes/pipes.module.ts         ← declarar y exportar WebhookHealthPipe
```

---

## 3. Product Backlog

### Épica: Gestión de Webhooks

| ID   | Historia de Usuario | Prioridad | Puntos | Sprint |
|------|---------------------|-----------|--------|--------|
| US-01 | Como usuario autenticado, quiero ver la lista de webhooks de mi compañía con su estado de salud visual | Alta | 5 | 2 |
| US-02 | Como usuario autenticado, quiero crear un webhook nuevo indicando URL, eventos suscritos y descripción | Alta | 8 | 2 |
| US-03 | Como usuario autenticado, quiero editar URL, eventos y estado activo/inactivo de un webhook existente | Alta | 5 | 2 |
| US-04 | Como usuario autenticado, quiero eliminar un webhook con confirmación previa | Alta | 3 | 2 |
| US-05 | Como usuario autenticado, quiero obtener el secret de un webhook recién creado (rotate-secret) | Alta | 8 | 3 |
| US-06 | Como usuario autenticado, quiero rotar el secret de un webhook existente con advertencia de invalidación | Alta | 5 | 3 |
| US-07 | Como usuario autenticado, quiero ver el historial de entregas de un webhook con paginación | Media | 8 | 3 |
| US-08 | Como usuario autenticado, quiero visualizar el payload completo de una entrega en un modal formateado | Media | 5 | 3 |
| US-09 | Como usuario autenticado, quiero ver la información de firma HMAC como referencia técnica | Baja | 3 | 4 |

---

## 4. Sprints

---

### Sprint 0 — Preparación e Infraestructura _(3 días)_

**Objetivo:** Establecer la base del módulo para que los demás sprints puedan ejecutarse en paralelo sin bloqueos.

#### Tareas Técnicas

| # | Tarea | Archivo(s) | Responsable | Estado |
|---|-------|-----------|-------------|--------|
| T-01 | Crear carpeta y scaffolding de componentes vacíos | `src/app/settings/webhooks/**` | Dev | ⬜ |
| T-02 | Crear `webhooks.module.ts` con imports (CoreModule, CoreCommonModule, CommonComponentsModule, CorePipesModule) | `settings/webhooks/webhooks.module.ts` | Dev | ⬜ |
| T-03 | Crear `webhooks-routing.module.ts` con las 4 rutas declaradas | `settings/webhooks/webhooks-routing.module.ts` | Dev | ⬜ |
| T-04 | Registrar ruta lazy `webhooks` en settings routing | `src/app/settings/settings-routing.module.ts` | Dev | ⬜ |
| T-05 | Crear interfaces de dominio en la carpeta global de interfaces | `src/app/interfaces/webhook-endpoint.interface.ts` `src/app/interfaces/webhook-delivery.interface.ts` | Dev | ⬜ |
| T-06 | Re-exportar nuevas interfaces en el barrel de interfaces | `src/app/interfaces/index.ts` | Dev | ⬜ |
| T-07 | Crear modelo de entidad webhook | `src/app/models/webhook.model.ts` | Dev | ⬜ |
| T-08 | Crear enum `WebhookHealthStatus` en la carpeta de enums global | `src/app/common/enums/WebhookStatus.ts` | Dev | ⬜ |
| T-09 | Crear carpeta `services/webhooks/` con `index.ts` (sin implementación aún) | `src/app/services/webhooks/webhooks.service.ts` `src/app/services/webhooks/index.ts` | Dev | ⬜ |
| T-10 | Añadir ítem de menú "Webhooks" en Ajustes (sin `role` guard, patrón igual a otros ítems) | `src/app/menu/menu.ts` | Dev | ⬜ |
| T-11 | Añadir variables SCSS del módulo reutilizando variables de `_variables.scss` | `src/assets/scss/custom/webhooks.component.scss` | Dev | ⬜ |

#### Definición de interfaces

> Estos archivos van en `src/app/interfaces/` y se re-exportan en el `index.ts` de la misma carpeta, igual que `crud.interface.ts`, `json-response.interface.ts`, etc.

```typescript
// src/app/interfaces/webhook-endpoint.interface.ts
export interface WebhookEndpoint {
  id: number;
  company_id: number;
  url: string;
  events: string[];
  is_active: boolean;
  description: string | null;
  failure_count: number;
  last_triggered_at: string | null;
  created_at: string;
  updated_at: string;
}

export type WebhookHealthStatus =
  | 'active'            // is_active: true, failure_count: 0
  | 'warning'           // is_active: true, failure_count: 1-9
  | 'auto-disabled'     // is_active: false, failure_count >= 10
  | 'paused';           // is_active: false, failure_count < 10

export interface WebhookCreateRequest {
  url: string;
  events: string[];
  description?: string;
}

export interface WebhookUpdateRequest {
  url?: string;
  events?: string[];
  is_active?: boolean;
  description?: string;
}

export interface WebhookRotateSecretResponse {
  data: WebhookEndpoint;
  secret: string;
}

// src/app/interfaces/webhook-delivery.interface.ts
export interface WebhookDelivery {
  id: number;
  webhook_endpoint_id: number;
  event_type: string;
  payload: Record<string, any>;
  http_status: number | null;
  response_body: string | null;
  status: 'pending' | 'delivered' | 'failed';
  attempt: number;
  delivered_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface WebhookDeliveriesResponse {
  data: WebhookDelivery[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
```

#### Modelo de entidad

> Va en `src/app/models/webhook.model.ts`, siguiendo el patrón de `companies-model.ts`, `users-model.ts`, etc.

```typescript
// src/app/models/webhook.model.ts
export class WebhookModel {}
// Las interfaces de entidad viven en src/app/interfaces/ (ver arriba)
```

#### Enum de salud

> Va en `src/app/common/enums/WebhookStatus.ts`, siguiendo el patrón de `DocumentStatus.ts`.

```typescript
// src/app/common/enums/WebhookStatus.ts
export enum WebhookHealthStatus {
  ACTIVE        = 'active',         // is_active: true, failure_count: 0
  WARNING       = 'warning',        // is_active: true, failure_count: 1–9
  AUTO_DISABLED = 'auto-disabled',  // is_active: false, failure_count >= 10
  PAUSED        = 'paused',         // is_active: false, failure_count < 10
}

export enum WebhookDeliveryStatus {
  PENDING   = 'pending',
  DELIVERED = 'delivered',
  FAILED    = 'failed',
}
```

#### Definición del servicio

> Va en `src/app/services/webhooks/webhooks.service.ts`, siguiendo el patrón de `services/companies/company.service.ts`.  
> Se exporta desde `src/app/services/webhooks/index.ts`.

```typescript
// src/app/services/webhooks/webhooks.service.ts
@Injectable({ providedIn: 'root' })
export class WebhooksService {
  private readonly endpoint = '/webhooks';

  constructor(
    private api: HttpResponsesService,   // desde src/app/utils
    private debug: DebugService,          // desde src/app/utils
  ) {}

  getEvents(): Observable<{ data: string[] }>
  list(): Observable<{ data: WebhookEndpoint[] }>
  getById(id: number): Observable<{ data: WebhookEndpoint }>
  create(body: WebhookCreateRequest): Observable<{ data: WebhookEndpoint }>
  update(id: number, body: WebhookUpdateRequest): Observable<{ data: WebhookEndpoint }>
  delete(id: number): Observable<void>
  rotateSecret(id: number): Observable<WebhookRotateSecretResponse>
  getDeliveries(id: number, limit?: number): Observable<{ data: WebhookDeliveriesResponse }>
}
```

#### Criterios de Done Sprint 0:
- [ ] `ng build` sin errores
- [ ] Módulo cargando vía lazy load en `/settings/webhooks`
- [ ] Interfaces y enums creados en sus carpetas globales y re-exportados en los `index.ts` correspondientes
- [ ] Carpeta `services/webhooks/` creada con su `index.ts`
- [ ] Sin código de lógica de negocio todavía (solo scaffolding)

---

### Sprint 1 — Servicio y Pipe de Salud _(2 días)_

**Objetivo:** Implementar la capa de servicios completa con manejo de errores y la pipe de salud visual.

#### Tareas Técnicas

| # | Tarea | Archivo | Estado |
|---|-------|---------|--------|
| T-12 | Implementar todos los métodos HTTP en `WebhooksService` | `src/app/services/webhooks/webhooks.service.ts` | ⬜ |
| T-13 | Implementar `WebhookHealthPipe` usando el enum `WebhookHealthStatus` | `src/@core/pipes/webhook-health.pipe.ts` | ⬜ |
| T-14 | Declarar y exportar `WebhookHealthPipe` en `CorePipesModule` | `src/@core/pipes/pipes.module.ts` | ⬜ |
| T-15 | Unit test de `WebhooksService` (mock con `HttpClientTestingModule`) | `src/app/services/webhooks/webhooks.service.spec.ts` | ⬜ |
| T-16 | Unit test de `WebhookHealthPipe` — los 4 casos del enum | `src/@core/pipes/webhook-health.pipe.spec.ts` | ⬜ |

> **Nota de patrón:** `WebhookHealthPipe` se crea en `src/@core/pipes/` y se registra en `CorePipesModule`, exactamente igual que `InitialsPipe`, `FilterPipe`, etc. Al estar en `CorePipesModule` queda disponible en cualquier módulo que importe `CoreCommonModule`.

#### Lógica de `WebhookHealthPipe`

```typescript
// src/@core/pipes/webhook-health.pipe.ts
@Pipe({ name: 'webhookHealth' })
export class WebhookHealthPipe implements PipeTransform {
  transform(webhook: WebhookEndpoint): WebhookHealthStatus {
    if (!webhook.is_active && webhook.failure_count >= 10) return WebhookHealthStatus.AUTO_DISABLED;
    if (!webhook.is_active)       return WebhookHealthStatus.PAUSED;
    if (webhook.failure_count > 0) return WebhookHealthStatus.WARNING;
    return WebhookHealthStatus.ACTIVE;
  }
}
```

#### Mapping visual del pipe

| Estado | Badge color | Texto | Clase CSS |
|--------|-------------|-------|-----------|
| `ACTIVE` | Verde (`success`) | Activo | `badge-webhook--active` |
| `WARNING` | Naranja (`warning`) | Con advertencias | `badge-webhook--warning` |
| `PAUSED` | Amarillo | Pausado | `badge-webhook--paused` |
| `AUTO_DISABLED` | Rojo (`danger`) | Auto-desactivado | `badge-webhook--auto-disabled` |

#### Criterios de Done Sprint 1:
- [ ] Servicio funcionando contra el backend local (`http://cm-api.test`)
- [ ] Pipe produciendo los 4 estados correctamente
- [ ] Tests pasando (`ng test`)

---

### Sprint 2 — Lista y Formulario _(4 días)_

**Objetivo:** Implementar US-01, US-02, US-03, US-04 con UI completa.

#### Tareas Técnicas

| # | Tarea | US | Estado |
|---|-------|-----|--------|
| T-17 | `WebhooksListComponent` — estructura HTML con `LayoutComponentComponent` | US-01 | ⬜ |
| T-18 | Tabla de webhooks con badge de salud (via `webhookHealth` pipe), URL, eventos, última ejecución | US-01 | ⬜ |
| T-19 | Botones de acción: Editar, Gestionar secret, Ver entregas, Eliminar | US-01 | ⬜ |
| T-20 | Indicador contador "X / 5 webhooks usados" con constante `WEBHOOK_MAX_COUNT` | US-01 | ⬜ |
| T-21 | `WebhookFormComponent` — Formulario reactivo en modal (`NgbModal`) | US-02, US-03 | ⬜ |
| T-22 | Validaciones en el formulario (URL max 500, mínimo 1 evento) | US-02, US-03 | ⬜ |
| T-23 | `ng-select` para selección múltiple de eventos (cargados dinámicamente desde `getEvents()`) | US-02, US-03 | ⬜ |
| T-24 | Toggle `is_active` con label descriptivo | US-03 | ⬜ |
| T-25 | Confirmar eliminación con `MessagesService.confirm()` | US-04 | ⬜ |
| T-26 | Manejo de error 422 con errores de campo del backend | US-02, US-03 | ⬜ |
| T-27 | Estilos SCSS del módulo en `assets/scss/custom/webhooks.component.scss` | — | ⬜ |

#### Estructura del Formulario Reactivo

```typescript
// webhook-form.component.ts
this.customForm = this.fb.group({
  url: [
    '',
    [Validators.required, Validators.maxLength(500), Validators.pattern(/^https?:\/\/.+/)]
  ],
  events: [[], [Validators.required, Validators.minLength(1)]],
  description: ['', [Validators.maxLength(255)]],
  is_active: [true],
});
```

#### Wireframe — Lista de Webhooks

```
┌─────────────────────────────────────────────────────────┐
│  Webhooks                          [ + Nuevo Webhook ]   │
│  Mostrando 2 / 5 webhooks disponibles                    │
├─────────────────────────────────────────────────────────┤
│ URL                   │ Eventos │ Estado  │ Último  │ ⚙  │
├──────────────────────-┼─────────┼─────────┼─────────┼───┤
│ https://erp.com/hk   │  2      │🟢Activo │ hace 2h │ … │
│ https://crm.com/hk   │  4      │🟡Pausado│ hace 1d │ … │
└─────────────────────────────────────────────────────────┘
```

#### Wireframe — Formulario (panel/modal)

```
┌──────────────────────────────────┐
│  Nuevo Webhook                 × │
├──────────────────────────────────┤
│  URL del Endpoint *              │
│  [ https://...                 ] │
│                                  │
│  Eventos a suscribir *           │
│  [ certificate_request ▼  × …  ] │
│                                  │
│  Descripción (opcional)          │
│  [ Notificaciones al ERP…      ] │
│                                  │
│  Estado                          │
│  [●] Activo                      │
├──────────────────────────────────┤
│          [Cancelar] [Guardar]    │
└──────────────────────────────────┘
```

#### Criterios de Done Sprint 2:
- [ ] CRUD completo funcionando
- [ ] Validaciones de frontend visibles
- [ ] Errores del backend (422) mapeados a campos del formulario
- [ ] Límite de 5 webhooks respetado (deshabilitar botón al llegar al límite)
- [ ] `DebugService` usado en lugar de `console.log`

---

### Sprint 3 — Secret y Historial de Entregas _(4 días)_

**Objetivo:** Implementar US-05, US-06, US-07, US-08.

#### Tareas Técnicas

| # | Tarea | US | Estado |
|---|-------|-----|--------|
| T-28 | `WebhookSecretModalComponent` — Modal para mostrar secret | US-05, US-06 | ⬜ |
| T-29 | Input readonly + botón "Copiar al portapapeles" con feedback visual | US-05, US-06 | ⬜ |
| T-30 | Advertencia "Este secret no se volverá a mostrar" con ícono de alerta | US-05, US-06 | ⬜ |
| T-31 | Flujo post-creación: abrir automáticamente modal de secret tras 201 | US-05 | ⬜ |
| T-32 | Confirmación previa al rotar secret con `MessagesService.confirm()` | US-06 | ⬜ |
| T-33 | `WebhookDeliveriesComponent` — Vista historial con tabla paginada | US-07 | ⬜ |
| T-34 | Columnas: ID, Evento, Estado (badge), HTTP Status, Intento, Fecha | US-07 | ⬜ |
| T-35 | Paginación con `limit` configurable | US-07 | ⬜ |
| T-36 | Modal de detalle de payload con formato JSON resaltado (`ngx-highlightjs`) | US-08 | ⬜ |
| T-37 | Badge `delivered/failed/pending` con colores correctos del enum `WebhookDeliveryStatus` | US-07 | ⬜ |

#### Flujo de Secret (post-creación)

```
POST /webhooks  ─→  201 Created
       ↓
Toast: "Webhook creado. Obtén tu secret ahora."
       ↓
Abrir automáticamente WebhookSecretModalComponent
       ↓
Llamar POST /webhooks/{id}/rotate-secret
       ↓
Mostrar secret en input readonly
+ Botón copiar + Advertencia visual
       ↓
Botón "Entendido, lo guardé"
```

#### Wireframe — Modal de Secret

```
┌──────────────────────────────────────────┐
│  🔑 Secret del Webhook                 × │
├──────────────────────────────────────────┤
│  ⚠️  IMPORTANTE                          │
│  Este secret se muestra una sola vez.   │
│  Cópialo y guárdalo en un lugar seguro. │
├──────────────────────────────────────────┤
│  Tu secret:                              │
│  [ xK9mP2qL7nR4tY1uI8...  ] [📋 Copiar] │
├──────────────────────────────────────────┤
│           [✅ Entendido, lo guardé]      │
└──────────────────────────────────────────┘
```

#### Wireframe — Historial de Entregas

```
┌────────────────────────────────────────────────────────────┐
│  ← Volver   Entregas: https://erp.com/webhook              │
├────────────────────────────────────────────────────────────┤
│ # │ Evento              │ Estado    │ HTTP │ Intento │ Fecha│
├───┼─────────────────────┼───────────┼──────┼─────────┼─────┤
│42 │ status_changed      │ 🟢 Entregado │ 200  │   1    │ 10:30│
│41 │ created             │ 🔴 Fallido   │ 500  │   3    │ 09:00│
└────────────────────────────────────────────────────────────┘
              [Ver payload completo] al pulsar una fila
```

#### Criterios de Done Sprint 3:
- [ ] Secret mostrado UNA SOLA VEZ y nunca almacenado en estado persistente
- [ ] Copia al portapapeles funcional
- [ ] Historial con paginación operativa
- [ ] Viewer de payload JSON con syntax highlighting
- [ ] Confirmación al rotar secret funcionando

---

### Sprint 4 — Referencia HMAC, Pulido y QA _(3 días)_

**Objetivo:** Implementar US-09, refinar la UX y garantizar calidad.

#### Tareas Técnicas

| # | Tarea | US | Estado |
|---|-------|-----|--------|
| T-38 | Sección "Verificación de firma" en detalle de webhook | US-09 | ⬜ |
| T-39 | Mostrar headers de ejemplo con código formateado | US-09 | ⬜ |
| T-40 | Bloque instructivo con formato `X-Webhook-Sig` | US-09 | ⬜ |
| T-41 | Accesibilidad: ARIA labels en botones y modales | — | ⬜ |
| T-42 | Responsividad: revisar en 320px, 768px, 1280px | — | ⬜ |
| T-43 | Revisar todos los flujos de error (404, 422, 401) | — | ⬜ |
| T-44 | Loading states con `ng-block-ui` en todas las llamadas async | — | ⬜ |
| T-45 | Estado vacío (empty state) cuando no hay webhooks | — | ⬜ |
| T-46 | Traducción de textos al módulo i18n español | — | ⬜ |
| T-47 | E2E smoke test básico | — | ⬜ |

#### Criterios de Done Sprint 4 (Definition of Done Global):
- [ ] `ng build --configuration production` sin warnings
- [ ] Funcionalidad completa probada contra backend real
- [ ] Todos los estados de error HTTP manejados
- [ ] Sin `console.log` residuales
- [ ] Código revisado con principios SOLID
- [ ] PR aprobado con revisión de código

---

## 5. Convenciones y Principios

### SOLID aplicado al módulo

| Principio | Aplicación |
|-----------|-----------|
| **SRP** | `WebhooksService` (en `services/webhooks/`) solo hace HTTP; `WebhookHealthPipe` (en `@core/pipes/`) solo computa estado visual; `WebhookHealthStatus` (en `common/enums/`) solo define valores |
| **OCP** | `WebhookFormComponent extends FormComponent` — extensible sin modificar la base; `WebhookHealthPipe` extensible agregando casos sin cambiar consumidores |
| **LSP** | Los componentes derivados son intercambiables con `BaseComponent` y `FormComponent` en cualquier contexto |
| **ISP** | Interfaces en `src/app/interfaces/` separadas por responsabilidad: `WebhookEndpoint`, `WebhookDelivery`, `WebhookCreateRequest`, `WebhookUpdateRequest`, `WebhookRotateSecretResponse` |
| **DIP** | Componentes dependen de `HttpResponsesService` y `MessagesService` desde `src/app/utils/` (abstracciones), nunca de `HttpClient` directamente |

### Clean Code

- Nombres en **inglés** para clases, métodos, variables; **español** para UI y comentarios públicos.
- Métodos de máximo **20 líneas**; responsabilidad única.
- Sin magic numbers: usar constantes (`WEBHOOK_MAX_COUNT = 5`).
- Manejo de subscripciones con `takeUntilDestroyed` (Angular 18) o `Subject + takeUntil`.
- Todos los métodos async consumen `Observable` del servicio, sin `async/await` mezclado con RxJS.
- `DebugService` en lugar de `console.*` en todos los archivos.

### Convenciones de Commits (Conventional Commits)

```
feat(webhooks): add webhook list component with health badge
feat(webhooks): implement create/edit form with reactive validation
feat(webhooks): add secret modal with clipboard copy
feat(webhooks): add deliveries history with JSON payload viewer
feat(webhooks): add HMAC signature reference section
fix(webhooks): handle 422 validation errors on webhook form
chore(webhooks): add DebugService to all webhook components
test(webhooks): add unit tests for WebhooksService and WebhookHealthPipe
```

### Variables SCSS (no hardcodear colores)

Usar las variables existentes en `src/assets/scss/_variables.scss`:

```scss
// En webhooks.component.scss
.badge-webhook {
  &--active       { background-color: $table-color-available; }   // #4CAF50
  &--warning      { background-color: $background-container; }    // Naranja
  &--paused       { background-color: #ffc107; }                  // Definir var en _variables.scss
  &--auto-disabled{ background-color: $table-color-occupied; }    // #F44336
}
```

---

## 6. Diagrama de Componentes

```
ARTEFACTOS GLOBALES (carpetas compartidas del proyecto)
─────────────────────────────────────────────────────────────────
src/app/interfaces/
  webhook-endpoint.interface.ts   ← WebhookEndpoint, WebhookCreateRequest,
  webhook-delivery.interface.ts     WebhookUpdateRequest, WebhookRotateSecretResponse

src/app/models/
  webhook.model.ts                ← WebhookModel (clase entidad)

src/app/common/enums/
  WebhookStatus.ts                ← WebhookHealthStatus, WebhookDeliveryStatus

src/app/services/webhooks/
  webhooks.service.ts             ← WebhooksService (HTTP, SRP)
  index.ts                        ← re-export

src/@core/pipes/
  webhook-health.pipe.ts          ← WebhookHealthPipe (transform visual)
  pipes.module.ts                 ← declarar / exportar WebhookHealthPipe

src/assets/scss/custom/
  webhooks.component.scss         ← .badge-webhook, variables sin hardcodear

MÓDULO DE SETTINGS (solo componentes)
─────────────────────────────────────────────────────────────────
settings-routing.module.ts  ← ruta 'webhooks' → WebhooksModule (lazy)
menu/menu.ts                ← ítem Webhooks sin role guard

src/app/settings/webhooks/
  webhooks.module.ts
  webhooks-routing.module.ts
  │
  ├── WebhooksListComponent (extends BaseComponent)
  │     ├── usa WebhooksService (inyectado)
  │     ├── usa WebhookHealthPipe (| webhookHealth en template)
  │     ├── usa LayoutComponentComponent (de CommonComponentsModule)
  │     ├── usa MessagesService (confirmar borrado)
  │     └── abre NgbModal → WebhookFormComponent
  │                      → WebhookSecretModalComponent
  │                      → WebhookDeliveriesComponent
  │
  ├── WebhookFormComponent (extends FormComponent)
  │     ├── usa WebhooksService
  │     ├── usa ng-select (eventos dinámicos)
  │     └── usa MessagesService (toast éxito/error)
  │
  ├── WebhookSecretModalComponent
  │     ├── usa WebhooksService (rotateSecret)
  │     └── Clipboard API (navigator.clipboard)
  │
  └── WebhookDeliveriesComponent (extends BaseComponent)
        ├── usa WebhooksService (getDeliveries)
        └── usa ngx-highlightjs (payload JSON viewer)
```

---

## Resumen de Sprints

| Sprint | Duración | Entregable |
|--------|----------|-----------|
| Sprint 0 | 3 días | Estructura, interfaces, routing, servicio vacío |
| Sprint 1 | 2 días | Servicio funcional + pipe de salud + tests |
| Sprint 2 | 4 días | Lista + CRUD completo + validaciones |
| Sprint 3 | 4 días | Secret modal + historial de entregas |
| Sprint 4 | 3 días | HMAC reference + QA + pulido accesibilidad |
| **Total** | **~16 días hábiles** | **Módulo completo** |

---

> **Nota de arquitectura:**  
> Si en el futuro se requiere gestión de estado más compleja (webhooks en tiempo real vía WebSocket), la capa de servicios ya está desacoplada para incorporar `NgRx Effects` sin afectar los componentes.
