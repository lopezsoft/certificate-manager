# Roadmap: Limpieza Definitiva y Consolidación de API v1 Agnóstica

## 1. Objetivo y Contexto

Se nos ha solicitado realizar una limpieza estricta para eliminar cualquier rastro de rutas legacy, redirecciones `v2` y endpoints deprecados. La arquitectura subyacente (Strategy/Factory con `CertificateIssuanceOrchestrator`) ya está implementada y es **totalmente agnóstica**, soportando envío vía API (Viafirma) o Email, delegando la responsabilidad de forma dinámica.

El objetivo de este roadmap es que **únicamente queden expuestas las rutas agnósticas en `v1`**, sin arrastrar compatibilidad hacia atrás mediante alias obsoletos.

> **Breaking Changes (Eliminación de rutas legacy):** 
> - Se eliminará el endpoint `POST /api/v1/certificate-request/{id}/send-mail` (que actualmente funcionaba como alias deprecado). El frontend o clientes externos **DEBEN** usar el nuevo endpoint agnóstico `POST /api/v1/certificate-request/{id}/issue`.
> - Se eliminará por completo el grupo de rutas `/api/v2/*` y todas sus redirecciones 308/410.

## 2. Propuesta de Cambios

---

### Capa de Enrutamiento (API v1 Exclusiva)

- **`routes/api.php`**:
  - **Eliminar** la carga condicional del alias deprecado `/send-mail`.
  - **Eliminar** por completo el grupo de rutas `Route::group(['prefix' => 'v2'], ...)` que incluye el archivo `v2-deprecated.php` al final del archivo.
  - Mantener las rutas de emisión que ya están estructuradas de forma agnóstica:
    - `POST /api/v1/certificate-request/{id}/issue`
    - `GET /api/v1/certificate-request/{id}/issuance`
    - `GET /api/v1/certificate-request/{id}/issuance/download`
    - `GET /api/v1/certificate-request/{id}/issuance/download/file`

- **`routes/v2-deprecated.php`**:
  - **Eliminar** este archivo. Ya no soportaremos las redirecciones permanentes (308) hacia la versión `v1`.

---

### Capa de Controladores (Controladores Obsoletos)

- **`app/Http/Controllers/V2/HealthCheckController.php`**:
  - **Eliminar** el controlador legacy en el namespace `V2\`.

---

### Capa de Configuración

- **`config/certificate.php`**:
  - **Eliminar** la variable de configuración `expose_legacy_send_mail`. Dado que el alias ha sido borrado del enrutamiento, esta flag pierde su propósito.

---

## 3. Plan de Verificación

### Pruebas Automatizadas
- Ejecutar `php artisan route:list` para garantizar que **no existe ninguna ruta que contenga `/v2/`** y que la ruta `/send-mail` no esté expuesta.
- Correr tests (`php artisan test`) para asegurar que la compilación funciona y los tests unificados para Viafirma/Mail siguen pasando.

### Verificación Manual
- Solicitar a los consumidores y Frontend actualizar todos los llamados a usar de `/send-mail` a `/issue`.
- Regenerar documentación de OpenAPI/Swagger (`php artisan l5-swagger:generate`).
