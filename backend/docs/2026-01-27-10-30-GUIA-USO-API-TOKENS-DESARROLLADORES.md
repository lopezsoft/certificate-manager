# Guía de Uso de API: Personal Access Tokens (PAT)

**Fecha:** 27 de Enero, 2026  
**Versión:** 2.0  
**Audiencia:** Desarrolladores que consumen la API APIDIAN

---

## 📋 Tabla de Contenidos

1. [Introducción](#introducción)
2. [Arquitectura de Tokens](#arquitectura-de-tokens)
3. [Endpoints Disponibles](#endpoints-disponibles)
4. [Flujo de Autenticación](#flujo-de-autenticación)
5. [Ejemplos Prácticos](#ejemplos-prácticos)
6. [Casos de Uso](#casos-de-uso)
7. [Mejores Prácticas](#mejores-prácticas)
8. [Troubleshooting](#troubleshooting)

---

## Introducción

APIDIAN utiliza **Personal Access Tokens (PAT)** como sistema único de autenticación API.

### ¿Qué es un PAT?

Un token de acceso personal es una credencial de autenticación de larga duración (90 días) que permite a tus aplicaciones, scripts o integraciones consumir la API de forma segura sin necesidad de exponer credenciales de usuario.

**Ventajas:**
- ✅ Un solo paso para generar el token
- ✅ Válido por 90 días (configurable hasta 365)
- ✅ Revocación instantánea si se compromete
- ✅ Auditoría completa de creación y uso
- ✅ Rate limiting: 10 tokens/día por usuario

---

## Arquitectura de Tokens

```
┌─────────────────────────────────────────────────────────────┐
│                  FLUJO DE AUTENTICACIÓN PAT                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1️⃣  Registro/Login Inicial (Una sola vez)                 │
│      POST /api/register  o  POST /api/login                │
│      ↓                                                      │
│      Token de Sesión Temporal                              │
│                                                             │
│  2️⃣  Crear Personal Access Token (Una sola vez)            │
│      POST /api/ubl2.1/tokens                               │
│      Authorization: Bearer {token_de_sesion}               │
│      ↓                                                      │
│      PAT de Larga Duración (90 días)                       │
│      Ejemplo: "1|abc123xyz789..."                          │
│                                                             │
│  3️⃣  Usar PAT en Todas tus Peticiones (90 días)            │
│      GET/POST /api/ubl2.1/{cualquier-endpoint}             │
│      Authorization: Bearer {tu_pat_token}                  │
│                                                             │
│  4️⃣  Renovar PAT antes de vencimiento (Día 85-90)          │
│      Crear nuevo PAT → Actualizar .env → Revocar antiguo   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Endpoints Disponibles

### 🔐 Autenticación Inicial

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/api/register` | Crear nueva cuenta |
| `POST` | `/api/login` | Iniciar sesión (obtener token temporal) |

### 🎯 Gestión de Personal Access Tokens (PAT)

**Base URL:** `/api/ubl2.1/tokens`

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/ubl2.1/tokens` | Listar todos mis tokens |
| `POST` | `/api/ubl2.1/tokens` | Crear nuevo token (⭐ Acción principal) |
| `GET` | `/api/ubl2.1/tokens/{id}` | Ver detalles de un token |
| `DELETE` | `/api/ubl2.1/tokens/{id}` | Revocar token específico |
| `POST` | `/api/ubl2.1/tokens/revoke-all` | Revocar todos mis tokens |

---

## Flujo de Autenticación

### ⭐ Flujo Completo PAT (Único Sistema)

**Ideal para:** Apps, scripts, integraciones, webhooks, CLIs, ERPs

```
┌──────────────────────────────────────────────────────────┐
│  PASO 1: Registro/Login Inicial (Solo una vez)          │
└──────────────────────────────────────────────────────────┘

POST /api/register  (si no tienes cuenta)
Content-Type: application/json

{
  "name": "Mi Empresa SAS",
  "email": "empresa@ejemplo.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}

O si ya tienes cuenta:

POST /api/login
Content-Type: application/json

{
  "email": "empresa@ejemplo.com",
  "password": "SecurePass123!"
}

✅ Respuesta:
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "user": {
    "id": 1,
    "name": "Mi Empresa SAS",
    "email": "empresa@ejemplo.com"
  }
}

⚠️ NOTA: Este token de sesión es temporal. Úsalo solo para crear tu PAT.

┌──────────────────────────────────────────────────────────┐
│  PASO 2: Crear Personal Access Token (Solo una vez)     │
└──────────────────────────────────────────────────────────┘

POST /api/ubl2.1/tokens
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json

{
  "name": "Token Producción ERP",
  "abilities": ["*"]  // Opcional: permisos específicos
}

✅ Respuesta:
{
  "success": true,
  "data": {
    "id": "9d471f3b-8c6e-4a2d-b9f0-1234567890ab",
    "name": "Token Producción ERP",
    "token": "1|aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5",
    "expires_at": "2026-04-27 10:30:00",
    "created_at": "2026-01-27 10:30:00"
  },
  "message": "⚠️ IMPORTANTE: Copia este token ahora. No se mostrará de nuevo."
}

🔑 GUARDA ESTE TOKEN: 1|aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5

┌──────────────────────────────────────────────────────────┐
│  PASO 3: Usar PAT en Todas las Peticiones (90 días)     │
└──────────────────────────────────────────────────────────┘

GET /api/ubl2.1/companies
Authorization: Bearer 1|aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5

POST /api/ubl2.1/invoices
Authorization: Bearer 1|aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV3wX4yZ5
Content-Type: application/json

{
  "company_id": "123",
  "invoice_data": {...}
}

✅ Este token dura 90 días. Configúralo en tus variables de entorno.

┌──────────────────────────────────────────────────────────┐
│  PASO 4: Renovar PAT antes de vencimiento (Opcional)    │
└──────────────────────────────────────────────────────────┘

// Crear nuevo token (Día 85-90)
POST /api/ubl2.1/tokens
Authorization: Bearer {tu_pat_actual}

// Actualizar .env con nuevo token
APIDIAN_API_TOKEN=1|nuevoToken...

// Revocar token antiguo
DELETE /api/ubl2.1/tokens/{id_token_antiguo}
Authorization: Bearer {nuevo_pat}
```

---

## Ejemplos Prácticos

### Ejemplo 1: Integración con Python

```python
import requests
import os

# ============================================
# CONFIGURACIÓN INICIAL (Solo una vez)
# ============================================

def crear_pat_token():
    """
    Paso 1: Login para obtener token de sesión temporal
    """
    login_url = "https://api.apidian.com/api/login"
    login_data = {
        "email": os.getenv("APIDIAN_EMAIL"),
        "password": os.getenv("APIDIAN_PASSWORD")
    }
    
    response = requests.post(login_url, json=login_data)
    session_token = response.json()["token"]
    
    """
    Paso 2: Crear Personal Access Token (PAT)
    """
    pat_url = "https://api.apidian.com/api/ubl2.1/tokens"
    headers = {"Authorization": f"Bearer {access_token}"}
    pat_data = {
        "name": "Script Python - Producción",
        "expires_in_days": 90
    }
    
    pat_url = "https://api.apidian.com/api/ubl2.1/tokens"
    pat_data = {"name": "Token Producción Python"}
    headers = {"Authorization": f"Bearer {session_token}"}
    
    response = requests.post(pat_url, json=pat_data, headers=headers)
    pat_token = response.json()["data"]["token"]
    
    # ⚠️ GUARDAR EN VARIABLE DE ENTORNO
    print(f"PAT Token: {pat_token}")
    print("Guarda este token en tu .env como APIDIAN_PAT_TOKEN")
    
    return pat_token

# ============================================
# USO DIARIO (Con PAT guardado)
# ============================================

class ApidianClient:
    def __init__(self):
        self.base_url = "https://api.apidian.com/api/ubl2.1"
        self.token = os.getenv("APIDIAN_PAT_TOKEN")
        self.headers = {"Authorization": f"Bearer {self.token}"}
    
    def listar_empresas(self):
        url = f"{self.base_url}/companies"
        response = requests.get(url, headers=self.headers)
        return response.json()
    
    def crear_factura(self, factura_data):
        url = f"{self.base_url}/invoices"
        response = requests.post(url, json=factura_data, headers=self.headers)
        return response.json()

# Uso
client = ApidianClient()
empresas = client.listar_empresas()
print(empresas)
```

### Ejemplo 2: Integración con JavaScript/Node.js

```javascript
// ============================================
// CONFIGURACIÓN INICIAL (Solo una vez)
// ============================================

const axios = require('axios');

async function crearPATToken() {
  // Paso 1: Login
  const loginResponse = await axios.post('https://api.apidian.com/api/login', {
    email: process.env.APIDIAN_EMAIL,
    password: process.env.APIDIAN_PASSWORD
  });
  
  const sessionToken = loginResponse.data.token;
  
  // Paso 2: Crear PAT
  const patResponse = await axios.post(
    'https://api.apidian.com/api/ubl2.1/tokens',
    { name: 'App Node.js - Producción' },
    { headers: { Authorization: `Bearer ${sessionToken}` } }
  );
  
  const patToken = patResponse.data.data.token;
  console.log('PAT Token:', patToken);
  console.log('Guarda este token en tu .env como APIDIAN_PAT_TOKEN');
  
  return patToken;
}

// ============================================
// USO DIARIO (Con PAT guardado)
// ============================================

class ApidianClient {
  constructor() {
    this.baseURL = 'https://api.apidian.com/api/ubl2.1';
    this.axiosInstance = axios.create({
      baseURL: this.baseURL,
      headers: {
        'Authorization': `Bearer ${process.env.APIDIAN_PAT_TOKEN}`,
        'Content-Type': 'application/json'
      }
    });
  }
  
  async listarEmpresas() {
    const response = await this.axiosInstance.get('/companies');
    return response.data;
  }
  
  async crearFactura(facturaData) {
    const response = await this.axiosInstance.post('/invoices', facturaData);
    return response.data;
  }
}

// Uso
const client = new ApidianClient();
const empresas = await client.listarEmpresas();
console.log(empresas);
```

### Ejemplo 3: cURL

```bash
# ============================================
# PASO 1: Login
# ============================================

curl -X POST https://api.apidian.com/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "password",
  -H "Content-Type: application/json" \
  -d '{
    "email": "usuario@ejemplo.com",
    "password": "contraseña"
  }'

# Respuesta: { "token": "eyJ0eXAi..." }

# ============================================
# PASO 2: Crear PAT
# ============================================

curl -X POST https://api.apidian.com/api/ubl2.1/tokens \
  -H "Authorization: Bearer eyJ0eXAi..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mi Token de Producción"
  }'

# Respuesta: { "data": { "token": "1|aB3cD4eF5g..." } }

# ============================================
# PASO 3: Usar PAT (próximos 90 días)
# ============================================

curl -X GET https://api.apidian.com/api/ubl2.1/companies \
  -H "Authorization: Bearer 1|aB3cD4eF5g..."
```

---

## Casos de Uso

### ✅ Personal Access Tokens (PAT) - Sistema Único

| Caso de Uso | Ventajas |
|-------------|----------|
| **App Móvil** | Token de larga duración (90 días) |
| **Integración ERP** | Token persistente, fácil de configurar |
| **Script Automatizado** | Un solo token para todo el proceso |
| **CLI/Línea de Comandos** | Simple, solo requiere Bearer token |
| **Webhook Entrante** | Token dedicado por webhook |
| **Testing/QA** | Tokens separados por ambiente |
| **Desarrollo Local** | Token de desarrollo sin afectar producción |

**Características:**
- ✅ Token dura 90 días (configurable hasta 365)
- ✅ Gestión desde UI o API
- ✅ Rate limiting: 10 tokens/día por usuario
- ✅ Auditoría completa de creación
- ✅ Revocación instantánea
- ✅ Sin necesidad de client_id/client_secret

---

## Mejores Prácticas

### 🔒 Seguridad

```bash
# ✅ HACER
# Almacenar tokens en variables de entorno
export APIDIAN_PAT_TOKEN="1|aB3cD4eF5g..."

# ✅ HACER
# Usar archivos .env (no commitear)
echo "APIDIAN_PAT_TOKEN=1|aB3cD4eF5g..." >> .env
echo ".env" >> .gitignore

# ❌ NO HACER
# Hardcodear tokens en el código
const token = "1|aB3cD4eF5g..."; // ❌ MAL

# ❌ NO HACER
# Compartir tokens en repositorios públicos
```

### 📊 Gestión de Tokens

```python
# ✅ HACER: Un token por propósito
tokens = {
    "produccion": "Token para app en producción",
    "staging": "Token para ambiente de pruebas",
    "desarrollo": "Token para desarrollo local"
}

# ✅ HACER: Nombres descriptivos
POST /api/ubl2.1/tokens
{
  "name": "App Mobile Android - Producción v2.3",
  "expires_in_days": 90
}

# ✅ HACER: Revocar tokens comprometidos inmediatamente
DELETE /api/ubl2.1/tokens/{id}
```

### ⚡ Rendimiento

```javascript
// ✅ HACER: Reutilizar cliente HTTP
const client = new ApidianClient(); // Una instancia
client.listarEmpresas();
client.crearFactura();

// ❌ NO HACER: Crear cliente en cada petición
function listar() {
  const client = new ApidianClient(); // ❌ Ineficiente
  return client.listarEmpresas();
}
```

### 🧪 Testing

```python
# ✅ HACER: Tokens separados por ambiente
# .env.production
APIDIAN_PAT_TOKEN=1|token_produccion

# .env.staging
APIDIAN_PAT_TOKEN=1|token_staging

# .env.development
APIDIAN_PAT_TOKEN=1|token_desarrollo
```

### 🔄 Renovación de Tokens

```python
# ✅ HACER: Renovar antes del vencimiento
# Día 85-90 de los 90 días

def renovar_token():
    # Crear nuevo token con el actual
    nuevo = crear_pat_token()
    
    # Actualizar .env
    actualizar_env("APIDIAN_PAT_TOKEN", nuevo)
    
    # Revocar token antiguo (opcional)
    revocar_token_antiguo()
```

---

## Troubleshooting

### Error 401: Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

**Causas:**
- ❌ Token expirado (>90 días)
- ❌ Token revocado manualmente
- ❌ Token mal formado
- ❌ Falta header `Authorization`

**Solución:**
```bash
# Verificar estado del token
GET /api/ubl2.1/tokens
Authorization: Bearer {tu_token}

# Si expiró, crear nuevo
# Paso 1: Login
POST /api/login
{"email": "...", "password": "..."}

# Paso 2: Crear nuevo PAT
POST /api/ubl2.1/tokens
Authorization: Bearer {session_token}
{"name": "Nuevo Token Producción"}
```

### Error 429: Too Many Requests

```json
{
  "success": false,
  "message": "Límite de creación de tokens alcanzado. Máximo 10 por día.",
  "error": "Rate limit exceeded"
}
```

**Causa:**
- ❌ Superaste 10 creaciones de tokens en 24h

**Solución:**
- ✅ Espera 24 horas
- ✅ Usa tokens existentes (listar con `GET /api/ubl2.1/tokens`)
- ✅ Revoca tokens innecesarios primero

### Error 422: Validation Error

```json
{
  "success": false,
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "name": ["El campo name es obligatorio."]
  }
}
```

**Causa:**
- ❌ `name` vacío o muy largo (>255 caracteres)

**Solución:**
```json
// ✅ Correcto
{
  "name": "Mi Token Producción ERP"
}

// ❌ Incorrecto
{
  "name": ""
}
```

---

## Resumen Ejecutivo

### Para Desarrolladores: Sigue Este Flujo

```
1️⃣  Registrarse/Login una vez
    POST /api/register o POST /api/login → token de sesión

2️⃣  Crear PAT
    POST /api/ubl2.1/tokens → PAT de 90 días

3️⃣  Guardar PAT en .env
    APIDIAN_PAT_TOKEN=1|tu_token

4️⃣  Usar PAT en todas las peticiones
    Authorization: Bearer 1|tu_token

5️⃣  Renovar cada 85-90 días
    Repetir pasos 1-4 antes del vencimiento
```

### Endpoints Principales

```
✅ REGISTRO/LOGIN:        /api/register, /api/login
✅ GESTIÓN DE TOKENS:     /api/ubl2.1/tokens
✅ API DE NEGOCIO:        /api/ubl2.1/{recurso}
```

### Configuración de Expiración

| Tipo de Token | Duración por Defecto |
|--------------|---------------------|
| Token de Sesión (Login) | 90 días |
| Personal Access Token | 90 días (configurable) |

**Nota:** El token de sesión del login solo se usa para crear tu PAT. Después usas exclusivamente el PAT.

---

## Preguntas Frecuentes (FAQ)

### ¿Puedo tener múltiples tokens activos?
✅ Sí, puedes crear hasta 10 tokens por día. Útil para separar ambientes (dev, staging, prod).

### ¿Qué pasa si mi token se compromete?
🔒 Revócalo inmediatamente con `DELETE /api/ubl2.1/tokens/{id}` y crea uno nuevo.

### ¿Los tokens expiran automáticamente?
✅ Sí, después de 90 días. Recibirás notificación antes del vencimiento.

### ¿Necesito renovar el token manualmente?
⚠️ Sí. A diferencia de OAuth2 con refresh automático, PAT requiere renovación manual antes del vencimiento.

### ¿Puedo cambiar la duración del token?
⚠️ No directamente. La duración se configura en el servidor (90 días por defecto, máximo 365).

---

## Soporte

Para preguntas o problemas:
- 📧 Email: soporte@apidian.com
- 📚 Documentación: https://docs.apidian.com
- 🐛 Issues: https://github.com/lopezsoft/apidian/issues

---

**Última actualización:** 27 de Enero, 2026  
**Versión del documento:** 2.0 (Sistema PAT único)
