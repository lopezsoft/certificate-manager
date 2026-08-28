<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Controllers;

use App\Modules\Viafirma\Application\UseCases\RecordKycFlowCompletedUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Callback público (SIN autenticación) al que MetaMap redirige el navegador
 * del cliente tras completar el flujo de verificación de identidad KYC.
 *
 * GET /api/v1/viafirma/kyc-callback/{publicId}
 *
 * No requiere auth: lo invoca el navegador del suscriptor final, que nunca
 * tiene sesión en nuestro sistema. Registra la finalización del flujo
 * (señal de UX — ver RecordKycFlowCompletedUseCase) y reenvía al destino
 * final configurado en `viafirma.kyc.completed_path`.
 */
final class ViafirmaKycCallbackController extends Controller
{
    public function __construct(
        private readonly RecordKycFlowCompletedUseCase $useCase,
    ) {}

    public function __invoke(Request $request, string $publicId): RedirectResponse
    {
        $this->useCase->handle($publicId, $request->ip(), $request->userAgent());

        $destination = rtrim((string) config('app.frontend_url'), '/')
            . '/' . ltrim((string) config('viafirma.kyc.completed_path', ''), '/');

        return redirect($destination);
    }
}
