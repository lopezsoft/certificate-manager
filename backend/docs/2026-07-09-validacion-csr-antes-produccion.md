# Validación del CSR Antes de Ir a Producción

**Fecha:** 2026-07-09  
**Objetivo:** Verificar que el contenido del CSR (Certificate Signing Request) sea correcto antes de enviarlo a Viafirma

---

## 🔐 ¿Qué es el CSR?

El **CSR (Certificate Signing Request)** es un archivo PKCS#10 que contiene:
- La **clave pública** generada localmente
- Los **atributos X.509** (CN, O, OU, C, ST, L, emailAddress, etc.)
- La **firma de la clave privada** (para demostrar que tienes la clave privada)

Es lo que se envía a Viafirma para que genere el certificado.

---

## ✅ Herramientas para Validar el CSR

### 1. Comando: Ver el contenido del CSR

```bash
php artisan debug:viafirma-csr {VIAFIRMA_CERTIFICATE_REQUEST_ID}
```

Este comando:
- Decodifica el base64 del CSR
- Parsea su contenido con OpenSSL
- Muestra todos los atributos X.509
- Valida que sean correctos

**Ejemplo:**
```bash
php artisan debug:viafirma-csr 5
```

**Output esperado:**
```
═════════════════════════════════════════════════════════════
ANÁLISIS DEL CSR (Certificate Signing Request)
═════════════════════════════════════════════════════════════

📋 INFORMACIÓN DEL TRÁMITE:
  • ID Viafirma: 5
  • Código de Solicitud: D4AZEQQG6
  • Perfil: FE-PN (Persona Natural)
  • Estado Interno: SUBMITTED
  • Estado Remoto: accreditation

🏢 DATOS DE LA EMPRESA EN LA SOLICITUD:
  • Nombre: MI EMPRESA S.A.S.
  • NIT: 900455420 (DV: 8)
  • Representante: JUAN PABLO PÉREZ GÓMEZ
  • Email: juan@empresa.com
  • País: CO
  • Departamento: BOGOTA D.C.
  • Ciudad: Bogotá
  • Dirección: Carrera 7 # 71-21

🔐 ANÁLISIS DEL CSR:
[OUTPUT DE OPENSSL CON TODOS LOS ATRIBUTOS]

✅ VALIDACIÓN DE ATRIBUTOS X.509:
  ✅ CN (Common Name): JUAN PABLO PÉREZ GÓMEZ
  ✅ O (Organization): MI EMPRESA S.A.S.
  ✅ OU (Organization Unit): FACTURACION
  ✅ C (Country): CO
  ✅ ST (State/Department): BOGOTA D.C.
  ✅ L (Locality): Bogotá
  ✅ emailAddress: juan@empresa.com
  ✅ Firma del CSR: VÁLIDA

💾 PARA ANÁLISIS MANUAL:
  CSR guardado en: /storage/csr-debug-5.csr
  Puedes abrirlo con OpenSSL:
    openssl req -in /storage/csr-debug-5.csr -noout -text
```

---

### 2. Clase Validadora: Validar CSR Programáticamente

```php
use App\Services\Certificate\CsrValidator;

$validation = CsrValidator::validate($csrPem);

if (!$validation['valid']) {
    echo "Errores encontrados:";
    foreach ($validation['errors'] as $error) {
        echo "  ❌ {$error}\n";
    }
}

foreach ($validation['warnings'] as $warning) {
    echo "  ⚠️  {$warning}\n";
}

echo "Atributos encontrados:";
foreach ($validation['attributes'] as $attr => $value) {
    echo "  • {$attr}: {$value}\n";
}
```

---

## 🔍 Qué Verificar Antes de Producción

### ✅ Estructura del CSR
- [ ] Es un PKCS#10 válido (formato PEM con headers BEGIN/END)
- [ ] Contiene una clave pública
- [ ] Está firmado con la clave privada (Signature ok)
- [ ] El algoritmo de firma es soportado (RSA 2048, RSA 4096)

### ✅ Atributos Requeridos
- [ ] **CN (Common Name):** Nombre del representante legal completo
  - Para **PJ:** "JUAN PABLO PÉREZ GÓMEZ"
  - Para **PN:** "JUAN PABLO PÉREZ GÓMEZ"

- [ ] **O (Organization):** Nombre de la empresa
  - "MI EMPRESA S.A.S."
  - Debe coincidir EXACTAMENTE con lo que está en RUES

- [ ] **C (Country):** Código de país
  - Debe ser "CO" (Colombia)

- [ ] **L (Locality):** Ciudad
  - "Bogotá" o "BOGOTA D.C."

- [ ] **ST (State):** Departamento
  - "BOGOTA D.C." o según configuración

- [ ] **emailAddress:** Email del representante legal
  - "juan@empresa.com"
  - Debe ser válido

### ✅ Atributos Opcionales (pero recomendados)
- [ ] **OU (Organization Unit):** Unidad organizativa
  - Para FE-PJ: "FACTURACION"
  - Para FE-PN: Puede omitirse

- [ ] **STREET:** Dirección física
  - "Carrera 7 # 71-21"

### ❌ Problemas Comunes

| Problema | Causa | Solución |
|----------|-------|----------|
| CN tiene caracteres especiales | Nombre mal capturado | Validar nombres sin acentos/ñ |
| O no coincide con RUES | Nombre incorrecto en BD | Actualizar según RUES oficial |
| C no es CO | País incorrecto | Verificar country_id = 45 |
| Firma inválida | Clave privada corrupta | Regenerar CSR desde cero |
| emailAddress vacío | Email no capturado | Rellenar legal_rep_email |

