# 🔄 GUÍA ACTUALIZADA: OBTENER CREDENCIALES OCR (2025)

## 🚀 **AWS TEXTRACT (Recomendado en tu plan)**

### **¿Por qué AWS Textract?**
- ✅ **Especializado en documentos** (mejor que Vision para formularios)
- ✅ **Extracción de tablas y formularios** automática
- ✅ **Mejor precio** para documentos complejos
- ✅ **Integración nativa con AWS**

### **Paso 1: Crear Cuenta AWS**
1. **Ir a:** https://aws.amazon.com/
2. **Crear cuenta** (tarjeta requerida, pero hay nivel gratuito)
3. **Verificar cuenta** por email/teléfono

### **Paso 2: Crear Usuario IAM**
1. **AWS Console:** https://console.aws.amazon.com/
2. **Buscar:** "IAM" → Identity and Access Management
3. **Users:** Create user
4. **Username:** `certificate-textract-user`
5. **Access type:** Programmatic access
6. **Permissions:** Attach existing policy → `AmazonTextractFullAccess`
7. **Descargar CSV** con Access Key ID y Secret Access Key

### **Paso 3: Configurar en Laravel**
```env
# AWS Textract
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_TEXTRACT_REGION=us-east-1
```

### **Paso 4: Instalar SDK**
```bash
composer require aws/aws-sdk-php
```

---

## 🔄 **GOOGLE CLOUD VISION API (Actualizado 2025)**

### **Cambios Recientes:**
- ✅ **Nueva interfaz** de Google Cloud Console
- ✅ **Integración mejorada** con AI Studio
- ✅ **Nuevos métodos** de autenticación
- ✅ **Precios actualizados**

### **Método 1: Service Account (Recomendado para producción)**

#### **Paso 1: Google Cloud Console**
1. **Ir a:** https://console.cloud.google.com/
2. **Crear proyecto** o seleccionar existente
3. **Habilitar API:**
   - Buscar "Cloud Vision API"
   - Hacer clic "Enable"

#### **Paso 2: Crear Service Account**
1. **Menú:** APIs & Services → Credentials
2. **Create Credentials:** Service account
3. **Nombre:** `certificate-vision-service`
4. **Role:** Cloud Vision AI Service Agent
5. **Create and download** JSON key

#### **Paso 3: Configurar Laravel**
```env
GOOGLE_CLOUD_PROJECT_ID=tu-proyecto-id
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google-vision-key.json
```

### **Método 2: API Key (Más simple, para desarrollo)**

#### **Paso 1: Crear API Key**
1. **Google Cloud Console:** APIs & Services → Credentials
2. **Create Credentials:** API key
3. **Restrict API key:** Cloud Vision API
4. **Copiar** la API key

#### **Paso 2: Configurar Laravel**
```env
GOOGLE_VISION_API_KEY=AIzaSyC...
GOOGLE_CLOUD_PROJECT_ID=tu-proyecto-id
```

---

## 💰 **COMPARACIÓN DE COSTOS (2025)**

### **AWS Textract:**
- **Gratuito:** 1,000 páginas/mes (primer año)
- **Después:** $1.50 por 1,000 páginas
- **Formularios/Tablas:** $50 por 1,000 páginas

### **Google Cloud Vision:**
- **Gratuito:** 1,000 imágenes/mes
- **Después:** $1.50 por 1,000 imágenes
- **Document AI:** $2.50 por 1,000 páginas

---

## 🔧 **IMPLEMENTACIÓN RECOMENDADA**

### **Para tu caso de uso (certificados colombianos):**
**Recomiendo AWS Textract** porque:
1. Mejor en **formularios estructurados**
2. **Extracción automática** de campos de formularios
3. **Mejor manejo** de documentos oficiales
4. **Precio más competitivo** para documentos

### **Configuración sugerida:**
```env
# Usar AWS Textract como principal
OCR_SERVICE=textract
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1

# Google Vision como backup
GOOGLE_VISION_API_KEY=...
GOOGLE_CLOUD_PROJECT_ID=...
```

---

## 📱 **NUEVAS CARACTERÍSTICAS 2025**

### **AWS Textract:**
- ✅ **Queries personalizadas** ("¿Cuál es el NIT?")
- ✅ **Análisis de identidad** automático
- ✅ **Detección de firmas**
- ✅ **Verificación de autenticidad**

### **Google Vision:**
- ✅ **Document AI Workbench** visual
- ✅ **Procesadores especializados** por tipo de documento
- ✅ **Integración con Vertex AI**
- ✅ **Análisis de calidad** de documento

---

## 🚀 **PRÓXIMOS PASOS**

1. **Decidir:** AWS Textract (recomendado) o Google Vision
2. **Configurar credenciales** según la guía
3. **Actualizar** `OcrService.php` con el servicio elegido
4. **Probar** con documentos reales
5. **Optimizar** según resultados

¿Prefieres que implemente **AWS Textract** como está en tu plan original?