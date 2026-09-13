# Implementación: correcciones del Sandbox reportadas por Posyma (12/09/2026)

> Estado: **DISEÑO — PENDIENTE DE APROBACIÓN**. No implementar hasta confirmar la sección 6.
>
> **Restricción rectora:** producción NO presenta ninguno de estos errores. Cada cambio se evalúa
> primero por su riesgo sobre el flujo productivo; se prefiere no tocar código compartido salvo
> que el beneficio sea claro y el cambio sea acotado y cubierto por tests.

## 1. Contexto

Posyma (Andrés Rodríguez, ROBOLAF S.A.S.) reportó 5 hallazgos probando la integración contra
nuestro Sandbox. Ninguno se reproduce en producción, porque **cuatro de los cinco viven en el
mock**, que solo existe cuando `VIAFIRMA_SANDBOX_MODE=true`.

### 1.1 Frontera de riesgo — qué es sandbox-only y qué es compartido

Verificado en `ViafirmaServiceProvider.php:118-123`:

```php
$this->app->bind(ViafirmaClient::class, function ($app) {
    if (config('viafirma.sandbox_mode', false)) {
        return $app->make(MockViafirmaClient::class);   // ← nunca en producción
    }
    return $app->make(GuzzleViafirmaClient::class);      // ← producción
});
```

| Hallazgo | Dónde vive | ¿Toca producción? |
|---|---|---|
| (b) P7B inválido → `ASSEMBLE_FAILED` | `MockViafirmaClient::downloadP7b()` | **No** — sandbox-only |
| (c) Enlace KYC 404 | `MockViafirmaClient::getAccreditationLink()` | **No** — sandbox-only |
| (d) Correo KYC duplicado | Reintento de `AutoIssueViafirmaJob` (¿?) | **Sí, a investigar** |
| (a) Latencia de 22 min entre sondeos | Infraestructura (Supervisor Sandbox) | **No** — no es código |
| (e) `uuid` ausente en el create | `CreateCertificateRequestHandler` | **Sí** — API compartida |
| (f) Bucle infinito de reensamblado | `AssembleP12Job` + watchdog | **Sí** — reproducible en prod |

> Sobre (f): el flag de sandbox **no lo cubre**. El bucle se dispara con cualquier P7B corrupto,
> venga del mock o de Viafirma. Ver sección 3 — se blinda por código, no por entorno.

## 2. (b) P7B inválido — sandbox-only, prioridad alta

**Causa exacta** (`MockViafirmaClient.php:138`):

```php
return base64_encode('MOCK_P7B_DATA_FOR_PUBLIC_ID_' . $publicId);
```

No es un PKCS#7; OpenSSL falla con `asn1 encoding routines::too long | bad object header`.
**El ensamblador está bien** — el mock nunca fue capaz de llevar el flujo hasta `ASSEMBLED`,
así que este camino jamás se probó de punta a punta en Sandbox.

**Propuesta:** que el mock genere un PKCS#7 real y autofirmado en tiempo de ejecución, con
OpenSSL, a partir del CSR/llave que la propia solicitud ya tiene en el vault. Requisitos:

- Debe pasar por `OpenSslCryptoService::assembleP12()` sin cambios en el ensamblador.
- Debe respetar la validación de identidad ya existente (`extractSubjectIdentity()` vs.
  `extractCsrSubjectIdentity()`), es decir, el certificado autofirmado debe emitirse **con el
  mismo subject del CSR**; de lo contrario el mock chocaría con la protección
  `IdentityMismatchException` que agregamos para el incidente de María Salazar.
- Cero impacto productivo: el archivo solo se carga con `VIAFIRMA_SANDBOX_MODE=true`.

**Riesgo:** bajo. Aislado al mock. El riesgo real es *no* hacerlo: hoy Sandbox no puede
completar una emisión, y cualquier integrador nuevo se topará con lo mismo.

## 3. (f) `ASSEMBLE_FAILED` recuperable — el bucle infinito (diagnóstico corregido)

