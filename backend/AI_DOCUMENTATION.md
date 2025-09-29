# Documentación de Integración de IA - Certificate Manager

## Resumen

Este documento describe la implementación de las funcionalidades de Inteligencia Artificial integradas en el sistema Certificate Manager. Las nuevas capacidades incluyen:

- **OCR (Reconocimiento Óptico de Caracteres)** usando Google Cloud Vision
- **Análisis inteligente de contenido** usando Google Gemini
- **Generación automática de correos electrónicos**
- **Clasificación automática de documentos**
- **Procesamiento en segundo plano** con Jobs de Laravel

## Configuración Inicial

### 1. Variables de Entorno

Añade las siguientes variables a tu archivo `.env`:

```env
# Google Cloud Vision API Configuration
GOOGLE_VISION_API_KEY=tu_api_key_aqui
GOOGLE_CLOUD_PROJECT_ID=tu_project_id
GOOGLE_VISION_PRIVATE_KEY_ID=tu_private_key_id
GOOGLE_VISION_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\ntu_clave_privada\n-----END PRIVATE KEY-----"
GOOGLE_VISION_CLIENT_EMAIL=tu_service_account@tu_proyecto.iam.gserviceaccount.com
GOOGLE_VISION_CLIENT_ID=tu_client_id

# Google Gemini API Configuration
GEMINI_API_KEY=tu_gemini_api_key_aqui
GEMINI_MODEL=gemini-1.5-flash

# AI Processing Settings
AI_MAX_FILE_SIZE=10485760
AI_REQUEST_TIMEOUT=30
```

### 2. Configuración de Google Cloud

#### Para Google Cloud Vision:
1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la API de Vision AI
4. Crea una cuenta de servicio y descarga el archivo JSON de credenciales
5. Extrae los valores del JSON y añádelos a tu `.env`

