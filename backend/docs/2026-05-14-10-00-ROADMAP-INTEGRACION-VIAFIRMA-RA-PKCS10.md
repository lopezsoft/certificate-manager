# 🛣️ Roadmap de Desarrollo — Integración Viafirma RA Colombia (PKCS#10)

**Proyecto:** CERTIFICATE MANAGER v3.0 — Plataforma de Gestión y Emisión de Certificados Digitales (integrado al ecosistema de Facturación Electrónica DIAN)
**Módulo:** Emisión automatizada de Certificados Digitales (Zero-Touch)
**Proveedor RA:** Viafirma Colombia — Endpoints **100% configurables por entorno** (ver `config/viafirma.php` y variables `VIAFIRMA_*` en §3.3). No se asume ningún dominio fijo en código.
**Documento generado:** 2026-05-14 10:00 (UTC-5) · **Última revisión:** 2026-05-15 (alineado a PDF Viafirma "Uso del API para perfiles PKCS#10 V1.1 — 15/05/2026")
**Autor:** Tech Lead / Arquitecto Backend
**Stack:** Laravel 10/11 · PHP 8.2+ · MySQL · Redis · Horizon · Docker · AWS

### 📒 Changelog del Roadmap

| Fecha       | Versión | Cambios principales                                                                                                 |
|-------------|:-------:|---------------------------------------------------------------------------------------------------------------------|
| 2026-05-14  | 1.0     | Versión inicial del roadmap (basada en PDF V1.0 de Viafirma + colección Postman).                                   |
| 2026-05-15  | 1.1     | **Alineación con PDF V1.1**: soporte de **dos perfiles** (FE-PJ y FE-PN) con payloads y CSRs distintos · sub-estados de `accreditation` (`accreditation_check`, `accreditation_completed`, `accreditation_verified`) · nuevo estado terminal `Generated_And_Downloaded` (re-descargable) · query param `codRa` en `/ra/available-profiles` · enum `identityType` (IDC/PAS) y `organizationType` (RM/PROP/RUNEOL/RNT/ESAL/ESOL/JUEGOS/EXTRANJERAS) · validez del cert = 730 días · doble endpoint base (`.com` sandbox local / `.do` documentado oficial). |
| 2026-05-15  | 1.2     | **Homologación con DB de producción**: nueva sección §3.0 que mapea catálogos reales (`identity_documents`, `type_organization`) a enums Viafirma · `viafirma_certificate_requests` deja de duplicar datos del solicitante y se enlaza por FK a `certificate_requests` + `companies` (fuente única de verdad) · nuevos campos requeridos en `certificate_requests` (representante legal estructurado) vía migración aditiva NO destructiva · seeder de homologación opcional para añadir `Pasaporte` a `identity_documents`. **Cero cambios destructivos sobre data productiva existente.** |
| 2026-05-15  | 1.3     | **🚀 Sprint 1 CERRADO**: 11 historias completadas + 4 entregables extra (`viafirma:migrate`, `openssl.cnf` empaquetado, excepciones tipadas, validación ISO-3166). 21 tests verdes · suite global 214/214 · cero dependencias nuevas instaladas · cero cambios en `.env`/`docker-compose.yml`. Ver §Sprint 1 con resultado por historia, ADRs implícitos y árbol de archivos producidos. |

---

## 1. 🎯 Objetivo Estratégico

Migrar el proceso **manual** de emisión de certificados de firma electrónica (descarga directa `.p12`) a un flujo **Zero-Touch PKCS#10** dentro de **CERTIFICATE MANAGER**, donde:

1. La **llave privada nunca sale** del servidor de Certificate Manager.
2. El cliente final sólo realiza la prueba KYC (acreditación biométrica).
3. El `.p12/.pfx` se ensambla **localmente** uniendo la llave privada con el `.p7b` firmado por Viafirma.
4. El certificado queda **listo para firmar XMLs DIAN** sin intervención humana.

---

## 2. 🏛️ Visión Arquitectónica (High-Level)

```
┌──────────────────────────────────────────────────────────────────────────┐
│                       CERTIFICATE MANAGER v3.0                            │
│                                                                           │
│  ┌─────────────┐   ┌──────────────────┐   ┌──────────────────────────┐  │
│  │ Controller  │──▶│ Application Svc  │──▶│  Domain (Use Cases)      │  │
│  │ (REST API)  │   │ CertificateIssue │   │  - GenerateCsrUseCase    │  │
│  └─────────────┘   └──────────────────┘   │  - SubmitRequestUseCase  │  │
│                                            │  - PollStatusUseCase     │  │
│  ┌──────────────────────────────────┐     │  - AssembleP12UseCase    │  │
│  │   Infrastructure Layer            │     └──────────────────────────┘  │
│  │   ┌─────────────────────────┐    │                                    │
│  │   │ ViafirmaRaClient (HTTP) │◀───┼──── OAuth1 HMAC-SHA1 Signer        │
│  │   │  (Guzzle/Saloon)        │    │                                    │
│  │   └─────────────────────────┘    │                                    │
│  │   ┌─────────────────────────┐    │                                    │
│  │   │ CryptoService           │◀───┼──── phpseclib3 + openssl           │
│  │   │  - generateKeyPair      │    │                                    │
│  │   │  - buildCsr (PKCS#10)   │    │                                    │
│  │   │  - assembleP12          │    │                                    │
│  │   └─────────────────────────┘    │                                    │
│  │   ┌─────────────────────────┐    │                                    │
│  │   │ KeyVault (Encrypted FS) │◀───┼──── AES-256-GCM + AWS KMS (prod)   │
│  │   └─────────────────────────┘    │                                    │
│  └──────────────────────────────────┘                                    │
│                                                                           │
│  ┌──────────────────────────────────┐                                    │
│  │  Async Workers (Horizon/Redis)    │                                    │
│  │  - PollViafirmaStatusJob (backoff)│                                    │
│  │  - AssembleP12Job                 │                                    │
│  │  - NotifyClientJob                │                                    │
│  └──────────────────────────────────┘                                    │
└──────────────────────────────────────────────────────────────────────────┘
                           ▲ HTTPS / OAuth1
                           ▼
                ┌──────────────────────┐
                │  Viafirma RA Sandbox │
                └──────────────────────┘
```

**Patrones aplicados:**
- **Hexagonal / Ports & Adapters** para aislar el cliente Viafirma (testeable con fakes).
- **Strategy** para el cálculo del intervalo de polling según estado actual.
- **Strategy** para construir el CSR/payload según el perfil (`FE-PJ` vs `FE-PN`).
- **State Machine** para las transiciones de `request_status` (evita estados inválidos).
- **Outbox / Saga ligera** para garantizar idempotencia entre llamadas remotas y persistencia local.
- **Repository Pattern** consistente con la convención existente en `app/Repositories`.

---

## 2.bis 📑 Perfiles Soportados por Viafirma (PKCS#10 v1.1)

El API expone **dos perfiles** para Factura Electrónica DIAN. Certificate Manager debe soportar **ambos** desde el día uno, aplicando el patrón **Strategy** para construir CSR y payload.

### 2.bis.1 Resumen comparativo

| Aspecto                     | **FE-PJ** (Persona Jurídica)                                          | **FE-PN** (Persona Natural)                              |
|-----------------------------|------------------------------------------------------------------------|----------------------------------------------------------|
| `codProfile` (ejemplo)      | `FE-PJ en formato PKCS10`                                              | `FE-PN en formato PKCS10`                                |
| `organizationType` en POST  | **Requerido** (`RM`,`PROP`,`RUNEOL`,`RNT`,`ESAL`,`ESOL`,`JUEGOS`,`EXTRANJERAS`) | **""** (vacío) o ausente                          |
| Atributos en el CSR         | **10**: C, ST, L, STREET, O, OU, SERIALNUMBER (NIT), E, GN, SN          | **9**: C, ST, L, STREET, SERIALNUMBER (cédula), E, GN, SN |
| `SERIALNUMBER` representa   | **NIT** de la empresa (ej. `900400300`)                                | **Cédula** de la persona (ej. `1002000400`)              |
| `rues_check`                | **Sí** aplica (validación de NIT)                                      | **No** aplica                                            |
| Validez del certificado     | 730 días (2 años)                                                      | 730 días (2 años)                                        |
| Token                       | `P7B`                                                                  | `P7B`                                                    |

### 2.bis.2 Payloads `POST /request/fromCSR`

**FE-PJ (Persona Jurídica):**
```json
{
  "identityType": "IDC",
  "countryCode": "CO",
  "identity": "1098765432",
  "ra": "viafirmaco",
  "codProfile": "VklBRklSTUEt...",
  "emailCertificate": "rep.legal@empresa.com",
  "organizationType": "EXTRANJERAS",
  "csr": "<CSR_BASE64>"
}
```

**FE-PN (Persona Natural):**
```json
{
  "identityType": "IDC",
  "countryCode": "CO",
  "identity": "1002000400",
  "ra": "viafirmaco",
  "codProfile": "VklBRklSTUEt...",
  "emailCertificate": "persona@correo.com",
  "csr": "<CSR_BASE64>"
}
```

