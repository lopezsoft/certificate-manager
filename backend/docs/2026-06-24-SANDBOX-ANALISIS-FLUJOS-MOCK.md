# Sandbox: Análisis del Sistema de Dos Flujos y Estrategia de Mock

**Fecha:** 2026-06-24
**Autor:** Equipo de Desarrollo — Certificate Manager
**Versión:** 1.0

---

## 1. Contexto del Sistema

El sistema de emisión de certificados es **agnóstico al proveedor**. Su arquitectura
sigue el patrón **Strategy + Factory**, donde un único endpoint HTTP (`POST /certificate-request/{id}/issue`)
delega en el proveedor concreto que corresponda a cada solicitud.

Actualmente existen **dos flujos de emisión** registrados en `config/certificate.php`:

```php
'providers' => [
    'mail'     => MailIssuanceProvider::class,    // Flujo legacy
    'viafirma' => ViafirmaIssuanceProvider::class, // Flujo PKCS#10
],
```

---

## 2. Flujo 1 — `mail` (Legacy)

### ¿Qué hace?
Envía la solicitud de certificado como un correo electrónico a la Autoridad Certificadora.
El proceso de firma es **100% manual** del lado de la CA. No hay API externa ni estado
de polling. El sistema simplemente registra que la solicitud fue enviada.

### Ciclo de vida
```
Frontend envía solicitud
        │
        ▼
POST /certificate-request/{id}/issue
        │
        ▼
CertificateIssuanceOrchestrator
        │
        ▼
MailIssuanceProvider::issue()
        │
        ├── CertificateRequestMailService::sendMail()
        │       └── Envía email con adjuntos a la CA
        │
        └── ChangeHistory: estado → SENT
                (estado final; no hay polling)
```

### Precondiciones
- La solicitud debe tener archivos adjuntos (`document_type = 'ATTACHED'`).
- No requiere conexión a ningún API externo.

### Variables de entorno relevantes
```env
CERTIFICATE_ISSUANCE_PROVIDER=mail
RECEIPT_EMAIL=soporte@matias.com.co
SEND_MAIL_TO_SUPPORT=false
```

### Implicaciones para Sandbox
✅ **No requiere ningún Mock.** No hay servicio externo que simular.
El flujo mail funciona completamente en local siempre que el driver de correo
esté configurado (ej. `MAIL_MAILER=log` o `MAIL_MAILER=array`).

---

## 3. Flujo 2 — `viafirma` (PKCS#10 Asíncrono)

### ¿Qué hace?
Emite un certificado digital X.509 mediante el API de Viafirma RA Colombia usando
el estándar PKCS#10. El proceso es **100% automatizado y asíncrono**:
se genera un CSR local, se envía a Viafirma, y Jobs en cola hacen polling hasta
obtener el certificado emitido (P7B) para ensamblar el P12 final.

### Ciclo de vida completo
```
POST /certificate-request/{id}/issue
        │
        ▼
ViafirmaIssuanceProvider::issue()
        │
        ├── 1. Resolución de perfil (FE-PJ / FE-PN)
        ├── 2. Generación de par RSA-2048 local
        ├── 3. Construcción del CSR (FePjCsrBuilder / FePnCsrBuilder)
        ├── 4. KeyVault: cifrado y persistencia de llave privada
        ├── 5. POST /request/fromCSR → Viafirma API
        │       └── Responde: { codRequest, publicId }
        ├── 6. DB: crea viafirma_certificate_requests + state (SUBMITTED)
        └── 7. Despacha PollViafirmaStatusJob →
                    │
                    ▼ (cada ~60s, indefinido — sin expiración automática por
                    │  tiempo/intentos desde el fix 2026-08-19; auto-repara
                    │  ante mutex ocupado o fallos vía hook `failed()`)
              GET /request/{codRequest}/status
                    │
                    ├── PROGRESSING (rues_check, inProcess, ...) → repoll
                    ├── ACCREDITATION* (bruto o sub-estados accreditation_check/
                    │   completed/verified) → ViafirmaAccreditationReached
                    │       └── FetchKycAccreditationLinkJob (delay 5s)
                    │             ├── Persiste kyc_accreditation_link
                    │             └── Email automático a companies.email de la
                    │                 empresa dueña de la solicitud (no al
                    │                 suscriptor final, que ya lo recibe de Viafirma)
                    ├── GENERATED_NOT_DOWNLOADED → DownloadP7bJob
                    │       └── downloadP7b(publicId) → guarda .p7b en Storage
                    │       └── Despacha AssembleP12Job →
                    │               ├── Extrae llave del KeyVault
                    │               ├── CryptoService::assembleP12()
                    │               ├── Guarda .p12 + .zip en Storage
                    │               ├── Guarda PIN cifrado en KeyVault
                    │               └── Estado → ASSEMBLED
                    └── FAIL → Estado → FAILED
```