#### Para Google Gemini:
1. Ve a [Google AI Studio](https://makersuite.google.com/)
2. Crea una nueva API key
3. Añádela a tu `.env` como `GEMINI_API_KEY`

## Endpoints Disponibles

Todos los endpoints están bajo el prefijo `/api/v1/ai/` y requieren autenticación.

### 1. Procesar Certificado Completo
```
POST /api/v1/ai/process-certificate
```

**Descripción:** Procesa una imagen de certificado usando OCR y análisis de IA.

**Parámetros:**
- `image` (archivo): Imagen del certificado (JPG, PNG, PDF)

**Respuesta de éxito:**
```json
{
    "success": true,
    "message": "Certificate processed successfully",
    "data": {
        "ocr_results": {
            "full_text": "Texto extraído del certificado...",
            "confidence": 0.95,
            "word_count": 250,
            "detected_language": "es"
        },
        "ai_analysis": {
            "nombre_completo": "Juan Pérez García",
            "titulo_certificado": "Curso de Laravel Avanzado",
            "institucion": "Universidad Tecnológica",
            "fecha_emision": "2024-03-15",
            "duracion_horas": 40
        },
        "classification": {
            "document_type": "CERTIFICADO_CURSO",
            "confidence": 0.9
        }
    }
}
```

### 2. Extraer Solo Texto (OCR)
```
POST /api/v1/ai/extract-text
```

**Descripción:** Extrae únicamente el texto de una imagen.

**Parámetros:**
- `image` (archivo): Imagen a procesar

### 3. Generar Contenido de Email
```
POST /api/v1/ai/generate-email
```

**Descripción:** Genera contenido personalizado para correos electrónicos.

**Parámetros:**
```json
{
    "certificate_data": {
        "nombre_completo": "Juan Pérez",
        "titulo_certificado": "Curso de Laravel",
        "institucion": "Universidad Tech"
    },
    "recipient_name": "Juan Pérez",
    "email_type": "notification"
}
```

**Tipos de email disponibles:**
- `notification`: Notificación de registro exitoso
- `reminder`: Recordatorio de expiración
- `congratulations`: Felicitaciones por obtener el certificado

### 4. Clasificar Documento
```
POST /api/v1/ai/classify-document
```

**Descripción:** Clasifica el tipo de documento basado en su contenido.

**Parámetros:**
```json
{
    "text": "Texto del documento a clasificar"
}
```

### 5. Estado de los Servicios
```
GET /api/v1/ai/status
```

**Descripción:** Obtiene el estado de configuración de los servicios de IA.

## Procesamiento en Segundo Plano

Para operaciones pesadas, usa el Job `ProcessCertificateJob`:

```php
use App\Jobs\ProcessCertificateJob;

// Despachar el job
ProcessCertificateJob::dispatch(
    $filePath,
    $userId,
    $requestId,
    [
        'generate_email' => true,
        'email_type' => 'notification',
        'recipient_name' => 'Juan Pérez'
    ]
);
```

## Ejemplos de Uso

### Ejemplo 1: Procesar Certificado con JavaScript

```javascript
const formData = new FormData();
formData.append('image', fileInput.files[0]);

fetch('/api/v1/ai/process-certificate', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/json'
    },
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Certificado procesado:', data.data);
        // Usar los datos extraídos para llenar formularios automáticamente
    }
});
```

### Ejemplo 2: Generar Email Automático

```javascript
const emailData = {
    certificate_data: {
        nombre_completo: "María González",
        titulo_certificado: "Certificación en PHP",
        institucion: "Instituto Tecnológico"
    },
    recipient_name: "María González",
    email_type: "congratulations"
};

fetch('/api/v1/ai/generate-email', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(emailData)
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Usar el contenido generado
        document.getElementById('email-subject').value = data.data.subject;
        document.getElementById('email-body').value = data.data.body;
    }
});
```

## Manejo de Errores

Todos los endpoints devuelven un formato consistente de respuesta:

### Respuesta de Error
```json
{
    "success": false,
    "message": "Descripción del error",
    "errors": {
        "campo": ["Lista de errores de validación"]
    }
}
```

### Códigos de Estado HTTP
- `200`: Éxito
- `422`: Error de validación
- `500`: Error interno del servidor

## Limitaciones y Consideraciones

### Limitaciones Técnicas
- **Tamaño máximo de archivo:** 10MB (configurable)
- **Formatos soportados:** JPG, PNG, PDF
- **Timeout:** 30 segundos por petición
- **Intentos en Jobs:** 3 reintentos automáticos

### Consideraciones de Rendimiento
- Las operaciones de IA son costosas computacionalmente
- Usa Jobs para procesamiento en segundo plano cuando sea posible
- Implementa caché para resultados que no cambien frecuentemente

### Consideraciones de Seguridad
- Todas las API keys están en variables de entorno
- Los archivos temporales se eliminan automáticamente
- Se requiere autenticación para todos los endpoints

## Monitoreo y Logs

El sistema registra automáticamente:

- Inicio y finalización de procesamiento
- Errores en OCR y análisis de IA
- Tiempos de procesamiento
- Fallos en Jobs

Revisa los logs en `storage/logs/laravel.log` para monitorear el rendimiento.

## Próximos Pasos y Mejoras

### Mejoras Recomendadas
1. **Implementar caché** para resultados de clasificación
2. **Añadir notificaciones en tiempo real** usando WebSockets
3. **Crear dashboard de métricas** de uso de IA
4. **Implementar rate limiting** para las APIs de IA
5. **Añadir soporte para más idiomas**

### Integraciones Futuras
- **Azure Cognitive Services** como alternativa
- **AWS Textract** para documentos complejos
- **OpenAI Vision** para análisis más avanzado de imágenes

## Soporte y Troubleshooting

### Problemas Comunes

1. **Error: "API key not found"**
   - Verifica que las variables de entorno estén configuradas correctamente
   - Revisa que el archivo `.env` tenga los valores correctos

2. **Error: "File not found"**
   - Asegúrate de que el directorio `storage/app/temp` exista
   - Verifica los permisos de escritura en `storage/`

3. **Error: "Vision API error"**
   - Verifica que la API de Google Cloud Vision esté habilitada
   - Revisa que las credenciales de la cuenta de servicio sean correctas

4. **Timeout en procesamiento**
   - Reduce el tamaño de la imagen
   - Usa Jobs para procesamiento en segundo plano

### Contacto de Soporte
Para soporte técnico, consulta los logs del sistema y contacta al equipo de desarrollo.