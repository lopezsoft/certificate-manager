# 🔑 GUÍA COMPLETA: OBTENER CLAVES DE IA

## 📋 **PRERREQUISITOS**
- Cuenta de Google Cloud
- Tarjeta de crédito (para verificación, hay nivel gratuito)

---

## 🌐 **GOOGLE CLOUD VISION API (OCR)**

### **Paso 1: Crear Proyecto**
1. **Ir a:** https://console.cloud.google.com/
2. **Hacer clic en:** "Select a project" → "New Project"
3. **Nombre:** `certificate-manager-2025`
4. **Hacer clic en:** "Create"

### **Paso 2: Habilitar Vision API**
1. **Buscar:** "Vision API" en la barra de búsqueda
2. **Hacer clic en:** "Cloud Vision API"
3. **Hacer clic en:** "Enable"
4. **Esperar:** A que se habilite (1-2 minutos)

### **Paso 3: Crear Service Account (Recomendado)**
1. **Ir a:** IAM & Admin → Service Accounts
2. **Hacer clic en:** "Create Service Account"
3. **Completar:**
   - Name: `certificate-ocr-service`
   - ID: `certificate-ocr-service`
   - Description: `Service for OCR processing`
4. **Hacer clic en:** "Create and Continue"
5. **Rol:** Seleccionar "Cloud Vision API Service Agent"
6. **Hacer clic en:** "Continue" → "Done"

### **Paso 4: Generar Clave JSON**
1. **En Service Accounts:** Hacer clic en el email del service account creado
2. **Pestaña "Keys":** Hacer clic en "Add Key" → "Create new key"
3. **Tipo:** JSON
4. **Hacer clic en:** "Create"
5. **Descargar:** El archivo JSON automáticamente

### **Paso 5: Configurar en Laravel**
1. **Guardar JSON:** En `storage/app/google-credentials.json`
2. **Actualizar .env:**
```env
GOOGLE_CLOUD_PROJECT_ID=certificate-manager-2025
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google-credentials.json
```

---

## 🤖 **GOOGLE GEMINI API (IA)**

### **Paso 1: Obtener API Key**
1. **Ir a:** https://makersuite.google.com/app/apikey
2. **Iniciar sesión** con tu cuenta de Google
3. **Hacer clic en:** "Create API Key"
4. **Seleccionar:** Tu proyecto de Google Cloud (el mismo de arriba)
5. **Copiar:** La API key generada

### **Paso 2: Configurar en Laravel**
1. **Actualizar .env:**
```env
GEMINI_API_KEY=AIzaSyD0987654321fedcba
GEMINI_MODEL=gemini-1.5-flash
```

---

## ⚡ **ACTIVAR APIS REALES EN EL CÓDIGO**

### **1. Descomentar OCR Real:**
En `app/Services/OcrService.php`, descomentar:
```php
// TODO: Uncomment when Google Vision credentials are available
```

### **2. Descomentar IA Real:**
En `app/Services/AiContentService.php`, descomentar:
```php
// TODO: Implement actual Gemini API call when keys are available
```

---

## 💰 **COSTOS ESTIMADOS**

### **Google Cloud Vision API:**
- **Gratuito:** Primeras 1,000 imágenes/mes
- **Después:** $1.50 por 1,000 imágenes
- **Para certificados:** ~$0.0015 por documento

### **Google Gemini API:**
- **Gratuito:** 15 requests/minuto, 1,500 requests/día
- **Escalado:** Muy económico para uso empresarial

---

## 🔒 **SEGURIDAD**

### **Variables de Entorno (.env):**
```env
# Google Cloud Vision API
GOOGLE_CLOUD_PROJECT_ID=tu-proyecto-id
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google-credentials.json

# Google Gemini API  
GEMINI_API_KEY=tu-api-key-aqui
GEMINI_MODEL=gemini-1.5-flash
```

### **Archivo JSON de Credenciales:**
- **Ubicación:** `storage/app/google-credentials.json`
- **Permisos:** Solo lectura para la aplicación
- **Git:** Agregar a `.gitignore`

---

## 🧪 **TESTING**

### **Verificar Configuración:**
```php
// En tinker o controlador de prueba
$ocrService = new App\Services\OcrService();
$aiService = new App\Services\AiContentService();

// Probar OCR
$result = $ocrService->extractTextFromImage($imagePath);

// Probar IA
$result = $aiService->generateSimpleResponse("Hola, ¿funcionas?");
```

---

## 🚨 **TROUBLESHOOTING**

### **Error: "API not enabled"**
- Verificar que Vision API esté habilitada en Google Cloud Console

### **Error: "Authentication failed"**
- Verificar que el archivo JSON esté en la ubicación correcta
- Verificar permisos del service account

### **Error: "Quota exceeded"**
- Verificar límites en Google Cloud Console
- Considerar upgrade del plan

---

## 📞 **SOPORTE**
- **Google Cloud:** https://cloud.google.com/support
- **Gemini API:** https://ai.google.dev/docs