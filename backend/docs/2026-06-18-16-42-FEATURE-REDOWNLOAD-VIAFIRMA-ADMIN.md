# Feature: Re-descarga y Regeneración de P12 — Endpoint Admin Viafirma

> **Fecha de creación:** 2026-06-18 16:42 (hora local COT)
> **Autor:** Arquitectura / Backend
> **Estado:** 📋 **Pendiente de implementación**
> **Ámbito:** `backend/` (Laravel) — módulo `app/Modules/Viafirma/` + `app/Http/Controllers/Certificate/`
> **Tickets relacionados:** Viafirma RA — Flujo de re-descarga admin
> **Principios rectores:** SOLID (SRP, OCP), Clean Architecture, Security by Design

---

## 1. Contexto y Problema

### 1.1 Situación Actual

El flujo automático de Viafirma descarga el P7B y ensambla el P12 una sola vez, de forma asíncrona, cuando el estado remoto llega a `Generated_Not_Downloaded`. Una vez completado, el estado interno pasa a `COMPLETED`.

**Problema:** Si el archivo P12 se corrompe, se pierde del storage, o el PIN del vault es purgado antes de que el cliente lo descargue, **no existe un mecanismo para que el ADMIN regenere el certificado** sin intervención manual en la base de datos.

### 1.2 Requerimiento

Crear un endpoint exclusivo para ADMIN que:

1. Consulte el estado remoto en Viafirma (`GET /request/{codRequest}/status`)
2. Valide que el estado sea `Generated_Not_Downloaded` o `Generated_And_Downloaded`
3. Descargue nuevamente el P7B desde Viafirma (`GET /downloadCertificateServlet?req={publicId}`)
4. Regenere el P12 con un nuevo PIN
5. Actualice el registro en BD
6. Retorne el nuevo PIN al ADMIN

---

## 2. Objetivos

1. **Resiliencia operativa:** Permitir recuperación sin intervención manual en BD.
2. **Seguridad:** Solo ADMIN puede ejecutar esta operación.
3. **Validación remota:** Siempre consultar Viafirma antes de proceder (no confiar solo en el estado interno).
4. **Auditoría completa:** Registrar en `viafirma_status_history` y `change_histories`.
5. **Idempotencia:** Si el P12 ya existe, sobrescribirlo de forma segura.

---

## 3. Diseño Arquitectónico

### 3.1 Flujo de Ejecución

```
POST /api/v1/certificate-request/{id}/issuance/redownload
    │
    ├─ [Auth] Requiere auth:api
    ├─ [Authz] Requiere is_admin = true
    │
    ▼
CertificateIssuanceController::redownload()
    │
    ▼
RedownloadCertificateUseCase::handle(int $certificateRequestId, int $adminUserId)
    │
    ├─ 1. Buscar ViafirmaCertificateRequest por certificate_request_id
    │       └─ 404 si no existe
    │
    ├─ 2. Consultar estado remoto
    │       GET {VIAFIRMA_RA_URL}/request/{codRequest}/status
    │       └─ 502 si falla la consulta HTTP
    │
    ├─ 3. Validar estado remoto
    │       ¿code == "Generated_Not_Downloaded" || "Generated_And_Downloaded"?
    │       └─ 409 si el estado no permite re-descarga
    │
    ├─ 4. Descargar P7B
    │       GET {VIAFIRMA_RA_DOWNLOAD_URL}/downloadCertificateServlet?req={publicId}
    │       └─ 502 si falla la descarga
    │
    ├─ 5. Guardar P7B en storage (sobrescribir)
    │
    ├─ 6. Generar nuevo PIN CSPRNG (32 chars)
    │
    ├─ 7. Recuperar llave privada del KeyVault (key_vault_ref)
    │       └─ 422 si la llave fue purgada (key_vault_ref == 'PURGED')
    │
    ├─ 8. Ensamblar nuevo P12
    │       CryptoService::assembleP12(privateKeyPem, p7bBinary, pin)
    │
    ├─ 9. Guardar P12 en storage (sobrescribir)
    │
    ├─ 10. Destruir PIN anterior del vault (si existe y no es PURGED)
    │
    ├─ 11. Guardar nuevo PIN en KeyVault
    │
    ├─ 12. Actualizar ViafirmaCertificateRequest:
    │        - p12_storage_path (actualizar si cambió)
    │        - p12_password_ref (nueva referencia)
    │        - internal_state = ASSEMBLED
    │        - last_error_code = null
    │        - last_error_message = null
    │
    ├─ 13. Registrar en viafirma_status_history
    │
    ├─ 14. Registrar en change_histories
    │
    └─ 15. Retornar RedownloadResultDto { pin, download_url, expires_at }
```

