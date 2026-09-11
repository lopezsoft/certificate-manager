# Implementación: Job de recordatorio diario KYC + Webhook n8n (WhatsApp)

> Estado: **IMPLEMENTADO Y VERIFICADO — v1.12.0 (2026-09-09)**. Preguntas de la sección 6 resueltas por el usuario (definición de "pendiente" confirmada, `companies.phone` confirmado como fuente, tope de recordatorio en 14 días). Probado end-to-end contra un webhook real de n8n: normalización de teléfono, agrupamiento de solicitudes simultáneas en un solo flush, y payload recibido exitosamente (`n8n.kyc_webhook.sent`).
>
> **Revisión 2026-09-10:** feedback post-producción — con varios códigos pendientes, la Casa de Software tenía que entrar al sistema para identificar a quién pertenecía cada uno. Se agregó `nombre` a cada solicitud del payload (`{codigo, enlace, nombre}`), resuelto vía `CertificateRequest::applicantDisplayName()` — FE-PN muestra el nombre del titular, FE-PJ agrega la empresa entre paréntesis. Ver `## [Unreleased]` en `docs/CHANGELOG.md`.

## 1. Contexto

Hoy, cuando una solicitud Viafirma entra al estado remoto `accreditation`, `FetchKycAccreditationLinkJob` captura el link de acreditación y envía **una sola vez** un correo a la empresa dueña de la solicitud (`ViafirmaAccreditationPendingNotification`, vía `notifyMasterCompany()` en `app/Modules/Viafirma/Infrastructure/Jobs/FetchKycAccreditationLinkJob.php:128`). Si la empresa no reenvía el link a su cliente final a tiempo, o el cliente lo pierde, no hay ningún recordatorio posterior — la solicitud queda esperando indefinidamente sin que nadie se lo recuerde a la empresa.

Se piden dos mejoras:

1. **Job diario (8-9 AM)** que revise las solicitudes con verificación KYC pendiente y **reenvíe el mismo correo**.
2. **Cada vez que se envíe ese correo** (tanto el envío inmediato original como cada recordatorio diario), **disparar un webhook a n8n** que notifica por WhatsApp (vía Evolution API) a la empresa — incluyendo el link de acreditación de cada solicitud en el mensaje, no solo el código.
3. **El envío inmediato no debe dispararse instantáneamente por cada solicitud individual:** si una misma empresa genera varias solicitudes casi al mismo tiempo, deben agruparse en un solo aviso de WhatsApp. Si solo hay una, igual se espera una ventana corta (por si llega otra) antes de enviarla sola.

## 2. Contrato del webhook (provisto por n8n — v2, con enlace por solicitud)

> Revisión 2026-09-09: se cambió `codigos` (array plano de strings) por `solicitudes` (array de objetos `{codigo, enlace}`), porque el mensaje de WhatsApp debe incluir el link de acreditación de cada solicitud, no solo su código.

```php
Http::post('https://TU_INSTANCIA_N8N.com/webhook/kyc-notification', [
    'casa_software' => 'OFILAFT',
    'whatsapp'      => '573001234567',
    'solicitudes'   => [
        ['codigo' => 'P386BY149', 'enlace' => 'https://maticert.viafirma.com/kyc/xxxx'],
        ['codigo' => 'MEFBND7DG', 'enlace' => 'https://maticert.viafirma.com/kyc/yyyy'],
    ],
    'count' => 2,
    'tipo'  => 'inmediato', // o 'recordatorio'
]);
```

| Campo | Tipo | Obligatorio | Notas |
|---|---|---|---|
| `casa_software` | string | Sí | Nombre a mostrar en el mensaje |
| `whatsapp` | string | Sí | Formato internacional sin `+` ni espacios (ej. `573001234567`) |
| `solicitudes` | array de `{codigo, enlace}` | Sí | Una o varias solicitudes pendientes, cada una con su código (`cod_request`) y su link de acreditación KYC |
| `count` | int | Sí | Debe coincidir con `count(solicitudes)` |
| `tipo` | string | Sí | Solo `"inmediato"` o `"recordatorio"` — cualquier otro valor cae en la rama `else` del flujo n8n |

