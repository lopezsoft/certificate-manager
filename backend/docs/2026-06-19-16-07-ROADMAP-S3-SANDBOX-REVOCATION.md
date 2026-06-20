# Roadmap Arquitectónico: S3, Sandbox Interno y Revocaciones Automáticas

> **Fecha:** 2026-06-19 · **Revisado:** 2026-06-19
> **Ámbito:** `backend/` (Módulo Viafirma + ciclo de vida en `certificate_requests`)
> **Estado:** 📋 **Pendiente de Autorización** (rediseñado tras análisis técnico)

Este documento recopila las iteraciones de diseño para evolucionar el módulo de Viafirma, mejorando su
resiliencia (S3), facilitando el desarrollo (Sandbox Interno) y automatizando el modelo de negocio
comercial (Revocación Automática).

---

## 0. Premisa rectora y prerequisito (CRÍTICO)

**`certificate_requests` es la fuente de verdad del ciclo de vida y los vencimientos.** Es la capa
unificada y agnóstica de proveedor (hoy hay dos proveedores). Todo proceso de Viafirma debe mantenerla
actualizada (`request_status`, fecha de emisión, `expiration_date`, `pin`, `base_path`) y consultarla
como eje. El agregado `viafirma_certificate_requests` (1:1 vía `certificate_request_id`) es técnico y
complementario, **no** es donde vive el vencimiento comercial.

### 0.1 Modelo de vigencia (clave para todo lo demás)
- Viafirma **siempre emite a 2 años** (es el `validTo` del X.509; es su límite y ellos lo desactivan al
  cumplirse).
- La vigencia **comercial** la elige el cliente: **1 o 2 años**, guardada en `certificate_requests.life`.
- El vencimiento que gobierna negocio (notificaciones y revocación) es el **comercial**, almacenado en
  `certificate_requests.expiration_date`.
- **Renovación = extender la vigencia del mismo certificado** (no se emite uno nuevo): el cliente usa
  una opción de renovar → se genera una orden de pago → al pagarse se **extiende `expiration_date` a 2
  años y `life = 2`** en la misma fila.

### 0.2 Prerequisito bloqueante: persistir emisión y vencimiento comercial
Hoy `AssembleP12Job` solo escribe `request_status = PROCESSED`; **no puebla `expiration_date`,
`issued_at` ni `life`** en el flujo Viafirma (eso solo ocurría en el alta manual de P12). Efecto: los
certificados Viafirma son **invisibles** a `SendExpiringCertificatesNotificationsJob` y no hay fecha de
emisión sobre la cual calcular nada.

**Antes de cualquier automatización:** en `AssembleP12Job` (y `RedownloadCertificateUseCase`), tras
ensamblar el P12, parsear el X.509 (`openssl_x509_parse`, reutilizando el patrón de
`CertificateValidatorService::getExpirationDate()`) y persistir en `certificate_requests`:
- `issued_at = validFrom_time_t`
- `expiration_date = issued_at + life años` (vencimiento **comercial**, NO el `validTo` de 2 años cuando `life = 1`)
- opcional `cert_valid_to = validTo_time_t` (vencimiento real, para auditoría)

---

## 1. Almacenamiento en AWS S3 (Estructurado por Entornos)

Para evitar pérdida de certificados por daños en servidores físicos y garantizar alta disponibilidad,
se migrará el almacenamiento local a AWS S3.

### 1.1 Diseño de directorios (almacenamiento genérico, agnóstico de proveedor)
El almacenamiento es una **responsabilidad transversal** (SOLID): no pertenece a Viafirma. Hoy
conviven dos convenciones —el otro proveedor (legacy) usa el disco `attachment` + `base_path`, y
Viafirma usa el bloque `viafirma.storage`—. Nombrar todo `VIAFIRMA_*` daría a entender, erróneamente,
que **todos** los certificados son de Viafirma (cosa que no es cierta tras la migración ni cuando se
emite con ambos proveedores).

Por eso las variables de almacenamiento son **genéricas** y el **proveedor es un segmento de la ruta**:

- **Ruta:** `s3://{BUCKET}/{CERT_STORAGE_PREFIX}/certificates/{provider}/{artifact}/...`
- **Viafirma (prod):** `s3://mis-certificados/production/certificates/viafirma/p12/637_W4CZ1SDML.p12`
- **Otro proveedor (prod):** `s3://mis-certificados/production/certificates/{otro-proveedor}/...`
- **Dev "ana":** `s3://mis-certificados/dev-ana/certificates/viafirma/p12/637_W4CZ1SDML.p12`

