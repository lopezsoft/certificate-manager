# Troubleshooting: Error de RUES en Viafirma

**Fecha:** 2026-07-09  
**Error:** "Sus datos no coinciden con los encontrados en el RUES"

---

## 🔴 ¿Qué significa este error?

Viafirma valida los datos de tu solicitud contra el **RUES** (Registro Único Empresarial y Social). Si hay discrepancias, rechaza la solicitud.

Los datos validados por RUES son:
- **NIT/Cédula de la empresa** (debe ser exacto, sin dígito verificador si no lo requiere)
- **Nombre de la empresa** (tal como está registrado en el RUES)
- **Tipo de organización** (RM, PROP, ESAL, etc.)
- **Nombre del representante legal** (debe coincidir con quien está registrado)

---

## ✅ Pasos para Diagnosticar

### 1. Obtén el ID de tu solicitud
```sql
SELECT id, uuid, company_name, dni, document_number, legal_rep_first_name, legal_rep_last_name 
FROM certificate_requests 
ORDER BY created_at DESC 
LIMIT 5;
```

### 2. Ejecuta el comando de diagnóstico
```bash
php artisan debug:viafirma-payload {ID}
```

Ejemplo:
```bash
php artisan debug:viafirma-payload 636
```

### 3. Revisa el checklist que arroja el comando
El comando te mostrará:
- ✅ Datos capturados
- ⚠️ Problemas detectados
- 💡 Recomendaciones

---

## 🔧 Causas Comunes y Soluciones

### ❌ Problema 1: NIT con dígito verificador incluido

**Error:** El campo `dni` contiene un dígito verificador que no debería estar

**Solución:**
```sql
-- Busca NITs que terminan en -0, -1, etc.
SELECT id, dni FROM certificate_requests WHERE dni LIKE '%-_';

-- El RUES espera formato sin guión: 900455420 (no 900455420-8)
-- Verifica en tu formulario que se capture solo el NIT sin dígito
```

**En el formulario:**
- NIT debe capturarse sin separador: `900455420`
- El dígito verificador (`dv`) es un campo separado

---

### ❌ Problema 2: Nombre de la empresa incorrecto

**Error:** El `company_name` no coincide exactamente con lo registrado en RUES

**Solución:**
```bash
# Accede al RUES oficial en:
# https://www.rues.org.co (búsqueda pública)

# Busca tu NIT y copia el nombre EXACTO, incluyendo:
# - Espacios
# - Mayúsculas/minúsculas (aunque RUES es case-insensitive)
# - Caracteres especiales (Ñ, acentos, etc.)
```

**Ejemplo de error:**
- ❌ Enviado: "MI EMPRESA SA"
- ✅ RUES: "MI EMPRESA S.A.S."

---

### ❌ Problema 3: Tipo de organización incorrecto (PJ)

**Error:** El `entityDocumentType` seleccionado no existe en Viafirma

**Solución:**

Viafirma soporta estos tipos para Persona Jurídica:
```
RM   = Registro Mercantil
PROP = Propietario
RUNEOL = RUNEOL
RNT  = Registro Nacional de Turismo
ESAL = Entidad Sin Ánimo de Lucro
ESOL = ESOL
JUEGOS = Juegos de Azar
EXTRANJERAS = Empresa Extranjera
```

**Verifica tu selección:**
```sql
SELECT id, code, description FROM entity_document_types WHERE active = true ORDER BY code;
```

---

### ❌ Problema 4: Nombre del representante legal incompleto

**Error:** `legal_rep_first_name` o `legal_rep_last_name` están vacíos

**Solución:**
```sql
-- Busca solicitudes con nombres incompletos
SELECT id, legal_rep_first_name, legal_rep_last_name, legal_rep_email 
FROM certificate_requests 
WHERE legal_rep_first_name IS NULL OR legal_rep_last_name IS NULL;

-- En tu formulario, ambos campos deben ser REQUERIDOS para Viafirma
```

**En la validación (CreateCertificateRequestFormRequest):**
```php
'legal_rep_first_name' => 'required_if:provider,viafirma',
'legal_rep_last_name'  => 'required_if:provider,viafirma',
```

---

### ❌ Problema 5: Documento del representante inválido

**Error:** El `document_number` tiene caracteres no numéricos o formato incorrecto

**Solución:**
```sql
-- Busca documentos con caracteres no numéricos
SELECT id, document_number FROM certificate_requests 
WHERE document_number NOT REGEXP '^[0-9]+$';

-- El documento debe ser SOLO números: 1234567890
-- No debe incluir: guiones, puntos, letras, espacios
```

---

### ❌ Problema 6: País no es Colombia

**Error:** El `country_id` no es Colombia (ID = 45)

**Solución:**
```sql
-- Verifica el país de tu solicitud
SELECT id, cr.country_id, c.name, c.abbreviation_A2 
FROM certificate_requests cr
JOIN countries c ON cr.country_id = c.id
WHERE c.abbreviation_A2 != 'CO';

-- Viafirma RA Colombia SOLO emite para CO
-- Si es otro país, necesitas una RA diferente
```

---

## 📊 Query de Diagnóstico Completo

Ejecuta esta query para revisar TODAS tus solicitudes problemáticas:

```sql
SELECT 
    cr.id,
    cr.uuid,
    cr.company_name,
    cr.dni,
    CONCAT(cr.legal_rep_first_name, ' ', cr.legal_rep_last_name) AS rep_legal,
    cr.document_number,
    cr.legal_rep_email,
    c.abbreviation_A2,
    edt.code AS doc_type,
    vcr.cod_request,
    vcs.remote_status
FROM certificate_requests cr
LEFT JOIN countries c ON cr.country_id = c.id
LEFT JOIN entity_document_types edt ON cr.entity_document_type_id = edt.id
LEFT JOIN viafirma_certificate_requests vcr ON cr.id = vcr.certificate_request_id
LEFT JOIN viafirma_certificate_request_states vcs ON vcr.id = vcs.viafirma_certificate_request_id
WHERE cr.id = {TU_ID_AQUI}
ORDER BY cr.created_at DESC;
```

---

## 🚀 Próximos Pasos

1. **Ejecuta el comando:** `php artisan debug:viafirma-payload {ID}`
2. **Copia el checklist** que te muestra
3. **Valida cada punto** contra el RUES oficial
4. **Corrije los datos** en tu BD o formulario
5. **Intenta nuevamente** con una nueva solicitud

---

## 📞 Si el error persiste

Contacta a Viafirma con:
- **cod_request:** (en la respuesta anterior)
- **fecha del error:** (timestamp)
- **datos enviados:** (output del comando debug)

Email: **ecd@viafirma.com**