**Reglas de disparo (actualizadas):**
- `inmediato`: **agrupado por ventana de espera corta, no por evento individual** — ver sección 3.3. Si varias solicitudes de la misma empresa se generan casi al mismo tiempo, se combinan en un solo `Http::post()`. Si solo hay una en la ventana, igual se espera esa ventana y se envía sola (nunca se dispara instantáneamente).
- `recordatorio`: **agrupado por empresa** — un solo `Http::post()` por empresa con el arreglo completo de solicitudes pendientes de esa empresa (ya es un batch diario, no necesita ventana de espera adicional).

El nodo "Responder a Laravel" contesta `200 OK` casi de inmediato, antes de que el WhatsApp salga realmente — el webhook es *fire-and-forget* desde nuestro lado. La confirmación real llega después, de forma asíncrona, a un endpoint nuestro (`/api/notificaciones/confirmar-envio`, fuera del alcance de este documento — se trata como una pieza aparte).

## 3. Diseño propuesto

### 3.1 Nuevo contrato + cliente del webhook
- `app/Modules/Viafirma/Domain/Contracts/KycWebhookNotifierContract.php`:
  ```php
  interface KycWebhookNotifierContract
  {
      /** @param array<int, array{codigo: string, enlace: string}> $solicitudes */
      public function notify(string $casaSoftware, ?string $whatsapp, array $solicitudes, string $tipo): void;
  }
  ```
- `app/Modules/Viafirma/Infrastructure/Http/N8nKycWebhookClient.php` — implementación real con `Http::post()`, timeout corto (10s), captura cualquier excepción/status no-2xx como `warning` en logs, **nunca** relanza (fire-and-forget, no debe romper el flujo que lo invoca).
- Config nueva: `config('services.n8n.kyc_webhook_url')` ← `N8N_KYC_WEBHOOK_URL` en `.env`. Sin configurar, el cliente no hace nada (silencioso) — permite desplegar el código antes de que n8n esté listo.
- Registrar el binding en `ViafirmaServiceProvider` (`$this->app->bind(KycWebhookNotifierContract::class, N8nKycWebhookClient::class)`), mismo patrón que `ViafirmaClient`.

### 3.2 Normalización del número de WhatsApp
`companies` no tiene un campo `whatsapp` dedicado — se reutilizaría `companies.phone`, que se captura manualmente y llega con formatos inconsistentes (espacios, guiones, puntos). La normalización debe **eliminar todo carácter que no sea dígito** (`preg_replace('/\D+/', '', $raw)`) antes de evaluar la longitud — esto cubre guiones, puntos, espacios y paréntesis por igual, sin necesidad de reglas específicas por separador.

**Casos de prueba a cubrir en el test unitario:**

| Entrada (`companies.phone`) | Dígitos extraídos | Resultado normalizado |
|---|---|---|
| `300-782.48.44` | `3007824844` | `573007824844` |
| `300 782 4844` | `3007824844` | `573007824844` |
| `(300) 782-4844` | `3007824844` | `573007824844` |
| `3007824844` | `3007824844` | `573007824844` |
| `573007824844` (ya con indicativo) | `573007824844` | `573007824844` (se deja igual, 12 dígitos) |

Si quedan 10 dígitos (celular colombiano local sin indicativo), se antepone `57`; si ya trae indicativo (12 dígitos), se deja igual. **Sigue siendo una asunción a validar para formatos fuera de Colombia** (sección 6).

### 3.3 Envío inmediato — agrupado por ventana de espera corta

**Problema a resolver:** si una empresa tiene varias solicitudes que llegan a `accreditation` casi al mismo tiempo (ej. procesamiento en lote), no se debe disparar un WhatsApp por cada una — deben combinarse en un solo mensaje. Y si solo llega una, tampoco se dispara instantáneamente: se espera una ventana corta (unos segundos) por si llega otra de la misma empresa antes de enviar.

**Patrón: buffer en caché + job de flush con delay, deduplicado por empresa.**

