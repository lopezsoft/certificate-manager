# 📋 Runbook: Viafirma PKCS#10 — Respuesta a Incidentes

> Última actualización: 2026-08-19
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

**Causa:** El suscriptor final (cliente de la empresa) no ha completado el KYC (foto documento, selfie) en el link que Viafirma le envió directamente por email.

> ⚠️ Nosotros consultamos a Viafirma (RA), no al revés. "Contactar" aquí significa que
> el operador RA (nuestro equipo) contacta a **la empresa dueña de la solicitud**, no a Viafirma.

**Acciones:**
1. Verificar el link real capturado: `viafirma_certificate_request_states.kyc_accreditation_link` (columna, no construir URL manualmente).
2. Confirmar que el correo automático a la empresa se envió: buscar en logs `viafirma.kyc_link_job.company_notified` (éxito) o `viafirma.kyc_link_job.no_company_email` / `viafirma.kyc_link_job.notify_failed` (fallos — revisar `companies.email` de la solicitud).
3. Si el correo falló o la empresa lo perdió, reenviar manualmente el valor de `kyc_accreditation_link` — **no reconstruir la URL** con `{VIAFIRMA_RA_DOWNLOAD_URL}/public/{public_id}` (patrón obsoleto, no es el link real de onboarding).
4. **Ya no hay expiración automática por tiempo** — desde el fix de 2026-08-19, una solicitud puede permanecer días en `accreditation` sin que el sistema la marque `EXPIRED` ni deje de consultarla. Esta alerta es informativa, no indica que el polling vaya a detenerse solo.

---

### 2.3 Solicitudes Huérfanas (Stalled)

**Síntomas:** `⚠️ Solicitudes huérfanas (stalled): N` en health check.

**Causa:** El `PollViafirmaStatusJob` no se reagendó (crash del worker, despliegue, etc.).

> Desde el fix de 2026-08-19, el propio job se auto-repara en la mayoría de los
> casos: mutex ocupado → reintenta en 10s; excepción no controlada → hook
> `failed()` reprograma en 30s. El watchdog ahora es una red de seguridad
> secundaria, no la única vía de recuperación.

**Acciones:**
1. El watchdog (`ReviveStalledViafirmaPollsJob`) las re-arma automáticamente cada 5 min (ver `orphanedPolling(20)` — huecos de más de 20 min sin actualizarse)
2. Para forzar inmediato: `php artisan tinker --execute="App\Modules\Viafirma\Infrastructure\Jobs\ReviveStalledViafirmaPollsJob::dispatch();"`
3. Verificar que el worker de la cola **`viafirma-poll`** está activo: `sudo supervisorctl status matricerts-prod-worker-viafirma:*` (desde el fix de 2026-08-19, el polling ya NO corre en `default` — si el programa Supervisor dedicado no existe/está caído, los jobs se acumulan sin nadie que los procese)
4. Si el volumen de huérfanas es alto y persistente (no baja tras el watchdog), sospechar de contención de cola (ver `jobs` table: `SELECT queue, COUNT(*) FROM jobs GROUP BY queue;`) o caída sostenida del worker dedicado, no de un caso aislado.

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

> **`EXPIRED` ya no se dispara automáticamente por tiempo/intentos** (fix
> 2026-08-19). Solo llega a ese estado si Viafirma reporta explícitamente un
> fallo remoto terminal, o si un operador lo fuerza manualmente vía
> `StateMachine::markExpired()`. El polling continúa indefinidamente mientras
> el estado remoto siga en la familia `PROGRESSING`.
>
> **Captura del link KYC + notificación:** al entrar en cualquier estado de la
> familia `accreditation*` (bruto o sub-estados), se dispara
> `ViafirmaAccreditationReached` → `FetchKycAccreditationLinkJob` captura
> `kyc_accreditation_link` y envía un correo automático a `companies.email` de
> la empresa dueña de la solicitud (no al suscriptor final — ese ya lo recibe
> directo de Viafirma). Ver logs `viafirma.kyc_link_job.*`.
