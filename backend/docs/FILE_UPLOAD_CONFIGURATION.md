# Configuración de Carga de Archivos

## Descripción General

Este documento describe la configuración dinámica de límites para la carga de archivos en las solicitudes de certificados. La configuración permite ajustar los límites sin modificar el código, utilizando variables de entorno.

## Variables de Entorno

### Archivo `.env`

```properties
# Tamaño máximo permitido por archivo individual (en MB)
CERTIFICATE_MAX_FILE_SIZE=10

# Tamaño máximo total permitido para todos los archivos (en MB)
CERTIFICATE_MAX_TOTAL_SIZE=10

# Número máximo de archivos permitidos por solicitud
CERTIFICATE_MAX_FILES=3

# Número mínimo de archivos requeridos por solicitud
CERTIFICATE_MIN_FILES=2
```

### Descripción de Variables

| Variable | Tipo | Valor por Defecto | Descripción |
|----------|------|-------------------|-------------|
| `CERTIFICATE_MAX_FILE_SIZE` | Integer | 10 | Tamaño máximo en MB que puede tener un archivo individual |
| `CERTIFICATE_MAX_TOTAL_SIZE` | Integer | 10 | Tamaño máximo en MB que pueden sumar todos los archivos juntos |
| `CERTIFICATE_MAX_FILES` | Integer | 3 | Número máximo de archivos que se pueden adjuntar |
| `CERTIFICATE_MIN_FILES` | Integer | 2 | Número mínimo de archivos requeridos para crear una solicitud |

## Configuración en `config/certificate.php`

```php
'file_upload' => [
    'max_file_size' => env('CERTIFICATE_MAX_FILE_SIZE', 10),
    'max_total_size' => env('CERTIFICATE_MAX_TOTAL_SIZE', 10),
    'max_files' => env('CERTIFICATE_MAX_FILES', 3),
    'min_files' => env('CERTIFICATE_MIN_FILES', 2),
],
```

## Implementación en el Servicio

El servicio `CertificateRequestService` utiliza esta configuración de la siguiente manera:

```php
// Obtener configuración dinámica
$maxFileSize    = config('certificate.file_upload.max_file_size', 10);
$maxTotalSize   = config('certificate.file_upload.max_total_size', 10);
$maxFiles       = config('certificate.file_upload.max_files', 3);
$minFiles       = config('certificate.file_upload.min_files', 2);

// Convertir MB a bytes
$maxFileSizeBytes   = $maxFileSize * 1024 * 1024;
$maxTotalSizeBytes  = $maxTotalSize * 1024 * 1024;
```

## Validaciones Implementadas

### 1. Número de Archivos

- **Mínimo:** Valida que se envíen al menos `CERTIFICATE_MIN_FILES` archivos
- **Máximo:** Valida que no se supere `CERTIFICATE_MAX_FILES` archivos

### 2. Tamaño Individual

- Cada archivo se valida contra `CERTIFICATE_MAX_FILE_SIZE`
- Mensaje de error incluye el nombre del archivo y su tamaño real

### 3. Tamaño Total

- La suma de todos los archivos se valida contra `CERTIFICATE_MAX_TOTAL_SIZE`
- Mensaje de error incluye el tamaño total calculado

## Mensajes de Error

### Error: Número de archivos excedido
```
El número de archivos adjuntos supera los {max_files} soportados.
```

### Error: Archivos insuficientes
```
Debe enviar al menos {min_files} archivos adjuntos.
```

### Error: Archivo individual muy grande
```
El archivo '{nombre_archivo}' supera el tamaño máximo permitido de {max_file_size} MB (tamaño: {tamaño_real} MB).
```

### Error: Tamaño total excedido
```
El tamaño total de los archivos adjuntos supera los {max_total_size} MB permitidos. Tamaño total: {tamaño_calculado} MB
```

## Casos de Uso

### Caso 1: Configuración Actual (Por Defecto)
```properties
CERTIFICATE_MAX_FILE_SIZE=10
CERTIFICATE_MAX_TOTAL_SIZE=10
```
- ✅ 1 archivo de 10 MB
- ✅ 2 archivos de 5 MB cada uno
- ✅ 3 archivos de 3.3 MB cada uno
- ❌ 1 archivo de 11 MB (excede límite individual)
- ❌ 2 archivos de 6 MB cada uno (excede límite total)

### Caso 2: Configuración Restrictiva
```properties
CERTIFICATE_MAX_FILE_SIZE=5
CERTIFICATE_MAX_TOTAL_SIZE=10
```
- ✅ 2 archivos de 5 MB cada uno
- ✅ 3 archivos de 3 MB cada uno
- ❌ 1 archivo de 10 MB (excede límite individual de 5 MB)

### Caso 3: Configuración Flexible
```properties
CERTIFICATE_MAX_FILE_SIZE=15
CERTIFICATE_MAX_TOTAL_SIZE=20
```
- ✅ 1 archivo de 15 MB
- ✅ 2 archivos de 10 MB cada uno
- ✅ 3 archivos de 6 MB cada uno

## Cambios en el Sistema

### Archivos Modificados

1. **`.env.example`** - Plantilla con nuevas variables
2. **`config/certificate.php`** - Configuración centralizada
3. **`app/Services/CertificateRequestService.php`** - Lógica de validación refactorizada
4. **`.env`** - Valores actuales de producción/desarrollo

### Ventajas de esta Implementación

1. **Flexibilidad:** Cambios dinámicos sin modificar código
2. **Mantenibilidad:** Configuración centralizada en un solo lugar
3. **Escalabilidad:** Fácil ajuste según necesidades del negocio
4. **Mensajes Claros:** Errores descriptivos que ayudan al usuario
5. **Seguridad:** Validación robusta en múltiples niveles

## Consideraciones de Seguridad

- Los límites protegen contra ataques de denegación de servicio (DoS)
- Los valores por defecto son conservadores y seguros
- Se recomienda ajustar según la capacidad del servidor
- Monitorear el uso de disco y memoria al aumentar límites

## Aplicar Cambios en Producción

Después de modificar las variables en `.env`:

```bash
php artisan config:clear
php artisan config:cache
```

## Monitoreo y Logging

El servicio registra automáticamente:
- Intentos de carga que exceden límites
- Tamaños reales de archivos procesados
- Errores de validación

Revisar logs en `storage/logs/laravel.log` para análisis de uso.
