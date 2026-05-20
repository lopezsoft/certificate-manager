<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\System\HealthCheckController as SystemHealthCheckController;

/**
 * @deprecated 2026-05-19 — usar {@see \App\Http\Controllers\System\HealthCheckController}.
 *
 * Esta clase se conserva como alias de retro-compatibilidad para que cualquier
 * referencia legacy siga funcionando. Será eliminada en la siguiente release.
 */
class HealthCheckController extends SystemHealthCheckController
{
}