### 2.bis.3 Atributos comunes (DTO)

| Atributo            | Tipo      | Enum / Formato                                                       | Notas                                                    |
|---------------------|-----------|----------------------------------------------------------------------|----------------------------------------------------------|
| `identityType`      | `string`  | `IDC` (cédula) · `PAS` (pasaporte)                                   | Tipo de documento del **solicitante** (KYC).             |
| `countryCode`       | `string`  | ISO 3166-1 alpha-2 (ej. `CO`)                                        | País emisor del documento.                               |
| `identity`          | `string`  | número (sin puntos)                                                  | Cédula/pasaporte del solicitante.                        |
| `ra`                | `string`  | `viafirmaco`                                                         | Código RA. Se obtiene de `available-profiles`.           |
| `codProfile`        | `string`  | base64 opaco                                                         | Del `GET /ra/available-profiles?codRa={ra}`.             |
| `emailCertificate`  | `string`  | email RFC 5322                                                       | Email para envío del link KYC; **puede diferir** del E del CSR. |
| `organizationType`  | `string?` | `RM` · `PROP` · `RUNEOL` · `RNT` · `ESAL` · `ESOL` · `JUEGOS` · `EXTRANJERAS` | **Solo PJ**. Vacío para PN.                       |
| `csr`               | `string`  | base64 del CSR PEM                                                   | Codificación: base64 estándar (no URL-safe).             |

### 2.bis.4 Modelado en código

```php
// app/Modules/Viafirma/Domain/Enums/CertificateProfile.php
enum CertificateProfile: string {
    case FE_PJ = 'FE-PJ';
    case FE_PN = 'FE-PN';

    public function requiresOrganizationType(): bool {
        return $this === self::FE_PJ;
    }
    public function csrAttributeCount(): int {
        return $this === self::FE_PJ ? 10 : 9;
    }
}

// app/Modules/Viafirma/Domain/Enums/OrganizationType.php
enum OrganizationType: string {
    case RM          = 'RM';
    case PROP        = 'PROP';
    case RUNEOL      = 'RUNEOL';
    case RNT         = 'RNT';
    case ESAL        = 'ESAL';
    case ESOL        = 'ESOL';
    case JUEGOS      = 'JUEGOS';
    case EXTRANJERAS = 'EXTRANJERAS';
}

// app/Modules/Viafirma/Domain/Enums/IdentityType.php
enum IdentityType: string {
    case IDC = 'IDC';   // cédula
    case PAS = 'PAS';   // pasaporte
}

// Strategy de construcción de CSR por perfil
interface CsrBuilderStrategy {
    public function build(CsrInputDto $input): CsrResult;   // {pem, base64, fingerprint}
}
final class FePjCsrBuilder implements CsrBuilderStrategy { /* 10 attrs */ }
final class FePnCsrBuilder implements CsrBuilderStrategy { /* 9 attrs */ }

final class CsrBuilderFactory {
    public function for(CertificateProfile $profile): CsrBuilderStrategy {
        return match ($profile) {
            CertificateProfile::FE_PJ => app(FePjCsrBuilder::class),
            CertificateProfile::FE_PN => app(FePnCsrBuilder::class),
        };
    }
}
```

### 2.bis.5 Atributos del CSR (Subject DN)

**FE-PJ — 10 atributos:**

| OID / Alias   | Campo CSR        | Ejemplo                                      |
|---------------|------------------|----------------------------------------------|
| `C`           | Country (ISO)    | `CO`                                         |
| `ST`          | Departamento     | `ANTIOQUIA`                                  |
| `L`           | Ciudad           | `MEDELLÍN`                                   |
| `STREET`      | Dirección        | `Carrera 65 #3`                              |
| `O`           | Organización     | `MI COMPAÑÍA SAS`                            |
| `OU`          | Unidad org.      | `FACTURACIÓN`                                |
| `SERIALNUMBER`| NIT empresa      | `900400300`                                  |
| `E`           | Email            | `info@empresa.com`                           |
| `GN`          | Nombre rep.legal | `Paula`                                      |
| `SN`          | Apellidos        | `Ibarra`                                     |

**FE-PN — 9 atributos** (sin `O` ni `OU`; `SERIALNUMBER` = cédula):

| OID / Alias   | Campo CSR     | Ejemplo                                      |
|---------------|---------------|----------------------------------------------|
| `C`           | Country       | `CO`                                         |
| `ST`          | Departamento  | `ANTIOQUIA`                                  |
| `L`           | Ciudad        | `MEDELLÍN`                                   |
| `STREET`      | Dirección     | `Carrera 65 #3`                              |
| `SERIALNUMBER`| Cédula        | `1002000400`                                 |
| `E`           | Email         | `info@correo.com`                            |
| `GN`          | Nombre        | `Paula`                                      |
| `SN`          | Apellidos     | `Ibarra`                                     |

> ⚠️ El `dnPattern` real del perfil viene en la respuesta del `GET /ra/available-profiles?codRa={ra}` y **debe respetarse al construir el CSR** (orden y casing de los componentes). El builder validará el CSR generado contra ese `dnPattern` antes de enviarlo.

---

## 3. 🗄️ Modelo de Datos

> 🚫 **Política obligatoria de migraciones (NO NEGOCIABLE):**
> **NUNCA** se ejecutará `php artisan migrate` global en este proyecto. Toda migración relacionada con este módulo se correrá **individualmente** con `--path`, previa autorización explícita:
>
> ```bash
> php artisan migrate --path=/database/migrations/viafirma/2026_05_14_100000_add_legal_rep_fields_to_certificate_requests.php
> php artisan migrate --path=/database/migrations/viafirma/2026_05_14_100001_create_viafirma_certificate_requests_table.php
> php artisan migrate --path=/database/migrations/viafirma/2026_05_14_100002_create_viafirma_status_history_table.php
> ```
>
> Por ello las migraciones de este módulo se ubicarán en una **carpeta dedicada** `database/migrations/viafirma/` (no en la raíz de migrations) para evitar que un `migrate` accidental las ejecute. El `MigrationServiceProvider` del módulo **no** registrará `loadMigrationsFrom()` para evitar ejecución implícita.

### 3.0 🔗 Homologación con la Base de Datos Productiva (NO NEGOCIABLE)

> ⚠️ **Contexto crítico:** Certificate Manager v3.0 ya está en **producción con data real** (clientes activos, solicitudes históricas, archivos cargados). Este módulo **NO crea catálogos paralelos**: reutiliza los existentes y se integra de forma **aditiva**, sin tocar columnas con data ni romper relaciones vigentes.

#### 3.0.1 Catálogos existentes en producción (fuente única de verdad)

| Catálogo productivo                              | Uso por este módulo                                                              |
|--------------------------------------------------|----------------------------------------------------------------------------------|
| `identity_documents` (modelo `IdentityDocument`) | Tipo de documento DIAN del solicitante / representante legal                     |
| `type_organization` (modelo `TypeOrganization`)  | Naturaleza del cliente: Persona Jurídica / Persona Natural                       |
| `companies` (modelo `Company`)                   | **Empresa cliente** (NIT, DV, dirección, ciudad, país, email). NO se duplica.    |
| `certificate_requests` (modelo `CertificateRequest`) | **Solicitud "negocio" existente** (manual). El nuevo flujo Viafirma se **engancha** a ésta. |
| `countries`, `cities`, `departments`             | Geografía. `companies.country_id = 45` (Colombia) por defecto.                   |
| `file_managers` (FK `certificate_request_id`)    | Documentos adjuntos del solicitante (RUT, cédula RL, comprobante de pago, etc.). |
| `change_histories` (FK `certificate_request_id`) | Auditoría de cambios de estado del proceso "negocio".                            |

**Valores reales productivos verificados (no se modifican):**

```sql
-- identity_documents (DIAN code es el oficial)
INSERT INTO `identity_documents` (`id`, `code`, `abbreviation`, `document_name`) VALUES
    (1, '13', 'CC',  'Cédula de Ciudadanía'),
    (2, '22', 'CE',  'Cédula de Extranjería'),
    (3, '31', 'NIT', 'NIT');

-- type_organization
INSERT INTO `type_organization` (`id`, `code`, `description`) VALUES
    (1, 1, 'Persona Jurídica'),
    (2, 2, 'Persona Natural');
```

#### 3.0.2 Mapeo `Catálogo Local → Enum Viafirma`

Estos mappers serán implementados como **enums PHP 8.2** + métodos `fromIdentityDocument()` / `fromTypeOrganization()` en `app/Modules/Viafirma/Domain/Mappers/`. **Sin if-else regados por servicios.**