1. `FetchKycAccreditationLinkJob::notifyMasterCompany()` ya no llama al webhook directamente. En su lugar, tras enviar el correo, llama a un nuevo servicio `KycImmediateWebhookBatcher::enqueue()`:
   ```php
   $batcher->enqueue(
       companyId: $company->id,
       casaSoftware: $company->company_name ?? 'Empresa',
       whatsapp: $company->phone,
       codigo: $entity->cod_request,
       enlace: $link,
   );
   ```
2. `KycImmediateWebhookBatcher::enqueue()`:
   - Agrega `{codigo, enlace}` al buffer en caché de esa empresa: `Cache::put("kyc_webhook_batch_{$companyId}", [...acumulado...], now()->addMinutes(5))`.
   - Verifica un "lock" separado `kyc_webhook_batch_scheduled_{$companyId}`. Si **no** existe, lo marca (TTL = ventana + margen) y despacha `FlushKycImmediateWebhookJob::dispatch($companyId, $casaSoftware, $whatsapp)->delay(now()->addSeconds($window))`. Si **ya** existe (otro item de la misma empresa llegó hace instantes y ya programó el flush), no hace nada más — el flush pendiente recogerá también este nuevo item porque lee el buffer completo al momento de ejecutarse.
3. `FlushKycImmediateWebhookJob::handle()`:
   - Lee `Cache::get("kyc_webhook_batch_{$companyId}", [])`.
   - Si está vacío, no hace nada (caso borde).
   - Llama a `KycWebhookNotifierContract::notify(..., tipo: 'inmediato')` con **todas** las solicitudes acumuladas hasta ese momento.
   - Limpia el buffer y el lock (`Cache::forget` de ambas claves).

**Ventana de espera:** propuesta inicial de **20 segundos**, configurable vía `KYC_WEBHOOK_BATCH_WINDOW_SECONDS` — a confirmar (sección 6).

⚠️ **Requiere `CACHE_DRIVER` persistente entre workers** (`file`, `redis` o `database`) — con `array` el buffer no sobrevive entre el `enqueue()` y el `flush()` si corren en procesos distintos. Mismo requisito ya documentado para `MockViafirmaClient` en el módulo Viafirma.

### 3.4 Nuevo job: recordatorio diario
`app/Modules/Viafirma/Infrastructure/Jobs/ResendPendingKycAccreditationNotificationsJob.php`:

**Criterio de "pendiente"** (a confirmar, sección 6):
```php
ViafirmaCertificateRequest::query()
    ->whereHas('state', fn ($q) => $q
        ->where('internal_state', InternalState::POLLING->value)
        ->whereNotNull('kyc_accreditation_link')
        ->whereNull('kyc_flow_completed_at')   // aún no completó el flujo en el navegador
    )
    ->with(['state', 'certificateRequest.company'])
    ->get();
```

**Flujo:**
1. Agrupar los resultados por `company_id` (vía `certificateRequest.company`).
2. Por cada empresa:
   - Reenviar `ViafirmaAccreditationPendingNotification` **una vez por solicitud pendiente** (igual que el envío original — no se consolida el correo).
   - Acumular `{codigo: $entity->cod_request, enlace: $entity->state->kyc_accreditation_link}` de las solicitudes reenviadas exitosamente.
   - Al terminar el grupo, **un solo** `webhookNotifier->notify(..., tipo: 'recordatorio')` con el arreglo completo de `solicitudes` de esa empresa.
3. Fallos de envío de un correo individual no detienen el resto (mismo criterio defensivo usado en `notifyMasterCompany()`). No aplica ventana de espera aquí — ya es un batch diario por naturaleza.

### 3.5 Programación (Kernel)
Mismo patrón que los demás jobs diarios en `app/Console/Kernel.php`:
```php
$schedule->job(new ResendPendingKycAccreditationNotificationsJob())
    ->dailyAt('08:30')
    ->timezone('America/Bogota')
    ->name('viafirma:kyc-daily-reminder')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->emailOutputOnFailure(env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')))
    ->appendOutputTo(storage_path('logs/scheduled-viafirma-kyc-reminder.log'));
```
Cola: `notifications` (igual que los otros jobs de correo masivo).

