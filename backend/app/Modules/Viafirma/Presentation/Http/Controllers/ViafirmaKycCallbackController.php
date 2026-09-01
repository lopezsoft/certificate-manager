<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Controllers;

use App\Modules\Viafirma\Application\UseCases\RecordKycFlowCompletedUseCase;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
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
 * final: el que la empresa haya configurado en general_settings
 * (VIAFIRMA_KYC_REDIRECT_URL), o si no, el default global
 * (`viafirma.kyc.completed_path` + FRONTEND_URL).
 */
final class ViafirmaKycCallbackController extends Controller
{
    private const COMPANY_SETTING_KEY = 'VIAFIRMA_KYC_REDIRECT_URL';

    public function __construct(
        private readonly RecordKycFlowCompletedUseCase $useCase,
    ) {}

    public function __invoke(Request $request, string $publicId): RedirectResponse
    {
        $entity = $this->useCase->handle($publicId, $request->ip(), $request->userAgent());

        return redirect($this->resolveDestination($entity));
    }

    private function resolveDestination(?ViafirmaCertificateRequest $entity): string
    {
        $override = $this->resolveCompanyOverride($entity);
        if ($override !== null) {
            return $override;
        }

        return rtrim((string) config('app.frontend_url'), '/')
            . '/' . ltrim((string) config('viafirma.kyc.completed_path', ''), '/');
    }

    /**
     * Busca un override de URL de redirección configurado por la empresa en
     * general_settings/general_setting_companies (mismo patrón que
     * CheckSuppressedEmail::handle() para NOTIFICATIONEMAIL).
     */
    private function resolveCompanyOverride(?ViafirmaCertificateRequest $entity): ?string
    {
        $settings = collect($entity?->company?->settings ?? []);

        foreach ($settings as $companySetting) {
            if ($companySetting->setting?->key_value === self::COMPANY_SETTING_KEY
                && !empty($companySetting->value)
            ) {
                return $companySetting->value;
            }
        }

        return null;
    }
}