| Local                                            | Viafirma (`profileType`) | Viafirma (`identityType`) | Notas                                                                 |
|--------------------------------------------------|--------------------------|---------------------------|-----------------------------------------------------------------------|
| `type_organization.code = 1` (Persona Jurídica)  | `FE_PJ`                  | (KYC al **representante legal**) | El `identity` enviado a Viafirma **NO** es el NIT; es la CC/CE/PAS del RL. |
| `type_organization.code = 2` (Persona Natural)   | `FE_PN`                  | derivado del documento del titular | `identity` = `certificate_requests.document_number` (CC/CE/PAS).      |
| `identity_documents.abbreviation = 'CC'` (`code=13`) | —                    | `IDC`                     | Cédula de Ciudadanía.                                                 |
| `identity_documents.abbreviation = 'CE'` (`code=22`) | —                    | `IDC`                     | Cédula de Extranjería. (Viafirma trata ambas como Identity Card.)     |
| `identity_documents.abbreviation = 'PAS'` (futuro, `code='41'`) | —          | `PAS`                     | **No existe aún en producción**: ver seeder aditivo §3.0.4.           |
| `identity_documents.abbreviation = 'NIT'` (`code=31`) | —                    | **N/A**                   | El NIT identifica a la empresa, no al solicitante; jamás se envía como `identityType`. |

**El `organizationType` Viafirma** (`RM` / `PROP` / `RUNEOL` / `RNT` / `ESAL` / `ESOL` / `JUEGOS` / `EXTRANJERAS`) **no está en el modelo actual**. Se introduce como columna nueva **opcional** en la tabla del módulo (no en `companies`) ya que es un dato específico del trámite Viafirma y no de la empresa en sí.

#### 3.0.3 Cambios aditivos requeridos en tablas existentes (migraciones individuales, NO destructivas)

Para soportar el KYC del **representante legal** en perfiles PJ — dato que hoy `certificate_requests.legal_representative` guarda solo como string libre — se agregan columnas **`nullable`** que no afectan registros antiguos:

```php
// database/migrations/viafirma/2026_05_14_100000_add_legal_rep_fields_to_certificate_requests.php
Schema::table('certificate_requests', function (Blueprint $t) {
    // Documento estructurado del Representante Legal (solo se llena para PJ que vayan por Viafirma)
    $t->foreignId('legal_rep_identity_document_id')
      ->nullable()
      ->after('legal_representative')
      ->constrained('identity_documents')
      ->nullOnDelete();
    $t->string('legal_rep_identity_number', 32)->nullable()->after('legal_rep_identity_document_id');
    $t->string('legal_rep_email', 150)->nullable()->after('legal_rep_identity_number');
});
```

> ✅ Compatible 100% con los 581+ registros históricos de `certificate_requests` (todas las nuevas columnas son `NULL` por defecto).
> ❌ **NO se modifica** ninguna columna existente.
> ❌ **NO se borra** nada.
> 🔒 Solo se ejecuta tras autorización explícita y vía `php artisan migrate --path=...` (ver política §3).

#### 3.0.4 Seeder aditivo opcional: catálogo Pasaporte

Si el negocio decide soportar Pasaporte (Viafirma `identityType=PAS`):

```php
// database/seeders/viafirma/AddPassportIdentityDocumentSeeder.php
IdentityDocument::firstOrCreate(
    ['code' => '41'],
    ['document_name' => 'Pasaporte', 'abbreviation' => 'PAS', 'scheme_name' => 'salud_identificación.gc', 'active' => 1]
);
```

> Ejecución manual: `php artisan db:seed --class=AddPassportIdentityDocumentSeeder`. Idempotente.

#### 3.0.5 Modelo de relaciones final

```text
companies (existente, sin cambios)
    └── 1:N → certificate_requests (existente, +3 columnas nullable de RL)
                  └── 1:1 → viafirma_certificate_requests  ⬅ NUEVO (este módulo)
                                  └── 1:N → viafirma_status_history
                  └── 1:N → file_managers (existente, reutilizado para adjuntos KYC)
                  └── 1:N → change_histories (existente, reutilizado para auditoría)
```

**Principio**: el `viafirma_certificate_requests` es un **agregado complementario** que materializa el "subdominio Viafirma" sobre una `certificate_request` ya creada. **No reemplaza** ni duplica el flujo actual: lo extiende cuando la solicitud opta por el camino Zero-Touch PKCS#10.

---

### 3.1 Tabla principal: `viafirma_certificate_requests`

Esta es la **tabla principal del nuevo flujo PKCS#10**, núcleo del módulo de emisión de certificados. Es el agregado raíz del bounded context "Viafirma Issuance".

> 🔁 **Homologación aplicada (v1.2):** los datos del solicitante (identidad, organización, ubicación, email) **NO se duplican** aquí. Se obtienen mediante eager-loading desde `certificate_requests` → `companies` → catálogos. La tabla solo guarda: (a) FK hacia el agregado padre, (b) datos **propios del trámite Viafirma**, (c) estado de la FSM, (d) artefactos criptográficos.

```php
Schema::create('viafirma_certificate_requests', function (Blueprint $t) {
    $t->id();

    // --- Enlace fuerte al agregado de negocio existente ---
    $t->foreignId('certificate_request_id')   // FK al flujo "negocio" ya existente
        ->unique()                            // 1:1 (cada solicitud genera a lo más un proceso Viafirma)
        ->constrained('certificate_requests')
        ->cascadeOnDelete();
    $t->foreignId('company_id')->constrained()->cascadeOnDelete(); // denormalizado para índices y queries rápidas
    $t->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();

    // --- Identificadores Viafirma ---
    $t->string('cod_request', 32)->nullable()->unique()->index();   // PYJR5N4QC
    $t->string('public_id', 64)->nullable()->index();               // bd6eda8d0f2d…
    $t->string('cod_profile')->nullable();
    $t->string('ra_code', 32); // tomado de config('viafirma.ra_code'); sin default hardcoded

    // --- Datos derivados / específicos del trámite Viafirma ---
    // ⚠️ profile_type se DERIVA de companies.type_organization_id, pero se persiste
    //    para snapshot histórico (si el cliente cambia su tipo, el trámite emitido no muta).
    $t->enum('profile_type', ['FE_PJ', 'FE_PN'])->index();
    $t->enum('identity_type', ['IDC', 'PAS'])->default('IDC');
    $t->string('country_code', 2)->default('CO');

    // organizationType de Viafirma (RM/PROP/RUNEOL/RNT/ESAL/ESOL/JUEGOS/EXTRANJERAS)
    // Solo aplica a FE_PJ. No existe en el catálogo local → se persiste aquí, no en companies.
    $t->string('organization_type', 16)->nullable();

    $t->unsignedSmallInteger('validity_days')->default(730);

    // --- Estado de la máquina (FSM interna + estado remoto) ---
    $t->string('internal_state', 32)->default('DRAFT')->index();
    // DRAFT → CSR_GENERATED → SUBMITTED → POLLING → READY_TO_DOWNLOAD
    //   → DOWNLOADED → ASSEMBLED → COMPLETED | FAILED | EXPIRED
    $t->string('remote_status', 64)->nullable()->index();
    // Estados V1.1:
    //  rues_check | rues_error |
    //  accreditation | accreditation_check | accreditation_completed |
    //  accreditation_verified | accreditation_rejected |
    //  proposeFor | proposedToAcceptance | All_Ok | inProcess | fail |
    //  Generated_Not_Downloaded | Generated_And_Downloaded

    // --- Criptografía (referencias, NUNCA el material) ---
    $t->string('key_vault_ref', 128);          // Ruta cifrada o ARN KMS
    $t->string('csr_fingerprint', 64);         // SHA-256 hex del CSR (auditoría)
    $t->text('csr_pem')->nullable();           // CSR en PEM (público, OK guardar)
    $t->string('p7b_storage_path')->nullable();// Ruta storage al .p7b descargado
    $t->string('p12_storage_path')->nullable();// Ruta storage al .p12 ensamblado
    $t->string('p12_password_ref')->nullable();// Referencia al PIN cifrado (Vault)

    // --- Payload original y respuesta (auditoría) ---
    $t->json('request_payload')->nullable();
    $t->json('last_status_response')->nullable();

    // --- Polling control ---
    $t->unsignedSmallInteger('poll_attempts')->default(0);
    $t->timestamp('next_poll_at')->nullable()->index();
    $t->timestamp('last_polled_at')->nullable();
    $t->timestamp('submitted_at')->nullable();
    $t->timestamp('downloaded_at')->nullable();
    $t->timestamp('assembled_at')->nullable();
    $t->timestamp('expires_at')->nullable();   // SLA: 72h sin acreditar → EXPIRED

    // --- Errores ---
    $t->string('last_error_code', 64)->nullable();
    $t->text('last_error_message')->nullable();

    $t->timestamps();
    $t->softDeletes();

    $t->index(['internal_state', 'next_poll_at']); // Crítico para el scheduler
});
```

