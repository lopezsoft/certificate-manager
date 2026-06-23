# Centralización de Almacenamiento de Certificados Digitales

**Fecha:** 22 de Junio de 2026  
**Versión:** 1.0  
**Estado:** Plan de Implementación

---

## 1. Contexto y Motivación

### Situación Actual
- Archivos dispersos en múltiples ubicaciones:
  - P7B: `viafirma/p7b/`
  - P12: `viafirma/p12/`
  - Otros: ubicaciones variadas
- Llaves privadas: KeyVault (cifrado)
- Metadatos: `file_managers` (sin centralización)

### Nuevo Modelo de Negocio
- Archivos se guardan en **AWS S3** (no se purgan)
- Permitir descargas futuras de certificados
- Trazabilidad completa del ciclo de vida
- Cumplimiento normativo (auditoría, seguridad)

---

## 2. Objetivo General

**Centralizar todos los archivos relacionados con certificados bajo el `base_path` de `certificate_requests`**

Estructura esperada:
```
{base_path}/
├── {certificate_request_id}_{cod_request}.zip          (P12 comprimido)
├── {certificate_request_id}_{cod_request}.p7b          (Cadena de certificados)
└── [otros archivos relacionados]
```

---

## 3. Cambios en `file_managers`

### Nuevos tipos de documento (`document_type`)

| Tipo | Descripción | Ejemplo |
|------|-------------|---------|
| `CERTIFICATE` | P12 comprimido (producto final) | `637_W4CZ1SDML.zip` |
| `P7B_CERTIFICATE` | Cadena de certificados (intermediario técnico) | `637_W4CZ1SDML.p7b` |
| `PRIVATE_KEY` | Referencia a llave privada en KeyVault | `vault://key-ref-12345` |
| `ATTACHED` | Documentos adjuntos por usuario (DNI, RUT, etc.) | `documento_identidad.pdf` |

### Estructura de registros en `file_managers`

```php
// Ejemplo 1: P12 comprimido
[
    'certificate_request_id' => 1,
    'file_path' => 'companies/1/2026/06/9010914032/637_W4CZ1SDML.zip',
    'file_name' => '637_W4CZ1SDML.zip',
    'extension_file' => 'zip',
    'mime_type' => 'application/zip',
    'document_type' => 'CERTIFICATE',
    'file_size' => 2048,
    'status' => 'COMPLETED',
    'created_at' => '2026-06-22 10:30:00'
]

// Ejemplo 2: P7B
[
    'certificate_request_id' => 1,
    'file_path' => 'companies/1/2026/06/9010914032/637_W4CZ1SDML.p7b',
    'file_name' => '637_W4CZ1SDML.p7b',
    'extension_file' => 'p7b',
    'mime_type' => 'application/pkcs7-mime',
    'document_type' => 'P7B_CERTIFICATE',
    'file_size' => 1024,
    'status' => 'COMPLETED',
    'created_at' => '2026-06-22 10:25:00'
]

// Ejemplo 3: Referencia a llave privada
[
    'certificate_request_id' => 1,
    'file_path' => 'vault://key-vault-ref-abc123xyz',
    'file_name' => 'private_key_reference',
    'extension_file' => 'key',
    'mime_type' => 'application/x-pkcs12-key',
    'document_type' => 'PRIVATE_KEY',
    'file_size' => 0,  // No aplica para referencias
    'status' => 'ACTIVE',
    'created_at' => '2026-06-22 10:20:00'
]
```

---

## 4. Cambios en Archivos de Código

### 4.1 RedownloadCertificateUseCase.php ✅ (Ya implementado)

**Cambios:**
- ✅ Validar que `certificate_requests.base_path` esté configurado
- ✅ Crear directorio si no existe
- ✅ Guardar ZIP bajo `base_path`
- ✅ Registrar en `file_managers` con `document_type = 'CERTIFICATE'`
- ✅ Manejar errores de metadatos

**Flujo:**
```
1. Validar base_path
2. Crear directorio si no existe
3. Ensamblar P12 en memoria
4. Comprimir en ZIP
5. Guardar en {base_path}/{certificate_request_id}_{cod_request}.zip
6. Registrar en file_managers
7. Guardar PIN en KeyVault
```

---

### 4.2 AssembleP12Job.php ✅ (Ya implementado)

**Cambios realizados:**
- ✅ Usar `$certificateRequest->base_path` para guardar ZIP
- ✅ Crear directorio si no existe
- ✅ Guardar ZIP con P12 comprimido
- ✅ Registrar en `file_managers` con `document_type = 'CERTIFICATE'`
- ✅ Registrar referencia de llave privada con `document_type = 'PRIVATE_KEY'`