`CERT_STORAGE_PREFIX` es un nombre libre (no `APP_ENV`) que cada entorno elige; así los locales no
colisionan entre sí y producción/staging quedan separados.

### 1.2 Configuración (no basta con el `.env`)
Además de las variables, hay que **declarar el disco `s3` en `config/filesystems.php`** e instalar
`league/flysystem-aws-s3-v3`. Con `config:cache`, la interpolación `${APP_ENV}` queda congelada al
cachear (cuidar el momento del cacheo).

```env
AWS_ACCESS_KEY_ID=tu-key
AWS_SECRET_ACCESS_KEY=tu-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=mis-certificados

# Almacenamiento de certificados (genérico, agnóstico de proveedor)
CERT_STORAGE_DISK=s3
CERT_STORAGE_PREFIX=production

# Sub-rutas por proveedor/artefacto (relativas a {CERT_STORAGE_PREFIX}/certificates)
CERT_VIAFIRMA_P12_PATH=viafirma/p12
CERT_VIAFIRMA_P7B_PATH=viafirma/p7b
```

> Las antiguas `VIAFIRMA_P12_DISK`/`VIAFIRMA_P12_PATH`/`VIAFIRMA_P7B_DISK`/`VIAFIRMA_P7B_PATH` se
> **deprecan** en favor de `CERT_STORAGE_DISK` + `CERT_STORAGE_PREFIX` + sub-rutas por proveedor. Un
> resolver central (p.ej. `CertificateStoragePathResolver`) compone
> `{prefix}/certificates/{provider}/{artifact}` y es consumido tanto por Viafirma como por el otro
> proveedor, evitando literales dispersos (SOLID: una sola responsabilidad de ruteo de almacenamiento).

### 1.3 Migración de datos existentes (obligatoria — exclusiva del otro proveedor)
**Aclaración importante:** Viafirma es el proveedor **nuevo**, por lo que **no tiene certificados
emitidos** que migrar. La migración aplica **exclusivamente a los certificados existentes del OTRO
proveedor** (los del flujo legacy, almacenados en el disco `attachment` + `base_path`). No existe ni se
contempla migración de archivos de Viafirma.

Criterio del comando de migración (solo otro proveedor):
- Certificados **vigentes** (`expiration_date > now()` y `request_status = PROCESSED`).
- **Del otro proveedor**: registros del flujo legacy, **sin** `viafirma_certificate_requests` asociado.

El comando copia los archivos del disco legacy (`attachment`) → S3 y reescribe `base_path` (y rutas de
archivo asociadas) en BD solo para ese subconjunto. Los certificados de Viafirma se escriben
directamente en S3 desde su emisión, sin paso de migración.

### 1.4 Entrega al usuario final: Base64
El endpoint de descarga lee el `.p12` del disco (S3 o local) y devuelve su contenido **codificado en
Base64**, para que el cliente pueda usarlo de forma **desatendida**. Funciona igual con cualquier disco
y mantiene el control de acceso/auditoría en el backend.

---

## 2. Implementación de Sandbox Interno (Mock) — ⏸️ APLAZADO

**Decisión:** esta sección se **aplaza**. Primero se diseña e implementa todo el sistema (S3, ciclo de
vida en `certificate_requests`, estados unificados y revocación automática) y se valida su
funcionamiento end-to-end; **luego** se construye el Sandbox/Mock sobre una base ya estable.

Notas de diseño a retomar cuando se aborde (no implementar ahora):
- `MockViafirmaClient` deberá implementar **los 6 métodos** de `ViafirmaClient`.
- `getStatus()` debe respetar el flujo (`Generated_Not_Downloaded` → `Generated_And_Downloaded`) para no
  saltarse descarga/ensamblado.
- `downloadP7b()` debe generar un certificado autofirmado **sobre el CSR recién enviado** (la clave
  pública debe corresponder a la privada local).
- Binding por flag dedicado **con guard anti-producción**.

---

## 3. Estados unificados y mapeo (certificate_requests ⇆ Viafirma)

`certificate_requests.request_status` (`CertificateRequestStatusEnum`) es la capa unificada; el FSM
`InternalState` es técnico de Viafirma. El mapeo debe ser **explícito y centralizado** (hoy está
disperso en literales dentro de `AssembleP12Job` y `RevokeCertificateUseCase`).