> 📌 **Resolución dinámica vs. snapshot:**
> - `identity`, `email_certificate`, `company_name`, `address`, `city_id` y demás datos del solicitante **no se duplican** como columnas: se resuelven en tiempo de consulta vía `viafirmaRequest->certificateRequest->company` y `viafirmaRequest->certificateRequest->identity`.
> - En el momento del `submit` a Viafirma, el `RequestPayloadBuilder` materializa esos valores y los persiste **íntegros** en `request_payload` (JSON), que actúa como **snapshot inmutable de auditoría**. Si el cliente luego edita su email o ciudad, el trámite ya enviado conserva el dato original. Esto cumple con el principio CQRS aplicado: la lectura es dinámica, el envío es histórico.
> - Cualquier consulta del frontend debe usar la relación `with(['certificateRequest.company.country','certificateRequest.company.city','certificateRequest.identity','certificateRequest.organization'])` para evitar N+1.

### 3.2 Tabla auxiliar: `viafirma_status_history` (auditoría / debugging)

```php
Schema::create('viafirma_status_history', function (Blueprint $t) {
    $t->id();
    $t->foreignId('viafirma_certificate_request_id')->constrained()->cascadeOnDelete();
    $t->string('previous_state', 32)->nullable();
    $t->string('new_state', 32);
    $t->string('remote_status', 64)->nullable();
    $t->json('raw_response')->nullable();
    $t->unsignedInteger('attempt_number')->default(0);
    $t->timestamp('occurred_at')->useCurrent();
    $t->index(['viafirma_certificate_request_id', 'occurred_at']);
});
```

### 3.3 Configuración global: `config/viafirma.php` (NUEVO)

```php
return [
    // ⚠️ TODOS los dominios/URLs son CONFIGURABLES por entorno.
    // Ningún valor de proveedor está hardcodeado en el código. Las variables
    // se cargan desde .env y se gestionan por entorno (sandbox / staging / producción).
    // Valores requeridos en .env (sin defaults productivos):
    //   VIAFIRMA_RA_URL            (base del API REST, ej. https://<host>/ra/api/v2)
    //   VIAFIRMA_RA_DOWNLOAD_URL   (base para descarga del P7B, ej. https://<host>/ra)
    //   VIAFIRMA_CLIENT_ID         (OAuth1 Consumer Key)
    //   VIAFIRMA_CLIENT_SECRET     (OAuth1 Consumer Secret)
    //   VIAFIRMA_RA_CODE           (código de RA asignado por el proveedor)
    'base_url'        => env('VIAFIRMA_RA_URL'),
    'download_url'    => env('VIAFIRMA_RA_DOWNLOAD_URL'),
    'client_id'       => env('VIAFIRMA_CLIENT_ID'),
    'client_secret'   => env('VIAFIRMA_CLIENT_SECRET'),
    'ra_code'         => env('VIAFIRMA_RA_CODE'),
    'timeout'         => env('VIAFIRMA_HTTP_TIMEOUT', 30),
    'retry'           => ['max' => 3, 'base_ms' => 500],

    // Validez por defecto reportada por el perfil (V1.1 = 730 días).
    // Se sobreescribe con el campo `validity` retornado por /ra/available-profiles.
    'certificate_validity_days' => 730,

    'polling'         => [
        'max_attempts'     => 96,                  // ≈ techo 24h con backoff
        'expiration_hours' => 72,                  // SLA de acreditación
        'intervals'        => [                    // segundos por estado
            'rues_check'              => 30,
            'accreditation'           => 300,      // KYC humano → 5 min base
            'accreditation_check'     => 120,      // sub-estado
            'accreditation_completed' => 60,       // sub-estado, casi listo
            'accreditation_verified'  => 30,       // sub-estado, transición inminente
            'proposeFor'              => 120,
            'proposedToAcceptance'    => 120,
            'inProcess'               => 60,
            'All_Ok'                  => 30,
            'default'                 => 180,
        ],
        'jitter_pct' => 20,                        // ±20% para evitar thundering herd
    ],
    'crypto' => [
        'key_size'         => 2048,
        'signature_algo'   => 'sha256WithRSAEncryption',
        'key_vault_driver' => env('VIAFIRMA_KEY_VAULT', 'encrypted_local'), // encrypted_local | aws_kms
        'aws_kms_key_id'   => env('AWS_KMS_KEY_ID'),
    ],
];
```

---

## 4. 🔁 Estrategia de Polling (Punto Crítico)

### 4.1 Problema

- `accreditation` puede tardar de **5 min a varias horas** (KYC humano).
- Cron fijo cada 1 min ⇒ ~1440 req/día/solicitud × N solicitudes = saturación bilateral.
- Llamadas síncronas en el request HTTP del cliente ⇒ timeouts.

### 4.2 Solución recomendada: **Self-Scheduling Job con Exponential Backoff + Jitter**

> No usar `schedule:run` para iterar registros. En su lugar, cada job se **reagenda a sí mismo** usando `release($delay)` o `dispatch(...)->delay($next)`. Esto distribuye la carga, respeta el estado individual y permite cancelar fácilmente.

#### 4.2.1 Algoritmo de cálculo del próximo intervalo

```
nextDelay(state, attempts):
    base   = config.intervals[state] ?? config.intervals.default
    growth = min(2 ^ floor(attempts / 5), 8)        // duplica cada 5 intentos, tope x8
    jitter = random(-base*0.2, base*0.2)
    return base * growth + jitter
```

| Estado remoto                | Intervalo base | Comportamiento                                                |
|------------------------------|---------------:|---------------------------------------------------------------|
| `rues_check`                 |  30 s          | Validación RUES rápida (**solo PJ**); backoff agresivo        |
| `rues_error`                 |  —             | **STOP polling**, requiere operador RA. Marca `FAILED_RECOVERABLE` |
| `accreditation`              | 300 s (5 min)  | Espera KYC humano; backoff progresivo hasta ~40 min           |
| `accreditation_check`        | 120 s          | KYC en curso (validación automática del documento)            |
| `accreditation_completed`    |  60 s          | KYC completado; transición inminente a `verified`             |
| `accreditation_verified`     |  30 s          | KYC verificado; próximo paso `proposeFor`                     |
| `accreditation_rejected`     |  —             | **STOP polling**, operador RA decide (re-enviar link o rechazar) |
| `proposeFor` / `proposed…`   | 120 s          | Validación interna RA                                         |
| `inProcess`                  |  60 s          | Generación del certificado en la CA (≤ 5 min según SLA Viafirma) |
| `All_Ok`                     |  30 s          | Inminente paso a `Generated_Not_Downloaded`                   |
| `Generated_Not_Downloaded`   |  —             | **STOP polling**, dispara `DownloadP7bJob` inmediatamente     |
| `Generated_And_Downloaded`   |  —             | **Terminal OK** (re-descargable). No re-poll salvo solicitud manual. |
| `fail`                       |  —             | **STOP polling**, marca `FAILED`, notifica al cliente         |

#### 4.2.2 Implementación (esqueleto)

```php
final class PollViafirmaStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;          // El reintento lo controla el dominio, no la cola
    public int $timeout = 25;

    public function __construct(public readonly int $requestId) {}

    public function uniqueId(): string { return "viafirma-poll-{$this->requestId}"; }
    public function uniqueFor(): int   { return 600; }

    public function handle(
        ViafirmaCertificateRequestRepository $repo,
        ViafirmaRaClient $client,
        PollingScheduler $scheduler,
        StateMachine $fsm,
    ): void {
        $req = $repo->findOrFail($this->requestId);

        if ($req->isTerminal() || $req->hasExpired()) return;

        try {
            $status = $client->getStatus($req->cod_request);
        } catch (TransientHttpException $e) {
            // Red/5xx → reagendar corto con jitter, no escalar al cliente
            self::dispatch($req->id)->delay($scheduler->retryAfter($req));
            return;
        }

        $fsm->transition($req, $status->code, $status->raw);
        $repo->save($req);

        match (true) {
            $req->isReadyToDownload() => DownloadP7bJob::dispatch($req->id),
            $req->isFailed()          => NotifyFailureJob::dispatch($req->id),
            default                   => self::dispatch($req->id)
                                            ->delay($scheduler->nextDelay($req)),
        };
    }
}
```

#### 4.2.3 Disparo inicial

Tras `POST /request/fromCSR` exitoso, el `SubmitCertificateRequestUseCase` despacha **una sola vez**:

```php
PollViafirmaStatusJob::dispatch($req->id)->delay(now()->addSeconds(15));
```

#### 4.2.4 Red de seguridad (Watchdog Cron)

Único `schedule:command` cada **15 min** que sólo busca **solicitudes huérfanas** (sin `next_poll_at` programado y no terminales) y las re-arma. Evita zombis por workers caídos.

```php
// app/Console/Kernel.php
$schedule->job(new ReviveStalledViafirmaPollsJob)->everyFifteenMinutes()->withoutOverlapping();
```

#### 4.2.5 Beneficios

- **Carga acotada:** 1 req cada 30s-40min por solicitud, no fija.
- **Jitter:** evita ráfagas sincronizadas si se crean N solicitudes simultáneas.
- **Idempotencia:** `ShouldBeUnique` previene doble polling concurrente.
- **Observabilidad:** cada transición queda en `viafirma_status_history`.
- **Circuit breaker:** si el cliente Viafirma falla > X veces, abre el circuito y pausa todos los polls 5 min (config global vía Redis).