### 3.2 Componentes Nuevos

| Componente | Tipo | Ubicación |
|---|---|---|
| `RedownloadCertificateUseCase` | UseCase | `app/Modules/Viafirma/Application/UseCases/` |
| `RedownloadResultDto` | DTO | `app/Modules/Viafirma/Application/DTOs/` |

### 3.3 Componentes Modificados

| Componente | Cambio |
|---|---|
| `CertificateIssuanceController` | Agregar método `redownload()` |
| `routes/api.php` | Agregar ruta `POST /{id}/issuance/redownload` |

---

## 4. Especificación del Endpoint

### 4.1 Request

```
POST /api/v1/certificate-request/{id}/issuance/redownload
Authorization: Bearer {token}
Content-Type: application/json
```

**Parámetros de ruta:**
| Parámetro | Tipo | Descripción |
|---|---|---|
| `id` | integer | ID de `certificate_requests.id` |

**Body:** No requerido.

### 4.2 Respuestas

#### 200 OK — Re-descarga exitosa
```json
{
  "success": true,
  "message": "Certificado re-descargado y P12 regenerado exitosamente.",
  "dataRecords": {
    "pin": "aB3xK9mNpQ7rT2vW",
    "download_url": "/api/v1/certificate-request/123/issuance/download/file",
    "expires_at": null,
    "viafirma_id": 45,
    "internal_state": "assembled",
    "remote_status": "Generated_And_Downloaded"
  }
}
```

#### 403 Forbidden — No es ADMIN
```json
{
  "success": false,
  "message": "No autorizado. Esta operación requiere permisos de administrador."
}
```

#### 404 Not Found — Solicitud o trámite no encontrado
```json
{
  "success": false,
  "message": "No se encontró un trámite Viafirma para la solicitud 123."
}
```

#### 409 Conflict — Estado remoto no permite re-descarga
```json
{
  "success": false,
  "message": "El estado remoto 'accreditation' no permite re-descarga. Solo se permite en estados: Generated_Not_Downloaded, Generated_And_Downloaded.",
  "remote_status": "accreditation"
}
```

#### 422 Unprocessable Entity — Llave privada purgada
```json
{
  "success": false,
  "message": "La llave privada de esta solicitud fue purgada y no puede regenerarse el P12. Se requiere una nueva emisión."
}
```

#### 502 Bad Gateway — Error al consultar Viafirma
```json
{
  "success": false,
  "message": "Error al consultar el estado remoto en Viafirma: Connection timeout."
}
```

---

## 5. Implementación

### 5.1 RedownloadResultDto

```php
// app/Modules/Viafirma/Application/DTOs/RedownloadResultDto.php

final class RedownloadResultDto
{
    public function __construct(
        public readonly string $pin,
        public readonly string $downloadUrl,
        public readonly int    $viafirmaId,
        public readonly string $internalState,
        public readonly string $remoteStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'pin'            => $this->pin,
            'download_url'   => $this->downloadUrl,
            'expires_at'     => null,
            'viafirma_id'    => $this->viafirmaId,
            'internal_state' => $this->internalState,
            'remote_status'  => $this->remoteStatus,
        ];
    }
}
```

### 5.2 RedownloadCertificateUseCase (esqueleto)

```php
// app/Modules/Viafirma/Application/UseCases/RedownloadCertificateUseCase.php

final class RedownloadCertificateUseCase
{
    public function __construct(
        private readonly ViafirmaClient $client,
        private readonly CryptoServiceContract $crypto,
        private readonly KeyVault $vault,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(int $certificateRequestId, int $adminUserId): RedownloadResultDto
    {
        // 1. Buscar entidad
        $entity = ViafirmaCertificateRequest::where('certificate_request_id', $certificateRequestId)
            ->firstOrFail();

        // 2. Consultar estado remoto
        $statusResult = $this->client->getStatus($entity->cod_request);

        // 3. Validar estado
        if (!$statusResult->status->isReadyToDownload() && !$statusResult->status->isTerminalOk()) {
            throw new ViafirmaException(
                "El estado remoto '{$statusResult->status->value}' no permite re-descarga. " .
                "Solo se permite en estados: Generated_Not_Downloaded, Generated_And_Downloaded.",
                409
            );
        }

        // 4. Validar que la llave privada no fue purgada
        if ($entity->key_vault_ref === 'PURGED' || empty($entity->key_vault_ref)) {
            throw new ViafirmaException(
                'La llave privada de esta solicitud fue purgada y no puede regenerarse el P12.',
                422
            );
        }

        // 5-11. Descargar + ensamblar (ver implementación completa)
        // ...

        return new RedownloadResultDto(
            pin:           $newPin,
            downloadUrl:   route('v1.certificate-request.issuance.download.file', ['id' => $certificateRequestId]),
            viafirmaId:    $entity->id,
            internalState: InternalState::ASSEMBLED->value,
            remoteStatus:  $statusResult->status->value,
        );
    }
}
```

