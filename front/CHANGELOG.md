# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [2.2.1] - 2026-09-03

### Cambiado

- **Bloque de error de emisión** (`document-view`, `request-in-process-view`): los remote_status de revisión manual RA que son parte normal del proceso (`collate_data`, `checking`, `docRequired`, `docUploaded`, `rues_error`) ya no se muestran como "Fallo recuperable" en rojo alarmante — cambian a un estilo `alert-warning` con título "En revisión manual" y un mensaje tranquilizador, para reducir tickets de soporte innecesarios generados por clientes que no entendían el mensaje técnico
- **`accreditationRemoteStatuses`**: se agregó `collate_data` — el bloque `kyc-callout` con el enlace de MetaMap vuelve a mostrarse en este estado, porque el operador RA suele requerir que el suscriptor repita la verificación de identidad cuando los datos no coinciden
- **`kyc-callout`**: nueva rama específica para `collate_data` ("Se requiere repetir la verificación de identidad"); los botones de acción (abrir enlace, copiar, WhatsApp) se muestran aunque ya exista un `kyc_flow_completed_at` previo

## [2.2.0] - 2026-08-28

### Añadido

- **Nueva ruta pública `/#/viafirma/verificacion-completada`**: página de confirmación de verificación KYC completada, sin autenticación, redirigida por el backend tras completar MetaMap
- **Campo `kyc_flow_completed_at`** en respuesta de `GET /certificate-request/{id}/issuance`: timestamp de cuándo el cliente completó la verificación de identidad en MetaMap
- **Módulo Viafirma (`ViafirmaModule`)**: nuevo módulo lazy-loaded registrado en `app-routing.module.ts`, contiene la ruta de confirmación KYC

### Cambiado

- **Bloque `kyc-callout`** ahora diferencia 3 estados del flujo de acreditación:
  1. **Pendiente** (sin `kyc_flow_completed_at`): "El cliente debe completar su acreditación KYC" — botones de acción visibles
  2. **Completada** (con `kyc_flow_completed_at`): "Completada el [fecha/hora]" — botones de acción ocultos
  3. **Rechazada** (`accreditation_rejected`): "La verificación no superó la validación..." — botones de acción visibles
- **Botones de acción KYC** (abrir, copiar, WhatsApp) se ocultan automáticamente cuando la verificación ya está completada
- Aplicado en ambos componentes: `document-view` y `request-in-process-view`

## [2.1.0] - 2026-08-26

### Añadido

- **Validaciones específicas de Viafirma** en `create-request` para Persona Jurídica y Persona Natural (nombre, apellidos, correo, N.I.T, dirección e identificación) según los perfiles oficiales del proveedor
- **Checkbox obligatorio de aceptación** de la Política de Servicios de Certificación de Viafirma, con enlace al documento PDS
- **Carga de 1 a 3 documentos de soporte** para Persona Jurídica cuando el tipo de documento constitutivo es "Sin RUES"
- **Campos de confirmación** (re-escritura) para N.I.T, número de documento y correo del representante legal, para detectar errores de tipeo antes de enviar la solicitud
- **Número de celular obligatorio** en el flujo Viafirma, con aviso de que debe tener WhatsApp
- **Tabla completa de `remote_status`** (`ViafirmaRemoteStatusDescription`): se agregaron los códigos `Cite_To_Finish`, `processingContract`, `collate_data`, `checking`, `docRequired` y `docUploaded`
- **Manejo diferenciado del estado `accreditation_rejected`**: el bloque de verificación de identidad (KYC) ahora distingue entre "pendiente" y "rechazada" (esta última requiere intervención de los operadores RA), en `document-view` y `request-in-process-view`

### Cambiado

- **`remoteStatusLabel`** ahora se muestra siempre, incluyendo durante la familia de estados de acreditación (antes se ocultaba)
- **Bloque de error de emisión**: usa `ViafirmaRemoteStatusDescription` como *fallback* cuando el backend no envía un `last_error_message` específico
- **HTTP 401 (Unauthorized)**: ahora siempre redirige a `/auth/not-authorized` para que el usuario pueda restablecer acceso
- Formulario de solicitud más compacto para Viafirma: se ocultó el teléfono fijo (redundante con el celular obligatorio), la información adicional y avisos redundantes; se acortó la nota del correo del representante legal

