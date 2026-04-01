# Matriz de Compatibilidad de Endpoints — Laravel → NestJS

> **Fecha:** 2026-03-27  
> **Build NestJS:** ✅ Verde (0 errores TypeScript)  
> **Prefijo global:** `/api/v1`

## Leyenda

| Símbolo | Significado |
|---------|-------------|
| ✅ | Migrado — método y path coinciden exactamente |
| ⚡ | Mejorado — funcionalidad adicional en NestJS |
| ➕ | Adición — existe en NestJS, no en Laravel (backward-compatible) |
| ⚠️ | Diferencia menor — comportamiento ligeramente distinto |
| ❌ | Gap — existe en Laravel, no implementado en NestJS |

---

## 1. Autenticación (`auth.php` + `auth-api.php`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 1 | POST | `/api/v1/auth/login` | `POST auth/login` | ✅ | auth.controller.ts |
| 2 | GET | `/api/v1/auth/logout` | `GET auth/logout` | ✅ | auth.controller.ts |
| 3 | GET | `/api/v1/auth/user` | `GET auth/user` | ✅ | auth.controller.ts |
| 4 | POST | `/api/v1/register` | `POST register` | ✅ | auth.controller.ts |
| 5 | POST | `/api/v1/forgot-password` | `POST forgot-password` | ✅ | auth.controller.ts |
| 6 | POST | `/api/v1/reset-password` | `POST reset-password` | ✅ | auth.controller.ts |
| 7 | GET | `/api/v1/verify-email/{id}/{hash}` | `GET verify-email/:id/:hash` | ✅ | auth.controller.ts |
| 8 | POST | `/api/v1/email/verification-notification` | `POST email/verification-notification` | ⚠️ | auth.controller.ts |

> **⚠️ Nota #8:** En Laravel la ruta es `middleware:guest` (pública). En NestJS está protegida con `JwtAuthGuard`. Evaluar si el frontend la llama antes o después de login.

---

## 2. Perfil de usuario (`api.php → profile`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 9 | GET | `/api/v1/profile/` | `GET profile` (alias de `GET auth/user`) | ✅ | auth.controller.ts |
| 10 | GET | `/api/v1/profile/types` | `GET profile/types` | ✅ | auth.controller.ts |
| 11 | PUT | `/api/v1/profile/{id}` | `PUT profile/:id` | ✅ | auth.controller.ts |
| 12 | GET | `/api/v1/auth/types` | `GET auth/types` | ➕ | auth.controller.ts |

---

## 3. Catálogos públicos (`public.php`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 13 | GET | `/api/v1/countries` | `GET countries` | ✅ | locations.controller.ts |
| 14 | GET | `/api/v1/departments` | `GET departments?country_id=` | ⚠️ | locations.controller.ts |
| 15 | GET | `/api/v1/cities` | `GET cities?department_id=` | ⚠️ | locations.controller.ts |
| 16 | GET | `/api/v1/identity-documents` | `GET identity-documents` | ✅ | master.controller.ts |
| 17 | GET | `/api/v1/organization-type` | `GET organization-type` | ✅ | master.controller.ts |
| 18 | GET | — | `GET postal-codes/:cityId` | ➕ | locations.controller.ts |
| 19 | GET | — | `GET user-types` | ➕ | master.controller.ts |
| 20 | GET | — | `GET languages` | ➕ | master.controller.ts |

> **⚠️ Nota #14 y #15:** En Laravel, `country_id` y `department_id` llegan como query param opcional (si no se envía puede devolver todo). En NestJS usa `ParseIntPipe` que falla si el param está ausente. **Acción recomendada:** hacer el pipe opcional con `@Query('country_id') countryId?: number`.

---

