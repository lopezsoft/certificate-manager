# Analisis del Flujo Sandbox — Proveedor mail (No Viafirma)

**Fecha:** 2026-07-19 21:44 (hora Colombia, UTC-5)
**Version:** 4.1 (DEFINITIVO)
**Alcance:** El UNICO mock en APP_ENV=sandbox es la generacion automatica del ZIP/P12 protegido.
Todo el resto del flujo es identico a produccion.

---

## 1. Principio Fundamental (Correccion Final)

El flujo del proveedor mail en sandbox es **IDENTICO a produccion** en todos sus aspectos:
- El correo a la CA se **ENVIA REAL**
- Las notificaciones al cliente son **REALES**
- Los webhooks son **REALES**
- El estado PROCESSING se actualiza **REAL**
- Los logs son **REALES**

**La UNICA diferencia:**
En produccion, la CA recibe el correo y **manualmente** genera el certificado P12, lo comprime
en un archivo ZIP protegido con contraseña (NIT+DV o NIT) y lo entrega al administrador.
En APP_ENV=sandbox, el sistema **automaticamente** genera un P12 de prueba, lo comprime en
un ZIP protegido con la misma lógica de contraseña y completa el ciclo.

---

## 2. Flujo Completo: Produccion vs Sandbox

### Produccion

    POST /certificate-request/{id}/issue
              |
              v
    MailIssuanceProvider::issue()
              |
              v
    CertificateRequestMailService::sendMail()
         |-- DB: request_status = PROCESSING + ChangeHistory
         |-- Mail::to(CA)->queue(SendMail)  <- correo REAL a la CA
         v
    [=== PROCESO MANUAL EXTERNO ===]
         La CA genera el P12, lo comprime en ZIP protegido con NIT/NIT+DV
         El admin recibe el ZIP y lo sube al sistema
         El admin actualiza el estado a PROCESSED

### Sandbox (APP_ENV=sandbox)

    POST /certificate-request/{id}/issue
              |
              v
    MailIssuanceProvider::issue()  <- MISMA CLASE, SIN CAMBIOS INTERNOS
              |
              v
    CertificateRequestMailService::sendMail()
         |-- Correo REAL a la CA y a soporte
         v
    [Correo enviado exitosamente]
              | <- SOLO EN SANDBOX: se despacha adicionalmente
              v
    MockMailCaResponseJob::dispatch(delay: 30s)
         |-- Genera par RSA-2048 y X.509 auto-firmado
         |-- Ensambla P12
         |-- Crea ZIP PROTEGIDO con contraseña (DNI + DV o DNI)
         |-- KeyVault::store(exportPin)
         |-- Actualiza estado a PROCESSED y lanza evento (notificaciones REALES)

---

## 3. El Cambio de Codigo: Minimo y Quirurgico

### MailIssuanceProvider.php (modificacion)

    $response = $this->mailService->sendMail($synthetic, $request->certificateRequestId);
    $payload  = json_decode((string) $response->getContent(), true) ?: [];
    $status   = $response->getStatusCode();
    
    if ($status >= 400) { /* manejo error */ }

    // === SANDBOX ONLY: simular respuesta de la CA ===
    if (app()->environment('sandbox')) {
        \App\Jobs\Certificate\MockMailCaResponseJob::dispatch(
            $request->certificateRequestId
        )->delay(now()->addSeconds(30));
    }
    // === FIN SANDBOX ONLY ===

    return new IssuanceResult(...);

---

## 4. MockMailCaResponseJob — Replica de AssembleP12Job + ZIP protegido

### 4.1 Generacion del P12 auto-firmado
Identico a producción mediante `OpenSslCryptoService`.

### 4.2 Creacion del ZIP protegido (Lógica existente para Mail)
La CA protege el ZIP devuelto con el número de identificación (NIT+DV o NIT). El mock
debe replicar esto exactamente, tal como espera `FileStorageService::processZipFile()`.

    $disk    = $pathResolver->disk();
    $zipPath = Storage::path($zipFilename);
    $zipDir  = dirname($zipPath);
    if (!is_dir($zipDir)) { mkdir($zipDir, 0755, true); }

    // 1. Determinar contraseña (método que hoy existe para ello)
    $password = $cr->dni;
    if ($cr->type_organization_id == 1) { // Juridica
        $password = "{$cr->dni}{$cr->dv}";
    }

    // 2. Crear ZIP y proteger con contraseña
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->setPassword($password);
    $zip->addFromString($p12Filename, $p12Binary);
    $zip->setEncryptionName($p12Filename, ZipArchive::EM_AES_256);
    $zip->close();

    if ($disk !== 'local') {
        Storage::disk($disk)->put($zipFilename, file_get_contents($zipPath));
        @unlink($zipPath);
    }

### 4.3 PIN del P12 y persistencia
El P12 interno también está protegido (CSPRNG 32 chars).

    $pinRef = $vault->store($exportPin, ['type' => 'p12_pin', 'request_id' => $cr->id]);
    $cr->pin = $exportPin;
    $cr->request_status = CertificateRequestStatusEnum::PROCESSED->value;
    $cr->save();

---

## 5. Tabla de Archivos

### Crear
- `app/Services/Certificate/SelfSignedCertificateGenerator.php`
- `app/Jobs/Certificate/MockMailCaResponseJob.php`

### Modificar (4 lineas)
- `app/Services/Certificate/Providers/MailIssuanceProvider.php`

**Fin del informe v4.1 — DEFINITIVO**