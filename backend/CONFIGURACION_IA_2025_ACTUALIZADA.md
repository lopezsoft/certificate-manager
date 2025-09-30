# 🚀 CONFIGURACIÓN ACTUALIZADA DE IA (2025)

## 🔥 **LO QUE HA CAMBIADO**

### **Google Cloud Vision (2025):**
- ✅ **Nueva interfaz** de Google Cloud Console
- ✅ **Método de autenticación actualizado**
- ✅ **Integración con AI Platform**
- ✅ **Document AI Processors** especializados

### **AWS Textract (Recomendado):**
- ✅ **Mejor para formularios** como RUT, Cédula, Cámara de Comercio
- ✅ **Extracción automática** de campos de formularios
- ✅ **Queries personalizadas** ("¿Cuál es el NIT?")
- ✅ **Mejor precio** para documentos estructurados

---

## 🎯 **RECOMENDACIÓN ACTUALIZADA PARA TU PROYECTO**

### **Para Certificados Colombianos → AWS TEXTRACT**

**¿Por qué AWS Textract es mejor para tu caso?**
1. **Documentos estructurados:** RUT, Cédula, Cámara de Comercio son formularios
2. **Extracción de campos:** Automáticamente identifica NIT, nombres, direcciones
3. **Mejor precision:** En documentos oficiales latinos
4. **Precio competitivo:** Más económico para análisis de formularios

---

## 📋 **GUÍA ACTUALIZADA: AWS TEXTRACT**

### **Paso 1: Crear Cuenta AWS (2025)**
1. **URL:** https://aws.amazon.com/
2. **Crear cuenta** (necesita tarjeta, pero hay nivel gratuito)
3. **Verificar identidad** (nuevo proceso 2025)

### **Paso 2: Configurar IAM User**
1. **AWS Console:** https://console.aws.amazon.com/
2. **IAM → Users → Create user**
3. **Username:** `textract-certificate-user`
4. **Permissions:** Attach policy → `AmazonTextractFullAccess`
5. **Create access key** → Download CSV

### **Paso 3: Instalar SDK**
```bash
composer require aws/aws-sdk-php
```

### **Paso 4: Configurar .env**
```env
# AWS Textract (Recomendado)
OCR_SERVICE=textract
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_TEXTRACT_REGION=us-east-1
```

---

## 📋 **ALTERNATIVA: GOOGLE CLOUD VISION (Actualizado)**

### **Método Actualizado 2025:**

#### **1. Google Cloud Console (Nueva Interfaz)**
1. **URL:** https://console.cloud.google.com/
2. **Create Project** o seleccionar existente
3. **APIs & Services → Library**
4. **Buscar:** "Cloud Vision API" → Enable

#### **2. Autenticación Actualizada**
**Opción A: Service Account (Producción)**
1. **IAM & Admin → Service Accounts**
2. **Create Service Account:**
   - Name: `certificate-vision-ocr`
   - Role: `Cloud Vision AI Service Agent`
3. **Keys → Add Key → Create new key** (JSON)
4. **Download** y guardar como `storage/app/google-vision.json`

**Opción B: API Key (Desarrollo)**
1. **APIs & Services → Credentials**
2. **Create Credentials → API Key**
3. **Restrict key:** Cloud Vision API only

#### **3. Configurar Laravel**
```env
# Google Vision (Alternativa)
OCR_SERVICE=vision
GOOGLE_CLOUD_PROJECT_ID=tu-proyecto
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google-vision.json
# O usando API Key:
GOOGLE_VISION_API_KEY=AIzaSyC...
```

---

## 🔧 **INSTALACIÓN COMPLETA**

### **1. Instalar Dependencias**
```bash
# Para AWS Textract
composer require aws/aws-sdk-php

# Para Google Vision (opcional)
composer require google/cloud-vision
```

### **2. Configurar Variables**
```env
# Servicio principal
OCR_SERVICE=textract

# AWS Textract
AWS_ACCESS_KEY_ID=tu-access-key
AWS_SECRET_ACCESS_KEY=tu-secret-key
AWS_DEFAULT_REGION=us-east-1

# Google Vision (backup)
GOOGLE_CLOUD_PROJECT_ID=tu-proyecto
GOOGLE_VISION_API_KEY=tu-api-key

# Gemini (IA)
GEMINI_API_KEY=tu-gemini-key
GEMINI_MODEL=gemini-1.5-flash
```

### **3. Probar Configuración**
```php
// En tinker
$ocr = new App\Services\UnifiedOcrService();
$status = $ocr->getServicesStatus();
dd($status);
```

---

## 💰 **PRECIOS ACTUALIZADOS (2025)**

### **AWS Textract:**
- 🆓 **Gratuito:** 1,000 páginas/mes (primer año)
- 💵 **OCR básico:** $1.50 por 1,000 páginas
- 💵 **Análisis de formularios:** $2.50 por 1,000 páginas
- 💵 **Para tu proyecto:** ~$0.0025 por certificado

### **Google Cloud Vision:**
- 🆓 **Gratuito:** 1,000 imágenes/mes
- 💵 **OCR básico:** $1.50 por 1,000 imágenes
- 💵 **Document AI:** $3.00 por 1,000 páginas
- 💵 **Para tu proyecto:** ~$0.003 por certificado

**💡 Conclusión:** AWS Textract es más económico y preciso para tu caso de uso.

---

## 🚀 **PRÓXIMOS PASOS**

1. ✅ **Elegir servicio:** AWS Textract (recomendado)
2. ✅ **Crear cuenta** y obtener credenciales
3. ✅ **Configurar .env** con las claves
4. ✅ **Instalar SDK:** `composer require aws/aws-sdk-php`
5. ✅ **Probar** con documentos reales
6. ✅ **Optimizar** según resultados

¿Te ayudo a configurar AWS Textract específicamente para tu proyecto?