> Corrige la versión inicial de este documento, que atribuía el bucle a la simple ausencia de un
> tope de reintentos. **Sí hay tope — y aun así se dio el bucle.** El mecanismo real es otro.

### 3.1 Por qué el límite de 5 no frenó nada

Existe un tope: `auto_redownload_attempts < 5`
(`ViafirmaCertificateRequestState::scopePendingAutoRedownload()`, línea 192). Debería haber
cortado en 5 intentos. Llegó a 2.252 porque **el contador se reinicia solo**:

1. `AutoRedownloadPendingViafirmaJob` (cada 5 min) toma los `FAILED_RECOVERABLE` cuyo
   `remote_status` tiene P7B disponible, hace `increment('auto_redownload_attempts')` y despacha
   `RetryAssembleP12Job`.
2. `RetryAssembleP12Job` (con sus propios `tries = 3`) llama a `RedownloadCertificateUseCase`,
   que vuelve a ensamblar.
3. El parseo del P7B inválido falla otra vez → el `catch (\Throwable)` de `AssembleP12Job`
   **reescribe** `internal_state = FAILED_RECOVERABLE` y hace `save()`.
4. Ese `save()` refresca `updated_at`, así que el registro **vuelve a calificar** para el scope en
   el siguiente ciclo. El tope de 5 nunca se alcanza porque el denominador se reinicia en cada
   pasada.

Multiplicado por los `tries = 3` de `RetryAssembleP12Job`, cada ciclo de 5 minutos genera varios
intentos. De ahí los 2.252.

### 3.2 Por qué el flag de sandbox no aplica aquí

Este bucle **no depende del mock**. Si Viafirma entrega un P7B corrupto en producción —
truncado por un fallo de red, mal generado en su lado — se dispara idéntico, y ahí no hay flag
que lo detenga. Aislar por entorno resuelve el síntoma de Posyma y deja intacta la causa.

### 3.0 DECISIÓN FINAL: no se implementa. Se deja como estaba.

> Lo que sigue en 3.3–3.5 describe una corrección que **fue implementada y luego revertida**.
> Se conserva el análisis porque el diagnóstico del bucle (3.1) es correcto y útil, pero la
> solución propuesta era **semánticamente equivocada**.

**Razón del descarte:** el modelo de estados ya expresa esta distinción, y la propuesta la
violaba. `FAILED_RECOVERABLE` significa *puede recuperarse*; `FAILED` significa *murió, sin
recuperación*. Un P7B corrupto **puede** serlo por una causa transitoria — una descarga truncada
por red — y en ese caso es recuperable por definición. Clasificarlo como `FAILED` sería usar el
estado para decir algo que no significa.

El error de razonamiento fue tratar «determinista respecto del contenido del bundle» como
equivalente a «irrecuperable». No lo es: el bundle puede volver a descargarse y llegar bien.
Lo determinista es reensamblar *ese mismo* bundle, no el trámite.

**Estado real del bucle:** sigue siendo posible en teoría. Nunca ha ocurrido en producción — sólo
en Sandbox, alimentado por el mock que devolvía un bundle imposible (corregido en §2). Si algún
día se materializa en producción, la vía correcta es arreglar el contador que se reinicia
(§3.1, punto 4), no reclasificar el estado.

### 3.3 La corrección NO lleva flag de entorno

**Decisión explícita: `AssembleP12Job` se comporta igual en Sandbox y en producción.**

Condicionar esta lógica al flag sería contraproducente. Sandbox sirve para una sola cosa:
predecir qué hará producción. Si un P7B corrupto se marcara terminal en Sandbox y siguiera
reintentándose 2.252 veces en producción, Sandbox estaría mintiendo — y el bug de producción
quedaría además invisible justo en el entorno donde debería detectarse. Un integrador
(Posyma) prueba en Sandbox precisamente para confiar en que producción responde igual.