### Jobs programados relacionados (Kernel)
| Job | Frecuencia | Función |
|-----|-----------|---------|
| `ReviveStalledViafirmaPollsJob` | Cada 5 min | Revive polls huérfanos |
| `AutoRedownloadPendingViafirmaJob` | Cada 5 min | Reintenta FAILED_RECOVERABLE |
| `PurgeExpiredKeysJob` | 02:00 AM | Purga llaves expiradas del vault |
| `AutoRevokeUnpaidCertificatesJob` | 03:00 AM | Revocación comercial automática |
| `MarkExpiredCertificatesJob` | 04:00 AM | Marca expiración técnica |

### Variables de entorno relevantes
```env
CERTIFICATE_ISSUANCE_PROVIDER=viafirma
VIAFIRMA_RA_URL=https://<host>/ra/api/v2
VIAFIRMA_RA_DOWNLOAD_URL=https://<host>/ra
VIAFIRMA_CLIENT_ID=<oauth1_key>
VIAFIRMA_CLIENT_SECRET=<oauth1_secret>
VIAFIRMA_RA_CODE=<ra_code>
```

---

## 4. Lógica de Selección del Proveedor

`CertificateIssuanceProviderFactory::resolveFor()` aplica esta cascada:

```
1. ¿Payload override? (callerIsAdmin + allow_payload_override=true)
        │ SI → usa el proveedor indicado en el request
        │ NO ↓
2. ¿La empresa tiene issuance_provider configurado?
        │ SI → usa ese proveedor (si supports() = true)
        │ NO ↓
3. ¿CERTIFICATE_ISSUANCE_PROVIDER en .env?
        │ → usa ese proveedor (si supports() = true)
        │ NO APLICA ↓
4. Fallback duro → 'mail'
```

---

## 5. Implementación del Sandbox (Mock)

### 5.1 Problema que resuelve

Para desarrollar y probar el flujo Viafirma en local es **imprescindible** no
llamar al API real porque:
- Viafirma no tiene un entorno de pruebas self-service; cada integración
  requiere un proceso manual por parte de su equipo.
- Las credenciales de producción no deben usarse en desarrollo.
- Las emisiones reales consumen cuotas y generan certificados reales en el sistema
  de la CA, lo que es contraproducente para pruebas.

### 5.2 Estrategia implementada

Se creó `MockViafirmaClient` que implementa la misma interfaz `ViafirmaClient`
que usa el flujo real, activado mediante una bandera de entorno.

**Archivo:** `app/Modules/Viafirma/Infrastructure/Http/MockViafirmaClient.php`

### 5.3 Activación

Agregar en `.env` (solo en entornos de desarrollo/testing):
```env
VIAFIRMA_SANDBOX_MODE=true
```

El `ViafirmaServiceProvider` resuelve condicionalmente:
```php
$this->app->bind(ViafirmaClient::class, function ($app) {
    if (config('viafirma.sandbox_mode', false)) {
        return $app->make(MockViafirmaClient::class); // Sandbox
    }
    return $app->make(GuzzleViafirmaClient::class);   // Producción
});
```

### 5.4 Comportamiento simulado por método

| Método | Comportamiento en Sandbox |
|--------|--------------------------|
| `getProfiles()` | Retorna 2 perfiles estáticos (FE-PJ y FE-PN) usando los `cod_profile` del config |
| `submitCsr()` | Genera `codRequest` y `publicId` aleatorios (`MOCK-REQ-*`, `MOCK-PUB-*`). Guarda contador de polls en Cache |
| `getStatus()` | Simula demora realista: Poll 1 → `rues_check`, Poll 2 → `accreditation`, Poll 3 → `inProcess`, Poll 4+ → `Generated_Not_Downloaded`. El paso `accreditation` (agregado 2026-08-19) permite probar en sandbox la captura automática del link KYC y el correo a la empresa. Requiere `CACHE_DRIVER` distinto de `array` para que el contador de polls persista entre requests HTTP — con `array` se salta directo a `Generated_Not_Downloaded` y el paso `accreditation` nunca se ejercita |
| `downloadP7b()` | Retorna un binario dummy en Base64 (no un P7B real) |
| `revokeCertificate()` | Retorna código de revocación ficticio con éxito inmediato |
| `getAccreditationLink()` | Retorna URL ficticia de KYC |