### Corregido

- **`create-request`**: al enviar la solicitud se sobrescribía el tipo de documento constitutivo a "Con RUES" aunque el usuario hubiera seleccionado "Sin RUES"
- **Re-descarga de certificado**: `document-view` permitía re-descargar en estados `FAILED`/`FAILED_RECOVERABLE`, que el backend ya no soporta (genera error); se alineó con `request-in-process-view`
- **`request-in-process-view`**: faltaba el guard que oculta la re-descarga cuando `request_status === PROCESSED`, y el manejo de errores era genérico en vez de mensajes específicos por código HTTP (403/404/409/422/502)
- **`document-view`**: faltaba `accreditation_rejected` en la familia de estados de acreditación, por lo que el bloque KYC nunca se mostraba en ese estado

## [2.0.0] - 2026-08-19

### Añadido

- **Rediseño de la tarjeta "Emisión del Certificado"** (`document-view`, `request-in-process-view`):
  - Stepper visual con los 6 pasos reales de la FSM de Viafirma (Enviado → Consultando → Listo para descargar → Descargado → Ensamblado → Completado)
  - Badge de estado con color semántico según `internal_state`
  - Chip de expiración del trámite con niveles de urgencia progresivos
  - Descripción legible del sub-estado remoto de Viafirma (`ViafirmaRemoteStatusDescription`): validación RUES, revisión de operador RA, firma en la CA, etc.
  - Aviso de verificación de identidad (KYC) con acciones para **copiar el enlace** y **enviarlo por WhatsApp** (`wa.me`, usando el celular/teléfono del solicitante cuando está disponible)

- **`request-in-process-view`**: se agregó la tarjeta de estado de emisión Viafirma y el modal de PIN de re-descarga — la vista de administrador tenía la lógica implementada pero nunca se había construido la interfaz

- **`ViafirmaStatus`** (interfaz): nuevos campos `kyc_accreditation_link` y `history_count`

### Cambiado

- **Copiado al portapapeles**: unificado en un helper `copyToClipboard`/`copyToClipboardFallback` reutilizado por el PIN de re-descarga y el enlace KYC (antes duplicado en cada componente)
- **Vocabulario de estados**: las etiquetas del stepper ahora se derivan de `ViafirmaInternalStateDescription`, eliminando el doble vocabulario que existía entre el stepper y el texto de estado

### Técnico

- **Angular**: 20.0.0
- **Archivos modificados**: `document-view.component.*`, `request-in-process-view.component.*`, `issuance.interface.ts`, `ViafirmaInternalState.ts`

## [1.9.0] - 2026-07-07

### Añadido

- **Validación mejorada de archivos ZIP** (`/documents`):
  - Validación mejorada y almacenamiento local con limpieza automática
  - Mejor manejo de errores durante descompresión y validación de archivos ZIP
  - Prevención de memory leaks con limpieza de recursos temporales

- **Unificación de rutas API v1**:
  - Parámetros mixtos en métodos de solicitud de certificados
  - Consistencia mejorada en endpoints de API (`/certificate-requests`)
  - Mejor tipado TypeScript para compatibilidad con múltiples tipos de parámetros

- **Opción CERTIFICATE en validación de document_type**:
  - Nuevas opciones de tipo de documento para solicitudes
  - Validación más flexible en formularios de solicitud

### Cambiado

- **Servicio de manejo de archivos**:
  - Filtrado de archivos adjuntos en consultas de notificación
  - Validación de existencia antes de adjuntar archivos en correos
  - Mejor gestión de rutas y nombres de archivo

- **API de cupos**:
  - Desglose de estado de cupos por vigencia en consulta de disponibilidad
  - Respuestas más granulares para análisis de cupos por año

### Corregido

- **Componente jqxEditor**: Corrección de capitalización en vista de solicitud en proceso
- **Memory leaks**: Prevención en manejo de archivos temporales durante validaciones

### Técnico

- **Angular**: 20.0.0
- **TypeScript**: 5.8.0
- **Archivos modificados**: `document-service.ts`, `order.service.ts`, `certificate-request.component.ts`, `request-in-process.component.ts`

