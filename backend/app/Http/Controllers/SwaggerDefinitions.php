<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.8.0",
 *     title="Certificate Manager API",
 *     description="API REST para la gestión de solicitudes de certificados digitales. Requiere autenticación OAuth 2.0 con Laravel Passport.",
 *     @OA\Contact(
 *         email="soporte@matias.com.co",
 *         name="Soporte Matias"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1"
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
 * @OA\Tag(name="Autenticación", description="Endpoints de login y registro")
 * @OA\Tag(name="Solicitudes de Certificado", description="Gestión completa de solicitudes de certificados digitales")
 * @OA\Tag(name="Archivos", description="Carga y eliminación de archivos adjuntos")
 * @OA\Tag(name="Empresa", description="Configuración y perfil de la empresa")
 * @OA\Tag(name="Perfil", description="Gestión del perfil de usuario")
 * @OA\Tag(name="Consumo", description="Estadísticas y reportes de consumo")
 * @OA\Tag(name="Tokens", description="Gestión de Personal Access Tokens (PAT) para integraciones externas")
 * @OA\Tag(name="Webhooks", description="Gestión de endpoints externos para notificaciones en tiempo real")
 * @OA\Tag(name="Notificaciones", description="Alertas de vencimiento de certificados: listado, marcado de lectura y disparo manual")
 * @OA\Tag(name="Datos Maestros", description="Datos de referencia públicos: países, departamentos, ciudades, tipos de documento y organización")
 * @OA\Tag(name="Configuración", description="Configuración de encabezados de reportes")
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
 */
class SwaggerDefinitions
{
    // Este archivo solo contiene anotaciones OpenAPI.
    // No tiene lógica de aplicación.
}
