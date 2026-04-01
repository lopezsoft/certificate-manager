<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Services\CertificateRequestService;
use App\Services\CertificateRequestMailService;
use App\Services\CertificateRequestFilesService;
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
    public function register()
    {
        // app()->usePublicPath(__DIR__.'/public'); // TODO: Enable this line when you are ready to deploy to production
        Passport::ignoreRoutes();

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

        $this->app->when(\App\Webhooks\Services\WebhookDispatcher::class)
            ->needs('$builders')
            ->give([
                new \App\Webhooks\Builders\CertificateCreatedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateStatusChangedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateAIProcessedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateFileUploadedPayloadBuilder(),
                new \App\Webhooks\Builders\CertificateDeletedPayloadBuilder(),
            ]);
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
