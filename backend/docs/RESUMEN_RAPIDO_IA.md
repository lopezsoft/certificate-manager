# 🚀 RESUMEN RÁPIDO: OBTENER CLAVES DE IA

## ⏱️ **PASOS RÁPIDOS (15 minutos)**

### **1. Google Cloud Vision (OCR)** 
🔗 **URL:** https://console.cloud.google.com/
1. Crear proyecto → Habilitar "Vision API"
2. IAM → Service Accounts → Crear service account
3. Descargar clave JSON → Guardar en `storage/app/google-credentials.json`

### **2. Google Gemini (IA)**
🔗 **URL:** https://makersuite.google.com/app/apikey
1. Hacer clic en "Create API Key"
2. Copiar la clave generada

---

## 📝 **CONFIGURAR .ENV**

```env
# Google Cloud Vision
GOOGLE_CLOUD_PROJECT_ID=tu-proyecto-id
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google-credentials.json

# Google Gemini
GEMINI_API_KEY=AIzaSyD0987654321fedcba  
GEMINI_MODEL=gemini-1.5-flash
```

---

## 🔧 **ACTIVAR EN CÓDIGO**

### **En OcrService.php:**
Descomentar las líneas que dicen:
```php
/* TODO: Uncomment when Google Vision credentials are available
```

### **En AiContentService.php:**
Descomentar las líneas que dicen:
```php
/* TODO: Implement actual Gemini API call when keys are available
```

---

## 💰 **COSTOS**
- **Vision API:** Gratuito hasta 1,000 imágenes/mes
- **Gemini API:** Gratuito hasta 15 requests/minuto

---

## 🧪 **PROBAR**
```php
// En tinker
$service = new App\Services\CertificateProcessingService();
$result = $service->processCertificate(1, 1);
```

**¡Listo! Tu sistema de IA estará completamente funcional.**