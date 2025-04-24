<?php

namespace App\Providers;

use App\Services\Mail\NotificationProcessor;
use App\Services\Mail\SNSMessageValidator;
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
        //
        // app()->usePublicPath(__DIR__.'/public'); // TODO: Enable this line when you are ready to deploy to production
        Passport::ignoreRoutes();
        $this->app->singleton(SNSMessageValidator::class, function () {
            return new SNSMessageValidator();
        });

        $this->app->singleton(NotificationProcessor::class, function () {
            return new NotificationProcessor();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