**La corrección:** un `catch (CryptoException)` específico **antes** del `catch (\Throwable)`
genérico, tratado como terminal: `internal_state = FAILED`,
`last_error_code = 'ASSEMBLE_INVALID_BUNDLE'`, y `return` en lugar de `throw`. Es exactamente el
patrón que el propio archivo ya aplica a `IdentityMismatchException` (línea 335), con su
comentario ya escrito: *"Relanzar activaría reintentos de cola sin sentido (el P7B nunca va a
cambiar)"*. Un bundle malformado es determinista: reintentarlo no lo arregla nunca.

El `catch (\Throwable)` genérico queda **sin tocar**: los fallos transitorios (S3, vault, red)
siguen siendo `FAILED_RECOVERABLE` y se reintentan igual que hoy.

### 3.4 Dónde sí interviene el flag — y por qué no es "comportamiento distinto"

Conviene separar dos cosas que el reporte de Posyma mezcló:

| | Qué es | Flag |
|---|---|---|
| **Lógica de negocio** (`AssembleP12Job`) | Cómo clasificamos un error | **No.** Una sola, ambos entornos |
| **Frontera con el proveedor** (`ViafirmaClient`) | Quién nos entrega los datos | **Sí**, pero mismo contrato |

El `MockViafirmaClient` existe porque en Sandbox no hay un Viafirma real al otro lado. Sustituye
al **proveedor**, no a nuestro código. Hoy incumple el contrato: devuelve un string que no es un
PKCS#7, algo que el Viafirma real nunca devolvería.

Por eso, arreglar el mock (sección 2) **no introduce una diferencia entre entornos: la elimina.**
Hace que Sandbox se parezca más a producción, que es lo contrario de aislar. Y no requiere añadir
ningún `if (sandbox)` nuevo: el aislamiento ya existe por construcción en el binding del
`ViafirmaServiceProvider`.

**En resumen:** (f) y (b) no son dos capas de defensa contra el mismo riesgo. Son dos problemas
distintos —un bug de producción y un doble defectuoso— que aparecieron en el mismo reporte.

### 3.5 Alcance real del cambio — lo que hay que decidir

`CryptoException` no cubre solo el ASN.1 malformado. También cubre *"No se encontró un certificado
que corresponda a la llave privada"* (`OpenSslCryptoService.php:221`) y *"No se encontraron
certificados en el bundle P7B"* (línea 193). **Los tres son deterministas** con el mismo P7B, así
que terminal es la clasificación correcta para todos.

Pero es un **cambio de comportamiento real en producción**: hoy esos casos reintentan hasta 5
veces; con el cambio se detienen en el primero y quedan visibles al operador como `FAILED`.
Lo considero una mejora (falla rápido y visible en vez de girar en silencio), no una regresión
— pero es decisión tuya, no la doy por hecha.

**Riesgo:** acotado. Un `catch` nuevo antes del genérico; ninguna ruta existente cambia de
clasificación salvo las tres `CryptoException` deterministas listadas arriba. Con tests mockeados.

## 4. (c) Enlace KYC 404 — sandbox-only

`getAccreditationLink()` devuelve `https://sandbox.viafirma.com/accreditation/success?req=...`,
un host real con una ruta inexistente. En Sandbox nadie puede completar el KYC por navegador.

**Propuesta:** que el mock apunte a una página propia que simule el flujo y redirija al callback
(el mismo que Andrés tuvo que invocar a mano). Alternativa mínima: devolver directamente la URL
del callback, de modo que abrir el enlace complete el KYC simulado.

**Riesgo:** nulo en producción. Requiere decidir si se crea una página o basta con el callback
directo (ver pregunta 6.2).

## 5. (d) Correo KYC duplicado — DESCARTADO

Los identificadores difieren (`…38D8A` vs `…8574E`) porque `submitCsr()` genera uno nuevo con
`uniqid()` en cada llamada, así que en Sandbox hubo dos envíos para la misma solicitud.

**No se investiga como bug de producción.** Producción emite certificados todos los días: si
existiera una condición que duplica trámites, habría dado problemas visibles (cupo consumido
doble, trámites huérfanos en Viafirma) hace mucho tiempo. La evidencia operativa acumulada pesa
más que una inferencia sacada de un único caso en Sandbox.

