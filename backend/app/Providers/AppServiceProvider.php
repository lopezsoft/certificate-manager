<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Services\CertificateRequestService;
use App\Services\CertificateRequestMailService;
use App\Services\CertificateRequestFilesService;
use App\Services\CompanyService;
use App\Handlers\Certificate\CreateCertificateRequestHandler;
use App\Handlers\Certificate\UpdateCertificateRequestHandler;
use App\Handlers\Certificate\UpdateCertificateStatusHandler;
use App\Handlers\Certificate\DeleteCertificateRequestHandler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // app()->usePublicPath(__DIR__.'/public'); // TODO: Enable this line when you are ready to deploy to production
        // NOTA: usePublicPath se habilita solo en producción vía el script de deploy.
        Passport::ignoreRoutes();

        $this->app->singleton(CompanyService::class);

        $this->app->singleton(CertificateRequestService::class);
        $this->app->singleton(CertificateRequestMailService::class);
        $this->app->singleton(CertificateRequestFilesService::class);
        $this->app->singleton(CreateCertificateRequestHandler::class);
        $this->app->singleton(UpdateCertificateRequestHandler::class);
        $this->app->singleton(UpdateCertificateStatusHandler::class);
        $this->app->singleton(DeleteCertificateRequestHandler::class);

        $this->app->bind(
            \App\Webhooks\Contracts\WebhookRepositoryContract::class,
            \App\Webhooks\Repositories\WebhookEndpointRepository::class,
        );

        $this->app->bind(
            \App\Contracts\CertificateRequestRepositoryContract::class,
            \App\Repositories\EloquentCertificateRequestRepository::class,
        );

        $this->app->when(\App\Webhooks\Services\WebhookDispatcher::class)
            ->needs('$builders')
            ->give([
                new \App\Webhooks\Builders\CertificateCreatedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateStatusChangedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateAIProcessedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateFileUploadedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateDeletedPayloadBuilder(),
            ]);

        // ── Sprint 4: Bindings IA (Auto-detección de credenciales) ───────
        $this->app->bind(
            \App\Contracts\OcrServiceContract::class,
            fn () => ! empty(config('ai.google_vision.api_key'))
                ? new \App\Services\Ocr\GoogleVisionOcrService()
                : new \App\Services\Ocr\MockOcrService(),
        );

        $this->app->bind(
            \App\Contracts\AiAnalysisServiceContract::class,
            fn () => ! empty(config('ai.gemini.api_key'))
                ? new \App\Services\Ai\GeminiAnalysisService()
                : new \App\Services\Ai\MockAiAnalysisService(),
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Passport::tokensExpireIn(now()->addDays(config('tokens.expiration_days', 90)));
        Passport::refreshTokensExpireIn(now()->addDays(config('tokens.expiration_days', 90)));
        Passport::personalAccessTokensExpireIn(now()->addDays(config('tokens.expiration_days', 90)));
    }
}
