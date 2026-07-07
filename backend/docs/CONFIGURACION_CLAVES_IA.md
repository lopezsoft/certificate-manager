## 🔑 CONFIGURACIÓN DE CLAVES PARA SERVICIOS DE IA

### Agrega estas variables a tu archivo .env:

# Google Cloud Vision API (OCR)
GOOGLE_CLOUD_PROJECT_ID=tu-proyecto-id
GOOGLE_VISION_API_KEY=tu-api-key-de-vision

# O usando Service Account (recomendado para producción)
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google-credentials.json

# Google Gemini API (IA)
GEMINI_API_KEY=tu-api-key-de-gemini
GEMINI_MODEL=gemini-1.5-flash

### Ejemplo de configuración completa:
GOOGLE_CLOUD_PROJECT_ID=certificate-manager-2025
GOOGLE_VISION_API_KEY=AIzaSyC1234567890abcdef
GEMINI_API_KEY=AIzaSyD0987654321fedcba
GEMINI_MODEL=gemini-1.5-flash