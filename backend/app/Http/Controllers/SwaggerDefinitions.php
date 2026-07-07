<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.9.0",
 *     title="MatiCerts",
 *     description="API REST unificada (v1) para la gestión completa de solicitudes de certificados digitales.\n\n**Autenticación:** La API utiliza tokens OAuth 2.0 (Bearer Token). Puede obtener su token de sesión a través del endpoint `POST /api/v1/auth/login`. Para integraciones de sistema a sistema o desarrolladores externos, recomendamos utilizar Personal Access Tokens (PAT) generados desde el endpoint `/api/v1/tokens`.",
 *     @OA\Contact(
 *         email="soporte@matias.com.co",
 *         name="Soporte Matias"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1 — única versión soportada"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token de acceso OAuth 2.0 obtenido desde POST /api/v1/auth/login. Para integraciones machine-to-machine use Personal Access Tokens (PAT) desde /api/v1/tokens."
 * )
 *
 * @OA\Tag(name="Autenticación", description="Login, registro, verificación de email y recuperación de contraseña. Todos los endpoints que no son de solo lectura requieren el Bearer Token obtenido aquí.")
 * @OA\Tag(name="Datos Maestros", description="Datos de referencia públicos (sin autenticación): países, departamentos, ciudades, tipos de documento e identidad y tipos de organización DIAN.")
 * @OA\Tag(name="Solicitudes de Certificado", description="Gestión CRUD de solicitudes de certificados digitales: creación, listado, actualización de estado, búsqueda por DNI y eliminación lógica.")
 * @OA\Tag(name="Archivos", description="Carga (multipart/form-data) y eliminación de archivos adjuntos a una solicitud de certificado. Soporta PDF y ZIP.")
 * @OA\Tag(name="Emisión de Certificados", description="Emisión agnóstica del proveedor (mail / viafirma / futuros). Cada solicitud puede dispararse (`POST /issue`), consultarse (`GET /issuance`) y descargarse (`GET /issuance/download`). El proveedor activo se resuelve por cascada de configuración.")
 * @OA\Tag(name="Viafirma", description="Operaciones específicas del proveedor Viafirma RA: revocación de certificados emitidos y obtención del link KYC para el proceso de acreditación biométrica. Requiere que el trámite exista en `viafirma_certificate_requests`.")
 * @OA\Tag(name="Empresa", description="Configuración del perfil de la empresa: datos generales, proveedor de emisión por defecto y configuración de reportes.")
 * @OA\Tag(name="Perfil", description="Gestión del perfil del usuario autenticado: datos personales y tipos de usuario.")
 * @OA\Tag(name="Consumo", description="Estadísticas y reportes de consumo de certificados agrupados por año y mes.")
 * @OA\Tag(name="CRUD Genérico", description="Operaciones CRUD dinámicas sobre tablas de catálogo configuradas en el sistema.")
 * @OA\Tag(name="Tokens", description="Gestión de Personal Access Tokens (PAT) para integraciones externas (ERP, scripts automatizados). Los PAT no expiran por defecto salvo que se especifique.")
 * @OA\Tag(name="Webhooks", description="Gestión de endpoints externos para notificaciones push en tiempo real. Los eventos disponibles incluyen: certificate_request.created, status_changed, ai_processed, file_uploaded, deleted.")
 * @OA\Tag(name="Notificaciones", description="Alertas de vencimiento de certificados: listado, marcado de lectura individual/masivo y disparo manual de notificaciones (admin).")
 * @OA\Tag(name="Configuración", description="Configuración de encabezados de reportes PDF/Excel.")
 * @OA\Tag(name="Órdenes", description="Compra de certificados PREPAID: crear orden, ejecutar pago WOMPI, consultar estado y reintentar pagos fallidos.")
 * @OA\Tag(name="Cupos Admin", description="Gestión de cupos POSTPAID — exclusivo para administradores LOPEZSOFT. Permite asignar cupos de certificados a empresas con facturación diferida.")
 * @OA\Tag(name="Precios", description="Consulta pública (sin autenticación) de tarifas por volumen en COP (1 año / 2 años), incluyendo IVA 19%.")
 * @OA\Tag(name="Pagos Externos", description="Webhooks entrantes de WOMPI (sin autenticación). La autenticidad se verifica mediante firma HMAC-SHA256 con el `events_secret` configurado.")
 * @OA\Tag(name="Analíticas IA", description="Pipeline OCR + IA: resultados de análisis de documentos adjuntos (RUT, cédula, cámara de comercio), estadísticas agregadas y estado de proveedores activos.")
 * @OA\Tag(name="Sistema", description="Health check de servicios externos: verifica la conectividad con WOMPI y otros servicios críticos.")
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
 *     @OA\Property(property="entity_document_type_id", type="integer", example=1),
 *     @OA\Property(property="document_number", type="string", maxLength=30, example="1234567890"),
 *     @OA\Property(property="address", type="string", maxLength=255, example="Calle 123 # 45-67"),
 *     @OA\Property(property="legal_representative", type="string", maxLength=120, example="JUAN PÉREZ GÓMEZ", description="Usado en flujo legacy"),
 *     @OA\Property(property="legal_rep_first_name", type="string", maxLength=120, nullable=true, example="JUAN PABLO", description="Obligatorio para Viafirma"),
 *     @OA\Property(property="legal_rep_last_name", type="string", maxLength=120, nullable=true, example="PÉREZ GÓMEZ", description="Obligatorio para Viafirma"),
 *     @OA\Property(property="legal_rep_email", type="string", maxLength=250, nullable=true, example="juan.perez@empresa.com"),
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
 *     @OA\Property(property="pricing_tier_id", type="integer", nullable=true, example=2, description="FK a pricing_tiers — rango de precio asociado al cupo"),
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
 *
 * @OA\Schema(
 *     schema="IssueCertificateBody",
 *     description="Payload del endpoint unificado de emisión: POST /certificate-request/{id}/issue. Todos los campos son opcionales — el sistema resuelve el proveedor activo (mail/viafirma) por config + reglas de la empresa.",
 *     @OA\Property(property="provider", type="string", nullable=true, enum={"mail","viafirma"}, example="viafirma", description="Override del proveedor. Sólo se honra si el caller es admin y la config CERTIFICATE_ISSUANCE_ALLOW_OVERRIDE está activa."),
 *     @OA\Property(property="email_certificate", type="string", format="email", nullable=true, example="rep.legal@empresa.com", description="Email del representante legal. OBLIGATORIO para el proveedor 'viafirma'. Recibe notificaciones del proceso KYC."),
 *     @OA\Property(property="organization_type", type="string", nullable=true, enum={"RM","PROP","RUNEOL","RNT","ESAL","ESOL","JUEGOS","EXTRANJERAS"}, example="EXTRANJERAS", description="Tipo de organización DIAN. OBLIGATORIO para perfil FE-PJ (Persona Jurídica). NO debe enviarse para FE-PN (Persona Natural). Valores: RM=Registro Mercantil, PROP=Propietario, RUNEOL=RUNEOL, RNT=Registro Nacional de Turismo, ESAL=Entidad sin ánimo de lucro, ESOL=ESOL, JUEGOS=Juegos de azar, EXTRANJERAS=Empresa extranjera."),
 *     @OA\Property(property="identity_type_override", type="string", nullable=true, enum={"IDC","PAS"}, example=null, description="Override del tipo de documento de identidad. IDC=Cédula de ciudadanía, PAS=Pasaporte. Si null se deriva automáticamente del catálogo identity_documents."),
 *     @OA\Property(property="comments", type="string", nullable=true, maxLength=1000, example="Solicitud expedita por convenio empresarial"),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Metadatos libres adjuntos a la auditoría")
 * )
 *
 * @OA\Schema(
 *     schema="IssuanceResultData",
 *     description="Resultado normalizado de cualquier proveedor de emisión (mail, viafirma, ...). El campo 'data' contiene información extendida específica del proveedor.",
 *     @OA\Property(property="provider", type="string", enum={"mail","viafirma"}, example="viafirma"),
 *     @OA\Property(property="status", type="string",
 *         enum={"sent","submitted","processing","ready","completed","failed","unsupported"},
 *         example="submitted",
 *         description="sent=correo enviado (mail). submitted=solicitud enviada a Viafirma RA. processing=en proceso (polling activo). ready=listo para descargar. completed=certificado ensamblado. failed=error irrecuperable. unsupported=solicitud no tiene trámite."
 *     ),
 *     @OA\Property(property="message", type="string", example="Solicitud de certificado enviada al proveedor para emisión automática."),
 *     @OA\Property(property="external_id", type="string", nullable=true, example="D4AZEQQG6", description="Código de solicitud Viafirma RA (cod_request). Null para proveedor mail."),
 *     @OA\Property(property="resource_id", type="integer", nullable=true, example=5, description="ID interno del registro viafirma_certificate_requests. Null para proveedor mail."),
 *     @OA\Property(property="data", ref="#/components/schemas/IssuanceViafirmaData", nullable=true, description="Datos extendidos Viafirma. Null para proveedor mail.")
 * )
 *
 * @OA\Schema(
 *     schema="IssuanceViafirmaData",
 *     description="Datos extendidos del trámite Viafirma dentro de IssuanceResultData.data.",
 *     @OA\Property(property="public_id", type="string", nullable=true, example="fab8d88e0c5a4cab8bfc2b72be05a098", description="ID público Viafirma. Usado internamente para descargar el P7B."),
 *     @OA\Property(property="profile_type", type="string", nullable=true, enum={"FE-PJ","FE-PN"}, example="FE-PN", description="Perfil del certificado: FE-PJ=Persona Jurídica, FE-PN=Persona Natural."),
 *     @OA\Property(property="identity_type", type="string", nullable=true, enum={"IDC","PAS"}, example="IDC"),
 *     @OA\Property(property="internal_state", type="string", nullable=true,
 *         enum={"DRAFT","CSR_GENERATED","SUBMITTED","POLLING","READY_TO_DOWNLOAD","DOWNLOADED","ASSEMBLED","COMPLETED","FAILED","FAILED_RECOVERABLE","EXPIRED"},
 *         example="SUBMITTED",
 *         description="Estado interno del proceso en Certificate Manager. Progresa automáticamente vía jobs de cola."
 *     ),
 *     @OA\Property(property="remote_status", type="string", nullable=true, example="accreditation", description="Estado reportado por Viafirma RA. Valores posibles: accreditation, Generated_Not_Downloaded, error, etc."),
 *     @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true, example="2026-06-08T00:00:00Z"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2026-06-11T00:00:00Z", description="Expiración del trámite en Certificate Manager (72h por defecto). Pasada esta fecha el job de purga elimina las llaves."),
 *     @OA\Property(property="history_count", type="integer", nullable=true, example=3, description="Número de entradas en viafirma_status_history. Solo presente en GET /issuance.")
 * )
 *
 * @OA\Schema(
 *     schema="IssuanceResponse",
 *     description="Envoltura estándar de los endpoints de emisión.",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Solicitud de certificado enviada al proveedor para emisión automática."),
 *     @OA\Property(property="dataRecords", ref="#/components/schemas/IssuanceResultData")
 * )
 *
 * @OA\Schema(
 *     schema="IssuanceDownloadMetadata",
 *     description="Metadata de descarga del certificado P12. Disponible cuando internal_state es ASSEMBLED o COMPLETED. El PIN se almacena cifrado hasta que el job de purga lo elimine (72h por defecto).",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="p12_pin", type="string", example="X3kP9aQ1mZv7nR2sYwQe8BcZ4uVhKpTf", description="PIN de 32 caracteres para abrir el archivo .p12 en cualquier aplicación de certificados (ej. Certmanager, OpenSSL). Guárdelo en un lugar seguro — solo se muestra una vez por solicitud."),
 *     @OA\Property(property="p12_filename", type="string", example="D4AZEQQG6.p12"),
 *     @OA\Property(property="download_url", type="string", format="uri", nullable=true, example="https://s3.amazonaws.com/...", description="URL firmada con vigencia de 24 horas para descarga directa. Null cuando el disco de almacenamiento es local (sin soporte de URLs firmadas)."),
 *     @OA\Property(property="expires_at", type="string", format="date-time", example="2026-06-09T00:00:00Z", description="Expiración de la URL firmada (24h). Después debe solicitarse nuevamente.")
 * )
 *
 * @OA\Schema(
 *     schema="ViafirmaCertificateRequest",
 *     description="Agregado completo del trámite Viafirma. Retornado por GET /certificate-request/{id}/issuance cuando el proveedor activo es 'viafirma'.",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=5),
 *     @OA\Property(property="certificate_request_id", type="integer", example=636),
 *     @OA\Property(property="company_id", type="integer", example=1),
 *     @OA\Property(property="requested_by_user_id", type="integer", nullable=true, example=null, description="Null para emisiones automáticas del sistema (SYSTEM)."),
 *
 *     @OA\Property(property="cod_request", type="string", nullable=true, example="D4AZEQQG6", description="Código único asignado por Viafirma RA al crear la solicitud. Usar para soporte técnico."),
 *     @OA\Property(property="public_id", type="string", nullable=true, example="fab8d88e0c5a4cab8bfc2b72be05a098", description="ID público Viafirma. Usado para descargar el P7B una vez aprobado."),
 *     @OA\Property(property="ra_code", type="string", example="viafirmaco", description="Código de la RA (Registration Authority) configurada."),
 *
 *     @OA\Property(property="profile_type", type="string", enum={"FE-PJ","FE-PN"}, example="FE-PN", description="Perfil del certificado DIAN: FE-PJ=Factura Electrónica Persona Jurídica, FE-PN=Factura Electrónica Persona Natural."),
 *     @OA\Property(property="profile_type_label", type="string", example="Persona Natural", description="Descripción legible del perfil."),
 *     @OA\Property(property="identity_type", type="string", enum={"IDC","PAS"}, example="IDC", description="Tipo de documento: IDC=Cédula de ciudadanía, PAS=Pasaporte."),
 *     @OA\Property(property="country_code", type="string", example="CO"),
 *     @OA\Property(property="organization_type", type="string", nullable=true, enum={"RM","PROP","RUNEOL","RNT","ESAL","ESOL","JUEGOS","EXTRANJERAS"}, example=null, description="Tipo de organización. Solo presente para FE-PJ."),
 *     @OA\Property(property="validity_days", type="integer", example=730, description="Vigencia del certificado en días (configurada en VIAFIRMA_CERTIFICATE_VALIDITY_DAYS)."),
 *
 *     @OA\Property(property="internal_state", type="string",
 *         enum={"DRAFT","CSR_GENERATED","SUBMITTED","POLLING","READY_TO_DOWNLOAD","DOWNLOADED","ASSEMBLED","COMPLETED","FAILED","FAILED_RECOVERABLE","EXPIRED"},
 *         example="SUBMITTED",
 *         description="Máquina de estados interna. Flujo normal: SUBMITTED → POLLING → READY_TO_DOWNLOAD → DOWNLOADED → ASSEMBLED → COMPLETED."
 *     ),
 *     @OA\Property(property="remote_status", type="string", nullable=true, example="accreditation", description="Último estado reportado por Viafirma RA en el polling. 'Generated_Not_Downloaded' indica que el certificado está listo para descarga."),
 *     @OA\Property(property="is_terminal", type="boolean", example=false, description="True cuando internal_state es COMPLETED, FAILED o EXPIRED."),
 *     @OA\Property(property="is_failed", type="boolean", example=false, description="True cuando internal_state es FAILED, FAILED_RECOVERABLE o EXPIRED."),
 *     @OA\Property(property="has_expired", type="boolean", example=false, description="True cuando expires_at ha pasado."),
 *
 *     @OA\Property(property="csr_fingerprint", type="string", example="b5a403ff5dd3ce1a790565a8be9d8a90...", description="SHA-256 hex del CSR PKCS#10 enviado a Viafirma RA. La llave pública está embebida en el CSR."),
 *     @OA\Property(property="poll_attempts", type="integer", example=3, description="Número de intentos de polling realizados."),
 *     @OA\Property(property="next_poll_at", type="string", format="date-time", nullable=true, description="Próxima ejecución programada del polling (cada 60s)."),
 *     @OA\Property(property="last_polled_at", type="string", format="date-time", nullable=true),
 *
 *     @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true, description="Momento en que se envió el CSR a Viafirma RA."),
 *     @OA\Property(property="downloaded_at", type="string", format="date-time", nullable=true, description="Momento en que se descargó el P7B desde Viafirma RA."),
 *     @OA\Property(property="assembled_at", type="string", format="date-time", nullable=true, description="Momento en que se ensambló el P12 (llave privada + certificado P7B)."),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, description="Expiración del trámite en Certificate Manager. Pasada esta fecha se purgan las llaves criptográficas."),
 *
 *     @OA\Property(property="last_error_code", type="string", nullable=true, example="ASSEMBLE_FAILED"),
 *     @OA\Property(property="last_error_message", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="ViafirmaStatusHistoryItem",
 *     description="Entrada del historial interno de estados del trámite Viafirma (tabla viafirma_status_history). Registra cada transición de estado.",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="viafirma_certificate_request_id", type="integer", example=5),
 *     @OA\Property(property="previous_state", type="string",
 *         enum={"DRAFT","CSR_GENERATED","SUBMITTED","POLLING","READY_TO_DOWNLOAD","DOWNLOADED","ASSEMBLED","COMPLETED","FAILED","FAILED_RECOVERABLE","EXPIRED"},
 *         example="DRAFT"
 *     ),
 *     @OA\Property(property="new_state", type="string",
 *         enum={"DRAFT","CSR_GENERATED","SUBMITTED","POLLING","READY_TO_DOWNLOAD","DOWNLOADED","ASSEMBLED","COMPLETED","FAILED","FAILED_RECOVERABLE","EXPIRED"},
 *         example="SUBMITTED"
 *     ),
 *     @OA\Property(property="remote_status", type="string", nullable=true, example="accreditation"),
 *     @OA\Property(property="raw_response", type="object", nullable=true, description="Respuesta cruda de Viafirma RA o acción interna."),
 *     @OA\Property(property="attempt_number", type="integer", example=0),
 *     @OA\Property(property="occurred_at", type="string", format="date-time", example="2026-06-08T00:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="ChangeHistoryItem",
 *     description="Entrada del historial de cambios de una solicitud (tabla change_histories). Visible en el seguimiento del trámite.",
 *     @OA\Property(property="id", type="integer", readOnly=true, example=3358),
 *     @OA\Property(property="uuid", type="string", format="uuid", readOnly=true),
 *     @OA\Property(property="certificate_request_id", type="integer", example=636),
 *     @OA\Property(property="user_id", type="integer", nullable=true, example=null, description="Null para acciones automáticas del sistema."),
 *     @OA\Property(property="user_of_change", type="string",
 *         enum={"USER","MANAGER","SYSTEM","PROVIDER"},
 *         example="SYSTEM",
 *         description="Origen del cambio: USER=usuario final, MANAGER=gestor/admin, SYSTEM=job automático, PROVIDER=proveedor externo."
 *     ),
 *     @OA\Property(property="status", type="string",
 *         enum={"DRAFT","SENT","CANCELLED","REJECTED","ON_HOLD","DEFINITIVE","CLOSED","OPEN","DELETED","PENDING","ACCEPTED","PROCESSING","PROCESSED","UNKNOWN"},
 *         example="PROCESSING",
 *         description="Estado de la solicitud en el momento del cambio. PROCESSING=en proceso de emisión automática. PROCESSED=certificado generado y listo."
 *     ),
 *     @OA\Property(property="comments", type="string", example="Solicitud de certificado enviada al proveedor para emisión automática."),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true)
 * )
 *
 * @OA\Schema(
 *     schema="RevocationRequest",
 *     description="Payload para revocar un certificado Viafirma emitido.",
 *     required={"revoking_code","revocation_reason"},
 *     @OA\Property(property="revoking_code", type="string", example="MOCK-REV-CODE-A1B2C3D4", description="Código de revocación emitido por Viafirma RA. Obtenible con GET /certificate-request/{id}/kyc-link una vez el certificado está activo."),
 *     @OA\Property(
 *         property="revocation_reason",
 *         type="integer",
 *         enum={0,1,2,3,4,5,9,10},
 *         example=0,
 *         description="Motivo de revocación según RFC 5280: 0=unspecified, 1=keyCompromise, 2=cACompromise, 3=affiliationChanged, 4=superseded, 5=cessationOfOperation, 9=privilegeWithdrawn, 10=aACompromise."
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="QuotaStatus",
 *     description="Estado actual del cupo de certificados del usuario autenticado.",
 *     @OA\Property(property="billing_type", type="string", enum={"PREPAID","POSTPAID","NONE"}, example="POSTPAID", description="Tipo de facturación activo para la empresa."),
 *     @OA\Property(property="has_quota", type="boolean", example=true, description="True si la empresa tiene cupo disponible para emitir certificados."),
 *     @OA\Property(property="prepaid", type="object", nullable=true, description="Detalle de cupo PREPAID si aplica.",
 *         @OA\Property(property="available_certificates", type="integer", example=3, description="Certificados comprados y disponibles para usar."),
 *         @OA\Property(property="vigencia_1", type="integer", example=2, description="Certificados de 1 año disponibles."),
 *         @OA\Property(property="vigencia_2", type="integer", example=1, description="Certificados de 2 años disponibles.")
 *     ),
 *     @OA\Property(property="postpaid", type="object", nullable=true, description="Detalle de cupo POSTPAID si aplica.",
 *         @OA\Property(property="allocated_quantity", type="integer", example=50),
 *         @OA\Property(property="used_quantity", type="integer", example=12),
 *         @OA\Property(property="remaining", type="integer", example=38),
 *         @OA\Property(property="period_start", type="string", format="date", example="2026-06-01"),
 *         @OA\Property(property="period_end", type="string", format="date", example="2026-06-30"),
 *         @OA\Property(property="status", type="string", enum={"ACTIVE","EXHAUSTED","EXPIRED"}, example="ACTIVE")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="SandboxInfo",
 *     description="Información de modo Sandbox para desarrollo y pruebas.",
 *     @OA\Property(property="sandbox_active", type="boolean", example=true, description="True cuando VIAFIRMA_SANDBOX_MODE=true. Indica que el cliente Viafirma es un Mock y no genera certificados reales."),
 *     @OA\Property(property="mock_flow", type="string", example="getProfiles → submitCsr → RUES_CHECK (poll 1) → IN_PROCESS (poll 2) → GENERATED_NOT_DOWNLOADED (poll 3+) → downloadP7b → assemble P12", description="Flujo simulado por MockViafirmaClient."),
 *     @OA\Property(property="cache_warning", type="string", example="Requiere CACHE_DRIVER=file o redis para que el estado persista entre requests HTTP.", description="El driver 'array' hace que getStatus() siempre devuelva GENERATED inmediatamente.")
 * )
 */
class SwaggerDefinitions
{
    // Este archivo solo contiene anotaciones OpenAPI.
    // No tiene lógica de aplicación.
}