## [1.8.0] - 2026-06-06

### Añadido

- **Módulo de Órdenes de Compra** (`/orders`):
  - Grid profesional con columnas: Referencia, Método de Pago, Cantidad, Vigencia, Subtotal, Impuesto, Total, Estado, Acciones
  - Badges de estado con colores semánticos (PENDING, PAID, FAILED, CANCELLED)
  - Columna de método de pago (`payment_method`) visible en la tabla
  - Botón **"Reintentar pago"** para órdenes en estado `PENDING`
  - Botón **"Cancelar / Eliminar"** para órdenes pendientes con confirmación SweetAlert2

- **Modal global de pago Wompi** (`WompiPaymentModalComponent`):
  - Componente reutilizable declarado en `CommonComponentsModule`
  - Invocable desde cualquier ubicación via `WompiPaymentService` (servicio + modal desacoplados)
  - Estados progresivos: `IDLE → LOADING → WIDGET_OPEN → POLLING → SUCCESS/FAILED`
  - Polling automático del estado de la orden cada 3s hasta confirmación o timeout
  - Muestra referencia de la orden, monto y proveedor de pago

- **Servicio `WompiPaymentService`**:
  - Apertura del widget Wompi con `acceptance_token` fresco
  - Método `retryPayment(uuid)` para reintentar desde cualquier componente
  - Método `cancelOrder(uuid)` con diálogo de confirmación integrado
  - Polling de estado con `takeWhile` + `takeUntil` para evitar memory leaks

- **Seguridad — UUID en órdenes**:
  - Las órdenes ahora se identifican públicamente por `uuid` (eliminado el `id` secuencial)
  - Todos los endpoints frontend actualizados: `GET /orders/{uuid}`, `POST /orders/{uuid}/pay`, `POST /orders/{uuid}/retry`, `DELETE /orders/{uuid}`
  - La interfaz `Order` y `OrderResponse` usan `uuid: string` como identificador primario

- **Navbar — Session Info Card**:
  - Nueva tarjeta corporativa en el **lado izquierdo del navbar** (visible ≥ lg)
  - Muestra: ícono empresa (gradiente azul) + badge de rol (`user_type_name`) + UUID completo copiable
  - UUID con tooltip via `appCustomTooltip`: _"Identificador único de la cuenta · Clic para copiar"_
  - Clic en UUID copia al portapapeles (Clipboard API con fallback `execCommand` para HTTP)
  - Nombre de empresa destacado junto al nombre de usuario (derecha) con ícono y color primario

- **Pipe `AvatarFallbackPipe`** (`avatar-fallback.pipe.ts`):
  - Retorna avatar de fallback profesional cuando el avatar del usuario está vacío o es inválido
  - Mapeo por inicial del nombre: A–M → `man.png`, N–Z → `woman.png`, desconocido → `unknown.png`
  - Handler `(error)` en el `<img>` como segunda línea de defensa para URLs rotas
  - Registrado en `NavbarModule` (declarado + exportado)

### Cambiado

- **`OrderService`**: todos los métodos (`getOrder`, `payOrder`, `retryOrder`, `cancelOrder`, `pollOrderStatus`) usan `uuid: string` en lugar de `id: number`
- **`PurchaseComponent`**: el paso 3 (confirmación) muestra la **Referencia** (`provider_reference`) en vez del ID secuencial
- **`OrderListComponent`**: acciones de reintentar/cancelar pasan `order.uuid` a los servicios
- **`NavbarModule`**: importa `CoreModule` para acceder a `CustomTooltipDirective`
- **Navbar HTML**: avatar del usuario usa el pipe `avatarFallback` + handler `(error)` de seguridad

### Corregido

- **`copyUuid`**: error `Cannot read properties of undefined (reading 'writeText')` en contextos HTTP — corregido con fallback a `document.execCommand('copy')`
- **Tipado TypeScript**: el objeto `userData` en el navbar se obtiene del token completo (`data.user`) en vez del objeto `User` mapeado localmente, evitando el error `Property 'user_type' does not exist on type 'User'`

### Técnico

