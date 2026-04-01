<?php

namespace App\Providers;

use App\Events\CertificateFileUploaded;
use App\Events\CertificateProcessedWithAI;
use App\Events\CertificateRequestCreated;
use App\Events\CertificateRequestDeleted;
use App\Events\CertificateStatusChanged;
use App\Listeners\HandleCertificateAIProcessing;
use App\Listeners\LogVerifiedUser;
use App\Listeners\SendPasswordResetEmail;
use App\Webhooks\Listeners\DispatchWebhookOnAIProcessed;
use App\Webhooks\Listeners\DispatchWebhookOnCertificateCreated;
use App\Webhooks\Listeners\DispatchWebhookOnCertificateDeleted;
use App\Webhooks\Listeners\DispatchWebhookOnFileUploaded;
use App\Webhooks\Listeners\DispatchWebhookOnStatusChanged;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Auth events
        Verified::class => [
            LogVerifiedUser::class,
        ],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        PasswordReset::class => [
            SendPasswordResetEmail::class,
        ],

        // Domain events → Webhook listeners
        CertificateRequestCreated::class => [
            DispatchWebhookOnCertificateCreated::class,
        ],
        CertificateStatusChanged::class => [
            DispatchWebhookOnStatusChanged::class,
        ],
        CertificateFileUploaded::class => [
            DispatchWebhookOnFileUploaded::class,
        ],
        CertificateRequestDeleted::class => [
            DispatchWebhookOnCertificateDeleted::class,
        ],

        // AI event → existing listener + webhook listener
        CertificateProcessedWithAI::class => [
            HandleCertificateAIProcessing::class,
            DispatchWebhookOnAIProcessed::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