### 5.5 Limitación conocida: P7B dummy

> ⚠️ **El `downloadP7b()` del Mock retorna un binario inválido.**
>
> El Job `AssembleP12Job` intentará ensamblar un P12 real llamando a
> `CryptoService::assembleP12(p7bDer: <dummy>)`, lo cual fallará
> porque OpenSSL no puede parsear un P7B falso.
>
> **Impacto:** En modo Sandbox el flujo llega hasta el estado `READY_TO_DOWNLOAD`
> pero el ensamblado del P12 fallará con un error de OpenSSL.
>
> **Soluciones posibles (pendiente de implementar):**
> - Opción A: Generar un P7B sintético válido en `downloadP7b()` usando
>   `openssl_pkcs7_*` con el propio par de llaves generado en el CSR.
> - Opción B: Añadir una bandera `VIAFIRMA_SANDBOX_SKIP_P12_ASSEMBLY=true`
>   que haga que `AssembleP12Job` marque el estado como `ASSEMBLED` sin
>   ensamblar el P12 real.
> - Opción C *(recomendada)*: Crear un `MockAssembleP12Job` que
>   genere un P12 de prueba usando un certificado auto-firmado local.

### 5.6 Cobertura del Sandbox por flujo

| Flujo | Cobertura |
|-------|-----------|
| **mail** | ✅ 100% — No requiere Mock. Configura `MAIL_MAILER=log` |
| **viafirma: emisión CSR** | ✅ Cubierto por `MockViafirmaClient::submitCsr()` |
| **viafirma: polling** | ✅ Cubierto por `MockViafirmaClient::getStatus()` con demora simulada |
| **viafirma: descarga P7B** | ⚠️ Parcial — retorna dummy, falla en ensamblado |
| **viafirma: ensamblado P12** | ❌ No cubierto — falla con P7B inválido |
| **viafirma: revocación** | ✅ Cubierto por `MockViafirmaClient::revokeCertificate()` |
| **viafirma: KYC link** | ✅ Cubierto por `MockViafirmaClient::getAccreditationLink()` |
| **Jobs programados** | ✅ Funcionales — usan el mismo Mock al resolver `ViafirmaClient` |

---

## 6. Configuración Recomendada por Entorno

### Desarrollo local (`.env`)
```env
# Proveedor
CERTIFICATE_ISSUANCE_PROVIDER=viafirma

# Sandbox activo — no llama al API real
VIAFIRMA_SANDBOX_MODE=true

# Viafirma (valores dummy en sandbox, no se usan para HTTP)
VIAFIRMA_RA_URL=http://sandbox.local/ra/api/v2
VIAFIRMA_CLIENT_ID=mock-key
VIAFIRMA_CLIENT_SECRET=mock-secret
VIAFIRMA_RA_CODE=MOCK_RA

# Correo en modo log (no envía emails reales)
MAIL_MAILER=log

# Storage local
CERT_STORAGE_DISK=local
```

### Staging / QA
```env
CERTIFICATE_ISSUANCE_PROVIDER=viafirma
VIAFIRMA_SANDBOX_MODE=true   # hasta tener credenciales reales de staging
CERT_STORAGE_DISK=s3
```

### Producción
```env
CERTIFICATE_ISSUANCE_PROVIDER=viafirma
VIAFIRMA_SANDBOX_MODE=false  # NUNCA true en producción
CERT_STORAGE_DISK=s3
```

---

## 7. Próximos Pasos del Sandbox

| # | Tarea | Prioridad |
|---|-------|-----------|
| 1 | Implementar P7B sintético válido en `MockViafirmaClient::downloadP7b()` | Alta |
| 2 | Cubrir el ensamblado P12 en modo Sandbox (Opción B o C del §5.5) | Alta |
| 3 | Agregar pruebas de integración usando `VIAFIRMA_SANDBOX_MODE=true` | Media |
| 4 | Documentar el flujo de prueba manual end-to-end en Sandbox | Media |