## 4. Impacto en código existente

| Archivo | Cambio |
|---|---|
| `config/services.php` | + `n8n.kyc_webhook_url` |
| `app/Modules/Viafirma/Domain/Contracts/KycWebhookNotifierContract.php` | Nuevo |
| `app/Modules/Viafirma/Infrastructure/Http/N8nKycWebhookClient.php` | Nuevo |
| `app/Providers/ViafirmaServiceProvider.php` | + binding del contrato nuevo |
| `app/Modules/Viafirma/Infrastructure/Jobs/FetchKycAccreditationLinkJob.php` | Ya no llama al webhook directamente — llama a `KycImmediateWebhookBatcher::enqueue()` |
| `app/Modules/Viafirma/Application/Services/KycImmediateWebhookBatcher.php` | Nuevo — maneja el buffer en caché y programa el flush con delay |
| `app/Modules/Viafirma/Infrastructure/Jobs/FlushKycImmediateWebhookJob.php` | Nuevo — job con delay que lee el buffer acumulado y llama al webhook una sola vez |
| `app/Modules/Viafirma/Infrastructure/Jobs/ResendPendingKycAccreditationNotificationsJob.php` | Nuevo — job diario de recordatorio |
| `app/Console/Kernel.php` | + entrada de scheduler diaria |
| Tests nuevos (mockeados, sin BD) | `N8nKycWebhookClient`, `KycImmediateWebhookBatcher` (Cache fake/mock), `FlushKycImmediateWebhookJob`, el job de recordatorio (repositorio/consultas mockeadas) |

## 5. Manejo de errores

- El webhook nunca debe fallar el job/notificación que lo invoca — captura total de excepciones, solo `warning` en logs.
- Sin `N8N_KYC_WEBHOOK_URL` configurada, la función retorna inmediatamente sin error — despliegue seguro antes de tener la URL real de n8n.
- Fallos de envío de correo individual dentro del job de recordatorio no detienen el procesamiento de las demás solicitudes/empresas.

## 6. Preguntas abiertas — antes de implementar

1. **Definición de "pendiente":** ¿el criterio propuesto (`POLLING` + link ya generado + aún no completó el flujo en el navegador) es correcto, o debe incluir/excluir otros casos (ej. reenviar aunque ya haya completado el flujo pero Viafirma no confirme, dado el patrón de discrepancias visto con Cesar/Beni)?
2. **Fuente del número de WhatsApp:** ¿`companies.phone` realmente contiene el celular WhatsApp de contacto, o hay otro campo/tabla más apropiado? ¿Está garantizado el formato colombiano de 10 dígitos, o hay empresas con otros formatos/países?
3. **Límite de recordatorios:** ¿se reenvía todos los días indefinidamente mientras siga pendiente, o hay un tope (ej. máximo 3 recordatorios) antes de dejar de insistir?
4. **Duración de la ventana de agrupación del envío inmediato:** propuse 20 segundos como punto de partida — ¿es razonable, o debería ser mayor/menor? Afecta directamente cuánto se demora la Casa de Software en recibir el WhatsApp tras generarse el primer link del lote.
5. **Hora exacta:** ¿`08:30 America/Bogota` está bien, o se requiere una hora específica dentro del rango 8-9 AM?

## 7. Plan de rollout (una vez aprobado)

1. Confirmar respuestas de la sección 6.
2. Solicitar la URL real del webhook de n8n (`N8N_KYC_WEBHOOK_URL`) — probar primero contra `/webhook-test/kyc-notification` en modo test.
3. Implementar contrato + cliente + tests unitarios mockeados (sin BD, sin llamada HTTP real — usar `Http::fake()` o mock del contrato según corresponda).
4. Enganchar `tipo: inmediato` en `FetchKycAccreditationLinkJob`.
5. Implementar `ResendPendingKycAccreditationNotificationsJob` + su test.
6. Agregar entrada al scheduler en `Kernel.php`.
7. Probar en sandbox/staging con al menos una solicitud real pendiente antes de desplegar a producción.
8. Documentar en `CHANGELOG.md`.