- **Angular**: 18.2.10
- **Nuevos archivos**: `wompi-payment-modal/` (component + html + scss), `wompi-payment.service.ts`, `avatar-fallback.pipe.ts`
- **Archivos modificados**: `order.interface.ts`, `order.service.ts`, `order-list.*`, `purchase.*`, `navbar.*`, `navbar.module.ts`, `common-components.module.ts`

## [1.7.0] - 2026-02-19


### Añadido

- **Sistema de Personal Access Tokens (PAT)** (backend):
  - Endpoints REST: `GET /tokens`, `POST /tokens`, `GET /tokens/{id}`, `DELETE /tokens/{id}`, `POST /tokens/revoke-all`, `POST /tokens/{id}/renew`
  - Tokens de larga duración para integraciones externas (ERP, scripts, CLIs, webhooks)
  - Expiración configurable: 90 días por defecto, máximo 365 días
  - Rate limiting de creación: 10 tokens/día por usuario
  - Renovación atómica: crea nuevo token y revoca el anterior en una sola operación
  - El valor del token solo se expone al crear o renovar — nunca recuperable después
  - Guía de integración SPA en `docs/pat-integration.md`

- **Configuración de expiración Passport**:
  - `Passport::tokensExpireIn()` configurado globalmente (antes los tokens nunca expiraban)
  - Variables: `PAT_EXPIRATION_DAYS`, `PAT_MAX_EXPIRATION_DAYS`, `PAT_MAX_PER_DAY`, `PAT_MAX_ACTIVE`

- **Documentación Swagger actualizada**:
  - Tag `Tokens` con 6 endpoints documentados
  - Schema `PersonalAccessToken`
  - Versión API: 1.7.0

### Técnico

- **Nuevos archivos**: `config/tokens.php`, `app/Http/Controllers/Api/TokenController.php`, `app/Http/Requests/CreateTokenRequest.php`
- **Rate limiter**: `token-create` (10/día) en `RouteServiceProvider`
- **Sin cambios en DB**: usa las tablas `oauth_access_tokens` ya existentes de Passport

## [1.6.0] - 2026-02-19

### Añadido

- **Sistema de Webhooks Salientes** (backend):
  - Endpoints REST completos: `GET/POST /webhooks`, `GET/PUT/DELETE /webhooks/{id}`, `POST /webhooks/{id}/rotate-secret`, `GET /webhooks/{id}/deliveries`
  - 6 tipos de evento: `certificate_request.created`, `certificate_request.status_changed`, `certificate_request.ai_processed`, `certificate_request.file_uploaded`, `certificate_request.deleted`, `certificate.expiring`
  - Firma HMAC-SHA256 en formato Stripe (`t={timestamp},v1={hmac}`) para verificación de autenticidad
  - Cola dedicada (`webhooks`) con 3 reintentos y backoff exponencial (60s / 300s / 900s)
  - Auto-desactivación de endpoints tras 10 fallos consecutivos
  - Historial paginado de entregas con estado (delivered / failed / pending)
  - Comandos Artisan: `webhook:cleanup` y `webhook:retry`
  - Máximo de 5 webhooks por empresa
  - Tablas: `webhook_endpoints` y `webhook_deliveries`
  - Guía de integración SPA en `docs/webhooks-frontend.md`

- **Testing (backend)**:
  - 45 tests unitarios sin base de datos (PHPUnit)
  - Cobertura: `HttpResponseMessages`, `MessageExceptionResponse`, `VerificationDigit`, `ValidateFileMimeType`, `CertificateValidatorService`, `ZipExtractorService`

- **Documentación Swagger / OpenAPI completa**:
  - Todos los endpoints de la API documentados con anotaciones `@OA`
  - Tags organizados: Autenticación, Perfil, Datos Maestros, Configuración, Solicitudes de Certificado, Webhooks
  - Schemas: `ApiSuccessResponse`, `ApiErrorResponse`, `WebhookEndpoint`, `WebhookDelivery`, `PaginationMeta`

### Cambiado

- **Seguridad**:
  - Validación MIME real contra extensión declarada (previene spoofing)
  - Headers de seguridad HTTP añadidos (X-Frame-Options, X-Content-Type-Options, Strict-Transport-Security)
  - Rate limiting configurado para carga de archivos y envío de correos
  - Validación de certificados PKCS12 antes de procesamiento