**Pseudocódigo:**
```php
$basePath = $certificateRequest->base_path;
if (!Storage::disk($disk)->exists($basePath)) {
    Storage::disk($disk)->makeDirectory($basePath, 0755, true);
}

// Guardar ZIP
$zipFilename = $basePath . '/' . "{$entity->certificate_request_id}_{$entity->cod_request}.zip";
// ... crear ZIP ...

// Registrar en file_managers
FileManager::create([
    'certificate_request_id' => $certificateRequest->id,
    'file_path' => $zipFilename,
    'document_type' => 'CERTIFICATE',
    // ... otros campos ...
]);

// Registrar referencia de llave privada
FileManager::create([
    'certificate_request_id' => $certificateRequest->id,
    'file_path' => 'vault://' . $state->key_vault_ref,
    'document_type' => 'PRIVATE_KEY',
    // ... otros campos ...
]);
```

---

### 4.3 DownloadP7bJob.php ✅ (Ya implementado)

**Cambios realizados:**
- ✅ Usar `$certificateRequest->base_path` para guardar P7B
- ✅ Crear directorio si no existe
- ✅ Registrar en `file_managers` con `document_type = 'P7B_CERTIFICATE'`

**Pseudocódigo:**
```php
$basePath = $certificateRequest->base_path;
if (!Storage::disk($disk)->exists($basePath)) {
    Storage::disk($disk)->makeDirectory($basePath, 0755, true);
}

$p7bFilename = $basePath . '/' . "{$entity->certificate_request_id}_{$entity->cod_request}.p7b";
Storage::disk($disk)->put($p7bFilename, $p7bBinary);

FileManager::create([
    'certificate_request_id' => $certificateRequest->id,
    'file_path' => $p7bFilename,
    'document_type' => 'P7B_CERTIFICATE',
    // ... otros campos ...
]);
```

---

### 4.4 PurgeExpiredKeysJob.php ✅ (Ya implementado)

**Cambios realizados:**
- ❌ Eliminar lógica de purga de KeyVault (ya no se purgan llaves)
- ✅ Agregar lógica de limpieza de archivos
- ✅ Purgar archivos de certificados ya vencidos (`expiration_date < now()`)
- ✅ Purgar archivos de certificados revocados/marcados como DELETE
- ✅ Actualizar `file_managers` marcando como `DELETED`
- ✅ Mantener auditoría (logs detallados)
- ✅ Mantener referencia de llave privada (NO eliminar)

**Pseudocódigo:**
```php
// Criterios de purga:
// 1. Certificados ya vencidos (expiration_date < now())
// 2. Certificados revocados o marcados como DELETE (request_status = 'DELETE')

$candidates = ViafirmaCertificateRequestState::query()
    ->where('internal_state', InternalState::COMPLETED->value)
    ->whereHas('certificateRequest', function ($q) {
        $q->where(function ($q2) {
            $q2->where('expiration_date', '<', now())
               ->orWhere('request_status', 'DELETE');
        });
    })
    ->get();

foreach ($candidates as $state) {
    // Eliminar P7B
    if ($state->p7b_storage_path && Storage::disk($disk)->exists($state->p7b_storage_path)) {
        Storage::disk($disk)->delete($state->p7b_storage_path);
        FileManager::where('file_path', $state->p7b_storage_path)
            ->update(['status' => 'DELETED']);
    }
    
    // Eliminar ZIP (P12 comprimido)
    if ($state->p12_storage_path && Storage::disk($disk)->exists($state->p12_storage_path)) {
        Storage::disk($disk)->delete($state->p12_storage_path);
        FileManager::where('file_path', $state->p12_storage_path)
            ->update(['status' => 'DELETED']);
    }
    
    // Mantener referencia de llave privada (NO eliminar)
    // Mantener auditoría en file_managers
    
    // Registrar en logs
    $logger->info('viafirma.purge.files_deleted', [
        'viafirma_id' => $state->viafirma_certificate_request_id,
        'p7b_path' => $state->p7b_storage_path,
        'p12_path' => $state->p12_storage_path,
    ]);
}
```

---

## 5. Migración de Datos Existentes (Desarrollo)

### Fase 1: Backup
```bash
# Respaldar archivos actuales
cp -r storage/viafirma/p7b storage/viafirma/p7b.backup
cp -r storage/viafirma/p12 storage/viafirma/p12.backup
```

### Fase 2: Migración
```sql
-- Mover archivos a base_path
-- Actualizar file_managers con nuevas rutas
-- Actualizar viafirma_certificate_request_states
```

### Fase 3: Validación
```bash
# Verificar integridad de archivos
# Verificar registros en file_managers
# Verificar referencias en KeyVault
```

---

## 6. Seguridad y Cumplimiento

