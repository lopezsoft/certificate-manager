# Protección de Endpoints de Analytics

## Resumen

Se ha implementado un sistema de autenticación mediante token para proteger los endpoints de analytics administrativos. Estos endpoints ahora **solo son accesibles mediante un token interno** que debe ser conocido únicamente por la empresa propietaria del sistema.

## Archivos Modificados

1. **[VerifyInternalAnalyticsToken.php](app/Http/Middleware/VerifyInternalAnalyticsToken.php)** - Middleware de validación de token
2. **[Kernel.php](app/Http/Kernel.php)** - Registro del middleware
3. **[api.php](routes/api.php)** - Aplicación del middleware a rutas de analytics
4. **[analytics.php](config/analytics.php)** - Configuración del sistema de analytics
5. **[.env.example](.env.example)** - Variables de entorno documentadas

## Endpoints Protegidos

Todos los endpoints bajo `/api/ubl2.1/memberships/analytics/*` ahora requieren autenticación con token interno:

- `GET /api/ubl2.1/memberships/analytics/overview` - Estadísticas generales
- `GET /api/ubl2.1/memberships/analytics/conversions` - Métricas de conversión
- `GET /api/ubl2.1/memberships/analytics/consumption` - Métricas de consumo
- `GET /api/ubl2.1/memberships/analytics/notifications` - Métricas de notificaciones
- `GET /api/ubl2.1/memberships/analytics/abuse-patterns` - Patrones de abuso
- `GET /api/ubl2.1/memberships/analytics/revenue-projection` - Proyección de ingresos
- `GET /api/ubl2.1/memberships/analytics/dashboard` - Dashboard completo
- `POST /api/ubl2.1/memberships/analytics/clear-cache` - Limpiar caché

## Configuración

### 1. Generar Token Seguro

Genera un token aleatorio fuerte usando uno de estos métodos:

```bash
# Método 1: usando openssl
openssl rand -base64 32

# Método 2: usando Laravel
php artisan tinker
>>> Illuminate\Support\Str::random(64)

# Método 3: generador online
# https://randomkeygen.com/ (elige "CodeIgniter Encryption Keys")
```

### 2. Configurar el .env

Agrega la variable en tu archivo `.env`:

```dotenv
# Token de acceso a analytics (SOLO para uso interno de la empresa)
ANALYTICS_INTERNAL_TOKEN=tu_token_super_secreto_aqui

# Opcional: Tiempo de caché (por defecto 5 minutos)
ANALYTICS_CACHE_TTL=300
```

⚠️ **IMPORTANTE**: 
- **NO compartas** este token con clientes ni terceros
- **NO lo subas** al control de versiones
- **Guárdalo** en un gestor de contraseñas seguro
- **Rótalo periódicamente** (cada 3-6 meses)

## Uso de los Endpoints

### Opción 1: Header `X-Analytics-Token` (Recomendado)

```bash
curl -X GET https://apidian.test/api/ubl2.1/memberships/analytics/overview \
  -H "X-Analytics-Token: tu_token_aqui"
```

### Opción 2: Bearer Token

```bash
curl -X GET https://apidian.test/api/ubl2.1/memberships/analytics/overview \
  -H "Authorization: Bearer tu_token_aqui"
```

### Opción 3: Query Parameter (menos seguro, no recomendado para producción)

```bash
curl -X GET "https://apidian.test/api/ubl2.1/memberships/analytics/overview?analytics_token=tu_token_aqui"
```

### Ejemplo con JavaScript/Axios

```javascript
const axios = require('axios');

const response = await axios.get(
  'https://apidian.test/api/ubl2.1/memberships/analytics/overview',
  {
    headers: {
      'X-Analytics-Token': process.env.ANALYTICS_INTERNAL_TOKEN
    }
  }
);
```

### Ejemplo con Postman