- **Arquitectura backend**:
  - Desacoplamiento completo via eventos de dominio Laravel (dominio no conoce webhooks)
  - Patrón Repository para webhooks (`WebhookRepositoryContract`)
  - Patrón Builder para payloads de webhooks (`WebhookPayloadBuilderContract`)

### Técnico

- **PHP**: 8.1+
- **Laravel**: 10.x
- **Nuevas tablas DB**: `webhook_endpoints`, `webhook_deliveries`
- **Nuevas colas**: `webhooks`, `notifications`, `reports`
- **Variables .env nuevas**: `WEBHOOK_QUEUE`, `WEBHOOK_TIMEOUT`, `WEBHOOK_MAX_FAILURES`, `WEBHOOK_MAX_ENDPOINTS`, `WEBHOOK_LOG_RETENTION`

## [1.5.0] - 2025-11-05

### Añadido
- **Componente FileUpload Reutilizable**: Nuevo componente modular para carga de archivos
  - Drag & Drop funcional con indicadores visuales
  - Preview de archivos con miniaturas para imágenes
  - Indicador de espacio usado y disponible con barra de progreso
  - Soporte para múltiples archivos con límite configurable
  - Validación de formatos permitidos (PDF, JPG, PNG)
  - Animaciones suaves (fadeInOut) para mejor UX
  - Auto-ocultación al alcanzar límite de archivos
  - Interfaz `FileUploadConfig` para configuración completa

- **Validación Dinámica por Tipo de Persona**:
  - Persona Jurídica: Requiere exactamente 3 archivos
  - Persona Natural: Requiere exactamente 2 archivos
  - Detección automática según `type_organization_id`
  - Mensajes de validación contextuales y específicos
  - Texto de ayuda dinámico según tipo de persona
  - Límite de archivos adaptativo (2 o 3)

- **Gestión Inteligente de Archivos**:
  - Límite total de 10MB distribuible entre archivos
  - Contador en tiempo real de espacio usado/disponible
  - Reemplazo automático de archivos del mismo tipo
  - Validación de tamaño total excluyendo archivos a reemplazar
  - Indicadores de estado: ✓ Completo, ⚠ Advertencia, ✗ Error

### Cambiado
- **Interfaz de Carga de Archivos**:
  - Unificación de 3 componentes separados en 1 solo componente reutilizable
  - Diseño más limpio y moderno con Bootstrap 5
  - Mejor distribución del espacio en la interfaz
  - Drop zone con feedback visual mejorado

- **Sistema de Validación**:
  - Eliminadas validaciones por nombre de archivo (hasRut, hasCc, hastCamera)
  - Validación simplificada: solo cantidad exacta de archivos
  - Mensajes de error más claros y específicos
  - Botón "Guardar" habilitado solo con cantidad exacta de archivos

- **Experiencia de Usuario**:
  - Label dinámico: "Documentos Requeridos (2/3 archivos)"
  - Alert informativo cuando se alcanza el límite
  - Advertencia al cambiar tipo de organización con archivos cargados
  - Indicadores de color: 🟢 Verde (completo), 🟡 Amarillo (faltantes), 🔴 Rojo (excedidos)

### Corregido
- **Bug de Límite de Archivos**: Drop zone ahora se oculta correctamente al alcanzar maxFiles
- **Validaciones Falsas**: Eliminados errores de "Falta RUT/Cédula" basados en nombres de archivo
- **Cálculo de Tamaño**: Ahora excluye correctamente archivo a reemplazar del total
- **Mensajes de Error**: Textos actualizados para reflejar validación por cantidad, no por tipo

### Removido
- ❌ Validación AI/OCR (implementación inicial revertida)
- ❌ Servicios `DocumentValidatorService` y `OCRService`
- ❌ Tesseract.js y dependencias OCR
- ❌ Validación de contenido de documentos
- ❌ Detección automática de tipo de documento
- ❌ Validación de fecha de Cámara de Comercio (30 días)
- ❌ Variables de estado: `hasRut`, `hasCc`, `hastCamera`
- ❌ Método `updateFileFlags()`

### Optimizado
- **Arquitectura de Componentes**: Diseño modular y reutilizable
- **Change Detection**: Uso de `OnPush` para mejor rendimiento
- **Type Safety**: Interfaces TypeScript bien definidas
- **Gestión de Estado**: Uso de getters reactivos para UI dinámica