---

## 5. 🔐 Seguridad de Llaves Privadas (No Negociable)

| Riesgo                                     | Mitigación                                                                   |
|--------------------------------------------|------------------------------------------------------------------------------|
| Llave en texto plano en FS                 | **AES-256-GCM** vía Laravel `Crypt` + clave por-tenant derivada (HKDF)       |
| Acceso indebido al servidor                | Almacenamiento delegado a **AWS KMS / Secrets Manager** en producción        |
| Log accidental del PEM                     | Redactor en `smart-logger.service` (regex `BEGIN .* PRIVATE KEY`)            |
| Backup con llaves                          | Disco S3 dedicado con bucket policy + SSE-KMS + Object Lock                  |
| Llave huérfana tras `FAILED`               | Job de purga: borrado seguro (overwrite + delete) a las 72h del fallo        |
| Robo del PIN del `.p12`                    | PIN aleatorio 32 chars (CSPRNG), almacenado encriptado, rotable              |
| Reutilización indebida CSR                 | `csr_fingerprint` único; rechazo si ya existe en `ASSEMBLED`                 |

**Interfaz `KeyVault`** (DIP — Dependency Inversion):

```php
interface KeyVault
{
    public function store(string $material, array $metadata = []): string; // returns ref
    public function retrieve(string $ref): string;
    public function destroy(string $ref): void;
}

// Implementaciones intercambiables:
final class EncryptedLocalKeyVault implements KeyVault { /* dev/staging */ }
final class AwsKmsKeyVault       implements KeyVault { /* producción  */ }
```

---

## 6. 📅 Roadmap por Sprints (Scrum)

> **Cadencia:** Sprints de 2 semanas · **Team:** 2 backend + 1 QA + 0.5 DevOps · **Velocidad estimada:** ~25 SP/sprint

---

### 🏁 Sprint 0 — Discovery & Spike Técnico (3 días, pre-roadmap)

**Objetivo:** Validar viabilidad técnica antes de comprometer scope.

- [ ] Spike: ejecutar la colección Postman completa contra sandbox (smoke test manual).
- [ ] Spike: generar par RSA-2048 + CSR en PHP con `phpseclib3` y aceptarlo en sandbox.
- [ ] Spike: ensamblar `.p12` válido (probarlo con `openssl pkcs12 -info`).
- [ ] Spike: firmar OAuth 1.0 HMAC-SHA1 con `guzzlehttp/oauth-subscriber` y obtener 200 OK.
- [ ] Decisión: ¿Saloon v3 o Guzzle puro? (recomendado **Saloon** por testabilidad).

**Entregable:** Documento `SPIKE-VIAFIRMA-2026-05-XX.md` con go/no-go.

---

### 🚀 Sprint 1 — Fundación: Criptografía Local + Auth + Cliente HTTP (2 semanas)

**Sprint Goal:** *"Como sistema, puedo generar localmente una llave privada y un CSR válido, y autenticarme contra Viafirma."*

#### Backlog

| ID    | Historia / Tarea                                                              | SP | Tipo   |
|-------|--------------------------------------------------------------------------------|----|--------|
| V-101 | Crear módulo `app/Modules/Viafirma/` (Domain, Application, Infrastructure)     | 3  | Arch   |
| V-102 | `config/viafirma.php` + variables `.env.example`                              | 1  | Config |
| V-103 | `CryptoService::generateKeyPair()` (RSA-2048 vía phpseclib3)                  | 3  | Dev    |
| V-104 | `CsrBuilderFactory` + `FePjCsrBuilder` (10 attrs) + `FePnCsrBuilder` (9 attrs) | 8  | Dev    |
| V-105 | Interfaz `KeyVault` + impl. `EncryptedLocalKeyVault` (AES-256-GCM)            | 5  | Sec    |
| V-106 | `ViafirmaRaClient` (Saloon Connector) con OAuth1 HMAC-SHA1 Middleware         | 5  | Dev    |
| V-107 | Endpoint `GET /ra/available-profiles?codRa={ra}` envuelto en `GetProfilesRequest` con parser de `dnPattern`, `validity` y `token` | 3  | Dev    |
| V-108 | Tests unitarios `CryptoService` + ambos builders (PJ/PN) con golden CSRs      | 5  | QA     |
| V-109 | Tests Feature con Saloon `MockClient` para profiles                           | 2  | QA     |
| V-110 | Logger seguro: extender `smart-logger.service` con redactor de PEMs            | 2  | Sec    |
| V-111 | Enums `CertificateProfile`, `IdentityType`, `OrganizationType` + validadores  | 2  | Dev    |

**Definition of Done:**
- `php artisan tinker` → `app(CsrBuilderFactory::class)->for(CertificateProfile::FE_PJ)->build($dto)` retorna CSR PEM válido con los **10 atributos** requeridos (verificable con `openssl req -in csr.pem -noout -text`).
- Idem para `FE_PN` con **9 atributos** (sin `O`/`OU`).
- `app(ViafirmaRaClient::class)->getProfiles(config('viafirma.ra_code'))` retorna ambos perfiles tipados del entorno configurado.
- Cobertura ≥ 85% en módulo Viafirma.

---

### 🔗 Sprint 2 — Integración de Endpoints + Caso de Uso de Emisión (2 semanas)

**Sprint Goal:** *"Como operador del ERP, puedo iniciar la emisión de un certificado y obtener `codRequest` + `publicId` de Viafirma."*

#### Backlog

| ID    | Historia / Tarea                                                              | SP |
|-------|--------------------------------------------------------------------------------|----|
| V-201 | Migraciones (carpeta `database/migrations/viafirma/`) ejecutadas con `--path`  | 3  |
| V-202 | Modelos Eloquent + `ViafirmaCertificateRequestRepository`                      | 3  |
| V-203 | DTO `IssueCertificateCommand` con validación (FormRequest), discriminado por `profile_type` (PJ/PN) | 5  |
| V-204 | `IssueCertificateUseCase` (orquesta: resolver perfil→genKey→CSR via factory→submit→persist→dispatch poll) | 8  |
| V-205 | `SubmitCsrRequest` (Saloon) → `POST /request/fromCSR` con payload condicional (`organizationType` solo si PJ) | 5  |
| V-206 | `GetPublicIdRequest` (Saloon) → `GET /request/{cod}/publicId`                 | 2  |
| V-207 | Controllers `POST /api/v2/certificates/viafirma/issue` (auto-detecta PJ/PN) + OpenAPI/Swagger | 3  |
| V-208 | Validación de `organizationType` contra enum `OrganizationType` (rechazo si PN lo envía) | 2  |
| V-209 | Tests Feature E2E con Saloon mocks: **PJ camino feliz**, **PN camino feliz**, 4xx, 5xx | 8  |
| V-210 | Hook a `ChangeHistory` para auditoría de creación de solicitud                 | 1  |
| V-211 | Validación cruzada: DN del CSR coincide con `dnPattern` del perfil obtenido    | 3  |

**DoD:**
- POST a `/api/v2/certificates/viafirma/issue` con payload de empresa real crea registro con `cod_request` y `public_id` no nulos, estado interno `SUBMITTED`, llave privada cifrada en vault.
- Llave privada **NUNCA** aparece en logs (test específico que falla si lo hace).

---

### ⏱️ Sprint 3 — Polling Asíncrono + State Machine + Resiliencia (2 semanas)

**Sprint Goal:** *"El sistema avanza autónomamente las solicitudes a través de los estados de Viafirma sin saturar la API remota."*

#### Backlog

| ID    | Historia / Tarea                                                              | SP |
|-------|--------------------------------------------------------------------------------|----|
| V-301 | `StateMachine` (transiciones válidas + guard clauses + sub-estados de `accreditation`) | 8  |
| V-302 | `PollingScheduler` (intervalos + exponential backoff + jitter)                 | 3  |
| V-303 | `PollViafirmaStatusJob` (`ShouldBeUnique` + auto-reschedule)                   | 5  |
| V-304 | `GetStatusRequest` (Saloon) con parser tipado                                  | 2  |
| V-305 | `ReviveStalledViafirmaPollsJob` (watchdog cron cada 15 min)                    | 2  |
| V-306 | Circuit Breaker (Redis-backed) ante 5xx repetidos                              | 3  |
| V-307 | Eventos: `ViafirmaStatusChanged`, `ViafirmaRequestFailed`, `…ReadyToDownload`  | 2  |
| V-308 | Listener: `NotifyClientOnAccreditationListener` (email con link KYC)           | 3  |
| V-309 | Tests time-travel (`Carbon::setTestNow`) para validar backoff                  | 3  |
| V-310 | Dashboard Horizon: tag jobs por `viafirma:*` + alertas de fallidos             | 2  |