---

## 🚀 Flujo Pre-Producción

### Paso 1: Crear una solicitud de prueba

```sql
-- En tu ambiente de DEV/STAGING
INSERT INTO certificate_requests (
    company_id, city_id, country_id, identity_document_id, type_organization_id,
    document_number, legal_rep_first_name, legal_rep_last_name, legal_rep_email,
    company_name, dni, dv, address, life
) VALUES (
    1,
    149,                                    -- Bogotá
    45,                                     -- Colombia
    1,                                      -- Cédula de Ciudadanía
    2,                                      -- Persona Natural
    '1234567890',                           -- Cédula del representante
    'JUAN PABLO',                           -- Primer nombre
    'PÉREZ GÓMEZ',                          -- Apellidos
    'juan@empresa.com',                     -- Email
    'MI EMPRESA S.A.S.',                   -- Nombre empresa
    '900455420',                            -- NIT empresa
    8,                                      -- Dígito verificador
    'Carrera 7 # 71-21 Bogotá',             -- Dirección
    1                                       -- Vigencia: 1 año
);
```

### Paso 2: Disparar la emisión

```bash
# Vía API
POST /api/v1/certificate-request/{id}/issue
Content-Type: application/json
{
    "email_certificate": "juan@empresa.com",
    "provider": "viafirma"
}

# O vía Artisan
php artisan viafirma:issue-certificate {certificate_request_id}
```

### Paso 3: Esperar a que se genere el CSR

```bash
# Monitorear el estado
php artisan debug:viafirma-csr {viafirma_certificate_request_id}

# El CSR se genera cuando internal_state pasa de DRAFT a CSR_GENERATED
```

### Paso 4: Validar el CSR

```bash
# Ver contenido completo
php artisan debug:viafirma-csr {viafirma_certificate_request_id}

# Verificar que TODOS los atributos sean correctos
# ✅ CN, O, C, L, emailAddress
# ⚠️  OU (si es FE-PJ)
```

### Paso 5: Revisar en RUES

```
1. Accede a https://www.rues.org.co
2. Busca por NIT: 900455420
3. Verifica:
   - Nombre empresa: "MI EMPRESA S.A.S."
   - Representante legal: "JUAN PABLO PÉREZ GÓMEZ"
   - Tipo organización
```

### Paso 6: Comparar CSR con RUES

```bash
# Imprime la validación lado a lado

CSR (lo que se envía)          │ RUES (registro oficial)
──────────────────────────────┼───────────────────────────
CN: JUAN PABLO PÉREZ GÓMEZ    │ Representante: JUAN PABLO PÉREZ GÓMEZ ✅
O: MI EMPRESA S.A.S.          │ Nombre: MI EMPRESA S.A.S. ✅
NIT: 900455420                │ NIT: 900455420 ✅
```

---

## 📊 Query SQL para Revisar CSR de Múltiples Solicitudes

```sql
SELECT 
    vcr.id,
    vcr.cod_request,
    cr.company_name,
    cr.legal_rep_first_name,
    cr.legal_rep_last_name,
    cr.legal_rep_email,
    vcr.profile_type,
    vcs.internal_state,
    vcs.remote_status,
    CHAR_LENGTH(vcs.csr_pem) as csr_size,
    IF(vcs.csr_pem IS NOT NULL, 'Sí', 'No') as tiene_csr,
    vcs.csr_fingerprint
FROM viafirma_certificate_requests vcr
JOIN viafirma_certificate_request_states vcs ON vcr.id = vcs.viafirma_certificate_request_id
JOIN certificate_requests cr ON vcr.certificate_request_id = cr.id
WHERE vcr.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY vcr.created_at DESC;
```

---

## 🔧 Exportar CSR para Análisis Manual

El comando `debug:viafirma-csr` guarda el CSR en:
```
/storage/csr-debug-{id}.csr
```

Puedes analizarlo localmente con OpenSSL:

```bash
# Descargar el archivo
scp servidor:/storage/csr-debug-5.csr ./

# Examinar el CSR
openssl req -in csr-debug-5.csr -noout -text

# Examinar información de la clave pública
openssl req -in csr-debug-5.csr -noout -pubkey

# Verificar la firma
openssl asn1parse -in csr-debug-5.csr
```

---

## ⚠️ Diferencias entre Ambientes

| Aspecto | DEV/STAGING | PRODUCCIÓN |
|---------|-------------|------------|
| Cliente Viafirma | MockViafirmaClient | GuzzleViafirmaClient (real) |
| Validación CSR | No afecta | Crítica — RUES valida |
| Tipo Organización | Flexible | Debe coincidir RUES |
| RUES | Simulado | Verificación real |

**Recomendación:** Valida el CSR localmente con `CsrValidator` ANTES de enviarlo a Viafirma en producción.

---

## 📞 Si Sigue Fallando el RUES

1. **Ejecuta:** `php artisan debug:viafirma-csr {id}`
2. **Compara** atributos X.509 con lo que está en RUES oficial
3. **Identifica** qué atributo no coincide
4. **Ajusta** los datos en `certificate_requests`
5. **Regenera** la solicitud con datos correctos

Si es un error sistemático (siempre falla), probablemente hay un mapeo incorrecto en el código de transformación de datos a CSR.
