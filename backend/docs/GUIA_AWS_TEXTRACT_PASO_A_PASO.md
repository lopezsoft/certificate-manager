# 🎯 CONFIGURACIÓN ESPECÍFICA: AWS TEXTRACT PARA CERTIFICADOS COLOMBIANOS

## 📋 **PASO 1: CREAR CUENTA AWS**

### **1.1 Registrarse en AWS**
1. **Ir a:** https://aws.amazon.com/
2. **Crear cuenta** → "Create an AWS Account"
3. **Información requerida:**
   - Email y contraseña
   - Información de contacto
   - Tarjeta de crédito (para verificación)
   - Verificación por teléfono

### **1.2 Acceder a AWS Console**
1. **Login:** https://console.aws.amazon.com/
2. **Región recomendada:** US East (N. Virginia) `us-east-1`
   - Es la más económica y estable para Textract

---

## 🔐 **PASO 2: CREAR USUARIO IAM PARA TEXTRACT**

### **2.1 Ir a IAM**
1. **AWS Console → Buscar "IAM"**
2. **Identity and Access Management**

### **2.2 Crear Usuario**
1. **Users → Create user**
2. **Configuración:**
   ```
   Username: certificate-textract-user
   AWS credential type: ✅ Access key - Programmatic access
   AWS Management Console access: ❌ No (no necesario)
   ```

### **2.3 Asignar Permisos**
1. **Attach existing policies directly**
2. **Buscar y seleccionar:**
   - ✅ `AmazonTextractFullAccess`
   - ✅ `AmazonS3ReadOnlyAccess` (para documentos en S3, opcional)

### **2.4 Descargar Credenciales**
1. **Create user → Success**
2. **⚠️ IMPORTANTE:** Descargar el CSV con:
   - `Access Key ID`
   - `Secret Access Key`
3. **Guardar en lugar seguro** (solo se muestra una vez)

---

## 📦 **PASO 3: INSTALAR SDK EN TU PROYECTO**

### **3.1 Instalar AWS SDK**
```bash
cd "d:\wamp64\www\certificate-manager\backend"
composer require aws/aws-sdk-php
```

### **3.2 Verificar Instalación**
```bash
php artisan tinker --execute="echo 'AWS SDK: ' . (class_exists('Aws\Textract\TextractClient') ? 'Instalado ✅' : 'No encontrado ❌');"
```

---

## ⚙️ **PASO 4: CONFIGURAR VARIABLES DE ENTORNO**

### **4.1 Actualizar .env**
Agregar estas líneas a tu archivo `.env`:

```env
# === CONFIGURACIÓN AWS TEXTRACT ===
OCR_SERVICE=textract
AWS_ACCESS_KEY_ID=AKIA1234567890ABCDEF
AWS_SECRET_ACCESS_KEY=tu-secret-key-aquí
AWS_DEFAULT_REGION=us-east-1
AWS_TEXTRACT_REGION=us-east-1

# === CONFIGURACIÓN GEMINI (IA) ===
GEMINI_API_KEY=tu-gemini-api-key
GEMINI_MODEL=gemini-1.5-flash
```

### **4.2 Ejemplo de .env completo**
```env
# Reemplaza con tus credenciales reales
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_DEFAULT_REGION=us-east-1
```

---

## 🧪 **PASO 5: PROBAR CONFIGURACIÓN**

### **5.1 Verificar Servicios**
```bash
php artisan tinker --execute="
\$ocr = new App\Services\UnifiedOcrService();
\$status = \$ocr->getServicesStatus();
print_r(\$status);
"
```

### **5.2 Resultado Esperado**
```
Array
(
    [preferred_service] => textract
    [textract_available] => 1
    [aws_configured] => 1
    [vision_configured] => 
)
```

---

## 📄 **PASO 6: PROBAR CON DOCUMENTO REAL**

### **6.1 Preparar Documento de Prueba**
1. **Crear carpeta:** `storage/app/test-documents/`
2. **Subir un RUT, Cédula o Cámara de Comercio** en formato JPG/PNG/PDF

### **6.2 Ejecutar Prueba**
```php
// En tinker
$service = new App\Services\CertificateProcessingService();
$result = $service->processCertificate(1, 1);
echo json_encode($result, JSON_PRETTY_PRINT);
```

---

## 💰 **COSTOS ESTIMADOS PARA TU PROYECTO**

### **Nivel Gratuito AWS (Primer Año)**
- ✅ **1,000 páginas/mes GRATIS**
- ✅ **Suficiente para ~33 certificados/día**

### **Después del Año Gratuito**
- 💵 **OCR básico:** $1.50 por 1,000 páginas
- 💵 **Análisis de formularios:** $2.50 por 1,000 páginas
- 💵 **Costo por certificado:** ~$0.0025 USD

### **Para 100 certificados/mes:**
- **Año 1:** GRATIS ✅
- **Después:** ~$0.25 USD/mes 💰

---

## 🎯 **PRÓXIMOS PASOS**

1. ✅ **Crear cuenta AWS** siguiendo la guía
2. ✅ **Configurar IAM user** con permisos de Textract
3. ✅ **Instalar SDK:** `composer require aws/aws-sdk-php`
4. ✅ **Configurar .env** con tus credenciales
5. ✅ **Probar** con un documento real
6. ✅ **Integrar** en tu flujo de trabajo existente

¿Necesitas ayuda con algún paso específico?