**DoD:**
- Test E2E: simular ciclo `rues_check → accreditation → accreditation_check → accreditation_completed → accreditation_verified → proposeFor → proposedToAcceptance → All_Ok → inProcess → Generated_Not_Downloaded` en <30s con `Bus::fake()` y `Carbon` controlado.
- Test E2E equivalente para FE-PN (omitiendo `rues_check`).
- Bajo carga simulada (50 solicitudes en `accreditation` concurrentes) la API Viafirma recibe ≤ 1 req/segundo agregada.

---

### 📦 Sprint 4 — Descarga, Ensamblaje P12 y Entrega al Cliente (2 semanas)

**Sprint Goal:** *"El sistema descarga el .p7b, ensambla un .p12 listo para firmar XMLs DIAN y lo entrega al cliente final de forma segura."*

#### Backlog

| ID    | Historia / Tarea                                                              | SP |
|-------|--------------------------------------------------------------------------------|----|
| V-401 | `DownloadP7bRequest` (Saloon, binary response handling)                        | 3  |
| V-402 | `DownloadP7bJob` (guarda en S3 con SSE-KMS, valida Content-Type)               | 3  |
| V-403 | `CryptoService::assembleP12(privateKeyRef, p7bPath, pin): string`              | 8  |
| V-404 | Validación post-ensamblaje: `openssl pkcs12 -info` + verificar cadena CA       | 3  |
| V-405 | `AssembleP12Job` orquesta descarga→ensamblaje→limpieza llave privada efímera  | 3  |
| V-406 | Endpoint `GET /api/v2/certificates/viafirma/{id}/download` (tokens firmados)   | 3  |
| V-407 | Notificación email al cliente con PIN cifrado + link descarga 24h              | 3  |
| V-408 | Publicar eventos del ciclo de vida del certificado en el bus interno de la app | 3  |
| V-409 | Pruebas de firma real: firmar un XML de prueba DIAN con el `.p12` ensamblado   | 5  |
| V-410 | Job de retención: purga segura de llaves privadas tras `COMPLETED`             | 2  |

**DoD:**
- Un `.p12` ensamblado firma exitosamente un Invoice XML UBL 2.1 DIAN (validado con xmldsig + chain trust).
- En estado `COMPLETED`, `key_vault_ref` ha sido marcado para purga programada.

---

### 🧪 Sprint 5 — Hardening, Observabilidad & Go-Live (1-2 semanas)

**Sprint Goal:** *"Sistema production-ready con métricas, alertas y plan de rollback."*

#### Backlog

| ID    | Historia / Tarea                                                              | SP |
|-------|--------------------------------------------------------------------------------|----|
| V-501 | Migrar `KeyVault` a `AwsKmsKeyVault` en entorno productivo                     | 5  |
| V-502 | Métricas Prometheus/CloudWatch: latencia, tasa de éxito, tiempos KYC           | 3  |
| V-503 | Alertas: solicitud > 24h en `accreditation`, ratio fail > 5%                   | 2  |
| V-504 | Runbook operativo (`docs/runbooks/viafirma-incidents.md`)                      | 2  |
| V-505 | Pruebas de carga (k6): 100 solicitudes/hora sustained                          | 3  |
| V-506 | Pen-test interno enfocado en endpoints `/issue` y `/download`                  | 5  |
| V-507 | Documentación API pública (Swagger/Stoplight) + ejemplos cURL                  | 2  |
| V-508 | Feature flag `viafirma_pkcs10_enabled` (Laravel Pennant) + rollout gradual     | 3  |
| V-509 | Sesión de KT con equipo de soporte + grabación                                 | 1  |

**DoD release:** Go-Live con feature flag al 10% → 50% → 100% en 2 semanas, monitoreando KPIs.

---

## 7. 🧱 Estructura de Carpetas Propuesta (alineada al proyecto existente)

```
app/
├── Modules/
│   └── Viafirma/
│       ├── Application/
│       │   ├── Commands/          # IssueCertificateCommand, DownloadP12Command
│       │   ├── UseCases/          # IssueCertificateUseCase, AssembleP12UseCase
│       │   └── DTOs/              # PkcsCsrDto, StatusResultDto
│       ├── Domain/
│       │   ├── Entities/          # ViafirmaCertificateRequest (Eloquent)
│       │   ├── ValueObjects/      # CodRequest, PublicId, RemoteStatus
│       │   ├── Events/            # ViafirmaStatusChanged, …
│       │   ├── StateMachine/      # FSM, transitions table
│       │   └── Contracts/         # KeyVault, ViafirmaClient, PollingScheduler
│       ├── Infrastructure/
│       │   ├── Http/Viafirma/     # Saloon Connector + Requests + Auth
│       │   ├── Crypto/            # CryptoService (phpseclib3)
│       │   ├── KeyVault/          # EncryptedLocal + AwsKms impls
│       │   └── Persistence/       # Repositories Eloquent
│       └── Presentation/
│           ├── Http/Controllers/  # ViafirmaCertificateController
│           ├── Http/Requests/     # IssueCertificateRequest (FormRequest)
│           └── Http/Resources/    # ViafirmaCertificateResource
├── Jobs/
│   ├── Viafirma/
│   │   ├── PollViafirmaStatusJob.php
│   │   ├── DownloadP7bJob.php
│   │   ├── AssembleP12Job.php
│   │   ├── NotifyFailureJob.php
│   │   └── ReviveStalledViafirmaPollsJob.php
```

---

## 8. 📦 Dependencias Externas Sugeridas

> ⚠️ Ninguna se instalará sin orden explícita (regla operativa). Solo se listan para validación.

| Paquete                              | Propósito                              | Justificación                              |
|--------------------------------------|----------------------------------------|--------------------------------------------|
| `phpseclib/phpseclib:^3.0`           | Generación RSA + CSR PKCS#10           | Puro PHP, no depende de ext-openssl quirks |
| `saloonphp/saloon:^3.0`              | Cliente HTTP estructurado              | Testabilidad (MockClient), tipado          |
| `saloonphp/laravel-plugin:^3.0`      | Integración Laravel                    | DI, caching, events                        |
| `guzzlehttp/oauth-subscriber:^0.8`   | Firma OAuth 1.0 HMAC-SHA1              | Implementación probada del estándar        |
| `spatie/laravel-data:^4.0`           | DTOs tipados                           | Reemplaza arrays inseguros                 |
| `aws/aws-sdk-php:^3.0` *(prod)*      | KMS / Secrets Manager                  | Custodia de llaves en producción           |

---

## 9. 🚨 Riesgos & Mitigaciones

| Riesgo                                                  | Probabilidad | Impacto | Mitigación                                         |
|---------------------------------------------------------|:------------:|:-------:|----------------------------------------------------|
| Viafirma cambia formato OAuth1 sin aviso                |     Baja     |  Alto   | Contract tests semanales contra sandbox            |
| KYC del cliente nunca se completa                       |    Media     |  Medio  | SLA 72h → `EXPIRED`, notificación + retry manual   |
| Pérdida del par de llaves antes del ensamblaje          |     Baja     | Crítico | Backup encriptado en S3 con versionado + KMS       |
| `.p12` no acepta firma DIAN por DN mal formado          |    Media     |  Alto   | Pre-validación DN contra `dnPattern` del perfil    |
| Saturación del worker por backlog                       |     Baja     |  Medio  | Horizon autoscaling + queue dedicada `viafirma`    |
| Filtración de PIN del `.p12` por email                  |    Media     |  Alto   | Link 24h con token firmado; PIN nunca en body plano|
| Variable `clientSecret_RA` filtrada en git              |     Baja     | Crítico | Pre-commit hook + scan secretos + AWS Secrets Mgr  |

---

## 10. 📊 KPIs de Éxito (post Go-Live)

- **TTC (Time-to-Certificate):** P50 < 15 min, P95 < 2 h.
- **Tasa de éxito automática (sin intervención):** > 95%.
- **Carga sobre Viafirma RA:** ≤ 200 req/h por cada 100 solicitudes activas.
- **MTTR ante fallo de red:** < 5 min (gracias a circuit breaker + reintentos).
- **Zero leaks:** 0 ocurrencias de PEM/PIN en logs (validado por scanner CI).

---

## 10.bis 🛠️ Política Operativa de Migraciones y Despliegue

### Reglas inviolables

1. ❌ **PROHIBIDO** ejecutar `php artisan migrate` (sin flags).
2. ❌ **PROHIBIDO** ejecutar `php artisan migrate:fresh`, `migrate:refresh` o `migrate:reset` en cualquier entorno que no sea local-aislado.
3. ✅ **OBLIGATORIO** ejecutar cada migración individualmente con `--path` apuntando al archivo específico.
4. ✅ **OBLIGATORIO** que las migraciones del módulo Viafirma vivan en `database/migrations/viafirma/` (subcarpeta) — la raíz `database/migrations/` queda intocada.
5. ✅ **OBLIGATORIO** registrar cada ejecución en el `CHANGELOG.md` con fecha, entorno y autor.

### Convención de nombres

