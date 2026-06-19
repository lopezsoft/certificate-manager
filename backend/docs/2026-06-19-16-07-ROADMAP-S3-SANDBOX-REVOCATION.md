# Roadmap Arquitectónico: S3, Sandbox Interno y Revocaciones Automáticas

> **Fecha:** 2026-06-19
> **Ámbito:** `backend/` (Módulo Viafirma)
> **Estado:** 📋 **Pendiente de Autorización**

Este documento recopila las iteraciones de diseño discutidas para evolucionar el módulo de Viafirma, mejorando su resiliencia (S3), facilitando el desarrollo (Sandbox Interno) y automatizando el modelo de negocio comercial (Revocación Automática).

---

## 1. Almacenamiento en AWS S3 (Estructurado por Entornos)

Para evitar pérdida de certificados por daños en servidores físicos y garantizar alta disponibilidad, se migrará el almacenamiento local a AWS S3.

### 1.1 Diseño de Directorios Dinámicos
Para evitar colisiones entre el entorno local de los desarrolladores, pruebas (staging) y producción, la ruta de almacenamiento incluirá la variable del entorno actual (`APP_ENV`).

*   **Ruta Base:** `s3://{BUCKET_NAME}/{APP_ENV}/viafirma/`
*   **Ejemplo Producción:** `s3://mis-certificados/production/viafirma/p12/637_W4CZ1SDML.p12`
*   **Ejemplo Local:** `s3://mis-certificados/local/viafirma/p12/637_W4CZ1SDML.p12`

### 1.2 Configuración
Solo será necesario modificar el archivo `.env` sin cambiar la lógica del negocio:
```env
AWS_ACCESS_KEY_ID=tu-key
AWS_SECRET_ACCESS_KEY=tu-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=mis-certificados

VIAFIRMA_P12_DISK=s3
VIAFIRMA_P7B_DISK=s3
VIAFIRMA_P12_PATH=${APP_ENV}/viafirma/p12
VIAFIRMA_P7B_PATH=${APP_ENV}/viafirma/p7b
```

---

## 2. Implementación de Sandbox Interno (Mock)

Dado que el entorno de pruebas real de Viafirma exige intervención humana manual (lo que bloquea el desarrollo automatizado), se construirá un **Sandbox Interno** o *Mock* 100% aislado.

### 2.1 Patrón de Diseño (Strategy / Mock)
Aprovechando que la arquitectura actual ya usa la interfaz `ViafirmaClient`, crearemos una nueva implementación llamada `MockViafirmaClient`.

*   **`GuzzleViafirmaClient`:** Se usará únicamente cuando `APP_ENV=production` o `staging`.
*   **`MockViafirmaClient`:** Se inyectará automáticamente cuando `APP_ENV=local` o si se define `VIAFIRMA_SANDBOX_MODE=true`.

### 2.2 Comportamiento del Mock
El Sandbox Interno simulará las respuestas de la API de Viafirma instantáneamente:
*   Al solicitar emisión, retornará un `cod_request` ficticio (ej. `MOCK-9999X`).
*   Al consultar el estado, saltará directamente a `Generated_And_Downloaded` sin esperas.
*   Al descargar el certificado, retornará un binario P7B falso o genérico pre-creado válido para pruebas criptográficas locales.
*   **Beneficio:** Los desarrolladores podrán probar el ciclo completo de vida (crear, descargar, revocar) en segundos, sin internet y sin depender del soporte de Viafirma.

---

## 3. Revocación Comercial Automática (Modelo de Negocio)

Para aplicar la política comercial (certificados válidos comercialmente por 1 año, a pesar de que la CA los emita por 2 años), se implementará un proceso desatendido.

### 3.1 Periodo de Gracia Configurable
Se añadirá una nueva variable de entorno para controlar el tiempo extra que se le da al cliente tras cumplir el año antes de apagarle el certificado:
```env
VIAFIRMA_REVOCATION_GRACE_DAYS=15
```
*(Un valor de 15 significa que la revocación ocurrirá al día 380 desde su emisión).*

### 3.2 Lógica de Automatización (Cron Job)
1.  Se creará el job `AutoRevokeUnpaidCertificatesJob`.
2.  Se registrará en el `Kernel.php` para ejecutarse diariamente (ej. `02:00 AM`).
3.  **Criterio de Selección:** Buscará certificados que cumplan estas reglas:
    *   Estado actual `COMPLETED` o `ASSEMBLED`.
    *   La fecha de creación es menor a `now() - (365 días + VIAFIRMA_REVOCATION_GRACE_DAYS)`.
    *   *(Condición de negocio)*: Validar que no exista un pago de renovación asociado a esa empresa o certificado.
4.  **Acción:** Invocará el `RevokeCertificateUseCase` internamente marcando el motivo como `5` (Cese de Operaciones) o `4` (Sustitución). El cliente ya no podrá firmar a partir del día siguiente.