1. Crea una nueva request
2. Método: `GET`
3. URL: `https://apidian.test/api/ubl2.1/memberships/analytics/overview`
4. Headers:
   - Key: `X-Analytics-Token`
   - Value: `tu_token_aqui`

## Respuestas de Error

### Token no proporcionado (401)

```json
{
  "error": "Unauthorized",
  "message": "Missing analytics token"
}
```

### Token inválido (401)

```json
{
  "error": "Unauthorized",
  "message": "Invalid analytics token"
}
```

### Token no configurado en el servidor (503)

```json
{
  "error": "Analytics service is not properly configured",
  "message": "Access denied"
}
```

## Seguridad

### Registro de Intentos de Acceso

El middleware registra todos los intentos de acceso no autorizados en los logs:

```php
// Log cuando falta el token
[Analytics] Intento de acceso sin token
  - IP: 192.168.1.100
  - Path: /api/ubl2.1/memberships/analytics/overview

// Log cuando el token es inválido
[Analytics] Intento de acceso con token inválido
  - IP: 192.168.1.100
  - Path: /api/ubl2.1/memberships/analytics/overview
  - Longitud del token proporcionado: 32
```

### Verificación Constante en Tiempo (timing-safe)

El middleware usa `hash_equals()` para evitar ataques de timing:

```php
if (!hash_equals($validToken, $providedToken)) {
    // Token inválido
}
```

## Despliegue

### Checklist antes de desplegar

- [ ] Generar token seguro
- [ ] Agregar `ANALYTICS_INTERNAL_TOKEN` al `.env` de producción
- [ ] Verificar que el token NO esté en el código fuente
- [ ] Guardar el token en el gestor de secretos del equipo
- [ ] Probar el acceso con y sin token
- [ ] Verificar los logs de acceso no autorizado

### Entornos

#### Desarrollo
```dotenv
ANALYTICS_INTERNAL_TOKEN=dev_token_solo_para_desarrollo_local
```

#### Staging
```dotenv
ANALYTICS_INTERNAL_TOKEN=staging_token_diferente_a_produccion
```

#### Producción
```dotenv
ANALYTICS_INTERNAL_TOKEN=prod_token_super_secreto_y_complejo_123abc456def
```

## Mantenimiento

### Rotar el Token

Para cambiar el token de seguridad:

1. Generar nuevo token
2. Actualizar `.env` en todos los servidores
3. Ejecutar: `php artisan config:cache`
4. Actualizar herramientas/scripts que usen el token
5. Invalidar el token antiguo

### Limpiar Caché de Analytics

```bash
curl -X POST https://apidian.test/api/ubl2.1/memberships/analytics/clear-cache \
  -H "X-Analytics-Token: tu_token_aqui"
```

## Preguntas Frecuentes

**Q: ¿Los clientes pueden acceder a estos endpoints?**  
A: No. Estos endpoints son exclusivos para la empresa propietaria del sistema.

**Q: ¿Qué pasa si alguien intenta acceder sin token?**  
A: Recibirá un error 401 y el intento se registrará en los logs.

**Q: ¿El token expira?**  
A: No automáticamente. Se recomienda rotarlo manualmente cada 3-6 meses.

**Q: ¿Puedo usar el mismo token para desarrollo y producción?**  
A: No. Usa tokens diferentes por entorno para mayor seguridad.

**Q: ¿Qué hacer si el token se compromete?**  
A: Rotarlo inmediatamente siguiendo los pasos de "Rotar el Token".

## Mensaje de Commit Sugerido

```
feat(security): add token authentication for analytics endpoints

- Implemented VerifyInternalAnalyticsToken middleware
- Protected all /api/ubl2.1/memberships/analytics/* routes
- Added analytics.php config file
- Token can be provided via header, bearer, or query param
- Logs all unauthorized access attempts
- Updated .env.example with ANALYTICS_INTERNAL_TOKEN

BREAKING CHANGE: Analytics endpoints now require internal token authentication
```