### Principios
- ✅ Llaves privadas: NUNCA en texto plano, solo referencias en KeyVault
- ✅ Trazabilidad: Todos los archivos registrados en `file_managers`
- ✅ Auditoría: Registro completo del ciclo de vida
- ✅ Cumplimiento: PCI-DSS, ISO 27001, normativas locales

### Estructura de referencias
```
Archivo físico: {base_path}/{certificate_request_id}_{cod_request}.zip
Referencia en file_managers: file_path = '{base_path}/{certificate_request_id}_{cod_request}.zip'
Llave privada: KeyVault (cifrado)
Referencia de llave: file_managers con document_type = 'PRIVATE_KEY', file_path = 'vault://key-ref-xxx'
```

---

## 7. Beneficios

| Beneficio | Descripción |
|-----------|-------------|
| **Centralización** | Todos los archivos bajo `base_path` |
| **Trazabilidad** | Registro completo en `file_managers` |
| **Auditoría** | Saber qué archivo, cuándo, con qué llave |
| **Escalabilidad** | Fácil agregar nuevos tipos de archivos |
| **Seguridad** | Llaves privadas cifradas en KeyVault |
| **Recuperación** | Descargas futuras sin regenerar |
| **Cumplimiento** | Normativas de seguridad y auditoría |

---

## 8. Próximos Pasos

1. ✅ Documentar plan (este archivo)
2. ✅ Implementar cambios en AssembleP12Job.php
3. ✅ Implementar cambios en DownloadP7bJob.php
4. ✅ Actualizar PurgeExpiredKeysJob.php
5. ✅ Crear script de migración (MigrateCertificateFilesToBasePath.php)
6. ⏳ Ejecutar migración de datos existentes
7. ⏳ Validar integridad
8. ⏳ Desplegar a producción

## 9. Ejecución del Script de Migración

### Modo DRY-RUN (Simular sin cambios)
```bash
php artisan migrate:certificate-files-to-base-path --dry-run
```

### Modo REAL (Ejecutar migración)
```bash
php artisan migrate:certificate-files-to-base-path
```

### Rollback (Revertir migración)
```bash
php artisan migrate:certificate-files-to-base-path --rollback
```

### Salida esperada:
```
═══════════════════════════════════════════════════════════
Migración de Archivos de Certificados a Base Path
═══════════════════════════════════════════════════════════

📋 Fase 1: Validación...
  ✓ Validando certificados con base_path configurado...
  ✓ Todos los certificados tienen base_path configurado

📦 Fase 2: Migrando archivos...
  Procesando 42 certificados...
  ✓ P7B migrado: viafirma/p7b/ABC123.p7b → companies/1/2026/06/9010914032/637_W4CZ1SDML.p7b
  ✓ P12 comprimido y migrado: viafirma/p12/ABC123.p12 → companies/1/2026/06/9010914032/637_W4CZ1SDML.zip

📝 Fase 3: Actualizando file_managers...

═══════════════════════════════════════════════════════════
📊 Resumen de Migración
═══════════════════════════════════════════════════════════
  ✓ Certificados migrados: 42
  ✗ Errores: 0

✅ Migración completada exitosamente
```

## 10. Verificación Post-Migración

Después de ejecutar la migración, verificar:

1. **Integridad de archivos:**
   ```bash
   # Verificar que los archivos existen en las nuevas rutas
   ls -la storage/companies/*/2026/*/*/
   ```

2. **Registros en file_managers:**
   ```sql
   SELECT * FROM file_managers 
   WHERE document_type IN ('CERTIFICATE', 'P7B_CERTIFICATE', 'PRIVATE_KEY')
   ORDER BY created_at DESC LIMIT 10;
   ```

3. **Rutas en viafirma_certificate_request_states:**
   ```sql
   SELECT id, p7b_storage_path, p12_storage_path 
   FROM viafirma_certificate_request_states 
   WHERE internal_state = 'COMPLETED' LIMIT 10;
   ```

4. **Limpiar archivos antiguos (opcional):**
   ```bash
   # Después de verificar que todo está bien
   rm -rf storage/viafirma/p7b/
   rm -rf storage/viafirma/p12/
   ```

---

## 9. Notas Técnicas

### Convención de rutas
```
{base_path}/{certificate_request_id}_{cod_request}.{extension}

Ejemplo:
companies/1/2026/06/9010914032/637_W4CZ1SDML.zip
companies/1/2026/06/9010914032/637_W4CZ1SDML.p7b
```

### Tipos MIME
- ZIP: `application/zip`
- P7B: `application/pkcs7-mime`
- P12: `application/x-pkcs12`
- Llave privada: `application/x-pkcs12-key`

### Permisos de directorio
```php
Storage::disk($disk)->makeDirectory($basePath, 0755, true);
```

---

**Documento creado:** 2026-06-22 23:23:39  
**Responsable:** Arquitecto Cloud / FullStack Engineer
