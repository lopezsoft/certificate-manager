<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