### 5.3 Ruta a agregar en routes/api.php

```php
// Dentro del grupo certificate-request, junto a los otros endpoints de issuance:
Route::post('/{id}/issuance/redownload', 'redownload')
    ->name('v1.certificate-request.issuance.redownload');
```

---

## 6. Seguridad

| Aspecto | Implementación |
|---|---|
| **Autenticación** | `auth:api` middleware |
| **Autorización** | `callerIsAdmin()` — retorna 403 si no es admin |
| **Validación remota** | Siempre consulta Viafirma antes de proceder |
| **Protección de PIN** | PIN nunca se loguea (SafePemLogger redacta `pin` y `p12_password_ref`) |
| **Limpieza de secretos** | PIN anterior destruido del vault antes de guardar el nuevo |
| **Auditoría** | Registra en `viafirma_status_history` con `action: admin_redownload` |
| **Rate limiting** | Considerar agregar `throttle:viafirma-redownload` (5 req/min por admin) |

---

## 7. Consideraciones de Negocio

### 7.1 Cuándo usar este endpoint

✅ El archivo P12 se corrompió o se perdió del storage
✅ El PIN fue purgado antes de que el cliente lo descargara
✅ El cliente reporta que no pudo descargar el certificado
✅ Recuperación manual tras un fallo en `AssembleP12Job`

### 7.2 Cuándo NO usar este endpoint

❌ El estado remoto es `accreditation`, `inProcess`, etc. (el certificado aún no está generado)
❌ La llave privada fue purgada (requiere nueva emisión completa)
❌ El trámite está en estado `FAILED` o `EXPIRED` (requiere nueva emisión)

### 7.3 Impacto en el cliente

- El **PIN cambia** con cada re-descarga — el cliente debe usar el nuevo PIN
- El **archivo P12 es el mismo** (mismo certificado, misma llave privada)
- La **URL de descarga no cambia** — sigue siendo `GET /issuance/download/file`

---

## 8. Testing

### 8.1 Casos de prueba unitarios

```
RedownloadCertificateUseCaseTest:
  ✅ handle_success_when_status_is_generated_not_downloaded
  ✅ handle_success_when_status_is_generated_and_downloaded
  ✅ throws_409_when_remote_status_is_not_downloadable
  ✅ throws_422_when_key_vault_ref_is_purged
  ✅ throws_404_when_viafirma_request_not_found
  ✅ throws_502_when_viafirma_client_fails
  ✅ destroys_old_pin_ref_before_storing_new_one
  ✅ records_history_on_success
```

### 8.2 Casos de prueba de integración

```
RedownloadControllerTest:
  ✅ returns_403_when_caller_is_not_admin
  ✅ returns_200_with_pin_on_success
  ✅ returns_409_when_remote_status_invalid
  ✅ returns_404_when_certificate_request_not_found
```

---

## 9. Checklist de Implementación

- [ ] Crear `RedownloadResultDto`
- [ ] Crear `RedownloadCertificateUseCase`
- [ ] Registrar UseCase en `ViafirmaServiceProvider`
- [ ] Agregar método `redownload()` en `CertificateIssuanceController`
- [ ] Agregar ruta en `routes/api.php`
- [ ] Agregar throttle rate limiter en `RouteServiceProvider` (opcional)
- [ ] Escribir tests unitarios
- [ ] Escribir tests de integración
- [ ] Actualizar documentación OpenAPI (anotaciones `@OA`)

---

## 10. Commit Sugerido

```
feat(viafirma): endpoint admin para re-descarga y regeneración de P12

- POST /api/v1/certificate-request/{id}/issuance/redownload
- Valida estado remoto antes de proceder (Generated_Not_Downloaded | Generated_And_Downloaded)
- Regenera P12 con nuevo PIN CSPRNG
- Destruye PIN anterior del vault
- Registra auditoría en viafirma_status_history
- Solo accesible para usuarios con rol admin
```