## 4. Solicitudes de Certificado (`api.php → certificate-request`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 21 | POST | `/api/v1/certificate-request/` | `POST certificate-request` | ✅ | certificates.controller.ts |
| 22 | GET | `/api/v1/certificate-request/` | `GET certificate-request` | ✅ | certificates.controller.ts |
| 23 | GET | `/api/v1/certificate-request/all` | `GET certificate-request/all` | ✅ | certificates.controller.ts |
| 24 | GET | `/api/v1/certificate-request/{id}` | `GET certificate-request/:id` | ✅ | certificates.controller.ts |
| 25 | PUT | `/api/v1/certificate-request/{id}` | `PUT certificate-request/:id` | ✅ | certificates.controller.ts |
| 26 | PUT | `/api/v1/certificate-request/{id}/status` | `PUT certificate-request/:id/status` | ✅ | certificates.controller.ts |
| 27 | DELETE | `/api/v1/certificate-request/{id}` | `DELETE certificate-request/:id` | ✅ | certificates.controller.ts |
| 28 | POST | `/api/v1/certificate-request/{id}/send-mail` | `POST certificate-request/:id/send-mail` | ✅ | certificates.controller.ts |

---

## 5. Archivos por Solicitud (`api.php → certificate-request/{id}/files`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 29 | POST | `/api/v1/certificate-request/{id}/files` | `POST certificate-request/:id/files` | ✅ | file-manager.controller.ts |
| 30 | DELETE | `/api/v1/certificate-request/{id}/files/{fileId}` | `DELETE certificate-request/:id/files/:fileId` | ✅ | file-manager.controller.ts |
| 31 | GET | — | `GET certificate-request/:id/files` | ➕ | file-manager.controller.ts |

---

## 6. Empresa (`api.php → company`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 32 | GET | `/api/v1/company/` | `GET company` | ✅ | companies.controller.ts |
| 33 | GET | `/api/v1/company/settings/` | `GET company/settings` | ✅ | companies.controller.ts |
| 34 | PUT | `/api/v1/company/settings/` | `PUT company/settings` | ✅ | companies.controller.ts |
| — | GET | — | `GET companies` | ➕ Admin | companies.controller.ts |
| — | GET | — | `GET companies/:id` | ➕ Admin | companies.controller.ts |
| — | POST | — | `POST companies` | ➕ Admin | companies.controller.ts |
| — | PUT | — | `PUT companies/:id` | ➕ Admin | companies.controller.ts |
| — | DELETE | — | `DELETE companies/:id` | ➕ Admin | companies.controller.ts |

---

## 7. Configuración / Reportes (`api.php → settings/reports`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 35 | GET | `/api/v1/settings/reports/` | `GET settings/reports` | ✅ | crud.controller.ts |
| 36 | PUT | `/api/v1/settings/reports/{id}` | `PUT settings/reports/:id` | ✅ | crud.controller.ts |
| — | GET | — | `GET settings` | ➕ | crud.controller.ts |
| — | GET | — | `GET settings/company` | ➕ | crud.controller.ts |
| — | GET | — | `GET settings/report-header` | ➕ Alias | crud.controller.ts |

---

## 8. CRUD Genérico (`api.php → crud`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 37 | GET | `/api/v1/crud` | ❌ No implementado | ❌ | — |
| 38 | POST | `/api/v1/crud` | ❌ No implementado | ❌ | — |
| 39 | GET | `/api/v1/crud/{id}` | ❌ No implementado | ❌ | — |
| 40 | PUT | `/api/v1/crud/{id}` | ❌ No implementado | ❌ | — |
| 41 | DELETE | `/api/v1/crud/{id}` | ❌ No implementado | ❌ | — |

> **Nota:** El `TableCrudController` de Laravel es una operación CRUD genérica sobre tablas de configuración. En NestJS esto está cubierto parcialmente bajo `/settings`. Si el frontend consume `/crud`, requiere implementación.

---

## 9. Consumo de Documentos (`api.php → consume`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 42 | GET | `/api/v1/consume/{year}` | `GET consume/:year` | ✅ | consume.controller.ts |
| 43 | GET | `/api/v1/consume/{year}/{month}` | `GET consume/:year/:month` | ✅ | consume.controller.ts |

---

