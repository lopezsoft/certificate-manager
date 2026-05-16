# 📋 Runbook: Viafirma PKCS#10 — Respuesta a Incidentes

> Última actualización: 2026-05-15
> Módulo: `App\Modules\Viafirma`
> Autor: Equipo Backend LOPEZSOFT

---

## 1. Diagnóstico Rápido

```bash
# Health check completo
php artisan viafirma:health-check

# Ver solicitudes en estado de fallo
php artisan tinker --execute="
  use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
  echo ViafirmaCertificateRequest::where('internal_state', 'FAILED')->count();
"

# Logs del módulo (últimas 100 líneas)
tail -n 100 storage/logs/laravel.log | grep viafirma

# Logs del watchdog
tail -n 50 storage/logs/scheduled-viafirma-watchdog.log
tail -n 50 storage/logs/scheduled-viafirma-purge.log
```

---

## 2. Incidentes Comunes

### 2.1 Circuit Breaker OPEN

**Síntomas:** El health check muestra `🔴 Circuit Breaker: OPEN`. Nuevos pollings no se ejecutan.

**Causa:** ≥5 errores 5xx/timeout consecutivos del API Viafirma.

**Acciones:**
1. Verificar estado del servicio Viafirma (contactar proveedor si es necesario)
2. Esperar la ventana de recuperación automática (5 min por defecto)
3. Para forzar reset: `php artisan tinker --execute="Cache::forget('viafirma:circuit_breaker:open'); Cache::forget('viafirma:circuit_breaker:failures');"`
4. Verificar con `php artisan viafirma:health-check`

---

### 2.2 Solicitudes en `accreditation` > 24h

**Síntomas:** Alerta en health check `⚠️ Solicitudes en accreditation > 24h`.

**Causa:** El cliente no ha completado el KYC (foto documento, selfie).

**Acciones:**
1. Verificar que la notificación se envió (tabla `notifications`, tipo `viafirma_accreditation_pending`)
2. Contactar al cliente vía email/teléfono
3. Si hay problemas con la URL KYC, verificar `public_id` en la entidad
4. URL KYC: `{VIAFIRMA_RA_DOWNLOAD_URL}/public/{public_id}`

---

### 2.3 Solicitudes Huérfanas (Stalled)

**Síntomas:** `⚠️ Solicitudes huérfanas (stalled): N` en health check.

**Causa:** El `PollViafirmaStatusJob` no se reagendó (crash del worker, despliegue, etc.).

**Acciones:**
1. El watchdog (`ReviveStalledViafirmaPollsJob`) las re-arma automáticamente cada 15 min
2. Para forzar inmediato: `php artisan tinker --execute="App\Modules\Viafirma\Infrastructure\Jobs\ReviveStalledViafirmaPollsJob::dispatch();"`
3. Verificar que el queue worker está activo: `php artisan queue:work --status`

---

### 2.4 Error en Ensamblaje P12

**Síntomas:** Solicitud en estado `FAILED` con `last_error_code = ASSEMBLE_FAILED`.

**Causa posibles:**
- P7B corrupto o vacío
- Llave privada no corresponde al certificado
- OpenSSL no puede parsear el bundle PKCS#7

**Acciones:**
1. Verificar el P7B: `php artisan tinker --execute="Storage::disk('local')->exists('viafirma/p7b/{cod_request}.p7b');"`
2. Verificar la llave en vault: `php artisan tinker --execute="app(App\Modules\Viafirma\Domain\Contracts\KeyVault::class)->exists('{key_vault_ref}');"`
3. Si el P7B es válido pero el ensamblaje falló, puede ser un problema de formato. Intentar convertir: `openssl pkcs7 -inform DER -in file.p7b -print_certs -out certs.pem`
4. Re-intentar manualmente: `php artisan tinker --execute="App\Modules\Viafirma\Infrastructure\Jobs\AssembleP12Job::dispatch({id});"`

---

### 2.5 Feature Flag — Desactivar en Emergencia

```bash
# Desactivar inmediatamente
# Agregar a .env:
VIAFIRMA_PKCS10_ENABLED=false

# Limpiar cache
php artisan config:clear

# Verificar
php artisan viafirma:health-check
```

**Para rollout gradual:**
```
VIAFIRMA_PKCS10_ENABLED=true
VIAFIRMA_PKCS10_ROLLOUT_PCT=10   # Solo 10% de empresas
```

---

### 2.6 Purga de Llaves — Verificación

```bash
# Verificar cuántas llaves están pendientes de purga
php artisan tinker --execute="
  use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
  echo 'Pendientes: ' . ViafirmaCertificateRequest::whereIn('internal_state', ['COMPLETED','FAILED','EXPIRED'])
    ->where('key_vault_ref', '!=', 'PURGED')
    ->whereNotNull('key_vault_ref')
    ->count();
"

# Forzar purga manual
php artisan tinker --execute="App\Modules\Viafirma\Infrastructure\Jobs\PurgeExpiredKeysJob::dispatch();"
```

---

## 3. Contactos

| Rol | Contacto |
|-----|----------|
| Equipo Backend | Equipo de desarrollo LOPEZSOFT |
| Viafirma Soporte | Proveedor Viafirma Colombia |
| AWS (si aplica) | Console AWS / soporte premium |

---

## 4. Comandos Artisan del Módulo

| Comando | Descripción |
|---------|-------------|
| `viafirma:health-check` | Diagnóstico completo del módulo |
| `viafirma:migrate` | Ejecutar migraciones del módulo |
| `schedule:list` | Verificar crons registrados |
| `queue:work` | Procesar cola de jobs |

---

## 5. Flujo de Estados (Referencia)

```
DRAFT → CSR_GENERATED → SUBMITTED → POLLING → READY_TO_DOWNLOAD → DOWNLOADED → ASSEMBLED → COMPLETED
                                       ↓                                                        
                             FAILED / FAILED_RECOVERABLE / EXPIRED                              
```

**Estados remotos Viafirma:** `rues_check → accreditation → accreditation_check → accreditation_completed → accreditation_verified → proposeFor → proposedToAcceptance → inProcess → All_Ok → Generated_Not_Downloaded`
