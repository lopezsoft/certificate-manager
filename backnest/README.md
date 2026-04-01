# backnest — Certificate Manager API (NestJS)

Migración del backend de Laravel a **NestJS v10 + Fastify + TypeORM + PostgreSQL**.

---

## Prerequisitos

| Herramienta | Versión mínima |
|-------------|----------------|
| Node.js | 20.x |
| npm | 10.x |
| PostgreSQL | 15+ |
| Redis | 7+ |

---

## Instalación local

```bash
# 1. Instalar dependencias
npm ci --legacy-peer-deps

# 2. Copiar variables de entorno
cp .env.example .env
# Editar .env con tus valores locales

# 3. Ejecutar migraciones (primera vez)
npm run migration:run

# 4. Iniciar en modo desarrollo (hot-reload)
npm run start:dev
```

La API estará disponible en `http://localhost:3000`.  
Swagger UI en `http://localhost:3000/api/docs`.

---

## Variables de entorno (`.env`)

```dotenv
APP_PORT=3000
APP_URL=http://localhost:3000
APP_ENV=local

DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=certificate_manager
DB_USERNAME=postgres
DB_PASSWORD=secret
DB_SYNCHRONIZE=false
DB_LOGGING=true

JWT_SECRET=your_jwt_secret_here
JWT_EXPIRES_IN=1d

REDIS_HOST=localhost
REDIS_PORT=6379

MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_NAME=Certificate Manager
MAIL_FROM_ADDRESS=noreply@example.com

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_REGION=us-east-1

ADMIN_EMAIL=admin@example.com
```

---

## Scripts disponibles

| Comando | Descripción |
|---------|-------------|
| `npm run start:dev` | Desarrollo con hot-reload |
| `npm run start:prod` | Producción desde `dist/` |
| `npm run build` | Compilar TypeScript |
| `npm run test` | Tests unitarios |
| `npm run test:e2e` | Tests end-to-end |
| `npm run migration:generate -- src/database/migrations/NombreMigracion` | Generar migración |
| `npm run migration:run` | Ejecutar migraciones pendientes |
| `npm run migration:revert` | Revertir última migración |

---

## Docker — Desarrollo

```bash
# Levantar stack completo (API + PostgreSQL + Redis + Mailpit)
docker compose -f docker-compose.dev.yml up --build

# Mailpit (captura de emails): http://localhost:8025
```

## Docker — Producción

```bash
# Build y levantar producción
docker compose up --build -d

# Ver logs
docker compose logs -f api
```

---

## Estructura del proyecto

```
backnest/
├── src/
│   ├── main.ts                   # Bootstrap Fastify
│   ├── app.module.ts             # Módulo raíz
│   ├── config/                   # Configuraciones por dominio
│   ├── common/
│   │   ├── decorators/           # @CurrentUser, @CurrentCompany
│   │   ├── dto/                  # PaginationQueryDto
│   │   ├── filters/              # LaravelExceptionFilter
│   │   ├── interceptors/         # LaravelResponseInterceptor, LaravelPaginationInterceptor
│   │   └── utils/                # date-formatter.util
│   ├── shared/
│   │   └── logger/               # SmartLoggerService
│   ├── database/
│   │   ├── database.config.ts    # TypeORM async factory
│   │   └── entities/             # 23 entidades TypeORM
│   └── modules/
│       ├── auth/                 # JWT auth + guards
│       ├── users/                # CRUD usuarios
│       ├── companies/            # CRUD empresas
│       ├── locations/            # Países, departamentos, ciudades
│       ├── master/               # Catálogos maestros
│       ├── certificates/         # Solicitudes de certificados
│       ├── files/                # Gestión de archivos
│       ├── notifications/        # Notificaciones polimórficas
│       ├── mail/                 # Envío de emails (Handlebars)
│       ├── webhooks/             # Endpoints webhook + entregas
│       ├── ai/                   # OCR con AWS Textract
│       ├── scheduler/            # Tareas programadas (cron)
│       ├── tokens/               # Personal Access Tokens
│       └── crud/                 # Configuraciones generales
├── Dockerfile
├── docker-compose.yml            # Producción
├── docker-compose.dev.yml        # Desarrollo
├── typeorm.config.ts             # CLI de migraciones
└── .env.example
```

---

## Compatibilidad de API con Laravel

- Todas las rutas mantienen el mismo path que el backend Laravel  
- Prefijo global: `/api` → rutas reales: `/api/auth/login`, `/api/certificates`, etc.
- Formato de respuesta idéntico a Laravel (`{ success, dataRecords, message }`)
- Paginación idéntica a Laravel (`{ data, current_page, total, per_page, links, ... }`)
- Formato de fechas: `dd-MM-yyyy hh:mm:ss aa` (ej: `"26-03-2026 10:00:00 am"`)