| InternalState (Viafirma) | request_status (unificado) |
|---|---|
| DRAFT, CSR_GENERATED | DRAFT / SENT |
| SUBMITTED, POLLING, READY_TO_DOWNLOAD, DOWNLOADED, ASSEMBLED, FAILED_RECOVERABLE | PROCESSING |
| **COMPLETED** (emitido y descargado OK) | **PROCESSED** |
| REVOKED | REVOKED |
| FAILED | REJECTED |
| EXPIRED | EXPIRED |

**Regla de negocio:** cuando el certificado se emite/descarga correctamente (transita
DOWNLOADED→ASSEMBLED→COMPLETED) el `request_status` debe quedar en **PROCESSED**, pues es ahí donde se
manejan los estados finales de cada emisión para ambos proveedores.

**Manejo de FAILED → REJECTED (recuperación):** cuando un certificado cae en `FAILED` (→ `REJECTED`),
el sistema debe determinar la vía de recuperación:
- **Reenviar actualizando los datos:** si el fallo es por datos corregibles de la misma solicitud, se
  permite reabrir/actualizar y reintentar la emisión (aprovechando la transición `REJECTED → DRAFT` ya
  existente).
- **Eliminar y crear una nueva solicitud:** si la solicitud no es recuperable, se descarta y se genera
  una solicitud nueva.

El criterio para escoger entre ambas vías (qué tipos de error son corregibles vs. irrecuperables) se
detallará en el documento de desarrollo.

Cambios necesarios:
- Añadir `REVOKED` y `EXPIRED` a `CertificateRequestStatusEnum` (con `description()` y como terminales).
- Corregir `allowedTransitions()`: hoy `PROCESSED => []` **prohíbe** la transición que la revocación
  necesita. Debe permitir `PROCESSED => [REVOKED, EXPIRED]`.
- Crear un mapper único (`InternalState::toRequestStatus()` o un `ViafirmaStatusMapper`) y reemplazar los
  literales dispersos.

---

## 4. Revocación Comercial Automática (Modelo de Negocio)

Aplica la política comercial: certificados comercialmente válidos por 1 año aunque la CA los emita por 2.
Gracias al modelo de renovación (§0.1), el criterio es **simple y seguro**: no requiere joins de pago.

### 4.1 Periodo de gracia configurable
```env
VIAFIRMA_REVOCATION_GRACE_DAYS=15
```
La revocación ocurre cuando el **vencimiento comercial** + gracia ya pasó.

### 4.2 Lógica de automatización (Cron Job)
1. `AutoRevokeUnpaidCertificatesJob` registrado en `Kernel.php` (diario, en horario **distinto** de
   `PurgeExpiredKeysJob` que corre 02:00).
2. **Criterio de selección sobre `certificate_requests`** (sin joins de pago):
   - `request_status = PROCESSED`
   - `life = 1` (los de 2 años / renovados quedan fuera por diseño; Viafirma los desactiva al límite)
   - `expiration_date + VIAFIRMA_REVOCATION_GRACE_DAYS < now()`
   - *(Al renovar, la fila pasa a `life = 2` con `expiration_date` extendida y sale automáticamente del
     conjunto.)*
3. **Escala:** el cron **despacha un job por certificado a la cola** (no revoca en serie), reutilizando
   reintentos/circuit-breaker. Índice recomendado sobre `(request_status, life, expiration_date)`.
4. **Acción:** invoca `RevokeCertificateUseCase` (motivo `5` Cese de Operaciones o `4` Sustitución),
   usando un **usuario "sistema"** para la auditoría y el `revokingCode` persistido. Deja
   `internal_state = REVOKED` y `request_status = REVOKED`.
5. **Aviso previo:** job de notificación N días antes del apagón comercial (evita revocación abrupta).

### 4.3 Expiración natural
Un paso adicional (mismo cron u otro) marca `EXPIRED` los `PROCESSED` cuyo `expiration_date` ya pasó y
que no apliquen a revocación, para que el vencimiento natural se refleje en la tabla unificada.

---

## Anexo — Puntos a confirmar antes de implementar
- Campo de `ViafirmaCertificateRequest` que aporta el código necesario para **solicitar** la revocación
  (distinto de `revocation_request_code`, que se setea *después* de revocar).
- Identificador del "usuario sistema" para `revokedByUserId` en la revocación automática.
- Endpoint actual de descarga del `.p12` (para adaptar la entrega en Base64 con S3).