```
database/migrations/viafirma/
├── 2026_05_14_100001_create_viafirma_certificate_requests_table.php
├── 2026_05_14_100002_create_viafirma_status_history_table.php
└── 2026_05_28_100003_add_kms_columns_to_viafirma_certificate_requests_table.php
```

### Comandos autorizados (a ejecutar previa orden explícita del Tech Lead)

```bash
# Dry-run primero (siempre)
php artisan migrate --path=/database/migrations/viafirma/2026_05_14_100001_create_viafirma_certificate_requests_table.php --pretend

# Aplicar
php artisan migrate --path=/database/migrations/viafirma/2026_05_14_100001_create_viafirma_certificate_requests_table.php

# Rollback puntual (solo si la migración aún es la última en migrations table)
php artisan migrate:rollback --path=/database/migrations/viafirma/2026_05_14_100001_create_viafirma_certificate_requests_table.php --step=1
```

### Salvaguarda: comando wrapper propio (opcional, Sprint 1)

Para evitar errores humanos, el Sprint 1 incluirá un Artisan command custom:

```bash
php artisan viafirma:migrate {file}    # valida que el path esté dentro de viafirma/ antes de ejecutar
php artisan viafirma:migrate:status    # estado de migraciones del módulo
```

Esto se implementará en `app/Console/Commands/Viafirma/ViafirmaMigrateCommand.php`.

---

## 11. 🔄 Backlog Futuro (Post v3.0)

- Renovación automática 30 días antes del vencimiento (`expires_at` watcher, validez 730 días).
- Soporte para nuevos perfiles que publique Viafirma (más allá de FE-PJ / FE-PN, p.ej. Procedimientos Tributarios, Funcionario Público).
- Webhook entrante de Viafirma cuando esté disponible (eliminar polling).
- Multi-tenant key segregation con KMS Customer Managed Keys por empresa.
- Dashboard Filament para operadores: estado en tiempo real de solicitudes (incl. sub-estados de acreditación).
- Re-descarga gobernada del P7B sobre solicitudes en `Generated_And_Downloaded` (auditoría + rate limit).

---

## 12.bis 🚀 Plan de Modernización de Plataforma (HABILITADO por PKCS#10)

> 💡 **Insight crítico del equipo:** durante años, el upgrade de PHP/Laravel del Certificate Manager (y de todas las APIs que firman documentos para la DIAN) ha estado bloqueado por una razón puramente criptográfica: los archivos `.p12` heredados de la antigua autoridad de certificación usan algoritmos marcados como **legacy** por OpenSSL 3.x (RC2-40-CBC, 3DES, SHA-1, PBE-MD5-DES), lo que rompe `openssl_pkcs12_read()` en PHP 8.2+ a menos que se compile/cargue el `legacy_provider`. La migración al flujo **PKCS#10** elimina este bloqueo de raíz porque generamos los certificados con algoritmos modernos.

### 12.bis.1 Por qué estábamos bloqueados

| Componente bloqueante               | Comportamiento en PHP 8.1 + OpenSSL 1.1 | Comportamiento en PHP 8.2+ + OpenSSL 3.x |
|--------------------------------------|------------------------------------------|-------------------------------------------|
| `openssl_pkcs12_read($p12Legacy)`    | ✅ Funciona transparentemente            | ❌ `error:0308010C:digital envelope routines::unsupported` |
| Cifrado interno del `.p12` heredado  | RC2-40-CBC / 3DES-CBC + SHA-1            | Marcado **legacy**, requiere `OSSL_PROVIDER_load(NULL, "legacy")` |
| MAC del `.p12`                       | HMAC-SHA1 con iteración baja             | Considerado inseguro por defecto          |
| Compilación PHP en distros modernas  | OpenSSL 1.1 disponible (EOL Sep 2023)    | Solo OpenSSL 3.x ⇒ rompe `.p12` viejos    |

**Consecuencia:** un upgrade del stack rompía la capacidad de firmar XML DIAN con certificados ya emitidos, lo que era inaceptable en producción.

### 12.bis.2 Por qué PKCS#10 desbloquea TODO

Con el nuevo flujo controlamos **el ciclo de vida criptográfico completo** del certificado:

