# Inventario Actual de Funcionalidades — Certificate Manager (Backend)

**Fecha de Generación:** 2026-05-04  
**Proyecto:** Certificate Manager (Backend / Laravel)  
**Estado:** Actualizado a la última versión 2.x

A continuación se detalla el estado global del proyecto, listando todo lo que ha sido exitosamente implementado y probado, las funcionalidades que fueron descartadas o removidas, y el backlog técnico/funcional pendiente de realizar.

---

## ✅ Funcionalidades IMPLEMENTADAS y Activas

El proyecto cuenta con una arquitectura robusta y altamente funcional. Estas características ya están integradas, documentadas y con cobertura de pruebas automatizadas:

### 1. Núcleo de Gestión (Core)
* **Gestión de Solicitudes de Certificados:** CRUD completo, carga de archivos (con validación real de MIME types), generación dinámica de Excel y PDF.
* **Autenticación y Seguridad:** OAuth2 vía Laravel Passport.
* **Seguridad de API:** Rate limiting (throttling) estricto en rutas sensibles (envío de correos, subida de archivos), sanitización de inputs y validación a través de FormRequests.
* **Manejo Centralizado de Errores:** Excepciones custom y manejador global para unificar respuestas de error, sin exponer detalles internos.

### 2. Módulos Avanzados (v1.6 - v1.8)
* **Personal Access Tokens (PAT):** Generación, rotación, validación y revocación de tokens API para desarrolladores y sistemas externos.
* **Sistema Multi-Tenant de Webhooks:** Emisión de eventos salientes con firma de seguridad HMAC-SHA256, manejo de entregas (deliveries), y jobs asíncronos con reintentos (retry-logic). Incluye comandos de consola para mantenimiento (`WebhookCleanupCommand` y `WebhookRetryCommand`).
* **Motor de Notificaciones:** Envíos automatizados (In-App y Email) de alertas de vencimiento de certificados (diarias, semanales, mensuales) y reportes automatizados para administradores y empresas.
* **Integración con MATIAS APP:** Middleware (`CheckMembership`) para la validación en tiempo real del estado activo de la membresía de la empresa antes de procesar operaciones críticas.

### 3. Módulo de Pagos y Cupos (WOMPI / v2.0)
* **Integración WOMPI:** Gestión completa de pagos vía API de Wompi (`WompiPaymentService`), incluyendo validación criptográfica de Webhooks entrantes.
* **Órdenes y Precios:** Motor de cálculo de tarifas basado en volumen y vigencia (`PricingService`), y generación de órdenes de compra de certificados (`CertificateOrder`).
* **Sistema de Cupos (Quotas):** Asignación y consumo de cupos corporativos (Postpago) de certificados, manejado exclusivamente por el administrador.
* **Eventos de Transacción:** Notificaciones automatizadas In-App y vía Email cuando un pago es aprobado o rechazado en la pasarela.

### 4. Calidad y Arquitectura (Refactorizaciones Completadas)
* **Arquitectura de Software:** Uso intensivo de *Dependency Injection*, *Command Pattern* (para desacoplar lógica compleja como la creación de certificados) y *Service Classes*.
* **Documentación Técnica:** OpenAPI / Swagger v2.1.0 documentando el 100% de los endpoints (rutas v1 y v2).
* **Testing Automatizado:** Más de 220 pruebas (Unit y Feature) que se ejecutan sin tocar la base de datos de desarrollo (100% basados en mocks y fakes de Laravel), garantizando integridad de datos.

---

## 🚫 Funcionalidades DESCARTADAS o En Pausa (Latentes)

* **Inteligencia Artificial (OCR) y Procesamiento de Documentos:** Existen servicios creados (`OcrService`, `AiContentService`, `HandleCertificateAIProcessing`, `ProcessCertificateJob`) preparados para AWS Textract, Google Vision y Gemini, pero **están en pausa/incompletos**. Falta habilitar credenciales (Google Vision/Gemini) y guardar los resultados estructurados en un panel de analíticas (marcado como `TODO` en el código).

---

## ⏳ Funcionalidades PENDIENTES (Backlog / Roadmap)

El proyecto es totalmente operativo, pero existen las siguientes tareas de infraestructura, deuda técnica y mejoras arquitectónicas aún no realizadas:

### 1. Entorno de Desarrollo Local
* **Programación de Tareas Locales:** Asegurar que los desarrolladores estén emulando los crons localmente (`php artisan schedule:work`) para probar notificaciones de expiración, vencimiento de cupos y mantenimiento de webhooks (`WebhookCleanupCommand`, `WebhookRetryCommand`).

### 2. Deuda Técnica y Mantenimiento
* **Refactorización del `CompanyController`:** Migrar su lógica de métodos estáticos (anti-patrón) hacia una clase de servicio (`CompanyService`).
* **Logging:** Cambiar la política de logs a formato `daily` en producción para evitar archivos de log monolíticos muy pesados.
* **Limpieza General:** Resolver etiquetas `TODO` remanentes en el código, eliminar archivos temporales y copias de seguridad del repositorio.
* **Tipado Estricto:** Refactorizar la clase `HttpResponseMessages` usando Strict Types y Enums.

### 3. Mejoras Arquitectónicas Futuras
* **Consolidación del Repository Pattern:** Estandarizar el patrón de repositorio para extraer la lógica compleja de Eloquent fuera de los controladores y servicios.
* **DTOs Generales:** Extender el uso de Data Transfer Objects a las operaciones del módulo original (`v1`), como ya se hace en el módulo de pagos.
* **Configuración Estricta de CORS:** Afinar orígenes permitidos en la configuración de producción.