Queda registrado por si algún día aparece el síntoma en producción; hasta entonces, sin acción.

## 6. (e) `uuid` ausente en el create — REPORTE INCORRECTO

**El `uuid` SÍ se devuelve.** Verificado contra la base real:

```
uuid en JSON? SI -> ff1dfff9-21a9-11f0-9c93-f02f74cac485
columna existe? SI
```

`CreateCertificateRequestHandler` devuelve el modelo completo en `dataRecords` (línea 164) y
`CertificateRequest` no define `$hidden`, así que todos los atributos cargados se serializan.

> **Nota sobre el error de análisis:** la versión inicial de este documento daba por bueno el
> reporte apoyándose en que `uuid` no aparece en `$fillable`. Eso fue un razonamiento equivocado:
> `$fillable` controla la asignación masiva, no la serialización. Debió verificarse antes de
> listarlo como pendiente.

Lo más probable es que el integrador consultara la documentación Swagger del endpoint —que sí
puede estar incompleta— en vez de la respuesta real. **Acción sugerida:** revisar el bloque
`@OA\Response` del create, no el código.

Contexto de por qué importa el campo: los endpoints de descarga y revocación resuelven por
`certificate_requests.uuid`, no por el `id` numérico
(`CertificateIssuanceController::download()`, línea 157). Sin el uuid, un integrador desatendido
no podría descargar el certificado que acaba de solicitar.

## 7. Fuera de alcance de este documento

- **(a) Latencia de polling.** No es código: es configuración de Supervisor en Sandbox
  (probablemente falta el worker dedicado de `viafirma-poll`, como pasó en producción).
  Se verifica al hacer deploy al Sandbox. **Decisión tomada: no se toca aquí.**

## 8. Estado final

| # | Hallazgo | Resolución | Alcance |
|---|---|---|---|
| 1 | **(b)** P7B inválido en el mock | ✅ Implementado | Solo el mock |
| 2 | **(c)** Enlace KYC 404 | ✅ Implementado | Solo el mock |
| 3 | **(f)** Bucle infinito de reensamblado | ❌ Descartado (§3.0) | Producción sin cambios |
| 4 | **(d)** Trámite duplicado | ❌ Descartado (§5) | — |
| 5 | **(e)** `uuid` ausente | ❌ Reporte incorrecto (§6) | Revisar Swagger |
| 6 | **(a)** Latencia de polling | ⏳ Al desplegar | Supervisor Sandbox |

**No se modifica ni una línea de código productivo.** Los únicos cambios viven en el mock
(inalcanzable en producción por el binding del `ViafirmaServiceProvider`) y en una clave de
config nueva que sólo el mock lee.

### 8.1 Archivos

**Nuevos**

- `tests/Unit/Modules/Viafirma/Infrastructure/Http/MockViafirmaClientP7bTest.php`

**Modificados**

- `app/Modules/Viafirma/Infrastructure/Http/MockViafirmaClient.php` — sandbox-only
- `config/viafirma.php` — clave `mock_cert_validity_days`, leída sólo por el mock

### 8.2 Verificación

- Flujo completo end-to-end: CSR → mock → `assembleP12` → P12 abierto con PIN, subject de la CSR
  preservado, cadena CA presente en `extracerts`.
- Tests: 15/15 en verde (mock + crypto), sin tocar BD.
- **Regresión:** el único fallo restante en `AssembleP12Test` (diferencia de mayúscula en el
  mensaje esperado) es preexistente. Se comparó con el baseline haciendo `git stash`: idéntico
  con y sin los cambios. Los demás fallos de la suite son de archivos con BOM UTF-8, ajenos a esto.

### 8.3 Al desplegar

1. `php artisan config:cache` — hay una clave nueva (`viafirma.mock_cert_validity_days`).
2. Revisar el Supervisor del Sandbox para (a) — worker dedicado de `viafirma-poll`.

No hace falta `queue:restart` por estos cambios: ningún job cambió. (Sigue haciendo falta por
otros despliegues pendientes, como el de `QuotaService`.)