| Aspecto                  | Antes (`.p12` recibido externo) | Ahora (PKCS#10 generado en casa) |
|--------------------------|----------------------------------|-----------------------------------|
| Generación de par RSA    | Externa, algoritmos opacos       | **Local**, RSA-2048 (o 3072)       |
| Hash de firma            | SHA-1 en muchos casos            | **SHA-256** garantizado            |
| Cifrado del `.p12`       | RC2-40 / 3DES (legacy)           | **AES-256-CBC** (FIPS-friendly)    |
| MAC                      | HMAC-SHA1 baja iteración         | **HMAC-SHA-256** alta iteración    |
| Compatibilidad OpenSSL 3 | ❌ Requiere legacy provider       | ✅ Nativo, sin flags                |

➡️ **Conclusión:** una vez completado el Sprint 4 (ensamblaje P12 moderno), el Certificate Manager queda **técnicamente libre** para actualizarse a PHP 8.3/8.4 y Laravel 11/12 sin afectar la operación de firma DIAN. **Lo mismo aplica para todas las APIs satélite** (ERPs, motores de facturación, micro-servicios de firma) que dependían de los certificados antiguos.

### 12.bis.3 Estado actual del stack vs. objetivo

| Componente              | Versión actual | Objetivo corto plazo | Objetivo largo plazo |
|-------------------------|----------------|----------------------|----------------------|
| PHP                     | `^8.1.6`       | **8.3**              | **8.4 LTS**          |
| Laravel Framework       | `^10.10`       | **11.x**             | **12.x**             |
| Laravel Passport        | `^11.8`        | `^12.x`              | `^13.x`              |
| PHPUnit                 | `^10.1`        | `^11.x`              | `^11.x`              |
| AWS SDK                 | `^3.356`       | última               | última               |
| Guzzle                  | `^7.9`         | `^7.9` (OK)          | `^7.9` (OK)          |
| `lopezsoft/ubl21dian`   | `^3.1`         | revisar PHP 8.3 OK   | fork si bloqueante   |
| `mpdf/mpdf`             | `^8.1`         | `^8.2`               | revisar v9           |
| `darkaonline/l5-swagger`| `^8.6`         | `^9.x`               | `^9.x`               |
| OpenSSL en imagen Docker| 1.1.x          | **3.0+**             | **3.2+**             |

### 12.bis.4 Riesgos a auditar antes del upgrade

> 🔍 **Pre-flight check obligatorio (auditoría dedicada antes del Sprint U-1):**

1. **`lopezsoft/ubl21dian`** — librería propietaria de firma UBL DIAN.
   - Verificar compatibilidad PHP 8.3+ (ejecutar suite en PHP 8.3 con `xmlsec`).
   - Revisar si usa internamente `openssl_pkcs12_read` con flags legacy.
   - Plan B: mantener fork interno bajo `lopezsoft/ubl21dian-modern` con parches.

2. **`mpdf/mpdf`** — generación de representación gráfica DIAN.
   - v8.2 + PHP 8.3 OK. v9 introduce breaking changes en helpers tipográficos.

3. **`laravel/passport`** — autenticación OAuth2 del API.
   - v12 requiere Laravel 11. Migración de tablas `oauth_*` (siempre con `--path`).

4. **`l5-swagger` 9.x** — anotaciones OpenAPI.
   - Cambio de sintaxis menor en algunos `@OA\Property`.

5. **Otras APIs DIAN del ecosistema** (a inventariar):
   - ERPs / motores de facturación que comparten librerías.
   - Servicios PHP que aún consumen el `.p12` antiguo directamente.

### 12.bis.5 Roadmap de Modernización (post Go-Live de v3.0)

> Estos sprints **se agendan después del Sprint 5** (Go-Live PKCS#10). Mientras los certificados viejos sigan en circulación, **NO** se sube de PHP. El upgrade arranca cuando un porcentaje suficiente de clientes ya esté en PKCS#10.

#### Sprint U-1 — Auditoría y Pre-flight (1 semana)

| ID    | Tarea                                                                          | SP |
|-------|---------------------------------------------------------------------------------|----|
| U-101 | Inventario completo de APIs/servicios afectados (Certificate Manager + N APIs) | 3  |
| U-102 | Matriz de dependencias y compatibilidad PHP 8.3 / Laravel 11                   | 5  |
| U-103 | Ejecutar suite de tests en contenedor PHP 8.3-fpm + OpenSSL 3.2 (read-only)    | 3  |
| U-104 | Identificar uso residual de `openssl_pkcs12_read` con `.p12` antiguos           | 5  |
| U-105 | Definir KPI de "salud certificados modernos" (% de clientes en PKCS#10)        | 2  |
| U-106 | Diseñar estrategia *strangler fig* para `.p12` heredados (helper compat)       | 3  |

**Gate de salida:** documento `MIGRATION-READINESS-2026-XX.md` con go/no-go por servicio.

#### Sprint U-2 — Upgrade PHP 8.1 → 8.3 (2 semanas)

| ID    | Tarea                                                                          | SP |
|-------|---------------------------------------------------------------------------------|----|
| U-201 | Actualizar `Dockerfile` y `compose.yaml` → `php:8.3-fpm-alpine` + OpenSSL 3.2  | 3  |
| U-202 | Actualizar `composer.json` → `"php": "^8.3"`                                   | 1  |
| U-203 | Rector + PHPStan nivel 6 sobre `app/` (PHP 8.3 ready set)                      | 8  |
| U-204 | Fix de breaking changes: `utf8_encode`, dynamic properties, `mt_rand`, etc.    | 5  |
| U-205 | Suite completa de regresión + smoke E2E firma DIAN con cert PKCS#10            | 5  |
| U-206 | Helper de compatibilidad para `.p12` legacy residuales (carga `legacy_provider`) | 3  |
| U-207 | Despliegue canario al 10% del tráfico de producción                            | 3  |
| U-208 | Promoción al 100% + monitoreo 48h                                              | 2  |

**Helper de compatibilidad (puente):**

```php
// app/Common/LegacyOpensslProvider.php
final class LegacyOpensslProvider
{
    public static function ensureLoaded(): void
    {
        if (PHP_VERSION_ID < 80200) return;
        // PHP 8.2+ con OpenSSL 3: el legacy_provider se carga
        // vía openssl.cnf del contenedor (ver Dockerfile).
        // Este método valida y emite warning si no está disponible.
        if (!extension_loaded('openssl') || !defined('OPENSSL_VERSION_TEXT')) {
            throw new \RuntimeException('OpenSSL no disponible');
        }
        if (str_starts_with(OPENSSL_VERSION_TEXT, 'OpenSSL 3') &&
            !self::isLegacyProviderActive()) {
            app(SmartLoggerService::class)->warning(
                'OpenSSL 3.x detectado sin legacy_provider; ' .
                '.p12 antiguos podrían fallar. Migrar a PKCS#10.'
            );
        }
    }

    private static function isLegacyProviderActive(): bool
    {
        // Probe: intenta operación que requiera RC2
        $probe = @openssl_decrypt('test', 'rc2-40-cbc', 'k', 0, str_repeat('0', 8));
        return $probe !== false || openssl_error_string() === false;
    }
}
```

`Dockerfile` (fragmento clave):

```dockerfile
FROM php:8.3-fpm-alpine
# Habilitar legacy provider durante la transición
RUN echo -e "openssl_conf = openssl_init\n\n[openssl_init]\nproviders = provider_sect\n\n[provider_sect]\ndefault = default_sect\nlegacy = legacy_sect\n\n[default_sect]\nactivate = 1\n\n[legacy_sect]\nactivate = 1" > /etc/ssl/openssl.cnf
```

#### Sprint U-3 — Upgrade Laravel 10 → 11 (2 semanas)

| ID    | Tarea                                                                          | SP |
|-------|---------------------------------------------------------------------------------|----|
| U-301 | `composer require laravel/framework:^11.0 --with-all-dependencies`             | 1  |
| U-302 | Adaptar `app/Http/Kernel.php` → `bootstrap/app.php` (nuevo skeleton L11)        | 5  |
| U-303 | Migrar `app/Console/Kernel.php` → `routes/console.php` + `bootstrap/app.php`   | 3  |
| U-304 | Actualizar Passport a `^12.x` + migraciones `oauth_*` **individuales**          | 5  |
| U-305 | Adaptar middleware groups (api, web) al nuevo registro                         | 3  |
| U-306 | Actualizar `l5-swagger` a `^9.x` y revisar anotaciones rotas                   | 3  |
| U-307 | Refactor a `casts()` method en modelos (recomendación L11)                     | 3  |
| U-308 | Suite de regresión + carga                                                     | 5  |
| U-309 | Canario + Go-Live                                                               | 3  |

#### Sprint U-4 — PHP 8.4 + Laravel 12 (opcional, Q4 2026)

| ID    | Tarea                                                                          | SP |
|-------|---------------------------------------------------------------------------------|----|
| U-401 | Property hooks + asymmetric visibility audit (oportunidades de refactor)       | 3  |
| U-402 | Upgrade a Laravel 12 (cuando alcance estabilidad LTS)                          | 5  |
| U-403 | Eliminación definitiva del `legacy_provider` de OpenSSL en Docker              | 2  |
| U-404 | Validar 0 `.p12` antiguos en producción (KPI ≥ 99.5% PKCS#10)                  | 2  |

### 12.bis.6 Estrategia para las demás APIs DIAN del ecosistema

> El upgrade del Certificate Manager debe ir **acompañado** por upgrades coordinados en cada API que firma documentos para la DIAN. Recomendamos:

1. **Catálogo central** de APIs (en `docs/`) con: nombre, repo, versión PHP/Laravel, dependencia de `.p12` antiguo.
2. **Librería compartida** `lopezsoft/dian-signer` (paquete privado) que abstraiga la firma — así un solo upgrade beneficia a todas.
3. **Política de versiones alineadas**: todas las APIs DIAN deben estar a ≤ 1 minor de diferencia respecto al Certificate Manager.
4. **Pipeline CI compartido** que corra los tests del *signer* contra PHP 8.1, 8.2, 8.3, 8.4 (matrix) — bloquea regresiones.
5. **Plan de comunicación** a clientes: notificar 60 días antes del retiro del soporte `.p12` legacy, ofrecer migración asistida a PKCS#10.

### 12.bis.7 Beneficios esperados del upgrade

- 🚀 **Performance:** PHP 8.3 ofrece ~5-15% mejora vs 8.1 (JIT mejorado, optimizaciones de array).
- 🔐 **Seguridad:** PHP 8.1 sale de soporte de seguridad en **Dic 2025**. Estamos en riesgo CVE.
- 🧰 **Ecosistema:** acceso a Laravel Reverb, Pennant, Folio, Volt, Pulse (observabilidad).
- 🧪 **Testabilidad:** PHPUnit 11 + Pest 3 con paralelismo nativo.
- 💼 **Talento:** atractivo para desarrolladores modernos; los frameworks/ libs nuevas asumen PHP 8.2+.
- 📉 **Deuda técnica:** elimina un bloqueo arquitectónico que llevaba ≥ 2 años pendiente.

### 12.bis.8 Condiciones de arranque (gate)

✅ El upgrade **no inicia** hasta que se cumplan:

- [ ] Sprint 5 (Go-Live PKCS#10) cerrado al 100%.
- [ ] ≥ 70% de clientes activos ya emitiendo certificados PKCS#10.
- [ ] Helper `LegacyOpensslProvider` probado contra los `.p12` heredados representativos.
- [ ] Inventario completo de APIs DIAN del ecosistema (Sprint U-1).
- [ ] Stakeholders informados y ventana de mantenimiento aprobada.

---


## 12. ✅ Resumen Ejecutivo

| Sprint | Duración | Foco                                  | Entregable Tangible                          |
|:------:|:--------:|----------------------------------------|----------------------------------------------|
| 0      | 3 días   | Spike técnico                          | Go/No-Go documentado                         |
| 1      | 2 sem    | Cripto local + Auth OAuth1             | CSR + cliente HTTP probado                   |
| 2      | 2 sem    | Endpoints de emisión                   | Solicitud creada end-to-end                  |
| 3      | 2 sem    | Polling resiliente + FSM               | Solicitudes avanzan solas                    |
| 4      | 2 sem    | Descarga + Ensamblaje P12              | `.p12` válido firma XML DIAN                 |
| 5      | 1-2 sem  | Hardening + Go-Live                    | Producción al 100% con observabilidad        |

**Duración total estimada:** ~10-12 semanas (≈ 3 meses) con un equipo de 2 backend + QA.

### 🎁 Bonus arquitectónico (Sección 12.bis)

La migración a PKCS#10 **desbloquea** la modernización de plataforma que llevaba años represada por la incompatibilidad de los `.p12` heredados con OpenSSL 3.x / PHP 8.2+:

| Sprint  | Duración | Foco                                | Resultado                                  |
|:-------:|:--------:|--------------------------------------|--------------------------------------------|
| U-1     | 1 sem    | Auditoría pre-upgrade                | Matriz de readiness por servicio           |
| U-2     | 2 sem    | PHP 8.1 → 8.3 + OpenSSL 3.2          | Stack moderno con `legacy_provider` puente |
| U-3     | 2 sem    | Laravel 10 → 11                      | Skeleton moderno, Passport 12              |
| U-4     | 2 sem    | PHP 8.4 + Laravel 12 (opcional)      | Plataforma LTS, eliminación de legacy      |

Aplicable también a **todas las demás APIs del ecosistema** que firman documentos DIAN.

---

> 📌 **Próxima acción sugerida:** validar este roadmap en sesión de refinamiento con PO y Stakeholders; ajustar SPs según velocidad real del equipo; arrancar **Sprint 0 (Spike)** la próxima semana.