## 10. Tokens PAT (`api.php → tokens`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 44 | GET | `/api/v1/tokens/` | `GET tokens` | ✅ | tokens.controller.ts |
| 45 | POST | `/api/v1/tokens/` | `POST tokens` | ✅ | tokens.controller.ts |
| 46 | POST | `/api/v1/tokens/revoke-all` | `POST tokens/revoke-all` | ✅ | tokens.controller.ts |
| 47 | GET | `/api/v1/tokens/{id}` | `GET tokens/:id` | ✅ | tokens.controller.ts |
| 48 | DELETE | `/api/v1/tokens/{id}` | `DELETE tokens/:id` | ✅ | tokens.controller.ts |
| 49 | POST | `/api/v1/tokens/{id}/renew` | `POST tokens/:id/renew` | ✅ | tokens.controller.ts |
| — | DELETE | — | `DELETE tokens` (revokeAll alias) | ➕ | tokens.controller.ts |

---

## 11. Notificaciones (`api.php → notifications`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 50 | GET | `/api/v1/notifications/` | `GET notifications` | ✅ | notifications.controller.ts |
| 51 | POST | `/api/v1/notifications/read-all` | `POST notifications/read-all` | ✅ | notifications.controller.ts |
| 52 | POST | `/api/v1/notifications/{id}/read` | `POST notifications/:id/read` | ✅ | notifications.controller.ts |
| 53 | GET | `/api/v1/certificates/expiring` | `GET certificates/expiring` | ✅ | notifications.controller.ts |
| 54 | POST | `/api/v1/admin/certificates/notify-now` | `POST admin/certificates/notify-now` | ✅ | notifications.controller.ts |
| — | GET | — | `GET notifications/unread` | ➕ | notifications.controller.ts |

---

## 12. Webhooks (`api.php → webhooks`)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| 55 | GET | `/api/v1/webhooks/events` | `GET webhooks/events` | ✅ | webhooks.controller.ts |
| 56 | GET | `/api/v1/webhooks/` | `GET webhooks` | ✅ | webhooks.controller.ts |
| 57 | POST | `/api/v1/webhooks/` | `POST webhooks` | ✅ | webhooks.controller.ts |
| 58 | GET | `/api/v1/webhooks/{id}` | `GET webhooks/:id` | ✅ | webhooks.controller.ts |
| 59 | PUT | `/api/v1/webhooks/{id}` | `PUT webhooks/:id` | ✅ | webhooks.controller.ts |
| 60 | DELETE | `/api/v1/webhooks/{id}` | `DELETE webhooks/:id` | ✅ | webhooks.controller.ts |
| 61 | POST | `/api/v1/webhooks/{id}/rotate-secret` | `POST webhooks/:id/rotate-secret` | ✅ | webhooks.controller.ts |
| 62 | GET | `/api/v1/webhooks/{id}/deliveries` | `GET webhooks/:id/deliveries` | ✅ | webhooks.controller.ts |

---

## 13. IA / OCR (módulo extra NestJS)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| — | GET | — | `GET ai/results/:certificateRequestId` | ➕ | ai.controller.ts |
| — | POST | — | `POST ai/analyze` | ➕ | ai.controller.ts |

---

## 14. Usuarios (módulo admin NestJS)

| # | Método | Ruta Laravel | Ruta NestJS | Estado | Archivo NestJS |
|---|--------|--------------|-------------|--------|---------------|
| — | GET | — | `GET users` | ➕ Admin | users.controller.ts |
| — | GET | — | `GET users/:id` | ➕ Admin | users.controller.ts |
| — | POST | — | `POST users` | ➕ Admin | users.controller.ts |
| — | PUT | — | `PUT users/:id` | ➕ Admin | users.controller.ts |
| — | DELETE | — | `DELETE users/:id` | ➕ Admin | users.controller.ts |

---

## Resumen Ejecutivo

