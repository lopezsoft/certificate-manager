# Fix: Error de Validación RUES - Identity Mismatch

**Fecha:** 2026-07-09  
**Problema:** Viafirma rechazaba solicitudes con error "Sus datos no coinciden con los encontrados en el Registro Único Empresarial y Social (RUES)"  
**Causa raíz:** El campo `identity` en el payload no coincidía con `serialNumber` en el CSR para FE-PJ

---

## 📋 Análisis del Problema

### Patrón DN Oficial de Viafirma

La documentación oficial de Viafirma define patrones DN para cada perfil:

**FE-PJ (Persona Jurídica):**
```
CN={legalNameCorp} - {departament},name={name} {lastName},serialNumber={dnAlternativo1},...
```

**FE-PN (Persona Natural):**
```
CN={name} {lastName} - {identity},serialNumber={identity},...
```

### Discrepancia Identificada

Para FE-PJ, el payload enviado a Viafirma contenía:

```json
{
    "identityType": "IDC",
    "countryCode": "CO",
    "identity": "<cédula_del_representante>",  // ❌ INCORRECTO
    "organizationType": "EXTRANJERAS",
    "csr": "base64_encoded_csr"
}
```

Pero el CSR contenía:
```
serialNumber: 901091403  // NIT de la empresa (correcto)
CN: LOPEZSOFT S.A.S - RISARALDA
O: LOPEZSOFT S.A.S
GN: LEWIS OSWALDO
SN: LOPEZ GOMEZ
```

**El problema:** 
- Campo `identity` en payload: cédula del representante (document_number)
- Campo `serialNumber` en CSR: NIT de la empresa (dni)
- Viafirma espera que coincidan para validar contra RUES

---

## ✅ Solución Implementada

### Cambio 1: `IssueCertificateUseCase.php`

**Función `resolveSubscriberIdentity()`** — Ahora devuelve:
- **Para FE-PJ:** `$cr->dni` (NIT de la empresa)
- **Para FE-PN:** `$cr->document_number` (cédula del representante)

```php
private function resolveSubscriberIdentity(CertificateRequest $cr, CertificateProfile $profile): string
{
    // Para FE-PJ: el identity es el NIT de la empresa (debe coincidir con serialNumber en CSR)
    // Para FE-PN: el identity es la cédula del representante legal
    if ($profile === CertificateProfile::FE_PJ) {
        return (string) $cr->dni;
    }
    return (string) $cr->document_number;
}
```

**Línea donde se usa (124-133):**
```php
$submitInput = new SubmitCsrInputDto(
    identityType:     $identityType,
    countryCode:      $countryCode,
    identity:         $this->resolveSubscriberIdentity($cr, $profile),  // ← Ahora recibe $profile
    raCode:           $raCode,
    codProfile:       $codProfile,
    emailCertificate: $cmd->emailCertificate,
    csrBase64:        $csrResult->base64,
    organizationType: $profile === CertificateProfile::FE_PJ ? $cmd->organizationType : null,
);
```

---

## 🔍 Por Qué Esto Resuelve el Error RUES

Viafirma valida contra RUES usando DOS fuentes de datos:

1. **El CSR** → contiene atributos X.509:
   - `serialNumber: 901091403` (NIT empresa)
   - `CN: LOPEZSOFT S.A.S - RISARALDA`
   - `O: LOPEZSOFT S.A.S`
   - etc.

2. **El Payload JSON** → contiene metadata:
   - `identity: 901091403` (AHORA CORRECTO — coincide con serialNumber)
   - `identityType: IDC`
   - `organizationType: EXTRANJERAS`

Cuando el campo `identity` en el payload coincide con `serialNumber` en el CSR, Viafirma valida exitosamente contra RUES.

---

## ✅ Verificación

### Tests
- `FePjCsrBuilderTest::test_builds_a_valid_csr_with_10_attributes` ✅ PASS
- Validación de CN: `MI COMPANIA SAS - ANTIOQUIA` (según patrón oficial)

### Patrones CN Correctos

**FE-PJ:**
```
CN = {organization} - {state}
Ej: LOPEZSOFT S.A.S - RISARALDA
```

**FE-PN:**
```
CN = {givenName} {surname} - {identity}
Ej: LEWIS OSWALDO LOPEZ GOMEZ - 1234567890
```

---

## 📝 Notas Importantes

- **CSR está correcto:** El CN del CSR SÍ debe ser `{empresa} - {departamento}` según patrón oficial de Viafirma
- **Payload ahora es correcto:** El campo `identity` ahora coincide con `serialNumber` en el CSR
- **FE-PN no se afecta:** Para Persona Natural, `identity` sigue siendo la cédula del representante (que coincide con su propio `serialNumber`)

---

## 🚀 Próximos Pasos

Para validar que la corrección funciona:

1. Crear una nueva solicitud de certificado (FE-PJ)
2. Disparar el flujo de emisión
3. Monitorear el estado en Viafirma (debe pasar validación RUES)

Si aún falla, revisar:
- Que el nombre de la empresa en BD coincida exactamente con RUES
- Que el NIT esté en formato correcto (sin espacios, sin caracteres especiales)
- Que el representante legal registrado en RUES sea el correcto