### Técnico
- **Angular**: 18.2.10
- **TypeScript**: 5.4.5
- **Bootstrap**: 5.3.3
- **Font Awesome**: 5.x
- **Endpoint Backend**: `POST /certificate-request` (multipart/form-data)
- **Límite de Archivos**: 10MB total configurable
- **Formatos Soportados**: PDF, JPG, JPEG, PNG

### Notas de Actualización Backend
Para soportar los nuevos límites, configurar en el backend:
```ini
# PHP
upload_max_filesize = 10M
post_max_size = 10M

# Laravel
'file0' => 'required|file|max:10240',
'file1' => 'required|file|max:10240',
'file2' => 'nullable|file|max:10240',
```

## [1.4.0] - 2025-11-05

### Añadido
- **Dashboard v1.4.0**: Nueva versión completa del dashboard con funcionalidades avanzadas
- **KPIs en tiempo real**: 3 tarjetas de indicadores clave (Total Certificados, Empresas Activas, En Proceso)
- **Gráfico de tendencias**: Visualización mensual de emisión de certificados (Enero-Diciembre) usando ApexCharts
- **Comparación temporal**: Comparativa año actual vs año anterior
- **Filtros de búsqueda locales**: 
  - Búsqueda por nombre de empresa
  - Filtro por estado (Procesado/En Proceso)
  - Filtro por vigencia (años)
- **Auto-refresh configurable**: Actualización automática cada 1, 5 o 10 minutos con contador en tiempo real
- **Exportación de datos**: Botones para exportar tablas en formato CSV, Excel y JSON
- **Columna de vigencia**: Agregada en ambas tablas (anual y mensual)

### Cambiado
- **Diseño de KPIs**: Simplificado sin avatares circulares, números resaltados con colores (primary, success, warning)
- **Orden de elementos**: Reorganizado según especificación de producción
- **Gráfico de meses**: Ahora muestra todos los meses (1-12) independientemente de si tienen datos o no
- **Tabla mensual**: Mantiene funcionalidad de filtro por mes seleccionado mientras el gráfico muestra todos los meses
- **Iconos de exportación**: Cambiados de Feather a Font Awesome para mejor visualización

### Corregido
- **Tipos de datos**: Alineados modelos TypeScript con respuestas reales del backend (total: number, nmonth: number)
- **Conversiones innecesarias**: Eliminadas todas las conversiones parseInt obsoletas
- **KPI Empresas Activas**: Corregido de `activeCompanies` a `totalCompanies`
- **Filtros locales**: Ahora funcionan completamente en frontend sin peticiones al backend
- **Contador de auto-refresh**: Corregido problema de actualización en tiempo real
- **Select de intervalo**: Binding corregido para mostrar valor por defecto (5 minutos)
- **Ordenamiento de meses**: Garantizado orden cronológico Enero-Diciembre en gráficos
- **Cálculo de totales**: Ahora respeta datos filtrados en tablas y KPIs

### Optimizado
- **Servicios modulares**: Código organizado en servicios especializados
  - `dashboard-metrics.service.ts` - Cálculo de KPIs y estadísticas
  - `chart-data-transformer.service.ts` - Transformación de datos para gráficos
  - `data-export.service.ts` - Exportación CSV/Excel/JSON
  - `chart-configuration.service.ts` - Configuración de ApexCharts
  - `dashboard-filter.service.ts` - Filtrado local de datos
  - `temporal-comparison.service.ts` - Comparaciones año vs año
  - `auto-refresh.service.ts` - Actualización automática con RxJS Observables
- **Gestión de memoria**: Uso de `takeUntil` y `destroy$` para prevenir memory leaks
- **Type safety**: Eliminados comentarios `@ts-ignore` con corrección de tipos apropiada

### Técnico
- **Angular**: 18.2.10
- **TypeScript**: 5.4.5
- **ApexCharts**: 4.0.0
- **RxJS**: 7.8.1 con programación reactiva
- **Bootstrap**: 5.3.3 para diseño responsivo

## [1.3.2] - Versiones anteriores

Ver historial de commits para versiones previas a 1.4.0.