| Categoría | Total Laravel | Migrados ✅ | Mejorados ⚡ | Adiciones ➕ | Gaps ❌ |
|-----------|:--:|:--:|:--:|:--:|:--:|
| Auth | 8 | 7 | 0 | 1 | 0 |
| Perfil | 3 | 3 | 0 | 1 | 0 |
| Catálogos públicos | 5 | 3 | 2 | 3 | 0 |
| Certificate Request | 8 | 8 | 0 | 0 | 0 |
| Archivos | 2 | 2 | 0 | 1 | 0 |
| Empresa | 3 | 3 | 0 | 5 | 0 |
| Settings/Reportes | 2 | 2 | 0 | 3 | 0 |
| CRUD Genérico | 5 | 0 | 0 | 0 | 5 |
| Consumo | 2 | 2 | 0 | 0 | 0 |
| Tokens PAT | 6 | 6 | 0 | 1 | 0 |
| Notificaciones | 5 | 5 | 0 | 1 | 0 |
| Webhooks | 8 | 8 | 0 | 0 | 0 |
| **TOTAL** | **57** | **49** | **0** | **16** | **5** |

### Paridad de compatibilidad: **49/57 = 86%**

---

## Acciones Pendientes para llegar al 100%

### 🔴 Alta Prioridad (Bloqueante si el frontend los usa)

#### 1. Implementar `/api/v1/crud` (5 endpoints faltantes)
Crear `CrudGenericController` o `TableCrudController` que replique `TableCrudController` de Laravel:
```ts
@Controller('crud')
export class TableCrudController {
  // GET /crud            → listar registros paginados
  // POST /crud           → crear registro
  // GET /crud/:id        → obtener registro
  // PUT /crud/:id        → actualizar registro
  // DELETE /crud/:id     → eliminar registro
}
```
Requiere analizar qué tabla/entidad maneja `TableCrudController` de Laravel.

### 🟡 Media Prioridad (Diferencias de comportamiento)

#### 2. Hacer opcionales los params de `departments` y `cities`
En [backnest/src/modules/locations/locations.controller.ts](../src/modules/locations/locations.controller.ts):
```ts
// Cambiar:
async departments(@Query('country_id', ParseIntPipe) countryId: number)
// Por:
async departments(@Query('country_id') countryId?: string)
// Y manejar la ausencia del param en el servicio
```

#### 3. Revisar `email/verification-notification`
Decidir si debe ser pública (como en Laravel) o protegida (como en NestJS actualmente). Si el frontend la llama antes del login, debe quitarse `JwtAuthGuard`.

### 🟢 Baja Prioridad (Mejoras internas)

- Implementar lógica real de `sendMail` en `certificates.service.ts` (actualmente es un placeholder que solo hace log)
- Conectar el scheduler de vencimiento con envío real de emails via `MailService`
- Implementar entrega real de notificaciones en `triggerExpiringNotificationsNow`  

---

## Errores TypeScript Corregidos en Esta Sesión

| Error | Causa | Fix Aplicado |
|-------|-------|-------------|
| `date-fns-tz` not found | Paquete no instalado | Reemplazado con `dayjs` + plugins timezone/utc |
| `timezone` no existe en PostgresConnectionOptions | TypeORM no lo soporta directo | Movido a `extra.options` `-c TimeZone=...` |
| `c.settings` no existe en Company | Nombre incorrecto | Corregido a `c.settingCompanies` |
| `n.notifiable` no existe en Notification | Relación polimórfica no tipada | Removida la relación `OneToMany` de User |
| `active: true` no asignable a `number` | Entidades usan `active: number` (0/1) | Cambiado a `active: 1` en queries y creates |
| `active: boolean` en `userRepo.create()` | DTO boolean vs entidad number | Convertido `(dto.active ?? true) ? 1 : 0` |
| `nit` no existe en Company | Campo no mapeado | `dto.nit` → `dni` (columna real en BD) |
| `active` no existe en Company | Campo inexistente en entidad | Removido del create/update de Company |
| Import path listener incorrecto | Path relativo equivocado | `'./notifications.service'` → `'../notifications.service'` |
| `deliveredAt = undefined` | Tipo `Date` no acepta `undefined` | Asignación condicional `if (ok) delivery.deliveredAt = new Date()` |
