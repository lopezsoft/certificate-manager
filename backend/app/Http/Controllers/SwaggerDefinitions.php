<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="2.2.0",
 *     title="Certificate Manager API",
 *     description="API REST para la gestión de solicitudes de certificados digitales. v1=CAMERFIRMA (flujo por email), v2=Pagos + Cuotas + Analíticas IA. Requiere autenticación OAuth 2.0 con Laravel Passport.",
 *     @OA\Contact(
 *         email="soporte@matias.com.co",
 *         name="Soporte Matias"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1 — CAMERFIRMA (flujo por email)"
 * )
 *
 * @OA\Server(
 *     url="/api/v2",
 *     description="API v2 — WOMPI + Cuotas (flujo automatizado)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token de acceso OAuth 2.0 obtenido desde /authentication/login"
 * )
 *
 * @OA\Tag(name="Autenticación", description="Endpoints de login, registro, verificación de email y recuperación de contraseña")
 * @OA\Tag(name="Solicitudes de Certificado", description="Gestión completa de solicitudes de certificados digitales (v1 - CAMERFIRMA)")
 * @OA\Tag(name="Archivos", description="Carga y eliminación de archivos adjuntos")
 * @OA\Tag(name="Empresa", description="Configuración y perfil de la empresa")
 * @OA\Tag(name="Perfil", description="Gestión del perfil de usuario")
 * @OA\Tag(name="Consumo", description="Estadísticas y reportes de consumo")
 * @OA\Tag(name="CRUD Genérico", description="Operaciones CRUD dinámicas sobre tablas configuradas del sistema")
 * @OA\Tag(name="Tokens", description="Gestión de Personal Access Tokens (PAT) para integraciones externas")
 * @OA\Tag(name="Webhooks", description="Gestión de endpoints externos para notificaciones en tiempo real")
 * @OA\Tag(name="Notificaciones", description="Alertas de vencimiento de certificados: listado, marcado de lectura y disparo manual")
 * @OA\Tag(name="Datos Maestros", description="Datos de referencia públicos: países, departamentos, ciudades, tipos de documento y organización")
 * @OA\Tag(name="Configuración", description="Configuración de encabezados de reportes")
 * @OA\Tag(name="v2 - Órdenes", description="[v2] Compra de certificados PREPAID: crear orden y ejecutar pago WOMPI")
 * @OA\Tag(name="v2 - Cupos Admin", description="[v2] Gestión de cupos POSTPAID — solo administradores LOPEZSOFT")
 * @OA\Tag(name="v2 - Precios", description="[v2] Consulta pública de tarifas por volumen (sin autenticación)")
 * @OA\Tag(name="v2 - Pagos Externos", description="[v2] Webhooks entrantes de WOMPI (sin autenticación, firmados con HMAC-SHA256)")
 * @OA\Tag(name="v2 - Analíticas IA", description="[v2] Pipeline OCR + IA: resultados de análisis, estadísticas y estado de proveedores")
 * @OA\Tag(name="v2 - Sistema", description="[v2] Health check de servicios externos: WOMPI")
 *
 * ─── Schemas reutilizables ────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="ApiSuccessResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operación exitosa")
 * )
 *
 * @OA\Schema(
 *     schema="ApiErrorResponse",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Descripción del error")
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=5),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=75)
 * )
 *
 * @OA\Schema(
 *     schema="CertificateRequest",
 *     required={"city_id","identity_document_id","type_organization_id","document_number","address","legal_representative","company_name","dni","life"},
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="uuid", type="string", readOnly=true, example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="company_id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="city_id", type="integer", example=149),
 *     @OA\Property(property="identity_document_id", type="integer", example=1),
 *     @OA\Property(property="type_organization_id", type="integer", example=1),
 *     @OA\Property(property="document_number", type="string", maxLength=30, example="1234567890"),
 *     @OA\Property(property="address", type="string", maxLength=255, example="Calle 123 # 45-67"),
 *     @OA\Property(property="legal_representative", type="string", maxLength=120, example="JUAN PÉREZ GÓMEZ"),
 *     @OA\Property(property="company_name", type="string", maxLength=120, example="MI EMPRESA S.A.S."),
 *     @OA\Property(property="dni", type="string", maxLength=30, example="900455420"),
 *     @OA\Property(property="dv", type="integer", readOnly=true, example=8),
 *     @OA\Property(property="life", type="integer", example=1, description="Vigencia en años"),
 *     @OA\Property(property="info", type="string", nullable=true, example="Información adicional"),
 *     @OA\Property(property="request_status", type="string", readOnly=true, enum={"DRAFT","SENT","PENDING","ACCEPTED","PROCESSING","PROCESSED","REJECTED"}, example="DRAFT"),
 *     @OA\Property(property="expiration_date", type="string", format="date-time", nullable=true, readOnly=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="FileManager",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="certificate_request_id", type="integer", example=1),
 *     @OA\Property(property="file_name", type="string", example="RUT-empresa.pdf"),
 *     @OA\Property(property="file_path", type="string", example="companies/1/2024/01/9004554208/RUT-empresa.pdf"),
 *     @OA\Property(property="extension_file", type="string", example="pdf"),
 *     @OA\Property(property="mime_type", type="string", example="application/pdf"),
 *     @OA\Property(property="file_size", type="string", example="102400"),
 *     @OA\Property(property="document_type", type="string", enum={"ATTACHED","GENERATED"}, example="ATTACHED"),
 *     @OA\Property(property="status", type="string", example="COMPLETED")
 * )
 *
 * @OA\Schema(
 *     schema="PersonalAccessToken",
 *     @OA\Property(property="id", type="string", readOnly=true, example="9d471f3b-8c6e-4a2d-b9f0-1234567890ab", description="UUID del token"),
 *     @OA\Property(property="name", type="string", example="Token ERP Producción"),
 *     @OA\Property(property="scopes", type="array", @OA\Items(type="string"), example={"*"}),
 *     @OA\Property(property="revoked", type="boolean", example=false),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2026-05-19 10:00:00"),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true, example="2026-02-19 10:00:00")
 * )
 *
 * @OA\Schema(
 *     schema="WebhookEndpoint",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="company_id", type="integer", readOnly=true, example=7),
 *     @OA\Property(property="url", type="string", format="uri", example="https://mi-erp.com/webhook"),
 *     @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"certificate_request.created","certificate_request.status_changed"}),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="description", type="string", nullable=true, example="Notificaciones al ERP"),
 *     @OA\Property(property="failure_count", type="integer", readOnly=true, example=0),
 *     @OA\Property(property="last_triggered_at", type="string", format="date-time", nullable=true, readOnly=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="WebhookDelivery",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=42),
 *     @OA\Property(property="webhook_endpoint_id", type="integer", example=1),
 *     @OA\Property(property="event_type", type="string", example="certificate_request.status_changed"),
 *     @OA\Property(property="payload", type="object", description="Payload JSON enviado al endpoint"),
 *     @OA\Property(property="http_status", type="integer", nullable=true, example=200),
 *     @OA\Property(property="status", type="string", enum={"pending","delivered","failed"}, example="delivered"),
 *     @OA\Property(property="attempt", type="integer", example=1),
 *     @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="ExpiringCertificate",
 *     description="Certificado próximo a vencer (status PROCESSED)",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=12),
 *     @OA\Property(property="company_name", type="string", example="MI EMPRESA S.A.S."),
 *     @OA\Property(property="dni", type="string", example="900455420"),
 *     @OA\Property(property="dv", type="integer", example=8),
 *     @OA\Property(property="email", type="string", format="email", nullable=true, example="empresa@correo.com"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="3001234567"),
 *     @OA\Property(property="expiration_date", type="string", format="date-time", example="2026-03-15 00:00:00"),
 *     @OA\Property(property="expiration_date_formatted", type="string", example="15-03-2026 12:00:00 am"),
 *     @OA\Property(property="days_remaining", type="integer", example=24),
 *     @OA\Property(property="urgency_level", type="string", enum={"critical","high","medium","low"}, example="medium"),
 *     @OA\Property(property="city", type="string", nullable=true, example="Bogotá D.C."),
 *     @OA\Property(property="legal_representative", type="string", example="JUAN PÉREZ")
 * )
 *
 * @OA\Schema(
 *     schema="NotificationItem",
 *     description="Notificación persistida en base de datos (canal database de Laravel)",
 *     @OA\Property(property="id", type="string", format="uuid", readOnly=true, example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="type", type="string", readOnly=true, example="App\\Notifications\\CertificateExpiringNotification"),
 *     @OA\Property(property="data", type="object", description="Datos serializados de la notificación",
 *         @OA\Property(property="certificate_id", type="integer", example=12),
 *         @OA\Property(property="company_name", type="string", example="MI EMPRESA S.A.S."),
 *         @OA\Property(property="expiration_date", type="string", example="2026-03-15"),
 *         @OA\Property(property="days_remaining", type="integer", example=24),
 *         @OA\Property(property="urgency_level", type="string", example="medium")
 *     ),
 *     @OA\Property(property="read_at", type="string", format="date-time", nullable=true, example=null),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="CertificateOrder",
 *     description="Orden de compra de certificados PREPAID",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="quantity", type="integer", example=5),
 *     @OA\Property(property="vigencia", type="integer", description="Años: 1 o 2", example=1),
 *     @OA\Property(property="unit_price", type="number", format="float", description="COP sin IVA", example=125000.00),
 *     @OA\Property(property="subtotal", type="number", format="float", example=625000.00),
 *     @OA\Property(property="tax_amount", type="number", format="float", description="IVA 19%", example=118750.00),
 *     @OA\Property(property="total_amount", type="number", format="float", description="Total con IVA en COP", example=743750.00),
 *     @OA\Property(property="payment_provider", type="string", example="WOMPI"),
 *     @OA\Property(property="provider_reference", type="string", example="ORD-ABCD1234EFGH"),
 *     @OA\Property(property="status", type="string", enum={"PENDING","PAID","FAILED","REFUNDED"}, example="PENDING"),
 *     @OA\Property(property="currency", type="string", example="COP")
 * )
 *
 * @OA\Schema(
 *     schema="CertificateQuota",
 *     description="Cupo POSTPAID asignado a una empresa por el admin LOPEZSOFT",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="company_id", type="integer", example=10),
 *     @OA\Property(property="allocated_quantity", type="integer", example=50),
 *     @OA\Property(property="used_quantity", type="integer", readOnly=true, example=12),
 *     @OA\Property(property="remaining", type="integer", readOnly=true, example=38),
 *     @OA\Property(property="period_start", type="string", format="date", example="2026-05-01"),
 *     @OA\Property(property="period_end", type="string", format="date", example="2026-05-31"),
 *     @OA\Property(property="status", type="string", enum={"ACTIVE","EXHAUSTED","EXPIRED"}, example="ACTIVE"),
 *     @OA\Property(property="billing_type", type="string", enum={"POSTPAID"}, example="POSTPAID"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Cupo mensual mayo 2026")
 * )
 *
 * @OA\Schema(
 *     schema="PricingResult",
 *     description="Resultado de cotización de certificados",
 *     @OA\Property(property="quantity", type="integer", example=5),
 *     @OA\Property(property="vigencia", type="integer", example=1),
 *     @OA\Property(property="tier", type="string", enum={"RANGO_1","RANGO_2","RANGO_3"}, example="RANGO_2"),
 *     @OA\Property(property="unit_price", type="number", format="float", description="Precio unitario COP sin IVA", example=125000.00),
 *     @OA\Property(property="subtotal", type="number", format="float", example=625000.00),
 *     @OA\Property(property="tax_amount", type="number", format="float", description="IVA 19%", example=118750.00),
 *     @OA\Property(property="total_amount", type="number", format="float", description="Total con IVA", example=743750.00),
 *     @OA\Property(property="currency", type="string", example="COP")
 * )
 *
 * @OA\Schema(
 *     schema="DocumentAnalysisResult",
 *     description="Resultado de análisis IA de un documento (OCR + IA)",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="certificate_request_id", type="integer", example=42),
 *     @OA\Property(property="file_manager_id", type="integer", nullable=true, example=15),
 *     @OA\Property(property="provider", type="string", description="Proveedor IA", example="GEMINI"),
 *     @OA\Property(property="analysis_type", type="string", enum={"general","rut","cedula","chamber_commerce"}, example="rut"),
 *     @OA\Property(property="ocr_text", type="string", nullable=true, description="Texto extraído por OCR"),
 *     @OA\Property(property="ocr_provider", type="string", nullable=true, description="Proveedor OCR", example="GOOGLE_VISION"),
 *     @OA\Property(property="ocr_confidence", type="number", format="float", nullable=true, example=0.87),
 *     @OA\Property(property="ai_response", type="object", nullable=true, description="Respuesta estructurada del análisis IA"),
 *     @OA\Property(property="ai_confidence", type="number", format="float", nullable=true, example=0.92),
 *     @OA\Property(property="completeness_score", type="number", format="float", nullable=true, example=0.85),
 *     @OA\Property(property="extracted_data", type="object", nullable=true, description="Datos clave extraídos del documento"),
 *     @OA\Property(property="validation_result", type="object", nullable=true, description="Resultado de validación"),
 *     @OA\Property(property="processing_time", type="number", format="float", nullable=true, description="Tiempo en segundos", example=2.345),
 *     @OA\Property(property="status", type="string", enum={"PENDING","PROCESSING","COMPLETED","FAILED"}, example="COMPLETED"),
 *     @OA\Property(property="error_message", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="AnalyticsStats",
 *     description="Estadísticas agregadas del módulo de análisis IA",
 *     @OA\Property(property="total", type="integer", example=150),
 *     @OA\Property(property="completed", type="integer", example=142),
 *     @OA\Property(property="failed", type="integer", example=8),
 *     @OA\Property(property="avg_confidence", type="number", format="float", example=0.89),
 *     @OA\Property(property="avg_processing_time", type="number", format="float", description="Promedio en segundos", example=1.876),
 *     @OA\Property(property="avg_completeness", type="number", format="float", example=0.82),
 *     @OA\Property(property="by_type", type="object", description="Conteo por tipo de análisis",
 *         @OA\Property(property="general", type="integer", example=50),
 *         @OA\Property(property="rut", type="integer", example=40),
 *         @OA\Property(property="cedula", type="integer", example=35),
 *         @OA\Property(property="chamber_commerce", type="integer", example=25)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ProviderStatus",
 *     description="Estado de los proveedores IA/OCR activos",
 *     @OA\Property(property="ocr", type="object",
 *         @OA\Property(property="provider", type="string", example="GOOGLE_VISION"),
 *         @OA\Property(property="available", type="boolean", example=true)
 *     ),
 *     @OA\Property(property="ai", type="object",
 *         @OA\Property(property="provider", type="string", example="GEMINI"),
 *         @OA\Property(property="available", type="boolean", example=true)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="HealthStatus",
 *     description="Estado de salud de los servicios externos",
 *     @OA\Property(property="status", type="string", enum={"healthy","degraded"}, example="healthy"),
 *     @OA\Property(property="services", type="object",
 *         @OA\Property(property="wompi", type="object",
 *             @OA\Property(property="status", type="string", enum={"ok","error","warning"}, example="ok"),
 *             @OA\Property(property="message", type="string", example="WOMPI disponible (LOPEZSOFT)")
 *         )
 *     ),
 *     @OA\Property(property="checked_at", type="string", format="date-time", example="2026-04-21T15:30:00Z")
 * )
 */
class SwaggerDefinitions
{
    // Este archivo solo contiene anotaciones OpenAPI.
    // No tiene lógica de aplicación.
}
